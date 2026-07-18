#!/usr/bin/env bash
# Atualização LESTBET (git pull + migrate + cache).
# Instalado em /usr/local/bin/lestbet-update.sh pelo vps-enable-git-updates.sh.
# Pode ser usado para revisar/atualizar o script sem rerodar o setup completo:
#   sudo install -m 755 scripts/lestbet-update.sh /usr/local/bin/lestbet-update.sh
#
set -euo pipefail

APP_ROOT="${APP_ROOT:-/var/www/lestbet}"
GIT_BRANCH="${GIT_BRANCH:-main}"
LOG_FILE="${LOG_FILE:-/var/log/lestbet-update.log}"
WEB_USER="${WEB_USER:-www-data}"
SKIP_COMPOSER="${SKIP_COMPOSER:-0}"

exec > >(tee -a "${LOG_FILE}") 2>&1

echo "==== $(date -Is) lestbet-update start ===="
echo "APP_ROOT=${APP_ROOT} branch=${GIT_BRANCH}"

cd "${APP_ROOT}"

if [[ ! -d .git ]]; then
  echo "ERRO: ${APP_ROOT} não é um repositório Git. Rode scripts/vps-enable-git-updates.sh."
  exit 1
fi

resolve_laravel_root() {
  if [[ -f artisan ]]; then
    echo "${APP_ROOT}"
  elif [[ -f platform/artisan ]]; then
    echo "${APP_ROOT}/platform"
  else
    echo ""
  fi
}

LARAVEL_ROOT="$(resolve_laravel_root)"
if [[ -z "${LARAVEL_ROOT}" ]]; then
  echo "ERRO: artisan não encontrado em ${APP_ROOT} nem em ${APP_ROOT}/platform"
  exit 1
fi
echo "LARAVEL_ROOT=${LARAVEL_ROOT}"

echo "==> git fetch + reset --hard origin/${GIT_BRANCH}"
git fetch --prune origin
git checkout "${GIT_BRANCH}"
git reset --hard "origin/${GIT_BRANCH}"

cd "${LARAVEL_ROOT}"

if [[ "${SKIP_COMPOSER}" != "1" ]] && [[ -f composer.lock ]]; then
  if command -v composer >/dev/null 2>&1; then
    echo "==> composer install --no-dev -o"
    composer install --no-dev --optimize-autoloader --no-interaction
  else
    echo "AVISO: composer não encontrado; pulando."
  fi
fi

echo "==> php artisan migrate --force"
php artisan migrate --force

echo "==> caches"
php artisan optimize:clear
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

echo "==> permissões storage/bootstrap/cache"
chown -R "${WEB_USER}:${WEB_USER}" storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || true

if [[ -f VERSION ]]; then
  echo "VERSION=$(tr -d '[:space:]' < VERSION)"
fi
echo "==== $(date -Is) lestbet-update ok ===="
