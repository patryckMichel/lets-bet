#!/usr/bin/env bash
# Deploy Laravel platform (LESTBET) na VPS
set -euo pipefail

DOMAIN="${DOMAIN:-lestber369.com}"
APP_ROOT="${APP_ROOT:-/var/www/lestbet}"
PHP_VERSION="${PHP_VERSION:-}"

export DEBIAN_FRONTEND=noninteractive

echo "==> [1/7] Pacotes (Nginx + PHP + Composer)"
apt-get update -y
apt-get install -y nginx unzip curl git ca-certificates

# Detecta PHP disponivel no Ubuntu (26.04 pode ter 8.5/8.4, nao 8.3)
detect_php_version() {
  if [[ -n "${PHP_VERSION}" ]]; then
    echo "${PHP_VERSION}"
    return
  fi
  for v in 8.5 8.4 8.3 8.2 8.1; do
    if apt-cache show "php${v}-fpm" >/dev/null 2>&1; then
      echo "$v"
      return
    fi
  done
  # meta-pacote sem versao
  if apt-cache show php-fpm >/dev/null 2>&1; then
    echo "meta"
    return
  fi
  echo ""
}

PHP_VERSION="$(detect_php_version)"
if [[ -z "${PHP_VERSION}" ]]; then
  echo "ERRO: nenhum pacote PHP encontrado no apt. Adicione o PPA ondrej/php."
  exit 1
fi

echo "PHP detectado: ${PHP_VERSION}"

if [[ "${PHP_VERSION}" == "meta" ]]; then
  apt-get install -y php-fpm php-cli php-pgsql php-mbstring php-xml php-curl \
    php-zip php-bcmath php-gd php-intl php-sqlite3
  PHP_BIN="php"
  # descobre versao real do socket
  PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
else
  apt-get install -y \
    "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-cli" "php${PHP_VERSION}-pgsql" \
    "php${PHP_VERSION}-mbstring" "php${PHP_VERSION}-xml" "php${PHP_VERSION}-curl" \
    "php${PHP_VERSION}-zip" "php${PHP_VERSION}-bcmath" "php${PHP_VERSION}-gd" \
    "php${PHP_VERSION}-intl" "php${PHP_VERSION}-sqlite3"
  PHP_BIN="php${PHP_VERSION}"
  if ! command -v "${PHP_BIN}" >/dev/null 2>&1; then
    PHP_BIN="php"
  fi
fi

command -v php >/dev/null 2>&1 || ln -sf "/usr/bin/${PHP_BIN}" /usr/local/bin/php || true
php -v

if ! command -v composer >/dev/null 2>&1; then
  curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
  php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
  rm -f /tmp/composer-setup.php
fi
composer --version

echo "==> [2/7] App em ${APP_ROOT}"
mkdir -p "${APP_ROOT}"
cd "${APP_ROOT}"

if [[ ! -f artisan ]]; then
  echo "ERRO: artisan nao encontrado em ${APP_ROOT}. Envie o codigo antes."
  exit 1
fi

if [[ ! -f .env ]]; then
  if [[ -f .env.production ]]; then
    cp .env.production .env
  elif [[ -f .env.example ]]; then
    cp .env.example .env
  else
    echo "ERRO: sem .env / .env.example"
    exit 1
  fi
fi

sed -i "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" .env || true
grep -q '^APP_ENV=' .env && sed -i 's/^APP_ENV=.*/APP_ENV=production/' .env || echo 'APP_ENV=production' >> .env
grep -q '^APP_DEBUG=' .env && sed -i 's/^APP_DEBUG=.*/APP_DEBUG=false/' .env || echo 'APP_DEBUG=false' >> .env
grep -q '^SESSION_DRIVER=' .env && sed -i 's/^SESSION_DRIVER=.*/SESSION_DRIVER=file/' .env || echo 'SESSION_DRIVER=file' >> .env
grep -q '^CACHE_STORE=' .env && sed -i 's/^CACHE_STORE=.*/CACHE_STORE=file/' .env || echo 'CACHE_STORE=file' >> .env
grep -q '^SESSION_DOMAIN=' .env && sed -i 's/^SESSION_DOMAIN=.*/SESSION_DOMAIN=/' .env || true
grep -q '^SESSION_SECURE_COOKIE=' .env && sed -i 's/^SESSION_SECURE_COOKIE=.*/SESSION_SECURE_COOKIE=true/' .env || echo 'SESSION_SECURE_COOKIE=true' >> .env

echo "==> [3/7] Composer install"
composer install --no-dev --optimize-autoloader --no-interaction

if ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
fi

echo "==> [4/7] Permissoes + migrate"
mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache
php artisan migrate --force
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache || true

php artisan tinker --execute="\\App\\Models\\User::where('email','patryck.michel@gmail.com')->update(['is_admin'=>true]);" || true

echo "==> [5/7] Nginx"
SOCK="/run/php/php${PHP_VERSION}-fpm.sock"
if [[ ! -S "$SOCK" ]]; then
  SOCK=$(ls /run/php/php*-fpm.sock 2>/dev/null | head -n1 || true)
fi
if [[ -z "$SOCK" ]]; then
  echo "ERRO: socket PHP-FPM nao encontrado em /run/php/"
  ls -la /run/php/ || true
  exit 1
fi
echo "Usando socket: ${SOCK}"

cat >/etc/nginx/sites-available/lestbet <<EOF
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name ${DOMAIN} www.${DOMAIN} _;
    root ${APP_ROOT}/public;
    index index.php;

    client_max_body_size 32M;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \\.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${SOCK};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\\.(?!well-known).* {
        deny all;
    }
}
EOF

ln -sfn /etc/nginx/sites-available/lestbet /etc/nginx/sites-enabled/lestbet
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl enable --now "php${PHP_VERSION}-fpm" 2>/dev/null || systemctl enable --now php-fpm 2>/dev/null || true
systemctl enable --now nginx
systemctl reload nginx

echo "==> [6/7] HTTPS (opcional)"
apt-get install -y certbot python3-certbot-nginx || true
certbot --nginx -d "${DOMAIN}" -d "www.${DOMAIN}" --non-interactive --agree-tos -m "admin@${DOMAIN}" --redirect || true

echo "==> [7/7] Pronto"
echo "App: ${APP_ROOT}"
echo "PHP: ${PHP_VERSION}"
echo "Teste IP: http://$(hostname -I | awk '{print $1}')"
echo "URL: http://${DOMAIN}"
echo "Admin: http://${DOMAIN}/admin"
