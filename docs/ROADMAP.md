# DearYou updates and remaining work

Updated 16 October 2026, Asia/Bangkok. Historical release/recovery observations are dated 15 October; public, selected authenticated read-only and isolated security/QA checks ran on 16 October. This is a work list, not a live service-status page. See [STATUS.md](STATUS.md) for the evidence boundary.

## Completed

| Update | Status and evidence |
| --- | --- |
| Email/feedback protection | Published in `5702b28` / `e64a15d`: conservative spam detection, Potential spam review/restoration, Mark all as read and capped alerts |
| Feedback email limit | Ordinary feedback alerts share a limit of 3/hour and 5/day across the website. Verification, resets and manual tests are separate email types |
| Backend layout migration | Production moved to `/var/www/dearyou/backend`; settings, data and uploads were preserved |
| Seven confirmed QA defects | Published in `8f1c618`: date validation, API media handling, unread feedback restoration, draft checkbox state, reply choices, mobile wrapping and password error placement |
| Focused live retest | Both supplied accounts used; all seven fixes passed. The 14 recorded follow-up checks include test cleanup |
| Feedback delivery recovery | Owner restarted the worker and received three emails; the live queue drained from 15 jobs to zero |
| Class project organization | Five-member, 50-minute plan, textbook and two partial draft scripts grouped under `Project Software Testing/` |
| Repository cleanup | Old one-off deployment files and the initial audit report moved to a local archive; status and transfer guidance refreshed |
| Staging preparation and repository sync | Cleanup/staging release committed and pushed as `7d149ad`; local tracked files matched `origin/main` before the new Cloudflare work |

These are selected completed changes and checks. Feedback receipt does not establish successful registration verification or password-reset delivery.

## Local implementation through Phase 4

The owner requested implementation through Phase 4. Application changes and operations helpers are now prepared locally, with permanent regressions and isolated failure/concurrency checks. See [PHASES_1_TO_4.md](PHASES_1_TO_4.md) for behavior, validation, three migrations and the required private-media rollout; see [OPERATIONS.md](OPERATIONS.md) for backup/restore and pinned deployment.

The folder differs from baseline `7d149ad`. This work does not commit, push, deploy, move production files, change DNS or confirm the installed server revision. The owner performs those actions. Private QA artifacts, credentials, runtime data, dependencies and the full textbook remain outside Git.

## Six update phases

| Phase | Current status | Evidence or next gate |
| --- | --- | --- |
| 1. Security and account recovery | Implemented locally | Patched locks, zero known advisories at audit time, session/recovery/API scope regressions |
| 2. Storage, privacy and cleanup | Implemented locally | Authorized private media, verified migration command, overlapping API upload test, rollback/deletion/mail failure regressions |
| 3. Features, routing and responsive UI | Implemented locally | Adjacent memory ordering, permitted login destinations, clipboard/draft recovery, platform logo, populated responsive acceptance |
| 4. Admin and operations | Implemented locally | Admin lifecycle/configuration regressions, guarded seeding, protected backup/restore/pinned updater, resilient workers, 18 mocked operations tests |
| 5. Cloudflare readiness | Deferred until staging works | DNS-only audio/video delivery with private access checks; proxy/cache/IP/CORS/origin policy |
| 6. Staging acceptance and owner release | Staging preparation first; owner rollout pending | Independent staging config/updater and isolated validation now; owner provisioning, actual restore/mail/media/device checks and release remain separate |

Local implementation does not replace live acceptance. [Cloudflare Free](CLOUDFLARE.md) remains the chosen plan with DigitalOcean hosting. Keep the application DNS only until protected media delivery is ready; the former public static media-host setup cannot serve the new private attachments.

The owner selected staging setup before Cloudflare. Use [STAGING.md](STAGING.md) to add `staging.dearyous.app` at the existing DNS provider, provision separate data/services and test updates while production stays on its current release. The original phase numbers identify work packages; staging acceptance is being brought forward without changing production or Cloudflare settings.

## Security/QA evidence

