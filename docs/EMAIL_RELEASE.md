# Email and feedback spam release

Updated 15 October 2026, Asia/Bangkok. Application changes remain local, uncommitted and undeployed. The owner performs Git commits/pushes and server deployment; Codex prepares changes and guides the process. Live findings below are the last confirmed results from 14 October, rather than a new server-state check.

## Current live state

Production uses the **old root installation** at `/var/www/dearyou`: `artisan` and `.env` are in that directory and `backend` is absent. The installed worker correctly targets this layout. September logs showed MySQL connection refusal and a start-limit failure, rather than an incorrect installed worker path. MySQL is now active.

After the owner's approximately 22:30 worker restart, queued feedback flooded the inbox. The owner stopped the worker and it is inactive. Resend reportedly displayed **200 / 100**; the exact quota period and remaining capacity are unconfirmed. At approximately 22:45, Codex muted new production feedback notifications by saving a blank `feedback_notify_email` while preserving all other settings. Legacy queued feedback can still send to its captured recipient if the old worker resumes.

The queue snapshot contained **1,270 jobs**, including **1,264 candidate feedback jobs**. The owner then ran the guarded exact-class cleanup successfully: **1,264 feedback notification jobs discarded, six VerifyEmail jobs retained**, with none reserved and the oldest queued 10 October. Saved feedback and failed-job history were preserved. This removed queued alerts, without deciding that every underlying submission was spam. Keep the worker stopped while provider recovery and deployment of the expired-code guard are checked.

Production is a clean `main` checkout at **e973aeb (2 July 2026)**, with Nginx root `/var/www/dearyou/public` and PHP 8.4 FPM. Local `main` contains three pre-existing unpublished commits, including the August move into `backend/`; they were not created by Codex during this work. A push/pull must account for this relocation. See [PRODUCTION_LAYOUT_MIGRATION.md](PRODUCTION_LAYOUT_MIGRATION.md) before deploying.

The 22:10 synthetic registration succeeded, but its verification email was not received and verification never completed. The marked QA account remains unverified. The direct Resend message received at 21:08 does not establish that queued verification works.

See [EMAIL_DELIVERY.md](EMAIL_DELIVERY.md) for full findings and [FEEDBACK_SPAM.md](FEEDBACK_SPAM.md) for selective cleanup and the local safeguards.

## Immediate deployment boundary

**Do not run the current `deploy/deploy.sh` on this root installation. Do not blindly pull the backend layout into the running checkout.** The script and repository service unit target `/var/www/dearyou/backend`, incompatible with the currently correct root-path worker. The script also restarts delivery, which must wait until backlog safety and provider capacity are resolved.

Before deployment, validate a recoverable database/upload backup and review a layout transition preserving:

- Production `.env`, application key, database connection and data.
- Uploaded files, logs, writable directories and cache state.
- Nginx document root and worker/scheduler paths.
- Reviewed source and matching dependencies from that version's lock file.
- The muted feedback setting and stopped worker until selective backlog handling is complete.

Do not copy the local `.env`, regenerate the production key or reseed production. Updated source uses the repository's installed Laravel API; copying isolated files into a server with different dependencies is not a reviewed release.

Read-only checks use the actual root paths:

```bash
ls -l /var/www/dearyou/artisan /var/www/dearyou/.env
sudo systemctl show dearyou-worker.service --property=LoadState,ActiveState,SubState,Result,WorkingDirectory,ExecStart
sudo systemctl status mysql dearyou-worker.service --no-pager
sudo journalctl -u dearyou-worker.service -n 50 --no-pager
```

Keep environment contents and serialized jobs private. Address layout, schema and backlog before restarting delivery. Do not use `queue:clear`, `queue:retry all` or an unreviewed worker restart. The selective procedure in [FEEDBACK_SPAM.md](FEEDBACK_SPAM.md) must preserve unrelated verification/password-reset jobs.

## Dependency-complete local release

The email and spam safeguards include a **new database migration** and must be released together. The staging-banner partial is required by the verification page; production renders it empty. Other staging infrastructure can be released separately.

These are owner instructions. Codex has not staged, committed or pushed them. Use Windows PowerShell on your computer, not the DigitalOcean console. The selected files are listed in [scripts/email-release-files.txt](../scripts/email-release-files.txt); the list includes the required migration and layout guide, and excludes real environment files and unrelated local work.

VS Code's integrated terminal works for these commands when its selected shell is PowerShell. Pre-commit cleanup on 15 October added narrow ignore rules for generated browser reports/captures, database sidecars, tooling caches and old root-layout runtime leftovers. The four README screenshots, shared Cursor workflow rule and original final report were preserved. The dated local QA report folder is ignored; real runtime data remains local. Two PHPUnit caches and one superseded scratch script were archived under the already ignored `graphify-out/local-cleanup-2026-10-15/`, without permanent deletion. Source/staging work is preserved for review, not swept into this release.

