#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/dearyou}"
BRANCH="${BRANCH:-main}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.3-fpm}"

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
