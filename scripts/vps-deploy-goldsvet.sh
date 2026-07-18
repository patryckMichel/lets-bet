#!/usr/bin/env bash
# Deploy Goldsvet Premium (betplatform-dev/goldsvetpremium) on the VPS.
# Target: https://lestber369.com  |  Admin: https://lestber369.com/backend
# Note: /back is only static AdminLTE assets (403 if opened as folder).
set -euo pipefail

DOMAIN="${DOMAIN:-lestber369.com}"
APP_ROOT="${APP_ROOT:-/var/www/goldsvet}"
DB_NAME="${DB_NAME:-goldsvet}"
DB_USER="${DB_USER:-goldsvet}"
DB_PASS="${DB_PASS:-}"
REPO_URL="${REPO_URL:-https://github.com/betplatform-dev/goldsvetpremium.git}"
PHP_VER="${PHP_VER:-8.2}"

export DEBIAN_FRONTEND=noninteractive

echo "==> [1/10] Installing packages (PHP ${PHP_VER}, MariaDB, Nginx, Node, Composer deps)"
apt-get update -y

# PHP 8.2 may need Ondrej PPA on older Ubuntu images
if ! apt-cache show "php${PHP_VER}-fpm" >/dev/null 2>&1; then
  add-apt-repository -y ppa:ondrej/php || true
  apt-get update -y
fi

apt-get install -y \
  software-properties-common curl ca-certificates gnupg unzip git \
  nginx mariadb-server \
  "php${PHP_VER}-fpm" "php${PHP_VER}-cli" "php${PHP_VER}-mysql" "php${PHP_VER}-xml" \
  "php${PHP_VER}-mbstring" "php${PHP_VER}-curl" "php${PHP_VER}-zip" "php${PHP_VER}-gd" \
  "php${PHP_VER}-bcmath" "php${PHP_VER}-intl" "php${PHP_VER}-tokenizer" "php${PHP_VER}-fileinfo" \
  "php${PHP_VER}-redis" || true

# Fallback: detect installed php-fpm version
if [[ ! -S "/run/php/php${PHP_VER}-fpm.sock" ]]; then
  DETECTED="$(ls /run/php/php*-fpm.sock 2>/dev/null | head -n1 | sed -E 's|.*/php([0-9.]+)-fpm.sock|\1|' || true)"
  if [[ -n "${DETECTED}" ]]; then
    echo "NOTE: using detected PHP ${DETECTED}"
    PHP_VER="${DETECTED}"
  fi
fi

if ! command -v composer >/dev/null 2>&1; then
  curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

if ! command -v node >/dev/null 2>&1; then
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
  apt-get install -y nodejs
fi

npm install -g pm2 >/dev/null 2>&1 || true

systemctl enable --now mariadb nginx "php${PHP_VER}-fpm"

echo "==> [2/10] Database"
if [[ -z "${DB_PASS}" ]]; then
  DB_PASS="$(openssl rand -base64 18 | tr -d '/+=' | head -c 20)"
fi
mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost'; FLUSH PRIVILEGES;"

echo "==> [3/10] Clone / update app at ${APP_ROOT}"
mkdir -p "$(dirname "${APP_ROOT}")"
if [[ ! -d "${APP_ROOT}/.git" ]]; then
  rm -rf "${APP_ROOT}"
  git clone --depth 1 "${REPO_URL}" "${APP_ROOT}"
else
  git -C "${APP_ROOT}" fetch --depth 1 origin
  git -C "${APP_ROOT}" reset --hard origin/main || git -C "${APP_ROOT}" reset --hard origin/master
fi

if [[ ! -f "${APP_ROOT}/v105.sql" ]]; then
  echo "ERROR: v105.sql missing in repo"
  exit 1
fi

TABLE_COUNT="$(mysql -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}';")"
if [[ "${TABLE_COUNT}" == "0" ]]; then
  echo "==> Importing v105.sql (first install)"
  mysql "${DB_NAME}" < "${APP_ROOT}/v105.sql"
else
  echo "==> DB already has ${TABLE_COUNT} tables — skip SQL import"
