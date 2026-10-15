# DearYou QA fixes — owner release instructions

All seven live QA findings have local fixes prepared on top of `e64a15d`. They are not committed, pushed, or deployed by Codex. Production remains at the owner's previous deployment until the owner publishes and deploys this release. Use **https://github.com/bundavit/Dearyou.git**.

## Changes ready for review

- Invalid relationship-date submissions retain a usable editor and validation message.
- API image, audio, and profile media use the browser's shared storage/quota handling. Upload/removal fields are not sent to SQL. Omitted API media and response settings retain their previous values.
- Restoring feedback to New returns to the unread feedback list.
- Draft restoration preserves the optional-private-note checkbox.
- Recipient choices are validated for the configured mode; message mode requires a nonblank reply.
- Long links wrap in public letter content.
- Profile, password, and deletion errors appear only in the corresponding form.

Validation completed locally: **171 PHP tests / 1,417 assertions passed**; offline Playwright autosave regression passed; public letter long-link regression passed at 390, 768, and 1,440 px. The local tests do not send provider email. Verification and inbox delivery were not retested live.

The release manifest contains 19 selected files. Existing staging-site work, deployment one-off scripts, local QA evidence, credentials, and unrelated pending files are outside this manifest. There are no new dependencies or migrations in this release. Run no seeder and generate no application key.

## 1. Stage and review in VS Code PowerShell

Copy this whole block into **VS Code's PowerShell terminal**, not DigitalOcean:

```powershell
Set-Location D:\Dearyou
if ((git branch --show-current) -ne 'main') { throw 'Switching branches needs review. Stop here.' }
git remote set-url origin https://github.com/bundavit/Dearyou.git
if ($LASTEXITCODE -ne 0) { throw 'Remote update failed.' }
git fetch origin
if ($LASTEXITCODE -ne 0) { throw 'Fetch failed. Stop here.' }
$difference = (git rev-list --left-right --count origin/main...HEAD).Trim()
if ($LASTEXITCODE -ne 0 -or $difference -notmatch '^0\s+0$') {
    throw 'Local and remote history differ. Share the counts before continuing; do not force-push.'
}
$alreadyStaged = @(git diff --cached --name-only)
if ($LASTEXITCODE -ne 0 -or $alreadyStaged.Count -ne 0) {
    throw 'The index already contains staged work. Review it before continuing.'
}
$files = Get-Content .\scripts\qa-fix-release-files.txt
git add -- $files
if ($LASTEXITCODE -ne 0) { throw 'Staging failed. Stop here.' }
git diff --cached --check
if ($LASTEXITCODE -ne 0) { throw 'Staged whitespace check failed.' }
git --no-pager diff --cached --stat
```

The review should contain exactly the manifest's 19 files. The full local audit is in `docs/qa-2026-10-14/QA_RETEST_2026-10-15.md`; it is local evidence and is intentionally ignored by Git.

## 2. Commit and push yourself after reviewing

Run in the same **VS Code PowerShell** terminal:

```powershell
$expectedFiles = @(Get-Content .\scripts\qa-fix-release-files.txt | Sort-Object)
$stagedFiles = @(git diff --cached --name-only | Sort-Object)
if ($LASTEXITCODE -ne 0 -or @(Compare-Object $expectedFiles $stagedFiles).Count -ne 0) {
    throw 'Staged files differ from the reviewed manifest. Stop here.'
}
git commit -m "Fix issues found in live DearYou QA"
if ($LASTEXITCODE -ne 0) { throw 'Commit failed. Stop here.' }
git push origin main
if ($LASTEXITCODE -ne 0) { throw 'Push failed. Share the error; do not force-push.' }
git rev-parse HEAD
```

Share the final full commit SHA after the push. That identifies the exact new release for deployment.

## 3. Deploy the exact pushed release

The existing server now uses `/var/www/dearyou/backend`, and its checkout is detached at `e64a15d`. Do not apply the older root-layout migration again or assume a branch `git pull` is appropriate.

After the new SHA is known, prepare a pinned update block against that revision. Preserve the existing backend `.env`, APP_KEY, database, uploads, TLS site, ownership, and rollback snapshot. Keep the worker stopped and feedback notifications muted while verification is excluded. Stop scheduling during the maintenance/update window, back up before switching source, rebuild Composer autoload plus configuration/routes/views at the backend path, validate runtime permissions and HTTPS, and restore the scheduler after checks pass.

Do not run the general `deploy/deploy.sh` for this paused-mail incident: it automatically starts the worker. This QA release needs no database migration, dependency update, or frontend compilation. Reuse the existing Composer lock file; no `composer update` is required.

After the owner deploys, repeat the seven failed cases and a short login/publish/reply smoke test on the live site. Email and verification remain a separate task when requested.
