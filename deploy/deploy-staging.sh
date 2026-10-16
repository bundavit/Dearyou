#!/usr/bin/env bash
# Deploy only the code already checked out in this fixed staging directory.
set -euo pipefail
umask 0027

readonly STAGING_REPO=/var/www/dearyou-staging
readonly STAGING_APP="$STAGING_REPO/backend"
readonly STAGING_PHP=/usr/bin/php
readonly STAGING_WORKER=dearyou-staging-worker.service
readonly STAGING_SCHEDULER=dearyou-staging-scheduler.service
readonly STAGING_TIMER=dearyou-staging-scheduler.timer

fail() { printf '%s\n' "$1" >&2; exit 1; }
[[ $# -eq 0 ]] || fail 'This staging-only script accepts no arguments.'
[[ "$(realpath -- "${BASH_SOURCE[0]}")" == "$STAGING_REPO/deploy/deploy-staging.sh" ]] || fail 'Refused: script must reside in the fixed staging checkout.'
[[ "$(realpath -- "$STAGING_APP")" == "$STAGING_APP" ]] || fail 'Refused: staging application directory is missing or symlinked.'
cd "$STAGING_APP"

for tool in composer npm systemctl cmp; do
    command -v "$tool" >/dev/null || fail "Required tool is missing: $tool"
done
[[ -x "$STAGING_PHP" ]] || fail 'Required /usr/bin/php is missing.'
"$STAGING_PHP" "$STAGING_REPO/deploy/check-staging.php"

unit_property() { systemctl show "$1" --property="$2" --value; }
check_unit() {
    local unit="$1" expected_command="${2:-}" actual prefix property
    [[ "$(unit_property "$unit" LoadState)" == loaded ]] || fail "Install the staging unit before deployment: $unit"
    [[ "$(unit_property "$unit" FragmentPath)" == "/etc/systemd/system/$unit" ]] || fail "Unexpected staging unit location: $unit"
    cmp -s -- "$STAGING_REPO/deploy/$unit" "/etc/systemd/system/$unit" || fail "Installed unit differs from the reviewed staging template: $unit"
    [[ "$(unit_property "$unit" DropInPaths)" == '' ]] || fail "Remove/review staging unit drop-ins before deployment: $unit"
    [[ "$(unit_property "$unit" NeedDaemonReload)" == no ]] || fail "Reload staging unit definitions before deployment: $unit"
    if [[ -n "$expected_command" ]]; then
        [[ "$(unit_property "$unit" WorkingDirectory)" == "$STAGING_APP" ]] || fail "Incorrect staging WorkingDirectory: $unit"
        [[ "$(unit_property "$unit" User)" == www-data && "$(unit_property "$unit" Group)" == www-data ]] || fail "Unexpected staging service user/group: $unit"
        for property in Environment EnvironmentFiles PassEnvironment ExecCondition ExecStartPre ExecStartPost ExecStop ExecStopPost ExecReload RootDirectory RootImage; do
            [[ "$(unit_property "$unit" "$property")" == '' ]] || fail "Unexpected staging service override ($property): $unit"
        done
        actual="$(unit_property "$unit" ExecStart)"
        prefix="{ path=/usr/bin/php ; argv[]=$expected_command ; ignore_errors=no ;"
        [[ "$actual" == "$prefix"* && "${actual#"$prefix"}" != *'path='* ]] || fail "Unexpected staging ExecStart: $unit"
    fi
}

# These are read-only checks. No service is touched until every guard passes.
check_unit "$STAGING_WORKER" '/usr/bin/php /var/www/dearyou-staging/backend/artisan queue:work --sleep=3 --tries=3 --timeout=60 --max-time=3600'
check_unit "$STAGING_SCHEDULER" '/usr/bin/php /var/www/dearyou-staging/backend/artisan schedule:run'
check_unit "$STAGING_TIMER"
[[ "$(unit_property "$STAGING_TIMER" Unit)" == "$STAGING_SCHEDULER" ]] || fail 'Staging timer targets an unexpected service.'
sudo -n true || fail 'Arrange noninteractive sudo for the staging service operations before deployment.'
sudo -n -u www-data "$STAGING_PHP" "$STAGING_REPO/deploy/check-staging.php"
for directory in storage bootstrap/cache; do
    [[ -w "$directory" ]] || fail "Deployment user cannot write staging $directory."
    sudo -n -u www-data test -w "$STAGING_APP/$directory" || fail "Staging service user cannot write $directory."
done

deployment_started=0
on_exit() {
    local status=$?
    if [[ "$status" -ne 0 && "$deployment_started" -eq 1 ]]; then
        # Do not automatically bring a partially deployed site back online.
        sudo -n systemctl stop "$STAGING_TIMER" "$STAGING_SCHEDULER" "$STAGING_WORKER" >/dev/null 2>&1 || true
        "$STAGING_PHP" artisan down --retry=60 >/dev/null 2>&1 || true
        # Keep the existing maintenance marker if Composer broke Artisan bootstrap.
        if [[ ! -f storage/framework/down ]]; then
            printf '{"retry":60,"status":503}\n' > storage/framework/down || true
        fi
        printf '%s\n' 'Staging deployment failed. Automatic reopening is disabled; maintenance and service stops were enforced.' \
            'Inspect the failure, restore or fix the staging checkout, then rerun this script to recover. No production services were touched.' >&2
        [[ -f storage/framework/down ]] || printf '%s\n' 'WARNING: Could not retain the staging maintenance marker; take staging offline manually.' >&2
        if systemctl is-active --quiet "$STAGING_WORKER" || systemctl is-active --quiet "$STAGING_TIMER" || systemctl is-active --quiet "$STAGING_SCHEDULER"; then
            printf '%s\n' 'WARNING: A staging process is still active; stop the staging worker/timer/service manually before recovery.' >&2
        fi
    fi
}
trap on_exit EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
deployment_started=1
sudo -n systemctl stop "$STAGING_TIMER" "$STAGING_SCHEDULER" "$STAGING_WORKER"
"$STAGING_PHP" artisan down --retry=60

# New code/dependencies must remain readable by the PHP/Nginx service user.
# Runtime directories retain their separate group-only permissions.
(
    umask 0022
    composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
    npm --prefix "$STAGING_APP" --global=false ci
    npm --prefix "$STAGING_APP" --global=false run build
)
"$STAGING_PHP" "$STAGING_REPO/deploy/check-staging.php"
"$STAGING_PHP" artisan config:clear
"$STAGING_PHP" "$STAGING_REPO/deploy/check-staging.php"
"$STAGING_PHP" artisan migrate --force
"$STAGING_PHP" artisan storage:link

# Run optimize's component commands separately so each failure stops deployment.
"$STAGING_PHP" artisan config:cache
"$STAGING_PHP" artisan event:cache
"$STAGING_PHP" artisan route:cache
"$STAGING_PHP" artisan view:cache
"$STAGING_PHP" "$STAGING_REPO/deploy/check-staging.php"
sudo -n -u www-data "$STAGING_PHP" "$STAGING_REPO/deploy/check-staging.php"
sudo -n systemctl start "$STAGING_WORKER" "$STAGING_TIMER"
systemctl is-active --quiet "$STAGING_WORKER"
systemctl is-active --quiet "$STAGING_TIMER"
"$STAGING_PHP" artisan up
trap - EXIT INT TERM
printf '%s\n' 'Staging deployment complete. Only staging application files, database, and services were targeted.'
