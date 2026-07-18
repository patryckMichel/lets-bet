#!/usr/bin/env bash
# Fix: nginx was proxying ALL traffic to Go gateway → "404 page not found"
# Restore static landing as site root; keep API proxies specific.
set -euo pipefail

DOMAIN="${DOMAIN:-lestber369.com}"
DEST="/var/www/lestbet"
SRC="/opt/bet/landing"

mkdir -p "$DEST"
if [[ -d "$SRC" ]]; then
  cp -a "$SRC"/. "$DEST"/
fi

if [[ ! -f "$DEST/index.html" ]]; then
  echo "ERROR: $DEST/index.html missing. Upload landing to $SRC first."
  exit 1
fi

# Prefer leads API when present; otherwise gateway still healthz
cat >/etc/nginx/sites-available/bet <<EOF
server {
    server_name ${DOMAIN} www.${DOMAIN};

    root ${DEST};
    index index.html;

    location = /healthz {
        proxy_pass http://127.0.0.1:8080/healthz;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }

    # Leads (VIP form / phone check / admin list)
    location /api/leads {
        proxy_pass http://127.0.0.1:8089;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }

    location /api/admin/ {
        proxy_pass http://127.0.0.1:8089;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }

    # Go gateway APIs (not the whole site)
    location /api/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }

    location /ws/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }

    # Static multipage site (login at /, VIP at /acessovip, admin at /admin)
    location / {
        try_files \$uri \$uri/ =404;
    }

    listen 443 ssl; # managed by Certbot
    ssl_certificate /etc/letsencrypt/live/${DOMAIN}/fullchain.pem; # managed by Certbot
    ssl_certificate_key /etc/letsencrypt/live/${DOMAIN}/privkey.pem; # managed by Certbot
    include /etc/letsencrypt/options-ssl-nginx.conf; # managed by Certbot
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem; # managed by Certbot
}

server {
    if (\$host = www.${DOMAIN}) {
        return 301 https://\$host\$request_uri;
    } # managed by Certbot

    if (\$host = ${DOMAIN}) {
        return 301 https://\$host\$request_uri;
    } # managed by Certbot

    listen 80;
    server_name ${DOMAIN} www.${DOMAIN};
    return 404; # managed by Certbot
}
EOF

ln -sfn /etc/nginx/sites-available/bet /etc/nginx/sites-enabled/bet
rm -f /etc/nginx/sites-enabled/default || true

# Show what was broken before (best effort)
echo "== current enabled sites =="
ls -la /etc/nginx/sites-enabled/ || true
echo "== nginx test =="
nginx -t
systemctl reload nginx

echo "== checks =="
curl -sS -o /dev/null -w "ROOT:%{http_code}\n" -k "https://127.0.0.1/" -H "Host: ${DOMAIN}" || true
curl -sS -k "https://127.0.0.1/healthz" -H "Host: ${DOMAIN}" || true
echo
head -n 3 "$DEST/index.html" || true
echo "FIX_OK"
