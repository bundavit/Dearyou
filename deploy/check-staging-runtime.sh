#!/usr/bin/env bash
# Owner-run, read-only staging acceptance. Never starts services or invokes Certbot.
set -uo pipefail

readonly STAGING_REPO=/var/www/dearyou-staging
readonly STAGING_APP="$STAGING_REPO/backend"
readonly STAGING_PHP=/usr/bin/php
readonly STAGING_NGINX=/usr/sbin/nginx
readonly STAGING_UNITS=/etc/systemd/system
readonly STAGING_SITE=/etc/nginx/sites-available/dearyou-staging
readonly STAGING_ENABLED=/etc/nginx/sites-enabled/dearyou-staging
readonly STAGING_PASSWORD=/etc/nginx/.htpasswd-dearyou-staging
readonly STAGING_CERT=/etc/letsencrypt/live/staging.dearyous.app/fullchain.pem
readonly STAGING_RENEWAL=/etc/letsencrypt/renewal/staging.dearyous.app.conf
readonly CERTBOT_CONFIG=/etc/letsencrypt
readonly ROOT_CERTBOT_CONFIG=/root/.config/letsencrypt

fail() { printf 'FAIL %s\n' "$1" >&2; exit 1; }
[[ $# -eq 0 ]] || fail 'This read-only staging check accepts no arguments.'
[[ $EUID -eq 0 ]] || fail 'Run this read-only server check as root.'
for tool in realpath systemctl cmp stat sudo openssl curl awk find git id; do
    command -v "$tool" >/dev/null || fail "Required tool is missing: $tool"
done
[[ -x "$STAGING_PHP" && -x "$STAGING_NGINX" ]] || fail 'PHP or Nginx is missing.'
[[ "$(realpath -- "${BASH_SOURCE[0]}")" == "$STAGING_REPO/deploy/check-staging-runtime.sh" ]] || fail 'Run the checker from the fixed staging checkout.'
[[ "$(realpath -- "$STAGING_APP")" == "$STAGING_APP" ]] || fail 'The fixed staging application is missing or symlinked.'

failures=0
check() {
    local description="$1"
    shift
    if "$@" >/dev/null 2>&1; then
        printf 'PASS %s\n' "$description"
    else
        printf 'FAIL %s\n' "$description"
        failures=$((failures + 1))
    fi
}
property() { systemctl show "$1" --property="$2" --value 2>/dev/null; }
equal_property() { [[ "$(property "$1" "$2")" == "$3" ]]; }
nonzero_property() { local value; value="$(property "$1" "$2")"; [[ "$value" =~ ^[0-9]+$ && "$value" != 0 ]]; }
regular_file() { [[ -f "$1" && ! -L "$1" ]]; }
clean_source() {
    local status
    status="$(sudo -n -u deploy git --no-optional-locks -C "$STAGING_REPO" status --porcelain --untracked-files=normal)" && [[ -z "$status" ]]
}
protected_password_file() {
    regular_file "$STAGING_PASSWORD" &&
        [[ "$(stat -c '%u:%g:%a' -- "$STAGING_PASSWORD")" == "0:$(id -g www-data):640" ]] &&
        sudo -n -u www-data test -r "$STAGING_PASSWORD"
}
safe_unit() {
    local unit="$1" expected_command="${2:-}" key actual prefix
    regular_file "$STAGING_UNITS/$unit" &&
        cmp -s -- "$STAGING_REPO/deploy/$unit" "$STAGING_UNITS/$unit" &&
        equal_property "$unit" LoadState loaded &&
        equal_property "$unit" FragmentPath "$STAGING_UNITS/$unit" &&
        equal_property "$unit" DropInPaths '' &&
        equal_property "$unit" NeedDaemonReload no || return 1
    if [[ -n "$expected_command" ]]; then
        equal_property "$unit" WorkingDirectory "$STAGING_APP" &&
            equal_property "$unit" User www-data && equal_property "$unit" Group www-data || return 1
        for key in Environment EnvironmentFiles PassEnvironment ExecCondition ExecStartPre ExecStartPost ExecStop ExecStopPost ExecReload RootDirectory RootImage; do
            equal_property "$unit" "$key" '' || return 1
        done
        actual="$(property "$unit" ExecStart)"
        prefix="{ path=$STAGING_PHP ; argv[]=$expected_command ; ignore_errors=no ;"
        [[ "$actual" == "$prefix"* && "${actual#"$prefix"}" != *'path='* ]] || return 1
    fi
}
staging_site_matches() {
    regular_file "$STAGING_SITE" && [[ -L "$STAGING_ENABLED" ]] &&
        [[ "$(realpath -- "$STAGING_ENABLED")" == "$STAGING_SITE" ]] &&
        cmp -s -- "$STAGING_REPO/deploy/nginx-dearyou-staging.conf" "$STAGING_SITE"
}
nginx_configuration_valid() {
    local output
    output="$("$STAGING_NGINX" -t 2>&1)" && [[ "$output" != *'conflicting server name'* ]]
}
https_gate() {
    local headers
    headers="$(curl --noproxy '*' --silent --show-error --head --max-time 15 "$@" https://staging.dearyous.app/ 2>/dev/null)" || return 1
    headers="${headers//$'\r'/}"
    awk '
        BEGIN { IGNORECASE=1 }
        NR == 1 && $2 == "401" { status=1 }
        tolower($0) ~ /^www-authenticate: basic realm="dearyou staging"$/ { auth=1 }
        tolower($0) ~ /^x-robots-tag:.*noindex/ { robots=1 }
        tolower($0) ~ /^cache-control:.*no-store/ { cache=1 }
        END { exit !(status && auth && robots && cache) }
    ' <<< "$headers"
}
production_responds() {
    [[ "$(curl --noproxy '*' --silent --show-error --head --output /dev/null --max-time 15 --write-out '%{http_code}' https://dearyous.app/ 2>/dev/null)" == 200 ]]
}
webroot_renewal() {
    regular_file "$STAGING_RENEWAL" || return 1
    # Inspect only fixed expected fields. Never execute or print configuration.
    awk -F= '
        { key=$1; gsub(/^[ \t]+|[ \t]+$/, "", key); value=substr($0,index($0,"=")+1); gsub(/^[ \t]+|[ \t]+$/, "", value) }
        key == "authenticator" { auth=(value == "webroot"); auth_count++ }
        key == "installer" && value != "None" && value != "" { installer=1 }
        key == "webroot_path" && value ~ /^\/var\/www\/letsencrypt-dearyou-staging,?$/ { root=1 }
        key == "staging.dearyous.app" && value == "/var/www/letsencrypt-dearyou-staging" { root=1 }
        END { exit !(auth_count == 1 && auth && root && !installer) }
    ' "$STAGING_RENEWAL"
}
renewal_timer() {
    local unit
    for unit in certbot.timer snap.certbot.renew.timer; do
        if equal_property "$unit" LoadState loaded && equal_property "$unit" ActiveState active &&
           equal_property "$unit" UnitFileState enabled; then
            return 0
        fi
    done
    return 1
}
hook_fields() {
    [[ -f "$1" ]] || { printf '0'; return; }
    awk -F= '{ key=$1; gsub(/^[ \t]+|[ \t]+$/, "", key); gsub(/-/, "_", key); if (key ~ /^(pre_hook|post_hook|deploy_hook|renew_hook)$/) count++ } END { print count+0 }' "$1"
}

printf '%s\n' 'Read-only staging runtime acceptance (no service, certificate or database mutations).'
check 'Staging configuration isolation as deploy' sudo -n -u deploy -H "$STAGING_PHP" "$STAGING_REPO/deploy/check-staging.php"
check 'Staging configuration isolation as www-data' sudo -n -u www-data "$STAGING_PHP" "$STAGING_REPO/deploy/check-staging.php"
check 'Staging source is clean' clean_source

check 'Worker definition targets only staging' safe_unit dearyou-staging-worker.service "$STAGING_PHP $STAGING_APP/artisan queue:work --sleep=3 --tries=3 --backoff=10 --timeout=60 --max-time=3600"
check 'Scheduler definition targets only staging' safe_unit dearyou-staging-scheduler.service "$STAGING_PHP $STAGING_APP/artisan schedule:run"
check 'Timer definition matches the staging template' safe_unit dearyou-staging-scheduler.timer
check 'Timer targets the staging scheduler' equal_property dearyou-staging-scheduler.timer Unit dearyou-staging-scheduler.service
check 'Staging worker is active' equal_property dearyou-staging-worker.service ActiveState active
check 'Staging worker is enabled' equal_property dearyou-staging-worker.service UnitFileState enabled
check 'Staging scheduler timer is active' equal_property dearyou-staging-scheduler.timer ActiveState active
check 'Staging scheduler timer is enabled' equal_property dearyou-staging-scheduler.timer UnitFileState enabled
check 'Staging scheduler timer has triggered' nonzero_property dearyou-staging-scheduler.timer LastTriggerUSecMonotonic
check 'Staging scheduler has completed a run' nonzero_property dearyou-staging-scheduler.service ExecMainExitTimestampMonotonic
check 'Last staging scheduler run succeeded' equal_property dearyou-staging-scheduler.service Result success
check 'Last staging scheduler exit code is zero' equal_property dearyou-staging-scheduler.service ExecMainStatus 0

check 'Installed staging Nginx site matches the reviewed template' staging_site_matches
check 'Nginx configuration is valid with no conflicting server names' nginx_configuration_valid
check 'Staging password file is protected and readable by www-data' protected_password_file
check 'Installed certificate covers the staging hostname' openssl x509 -in "$STAGING_CERT" -noout -checkhost staging.dearyous.app
check 'Installed staging certificate has more than 30 days remaining' openssl x509 -in "$STAGING_CERT" -noout -checkend 2592000
check 'Public staging HTTPS requires authentication with noindex and no-store' https_gate
check 'Local staging HTTPS requires authentication with noindex and no-store' https_gate --resolve staging.dearyous.app:443:127.0.0.1
check 'Production homepage responds with valid HTTPS and HTTP 200' production_responds
check 'Saved staging renewal uses the dedicated webroot without an installer' webroot_renewal
check 'A supported Certbot renewal timer is active and enabled' renewal_timer

printf 'REVIEW Saved staging hook fields: %s; system CLI hook fields: %s; root CLI hook fields: %s\n' \
    "$(hook_fields "$STAGING_RENEWAL")" "$(hook_fields "$CERTBOT_CONFIG/cli.ini")" "$(hook_fields "$ROOT_CERTBOT_CONFIG/cli.ini")"
for hook_kind in pre deploy post; do
    hook_count=0
    if [[ -d "$CERTBOT_CONFIG/renewal-hooks/$hook_kind" ]]; then
        while IFS= read -r -d '' _hook; do hook_count=$((hook_count + 1)); done < <(find "$CERTBOT_CONFIG/renewal-hooks/$hook_kind" -maxdepth 1 \( -type f -o -type l \) -executable -print0)
    fi
    printf 'REVIEW Executable Certbot %s directory hooks: %s\n' "$hook_kind" "$hook_count"
done
printf '%s\n' 'REVIEW Hook behavior, successful challenge issuance, certificate reload, queued code delivery and restore acceptance require separate owner evidence.'
if [[ "$failures" -ne 0 ]]; then
    printf 'Staging runtime acceptance has %s failed checks. No changes were made.\n' "$failures"
    exit 1
fi
printf '%s\n' 'Staging runtime checks passed. Separate REVIEW items remain acceptance requirements.'
