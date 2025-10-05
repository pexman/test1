#!/bin/bash
set -euo pipefail

if [ $# -lt 2 ]; then
  echo "Uso: ./scripts/restore.sh <ruta_config_php> <archivo_backup>"
  exit 1
fi

CONFIG_FILE="$1"
BACKUP_FILE="$2"

if [ ! -f "$CONFIG_FILE" ] || [ ! -f "$BACKUP_FILE" ]; then
  echo "Config o backup no encontrados"
  exit 1
fi

mapfile -t CONFIG_VALUES < <(php -r "require '${CONFIG_FILE}'; echo DB_HOST, PHP_EOL, DB_USER, PHP_EOL, DB_PASS, PHP_EOL, DB_NAME;")
DB_HOST="${CONFIG_VALUES[0]}"
DB_USER="${CONFIG_VALUES[1]}"
DB_PASS="${CONFIG_VALUES[2]}"
DB_NAME="${CONFIG_VALUES[3]}"

gunzip -c "$BACKUP_FILE" | mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME"
