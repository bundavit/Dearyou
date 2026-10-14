# Feedback and verification email

Updated 14 October 2026, Asia/Bangkok. Application changes below remain local, uncommitted and undeployed. Live incident actions are listed separately. Successful production verification has not been established.

## Confirmed production findings

- The owner received the direct Resend test in Gmail at 21:08. It bypassed the website queue and used the local project credential; equality with the production credential was not established.
- Website feedback was stored but initially produced no received email. A live registration at 22:10 reached `/verify-email`; the owner reported no code in Inbox or Spam. The retained synthetic QA account remains unverified. No manual verification or code bypass was used.
- Production uses the **old root layout**: `/var/www/dearyou/artisan` and `/var/www/dearyou/.env`. `/var/www/dearyou/backend` was absent. The installed worker correctly matches that root layout; an obsolete worker path was **not** the identified fault.
- Worker logs showed MySQL connection refusal on 1 September followed by the service reaching its restart/start limit. MySQL was active during the current inspection.
- The owner restarted the worker at approximately 22:30. Queued feedback flooded the inbox, demonstrating that the website's backlog could be processed. The owner then stopped the worker; it is inactive. This does not demonstrate verification-code delivery.
- Resend reportedly displayed **200 / 100** after the flood. The exact quota period and remaining capacity were not independently established; do not assume a reset time.
- At approximately 22:45, Codex saved a blank production `feedback_notify_email`, preserving all other settings. This mutes new feedback notification requests. Existing legacy queued notifications still contain their recipient and do not recheck the muted setting before sending.
- The queue snapshot contained **1,270 jobs**, including **1,264 candidate feedback jobs**. The owner subsequently ran the guarded exact-class cleanup and reported **1,264 feedback notification jobs discarded, six VerifyEmail jobs remaining**. None is reserved; the oldest was queued at 17:47:49 UTC on 10 October. Feedback records and failed-job storage were preserved. This removes old alerts, not the underlying submissions; it does not establish that every discarded alert was spam.

Feedback remains available in the admin panel while notifications are muted. Keep the worker stopped until selective backlog handling, provider capacity and deployment layout are reviewed. Do not clear the whole queue, retry all failures or restart the worker as a diagnostic shortcut. Verification shares this worker and is affected by its inactive state and the reported quota.

See [the release guide](EMAIL_RELEASE.md) and [feedback spam controls and selective cleanup](FEEDBACK_SPAM.md). The earlier local QA report at `docs/qa-2026-10-14/QA_REPORT.md` records the initial transport tests; these later findings supersede its unconfirmed worker-path hypothesis. That broader QA report is not part of the selected email release.

## Local email improvements

- Admin Email tools shows waiting, reserved and delayed jobs, oldest waiting-job age and storage availability. These counts include all jobs in that queue.
- Resend's badge says **Key configured**, not **Ready**; a configured key does not prove validity or inbox delivery.
- **Send feedback test** uses the saved recipient and normal application queue. Its `QA-FEEDBACK-...` reference identifies the message; it creates no feedback record. The administrator-only action is limited to three requests per minute. Log mode explicitly reports that no inbox email is sent.
- Missing recipients, unsupported transports, missing keys and enqueue failures are reported without displaying credentials. Raw job payloads and exception strings are excluded from the dashboard.
- **Retry listed emails** retries only the listed application email failures rather than `queue:retry all`. Do not use it while quota or backlog safety is unresolved.
- Registration and resending provide a recovery path after enqueue failure. With the configured same-database queue, a replacement code and its job commit together; failed enqueue preserves the previous valid code. External queue connections do not have the same atomic guarantee.
- Verification jobs skip expired, replaced, exhausted, already-verified and inactive-account codes. The ten-minute code lifetime and five-attempt limit remain. Raw code logs are shown only in the local environment.

## Local feedback and spam safeguards

These controls are not installed on production:

