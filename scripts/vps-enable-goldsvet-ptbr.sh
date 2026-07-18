#!/usr/bin/env bash
# Ativa idioma pt-br no Goldsvet (arquivos já devem estar em resources/lang/pt-br)
set -euo pipefail
APP="/var/www/goldsvet/casino"
DOMAIN="${DOMAIN:-lestber369.com}"

if [[ ! -f "$APP/resources/lang/pt-br/app.php" ]]; then
  echo "ERROR: pt-br/app.php missing. Upload lang files first."
  exit 1
fi

# Locale padrão
cd "$APP"
if [[ -f .env ]]; then
  if grep -q '^APP_LOCALE=' .env; then
    sed -i 's/^APP_LOCALE=.*/APP_LOCALE=pt-br/' .env
  else
    echo 'APP_LOCALE=pt-br' >> .env
  fi
fi

# Idioma do usuário admin (+ demais)
mysql -N goldsvet -e "UPDATE w_users SET language='pt-br' WHERE username='admin' OR language='en' OR language='' OR language IS NULL;" 2>/dev/null \
  || mysql -N -e "UPDATE goldsvet.w_users SET language='pt-br';" || true

php artisan config:clear
php artisan cache:clear
php artisan view:clear

echo "PT-BR_OK — recarregue https://${DOMAIN}/backend (Ctrl+F5)"
echo "Login admin continua: admin / admin"
