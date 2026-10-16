# Owner-run backups and releases

These helpers are prepared for review and execution by the owner. Editing or testing them locally does not deploy DearYou. They target Linux, systemd and MySQL/MariaDB; the isolated regression tests use synthetic data and intercept every database, Git, service and network mutation.

The current helpers replace the earlier moving-branch updater and shell-sourced `.env` backup. They do not seed accounts, generate keys, rewrite Nginx, install units, change DNS, recursively change permissions, or prune backups. Review the exact release diff and any data migrations first.

## Prepare once

- Keep the production `.env`, `APP_KEY`, database and media outside Git. The deployment account and `www-data` need the intended shared group. Runtime directories (`storage`, `bootstrap/cache`) should have group-only write access, directory setgid and no world access. The updater does not repair an incorrect server permission layout. The readiness helper creates missing private media directories with mode `2770`; their parent must already have the intended shared group. Cached configuration is set to `0640` because it contains secrets.
- Review/install the worker and scheduler templates manually, then reload systemd. Both worker templates use a 60-second job timeout, 10-second retry backoff, 10-second process restart delay and a 120-second graceful stop. The database/Redis/Beanstalk queue `retry_after` must exceed 60 seconds (the application default is 90); SQS visibility timeout must also exceed 60 seconds. `StartLimitIntervalSec=0` permits recovery after a longer infrastructure outage.
- Provision one protected MySQL `[client]` option file for backup access, and a **separate account and option file for restore verification**. Limit the restore account to newly created `dearyou_restore_*` databases; it must have no privileges on production. Match the backup server version with compatible MySQL/MariaDB client tools. Grant the backup account only the read/dump permissions required for this application's tables.
- MySQL option files must be regular files with mode `0600` or `0400`, owned by the operator or root, and supplied by absolute path. Passwords stay in those files; they are never passed in command arguments or parsed from `.env`. Use a text editor to create them. Do not put a real password in command history.
- Store backups outside the web root in a private `0700` directory, with a separate encrypted off-host copy. A bundle includes the database, legacy public uploads, private media and the secret `.env`. All bundle files are `0600`. Retention and removal are deliberate owner operations; these scripts delete no prior backup or database.
- Quiesce any additional cron jobs, Horizon/Octane instances, manually started workers, maintenance bypass users and external processes that write the database or uploads. `WRITERS_PAUSED=1` acknowledges this operator check. The scripts additionally require the local maintenance marker and inactive named worker/scheduler/timer units. They cannot detect a writer on another host.

## Take and verify a backup

Run as the deployment operator with the appropriate noninteractive sudo permissions. Substitute the actual database identifier; it is deliberately not taken from `.env`. Keep the site in maintenance continuously through backup, verification and deployment. Entering maintenance does not stop a request already running: stop incoming writers, drain requests, and stop any extra processes first.

```bash
export REPO_DIR=/var/www/dearyou
export APP_DIR="$REPO_DIR/backend"
export DB_NAME=your_actual_database
export BACKUP_DIR=/var/backups/dearyou
export WRITERS_PAUSED=1

cd "$APP_DIR"
php artisan down --retry=60
sudo -n systemctl stop dearyou-scheduler.timer dearyou-scheduler.service dearyou-worker.service
# Finish draining in-flight requests and pausing any additional writers here.

MYSQL_DEFAULTS_FILE=/absolute/private/backup-client.cnf \
  bash "$REPO_DIR/deploy/backup.sh"

# Use the exact bundle path printed by backup.sh.
export BACKUP_BUNDLE=/var/backups/dearyou/backup-TIMESTAMP-RANDOM
MYSQL_DEFAULTS_FILE=/absolute/private/restore-client.cnf \
  RESTORE_DB=dearyou_restore_release_20261016 \
  bash "$REPO_DIR/deploy/verify-backup.sh"
```