fi

echo "==> [4/10] Laravel .env"
cd "${APP_ROOT}/casino"
if [[ ! -f .env ]]; then
  cp .env.example .env
fi

APP_KEY_LINE="$(grep -E '^APP_KEY=' .env || true)"
sed -i "s|^APP_NAME=.*|APP_NAME=LESTBET369|" .env
sed -i "s|^APP_ENV=.*|APP_ENV=production|" .env
sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|" .env
sed -i "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" .env
sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" .env
sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" .env
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|" .env
sed -i "s|^SESSION_SECURE_COOKIE=.*|SESSION_SECURE_COOKIE=true|" .env || true

echo "==> [5/10] composer install + keys"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction
if [[ -z "${APP_KEY_LINE#APP_KEY=}" ]] || [[ "${APP_KEY_LINE}" == "APP_KEY=" ]]; then
  php artisan key:generate --force
fi
php artisan jwt:secret --force || true
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear || true

chown -R www-data:www-data "${APP_ROOT}"
chmod -R ug+rwx "${APP_ROOT}/casino/storage" "${APP_ROOT}/casino/bootstrap/cache" "${APP_ROOT}/storage" || true

echo "==> [6/10] Socket configs (domain + SSL via nginx proxy recommended paths)"
# Direct TLS on WS ports using Let's Encrypt material when available
CERT_DIR="/etc/letsencrypt/live/${DOMAIN}"
mkdir -p "${APP_ROOT}/casino/PTWebSocket/ssl"
if [[ -f "${CERT_DIR}/fullchain.pem" && -f "${CERT_DIR}/privkey.pem" ]]; then
  cp -f "${CERT_DIR}/fullchain.pem" "${APP_ROOT}/casino/PTWebSocket/ssl/cert.pem"
  cp -f "${CERT_DIR}/privkey.pem" "${APP_ROOT}/casino/PTWebSocket/ssl/key.pem"
  SSL_JSON_TRUE=true
  PREFIX="https://"
  PREFIX_WS="wss://"
else
  SSL_JSON_TRUE=false
  PREFIX="http://"
  PREFIX_WS="ws://"
fi

cat >"${APP_ROOT}/socket_config.json" <<EOF
{
  "port": "22154/slots",
  "host": "${DOMAIN}",
  "prefix": "${PREFIX}",
  "host_ws": "${DOMAIN}",
  "prefix_ws": "${PREFIX_WS}",
  "ssl": ${SSL_JSON_TRUE}
}
EOF

cat >"${APP_ROOT}/socket_config2.json" <<EOF
{
  "port": 22197,
  "host": "${DOMAIN}",
  "prefix": "${PREFIX}",
  "host_ws": "${DOMAIN}",
  "prefix_ws": "${PREFIX_WS}",
  "ssl": ${SSL_JSON_TRUE}
}
EOF

cat >"${APP_ROOT}/arcade_config.json" <<EOF
{
  "port": "22188/arcade",
  "host": "${DOMAIN}",
  "prefix": "${PREFIX}",
  "host_ws": "${DOMAIN}",
  "prefix_ws": "${PREFIX_WS}",
  "ssl": ${SSL_JSON_TRUE},
  "timezone": "America/Sao_Paulo"
}
EOF

echo "==> [7/10] Node WebSocket deps + PM2"
cd "${APP_ROOT}/casino/PTWebSocket"
npm install --omit=dev
pm2 delete casino-slots casino-server casino-arcade >/dev/null 2>&1 || true
pm2 start Slots.js --name casino-slots
pm2 start Server.js --name casino-server
pm2 start Arcade.js --name casino-arcade
pm2 save
pm2 startup systemd -u root --hp /root >/dev/null 2>&1 || true

echo "==> [8/10] Firewall ports for game sockets"
if command -v ufw >/dev/null 2>&1; then
  ufw allow 22154/tcp || true
  ufw allow 22197/tcp || true
  ufw allow 22188/tcp || true
fi

