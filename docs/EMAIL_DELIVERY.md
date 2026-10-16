# Email delivery and verification

Updated 16 October 2026, Asia/Bangkok. This guide separates the last confirmed live delivery results from verification work that remains untested. See [STATUS.md](STATUS.md) for current project status and [TRANSFER.md](TRANSFER.md) for the next work session.

## Last confirmed live state

On 15 October, the owner confirmed:

- Production was updated to `8f1c618b40a77d8358b252643cd35c54a49b34e6` and uses `/var/www/dearyou/backend`.
- The queue worker recovered and was active; the queue contained **zero jobs** at the checked time.
- **Three feedback emails arrived**, demonstrating that the website's queued feedback delivery path worked.
- The feedback recipient was configured again. The earlier emergency mute is historical.

These are dated observations, not a new server inspection on 16 October. An empty queue or active process alone does not prove inbox delivery. The received feedback messages provide that evidence for feedback only.

**Registration verification and received-code acceptance were skipped and remain untested.** Do not describe account verification as repaired or completed based on the feedback receipts. Requesting or queuing a code is not proof that it arrived and verified an account.

## Feedback protection now in the deployed code

Ordinary `FeedbackReceived` alerts have default limits of **3 per UTC clock hour and 5 per UTC calendar day**, with a **24-hour maximum age**. These limits apply to feedback alerts, not verification codes, password resets or all Resend traffic. UTC midnight is 07:00 Bangkok.

```dotenv
FEEDBACK_EMAIL_HOURLY_LIMIT=3
FEEDBACK_EMAIL_DAILY_LIMIT=5
FEEDBACK_EMAIL_MAX_AGE_HOURS=24
```

The saved Admin Settings recipient overrides the environment fallback. A saved blank recipient mutes feedback alerts while keeping submissions available in Admin. Changing or clearing that saved recipient is respected when an already queued feedback job runs.

Worker checks also skip missing/deleted, reviewed/resolved, Potential spam and stale feedback. Skipped alerts remain stored feedback; they are not automatically rescheduled into a later burst. Shared cache locks enforce the budget, and transport retries reuse their reservation. Unsupported or unavailable cache storage skips feedback safely. Do not clear the shared cache to reset these limits.

Suspicious submissions are retained as Potential spam with review reasons. Detection uses combined promotional signals, repeated patterns and duplicate messages; language alone is not a banning rule. Restoring legitimate feedback to New clears its flags as an explicit review override without creating a new notification. **Mark all read** preserves records. See [FEEDBACK_SPAM.md](FEEDBACK_SPAM.md) for the incident history and protection details.

The administrator's separate synthetic feedback test has its own three-requests-per-minute throttle and does not use the ordinary feedback budget. All mail types still share the provider's overall account allowance. The old reported `200 / 100` quota display is incident history, not a current quota reading or a promise of remaining capacity.

## Check delivery without sending mail

Open **Admin -> Email tools** and inspect the effective mailer, sender, configured recipient, queue counts and listed failures. Waiting/reserved/delayed counts include every job in the configured queue. **Key configured** means a key is loaded; it does not validate the key or confirm delivery. The page excludes raw payloads, codes and exception text.

Owner-run, read-only server checks use the backend path:

```bash
cd /var/www/dearyou/backend
sudo systemctl show dearyou-worker.service --property=LoadState,ActiveState,SubState,Result,WorkingDirectory,ExecStart
sudo systemctl status dearyou-worker.service --no-pager
```

Review detailed logs privately before sharing sanitized results. Never paste `.env`, provider credentials, verification codes or serialized jobs into chat or a public issue.

Do not run `queue:retry all`, clear the queue, replay the archived cleanup command or restart services merely to see whether mail arrives. Investigate a specific pending/failed job and current provider events first. **Retry listed emails** is limited to listed application mail failures, but retrying still requires a diagnosed cause and deliberate owner action.

Only if the owner explicitly requests another feedback delivery check, use **Send feedback test** once. Record its `QA-FEEDBACK-...` reference, confirm receipt in the configured inbox/Spam, and correlate the reference with provider events if available. A queued-success notice is not delivery confirmation. No additional test is needed merely to clean up files or prepare a commit.

## Verification work still to perform

The deployed code keeps ten-minute codes and a five-attempt limit. With the same-database queue, a replacement code and its job commit together; failed enqueue preserves the previous valid code. External queue connections do not have the same atomic guarantee. The worker skips expired, replaced, exhausted, already-verified and inactive-account codes. Registration/resend errors provide a recovery path. Raw local code logs are restricted to the local environment.

When the owner resumes verification testing, use one fresh synthetic account or a fresh resend to a controlled real inbox. Receive the latest code and verify with that received code. Do not replay expired requests, manually verify the account as a substitute, or infer success from feedback delivery. Check current provider capacity before sending. Password-reset inbox acceptance is a separate test too.

## Deployment and staging

The old root-to-backend migration is complete for the last confirmed production deployment. Do not apply its one-time cutover again or use `/var/www/dearyou/artisan` as the current application path.

Follow [DEPLOYMENT.md](DEPLOYMENT.md) and [OPERATIONS.md](OPERATIONS.md) for owner-reviewed pinned deployment, private backup/restore verification and controlled service actions. Preserve production `.env`, APP_KEY, database, media, TLS and correct service paths. The new warning cleanup grace starts after a delivering transport accepts its warning; log/array mail and failover chains containing them cannot authorize destructive cleanup.

Staging infrastructure and banner additions are prepared locally for separate review. This does not establish that `staging.dearyous.app` is provisioned. Staging must have independent data, sessions, storage, services and controlled mail settings before real testing.

The historical email release validation was **136 tests / 1,178 assertions**, plus **41 separate cleanup checks** and **five deployment mocks**. The later QA release passed **171 tests / 1,417 assertions** locally. These are dated release results, not new tests for this documentation cleanup or proof of verification inbox delivery. See [EMAIL_RELEASE.md](EMAIL_RELEASE.md) and [QA_FIX_RELEASE.md](QA_FIX_RELEASE.md).
