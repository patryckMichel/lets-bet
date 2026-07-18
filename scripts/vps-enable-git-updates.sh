#!/usr/bin/env bash
# One-time setup: torna /var/www/lestbet um clone Git e habilita update pelo admin.
#
# Uso (na VPS como root):
#   curl -fsSL ... | bash
#   # ou copie este arquivo e rode:
#   bash scripts/vps-enable-git-updates.sh
#
# O que faz:
#   1. Instala /usr/local/bin/lestbet-update.sh (git pull + migrate + cache)
#   2. sudoers: www-data pode rodar esse script sem senha
#   3. Opcional: converte APP_ROOT em clone se ainda não for repositório Git
#
# Variáveis:
#   APP_ROOT=/var/www/lestbet
#   GIT_REPO=https://github.com/patryckMichel/lets-bet.git
#   GIT_BRANCH=main
#   CONVERT_TO_GIT=1   # 0 = só instala script/sudoers (app já é clone)
#   SKIP_COMPOSER=0
#
set -euo pipefail

APP_ROOT="${APP_ROOT:-/var/www/lestbet}"
GIT_REPO="${GIT_REPO:-https://github.com/patryckMichel/lets-bet.git}"
GIT_BRANCH="${GIT_BRANCH:-main}"
CONVERT_TO_GIT="${CONVERT_TO_GIT:-1}"
SKIP_COMPOSER="${SKIP_COMPOSER:-0}"
UPDATE_BIN="/usr/local/bin/lestbet-update.sh"
SUDOERS_FILE="/etc/sudoers.d/lestbet-update"
LOG_FILE="/var/log/lestbet-update.log"
WEB_USER="${WEB_USER:-www-data}"

if [[ "$(id -u)" -ne 0 ]]; then
  echo "ERRO: rode como root."
  exit 1
fi

command -v git >/dev/null 2>&1 || { apt-get update -y && apt-get install -y git; }

echo "==> Instalando ${UPDATE_BIN}"
cat > "${UPDATE_BIN}" <<'UPDATE_EOF'
#!/usr/bin/env bash
# Atualização disparada pelo admin Laravel (sudo -n /usr/local/bin/lestbet-update.sh)
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
  echo "ERRO: ${APP_ROOT} não é um repositório Git. Rode vps-enable-git-updates.sh com CONVERT_TO_GIT=1."
  exit 1
fi

# Resolve onde está o Laravel (raiz do clone ou pasta platform/)
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
UPDATE_EOF

chmod 755 "${UPDATE_BIN}"
touch "${LOG_FILE}"
chmod 644 "${LOG_FILE}"

echo "==> sudoers ${SUDOERS_FILE}"
cat > "${SUDOERS_FILE}" <<EOF
# Allow ${WEB_USER} to run LESTBET git update without password (admin panel)
${WEB_USER} ALL=(root) NOPASSWD: ${UPDATE_BIN}
EOF
chmod 440 "${SUDOERS_FILE}"
visudo -cf "${SUDOERS_FILE}"

