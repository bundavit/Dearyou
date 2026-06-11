# DearYou deployment

Deployment is intentionally the final project stage. Use Ubuntu, Nginx, PHP-FPM, Composer, and MySQL on a DigitalOcean Droplet.

1. Point the Nginx document root to `public/`.
2. Copy `.env.example` to `.env`, use production database credentials, set `APP_ENV=production`, `APP_DEBUG=false`, and a correct `APP_URL`.
3. Run `composer install --no-dev --optimize-autoloader`, `php artisan key:generate`, and `php artisan migrate --force`.
4. Create the first admin with `ADMIN_EMAIL` and `ADMIN_PASSWORD`, then run `php artisan db:seed --force`. Change the seeded password immediately.
5. Give the web user write access to `storage/` and `bootstrap/cache/`.
6. Run `php artisan optimize` and configure HTTPS with Certbot/Let's Encrypt.
7. Back up MySQL and `.env`; never commit `.env`.

For API access, create a Sanctum token for the admin in a trusted console and send it as `Authorization: Bearer TOKEN`.

Included production templates:

- `deploy/nginx-dearyou.conf`
- `deploy/dearyou-worker.service`
- `deploy/backup.sh`
- `.env.production.example`

Before deployment, replace every example domain and credential, enable HTTPS, test a backup restore, and change the seeded admin password.