- **Mark all read** changes unread feedback to reviewed without deleting submissions.
- Automatic checks retain suspicious feedback as **Potential spam**, with reasons for admin review. Checks combine promotional signals, repeated short patterns and repeated duplicate messages. Language alone is not a banning rule.
- An administrator can restore legitimate feedback to New, clearing its automatic flags as an explicit review override. Restoration does not send a fresh email by itself.
- At processing time, feedback mail checks the current recipient and record state. Deleted, reviewed/resolved, spam, muted, changed-recipient and older-than-24-hour feedback is skipped. Legacy queued feedback is normalized into the same policy.
- Ordinary feedback mail receives a shared worker-side budget of **3 per UTC clock hour and 5 per UTC calendar day**, with a default maximum age of **24 hours**. This is an application safeguard, not a measurement of Resend's quota. Budget-skipped jobs remain stored feedback; they are not automatically rescheduled for a later email.
- Budget reservations use a shared cache with atomic locking and are reused for transport retries. Unsupported/unavailable cache storage fails closed for feedback delivery. This feedback budget does not apply to verification or password-reset mail. A stopped worker or exhausted provider quota can still block them.
- Settings show feedback delivery limits and explain that retained feedback can be reviewed even when its notification is held. Feedback enqueue warnings record exception type without including submitted content or credential-bearing exception messages.

The new migration adds `spam_score`, `spam_reasons` and a feedback creation-time index. Apply it before exposing the changed submission/admin routes. Release the controllers, model, migration, notification, policy/detector, configuration, environment examples, views and tests together. See [FEEDBACK_SPAM.md](FEEDBACK_SPAM.md) for exact settings, selective cleanup and operational limits.

## Production investigation and deployment boundaries

Read-only checks use the confirmed root layout:

```bash
ls -l /var/www/dearyou/artisan /var/www/dearyou/.env
sudo systemctl show dearyou-worker.service --property=LoadState,ActiveState,SubState,Result,WorkingDirectory,ExecStart
sudo systemctl status mysql dearyou-worker.service --no-pager
sudo journalctl -u dearyou-worker.service -n 50 --no-pager
```

Keep `.env` contents, credentials, codes and serialized jobs private. An active service alone does not prove mail delivery.

**Do not run the current `deploy/deploy.sh` against the old root layout.** Its application directory and repository worker unit target `/var/www/dearyou/backend`; installing that unit would replace the currently correct root-path unit with an incompatible one. First review a layout transition preserving the production `.env`, application key, database, uploads, writable directories, Nginx document root and service paths. A blind pull can remove or strand ignored production configuration/data.

The new local deployment failure handler retains maintenance mode after failure. The script installs/enables/restarts/checks its backend-layout worker before reopening the site. This does not make it suitable for the confirmed root installation or safe during quota exhaustion. Follow [EMAIL_RELEASE.md](EMAIL_RELEASE.md) for the owner-controlled boundary.

## Acceptance checks after a reviewed deployment

Start with separate staging, synthetic data and log transport. Its infrastructure and local guide at `docs/STAGING.md` are prepared for a separate release and are not included in this email subset. Confirm form feedback, diagnostic mail and verification use its own queue. Test replaced/expired codes, enqueue failure, spam retention/restoration, marking feedback read, muted recipients, legacy backlog skipping and the delivery budget.

Real inbox acceptance requires available provider capacity, a verified sender and controlled recipients. Refresh only the appropriate environment's configuration and worker after its backlog and service paths are safe. Then:

1. Keep feedback muted while requesting one fresh verification code for a synthetic account. Receive it and verify successfully using that code. A provider-accepted response, queued job or log message is insufficient.
2. Deliberately restore the saved feedback recipient, then confirm one marked diagnostic test arrives and record its reference. The diagnostic action is separate from ordinary feedback budget protection.
3. Submit one legitimate marked website feedback and confirm its email arrives.
4. Record the deployed version and evidence; remove the synthetic account after verification completes.

Production verification remains incomplete. Staging remains unprovisioned.

## Local validation

- The expanded backend suite passed **136 tests and 1,178 assertions** using isolated SQLite and test mail transport. These results do not prove live inbox delivery.
- The selective queue cleanup tool passed **41 additional checks**, counted separately from PHPUnit, including SQLite preservation checks against a 1,270-job fixture and actual PHP stdin refusal behavior. These checks did not modify the production queue.
- The deployment script passed Bash syntax checks and five mock deployment cases. These do not establish compatibility with the production root layout.

No commit, push or application deployment was performed. The live mute and owner-operated worker changes are recorded above.
