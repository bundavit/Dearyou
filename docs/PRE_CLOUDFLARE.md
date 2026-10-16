# Acceptance before Cloudflare

Updated 16 October 2026 (Asia/Bangkok). The owner performs commits, pushes, server commands and deployments. Cloudflare remains deferred. Finish staging acceptance, then promote the same tested commit to production with a fresh verified backup. A local test result is not evidence of a deployed fix.

## Confirmed and pending

The owner has confirmed separate staging configuration/database/storage, successful migrations and frontend build, HTTPS, the browser password gate, and a working original administrator login. Public staging returns 401 without the gate credentials; production returns 200. The owner also reported an enabled, running staging worker, an enabled staging scheduler timer with a recent trigger, and an enabled, active `certbot.timer`. The inactive/static scheduler service is normal for a completed oneshot.

Still record the scheduler's successful exit, a newly queued staging verification/reset message, certificate challenge and reload acceptance, the updated application's browser checks, and the release backup/restore evidence. No files were found under `/etc/letsencrypt/renewal-hooks` in the owner's current inventory, but hooks can also exist in Certbot configuration. Do not infer renewal acceptance from a valid certificate or an active timer alone.

## Read-only runtime report

After the owner commits and deploys the reviewed revision containing these tools to staging, run as root:

```bash
bash /var/www/dearyou-staging/deploy/check-staging-runtime.sh
```

This checker takes no arguments and targets fixed staging paths. It checks fresh/cached isolation as both `deploy` and `www-data`, clean source, exact installed unit definitions, effective staging service paths/users/commands, worker and timer state, a completed successful scheduler run, the reviewed Nginx site, protected gate credentials, certificate hostname/lifetime, public and local strict-TLS 401 headers, the production homepage, saved webroot renewal parameters and known renewal timers. It never invokes Certbot, Artisan, database clients, service changes or Git writes, and never prints passwords, configuration contents or mail logs.

A nonzero exit identifies a failed required check; nothing is changed. A zero exit covers the printed PASS rows. REVIEW rows still require separate acceptance. A custom existing cron-based Certbot renewal arrangement needs individual review rather than installing a second timer merely to satisfy this checker. `REVIEW` hook counts are an inventory, not proof that the hooks are safe or reload Nginx.

The new checker is not present in the old server revision. Do not mistake a missing script for an application failure. Before deployment, the owner can collect the current service evidence without printing logs:

```bash
systemctl show dearyou-staging-worker.service \
  dearyou-staging-scheduler.service dearyou-staging-scheduler.timer certbot.timer \
  --property=Id,LoadState,ActiveState,SubState,UnitFileState,Result,ExecMainStatus,ExecMainExitTimestamp,LastTriggerUSec,NextElapseUSecRealtime \
  --no-pager
```

## Staging codes and queued delivery

Keep staging on its dedicated log mailer with no production mail credentials. Request one fresh verification/reset code through the staging website. Observe the new message privately in `/var/www/dearyou-staging/backend/storage/logs/dearyou-mail.log`; verify the recipient and request time, then enter the code through the website. Do not share the code, mail body or log contents. Confirm the request is processed by the normal running staging worker; manually draining the queue proves a different path.

If the worker is inactive or the new mail never appears, inspect only the staging worker/queue before retrying. No production queue command is needed. Do not clear or retry every failed job, alter existing accounts, or change staging to a live transport to obtain a code. Keep successful code acceptance distinct from manually marking a test account verified. After a password change, update the ignored local QA credential file privately.

## Isolated certificate challenge test

Certbot's dry run normally executes pre/post hooks, and `--no-directory-hooks` suppresses only directory hooks. Saved and global configuration hooks need separate review. A direct `certbot renew --dry-run`, even with a certificate name, is therefore not the first diagnostic on this shared server. [Certbot documents the hook behavior and configurable directories](https://eff-certbot.readthedocs.io/en/stable/using.html#renewing-certificates).

The following explicit owner-run probe uses a new private configuration/work/log area, an empty CLI configuration, only the staging hostname, the staging ACME webroot and Let's Encrypt's test server. It does not load installed renewal files/hooks or replace the live certificate. It creates temporary HTTP challenge files only in the dedicated staging webroot. Run as root after confirming the existing staging HTTP challenge location is installed:

```bash
bash <<'STAGING_ACME'
set -euo pipefail
test -d /var/www/letsencrypt-dearyou-staging
test ! -L /var/www/letsencrypt-dearyou-staging
staging_probe=$(mktemp -d /var/lib/dearyou-staging-acme-probe.XXXXXXXX)
chmod 0700 "$staging_probe"
printf 'Private staging probe files: %s\n' "$staging_probe"

/usr/bin/certbot certonly \
  --config /dev/null \
  --config-dir "$staging_probe/config" \
  --work-dir "$staging_probe/work" \
  --logs-dir "$staging_probe/logs" \
  --non-interactive --agree-tos --register-unsafely-without-email \
  --no-directory-hooks --dry-run \
  --server https://acme-staging-v02.api.letsencrypt.org/directory \
  --webroot --webroot-path /var/www/letsencrypt-dearyou-staging \
  --cert-name dearyou-staging-only-probe \
  --domain staging.dearyous.app
STAGING_ACME
```

