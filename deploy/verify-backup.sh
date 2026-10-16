#!/usr/bin/env bash
# Restore a backup into a NEW disposable database, never into the source database.
set -euo pipefail
umask 077
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/operations-common.sh"
BUNDLE="$(realpath -- "${BACKUP_BUNDLE:?Set BACKUP_BUNDLE}")"
verify_bundle "$BUNDLE"
require_mysql_options "${MYSQL_DEFAULTS_FILE:-}"
[[ "${RESTORE_DB:-}" =~ ^dearyou_restore_[a-z0-9_]+$ ]] || fail 'RESTORE_DB must start with dearyou_restore_ and contain only lowercase identifier characters.'
[[ "$RESTORE_DB" != "$(metadata_value "$BUNDLE/metadata" SOURCE_DATABASE)" ]] || fail 'The source database cannot be a restore target.'
mysql_command=(mysql --defaults-extra-file="$MYSQL_DEFAULTS_FILE" --batch --skip-column-names)
# No IF NOT EXISTS: an existing database is always refused, with no DROP operation.
"${mysql_command[@]}" --execute="CREATE DATABASE \`$RESTORE_DB\`"
printf 'Created isolated restore database: %s\n' "$RESTORE_DB"
scratch="$(mktemp -d)"
trap 'rm -rf -- "$scratch"' EXIT
tar -xzf "$BUNDLE/uploads.tar.gz" --no-same-owner --no-same-permissions -C "$scratch"
gzip -dc -- "$BUNDLE/database.sql.gz" | "${mysql_command[@]}" "$RESTORE_DB"
for table in users letters; do
    actual="$("${mysql_command[@]}" "$RESTORE_DB" --execute="SELECT COUNT(*) FROM \`$table\`")"
    expected="$(metadata_value "$BUNDLE/metadata" "${table^^}")"
    [[ "$actual" == "$expected" ]] || fail "Restored row count differs: $table"
done
"${mysql_command[@]}" "$RESTORE_DB" --execute='SELECT COUNT(*) FROM migrations' >/dev/null
verify_bundle "$BUNDLE"
receipt="$(mktemp "$BUNDLE/.restore-verified.XXXXXXXX")"
printf 'RESTORE_VERSION=1\nVERIFIED_AT=%s\nBACKUP_MANIFEST_SHA256=%s\nRESTORE_DATABASE=%s\n' \
    "$(date +%s)" "$(sha256sum "$BUNDLE/SHA256SUMS" | cut -d ' ' -f 1)" "$RESTORE_DB" > "$receipt"
mv -- "$receipt" "$BUNDLE/restore-verified"
printf 'Restore verified; receipt: %s/restore-verified\nThe disposable database remains for inspection. Remove it manually using the restore account.\n' "$BUNDLE"
