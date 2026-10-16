# DearYou status

Updated 16 October 2026, Asia/Bangkok. Production observations below were last confirmed on 15 October; this document is not a live service monitor.

## Completed and confirmed

| Area | Recorded result |
| --- | --- |
| Source repository | `https://github.com/bundavit/Dearyou.git` |
| Production application | `/var/www/dearyou/backend`; web root `/var/www/dearyou/backend/public` |
| Published email/spam release | `5702b28`, integrated in `e64a15d` |
| Published QA release | `8f1c618b40a77d8358b252643cd35c54a49b34e6` |
| Feedback protection | Potential spam classification, Mark all as read, ordinary feedback limits of 3/hour and 5/day |
| Live QA | Both supplied accounts tested; all seven confirmed defects passed the focused post-deployment retest |
| Email worker | Owner reported `active/running`, `Result=success` |
| Queue recovery | Live admin page showed waiting jobs decrease from 15 to zero, with zero reserved/delayed jobs and no failed email jobs displayed |
| Feedback delivery | Owner identified arriving mail as New DearYou feedback and confirmed three received emails |
| Verification acceptance | Skipped at the owner's request; not established by feedback delivery |
| Release-time local tests | 171 PHP tests / 1,417 assertions, with offline editor and mobile-wrapping regressions |

The original live QA recorded 65 checks with seven distinct defects. The focused fix retest recorded 14 passing checks including cleanup. This is selected coverage, not a claim that every feature/browser or email type was verified. Synthetic letters/uploads were removed from active accounts and temporary API tokens were revoked.

## Prepared locally

- Staging guard, updater, Nginx template, worker/scheduler units and staging-only page banners.
- Staging guard validation: 75 boundary assertions plus PHP/Bash syntax checks passed during the 16 October cleanup. These checks do not provision DNS, TLS, services or a second website.
- A five-member, 50-minute software-testing case-study plan and two partial speaker-note drafts in `Project Software Testing/`. Slide creation remains paused.
- A selected-file commit manifest for the owner at `scripts/latest-release-files.txt`.

During the 16 October cleanup, the full backend suite passed again: **171 tests / 1,417 assertions**, using an in-memory SQLite database and test mail transport. This local rerun does not change the date of the last live production observations.

The owner must commit and push the pending local changes. Codex does not perform Git mutations or production deployments.

## Remaining work

Use [ROADMAP.md](ROADMAP.md) for priorities and completion evidence, [STAGING.md](STAGING.md) for staging preparation, [EMAIL_DELIVERY.md](EMAIL_DELIVERY.md) for mail diagnostics, and [TRANSFER.md](TRANSFER.md) for USB/Git transfer.

The legacy production updater and backup helper require hardening before they become recommended automation. Current owner-reviewed deployment preparation is in [DEPLOYMENT.md](DEPLOYMENT.md).
