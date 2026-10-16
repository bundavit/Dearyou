# Live and staging websites

The intended setup is two independent deployments of the same repository. Test a reviewed commit on staging, then promote that same commit to production after QA. Do not copy staging databases, uploads, `.env`, or cached configuration into production.

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

This plan can use the existing Ubuntu/Nginx/PHP 8.4 server, subject to available capacity. Composer and a Node/npm version compatible with the locked Vite dependencies are also required. It separates application data and configuration but still shares the machine, PHP-FPM, and `www-data` identity. Use a separate server or separately isolated processes if staging will execute untrusted code or load tests. The staging updater does not reload shared PHP-FPM or edit production services.

## 1. Provision a fresh staging checkout and database

The following commands are a server runbook, not commands already executed. Use the existing deployment user, replacing `deploy` if needed. Publish the reviewed staging setup files to the repository before cloning. Replace `REPOSITORY_URL` with the repository URL and select the commit intended for testing.

```bash
sudo install -d -o deploy -g www-data -m 0750 /var/www/dearyou-staging
git clone REPOSITORY_URL /var/www/dearyou-staging
cd /var/www/dearyou-staging
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

Set a unique `DB_PASSWORD` and `ADMIN_PASSWORD`. Keep the exact staging database/user, hostname, cookie and cache-prefix values from the example. Keep `MAIL_MAILER=log`, `MAIL_LOG_CHANNEL=mail`, `LOG_LEVEL=debug`, and an empty Resend key for initial testing. Do not copy the production `.env` or its key.

Generate the staging encryption key once, then run the read-only configuration check:

```bash
php artisan key:generate
php ../deploy/check-staging.php
```

The check validates the effective configuration and filesystem boundaries without writing to a database. It cannot prove a key differs from production without inspecting production secrets; generating a new key here provides that separation. If it reports an error, correct staging configuration before continuing.

Create the staging schema and initial administrator **before** caching configuration:

```bash
php artisan package:discover --ansi
npm ci
npm run build
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache
sudo chown -R deploy:www-data /var/www/dearyou-staging/backend
sudo find storage bootstrap/cache -type d -exec chmod 2770 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 0660 {} \;
chmod 640 .env
php ../deploy/check-staging.php
```

Seed only once. The current seeder reads admin credentials directly from `.env` and resets platform defaults, so do not add seeding to routine deployments. Cached configuration skips loading `.env`; clear staging configuration first if a failed initial setup needs seeding again. Laravel documents this behavior in its [deployment guidance](https://laravel.com/framework/docs/deployment).

## 2. Create staging DNS and HTTPS

Create an `A` record for `staging.dearyous.app` pointing to the selected server. Add an `AAAA` record only if that server's IPv6 address is configured. Preserve the existing production and Resend DNS records.

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

- Without the staging access password, verify `/`, `/login`, `/up`, a static asset and a known synthetic upload return 401 on HTTPS.
- With access, verify the homepage and both creator/admin login paths. The staging badge should appear, and the session cookie should be `dearyou_staging_session` without a parent-domain cookie.
- Register a synthetic creator; read the verification email from the private staging mail log and complete verification. `dearyou:codes` is local-only and does not work in staging.
- Create a synthetic letter, upload an image/audio file, publish it, reply, and test expiry. All generated links and uploads must use `staging.dearyous.app`.
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
bash deploy/deploy-staging.sh
```

The staging updater deploys the code already checked out. It does not fetch, switch branches, seed data, modify Nginx, or restart production services. It validates staging settings and service paths before changing anything, stops staging background tasks, installs locked PHP/Node dependencies, builds frontend assets, migrates staging, refreshes caches and restarts staging processes. A preflight rejection leaves the running site untouched. After deployment begins, failures leave staging in maintenance with its worker/timer stopped; inspect the failure, correct it and rerun. Do not blindly run `artisan up` against a partially migrated deployment. Authenticate sudo before running it; service operations use noninteractive sudo and refuse before changes if authorization is unavailable.

The current UI also serves checked-in assets under `backend/public/assets`; include those files when reviewing UI changes even though the updater builds the Vite bundle. Shared PHP-FPM must use timestamp validation for changed files; deployments with disabled OPcache timestamp validation need a staging-specific PHP process/reload plan.

After staging QA passes, promote the same reviewed commit through a separate production deployment. Back up production first and review its installed worker path and any migrations. Do not run the production `deploy/deploy.sh` on the staging checkout: that script references production-named services and configuration.

The seven confirmed live QA defects were fixed in production release `8f1c618` and passed the focused live retest. Staging still needs provisioning and its own acceptance checks; creating the environment alone does not establish isolation or email correctness. Verification acceptance testing remains skipped in the recorded production QA. See [the dated project status](STATUS.md).

## Backups and recovery

Staging starts with synthetic data. If staging backups are needed, use a dedicated destination and explicitly configure the backup script's variables. `DEARYOU_BACKUP_DIR` configures the application health display; it does not configure `deploy/backup.sh`.

```bash
sudo env APP_DIR=/var/www/dearyou-staging/backend BACKUP_DIR=/var/backups/dearyou-staging /bin/bash /var/www/dearyou-staging/deploy/backup.sh
```

Inspect the backup script and ensure only staging credentials and paths are used before scheduling it. Its retention policy applies to `BACKUP_DIR`. Verify restoration into a disposable staging database; never point a staging restore at production. Rollbacks must account for schema changes, not just switch code commits.

## Local validation completed

On 14 October 2026, the staging guard passed 75 boundary assertions, PHP/Bash syntax checks and rejection checks from a non-staging path. An isolated Ubuntu Nginx 1.24 test passed its syntax check and 12 localhost authentication/header/routing checks using a synthetic certificate and password; its temporary server was stopped afterward. All 11 changed staging-badge views compiled and passed PHP lint, and the badge rendered only for staging. The combined backend suite passed 107 tests and 890 assertions after the email changes.

The systemd files were parsed locally, but runtime verification could not complete because that WSL environment lacks `/usr/bin/php`. Installed systemd execution, real TLS/DNS, PHP-FPM and live/staging isolation must still be checked on the server. These local checks do not mean the staging website is online. The guard's 75 boundary assertions and PHP/Bash syntax checks passed again during the 16 October repository cleanup; this does not constitute a server deployment.
