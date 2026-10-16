# Live and staging websites

**Current priority: staging first, Cloudflare afterward.** Add the staging DNS record at the current authoritative DNS provider. Keep the existing nameservers and production DNS records. Cloudflare setup is not required to prepare or launch this staging site.

The intended setup is two independent deployments of the same repository. Test a reviewed commit on staging, then promote that same commit to production after QA. Do not copy staging databases, uploads, `.env`, or cached configuration into production.

For everyday updates, leave the live checkout and production services running while deploying new feature code to staging. Use synthetic accounts and test data on the password-protected staging site. After acceptance, the owner promotes the same tested commit through the separate production release workflow. Updating staging does not deploy production. The current production updater uses a maintenance window; this two-site setup keeps production available during feature testing, but does not make the final production deployment zero downtime.

| Resource | Live | Staging |
| --- | --- | --- |
| Website | `https://dearyous.app` | `https://staging.dearyous.app` |
| Checkout | `/var/www/dearyou` | `/var/www/dearyou-staging` |
| Laravel application | `/var/www/dearyou/backend` | `/var/www/dearyou-staging/backend` |
| MySQL database/user | Existing live configuration | `dearyou_staging` / `dearyou_staging` |
| Uploads, sessions, jobs, logs | Existing live data | Separate staging directories/database tables |
| Cookie | Existing live cookie | `dearyou_staging_session`, host-only |
| Cache prefix | Existing live prefix | `dearyou_staging_cache_` |
| Worker | `dearyou-worker` | `dearyou-staging-worker` |
| Scheduler | `dearyou-scheduler.timer` | `dearyou-staging-scheduler.timer` |
| Mail | Existing live Resend settings | Private log by default |
| Access | Public application | HTTPS plus a separate staging access password |

**Current status:** templates and deployment checks are prepared locally. The staging DNS record, server checkout, database, certificate, and services have not been provisioned. Existing production settings were not changed. The hostname is a proposed default and must be configured before it will work.

This plan can use the existing Ubuntu/Nginx/PHP 8.4.1+ server, subject to available capacity. Composer and a Node/npm version compatible with the locked Vite dependencies are also required. It separates application data and configuration but still shares the machine, PHP-FPM, and `www-data` identity. Use a separate server or separately isolated processes if staging will execute untrusted code or load tests. The staging updater does not reload shared PHP-FPM or edit production services.

## 1. Provision a fresh staging checkout and database

The following commands are a server runbook, not commands already executed. Use the existing deployment user, replacing `deploy` if needed, in a clean SSH shell without exported production `APP_*`, `DB_*` or other application overrides. Review, commit and push the local changes yourself before cloning; the uncommitted local setup cannot be fetched by the server. Choose the complete reviewed commit hash containing the updated staging files. Inspect any existing staging directory before creating a fresh checkout; preserve its environment/data rather than overwriting it.

```bash
sudo install -d -o deploy -g www-data -m 0750 /var/www/dearyou-staging
git clone https://github.com/bundavit/Dearyou.git /var/www/dearyou-staging
cd /var/www/dearyou-staging
export REVISION=replace_with_the_reviewed_40_character_commit_hash
git checkout --detach "$REVISION"
git status --short
git rev-parse HEAD
```

Create a new database and an account with access only to that database. Enter the SQL in `sudo mysql`, using a new random password in place of the placeholder:

```sql
CREATE DATABASE dearyou_staging CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'dearyou_staging'@'localhost' IDENTIFIED BY 'REPLACE_WITH_NEW_STAGING_PASSWORD';
GRANT ALL PRIVILEGES ON dearyou_staging.* TO 'dearyou_staging'@'localhost';
```

Initialize only the new checkout:

```bash
cd /var/www/dearyou-staging/backend
composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader
cp .env.staging.example .env
chmod 640 .env
nano .env
```

Set a unique `DB_PASSWORD`. Keep the exact staging database/user, hostname, cookie and cache-prefix values from the example. Provision synthetic accounts separately as described below. Keep `MAIL_MAILER=log`, `MAIL_LOG_CHANNEL=mail`, `LOG_LEVEL=debug`, and an empty Resend key for initial testing. Do not copy the production `.env` or its key.

Generate the staging encryption key once, then run the read-only configuration check:

```bash
php artisan key:generate
php ../deploy/check-staging.php
```

