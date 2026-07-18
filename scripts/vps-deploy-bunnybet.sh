#!/usr/bin/env bash
# Wipe Goldsvet (and old landing/leads stack) and deploy BunnyBet (Node + React)
# Domain: https://lestber369.com
#
# Expects app source already at APP_ROOT (uploaded via scripts/upload-bunnybet-vps.ps1)
# or set CLONE_UPSTREAM=1 to clone github then patch.

set -euo pipefail

DOMAIN="${DOMAIN:-lestber369.com}"
APP_ROOT="${APP_ROOT:-/var/www/bunnybet}"
DB_NAME="${DB_NAME:-bunnybet}"
DB_USER="${DB_USER:-bunnybet}"
DB_PASS="${DB_PASS:-}"
APP_PORT="${APP_PORT:-1111}"
PM2_NAME="${PM2_NAME:-bunnybet}"
GOLDSVET_ROOT="${GOLDSVET_ROOT:-/var/www/goldsvet}"
LANDING_ROOT="${LANDING_ROOT:-/var/www/landing}"
WIPE_OLD="${WIPE_OLD:-1}"
CLONE_UPSTREAM="${CLONE_UPSTREAM:-0}"
REPO_URL="${REPO_URL:-https://github.com/oanapopescu93/casino.git}"

export DEBIAN_FRONTEND=noninteractive

echo "==> [1/9] Packages (Node 20, Nginx, MariaDB, PM2)"
apt-get update -y
apt-get install -y nginx mariadb-server curl ca-certificates gnupg git rsync

if ! command -v node >/dev/null 2>&1 || [[ "$(node -v | sed 's/v//' | cut -d. -f1)" -lt 18 ]]; then
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
  apt-get install -y nodejs
fi
npm install -g pm2 >/dev/null 2>&1 || true
systemctl enable --now mariadb nginx

echo "==> [2/9] Stop / remove old stacks (Goldsvet, landing, leads)"
if [[ "${WIPE_OLD}" == "1" ]]; then
  pm2 delete all 2>/dev/null || true
  pm2 save --force 2>/dev/null || true

  systemctl stop php8.2-fpm 2>/dev/null || true
  systemctl disable php8.2-fpm 2>/dev/null || true

  # Old nginx site names used in previous deploys
  rm -f /etc/nginx/sites-enabled/goldsvet /etc/nginx/sites-enabled/lestber* \
        /etc/nginx/sites-enabled/default /etc/nginx/sites-enabled/landing \
        /etc/nginx/sites-enabled/bunnybet 2>/dev/null || true
  rm -f /etc/nginx/sites-available/goldsvet /etc/nginx/sites-available/lestber* \
        /etc/nginx/sites-available/landing 2>/dev/null || true

  if [[ -d "${GOLDSVET_ROOT}" ]]; then
    echo "Removing ${GOLDSVET_ROOT}"
    rm -rf "${GOLDSVET_ROOT}"
  fi
  if [[ -d "${LANDING_ROOT}" ]]; then
    echo "Removing ${LANDING_ROOT}"
    rm -rf "${LANDING_ROOT}"
  fi

  # Keep DB dump optional? Drop goldsvet DB (data loss intentional)
  mysql -e "DROP DATABASE IF EXISTS goldsvet;" 2>/dev/null || true
  mysql -e "DROP USER IF EXISTS 'goldsvet'@'localhost';" 2>/dev/null || true
fi

echo "==> [3/9] Database ${DB_NAME}"
if [[ -z "${DB_PASS}" ]]; then
  DB_PASS="$(openssl rand -base64 18 | tr -d '/+=' | head -c 20)"
fi
mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost'; FLUSH PRIVILEGES;"

echo "==> [4/9] App source at ${APP_ROOT}"
mkdir -p "$(dirname "${APP_ROOT}")"
if [[ "${CLONE_UPSTREAM}" == "1" ]]; then
  rm -rf "${APP_ROOT}"
  git clone --depth 1 "${REPO_URL}" "${APP_ROOT}"
fi

if [[ ! -d "${APP_ROOT}/server" ]] || [[ ! -d "${APP_ROOT}/client" ]]; then
  echo "ERROR: ${APP_ROOT} incompleto. Faça upload do codigo local primeiro (upload-bunnybet-vps.ps1)"
  exit 1
fi

