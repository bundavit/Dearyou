# DearYou

DearYou is a private digital-letter application for confessions, apologies, birthdays, anniversaries, celebrations, and custom messages.

## Local setup

Requirements: PHP 8.4.1+, Composer, Node.js, npm, and either SQLite or MySQL.

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan storage:link
php artisan migrate --seed
npm run build
php artisan serve
```

Open `http://127.0.0.1:8000/admin/login`.

Default local seeded account:

- Email: `admin@dearyou.test`
- Password: `ChangeMe123!`

Change this password immediately from **Account**.

## Quality checks

```bash
php artisan test
vendor/bin/pint --test
npm audit
composer audit
```

## API

Create a read-only or read/write token under **Admin > Account**. Import `postman/DearYou.postman_collection.json` into Postman and set its `token` variable.

## Production

The complete DigitalOcean Droplet guide is in `docs/DEPLOYMENT.md`. It includes
server installation, MySQL, Nginx, HTTPS, deployment updates, and backups.
Never commit `.env`, database exports, API tokens, or uploaded private images.
