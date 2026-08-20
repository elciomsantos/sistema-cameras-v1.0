#!/bin/sh
set -e

# =====================================================
# Database Initialization Script for Docker Deployment
# =====================================================
# This script ensures the database and required tables exist.
# It is idempotent and can be run on container start or during build.
#
# Usage (in Dockerfile ENTRYPOINT or CMD):
#   ["sh", "docker/db-init.sh"]
#   ou no docker-compose: command: ["sh", "docker/db-init.sh"]
# =====================================================

# Configuration (can be overridden by environment variables)
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
DB_NAME="${DB_NAME:-cftv_gml}"
CHARSET="${CHARSET:-utf8mb4}"

# Color output (optional)
red='\033[0;31m'
green='\033[0;32m'
yellow='\033[1;33m'
nc='\033[0m' # No Color

log() {
    echo -e "${green}[DB_INIT]${nc} $1"
}

warn() {
    echo -e "${yellow}[DB_INIT]${nc} WARNING: $1"
}

error() {
    echo -e "${red}[DB_INIT]${nc} ERROR: $1"
}

# Wait for MySQL to be ready
wait_for_mysql() {
    log "Aguardando MySQL ficar disponível em $DB_HOST:$DB_PORT..."
    for i in $(seq 1 60); do
        if mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" -e "SELECT 1" > /dev/null 2>&1; then
            log "MySQL está disponível."
            return 0
        fi
        if [ "$i" -eq 60 ]; then
            error "MySQL não ficou disponível após 60 segundos."
            exit 1
        fi
        sleep 1
    done
}

# Create database and user if not exist
setup_database() {
    log "Configurando banco de dados: $DB_NAME"

    # Create database if not exists
    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" -e "
        CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET $CHARSET COLLATE $CHARSET_unicode_ci;
        USE \`$DB_NAME\`;
    " 2>&1 || error "Falha ao criar banco de dados."

    # Create tables from schema file if they don't exist
    # We look for the schema file relative to the script location
    SCHEMA_FILE="config/DB/cftv_gml.sql"

    if [ -f "$SCHEMA_FILE" ]; then
        log "Aplicando schema de $SCHEMA_FILE..."
        mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$SCHEMA_FILE" 2>&1 | head -20
        log "Schema aplicado com sucesso."
    else
        warn "Arquivo de schema nao encontrado: $SCHEMA_FILE"
        warn "Procurando em caminhos alternativos..."
        # Try common locations
        for alt in "./config/DB/cftv_gml.sql" "/var/www/html/config/DB/cftv_gml.sql"; do
            if [ -f "$alt" ]; then
                SCHEMA_FILE="$alt"
                log "Schema encontrado em: $SCHEMA_FILE"
                mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$SCHEMA_FILE" 2>&1 | head -20
                log "Schema aplicado com sucesso."
                break
            fi
        done
    fi

    # Apply development seed (only in dev environment)
    if [ "${CAMERAS_ENV:-development}" = "development" ]; then
        SEED_FILE="config/DB/private/seed_dev.sql"
        if [ -f "$SEED_FILE" ]; then
            log "Aplicando seed de desenvolvimento..."
            mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$SEED_FILE" 2>&1 | head -20
            log "Seed de desenvolvimento aplicado."
        fi
    fi

    # Verify core tables exist
    log "Verificando tabelas essenciais..."
    local tables_ok=true
    for table in niveis_acesso secretarias usuarios; do
        local count=$(mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT COUNT(*) FROM $table;" 2>/dev/null | tail -1)
        if [ -z "$count" ] || [ "$count" = "0" ] 2>/dev/null; then
            warn "Tabela $table pode nao estar populada corretamente."
            tables_ok=false
        else
            log "Tabela $table: $count registro(s) encontrado(s)."
        fi
    done

    if [ "$tables_ok" = true ]; then
        log "Todas as tabelas essenciais verificadas."
    else
        warn "Algumas tabelas podem estar incompletas. Verifique o log acima."
    fi
}

# Main execution
log "========================================="
log "  Initialization of Database: $DB_NAME"
log "========================================="

# If DB is remote, wait for it
if [ "$DB_HOST" != "localhost" ] && [ "$DB_HOST" != "127.0.0.1" ]; then
    wait_for_mysql
fi

# Run setup
setup_database

log "========================================="
log "  Database initialization complete."
log "========================================="
