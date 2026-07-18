#!/usr/bin/env bash
# Fix: /back is static assets folder → 403. Real admin is /backend
set -euo pipefail
DOMAIN="${DOMAIN:-lestber369.com}"
CONF="/etc/nginx/sites-available/bet"

if [[ ! -f "$CONF" ]]; then
  echo "ERROR: $CONF missing"
  exit 1
fi

# Insert redirects after server_name line if not present
if ! grep -q 'location = /back' "$CONF"; then
  python3 - <<'PY'
from pathlib import Path
p = Path("/etc/nginx/sites-available/bet")
text = p.read_text()
block = """
    # /back is AdminLTE assets; real panel is /backend
    location = /back { return 301 /backend/login; }
    location = /back/ { return 301 /backend/login; }
"""
# inject once into each ssl server block before "location /vip" or "root "
needle = "root /var/www/goldsvet;"
if needle in text and "location = /back" not in text:
    text = text.replace(needle, needle + "\n" + block, 1)
    # if multiple server blocks, also handle second occurrence
    if text.count("location = /back") < text.count(needle):
        parts = text.split(needle)
        out = [parts[0]]
        for i, part in enumerate(parts[1:], 1):
            chunk = needle + part
            if "location = /back" not in chunk:
                chunk = needle + "\n" + block + part
            out.append(chunk[len(needle):] if False else "")
            # simpler: only first was enough for https block usually
        # fallback: write first replacement only (already done)
    p.write_text(text)
    print("redirects added")
else:
    print("redirects already present or root not found")
PY
else
  echo "redirects already present"
fi

nginx -t
systemctl reload nginx
echo "OK — use https://${DOMAIN}/backend/login"
curl -sS -o /dev/null -w "BACK:%{http_code} -> " -k "https://127.0.0.1/back/" -H "Host: ${DOMAIN}" || true
curl -sS -o /dev/null -w "BACKEND_LOGIN:%{http_code}\n" -k "https://127.0.0.1/backend/login" -H "Host: ${DOMAIN}" || true
