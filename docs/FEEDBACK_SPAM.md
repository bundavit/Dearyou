# Feedback spam recovery and protection

Prepared 14 October 2026, Asia/Bangkok. Code changes are local, unstaged and uncommitted. The owner performs Git and server operations.

## Confirmed live state

The installed production application is at `/var/www/dearyou/artisan`, with `/var/www/dearyou/.env`. There is no production `backend/artisan` directory. Do not install the repository's `/backend/` worker unit or run its deployment script against this old layout without first planning the layout transition and preserving configuration, database and uploaded files.

The owner recovered the failed queue worker at 22:30. The old logs showed MySQL connection refusal followed by systemd's start limit; MySQL is now listening and an Artisan database check succeeded. Processing the backlog then flooded the feedback inbox. The owner stopped `dearyou-worker.service`, confirmed inactive/dead. At 22:45 the live saved feedback recipient was cleared, and the other settings form values were checked unchanged. Feedback records continue to be saved; the old deployed code no longer queues new feedback emails with this setting blank. Existing old queued jobs still contain their recipient.

The owner-reported read-only count was **1,270 queued jobs, 1,264 matching FeedbackReceived**. The owner then ran the guarded exact-class cleanup successfully: **1,264 feedback notification jobs discarded, six other jobs retained**. Feedback records and failed-job history were preserved. All six retained jobs are **VerifyEmail**, none reserved; the oldest was queued at **2026-10-10 17:47:49 UTC**. Keep the worker stopped and preserve them while provider recovery and the new expired-code guard deployment are checked. Verification/reset delivery remains paused while this shared worker is stopped.

The server checkout is clean, branch `main`, commit **e973aeb (2026-07-02)**. Nginx serves `/var/www/dearyou/public` using PHP 8.4 FPM. Local `main` already contains a pre-existing August commit relocating Laravel into `backend/`; those existing commits were not made in this session. A normal push/pull therefore includes a layout transition in addition to the local email/spam changes. Complete [the layout preservation steps](PRODUCTION_LAYOUT_MIGRATION.md) before applying it.

