# Production root-to-backend migration

Updated 15 October 2026, Asia/Bangkok. This is an owner-run migration plan, not an executed deployment. Live incident facts were last confirmed on 14 October. Do not run the current production updater or pull new source into the live directory before completing the preservation plan.

## Last confirmed incident state on 14 October

The owner stopped the worker and muted feedback alerts. The guarded cleanup removed 1,264 queued feedback alerts while preserving their saved records. The six remaining jobs were identified as `VerifyEmail`, with zero reserved jobs; the oldest was created at 17:47 UTC on 10 October. These old requests cannot establish successful verification and must not be retried as an inbox test. Leave the worker stopped and feedback muted. The new email/spam code remains undeployed.

Confirmed production: clean Git `main` at `e973aeb` (2 July), Laravel at `/var/www/dearyou/artisan`, Nginx document root `/var/www/dearyou/public`, PHP 8.4 FPM. Local `main` is at `e32105e`, three existing commits ahead of local `origin/main` (`e7be0ad`, 3 July). Existing commit `04e2ae0` moves Laravel into `backend/`. The new local email changes need an owner-created, reviewed commit; none of those commits has been made or pushed by Codex.

The installed root-path worker is correct for the current server. Repository units target `/var/www/dearyou/backend` and become appropriate only after this migration. `deploy/deploy.sh` automatically enables/restarts the worker, so **do not use it for this incident migration**, even after fetching its updated failure handler.

## Read-only preflight for the next deployment session

Confirm the actual deploy user, available disk space, MySQL database identity, CLI PHP version and privileges before changing files. The CLI must satisfy the reviewed lock file's PHP requirement (`^8.4.1`). Inspect the active Nginx site file and all scheduler/backup launchers; a timer and cron must not both schedule the same application.

```bash
cd /var/www/dearyou
git rev-parse --short HEAD
git status --short
df -h /var/www /var/backups
php --version
sudo systemctl show dearyou-worker.service dearyou-scheduler.service --property=ActiveState,WorkingDirectory,ExecStart
sudo systemctl list-timers --all --no-pager
sudo crontab -l
crontab -l
sudo grep -R -n '/var/www/dearyou' /etc/cron.d /etc/systemd/system /etc/nginx/sites-enabled
readlink -f /var/www/dearyou/public/storage
find /var/www/dearyou/storage -type l -print
```

Review these privately. Do not print `.env`, keys, job payloads or verification codes. The current Nginx TLS/certificate/redirect configuration must be preserved; do not replace it with the repository's HTTP example. Check systemd drop-ins and any cron belonging to another service user separately. Stop if state differs from the confirmed layout, source has changed, storage uses unreviewed external symlinks, or backup/restore capacity is insufficient.

## Private backup before source changes

Enter maintenance through the current root app, leave the worker stopped, and stop the identified scheduler timer/cron launchers so they cannot prune codes or alter records during the snapshot. Save their enabled/disabled state for recovery. Do not stop MySQL or assume stopping the worker stops scheduled tasks.

Use a new private directory outside every web root. The following commands are templates for the owner after confirming the local MySQL database name and socket-admin privileges; replace the database placeholder first. Do not source the Laravel `.env` as shell code or put its password on a command line.

```bash
set -euo pipefail
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
SNAPSHOT="/var/backups/dearyou-layout/$STAMP"
DB_NAME='CONFIRMED_DATABASE_NAME'
test "$DB_NAME" != CONFIRMED_DATABASE_NAME
sudo install -d -m 0700 "$SNAPSHOT"
cd /var/www/dearyou
sudo -u www-data php artisan down --retry=60
sudo tar --acls --xattrs --numeric-owner -czf "$SNAPSHOT/application.tgz" -C /var/www dearyou
sudo mysqldump --single-transaction --no-tablespaces --routines --triggers --events --databases "$DB_NAME" | gzip -c | sudo tee "$SNAPSHOT/database.sql.gz" >/dev/null
sudo gzip -t "$SNAPSHOT/application.tgz" "$SNAPSHOT/database.sql.gz"
sudo tar -tzf "$SNAPSHOT/application.tgz" >/dev/null
```

Also back up the actual enabled Nginx site files, relevant systemd units/drop-ins, and cron definitions into this private directory. Record the old Git commit and service states there. Do not run the existing retention-pruning backup script as a substitute for this one-time snapshot. Compression checks are not a restore test: verify the SQL dump can restore into an isolated temporary database and the filesystem archive preserves `.env`, storage/uploads, ownership and symlinks. Remove only that confirmed temporary database afterward. Keep this rollback snapshot and a separate off-server copy. A backup failure leaves the site in maintenance; do not continue.

## Prepare a reviewed release separately

Follow [EMAIL_RELEASE.md](EMAIL_RELEASE.md) for the owner-controlled commit/file boundary. Push the reviewed release yourself and record its full commit SHA. The three existing local commits include the layout move and are part of the ancestry to review; a server `git pull` is not an email-only deployment.

Prepare a fresh checkout in a separate candidate directory, using the server's existing read-only repository access. Check out the exact owner-pushed SHA, rather than a moving `main` branch. Verify it contains the spam-fields migration, policy/detector, all notification/controller/view dependencies and the expected lock file. Do not copy credentials into Git or alter the current live checkout during preparation.