The check validates the effective configuration and filesystem boundaries without writing to a database. It cannot prove a key differs from production without inspecting production secrets; generating a new key here provides that separation. If it reports an error, correct staging configuration before continuing.

Build and create only the staging schema:

```bash
php artisan package:discover --ansi
npm ci --ignore-scripts
npm run build
php artisan migrate --force
php artisan storage:link
```

Provision the first synthetic administrator using [INITIAL_ACCOUNTS.md](INITIAL_ACCOUNTS.md) now, then finish the runtime setup:

```bash
cd /var/www/dearyou-staging/backend
sudo install -d -o deploy -g www-data -m 2770 storage/app/private/media
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache
sudo chown -R deploy:www-data /var/www/dearyou-staging/backend
sudo find storage bootstrap/cache -type d -exec chmod 2770 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 0660 {} \;
chmod 640 .env
php ../deploy/check-staging.php
sudo -u www-data php ../deploy/check-staging.php
```

The sample seeder is restricted to local/testing and must not run with `APP_ENV=staging` or production. Register other synthetic creators through the normal flow. Keep the log mailer and inspect only private staging mail logs for codes. Do not change the environment to bypass the seeder guard. The historical `public` disk name now points to `storage/app/private/media`; the separate `legacy_media` disk stays in staging's `storage/app/public`. Neither may point at production or be linked through the public web root.

## 2. Create staging DNS and HTTPS

At the current authoritative DNS provider, create an `A` record named `staging` for `staging.dearyous.app`, pointing to the selected DigitalOcean server's public IPv4 address. Keep the provider's ordinary TTL. Add an `AAAA` record only if the server's IPv6 address is configured. Preserve production and Resend DNS records and nameservers. No new domain or Cloudflare migration is required for this subdomain.

Cloudflare is deferred until staging is working. If the zone is already on Cloudflare, keep this record DNS only and leave the real-IP include disabled for this initial setup. Leave `PUBLIC_MEDIA_URL` blank so protected media stays on staging. Follow [CLOUDFLARE.md](CLOUDFLARE.md) separately when that work resumes. The template's `95m` body limit must agree with effective PHP and application limits.

The final Nginx template requires a certificate before it can load. First install an HTTP-only challenge site that does not expose the application:

```bash
sudo install -d -o root -g www-data -m 0755 /var/www/letsencrypt-dearyou-staging
sudo tee /etc/nginx/sites-available/dearyou-staging >/dev/null <<'NGINX'
server {
    listen 80;
    listen [::]:80;
    server_name staging.dearyous.app;
    location ^~ /.well-known/acme-challenge/ {
        root /var/www/letsencrypt-dearyou-staging;
        try_files $uri =404;
    }
    location / { return 404; }
}
NGINX
sudo ln -s /etc/nginx/sites-available/dearyou-staging /etc/nginx/sites-enabled/dearyou-staging
sudo nginx -t
sudo systemctl reload nginx
sudo certbot certonly --webroot -w /var/www/letsencrypt-dearyou-staging -d staging.dearyous.app
```

If a staging site file or symlink already exists, inspect it first instead of overwriting it. `certbot` must already be installed using the server's supported installation method. The [Certbot webroot method](https://eff-certbot.readthedocs.io/en/stable/using.html#webroot) obtains a certificate without automatically editing the production virtual host.

Create the staging access password interactively. This is a separate gate from the DearYou account login:

```bash
sudo apt install -y apache2-utils
sudo htpasswd -c /etc/nginx/.htpasswd-dearyou-staging tester
sudo chown root:www-data /etc/nginx/.htpasswd-dearyou-staging
sudo chmod 640 /etc/nginx/.htpasswd-dearyou-staging
sudo install -m 0644 /var/www/dearyou-staging/deploy/nginx-dearyou-staging.conf /etc/nginx/sites-available/dearyou-staging
sudo nginx -t
sudo systemctl reload nginx
sudo certbot renew --cert-name staging.dearyous.app --dry-run
```

Use `htpasswd` without `-c` to add or change users after the file exists. Arrange a certificate renewal deploy hook that validates and reloads Nginx, reusing the server's existing hook if present. Test renewal before considering setup complete.

