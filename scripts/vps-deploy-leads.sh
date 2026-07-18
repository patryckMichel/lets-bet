#!/usr/bin/env bash
set -euo pipefail
export PATH="/usr/local/go/bin:$PATH"

APP_DIR="/opt/bet"
DOMAIN="lestber369.com"
DEST="/var/www/lestbet"

cd "$APP_DIR"

mkdir -p "$DEST" bin
cp -a "$APP_DIR/landing"/. "$DEST"/

# Build leads API
go build -o bin/leads ./cmd/leads

cat >/etc/systemd/system/bet-leads.service <<'EOF'
[Unit]
Description=LESTBET Landing Leads API
After=network.target

[Service]
WorkingDirectory=/opt/bet
Environment=LEADS_ADDR=127.0.0.1:8089
Environment=LEADS_DATABASE_URL=postgres://postgres:postgres@217.196.60.187:32769/bet369?sslmode=disable
Environment=LEADS_ADMIN_TOKEN=LestBet369Admin
ExecStart=/opt/bet/bin/leads
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable --now bet-leads
systemctl restart bet-leads
sleep 1
systemctl --no-pager --full status bet-leads | head -n 20 || true

# Ensure nginx proxies leads + admin APIs
python3 - <<'PY'
from pathlib import Path
p = Path('/etc/nginx/sites-available/bet')
text = p.read_text() if p.exists() else ''

def ensure_block(needle, block, text):
    if needle in text:
        print(f'nginx already has {needle}')
        return text
    if 'location /api/' in text:
        return text.replace('location /api/', block + '\n    location /api/', 1)
    if 'root /var/www/lestbet' in text:
        return text.replace('root /var/www/lestbet;', 'root /var/www/lestbet;\n' + block, 1)
    print(f'WARN: could not insert {needle}')
    return text

leads_block = '''
    location /api/leads {
        proxy_pass http://127.0.0.1:8089;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
'''

admin_block = '''
    location /api/admin/ {
        proxy_pass http://127.0.0.1:8089;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
'''

text = ensure_block('location /api/leads', leads_block, text)
# migrate old exact match if present
text = text.replace('location = /api/leads {', 'location /api/leads {')
text = ensure_block('location /api/admin/', admin_block, text)
# multipage static (no SPA fallback to login)
text = text.replace('try_files $uri $uri/ /index.html;', 'try_files $uri $uri/ =404;')
p.write_text(text)
print('nginx patched')
PY

# Also ensure static landing root and healthz/proxy when file is minimal
if ! grep -q 'root /var/www/lestbet' /etc/nginx/sites-available/bet 2>/dev/null; then
  echo "WARNING: nginx root for landing missing; run vps-deploy-landing.sh first if needed"
fi

nginx -t
systemctl reload nginx

echo "=== leads health ==="
curl -sS http://127.0.0.1:8089/healthz || true
echo
echo "LEADS_DEPLOY_OK"