Resend shows **200/100 (200%)**, according to the owner. This is consistent with exhausting the free daily allowance but the actual usage label/error has not been confirmed. Resend documents a free quota of 100 emails per UTC day and 3,000 per month, with daily reset at midnight UTC (**07:00 Bangkok**). Monthly exhaustion has a different recovery time. See [Resend's quota documentation](https://resend.com/docs/knowledge-base/account-quotas-and-limits) and [distinct quota/rate errors](https://resend.com/docs/api-reference/errors). Do not send further tests or repeatedly retry jobs until the provider permits sending.

## Selective queue cleanup

The incident-specific cleanup below has been executed successfully by the owner; do not repeat it against the changed queue. Keep the worker stopped and the live feedback recipient blank. The reviewed `deploy/feedback-queue.php` tool remains available for read-only inspection and separately reviewed cleanup of queued **App\\Notifications\\FeedbackReceived** alerts. It reads JSON metadata; it does not deserialize executable job payloads or display messages, email addresses, keys or codes.

To identify the retained jobs without Tinker or displaying message content, run this read-only block in the console:

```bash
cd /var/www/dearyou
git rev-parse --show-toplevel
sudo -u www-data /usr/bin/php <<'PHP'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$types = [];
foreach (Illuminate\Support\Facades\DB::table('jobs')->get(['payload', 'reserved_at']) as $job) {
    $data = json_decode($job->payload, true);
    $type = $data['displayName'] ?? 'Unknown';
    if (!is_string($type) || !preg_match('/^[A-Za-z0-9_\\\\:]+$/D', $type)) $type = 'Unknown';
    $types[$type]['count'] = ($types[$type]['count'] ?? 0) + 1;
    $types[$type]['reserved'] = ($types[$type]['reserved'] ?? 0) + ($job->reserved_at !== null ? 1 : 0);
}
echo json_encode($types, JSON_PRETTY_PRINT), PHP_EOL;
PHP
```

The tool defaults to read-only. It requires the actual Laravel application root and uses the configured database queue connection/table/queue. A removal requires an explicit expected feedback count and zero reserved feedback jobs, and verifies the same job snapshot before deletion. It preserves feedback records, non-feedback jobs and failed-job history. Discarding a queued alert is irreversible; it does not delete the feedback available in Admin.

After copying this reviewed tool to a private server path, inspect the **old root layout**:

```bash
sudo -u www-data /usr/bin/php /path/to/feedback-queue.php --app=/var/www/dearyou
```

Only after its exact-class output is reviewed, discard the snapshot of feedback alerts:

```bash
sudo -u www-data /usr/bin/php /path/to/feedback-queue.php --app=/var/www/dearyou --discard-feedback --expect-feedback=1264
```

Use the count from the tool's inspection, which may differ from the preliminary 1,264. If it refuses because jobs are reserved or the snapshot changed, inspect the reason; do not change safeguards to force removal. Do not use `queue:clear`, `queue:flush`, `queue:retry all`, or a blanket jobs-table delete. The worker should remain stopped until queue cleanup and provider recovery are reviewed.

For the DigitalOcean web console without uploading a file, the reviewed PHP body in [feedback-queue-inline.php](../deploy/feedback-queue-inline.php) can be pasted between `sudo -u www-data /usr/bin/php <<'PHP'` and a final `PHP` line. This variant is fixed to the confirmed root layout and expected 1,264 feedback jobs; it performs the guarded cleanup immediately rather than defaulting to read-only. It refuses count changes and reserved feedback jobs. Its actual cleanup function passed the same preservation checks on a synthetic 1,270-job queue.

The complete ready-to-copy command is in [FEEDBACK_SPAM_CLEANUP.sh](FEEDBACK_SPAM_CLEANUP.sh). Copy its entire contents into the DigitalOcean web console while the worker is stopped, then review and share only the resulting counts or refusal message. No local source commit or file upload is needed to use this incident-specific block. The new application spam controls still require a reviewed release.

## Local application changes

- Admin Feedback has **Mark all as read**, with a clear note that it covers all new records across pages and filters. It changes New to Reviewed and sets the review time, without deleting records or changing spam/resolved/reviewed items. Only administrators can use it.
- **Potential spam** has its own status, filter, count and review reasons. An administrator can mark a record or restore it to New. Restoration clears the automated flags and is respected by the worker; it does not enqueue a new email automatically.
- Automatic checks use normalized repeated messages across IPs, repeated short noise patterns and combined promotional signals. Non-English language, script, country and an ordinary price question are not reasons to reject feedback. A useful bug report containing links remains allowed. Detection is conservative and cannot identify every translated or unique advertisement.
- Flagged submissions remain saved and do not queue an email. The existing honeypot and per-account/IP limit still apply. Existing records are not automatically relabeled by the migration; pre-update queued emails receive a worker-time spam check.
- Worker-time checks skip alerts for a missing/deleted record, a status other than New, a blank/changed saved recipient, an alert older than the configured age, or an exhausted feedback budget. Old model-based payloads are normalized so deleted feedback does not trigger model-restoration failures.
- A shared cache with atomic locks enforces default feedback budgets of **3 per UTC hour, 5 per UTC day**, with **24-hour** maximum age. A retry reuses its reservation. Skipped alerts are not delayed into another burst or digested; their records stay available in Admin. Cache/lock failures skip feedback email safely and write only sanitized diagnostic metadata. Avoid clearing the budget cache as a way to send more feedback alerts.
- These limits apply to FeedbackReceived alerts. They do not throttle verification/reset notifications, other email types or the administrator's separately throttled synthetic delivery test. All mail still shares the provider's overall account allowance.

Budget settings are documented in the environment examples:

```dotenv
FEEDBACK_EMAIL_HOURLY_LIMIT=3
FEEDBACK_EMAIL_DAILY_LIMIT=5
FEEDBACK_EMAIL_MAX_AGE_HOURS=24
```

Zero disables feedback alerts. Keep the saved recipient blank to mute alerts; an unset environment recipient can still fall back to ADMIN_EMAIL. The saved Admin Settings value takes precedence.

## Release and acceptance

The new migration adds nullable spam score/reasons and a creation-time index. Apply it before opening the updated application to feedback submissions. Use [EMAIL_RELEASE.md](EMAIL_RELEASE.md) for the dependency-complete owner-controlled release, with the production layout warning above. Never copy the local real `.env`, generate a new production app key or seed the production database.

After selective cleanup and provider recovery, restart only the intended production worker with the established application layout. Keep feedback alerts muted during the first account verification test. Request one fresh verification code, confirm receipt and finish verification with that received code; expired old jobs do not prove the flow works. Production verification has not yet been completed in this session.

After deployment, use synthetic data on separate staging to check:

1. A normal multilingual bug report is saved as New and uses the configured mail transport.
2. The third normalized duplicate is saved as Potential spam without a notification. Obvious promotional/noise samples are similarly held for review.
3. A restored record remains New and its override is honored by delivery checks.
4. Mark all as read covers more than one page while preserving other statuses and all records.
5. Feedback budgets hold across distinct guests and worker executions; a verification email still processes after the feedback budget is exhausted. A muted recipient blocks an already queued alert.

Cloudflare Turnstile can add a bot challenge later and works without moving DNS to Cloudflare. It needs server-side token validation, not just a visible widget. See [Turnstile](https://developers.cloudflare.com/turnstile/) and [server-side validation](https://developers.cloudflare.com/turnstile/get-started/server-side-validation/). Turnstile has not been configured or enabled here.

## Validation

The backend suite passed **136 tests / 1,178 assertions** using synthetic users, SQLite and in-memory/log mail, with no public email sends. Queue cleanup passed **41 separate checks**, including isolated SQLite fixtures retaining other jobs and feedback records, and actual PHP stdin refusal behavior. The owner subsequently confirmed successful live selective cleanup. Provider quota recovery, new code deployment and successful inbox/code verification remain pending acceptance steps.
