#!/bin/sh
set -e

log() {
    echo "[dev-entrypoint] $*"
}

APP_DIR="/var/www/html"
STORAGE_DIR="$APP_DIR/storage"
UPLOADS_DIR="$APP_DIR/public/uploads"

# Garantir permissões de escrita em storage e uploads
mkdir -p "$STORAGE_DIR/ratelimit" "$STORAGE_DIR/cep_cache" "$UPLOADS_DIR"
chown -R www-data:www-data "$STORAGE_DIR" "$UPLOADS_DIR" 2>/dev/null || true
chmod -R 755 "$STORAGE_DIR" "$UPLOADS_DIR" 2>/dev/null || true

# Database initialization (idempotent - safe to run on every start)
if [ -x "/usr/local/bin/db-init.sh" ]; then
    log "[db-init] Iniciando inicialização do banco de dados..."
    sh /usr/local/bin/db-init.sh
    log "[db-init] Inicialização do banco concluída."
else
    log "[db-init] db-init.sh não encontrado — pulando etapa de BD."
fi

# Garantir que vendor/ existe
if [ ! -d "$APP_DIR/vendor" ]; then
    echo "[dev-entrypoint] vendor/ ausente — executando composer install..."
    cd "$APP_DIR"
    composer install --no-interaction --no-dev 2>&1 | sed 's/^/[composer] /'
fi

# Se public/assets/ nao existir, avisar que o frontend precisa build
if [ ! -d "$APP_DIR/public/assets" ]; then
    echo "[dev-entrypoint] Aviso: public/assets/ ausente."
    echo "[dev-entrypoint] Rode no host: npm install && npm run dev   (mod dev)"
    echo "[dev-entrypoint] ou:           npm install && npm run build (build pronto)"
fi

# Remover PID obsoleto do Apache se existir (apos restart)
rm -f /var/run/apache2/apache2.pid 2>/dev/null || true

# Iniciar Apache em foreground como www-data
exec apache2-foreground
