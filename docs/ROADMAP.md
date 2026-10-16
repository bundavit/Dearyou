# DearYou updates and remaining work

Updated 16 October 2026, Asia/Bangkok. Production facts were last checked on 15 October. This is a work list, not a live service-status page. See [STATUS.md](STATUS.md) for the evidence boundary.

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

These are selected completed changes and checks. Feedback receipt does not establish successful registration verification or password-reset delivery.

## Prepared locally; owner commit and push pending

The current selected release adds the staging guard/updater, Nginx and systemd templates, staging-only page banners, updated docs, class plan and partial draft notes. It does not deploy a staging website or change production.

Use `scripts/latest-release-files.txt` for this release. The full textbook, private archive, credentials, databases, uploads, dependencies and local QA evidence stay outside Git. The owner performs commit/push and deployment steps.

## Priority 1: complete the email acceptance checks when resumed

| Remaining item | Next action | Completion evidence |
| --- | --- | --- |
| Registration verification | Resume only when requested; use one fresh synthetic account or resend to a controlled real inbox | Receive the current code, enter it through the ordinary page and observe successful verification |
| Password-reset delivery | Test separately with a controlled QA account when requested | Receive a fresh reset code, complete the ordinary reset and sign in with the new QA password |
| Delivery monitoring | Recheck pending jobs, worker state and provider events if mail stalls again | A diagnosed job outcome and confirmed inbox receipt where relevant |

Verification is currently deferred at the owner's request, not recorded as a confirmed new failure. Do not replay expired codes, manually verify the account to substitute for the real flow, or retry all failed jobs.

## Priority 2: provision the separate staging website

| Remaining item | Next action | Completion evidence |
| --- | --- | --- |
| Server and DNS setup | Provision the reviewed staging checkout, hostname, HTTPS and access gate | Proposed `staging.dearyous.app` resolves and the protected site works |
| Data isolation | Configure a separate database/user, APP_KEY, sessions, cache prefix, storage and log mailer | Staging checks pass; synthetic actions change only staging data and create staging-only links |
| Background processes | Install and validate the staging worker and scheduler units | Correct staging paths, active worker/timer and private log-email processing |
| Staging acceptance | Run the agreed creator/admin/media/expiry tests and inspect headers/access controls | Results tied to the exact reviewed commit, with live data and services preserved |

The local guard passed 75 boundary assertions; DNS, real TLS, installed systemd execution and server-side isolation remain unverified. Follow [STAGING.md](STAGING.md).

## Priority 3: harden operations before unattended automation

| Remaining item | Existing gap | Desired result |
| --- | --- | --- |
| Production updater | Legacy updater follows moving main, lacks a fresh restore-verified backup/background-write controls, and broadens cached-config permissions | Deploy a pinned revision with checked backups, protected settings, controlled writers, explicit cache steps and verified reopening |
| Backup helper | Legacy helper sources `.env`, passes a database password as an argument and prunes its destination | Use protected credential handling, bounded retention and an isolated restore check |
| Worker resilience | Previous DB outage/start-limit history; current service restarts without an explicit delay strategy | Review restart delay/recovery behavior and test a controlled outage outside production |

These are separate future changes. The cleanup records the gaps and does not claim to have hardened those scripts. Use [DEPLOYMENT.md](DEPLOYMENT.md) for the current owner-reviewed process.

## Later validation and optional improvements

- Broaden the selected QA coverage to audio/video lifecycles and additional browser engines. Those areas were outside the latest focused live pass.
- Add type-aware static analysis with framework-aware configuration if useful; PHP syntax/Pint checks do not supply that coverage, and PHPStan has not been demonstrated here.
- Re-run dependency audits when dependencies change and before a later release; historical audit results are not current advisory checks.

## Class presentation: paused

The plan and two partial scripts exist. Member names, the final examples, the remaining scripts, slides and a timed group rehearsal are still to do after the owner resumes this work. The website is the case study; this is separate from production/staging deployment.

## Suggested order

1. Review and commit/push the selected cleanup/staging/docs files yourself.
2. Keep a private USB copy of the textbook and local runtime data that Git does not carry.
3. Resume email verification/reset acceptance when ready, and provision staging for future tests.
4. Harden deployment/backup automation before relying on it for unattended updates.
5. Resume the class presentation and later QA extensions when requested.
