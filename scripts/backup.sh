#!/bin/bash
set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-/opt/backups}"
RETENTION_DAYS="${RETENTION_DAYS:-7}"
DATE=$(date +%Y%m%d_%H%M%S)
LOG_FILE="${BACKUP_DIR}/backup.log"

DB_HOST_CAMERAS="${DB_HOST_CAMERAS:-cameras-db}"
DB_USER_CAMERAS="${DB_USER_CAMERAS:-root}"
DB_PASS_CAMERAS="${DB_PASS_CAMERAS:?Erro: defina DB_PASS_CAMERAS}"

DB_HOST_VISITANTES="${DB_HOST_VISITANTES:-visitantes-db}"
DB_USER_VISITANTES="${DB_USER_VISITANTES:-root}"
DB_PASS_VISITANTES="${DB_PASS_VISITANTES:?Erro: defina DB_PASS_VISITANTES}"

mkdir -p "${BACKUP_DIR}"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "${LOG_FILE}"
}

log "=== Iniciando backup ==="

MYSQLDUMP_OPTS="--all-databases --single-transaction --routines --triggers --events"

log "Dump cameras-db..."
docker exec cameras-db mysqldump -u "${DB_USER_CAMERAS}" -p"${DB_PASS_CAMERAS}" \
  ${MYSQLDUMP_OPTS} 2>/dev/null | \
  gzip > "${BACKUP_DIR}/cameras-db_${DATE}.sql.gz" || {
    log "ERRO: Falha no dump cameras-db"
    echo "ERRO: Falha no dump cameras-db" >&2
    exit 1
}

log "Dump visitantes-db..."
docker exec visitantes-db mysqldump -u "${DB_USER_VISITANTES}" -p"${DB_PASS_VISITANTES}" \
  ${MYSQLDUMP_OPTS} 2>/dev/null | \
  gzip > "${BACKUP_DIR}/visitantes-db_${DATE}.sql.gz" || {
    log "ERRO: Falha no dump visitantes-db"
    echo "ERRO: Falha no dump visitantes-db" >&2
    exit 1
}

log "Backup fotos_visitantes..."
docker run --rm \
  -v sistema-visitantes-v10_fotos_visitantes:/data \
  -v "${BACKUP_DIR}:/backup" \
  alpine tar czf "/backup/fotos_visitantes_${DATE}.tar.gz" -C /data .

log "Removendo backups com mais de ${RETENTION_DAYS} dias..."
find "${BACKUP_DIR}" \( -name "*.sql.gz" -o -name "*.tar.gz" \) -type f -mtime +${RETENTION_DAYS} -delete -print

log "=== Backup concluido ==="
echo "Backup salvo em: ${BACKUP_DIR}"
