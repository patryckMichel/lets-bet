#!/usr/bin/env bash
set -euo pipefail

DOMAIN="lestber369.com"
APP_DIR="/opt/bet"
GATEWAY_PORT="8080"

export PATH="/usr/local/go/bin:/usr/local/bin:$PATH"

cd "$APP_DIR"

echo "==> [1/8] Installing Go if missing"
if ! command -v go >/dev/null 2>&1; then
  curl -fsSL https://go.dev/dl/go1.26.5.linux-amd64.tar.gz -o /tmp/go.tar.gz
  rm -rf /usr/local/go
  tar -C /usr/local -xzf /tmp/go.tar.gz
  echo 'export PATH=/usr/local/go/bin:$PATH' >/etc/profile.d/go.sh
  export PATH="/usr/local/go/bin:$PATH"
fi
go version

echo "==> [2/8] Docker infra"
docker compose up -d
docker compose ps

echo "==> [3/8] Configure .env"
if [[ ! -f .env ]]; then
  cp .env.example .env
fi
sed -i 's/^ENVIRONMENT=.*/ENVIRONMENT=production/' .env
sed -i "s|^CORS_ALLOWED_ORIGINS=.*|CORS_ALLOWED_ORIGINS=https://${DOMAIN},http://${DOMAIN},https://www.${DOMAIN}|" .env
# strip inline comments that break some parsers
sed -i 's/\s\+#.*$//' .env

echo "==> [4/8] Build services"
mkdir -p bin
go build -o bin/migrate ./cmd/migrate
go build -o bin/gateway ./cmd/gateway
go build -o bin/wallet ./cmd/wallet
go build -o bin/engine ./cmd/engine
go build -o bin/settlement ./cmd/settlement
go build -o bin/games ./cmd/games

echo "==> [5/8] Migrations"
./bin/migrate -dir ./migrations -action up

echo "==> [6/8] systemd units"
cat >/etc/systemd/system/bet-gateway.service <<EOF
[Unit]
Description=Bet Gateway
After=network.target docker.service
Requires=docker.service

[Service]
WorkingDirectory=${APP_DIR}
ExecStart=${APP_DIR}/bin/gateway
Restart=always
RestartSec=3
Environment=PORT=${GATEWAY_PORT}

[Install]
WantedBy=multi-user.target
EOF

for svc in wallet engine settlement games; do
  cat >/etc/systemd/system/bet-${svc}.service <<EOF
[Unit]
Description=Bet ${svc}
After=network.target docker.service bet-gateway.service
Requires=docker.service

[Service]
WorkingDirectory=${APP_DIR}
ExecStart=${APP_DIR}/bin/${svc}
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF
done

systemctl daemon-reload
systemctl enable --now bet-gateway bet-wallet bet-engine bet-settlement bet-games
sleep 2
systemctl --no-pager --full status bet-gateway | head -n 20 || true

echo "==> [7/8] Nginx reverse proxy"
cat >/etc/nginx/sites-available/bet <<EOF
server {
    listen 80;
    server_name ${DOMAIN} www.${DOMAIN};

    location / {
        proxy_pass http://127.0.0.1:${GATEWAY_PORT};
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
    }
}
EOF
ln -sfn /etc/nginx/sites-available/bet /etc/nginx/sites-enabled/bet
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

echo "==> [8/8] Firewall + SSL"
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable || true
# close db/cache from public if open
ufw deny 5432/tcp || true
ufw deny 6379/tcp || true
ufw deny 4222/tcp || true
ufw deny 8080/tcp || true

if command -v certbot >/dev/null 2>&1; then
  certbot --nginx -d "${DOMAIN}" -d "www.${DOMAIN}" --non-interactive --agree-tos -m admin@${DOMAIN} --redirect || \
    echo "Certbot skipped/failed (DNS may still be propagating). Retry later."
fi

echo "==> Health checks"
curl -sS "http://127.0.0.1:${GATEWAY_PORT}/healthz" || true
echo
curl -sS -I "http://127.0.0.1/healthz" | head -n 10 || true
echo
echo "DONE. Test: curl -sS https://${DOMAIN}/healthz"
