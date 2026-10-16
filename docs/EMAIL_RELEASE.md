# Email release history

**Historical completed release. Do not rerun the old commit list, root-layout migration or incident cleanup.** Updated 16 October 2026, Asia/Bangkok. For current facts use [STATUS.md](STATUS.md); for owner-reviewed future deployments use [DEPLOYMENT.md](DEPLOYMENT.md).

## Published changes

The owner committed the email/spam work as `5702b28`, integrated it in `e64a15da10ee278b8712558567b1bd04fcbd5899`, and subsequently deployed the QA release `8f1c618b40a77d8358b252643cd35c54a49b34e6`. Production now uses `/var/www/dearyou/backend` according to the last confirmation on 15 October. Codex did not perform those Git mutations or server deployments.

The email release added:

- Pending/reserved/delayed queue diagnostics, a labeled feedback test and retries limited to listed mail failures.
- Verification enqueue recovery and worker checks for expired, superseded or inactive-account codes.
- Potential spam review/restoration, Mark all read and ordinary feedback limits of 3/hour and 5/day with a 24-hour maximum age.
- Worker-time feedback eligibility checks and the spam-fields database migration.
- Selective incident cleanup tooling that preserved saved feedback and unrelated jobs.

The original release manifest at `scripts/email-release-files.txt` records that release's scope; it is not a manifest for future staging/documentation changes. Review the current diff and intended release scope before preparing a new owner commit. Use `https://github.com/bundavit/Dearyou.git` for the repository.

## Incident and recovery

On 14 October, a MySQL connection failure/start-limit history and stopped worker explained the stalled application queue. Restarting it released a feedback backlog and triggered a reported provider quota problem. The owner stopped the worker; Codex temporarily blanked the feedback recipient while preserving other settings. The owner then discarded exactly 1,264 queued feedback alerts through guarded cleanup, preserving the feedback records and six verification jobs.

That is historical state. On 15 October the owner confirmed a recovered active worker, an empty queue, a configured feedback recipient and three received feedback emails. Registration verification was skipped and remains untested. See [EMAIL_DELIVERY.md](EMAIL_DELIVERY.md) for the current distinction between working feedback delivery and pending code verification.

## Recorded validation

The email release passed **136 PHP tests / 1,178 assertions**, **41 separate cleanup-tool checks** and **five isolated deployment mock cases**. Tests used synthetic data and test mail transports; they did not establish production inbox delivery. The migration and application changes are already part of the deployed release; this history is not an instruction to reseed or regenerate APP_KEY.

Future updates must preserve private runtime data and TLS configuration, use a current backup and a reviewed source revision, and check worker/provider state before deliberate mail tests. Do not reuse old pinned incident scripts or run the general deployment script without reviewing its behavior. [TRANSFER.md](TRANSFER.md) records the next session's work.
