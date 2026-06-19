# DearYou

DearYou is a Laravel app for creating private digital letters that feel more personal than a text message. Creators can write letters, style them, add media and memories, publish time-limited links, and receive private replies. The project also includes a platform-admin area for moderation, storage settings, feedback review, and audit history.

`README.md` is the main text GitHub shows on your repository homepage, so this file is the right place for setup steps, feature overview, and public project notes.

## What DearYou includes

- Public homepage with product intro, occasions, FAQs, and feedback form
- Creator accounts with registration, email verification, login, and password reset by code
- Private letters with themes, fonts, envelopes, seals, media, music, memories, and response options
- Time-limited share links with open tracking and inbox replies
- Platform admin area for users, moderation, feedback, audit logs, and app-wide settings
- DigitalOcean-ready deployment files for Nginx, queue workers, scheduler, and backups

## Local setup

Requirements:

- PHP 8.4+
- Composer
- Node.js + npm
- MySQL or SQLite

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan storage:link
```

Before seeding, set your local admin login in `.env`:

```dotenv
ADMIN_EMAIL=admin@dearyou.test
ADMIN_PASSWORD=your-local-admin-password
```

Then finish setup:

```bash
php artisan migrate --seed
npm run build
php artisan serve --port=8001
```

Open `http://127.0.0.1:8001/`.

Useful local routes:

- `/` public homepage
- `/register` creator account signup
- `/login` creator sign-in
- `/dashboard` account redirect
- `/letters` creator letters
- `/admin/login` platform admin login

## Public repo safety

This repository is set up so you can keep it public, but only if you continue following a few rules:

- Never commit `.env`
- Never commit production database dumps or uploaded media
- Never commit API tokens, Resend keys, SSH keys, or backup archives
- Rotate any secret immediately if it was ever pasted into Git, screenshots, or chat logs

Already ignored by Git:

- `.env` and all local env variants
- local auth files
- SQLite/MySQL dumps and backup archives
- uploaded files under `storage/app`
- runtime logs, cache, sessions, and generated build artifacts

## Quality checks

```bash
php artisan test
vendor/bin/pint --test
composer audit
npm audit
```

## Deployment

The production server guide is in [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

It covers:

- Ubuntu + Nginx + PHP-FPM + MySQL setup
- GitHub deploy key cloning
- Laravel production config
- HTTPS with Certbot
- queue worker and scheduler setup
- backup script usage

## Notes for production

- Use real email delivery before opening public registration
- Set a strong `ADMIN_PASSWORD` and private `RESEND_API_KEY`
- Run backups outside the repo and copy them to a second location
- Keep the repository private until you confirm no secrets were ever committed in history

## License

No license file is currently included. If you plan to make the repository public for others to reuse, add a license before sharing it widely.