if [[ "${CONVERT_TO_GIT}" == "1" ]]; then
  echo "==> Convertendo ${APP_ROOT} em clone Git (se necessário)"
  if [[ -d "${APP_ROOT}/.git" ]]; then
    echo "Já é repositório Git."
    cd "${APP_ROOT}"
    git remote set-url origin "${GIT_REPO}" 2>/dev/null || git remote add origin "${GIT_REPO}"
    git fetch --prune origin || {
      echo "AVISO: git fetch falhou. Configure deploy key ou token HTTPS read-only."
    }
  else
    STAMP="$(date +%Y%m%d%H%M%S)"
    BACKUP="${APP_ROOT}.pre-git-${STAMP}"
    CLONE_TMP="${APP_ROOT}.git-clone-${STAMP}"

    echo "Backup: ${BACKUP}"
    if [[ -d "${APP_ROOT}" ]]; then
      mv "${APP_ROOT}" "${BACKUP}"
    fi

    echo "Clonando ${GIT_REPO} -> ${CLONE_TMP}"
    git clone --branch "${GIT_BRANCH}" --single-branch "${GIT_REPO}" "${CLONE_TMP}"

    # Preferência alinhada ao deploy atual: Laravel na raiz de APP_ROOT.
    # Se o repo for monorepo, copia platform/ para APP_ROOT e inicializa git
    # apontando para o mesmo remote (sparse: só platform no working tree via clone+move).
    if [[ -f "${CLONE_TMP}/artisan" ]]; then
      mv "${CLONE_TMP}" "${APP_ROOT}"
    elif [[ -f "${CLONE_TMP}/platform/artisan" ]]; then
      # Mantém clone monorepo intacto em APP_ROOT; update script resolve platform/.
      mv "${CLONE_TMP}" "${APP_ROOT}"
      echo "Monorepo detectado. APP_ROOT=${APP_ROOT} (Laravel em platform/)."
      echo "Ajuste nginx root para ${APP_ROOT}/platform/public se necessário."
    else
      echo "ERRO: clone sem artisan."
      [[ -d "${BACKUP}" ]] && mv "${BACKUP}" "${APP_ROOT}"
      exit 1
    fi

    # Restaura .env, storage e vendor do backup
    if [[ -d "${BACKUP}" ]]; then
      LARAVEL_NEW="${APP_ROOT}"
      [[ -f "${APP_ROOT}/platform/artisan" ]] && LARAVEL_NEW="${APP_ROOT}/platform"

      LARAVEL_OLD="${BACKUP}"
      if [[ -f "${BACKUP}/artisan" ]]; then
        LARAVEL_OLD="${BACKUP}"
      elif [[ -f "${BACKUP}/platform/artisan" ]]; then
        LARAVEL_OLD="${BACKUP}/platform"
      fi

      if [[ -f "${LARAVEL_OLD}/.env" ]]; then
        cp -a "${LARAVEL_OLD}/.env" "${LARAVEL_NEW}/.env"
        echo "Restaurou .env"
      fi
      if [[ -d "${LARAVEL_OLD}/storage" ]]; then
        mkdir -p "${LARAVEL_NEW}/storage"
        if command -v rsync >/dev/null 2>&1; then
          rsync -a "${LARAVEL_OLD}/storage/" "${LARAVEL_NEW}/storage/"
        else
          cp -a "${LARAVEL_OLD}/storage/." "${LARAVEL_NEW}/storage/"
        fi
        echo "Restaurou storage"
      fi
      if [[ -d "${LARAVEL_OLD}/vendor" ]] && [[ ! -d "${LARAVEL_NEW}/vendor" ]]; then
        cp -a "${LARAVEL_OLD}/vendor" "${LARAVEL_NEW}/vendor"
        echo "Restaurou vendor"
      fi
    fi

    chown -R "${WEB_USER}:${WEB_USER}" "${APP_ROOT}"
  fi
else
  echo "CONVERT_TO_GIT=0 — pulando conversão; certifique-se de que ${APP_ROOT} já é clone."
fi

# Garante env vars no .env do Laravel
LARAVEL_ENV="${APP_ROOT}/.env"
[[ -f "${APP_ROOT}/platform/.env" ]] && LARAVEL_ENV="${APP_ROOT}/platform/.env"
if [[ -f "${LARAVEL_ENV}" ]]; then
  echo "==> Atualizando chaves Git no .env (${LARAVEL_ENV})"
  ensure_env() {
    local key="$1" val="$2" file="$3"
    if grep -q "^${key}=" "${file}"; then
      sed -i "s|^${key}=.*|${key}=${val}|" "${file}"
    else
      printf '\n%s=%s\n' "${key}" "${val}" >> "${file}"
    fi
  }
  ensure_env "GITHUB_REPO" "patryckMichel/lets-bet" "${LARAVEL_ENV}"
  ensure_env "GITHUB_BRANCH" "${GIT_BRANCH}" "${LARAVEL_ENV}"
  ensure_env "GITHUB_VERSION_PATH" "platform/VERSION" "${LARAVEL_ENV}"
  ensure_env "UPDATE_SCRIPT_PATH" "${UPDATE_BIN}" "${LARAVEL_ENV}"
fi

echo ""
echo "OK. Próximos passos:"
echo "  1. Se o repo for privado, configure deploy key ou token em git credential / GITHUB_TOKEN no .env"
echo "  2. Teste: sudo -u ${WEB_USER} sudo -n ${UPDATE_BIN}"
echo "  3. Abra /admin/atualizacao no painel"
echo "  4. Backup antigo (se houve conversão) permanece em ${APP_ROOT}.pre-git-*"
echo ""