Verification checks all bundle hashes, validates and extracts the media archive into a private temporary directory, imports the SQL into a **new** scratch database, checks users/letters row counts and the migrations table, and writes `restore-verified` tied to that exact checksum manifest. `CREATE DATABASE` deliberately has no `IF NOT EXISTS`: an existing target fails without touching its contents. The scratch database remains for inspection and deliberate owner removal with the restricted restore account. A failed restore never creates a success receipt. Inspect the failure and use a new scratch database name on retry.

This is an import and archive integrity check, not a substitute for a full application restore rehearsal. On a separate isolated host, restore the bundle into a separate app/DB/media directory, disable outgoing mail, verify representative letters and attachments, and confirm the saved `APP_KEY` before relying on the recovery plan. Never point the rehearsal at production. The restore helper intentionally does not put the saved environment into the running application.

## Release an exact commit

Commit and push the reviewed changes yourself. The server checkout must be clean, and `REVISION` must be the complete 40-character lowercase commit hash. Supply the same application/database and backup bundle values used above. The updater requires a backup created in the last hour, a matching successful restore receipt, the same pre-release Git revision and an unchanged `.env` and maintenance marker. If the site was reopened and paused again, take and verify another backup.

```bash
export REVISION=replace_with_the_exact_40_character_reviewed_commit
bash "$REPO_DIR/deploy/deploy.sh"
```

The updater fetches that revision, verifies it, checks out the exact commit in detached mode, installs locked dependencies with Composer lifecycle scripts disabled, builds the frontend, runs explicit package discovery and database migrations, retries pending media deletions, and runs `dearyou:secure-media --apply --remove-public` as `www-data` before reopening. It refreshes caches, checks production readiness as both the deployment account and `www-data`, reloads PHP-FPM, verifies the worker is active, then reopens the app and starts the scheduler timer. Install/review server Nginx, PHP and service changes separately; the updater does not silently apply infrastructure changes. Install the new operations helpers before first using this workflow on a server that has the older helpers.

The automatic private-media step copies/checksums historical attachments and removes public copies only after verification. Review the command's help and dry run before invoking the updater; it is an explicit part of this release, beyond the database migration. Block legacy direct media URLs at the web server before reopening. A cleanup, media migration or service-account readiness failure leaves the app in maintenance.

Readiness verifies the exact private media disk root is local and outside the web public directory, refuses symlinked media directories, checks group-only permissions and performs an actual temporary file write/read/delete through the media disk. It checks the configured database queue connection/table and requires a real warning-mail delivery transport, including every failover child: log/array fallbacks cannot authorize storage-cleanup grace periods. These checks send no mail; separately verify provider credentials and delivery in an owner-controlled acceptance test. The service-account pass also checks that `www-data` can read the built application/cached configuration and use the same database and private storage.

Any failure after checkout stops the known writers and retains/reinstates maintenance, including failures after reopening. It does not attempt an automatic database rollback. Keep the service unavailable while you investigate; choose a reviewed forward fix or a complete, coordinated database/media/environment recovery. Returning only the code to the previous commit may be incompatible with migrated data. Check the public site and administrator health page after success.

For staging backup operations, set the staging `APP_DIR`, destination, database and all three service variables (`WORKER_SERVICE`, `SCHEDULER_SERVICE`, `SCHEDULER_TIMER`) explicitly. The production updater is not the staging updater; use the guarded staging workflow described in `STAGING.md`.

## Local verification

```bash
bash -n deploy/backup.sh deploy/verify-backup.sh deploy/operations-common.sh deploy/deploy.sh
python3 deploy/tests/operations-test.py
# Compatibility entry point: bash deploy/tests/deploy-test.sh
php -l deploy/check-production.php
```

Tests intercept all MySQL, Git, Composer/npm, PHP and service commands. Real gzip/tar/checksum operations act only on temporary synthetic files. This validates guards, sequencing, permission checks, archive integrity and failure recovery, without changing production services or data. It does not prove live MySQL privileges, restore performance, off-host recovery or installed service permissions; those require the owner's isolated server rehearsal.
