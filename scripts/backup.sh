#!/bin/bash
set -euo pipefail

if [ -z "${1:-}" ]; then
  echo "Uso: ./scripts/backup.sh <ruta_config_php>"
  exit 1
fi

CONFIG_FILE="$1"
if [ ! -f "$CONFIG_FILE" ]; then
  echo "Config no encontrada"
  exit 1
fi

mapfile -t CONFIG_VALUES < <(php -r "require '${CONFIG_FILE}'; echo DB_HOST, PHP_EOL, DB_USER, PHP_EOL, DB_PASS, PHP_EOL, DB_NAME, PHP_EOL, (defined('BACKUP_RETENTION') ? BACKUP_RETENTION : 10);")
DB_HOST="${CONFIG_VALUES[0]}"
DB_USER="${CONFIG_VALUES[1]}"
DB_PASS="${CONFIG_VALUES[2]}"
DB_NAME="${CONFIG_VALUES[3]}"
BACKUP_RETENTION="${CONFIG_VALUES[4]}"

TIMESTAMP=$(date +"%Y%m%d%H%M%S")
BACKUP_DIR="storage/backups"
mkdir -p "$BACKUP_DIR"

mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip > "$BACKUP_DIR/backup_${TIMESTAMP}.sql.gz"

ls -1t "$BACKUP_DIR"/backup_*.sql.gz | tail -n +$((BACKUP_RETENTION + 1)) | xargs -r rm --
