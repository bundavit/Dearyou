#!/usr/bin/env bash
# Functions shared by owner-run operations. Inputs are data, never shell-sourced.
fail() { printf '%s\n' "$1" >&2; exit 1; }
require_database_name() { [[ "$1" =~ ^[A-Za-z0-9_]+$ ]] || fail 'DB_NAME must be an explicit database identifier.'; }
require_private_directory() {
    [[ -d "$1" && ! -L "$1" ]] || fail 'A regular private directory is required.'
    local mode owner
    mode="$(stat -c '%a' -- "$1")"; owner="$(stat -c '%u' -- "$1")"
    [[ "$mode" == 700 && ( "$owner" == "$(id -u)" || "$owner" == 0 ) ]] || fail 'Backup directories must be owner/root owned with mode 0700.'
}
require_private_file() {
    [[ -f "$1" && ! -L "$1" ]] || fail 'A regular protected file is required.'
    local mode owner
    mode="$(stat -c '%a' -- "$1")"; owner="$(stat -c '%u' -- "$1")"
    [[ ( "$mode" == 600 || "$mode" == 400 ) && ( "$owner" == "$(id -u)" || "$owner" == 0 ) ]] || fail 'Sensitive files must be owner/root owned with mode 0600 or 0400.'
}
require_mysql_options() {
    [[ "$1" == /* ]] || fail 'MYSQL_DEFAULTS_FILE must be an absolute path to a protected MySQL [client] option file.'
    require_private_file "$1"
}
require_paused_writers() {
    [[ "${WRITERS_PAUSED:-}" == 1 ]] || fail 'Pause all writers and set WRITERS_PAUSED=1 to acknowledge external/manual writers are stopped.'
    [[ -f "$1/storage/framework/down" ]] || fail 'Enter application maintenance mode before taking a backup or deploying.'
    local unit state
    for unit in "${WORKER_SERVICE:-dearyou-worker.service}" "${SCHEDULER_SERVICE:-dearyou-scheduler.service}" "${SCHEDULER_TIMER:-dearyou-scheduler.timer}"; do
        state="$(systemctl show "$unit" --property=ActiveState --value)" || fail "Cannot inspect writer: $unit"
        [[ "$state" == inactive || "$state" == failed ]] || fail "Stop this writer first: $unit"
    done
}
metadata_value() {
    local value
    value="$(sed -n "s/^$2=//p" "$1")"
    [[ -n "$value" && "$value" != *$'\n'* ]] || fail "Missing or duplicated metadata field: $2"
    printf '%s' "$value"
}
verify_bundle() {
    local bundle="$1" item expected actual entry listing
    require_private_directory "$bundle"
    for item in database.sql.gz uploads.tar.gz environment.env metadata SHA256SUMS; do require_private_file "$bundle/$item"; done
    expected="$(cat -- "$bundle/SHA256SUMS")"
    actual="$(cd -- "$bundle"; sha256sum -- database.sql.gz uploads.tar.gz environment.env metadata)"
    [[ "$expected" == "$actual" ]] || fail 'Backup checksums do not match.'
    [[ "$(metadata_value "$bundle/metadata" BACKUP_VERSION)" == 1 ]] || fail 'Unsupported backup format.'
    gzip -t -- "$bundle/database.sql.gz"
    listing="$(tar -tzf "$bundle/uploads.tar.gz")" || fail 'Invalid media archive.'
    [[ -n "$listing" ]] || fail 'Media archive is empty.'
    while IFS= read -r entry; do
        [[ "$entry" == public || "$entry" == public/* || "$entry" == private/media || "$entry" == private/media/* ]] || fail 'Unexpected media archive path.'
        [[ "$entry" != *'/../'* && "$entry" != */.. ]] || fail 'Unsafe media archive path.'
    done <<< "$listing"
    listing="$(tar -tvzf "$bundle/uploads.tar.gz")"
    while IFS= read -r entry; do [[ "$entry" == d* || "$entry" == -* ]] || fail 'Media archive contains a link or special file.'; done <<< "$listing"
}
