# DearYou

DearYou is a multi-user digital letter platform for creating private letters that feel more personal than a text message. Creators can write letters, add media and memories, choose envelope, seal, and theme styles, publish expiring private links, receive recipient responses, and track opens.

Live site: <https://dearyous.app>

## Highlights

- Creator accounts with registration, email verification by code, login, and password reset by code
- Private letters with themes, fonts, envelopes, seals, media, music, memories, and optional replies
- Expiring public share links with open tracking and recipient response capture
- Keepsake HTML downloads with embedded image, GIF, or video media for offline viewing
- Platform admin area for users, settings, feedback, moderation, audit logs, storage limits, expiry options, and stats
- DigitalOcean-ready deployment files for Nginx, PHP-FPM, Laravel queues, scheduler, and backups

## Tech Stack

- Laravel 13 and PHP 8.4
- MySQL in production, SQLite-friendly local testing
- Blade templates with custom CSS and JavaScript
- Laravel Sanctum, queues, scheduler, and storage links
- Resend for transactional email
- Nginx, PHP-FPM, and DigitalOcean for production hosting

## Local Setup

Requirements:

- PHP 8.4+
- Composer
- Node.js and npm
- MySQL or SQLite

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan storage:link
```

Set local admin credentials in `.env`:

```dotenv
ADMIN_EMAIL=admin@dearyou.test
ADMIN_PASSWORD=your-local-admin-password
```

Finish setup:

```bash
php artisan migrate --seed
npm run build
php artisan serve --port=8001
```

Open `http://127.0.0.1:8001/`.

Useful routes:

- `/` public homepage
- `/register` creator signup
- `/login` creator sign-in
- `/letters` creator letters
- `/admin/login` platform admin login

## Quality Checks

```bash
php artisan test tests/Feature/DearYouFlowTest.php
vendor/bin/pint --test
composer audit
npm audit
```

## Deployment

Production deployment notes are in [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

The production flow is:

```bash
git pull --ff-only origin main
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
sudo systemctl restart php8.4-fpm
sudo systemctl restart dearyou-worker
sudo systemctl reload nginx
```

## Repository Safety

- Never commit `.env`, API tokens, SSH keys, database dumps, backup archives, or uploaded user media
- Keep production secrets in server environment files only
- Store backups outside the repository and copy them to a second private location
- Rotate any secret immediately if it is ever committed, pasted, or shown in a screenshot

## License

No license file is currently included. Add one before sharing this project for reuse outside the portfolio context.
