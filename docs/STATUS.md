# DearYou status

Updated 16 October 2026, Asia/Bangkok. Historical release/recovery observations below were confirmed on 15 October. Public, selected authenticated read-only and isolated security/QA checks ran on 16 October, as described separately below; this document is not a live service monitor.

## Current local update: Phases 1–4

Security/recovery, private media and storage, features/routing/UI and admin/operations are implemented locally. [Release notes](PHASES_1_TO_4.md) describe the changes, tests and three migrations; [OPERATIONS.md](OPERATIONS.md) describes the new owner-run scripts. Fresh locked Composer/backend npm audits report zero known advisories. Findings below describe the earlier audit state.

Final local validation: **276 PHP tests / 2,233 assertions**, independent concurrent API upload/rollback probes, 11 offline browser recovery scenarios, existing draft/wrapping regressions, frontend build, Composer validation/platform requirements, Pint, staging guard (84 assertions), Nginx integration (162 assertions) and 18 isolated operations tests. PHP tests ran on PHP 8.5.2 with SQLite and isolated storage; live PHP/MySQL/service/media/mail acceptance remains separate.

Nothing has been committed, pushed or deployed, and no production data, files, DNS or Cloudflare setting changed. The folder differs from baseline `7d149ad`; installed production fixes remain unverified. Phase 5 is protected Cloudflare media/proxy readiness; Phase 6 is real staging/restore/mail/media acceptance and owner release. The former static `PUBLIC_MEDIA_URL` attachment design is superseded by authorized routes; keep application DNS only until Phase 5 is ready.

The owner then selected **staging first, Cloudflare afterward**. Staging preparation now matches private media, requires an exact reviewed commit/clean checkout and has isolated validation: 129 Linux boundary assertions, 32 real-config integration assertions and 9 mocked updater tests. [STAGING.md](STAGING.md) covers first-time owner setup at the existing DNS provider and the update workflow; its DNS/server/HTTPS/services remain unprovisioned by this local work.

The dated sections below retain historical release and QA observations. Their pending findings and legacy-script descriptions do not override the current release notes.

## Completed and confirmed

| Area | Recorded result |
| --- | --- |
| Source repository | `https://github.com/bundavit/Dearyou.git` |
| Repository baseline | Cleanup/staging release committed and pushed as `7d149ad`; tracked local files matched `origin/main` before Cloudflare preparation |
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

## Repository baseline and new local work

- Staging guard, updater, Nginx template, worker/scheduler units and staging-only page banners are part of committed/pushed baseline `7d149ad`.
- Staging guard validation: 75 boundary assertions plus PHP/Bash syntax checks passed during the 16 October cleanup. These checks do not provision DNS, TLS, services or a second website.
- A five-member, 50-minute software-testing case-study plan and two partial speaker-note drafts in `Project Software Testing/`. Slide creation remains paused.
- The previous release's selected-file manifest remains at `scripts/latest-release-files.txt`; it does not list the later Cloudflare changes.
- New Cloudflare Free preparation is local and uncommitted: restricted real-IP snippet, application/upload cache rules and request limit, dedicated DNS-only media site, optional public-media URL and [owner-run rollout guide](CLOUDFLARE.md).
- The extended local staging guard passed **84 boundary assertions** after adding checks that reject an external media origin or inherited media-URL override; the earlier 75-assertion checks remain historical evidence for the baseline.
- Isolated Nginx template checks passed **103 assertions** on Ubuntu Nginx 1.28.3, covering IP trust, caching, staging access, media isolation and upload-size rejection. The temporary test server was stopped; installed production Nginx remains unverified by these checks.
- Cloudflare account/DNS/nameservers, installed server configuration, media TLS and deployment have not been changed. DigitalOcean remains the intended application host; its charges continue.

During the 16 October cleanup, the full backend suite passed again: **171 tests / 1,417 assertions**, using an in-memory SQLite database and test mail transport. After the new Cloudflare preparation, the latest local suite passed **176 tests / 1,452 assertions**; the frontend build, targeted Pint checks and extended staging guard's 84 assertions also passed. These local checks do not verify the installed production revision or server settings.

The owner performs all commits, pushes, DNS changes and deployments. The new local Cloudflare work means the working folder now differs from the committed baseline; it must not be described as identical to GitHub after those edits. Dependency security updates reported during the earlier scan remain pending and are not included in this preparation.

## Security and QA review: 16 October