echo "==> [9/10] Nginx site (Goldsvet as main site, VIP landing at /vip/)"
FPM_SOCK="/run/php/php${PHP_VER}-fpm.sock"
if [[ ! -S "${FPM_SOCK}" ]]; then
  # fallback common paths
  FPM_SOCK="$(ls /run/php/php*-fpm.sock 2>/dev/null | head -n1 || true)"
fi

LOCATION_CORE=$(cat <<'LOC'
    root APP_ROOT_PLACEHOLDER;
    index index.php index.html;
    client_max_body_size 64M;

    # /back is AdminLTE assets folder (403 if opened alone). Real admin = /backend
    location = /back { return 301 /backend/login; }
    location = /back/ { return 301 /backend/login; }

    location /vip/ {
        alias /var/www/lestbet/;
        try_files $uri $uri/ /vip/index.html;
    }

    location /api/leads {
        proxy_pass http://127.0.0.1:8089;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:FPM_SOCK_PLACEHOLDER;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff2?|ttf|eot|map|mp3|wav|json)$ {
        expires 7d;
        access_log off;
        try_files $uri =404;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
LOC
)
LOCATION_CORE="${LOCATION_CORE//APP_ROOT_PLACEHOLDER/${APP_ROOT}}"
LOCATION_CORE="${LOCATION_CORE//FPM_SOCK_PLACEHOLDER/${FPM_SOCK}}"

if [[ -f "/etc/letsencrypt/live/${DOMAIN}/fullchain.pem" ]]; then
  cat >/etc/nginx/sites-available/bet <<EOF
server {
    listen 80;
    server_name ${DOMAIN} www.${DOMAIN};
    return 301 https://\$host\$request_uri;
}

server {
    listen 443 ssl http2;
    server_name ${DOMAIN} www.${DOMAIN};
    ssl_certificate /etc/letsencrypt/live/${DOMAIN}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/${DOMAIN}/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;
${LOCATION_CORE}
}
EOF
else
  cat >/etc/nginx/sites-available/bet <<EOF
server {
    listen 80;
    server_name ${DOMAIN} www.${DOMAIN};
${LOCATION_CORE}
}
EOF
fi

ln -sfn /etc/nginx/sites-available/bet /etc/nginx/sites-enabled/bet
rm -f /etc/nginx/sites-enabled/default || true
nginx -t
systemctl reload nginx

echo "==> [10/10] Sanity checks"
php -v | head -n1
mysql -N -e "SELECT COUNT(*) AS tables_in_db FROM information_schema.tables WHERE table_schema='${DB_NAME}';"
pm2 ls || true
curl -sS -o /dev/null -w "HTTP_LOCAL:%{http_code}\n" -k "https://127.0.0.1/" -H "Host: ${DOMAIN}" || true
curl -sS -o /dev/null -w "HTTP_BACKEND:%{http_code}\n" -k "https://127.0.0.1/backend/login" -H "Host: ${DOMAIN}" || true

CREDS_FILE="/root/goldsvet-credentials.txt"
cat >"${CREDS_FILE}" <<EOF
DOMAIN=https://${DOMAIN}
ADMIN=https://${DOMAIN}/backend/login
ADMIN_USER=admin
ADMIN_PASS=password
AGENT_USER=agent
USER_USER=user1
DEFAULT_PASS=password
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASS=${DB_PASS}
APP_ROOT=${APP_ROOT}

IMPORTANT:
1) Change all default passwords immediately after login.
2) Admin URL is /backend (not /back). /back is asset folder only.
3) Game asset packs are NOT in the GitHub repo.
   Contact Telegram @Supergoaladmi for slot/arcade game files.
4) Without game packs, lobby/admin will load but many games stay empty.
EOF

chmod 600 "${CREDS_FILE}"
echo
echo "============================================"
echo " GOLDSVET DEPLOY DONE"
echo " Site:  https://${DOMAIN}"
echo " Admin: https://${DOMAIN}/backend/login"
echo " Login: admin / password  (CHANGE NOW)"
echo " Creds: ${CREDS_FILE}"
echo " Games: NOT included in OSS — need Telegram pack"
echo "============================================"
