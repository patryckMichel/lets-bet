#!/usr/bin/env bash
set -euo pipefail

SRC="/opt/bet/landing"
DEST="/var/www/lestbet"
DOMAIN="lestber369.com"

mkdir -p "$DEST"
cp -a "$SRC"/. "$DEST"/

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

    # VIP form + phone check
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

    location /metrics {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host \$host;
    }

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

nginx -t
systemctl reload nginx
echo "LANDING_OK"
curl -sS -o /dev/null -w "%{http_code}\n" -H "Host: ${DOMAIN}" http://127.0.0.1/ || true
curl -sS http://127.0.0.1:8080/healthz || true
echo