Retain the private probe directory for inspection; the command deletes no installed certificate, account, configuration or hook. This proves challenge issuance with the explicit parameters, not the saved renewal job or successful reload after renewal. Inspect saved staging webroot parameters and any global/lineage hooks privately before testing the installed renewal flow. Do not use `--force-renewal` or `--break-my-certs` for acceptance.

## Certificate reload hook

Nginx must reload after a renewed certificate becomes available. First review the existing renewal service, global CLI files and staging renewal configuration privately for an equivalent reload action. Reuse a suitable existing arrangement; avoid duplicate hooks. If no existing arrangement covers staging, the optional `deploy/certbot-staging-deploy-hook.sh`:

- exits without action for every other certificate lineage;
- requires the exact staging domain set and root;
- validates Nginx, rejects conflicting server names, then gracefully reloads it and checks its active state;
- never stops Nginx or changes site/certificate configuration.

After reviewing the committed hook, the owner can install it as root. The checks refuse an existing destination:

```bash
bash <<'STAGING_HOOK'
set -euo pipefail
staging_hook=/etc/letsencrypt/renewal-hooks/deploy/50-dearyou-staging-reload
test ! -e "$staging_hook"
test ! -L "$staging_hook"
install -d -o root -g root -m 0755 /etc/letsencrypt/renewal-hooks/deploy
install -o root -g root -m 0755 \
  /var/www/dearyou-staging/deploy/certbot-staging-deploy-hook.sh "$staging_hook"
STAGING_HOOK
```

An explicit owner-run hook test validates and reloads the shared Nginx process using the current certificate, so schedule it deliberately:

```bash
env RENEWED_LINEAGE=/etc/letsencrypt/live/staging.dearyous.app \
  RENEWED_DOMAINS=staging.dearyous.app \
  /etc/letsencrypt/renewal-hooks/deploy/50-dearyou-staging-reload

curl --noproxy '*' -sSI --max-time 20 https://staging.dearyous.app/
curl --noproxy '*' -sS --head --output /dev/null --max-time 20 \
  --write-out 'Production HTTP status: %{http_code}\n' https://dearyous.app/
```

Expected: hook success, staging 401 with noindex/no-store, and production 200. This tests reload behavior independently of the isolated issuance probe. Do not pass real passwords to curl arguments. The Certbot timer, reviewed saved renewal parameters, successful issuance probe and tested reload arrangement form the renewal acceptance evidence; none alone proves the entire future renewal event.

## Deploy the reviewed fixes to staging

Review and commit/push the prepared application and operations changes on the owner's PC. On the server, use the complete reviewed 40-character commit and the clean fixed staging checkout. Follow [STAGING.md](STAGING.md), then run the runtime report above and authenticated browser acceptance against the deployed fixes. Record the actual commit, browser results and synthetic-record cleanup.

The staging updater runs Composer/npm as the deployment user and uses sudo for explicitly named service operations. It requires working noninteractive sudo in that session; the root console is not a substitute for running dependency installs as `deploy`. If the owner does not know the deployment user's password, root can set a private replacement with `passwd deploy`, then the owner can enter a deploy login and use interactive `sudo -v` before the updater. Keep password entry private. Do not add broad passwordless sudo rules merely to bypass this prerequisite. Check the command permissions in [STAGING.md](STAGING.md) before starting maintenance.

Do not reload shared PHP-FPM during staging deployment. Updating staging leaves production running. If a staging deployment fails, its updater retains maintenance and stops only staging background processes; inspect and recover staging before continuing.

## Production promotion and recovery evidence

Production deployment remains an owner operation after staging acceptance. Use the same reviewed/tested commit and [OPERATIONS.md](OPERATIONS.md). Confirm the real production database name privately, the production PHP/service definitions, a restricted backup-client option file and a separate restore-client account limited to new `dearyou_restore_*` databases. Do not reuse staging credentials, configuration, database, uploads or synthetic records.

The final backup is taken during a controlled maintenance window with writers paused, preserved outside the web root in a private directory, and verified into a new isolated scratch database. The existing helpers record checksums, row-count checks and a restore receipt; the production updater requires that exact receipt and a backup less than one hour old with unchanged environment/maintenance identity. Keep maintenance continuous through backup, verification and release. No automatic database rollback is attempted. Preserve a separate encrypted off-host copy and record how the owner will retrieve it; local disk backups alone are insufficient for server loss.

An actual isolated application recovery rehearsal remains stronger evidence than the helper's SQL/archive checks. Use a separate host or truly isolated app/database/media paths with outgoing mail disabled, preserve the backed-up key, and inspect representative letters/media. Never run the rehearsal against production or drop an existing database to make a test pass. The scratch database and private bundles are retained for deliberate owner inspection/removal.

After production promotion, record homepage/login/admin health, the affected letter/media/expiry behavior, queued delivery and service status. Only then begin [CLOUDFLARE.md](CLOUDFLARE.md). System package upgrades and the reported pending reboot need their own owner maintenance window; do not restart the shared server during staging acceptance.

## Local validation of these tools

```bash
bash -n deploy/check-staging-runtime.sh deploy/certbot-staging-deploy-hook.sh
python3 deploy/tests/staging-runtime-test.py
python3 deploy/tests/staging-certbot-hook-test.py
```

The tests use temporary synthetic paths and mocked PHP/Git/Nginx/systemd/curl/OpenSSL. They cover fail-closed guards, service/HTTP/TLS/configuration errors, secret-free reports, absence of mutations, and the certificate hook's staging scope and reload failure behavior. They provide no live-server acceptance evidence.