Earlier 16 October audits reproduced session, API, reset, quota, export, cleanup and admin defects locally and recorded selected live read-only coverage. Those historical diagnostics intentionally asserted some faulty behavior. They remain under ignored `graphify-out/qa-security-2026-10-16/`, while new tracked regressions assert fixed behavior. This implementation does not establish that production received any fix.

The private-media release requires static server denials before reopening, all three database migrations, checksum-verified media migration, private runtime permissions and a fresh verified backup. The third migration resets old enqueue-only cleanup deadlines so a fresh accepted warning starts grace. Follow the new release notes rather than reusing an old selected-file release manifest.

## Email acceptance when resumed

| Remaining item | Next action | Completion evidence |
| --- | --- | --- |
| Registration verification | Resume only when requested; use one fresh synthetic account or resend to a controlled real inbox | Receive the current code, enter it through the ordinary page and observe successful verification |
| Password-reset delivery | Test separately with a controlled QA account when requested | Receive a fresh reset code, complete the ordinary reset and sign in with the new QA password |
| Delivery monitoring | Recheck pending jobs, worker state and provider events if mail stalls again | A diagnosed job outcome and confirmed inbox receipt where relevant |

Verification is currently deferred at the owner's request, not recorded as a confirmed new failure. Do not replay expired codes, manually verify the account to substitute for the real flow, or retry all failed jobs.

## Separate staging website

| Remaining item | Next action | Completion evidence |
| --- | --- | --- |
| Server and DNS setup | Provision the reviewed staging checkout, hostname, HTTPS and access gate | Proposed `staging.dearyous.app` resolves and the protected site works |
| Data isolation | Configure a separate database/user, APP_KEY, sessions, cache prefix, storage and log mailer | Staging checks pass; synthetic actions change only staging data and create staging-only links |
| Background processes | Install and validate the staging worker and scheduler units | Correct staging paths, active worker/timer and private log-email processing |
| Staging acceptance | Run the agreed creator/admin/media/expiry tests and inspect headers/access controls | Results tied to the exact reviewed commit, with live data and services preserved |

The baseline local guard passed 75 boundary assertions; the extended guard passed 84 after the Cloudflare media-boundary checks. DNS, real TLS, installed systemd execution and server-side isolation remain unverified. Follow [STAGING.md](STAGING.md).

## Operations before unattended automation

| Remaining item | Existing gap | Desired result |
| --- | --- | --- |
| Production updater | Legacy updater follows moving main, lacks a fresh restore-verified backup/background-write controls, and broadens cached-config permissions | Deploy a pinned revision with checked backups, protected settings, controlled writers, explicit cache steps and verified reopening |
| Backup helper | Legacy helper sources `.env`, passes a database password as an argument and prunes its destination | Use protected credential handling, bounded retention and an isolated restore check |
| Worker resilience | Previous DB outage/start-limit history; current service restarts without an explicit delay strategy | Review restart delay/recovery behavior and test a controlled outage outside production |

These are separate future changes. The cleanup records the gaps and does not claim to have hardened those scripts. Use [DEPLOYMENT.md](DEPLOYMENT.md) for the current owner-reviewed process.

## Later validation and optional improvements

- Broaden real audio/video codec/device testing and additional browser engines. Local synthetic upload lifecycles and generated WAV controls passed, but actual video playback/device acceptance remains open.
- Add type-aware static analysis with framework-aware configuration if useful; PHP syntax/Pint checks do not supply that coverage, and PHPStan has not been demonstrated here.
- Re-run dependency audits when dependencies change and before a later release; historical audit results are not current advisory checks.

## Class presentation: paused

The plan and two partial scripts exist. Member names, the final examples, the remaining scripts, slides and a timed group rehearsal are still to do after the owner resumes this work. The website is the case study; this is separate from production/staging deployment.

## Suggested order

Review the local Phase 1–4 implementation, then complete Phase 5 and Phase 6 before owner rollout. Keep a private USB copy of the textbook and runtime data outside Git. Class presentation work remains separate and paused until resumed.
