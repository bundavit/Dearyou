# Completed production layout migration

Historical record, updated 16 October 2026, Asia/Bangkok. The owner completed this migration. Do not replay the old cutover or use the old root application path for current commands.

## Before and after

| Resource | Old layout | Last confirmed current layout |
| --- | --- | --- |
| Laravel application | `/var/www/dearyou` | `/var/www/dearyou/backend` |
| Artisan | `/var/www/dearyou/artisan` | `/var/www/dearyou/backend/artisan` |
| Environment file | `/var/www/dearyou/.env` | `/var/www/dearyou/backend/.env` |
| Nginx document root | `/var/www/dearyou/public` | `/var/www/dearyou/backend/public` |
| Worker working directory | `/var/www/dearyou` | `/var/www/dearyou/backend` |

The owner prepared release `e64a15da10ee278b8712558567b1bd04fcbd5899` from `https://github.com/bundavit/Dearyou.git`, verified private application/configuration and database backups, copied uploads/settings and completed the backend-path cutover. The old application and rollback snapshot were retained privately. The subsequent QA release was `8f1c618b40a77d8358b252643cd35c54a49b34e6`.

The owner reported successful HTTPS homepage/login/health checks. On 15 October the worker was restarted, the queue drained from 15 waiting jobs to zero, and three feedback emails were received. The feedback recipient is configured. The old stopped-worker/muted-recipient incident state is no longer the last confirmed state.

The exact old queue counts and pinned cutover commands were specific to the incident. They are not a reusable migration or queue-cleanup instruction. Local one-off files were moved into the private `.local-archive/cleanup-2026-10-16/` folder during repository cleanup; full historical source also remains in Git history.

For current status see [STATUS.md](STATUS.md), for future owner-reviewed deployments see [DEPLOYMENT.md](DEPLOYMENT.md), and for pending work see [ROADMAP.md](ROADMAP.md). Existing-data updates must preserve APP_KEY, database, uploads, TLS and current service paths. A code rollback does not authorize replacing newer database contents with a historical dump.
