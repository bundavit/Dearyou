#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/dearyou}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/dearyou}"
STAMP="$(date +%Y%m%d-%H%M%S)"

mkdir -p "$BACKUP_DIR"
set -a
source "$APP_DIR/.env"
set +a

mysqldump --single-transaction --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USERNAME" --password="$DB_PASSWORD" "$DB_DATABASE" | gzip > "$BACKUP_DIR/database-$STAMP.sql.gz"
tar -czf "$BACKUP_DIR/uploads-$STAMP.tar.gz" -C "$APP_DIR/storage/app" public
find "$BACKUP_DIR" -type f -mtime +14 -delete
