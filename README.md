# DearYou

DearYou is a private digital letter platform built with Laravel. It lets people create styled letters, attach media and memories, publish expiring private links, receive recipient reactions or replies, and keep an offline keepsake copy of the letter.

Live site: <https://dearyous.app>

## Portfolio Summary

I built DearYou as a full-stack Laravel application focused on privacy, emotional presentation, and admin control. The project includes creator accounts, public recipient links, media-rich letters, response tracking, storage limits, moderation tools, audit history, and production deployment scripts for a DigitalOcean server.

The main product challenge was balancing a personal, visual recipient experience with the practical needs of a multi-user platform: authentication, rate limits, upload storage, expiring links, moderation, backups, and operational health checks.

## Core Features

- Account registration, login, password reset, and email verification by code
- Creator letter dashboard with private drafts and published share links
- Styled letters with themes, fonts, envelopes, seals, music, media, and memories
- Recipient page with open tracking, reaction responses, optional private replies, and thank-you states
- Keepsake HTML downloads that embed image, GIF, or video media for offline viewing
- User inbox for replies, unread states, filters, and bulk actions
- Admin platform tools for users, settings, feedback, moderation, storage limits, expiry options, and platform stats
- Moderation audit logs, soft deletes, account suspension, storage cleanup, and deployment health checks

## Tech Stack

- Laravel 13, PHP 8.4, Blade, and Laravel Sanctum
- MySQL in production, SQLite-friendly feature tests
- Custom CSS and JavaScript for the public, creator, and admin interfaces
- Vite build pipeline for compiled frontend assets
- Resend for transactional email
- Queues, scheduler, storage links, and Laravel rate limiting
- Nginx, PHP-FPM, MySQL, and DigitalOcean for production hosting

## Architecture Notes

- Public routes handle the homepage, feedback, authentication, and `/l/{token}` recipient links.
- Creator routes are protected by authentication, active-account checks, email verification, and the `user` role.
- Platform admin routes are protected by authentication, active-account checks, email verification, the `admin` role, and optional network allowlisting.
- Media uploads are stored outside the Git repository and counted against configurable storage limits.
- Expiring letter links can be unpublished, regenerated, disabled, or moderated by admins.
- Keepsake downloads embed media directly into the saved HTML so the downloaded file can work without the original private link.

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

Useful local routes:

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
npm audit --omit=dev
npm run build
```

Current audit status:

- Feature flow test: 84 tests passing
- Laravel Pint: passing
- Composer audit: no advisories
- npm production audit: no vulnerabilities
- Vite production build: passing

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

- `.env`, uploads, backups, database dumps, local caches, and build output are ignored
- Production secrets are kept on the server, not in the repository
- Public vendor assets are marked so GitHub language stats focus on the application code
- Generated local keepsake downloads and review exports are ignored
- Any exposed secret should be rotated immediately, even if it is later removed from Git

## License

No license file is currently included. Add one before sharing this project for reuse outside the portfolio context.
