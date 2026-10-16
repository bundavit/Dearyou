# Feedback spam protection

Updated 16 October 2026, Asia/Bangkok. For the current project state see [STATUS.md](STATUS.md); delivery and verification limits are in [EMAIL_DELIVERY.md](EMAIL_DELIVERY.md). Future improvements are listed in [ROADMAP.md](ROADMAP.md).

## Last confirmed production result

On 15 October, the owner confirmed production at `8f1c618b40a77d8358b252643cd35c54a49b34e6`, using `/var/www/dearyou/backend`. The worker had recovered and was active, the queue was empty, the feedback recipient was configured, and **three feedback messages arrived**.

Those are dated observations, not a new live inspection on 16 October. Feedback inbox delivery worked at that check. **Registration verification was skipped and remains untested**; feedback receipt and an empty queue do not prove that a received verification code can verify an account.

## Deployed protection

- **Mark all as read** covers all New feedback across pages and filters. It changes those items to Reviewed and sets their review time without deleting records or changing other statuses. Only administrators can use it.
- **Potential spam** has its own status, filter, count and review reasons. An administrator can classify a record or restore it to New. Restoration clears the automatic flags as an explicit review override; it does not create a new email automatically.
- Automatic checks combine promotional signals, repeated short patterns and repeated normalized messages across senders. Non-English language, script, country or an ordinary price question is not a reason to reject feedback. Useful reports containing links remain allowed. Detection is conservative and cannot identify every advertisement.
- Flagged submissions remain saved and do not queue an alert. The existing honeypot and per-account/IP request limits still apply. The migration did not automatically relabel old records; legacy queued feedback receives worker-time checks.
- Before delivery, the worker checks that the record still exists, is New, has an eligible recipient and is recent enough. It skips deleted, reviewed/resolved, spam, muted, changed-recipient and stale feedback. Old model-based notification payloads are normalized into the same checks.
- A shared cache with atomic locks enforces ordinary feedback budgets of **3 per UTC clock hour and 5 per UTC calendar day**, with a **24-hour maximum alert age**. A transport retry reuses its reservation. Cache/lock failures skip feedback safely and log sanitized diagnostic metadata.

The 24-hour setting limits how old a submission may be when its alert is processed. It is **not a rule deleting stored feedback after 24 hours**. Budget- or age-skipped alerts are not automatically delayed into another burst; their records remain available in Admin.

These limits apply only to ordinary `FeedbackReceived` alerts. They do not throttle verification, password resets, other mail types or the administrator's separately throttled diagnostic email. All mail still shares the provider's overall allowance. Do not clear the shared cache to reset feedback limits, and do not treat these counters as the provider's quota display.

## Settings

The deployed defaults are documented in the environment examples:

```dotenv
FEEDBACK_EMAIL_HOURLY_LIMIT=3
FEEDBACK_EMAIL_DAILY_LIMIT=5
FEEDBACK_EMAIL_MAX_AGE_HOURS=24
```

Setting a limit or maximum age to zero disables ordinary feedback alerts. The saved Admin Settings recipient takes precedence over the environment fallback. Saving it blank mutes feedback alerts while keeping submissions available for review; an unset environment value alone may still fall back to `ADMIN_EMAIL`.

The recipient was configured again by the last delivery check. Do not apply the old emergency mute or stop the worker as part of routine file cleanup. Any live setting or service change requires its own reviewed operational purpose.

## Read-only queue inspection

Use Admin Email tools first to inspect pending/reserved/delayed counts and failures. These counts include all jobs in that queue, not only feedback. An active worker or zero pending jobs alone does not establish delivery.

For a future owner-requested server inspection, first confirm the current application path, configured database queue and appropriate runtime identity. The generic tool defaults to read-only and uses that application's configured queue connection/table/name:

```bash
sudo -u www-data /usr/bin/php /var/www/dearyou/deploy/feedback-queue.php --app=/var/www/dearyou/backend
```

This example assumes the inspected production configuration still uses the confirmed backend path and `www-data` runtime user. Stop if those facts differ. The tool reads JSON metadata without executing serialized commands or printing messages, email addresses, keys or codes. Inspecting a running queue gives a point-in-time count that can change.

This guide provides no deletion or blanket-retry procedure. Queue removal needs a separate current diagnosis, private backup, reviewed scope and protection of unrelated/security jobs. Do not replay the old hardcoded incident count or change refusal safeguards to force an action.

## Short incident history

On 14 October, the owner recovered a worker that had MySQL connection-refusal/start-limit history. Processing the accumulated feedback then flooded the inbox and triggered a reported quota problem. The owner stopped the worker; the feedback recipient was temporarily blanked while preserving other settings.

The owner subsequently discarded exactly **1,264 queued feedback alerts** from a 1,270-job snapshot through guarded exact-class cleanup. Saved feedback and failed-job history were preserved; six verification jobs remained. These figures describe that completed incident, not today's queue or a future deletion target. By the 15 October check the worker was active, the queue was empty and three feedback emails had arrived.

`docs/FEEDBACK_SPAM_CLEANUP.sh` and `deploy/feedback-queue-inline.php` are historical one-off incident artifacts. Their old root path and fixed expected count must not be reused. The general root-to-backend migration also completed; do not repeat it. Use the current [DEPLOYMENT.md](DEPLOYMENT.md) with owner review for future updates.

## Recorded validation and remaining work

The historical email/spam release passed **136 PHP tests / 1,178 assertions**, plus **41 separate queue-tool checks**. These used synthetic data and test transports; they did not send public email. The later QA release passed **171 PHP tests / 1,417 assertions** locally. These are recorded release results, not new tests for this documentation cleanup.

Provider inbox receipt is confirmed for feedback by the owner, but fresh account-code acceptance and password-reset inbox acceptance remain separate tests. Do not manually verify an account or replay expired code requests as proof of delivery. Staging infrastructure is prepared locally; a separately provisioned staging site and additional bot controls remain reviewable work in [ROADMAP.md](ROADMAP.md).