The final template applies [Nginx Basic Authentication](https://nginx.org/en/docs/http/ngx_http_auth_basic_module.html) to the entire HTTPS site, including media and PHP routes. It adds `X-Robots-Tag` and `Cache-Control: no-store`. Noindex is supplementary; the password gate controls access. Basic Authentication uses the `Authorization` header, so bearer-token API testing needs an alternative access gate such as a restricted network. Browser/session testing works with the default gate.

## 3. Install staging processes

```bash
sudo install -m 0644 /var/www/dearyou-staging/deploy/dearyou-staging-worker.service /etc/systemd/system/dearyou-staging-worker.service
sudo install -m 0644 /var/www/dearyou-staging/deploy/dearyou-staging-scheduler.service /etc/systemd/system/dearyou-staging-scheduler.service
sudo install -m 0644 /var/www/dearyou-staging/deploy/dearyou-staging-scheduler.timer /etc/systemd/system/dearyou-staging-scheduler.timer
sudo systemctl daemon-reload
sudo systemctl enable --now dearyou-staging-worker.service dearyou-staging-scheduler.timer
sudo systemctl status dearyou-staging-worker.service dearyou-staging-scheduler.timer --no-pager
```

Both processes must run from `/var/www/dearyou-staging/backend`. A separate worker is essential even with log email: queued messages appear in `storage/logs/dearyou-mail.log` only after the staging worker processes them. The scheduler can remove expired media, which is why its database and storage must stay separate.

## 4. Test the separation

Email and verification are the first feature priority. Use [the email delivery runbook](EMAIL_DELIVERY.md) to test the updated diagnostics and the complete queued verification flow before promoting changes.

- Without the staging access password, verify `/`, `/login`, `/up`, a static asset and a synthetic `/l/{token}/media/{hash}` URL return 401 on HTTPS. Historical managed `/storage/letters/`, `/storage/avatars/` and `/storage/memories/` paths are intentionally 404 even with the password.
- With access, verify the homepage and both creator/admin login paths. The staging badge should appear, and the session cookie should be `dearyou_staging_session` without a parent-domain cookie.
- Register a synthetic creator; read the verification email from the private staging mail log and complete verification. `dearyou:codes` is local-only and does not work in staging.
- Create a synthetic letter, upload image/audio/video media, publish it, reply, and test expiry/disable/regeneration. New public media requests must fail after revocation; authenticated owner previews should still work. Generated links must use `staging.dearyous.app`, with authorized media routes rather than direct static storage URLs.
- After uploaded images finish loading, save the letter and use its publish and link controls. Both creator and administrator flows must return to that letter's editor, including when the browser sends no `Referer`. Repeat a validation error and memory change: attachment or download requests must not replace the page used for navigation history.
- Submit marked feedback. Check the staging saved recipient setting and its queued message in the mail log. Log output tests the application/queue flow; it does not test inbox delivery.
- Inspect staging worker logs, `php artisan schedule:list`, and the staging timer's last run. Verify only the staging database gained the synthetic records.
- Confirm the live homepage and existing live accounts still work, with no staging records or sessions present.
- Confirm HTTPS, access protection and noindex/no-store headers on successful and error responses. Record the tested commit before promotion.

For a deliberate real-delivery test later, set staging `MAIL_MAILER=resend`, a dedicated send-only Resend key, an address on a verified sender domain, and a feedback inbox you control. Keep only synthetic accounts with controlled recipients. Check the saved staging feedback recipient, which overrides the environment fallback. Refresh configuration and restart only the staging worker. Do not treat a queued-success message as inbox confirmation.

## 5. Update staging and promote approved code

Review and check out the intended commit in `/var/www/dearyou-staging`, then run:

```bash
cd /var/www/dearyou-staging
git status --short
git rev-parse HEAD
REVISION=replace_with_the_reviewed_40_character_commit_hash bash deploy/deploy-staging.sh
```

The staging updater requires `REVISION` to match the clean checkout's full 40-character lowercase commit hash. It does not fetch, switch branches, seed data, modify Nginx, reload shared PHP-FPM or restart production services. It validates staging settings and unit definitions before changing anything, stops staging background tasks, installs locked dependencies without automatic install scripts, explicitly discovers packages, builds frontend assets and migrates staging. It then retries pending deletions and checksum-migrates staging legacy media to private storage as `www-data`, refreshes caches and restarts only staging services. A preflight rejection leaves the running site untouched. Later failures, including failures after reopening, enforce staging maintenance and stop its worker/timer. Inspect and repair the failure before rerunning. Authenticate sudo before running; service actions use noninteractive sudo.

The current UI also serves checked-in assets under `backend/public/assets`; include those files when reviewing UI changes even though the updater builds the Vite bundle. Shared PHP-FPM must use timestamp validation for changed files; deployments with disabled OPcache timestamp validation need a staging-specific PHP process/reload plan.

After staging QA passes, promote the same reviewed commit through a separate production deployment. Back up production first and review its installed worker path and any migrations. Do not run the production `deploy/deploy.sh` on the staging checkout: that script references production-named services and configuration.

The seven confirmed live QA defects were fixed in production release `8f1c618` and passed the focused live retest. Staging still needs provisioning and its own acceptance checks; creating the environment alone does not establish isolation or email correctness. Verification acceptance testing remains skipped in the recorded production QA. See [the dated project status](STATUS.md).

## Backups and recovery

Staging starts with synthetic data. If staging backups are needed, use a dedicated destination and explicitly configure the backup script's variables. `DEARYOU_BACKUP_DIR` configures the application health display; it does not configure `deploy/backup.sh`.

```bash
APP_DIR=/var/www/dearyou-staging/backend \
BACKUP_DIR=/var/backups/dearyou-staging \
DB_NAME=dearyou_staging \
MYSQL_DEFAULTS_FILE=/absolute/private/staging-backup-client.cnf \
WORKER_SERVICE=dearyou-staging-worker.service \
SCHEDULER_SERVICE=dearyou-staging-scheduler.service \
SCHEDULER_TIMER=dearyou-staging-scheduler.timer \
WRITERS_PAUSED=1 bash /var/www/dearyou-staging/deploy/backup.sh
```

Follow [OPERATIONS.md](OPERATIONS.md), supplying the staging database, protected MySQL option file, private backup destination and all three staging service names. Enter staging maintenance and stop/drain staging writers first. There is no automatic retention deletion. Verify restoration with a separately restricted scratch account; never target production. Rollbacks must account for schema and private-media changes.

## Local validation completed

The 16 October 2026 navigation and expiry fixes passed the complete backend suite: **325 tests and 2,541 assertions**. Included are 46 navigation regression cases and three expiry-input cases. The fixes keep media and keepsake requests out of page history, redirect letter/memory changes to the correct creator or administrator editor, and normalize expiry timestamps consistently for letters and links. Live staging still requires the owner to deploy these fixes and repeat the browser acceptance checks.

The staging-first update passed **129 boundary assertions on Linux PHP** (115 configuration assertions on Windows, with Linux-only filesystem cases skipped), **32 integration assertions using the real Laravel fresh/cached configuration**, and **9 mocked staging-deployment tests**. The integration fixture checked poisoned caches and inherited/fresh environment overrides without changing fixture files or booting application providers/database clients. Deployment regressions checked early rejections, exact commit/clean checkout, unit guards, install flags, media migration, and recovery from failures both before and after reopening. Git, database, dependency installers and service writers were mocked in those deployment tests.

To repeat on Linux/WSL with PHP 8.4.1+ and this checkout's Composer dependencies:

```bash
php deploy/tests/check-staging-test.php
php scripts/test-staging-config.php
python3 deploy/tests/staging-deploy-test.py
bash -n deploy/deploy-staging.sh
```

The staging Nginx template is unchanged by this update. Its earlier isolated Nginx checks remain historical evidence; DNS, real HTTPS certificates, installed services, MySQL privileges and server capacity still need owner verification. No staging website, commit, deployment, production change or Cloudflare change was performed during local preparation.

On 14 October 2026, the staging guard passed 75 boundary assertions, PHP/Bash syntax checks and rejection checks from a non-staging path. An isolated Ubuntu Nginx 1.24 test passed its syntax check and 12 localhost authentication/header/routing checks using a synthetic certificate and password; its temporary server was stopped afterward. All 11 changed staging-badge views compiled and passed PHP lint, and the badge rendered only for staging. The combined backend suite passed 107 tests and 890 assertions after the email changes.

The systemd files were parsed locally, but runtime verification could not complete because that WSL environment lacks `/usr/bin/php`. Installed systemd execution, real TLS/DNS, PHP-FPM and live/staging isolation must still be checked on the server. These local checks do not mean the staging website is online. The guard's 75 boundary assertions and PHP/Bash syntax checks passed again during the 16 October repository cleanup; this does not constitute a server deployment.
