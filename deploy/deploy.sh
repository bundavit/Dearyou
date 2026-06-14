#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/dearyou}"
BRANCH="${BRANCH:-main}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.4-fpm}"

cd "$APP_DIR"

restore_application() {
    php artisan up >/dev/null 2>&1 || true
}
trap restore_application EXIT

php artisan down --retry=60 || true

git fetch origin "$BRANCH"
git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"

composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

sudo install -m 0644 deploy/dearyou-uploads.ini /etc/php/8.4/fpm/conf.d/99-dearyou-uploads.ini
sudo install -m 0644 deploy/dearyou-uploads.ini /etc/php/8.4/cli/conf.d/99-dearyou-uploads.ini
sudo install -m 0644 deploy/dearyou-scheduler.service /etc/systemd/system/dearyou-scheduler.service
sudo install -m 0644 deploy/dearyou-scheduler.timer /etc/systemd/system/dearyou-scheduler.timer
sudo systemctl daemon-reload
sudo systemctl enable --now dearyou-scheduler.timer
if [[ -f /etc/nginx/sites-available/dearyou ]]; then
    sudo sed -i -E 's/client_max_body_size[[:space:]]+[0-9]+[KMG];/client_max_body_size 128M;/' /etc/nginx/sites-available/dearyou
    sudo nginx -t
    sudo systemctl reload nginx
fi

php artisan migrate --force
php artisan storage:link
php artisan optimize

sudo chown -R "$(id -un)":www-data "$APP_DIR"
sudo find storage bootstrap/cache -type d -exec chmod 2775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 0664 {} \;

sudo systemctl reload "$PHP_FPM_SERVICE"
if systemctl list-unit-files --type=service | grep -q '^dearyou-worker.service'; then
    sudo systemctl restart dearyou-worker
fi

php artisan up
trap - EXIT

echo "DearYou deployment complete."