Copy the **existing production** `.env` and complete `storage/` into the candidate's `backend/`, preserving files and metadata. Keep the saved blank feedback-recipient setting in the database. Never copy the local development `.env`, run `key:generate`, or seed production. Resolve any external storage symlinks explicitly before copying. A private runtime copy can be verified with `cmp -s` for `.env` and a checksum-based `rsync --dry-run --checksum` for storage; neither requires displaying secrets or file contents.

Install dependencies from the candidate's lock file using `composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader`, with the validated PHP CLI. Do not use `composer update` or copy the old root vendor directory. Preserve a private `.env` readable by FPM and grant the runtime user write access only to `backend/storage` and `backend/bootstrap/cache`. Create `backend/public/storage` only after the final path is established, using the new app's `storage:link`. Refuse an unexpected real directory at `public/storage` instead of deleting it; an old symlink must be inspected and only that link replaced.

The candidate must remain in maintenance too. Do not cache configuration before its final path is established, because compiled configuration includes absolute storage paths. Do not run `optimize:clear` or `cache:clear` as a shortcut: shared cache may contain feedback-budget state and active sessions.

## Controlled cutover and checks before reopening

After the verified snapshot and candidate are ready, keep maintenance and all delivery/scheduler processes stopped. Move the old installation to a retained private rollback location and place the candidate at `/var/www/dearyou`; verify both resolved paths before any move. Do not delete the old installation. This two-step directory swap has a brief path gap, so perform it only during the maintenance window. Confirm no upload or database writers remain active first.

Then, in order:

1. Compare the new `backend/.env` with the preserved production copy without printing contents. Confirm the same database and `APP_KEY`, and complete storage/uploads. The root's old `.env` is no longer the application's environment file.
2. Apply reviewed pending migrations from `/var/www/dearyou/backend` using `php artisan migrate --force`. The new spam columns/index are additive. Review `migrate:status` first; unexpected pending migrations require inspection. Never reseed.
3. Update only the actual Nginx document-root path to `/var/www/dearyou/backend/public`, preserving HTTPS listeners, certificates, redirect rules and other directives. Run `sudo nginx -t` before reload; if it fails, restore the saved config and remain in maintenance.
4. Install the reviewed backend-path worker/scheduler units, handling any old drop-ins. Run `systemctl daemon-reload`, **without enabling or starting the worker**. Replace old `artisan schedule:run`/backup paths in the identified cron entries; remove duplicate scheduling. Keep scheduling stopped until application checks pass. Update any backup script's explicit `APP_DIR` override to the backend path.
5. Reload PHP 8.4 FPM and Nginx after validated configuration. Recreate/verify `public/storage` at its final backend path. Rebuild only configuration/routes/views (`config:cache`, `route:cache`, `view:cache`) as the appropriate deployment/runtime user; preserve the existing shared cache and maintenance state.
6. Check `migrate:status`, `route:list --path=verify-email`, `schedule:list`, database access, writable storage, public assets and resolved upload links before reopening. Confirm effective mailer is Resend, queue is the intended database, feedback recipient remains blank, and limits are 3/hour and 5/day. Use private output that reports key presence rather than the key. Confirm `systemctl is-active dearyou-worker.service` still reports inactive.
7. Confirm the HTTPS origin returns the maintenance response and the new app boots without unexpected exceptions. Do not send email or publish a test to bypass maintenance. Only after these checks, run `php artisan up` from the new backend and verify `/up`, login and stored content. Restore only the reviewed scheduler arrangement when its tasks are safe; avoid launching overlapping cron and timer processes.

If any step fails, keep maintenance enabled and delivery stopped. There is no automatic `artisan up` recovery. Being able to serve the website does not require starting its mail worker during quota recovery.

## Mail recovery is a separate step

After the deployed source, retained queue and provider capacity are reviewed, the owner explicitly starts the correctly configured backend-path worker. Its new verification policy should skip the six stale requests, rather than deliver obsolete codes. Keep feedback muted during one fresh registration/resend test, confirm actual inbox receipt, and verify using that received code. Only then restore the feedback recipient deliberately and confirm one legitimate feedback email. See [EMAIL_DELIVERY.md](EMAIL_DELIVERY.md). No successful verification is claimed yet.

## Rollback boundary

Keep the worker and scheduler stopped and enter maintenance before rollback. Preserve any new uploads/runtime changes before switching directories back; do not discard data created since cutover. Restore the saved old application directory, Nginx root/TLS configuration, units/drop-ins and cron paths, then validate Nginx, database access and storage before reopening. Leave delivery stopped and feedback muted until provider recovery is resolved.

An application rollback does **not** imply a database restore. The new spam migration is additive and can normally remain when reverting this code. Do not blindly run migration rollback or import the old SQL dump: either can lose newer data or revert unrelated migrations. Database restoration requires a separately reviewed recovery plan and reconciliation of writes since the snapshot. If recovery is uncertain, remain in maintenance and preserve both versions.

Also account for feedback saved with the new `spam` status after cutover: the old application's status labels do not include it. A rollback needs a compatible status label or a separately reviewed mapping that preserves records and classification metadata. Do not assume that leaving the additive columns alone makes all old admin views compatible.

Unconfirmed details for the next deployment session are the exact source release SHA, deploy ownership, active TLS site file, scheduler/cron/drop-ins, external storage links, CLI/MySQL backup access and verified backup restore. Confirm the current stopped-worker and muted-feedback state before making changes.
