# DearYou on DigitalOcean

**Last confirmed production: 15 October 2026.** Release `8f1c618` runs from `/var/www/dearyou/backend`, with Nginx serving `/var/www/dearyou/backend/public`. The owner restarted the worker, confirmed three feedback emails, and the admin queue drained to zero. Verification remains untested. See [STATUS.md](STATUS.md) and [the remaining work list](ROADMAP.md). Preparing a new Git commit does not deploy it.

## Updating the existing production website

Production is already installed. Preserve its `.env`, APP_KEY, database, uploads, TLS configuration and backend-path services. The root-to-backend migration is complete; do not replay it.

The owner performs Git and server operations. Each future deployment needs:

1. A reviewed, pinned commit and a clean server checkout; inspect migrations and dependency changes before scheduling the update.
2. A new private application/configuration/database backup with a verified restore into an isolated temporary database. Preserve newer data during any rollback.
3. Maintenance and a controlled stop of background writers before the final data snapshot and release change. Record the existing scheduler/worker state.
4. Locked dependencies and reviewed migrations. Keep the existing settings and key, do not seed production, and do not clear the feedback-budget cache.
5. Separate `config:cache`, `event:cache`, `route:cache` and `view:cache` commands so a component failure can stop deployment. Keep runtime directories group-writable without making cached configuration readable to every local account.
6. Validated Nginx/PHP settings, maintenance-response checks, then homepage/login/health and relevant feature checks before reopening and restoring background processing.

The legacy `deploy/deploy.sh` is retained for history and its mock tests, but is **not the recommended production update path**. It follows moving `main`, does not supply a fresh verified backup or fully stop background writers, and applies overly broad cached-configuration permissions. It needs a separate hardening change before use as unattended deployment automation. The legacy backup helper also needs review before reuse; see the backup section below.

Owner-run read-only checks for a future deployment session:

```bash
cd /var/www/dearyou
git status --short --branch
git rev-parse HEAD
systemctl show dearyou-worker.service dearyou-scheduler.timer \
  --property=Id,ActiveState,SubState,WorkingDirectory,ExecStart --no-pager
nginx -t
```

Review private configuration and logs on the server; share only sanitized status. Do not print `.env` or raw queued messages.

## New-server setup reference

Sections 1-6 below are for an empty new server, not an update or USB restore. Generate keys and seed an administrator only during a genuinely new installation.

For a separate testing website at `staging.dearyous.app`, use [the staging runbook](STAGING.md). Its database, environment file, storage, worker and scheduler are separate from the production setup below.

Use an Ubuntu 24.04 Droplet with Nginx, PHP 8.4, and MySQL. A Droplet is used
instead of App Platform because DearYou stores private images, GIFs, and music
under `backend/storage/app/public`; a Droplet keeps those uploads on persistent disk.

## 1. Create the Droplet

In DigitalOcean:

1. Create an Ubuntu 24.04 Droplet.
2. Choose 1 GB RAM with a 2 GB swap file for a small installation, or 2 GB RAM
   for more comfortable Composer and MySQL operation.
3. Add an SSH key.
4. Add a DigitalOcean Cloud Firewall allowing SSH (22), HTTP (80), and HTTPS
   (443). Restrict SSH to your IP when possible.
5. Point your domain's `A` record to the Droplet IP.

Connect from your computer:

```bash
ssh root@YOUR_DROPLET_IP
```

## 2. Install the server packages

```bash
apt update && apt upgrade -y
apt install -y nginx mysql-server git unzip curl software-properties-common
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring \
  php8.4-xml php8.4-curl php8.4-zip php8.4-gd php8.4-bcmath

curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm /tmp/composer-setup.php
```

Create a non-root deployment user:

```bash
adduser deploy
usermod -aG sudo,www-data deploy
mkdir -p /home/deploy/.ssh
cp /root/.ssh/authorized_keys /home/deploy/.ssh/authorized_keys
chown -R deploy:deploy /home/deploy/.ssh
chmod 700 /home/deploy/.ssh
chmod 600 /home/deploy/.ssh/authorized_keys
```

Reconnect:

```bash
ssh deploy@YOUR_DROPLET_IP
```

## 3. Create MySQL

Generate a long random database password and keep it private.

```bash
sudo mysql
```

Run inside MySQL:

```sql
CREATE DATABASE dearyou CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'dearyou'@'localhost' IDENTIFIED BY 'YOUR_LONG_DB_PASSWORD';
GRANT ALL PRIVILEGES ON dearyou.* TO 'dearyou'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## 4. Clone DearYou

The repository must be pushed to GitHub first. It can remain private. For a
private repository, give the deployment user a read-only GitHub deploy key:

```bash
sudo mkdir -p /var/www
sudo chown deploy:www-data /var/www
sudo -u deploy mkdir -p /home/deploy/.ssh
sudo -u deploy ssh-keygen -t ed25519 -C "dearyou-droplet" \
  -f /home/deploy/.ssh/github_dearyou -N ""
