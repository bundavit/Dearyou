#!/usr/bin/env bash
set -euo pipefail

REPO_DIR="${REPO_DIR:-/var/www/dearyou}"
APP_DIR="${APP_DIR:-$REPO_DIR/backend}"
BRANCH="${BRANCH:-main}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.4-fpm}"

cd "$REPO_DIR"

CURRENT_APP_DIR="$APP_DIR"
if [[ ! -f "$CURRENT_APP_DIR/artisan" && -f "$REPO_DIR/artisan" ]]; then
    CURRENT_APP_DIR="$REPO_DIR"
fi

report_failed_deployment() {
    local status=$?
    if [[ "$status" -ne 0 ]]; then
        echo "DearYou deployment failed. This script has not cleared maintenance mode." >&2
        echo "Fix the failing step and rerun the deployment. After any rollback, verify the application and worker before running 'php artisan up' in $APP_DIR." >&2
    fi
}
trap report_failed_deployment EXIT

cd "$CURRENT_APP_DIR"
php artisan down --retry=60

cd "$REPO_DIR"

git fetch origin "$BRANCH"
git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"

cd "$APP_DIR"

composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

sudo install -m 0644 "$REPO_DIR/deploy/dearyou-uploads.ini" /etc/php/8.4/fpm/conf.d/99-dearyou-uploads.ini
sudo install -m 0644 "$REPO_DIR/deploy/dearyou-uploads.ini" /etc/php/8.4/cli/conf.d/99-dearyou-uploads.ini
sudo install -m 0644 "$REPO_DIR/deploy/dearyou-scheduler.service" /etc/systemd/system/dearyou-scheduler.service
sudo install -m 0644 "$REPO_DIR/deploy/dearyou-scheduler.timer" /etc/systemd/system/dearyou-scheduler.timer
sudo install -m 0644 "$REPO_DIR/deploy/dearyou-worker.service" /etc/systemd/system/dearyou-worker.service
sudo systemctl daemon-reload
sudo systemctl enable --now dearyou-scheduler.timer
if [[ -f /etc/nginx/sites-available/dearyou ]]; then
    sudo sed -i 's#/var/www/dearyou/public#/var/www/dearyou/backend/public#g' /etc/nginx/sites-available/dearyou
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
sudo systemctl enable dearyou-worker.service
sudo systemctl restart dearyou-worker.service
sudo systemctl is-active --quiet dearyou-worker.service

php artisan up
trap - EXIT

echo "DearYou deployment complete."
