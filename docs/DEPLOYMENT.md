# DearYou on DigitalOcean

Use an Ubuntu 24.04 Droplet with Nginx, PHP 8.4, and MySQL. A Droplet is used
instead of App Platform because DearYou stores private images, GIFs, and music
under `storage/app/public`; a Droplet keeps those uploads on persistent disk.

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

sudo -u deploy git clone git@github.com:Vitkayo/Dearyou.git /var/www/dearyou
cd /var/www/dearyou
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
```

Passwords containing `#`, spaces, or other punctuation must stay inside quotes.
Never commit the production `.env`.

Initialize Laravel and the first admin:

```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan optimize

sudo chown -R deploy:www-data /var/www/dearyou
sudo find storage bootstrap/cache -type d -exec chmod 2775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 0664 {} \;
```

Run `db:seed` only for the initial installation. Running it repeatedly creates
more sample data.

## 5. Configure Nginx

```bash
sudo cp deploy/nginx-dearyou.conf /etc/nginx/sites-available/dearyou
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
cd /var/www/dearyou
php artisan optimize
```

## 7. Queue worker and updates

Install the included worker:

```bash
sudo cp deploy/dearyou-worker.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now dearyou-worker
```

For future GitHub updates:

```bash
cd /var/www/dearyou
chmod +x deploy/deploy.sh
./deploy/deploy.sh
```

The deployment script pulls `main`, installs production dependencies, runs
migrations, refreshes Laravel caches, fixes writable-directory permissions,
and restarts PHP and the queue worker.

## 8. Backups

The included backup script saves both MySQL and uploaded media:

```bash
sudo chmod +x /var/www/dearyou/deploy/backup.sh
sudo mkdir -p /var/backups/dearyou
sudo /var/www/dearyou/deploy/backup.sh
```

Schedule it only after testing a backup and restore:

```bash
sudo crontab -e
```

Example daily schedule:

```cron
30 2 * * * /var/www/dearyou/deploy/backup.sh >> /var/log/dearyou-backup.log 2>&1
```

Copy backups to a second location. A backup stored only on the same Droplet is
not enough if the Droplet is lost.

## Production checklist

```bash
curl -I https://dearyous.app/up
sudo systemctl status nginx php8.4-fpm mysql dearyou-worker
cd /var/www/dearyou
php artisan about
```

- Test admin login.
- Upload an image, GIF, and music file.
- Open a public letter link.
- Submit and read a response.
- Create a new Postman token; local API tokens are not transferred.
- Enable DigitalOcean Droplet backups or snapshots.
