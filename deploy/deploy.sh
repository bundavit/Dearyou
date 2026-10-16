#!/usr/bin/env bash
# Owner-run release of an exact reviewed commit after a verified backup.
set -euo pipefail
umask 0027
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/operations-common.sh"
REPO_DIR="$(realpath -- "${REPO_DIR:-/var/www/dearyou}")"
APP_DIR="$(realpath -- "${APP_DIR:-$REPO_DIR/backend}")"
WORKER_SERVICE="${WORKER_SERVICE:-dearyou-worker.service}"
SCHEDULER_SERVICE="${SCHEDULER_SERVICE:-dearyou-scheduler.service}"
SCHEDULER_TIMER="${SCHEDULER_TIMER:-dearyou-scheduler.timer}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.4-fpm}"
[[ "${REVISION:-}" =~ ^[0-9a-f]{40}$ ]] || fail 'REVISION must be the complete reviewed 40-character lowercase commit hash.'
require_database_name "${DB_NAME:-}"
[[ "$APP_DIR" == "$REPO_DIR/backend" && -f "$APP_DIR/artisan" ]] || fail 'APP_DIR must be the backend directory of this checkout.'
[[ -z "$(git -C "$REPO_DIR" status --porcelain --untracked-files=normal)" ]] || fail 'The checkout must be clean before deployment.'
require_paused_writers "$APP_DIR"
BUNDLE="$(realpath -- "${BACKUP_BUNDLE:?Set BACKUP_BUNDLE}")"
verify_bundle "$BUNDLE"
require_private_file "$BUNDLE/restore-verified"
[[ "$(metadata_value "$BUNDLE/restore-verified" RESTORE_VERSION)" == 1 ]] || fail 'Unsupported restore receipt.'
[[ "$(metadata_value "$BUNDLE/restore-verified" BACKUP_MANIFEST_SHA256)" == "$(sha256sum "$BUNDLE/SHA256SUMS" | cut -d ' ' -f 1)" ]] || fail 'Restore receipt does not match this backup.'
[[ "$(metadata_value "$BUNDLE/metadata" SOURCE_DATABASE)" == "$DB_NAME" && "$(metadata_value "$BUNDLE/metadata" SOURCE_APP_DIR)" == "$APP_DIR" ]] || fail 'Backup belongs to a different application or database.'
[[ "$(metadata_value "$BUNDLE/metadata" SOURCE_REVISION)" == "$(git -C "$REPO_DIR" rev-parse HEAD)" ]] || fail 'Backup was taken from a different checkout revision.'
cmp -s -- "$BUNDLE/environment.env" "$APP_DIR/.env" || fail 'Application configuration changed after backup.'
[[ "$(metadata_value "$BUNDLE/metadata" MAINTENANCE_ID)" == "$(stat -c '%d:%i:%y:%z' "$APP_DIR/storage/framework/down")" ]] || fail 'Maintenance changed after backup; take and verify a fresh backup.'
now="$(date +%s)"; created="$(metadata_value "$BUNDLE/metadata" CREATED_AT)"; verified="$(metadata_value "$BUNDLE/restore-verified" VERIFIED_AT)"
[[ "$created" =~ ^[0-9]{1,11}$ && "$verified" =~ ^[0-9]{1,11}$ ]] || fail 'Invalid backup timestamps.'
[[ "$created" -le "$verified" && "$verified" -le "$now" && $((now-created)) -le 3600 ]] || fail 'A restore-verified backup created within the last hour is required.'
sudo -n true
cd -- "$REPO_DIR"
git fetch --no-tags origin "$REVISION"
[[ "$(git rev-parse 'FETCH_HEAD^{commit}')" == "$REVISION" ]] || fail 'Fetched revision does not match REVISION.'
started=0
on_exit() {
    local status=$?
    if [[ "$status" -ne 0 && "$started" == 1 ]]; then
        sudo -n systemctl stop "$SCHEDULER_TIMER" "$SCHEDULER_SERVICE" "$WORKER_SERVICE" >/dev/null 2>&1 || true
        (cd -- "$APP_DIR"; php artisan down --retry=60) >/dev/null 2>&1 || true
        if [[ ! -f "$APP_DIR/storage/framework/down" ]]; then
            printf '{"retry":60,"status":503}\n' > "$APP_DIR/storage/framework/down" || true
        fi
        printf '%s\n' 'DearYou deployment failed. Maintenance remains enabled and writers were stopped. Inspect the failure before manual recovery; database rollback is never automatic.' >&2
    fi
}
trap on_exit EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
started=1
sudo -n systemctl stop "$SCHEDULER_TIMER" "$SCHEDULER_SERVICE" "$WORKER_SERVICE"
require_paused_writers "$APP_DIR"
git checkout --detach "$REVISION"
cd -- "$APP_DIR"
# Runtime directories must already have the deployment/service group and
# group-only access. No recursive ownership or permission changes occur here.
(
    umask 0022
    composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts
    npm ci --ignore-scripts
    npm run build
)
php artisan package:discover --ansi
php artisan optimize:clear
php "$REPO_DIR/deploy/check-production.php"
php artisan migrate --force
php artisan dearyou:retry-media-deletions
sudo -n -u www-data php artisan dearyou:secure-media --apply --remove-public
php artisan optimize
[[ ! -f bootstrap/cache/config.php ]] || chmod 0640 -- bootstrap/cache/config.php
php "$REPO_DIR/deploy/check-production.php"
sudo -n -u www-data env DB_NAME="$DB_NAME" php "$REPO_DIR/deploy/check-production.php"
sudo -n systemctl reload "$PHP_FPM_SERVICE"
sudo -n systemctl restart "$WORKER_SERVICE"
sudo -n systemctl is-active --quiet "$WORKER_SERVICE"
php artisan up
sudo -n systemctl start "$SCHEDULER_TIMER"
sudo -n systemctl is-active --quiet "$SCHEDULER_TIMER"
started=0
printf '%s\n' 'DearYou deployment complete. Verify the public site and administrator health page.'
