#!/usr/bin/env bash
# Owner-run, quiescent MySQL + media backup. Never executes the application's .env.
set -euo pipefail
umask 077
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/operations-common.sh"

APP_DIR="$(realpath -- "${APP_DIR:-/var/www/dearyou/backend}")"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/dearyou}"
require_database_name "${DB_NAME:-}"
require_mysql_options "${MYSQL_DEFAULTS_FILE:-}"
require_paused_writers "$APP_DIR"
maintenance_id="$(stat -c '%d:%i:%y:%z' "$APP_DIR/storage/framework/down")"
[[ -f "$APP_DIR/.env" && ! -L "$APP_DIR/.env" ]] || fail 'A regular application .env is required.'
[[ -d "$APP_DIR/storage/app/public" ]] || fail 'The legacy public upload directory is missing.'
[[ "$APP_DIR" != *$'\n'* ]] || fail 'Application path contains a newline.'
mkdir -p -- "$BACKUP_DIR"
require_private_directory "$BACKUP_DIR"
BUNDLE="$(mktemp -d "$BACKUP_DIR/backup-$(date -u +%Y%m%dT%H%M%SZ)-XXXXXXXX")"
complete=0
trap '[[ "$complete" == 1 ]] || printf "Incomplete backup retained for inspection: %s\n" "$BUNDLE" >&2' EXIT

mysqldump --defaults-extra-file="$MYSQL_DEFAULTS_FILE" --single-transaction --quick \
    --no-tablespaces --hex-blob --skip-add-locks --no-create-db "$DB_NAME" | gzip > "$BUNDLE/database.sql.gz"
gzip -t -- "$BUNDLE/database.sql.gz"
media=(public)
[[ ! -d "$APP_DIR/storage/app/private/media" ]] || media+=(private/media)
[[ -z "$(find "${media[@]/#/$APP_DIR/storage/app/}" -type l -print -quit)" ]] || fail 'Media contains a symlink; review it before backup.'
tar -czf "$BUNDLE/uploads.tar.gz" -C "$APP_DIR/storage/app" -- "${media[@]}"
cp -- "$APP_DIR/.env" "$BUNDLE/environment.env"
revision="$(git -C "$APP_DIR" rev-parse HEAD)"
[[ "$revision" =~ ^[0-9a-f]{40}$ ]] || fail 'Cannot determine the source revision.'
count_rows() { mysql --defaults-extra-file="$MYSQL_DEFAULTS_FILE" --batch --skip-column-names "$DB_NAME" --execute="SELECT COUNT(*) FROM \`$1\`"; }
users="$(count_rows users)"; letters="$(count_rows letters)"
[[ "$users" =~ ^[0-9]+$ && "$letters" =~ ^[0-9]+$ ]] || fail 'Could not verify source row counts.'
require_paused_writers "$APP_DIR"
[[ "$maintenance_id" == "$(stat -c '%d:%i:%y:%z' "$APP_DIR/storage/framework/down")" ]] || fail 'Maintenance changed during backup; discard this incomplete bundle and take a new backup.'
printf 'BACKUP_VERSION=1\nCREATED_AT=%s\nSOURCE_DATABASE=%s\nSOURCE_REVISION=%s\nSOURCE_APP_DIR=%s\nMAINTENANCE_ID=%s\nUSERS=%s\nLETTERS=%s\n' \
    "$(date +%s)" "$DB_NAME" "$revision" "$APP_DIR" "$maintenance_id" "$users" "$letters" > "$BUNDLE/metadata"
(cd -- "$BUNDLE"; sha256sum -- database.sql.gz uploads.tar.gz environment.env metadata > SHA256SUMS)
chmod 0600 -- "$BUNDLE"/*
verify_bundle "$BUNDLE"
complete=1
printf 'Backup created: %s\nVerify a restore with verify-backup.sh before deployment. No backups were pruned.\n' "$BUNDLE"
