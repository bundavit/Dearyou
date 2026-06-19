# DearYou Project Overview

DearYou is a private letter and special-message platform. Creators can write a personal letter, customize the experience, add media, publish a private random link, and receive private responses from recipients.

The project began as a single-admin confession website and has grown into a multi-user platform with creator accounts, platform administration, storage limits, feedback, analytics, and deployment support.

## Core Idea

DearYou helps people create messages that feel more personal than a normal text message.

Common use cases include:

- Confessions
- Apologies
- Birthdays
- Anniversaries
- Valentine messages
- Congratulations
- Thank-you notes
- Friendship letters
- Custom personal notes

Recipients do not need an account. They only need the private letter link.

## Public Visitor Flow

1. A visitor opens the homepage.
2. They learn what DearYou does through the homepage, occasion section, feature section, and how-it-works section.
3. They can send private feedback from the homepage.
4. If they want to create a letter, they create an account or log in.

## Creator Flow

1. A creator registers or logs in.
2. New accounts verify their email using a code sent by email.
3. The creator enters the My DearYou area.
4. They create a letter.
5. They choose an occasion, title, recipient name, sender name, and message.
6. They customize the design with theme, font, colors, decorations, envelope style, and seal shape.
7. They optionally add image, GIF, short video, music, profile pictures, and memory images.
8. They configure recipient responses.
9. They preview the recipient experience.
10. They publish the letter with an expiration duration.
11. DearYou creates a random private link.
12. The creator shares the link.
13. Recipient responses appear in the creator inbox.

Creators can also:

- Edit letters
- View letters
- Delete letters
- Unpublish links
- Regenerate links
- Republish expired links
- Track letter open counts
- Manage profile details
- Delete their account after confirming with password and the word `delete`

## Recipient Flow

1. A recipient opens a private letter link.
2. If the link is active, they see the envelope page.
3. They open the envelope.
4. The letter is revealed.
5. If music is attached, it starts after the envelope is opened.
6. If responses are enabled, they can reply using buttons or a message.
7. Their response is sent privately to the creator inbox.

Recipients cannot see the creator account email or admin tools.

If a letter is expired, disabled, unpublished, or owned by a suspended/deleted creator, the recipient sees a gentle unavailable page.

## Platform Admin Flow

Platform admins use the `/admin` area.

Admins can:

- View platform dashboard statistics
- See homepage visit count
- Manage users
- See user letter counts and storage usage
- Suspend and reactivate accounts
- Soft delete and restore users
- Permanently delete soft-deleted users
- Review feedback
- Moderate letters
- Disable public letter access
- View moderation audit logs
- Manage platform settings

Admin settings include:

- Allowed publishing durations
- Custom publishing windows
- Default publishing duration
- Enabled letter occasions
- Storage allowance
- Upload limits for image, GIF, video, audio, profile picture, and memory files
- Cleanup warning period
- Automatic expired-media cleanup policy

Admins should not casually read private letter content. Revealing private letter content requires a deliberate moderation action and is recorded in the audit log. Recipient response text stays hidden from platform admins.

## Technology Stack

- Laravel 13
- PHP
- Blade templates
- MySQL
- Laravel authentication
- Laravel queues
- Laravel scheduler
- Laravel notifications
- Resend for email delivery
- Nginx
- PHP-FPM
- DigitalOcean Droplet
- Name.com domain
- GitHub repository

The frontend is mostly Blade and CSS, without a heavy JavaScript framework.

## Database Overview

Important tables include:

- `users`
- `letters`
- `letter_links`
- `responses`
- `letter_memories`
- `letter_memory_images`
- `feedback`
- `platform_settings`
- `moderation_audits`
- `storage_cleanup_logs`
- `site_metrics`
- `email_verification_codes`
- `password_reset_codes`
- `jobs`
- `failed_jobs`
- `sessions`
- `cache`
- `migrations`

The database supports:

- User and admin roles
- Soft-deleted users
- Suspended accounts
- Email verification codes
- Password reset codes
- Letter ownership
- Private random links
- Link expiration
- Open counts
- Recipient responses
- Media storage tracking
- Feedback management
- Platform configuration
- Moderation audit history

## Storage Rules

Text letters are unlimited, but uploaded media is limited by platform settings.

Storage can include:

- Letter image, GIF, or video
- Letter audio/music
- Creator profile picture
- Sender and recipient profile pictures for response pages
- Memory images

Admins can set the storage allowance and per-file upload limits.

If a creator exceeds the storage allowance, the app can warn them and later clean up media from the oldest expired letters. Letter text and responses are preserved.

## Security Features

DearYou includes:

- Separate creator and admin areas
- User and admin roles
- Email verification before creator features
- Password reset by email code
- Rate limiting
- Random unguessable letter tokens
- Optional admin IP allowlist
- Suspended/deleted creator protection
- Private response inbox
- Moderation audit logging
- `.env` based secret configuration

Secrets such as database passwords, admin passwords, and API keys should stay in `.env` and never be committed to GitHub.

## Email

Email is handled through Resend.

DearYou sends email for:

- Account verification code
- Password reset code

Production needs a verified sending domain and a valid Resend API key.

Typical production values:

```env
MAIL_MAILER=resend
MAIL_FROM_ADDRESS=hello@dearyous.app
MAIL_FROM_NAME="DearYou"
RESEND_API_KEY=your-resend-api-key
```

## Local Development

Local development can run on:

```text
http://127.0.0.1:8001
```

The local app can use MySQL on a custom port, such as `3307`, if another MySQL server already uses `3306`.

Common local commands:

```bash
composer install
npm install
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8001
```

## Deployment

Production is deployed on a DigitalOcean Droplet.

The server uses:

- Nginx
- PHP-FPM
- MySQL
- Laravel queue worker
- Laravel scheduler timer
- GitHub pull deployment

Typical update flow:

```bash
cd /var/www/dearyou
sudo -u deploy git pull --ff-only origin main
sudo -u deploy composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
sudo -u deploy php artisan migrate --force
sudo -u deploy php artisan optimize:clear
sudo -u deploy php artisan optimize
sudo systemctl restart php8.4-fpm
sudo systemctl restart dearyou-worker
```

The scheduler is managed by systemd:

```bash
sudo systemctl status dearyou-scheduler.timer
```

## Testing

The project has feature tests for:

- Registration
- Login
- Email verification
- Password reset
- Admin and creator separation
- Admin IP allowlist
- Letter creation
- Publishing and expiration
- Link regeneration
- Recipient responses
- Inbox behavior
- Uploads
- GIF, MP4, WebM, and audio support
- Storage limits
- Cleanup behavior
- Feedback
- Platform settings
- Moderation
- Account deletion
- Homepage visit count

Run tests with:

```bash
php artisan test
```

## Future Improvement Ideas

Possible improvements for later:

- More mobile UI polish
- Stronger analytics charts
- Reply notification emails
- More letter templates
- Terms and privacy pages
- Spam protection for feedback and public responses
- Admin export tools
- Image compression
- Optional object storage for media
- Backup restore documentation