- Live public coverage: 32 targeted HTTP probes, six browser page states at four widths and 50 focused navigation/FAQ assertions. Sampled authentication barriers, secret-file denials, CSRF rejection, HTTPS/canonical redirects and secure session-cookie attributes behaved as expected; no sampled 5xx or horizontal overflow. Two minor UI defects remain: the login Email field lacks a programmatic label, and the mobile Feedback menu stays open over its destination heading.
- Live authenticated read-only follow-up: both owner-supplied accounts passed login/logout and 20 expected route/destination checks, including three cross-role 403 denials. All 60 layout samples at 320/390/1440px had no document horizontal overflow; no uncaught page errors or sampled 5xx occurred. The harness omitted a public logo asset, separately verified with HEAD 200. No content, settings or password changes, private-detail opens or email sends were performed; credentials and private page contents were not saved in QA artifacts.
- Local authenticated coverage: 24 creator/recipient/admin checks across 13 page states at desktop/mobile widths, using synthetic accounts and separate SQLite/storage. Publishing, replies, inbox, admin views and cross-role denial passed; no uncaught JavaScript errors or horizontal overflow. The local PHP server was stopped.
- The existing backend suite passed again: 176 tests / 1,452 assertions. Separate diagnostic tests reproduced missing password/session revocation and a storage-quota race. Link revocation retains known public attachment URLs; a larger-media keepsake probe also exhausted an explicit 128 MiB memory limit under the documented conditional settings.
- Fresh dependency audits still report 15 PHP advisories across four packages and eight backend npm dev/build packages. The affected Symfony request parser is the first dependency priority. The documented production release's lock matches the reviewed lock, but installed live package versions were not inspected.
- Bounded scans found no high-confidence secret matches in 256 current tracked text files or 2,789 historical text blobs across 57 reachable commits. This excludes ignored/private files and is not a guarantee that no secrets exist.
- Detailed report and sanitized evidence are retained locally under `graphify-out/qa-security-2026-10-16/`. Owner-account access was limited to the read-only follow-up and its temporary sessions were logged out. No application security fixes, commits or deployments were performed. The subsequent local UI/navigation changes are described below; they do not resolve the security findings. Production write workflows, exploitation of the locally reproduced findings, installed server state and real email acceptance remain outside this pass.

## Feature, routing and responsive follow-up

- The full suite passed after the UI/view/script changes: **176 tests / 1,452 assertions**. The frontend build and existing offline draft checkbox/radio regression passed.
- Added local feature diagnostics cover **54 cases**: auth/API **21 / 89 assertions**, content/media **16 / 252**, admin/feedback **17 / 143**. Thirteen cases intentionally assert current faulty behavior; one expected-behavior test fails because third-memory move-up swaps the first/third items. Do not describe these diagnostic results as all features passing.
- Additional findings include granular API response-scope leakage, conditional email-case recovery failure, reset queue/expired-mail handling, wrong-role intended login redirects, deleted-owner moderation failures, cleanup warning/deletion failure handling and smaller admin lifecycle/diagnostic gaps. Detailed conditions are in the private feature report; production exploitation was not attempted.
- Locally fixed: admin logo now opens `/admin/platform` as chosen by the owner; the personal Dashboard stays available. A real-browser routing pass completed **44 checks / 34 actual link clicks**, with 34 desktop/mobile width samples and no overflow. Mobile navbar links now close their panels; the login Email field has an associated label and autocomplete.
- Offline frontend checks passed **38 functional assertions**, plus the existing draft preference regression and **eight generated-WAV envelope/audio control assertions**. Two separate diagnostics confirm missing clipboard/draft-storage failure feedback; those fixes remain pending. The generated VP8 fixture contained no frames, so it establishes no application video defect or playback acceptance.
- Responsive visual review used populated synthetic creator/admin pages and long names/titles/URLs at 320/390/768/1440px. It found controls clipped by grid/flex minimum sizes even when document width looked clean. Local CSS now constrains those layouts, wraps long text, protects action labels and keeps statistics/feedback controls readable. Final creator acceptance passed **24 page/width samples and 71 control/menu/label checks**. Admin review covered **40 page/width samples plus six final targeted retests** to verify the last phone-card/desktop-number refinements. Final screenshots were inspected; no confirmed responsive defect remains in the sampled scope. Exact iteration boundaries are recorded in the private responsive reviews.
- This follow-up used isolated local data/storage/services and blocked external requests. No additional production login, real mail, account/content changes, DNS changes, commits or deployment were performed. The local fixes remain undeployed. Follow the **six update phases** in [ROADMAP.md](ROADMAP.md); the full feature evidence is local at `graphify-out/qa-security-2026-10-16/FEATURE_QA_REPORT.md`.

## Remaining work

Use [ROADMAP.md](ROADMAP.md) for priorities and completion evidence, [CLOUDFLARE.md](CLOUDFLARE.md) for the owner-run Cloudflare rollout, [STAGING.md](STAGING.md) for staging preparation, [EMAIL_DELIVERY.md](EMAIL_DELIVERY.md) for mail diagnostics, and [TRANSFER.md](TRANSFER.md) for USB/Git transfer.

The new owner-run updater and backup/restore helpers are documented in [OPERATIONS.md](OPERATIONS.md). Their isolated checks do not replace a real restore rehearsal.