sudo -u deploy cat /home/deploy/.ssh/github_dearyou.pub
```

Copy the displayed public key into GitHub under **Repository Settings > Deploy
keys > Add deploy key**. Name it `DearYou Droplet` and leave write access
disabled. Then configure SSH and clone:

```bash
sudo -u deploy ssh-keyscan github.com >> /home/deploy/.ssh/known_hosts
sudo -u deploy chmod 600 /home/deploy/.ssh/known_hosts
sudo -u deploy sh -c 'cat > /home/deploy/.ssh/config <<EOF
Host github.com
    HostName github.com
    User git
    IdentityFile /home/deploy/.ssh/github_dearyou
    IdentitiesOnly yes
EOF'
sudo -u deploy chmod 600 /home/deploy/.ssh/config

sudo -u deploy git clone git@github.com:bundavit/Dearyou.git /var/www/dearyou
cd /var/www/dearyou/backend
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
cp .env.production.example .env
nano .env
```

Do not enter your GitHub account password in `git clone`. GitHub HTTPS clones
require a token, while the read-only deploy key above avoids storing a personal
token on the server.

Set these values in `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://dearyous.app

DB_HOST=127.0.0.1
DB_DATABASE=dearyou
DB_USERNAME=dearyou
DB_PASSWORD="YOUR_LONG_DB_PASSWORD"

ADMIN_EMAIL=YOUR_ADMIN_EMAIL
ADMIN_PASSWORD="YOUR_ADMIN_PASSWORD"

MAIL_MAILER=resend
RESEND_API_KEY="YOUR_RESEND_API_KEY"
MAIL_FROM_ADDRESS=hello@dearyous.app
MAIL_FROM_NAME="${APP_NAME}"
```

Passwords containing `#`, spaces, or other punctuation must stay inside quotes.
Never commit the production `.env`.

Public registration requires real email delivery because new creators must
verify their email address. DearYou uses Resend's HTTPS API so deployment does
not depend on outbound SMTP ports. Verify `dearyous.app` in Resend, add its SPF
and DKIM records, and create an API key. A Gmail or Google Workspace mailbox is
not required.

Initialize Laravel and the first admin:

```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache

sudo chown -R deploy:www-data /var/www/dearyou/backend
sudo find storage bootstrap/cache -type d -exec chmod 2770 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 0660 {} \;
sudo chmod 0640 .env
```

Run `db:seed` only for the initial installation. Running it repeatedly creates
more sample data.

## 5. Configure Nginx

```bash
sudo cp /var/www/dearyou/deploy/nginx-dearyou.conf /etc/nginx/sites-available/dearyou
sudo nano /etc/nginx/sites-available/dearyou
```

The included configuration already uses `dearyous.app` and
`www.dearyous.app`. Enable it:

```bash
sudo ln -s /etc/nginx/sites-available/dearyou /etc/nginx/sites-enabled/dearyou
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
```

Before DNS and HTTPS are ready, you can temporarily use the Droplet IP as
`server_name` and visit `http://YOUR_DROPLET_IP`.

## 6. Enable HTTPS

After the domain points to the Droplet:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d dearyous.app -d www.dearyous.app
sudo certbot renew --dry-run
```

Confirm `.env` uses `APP_URL=https://dearyous.app`, then run:

```bash
cd /var/www/dearyou/backend
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache
```

## 7. Queue worker and updates

Install the included worker:

```bash
sudo cp /var/www/dearyou/deploy/dearyou-worker.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now dearyou-worker
```

For future source updates, use the owner-reviewed deployment process at the top of this guide. Installing the first worker does not make the legacy updater suitable for an existing-data release.

## 8. Backups

Keep application/settings/service/TLS and database backups private, outside the web root and repository. Use a protected MySQL option file or server socket authentication; do not source a Laravel `.env` as shell code or put its password on the command line. Verify SQL restoration into a separate temporary database and verify upload/configuration preservation before relying on the backup.

`deploy/backup.sh` is a legacy helper. Its current implementation sources `.env`, exposes a database password as a command argument, and applies retention deletion to its destination. It is not the current backup recommendation and needs a separate reviewed replacement before scheduling. The existing verified production snapshots are historical recovery assets, not fresh backups for a later deployment.

Keep an off-server copy and record the source revision, data snapshot time, service configuration and restore procedure. A code rollback does not authorize restoring an old database over newer records.

## Production checklist

```bash
curl -I https://dearyous.app/up
sudo systemctl status nginx php8.4-fpm mysql dearyou-worker
cd /var/www/dearyou/backend
php artisan about
```

- Test admin login.
- Upload an image, GIF, and music file.
- Open a public letter link.
- Submit and read a response.
- Create a new Postman token; local API tokens are not transferred.
- Enable DigitalOcean Droplet backups or snapshots.