# Ensure production env
cat > "${APP_ROOT}/server/.env.production" <<EOF
PORT=${APP_PORT}
BASE_URL=https://${DOMAIN}
DB_HOST=127.0.0.1
DB_USER=${DB_USER}
DB_PASSWORD=${DB_PASS}
DB_NAME=${DB_NAME}
EOF

# Schema + demo user (demo@lestbet.local / test123)
if [[ -f "${APP_ROOT}/sql/001_local_setup.sql" ]]; then
  # Strip CREATE DATABASE / USE so we apply into ${DB_NAME}
  grep -v -E '^(CREATE DATABASE|USE )' "${APP_ROOT}/sql/001_local_setup.sql" | mysql "${DB_NAME}"
else
  echo "ERROR: sql/001_local_setup.sql missing"
  exit 1
fi
if [[ -f "${APP_ROOT}/sql/002_seed_demo_user.sql" ]]; then
  mysql "${DB_NAME}" < "${APP_ROOT}/sql/002_seed_demo_user.sql" || true
fi

echo "==> [5/9] npm install + build React"
cd "${APP_ROOT}"
npm install --omit=dev
cd "${APP_ROOT}/client"
# Same-origin socket via nginx (do not set REACT_APP_SOCKET_URL)
rm -f .env .env.local .env.production.local 2>/dev/null || true
npm install
CI=false npm run build
cd "${APP_ROOT}"

echo "==> [6/9] Nginx reverse proxy + WebSocket"
cat > /etc/nginx/sites-available/bunnybet <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN} www.${DOMAIN};

    client_max_body_size 50M;

    location / {
        proxy_pass http://127.0.0.1:${APP_PORT};
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_read_timeout 86400;
    }
}
EOF
ln -sfn /etc/nginx/sites-available/bunnybet /etc/nginx/sites-enabled/bunnybet
nginx -t
systemctl reload nginx

echo "==> [7/9] TLS (certbot se disponivel)"
if command -v certbot >/dev/null 2>&1; then
  certbot --nginx -d "${DOMAIN}" -d "www.${DOMAIN}" --non-interactive --agree-tos \
    -m "admin@${DOMAIN}" --redirect || true
else
  apt-get install -y certbot python3-certbot-nginx || true
  certbot --nginx -d "${DOMAIN}" -d "www.${DOMAIN}" --non-interactive --agree-tos \
    -m "admin@${DOMAIN}" --redirect || true
fi

echo "==> [8/9] PM2 start"
cd "${APP_ROOT}"
# production node env for dotenv
pm2 delete "${PM2_NAME}" 2>/dev/null || true
NODE_ENV=production pm2 start server/index.js --name "${PM2_NAME}" --cwd "${APP_ROOT}" \
  --update-env --env production
# Ensure NODE_ENV is set on restart
pm2 set "${PM2_NAME}" || true
# Wrap with env file explicitly via ecosystem
cat > "${APP_ROOT}/ecosystem.config.cjs" <<EOF
module.exports = {
  apps: [{
    name: '${PM2_NAME}',
    cwd: '${APP_ROOT}',
    script: 'server/index.js',
    env: {
      NODE_ENV: 'production',
      PORT: '${APP_PORT}',
      DB_HOST: '127.0.0.1',
      DB_USER: '${DB_USER}',
      DB_PASSWORD: '${DB_PASS}',
      DB_NAME: '${DB_NAME}',
      BASE_URL: 'https://${DOMAIN}'
    }
  }]
}
EOF
pm2 delete "${PM2_NAME}" 2>/dev/null || true
pm2 start "${APP_ROOT}/ecosystem.config.cjs"
pm2 save
pm2 startup systemd -u root --hp /root >/dev/null 2>&1 || true

echo "==> [9/9] Health check"
sleep 2
curl -fsS -o /dev/null -w "HTTP %{http_code}\n" "http://127.0.0.1:${APP_PORT}/" || true
pm2 status

echo ""
echo "============================================"
echo " BunnyBet no ar"
echo " URL:     https://${DOMAIN}"
echo " Login:   demo@lestbet.local / test123"
echo " App:     ${APP_ROOT}"
echo " DB:      ${DB_NAME} / user ${DB_USER}"
echo " Pass DB: ${DB_PASS}"
echo " Guarde a senha do banco acima."
echo "============================================"