```powershell
$ErrorActionPreference = 'Stop'
Set-Location D:\Dearyou
$emailFiles = Get-Content -LiteralPath .\scripts\email-release-files.txt
git diff -- $emailFiles
git status --short
git log --oneline origin/main..HEAD
```

Review new files directly too; untracked files do not appear in the first diff. The selective cleanup tool is included in the list; follow its dry-run and review procedure in `FEEDBACK_SPAM.md` before applying it. Review later edits before using this list. Environment examples are safe templates; never add real `.env` files or credentials.

After reviewing the code and completed validation, the owner can stage the subset:

```powershell
git add -- $emailFiles
if ($LASTEXITCODE -ne 0) { throw 'Staging failed. Do not continue.' }
git diff --cached --stat
git diff --cached
```

Review the staged files and the three existing unpublished commits listed above. Then commit and push:

```powershell
git commit -m "Protect queued feedback email and improve verification recovery"
if ($LASTEXITCODE -ne 0) { throw 'Commit failed. Do not push.' }
git push origin main
if ($LASTEXITCODE -ne 0) { throw 'Push failed. Share the error; do not force-push.' }
```

The local branch is `main`, already three commits ahead of its local `origin/main`. A successful push publishes those existing commits as well as the new commit, including the move into `backend/`. Follow the repository's review policy if it differs. Inspect previously staged changes before committing. This list excludes real environment files, credentials, pre-existing `.cursor` content, the original untracked `FINAL_REPORT.md` and unrelated staging deployment setup. Avoid `git add .`. No GitHub Actions deployment workflow was found in the checkout; external deployment automation was not inspected.

## Release and acceptance sequence

1. Complete local review and expanded email/spam tests, then record results. Committing source does not update production by itself.
2. Provision separate staging with synthetic data. Apply the spam-fields migration before enabling changed routes. Use log mode to test feedback budgets, mute checks, legacy handling, spam review/restoration and verification.
3. Complete production layout preservation. The six retained jobs are verification notifications; preserve them, keep feedback muted and keep the worker stopped until the new guards and provider capacity are ready. Confirm provider quota/capacity before real sends.
4. Deploy reviewed source, matching dependencies, migration and configuration through a procedure appropriate to the established layout. Recheck Nginx and service paths before changing worker state. The current production script is not that procedure for the old root installation.
5. Once backlog and quota are safe, restart only the correct reviewed worker. Confirm processing and inspect private failures/provider events; active status alone is insufficient.
6. Keep feedback muted for one fresh verification request; confirm inbox receipt and verify using that code. Then deliberately restore the feedback recipient and confirm one diagnostic test and one legitimate marked website feedback arrive.
7. Record the deployed source and evidence; remove the synthetic QA account after verification succeeds.

The local deployment script retains maintenance after failure and installs/enables/restarts/checks its backend-layout worker before reopening. Five isolated mock cases passed for success, Composer failure, restart failure, inactive worker and maintenance-entry failure. These mocks ran no real Git/network, installations or services and do not establish root-layout compatibility.

The expanded backend suite passed **136 tests and 1,178 assertions** with isolated SQLite and test mail transport. The selective cleanup tool passed **41 additional checks**, outside that PHPUnit total, including SQLite preservation tests against a 1,270-job fixture and actual PHP stdin refusal behavior. No production queue cleanup was performed by those tests. This guide does not claim production verification delivery is repaired.

## Cloudflare and website protection

Cloudflare is optional for email repair. It does not fix a stopped worker, queued spam or exhausted Resend quota. Keep HTTPS and restrict the separate staging site's public access.

If adopting Cloudflare:

- Use **Full (strict)** with a valid origin certificate for each hostname. See [Full (strict)](https://developers.cloudflare.com/ssl/origin-configuration/ssl-modes/full-strict/).
- Preserve exact Resend SPF/DKIM/return-path and inbound-mail DNS records. Keep mail-related hostnames DNS-only and use actual dashboard values for this Tokyo-region domain. See [Resend's Cloudflare DNS guide](https://resend.com/docs/guides/dns/cloudflare).
- Protect staging using HTTPS Basic Auth or a Cloudflare Access allowlist; protect its origin against bypass. See [self-hosted Access applications](https://developers.cloudflare.com/cloudflare-one/access-controls/applications/http-apps/self-hosted-public-app/).
- Configure trusted proxy/client-IP handling before proxying so request limits identify the right visitor. Trust only the intended proxy network.
- Bypass shared caching for private recipient pages, authenticated pages, verification routes and `/storage/`. The known upload authorization defect still requires its application fix; cache bypass does not revoke a known public file URL.

No Cloudflare or DNS changes were performed. The live feedback setting was muted as described above; server worker operations were performed by the owner.
