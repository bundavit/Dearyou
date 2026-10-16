# Repository cleanup - 16 October 2026

The cleanup organized local material and prepared the next owner-run source commit. It did not change production or complete the staging setup.

## Archived locally

Nine historical files were moved from active `docs/` into `.local-archive/cleanup-2026-10-16/historical-local-files/docs/`:

- Four one-time scripts: `deploy-8f1c618.sh`, `deploy-e64a15d-backup.sh`, `deploy-e64a15d-cutover.sh`, `deploy-e64a15d-finish.sh`.
- Their four console wrappers: `DEPLOY_8F1C618.txt`, `DEPLOY_E64A15D_BACKUP.txt`, `DEPLOY_E64A15D_CUTOVER.txt`, `DEPLOY_E64A15D_FINISH.txt`.
- The initial, outdated local `FINAL_REPORT.md`.

Original documentation/configuration copies are retained under the same archive's `before-cleanup/` directory. Moved-file contents were checked using SHA-256; the private archive includes a file manifest. Historical files were preserved, not permanently deleted, and the archive is excluded from Git and the Docker build context.

## Consolidated and retained

- Both partial speaker-note drafts moved from `docs/class-presentation/` into `Project Software Testing/drafts/`.
- The class plan and supplied STF textbook remain in the class folder. Slides remain paused. The textbook stays local/USB-only; the plan and draft notes are included in the selected source commit.
- The unfinished staging setup remains intact and is included together with its banner views and tests. It has not been provisioned on a server.
- `.cursor/` remains local and is ignored. The generated graph and raw QA evidence remain available locally under their existing ignored paths.
- Dependencies were retained for local/offline work. Credentials, databases, uploads, Git metadata/history and saved stashes were preserved.

## Documentation corrections

Current status now distinguishes the deployed backend layout, recovered feedback delivery and seven retested fixes from pending verification and staging work. Old email/QA release guides are short historical records. The old root-layout migration is marked completed. The README no longer recommends a blind production pull/cache-clear/restart sequence.

The legacy production updater and backup helper remain source files, but their limitations are recorded and their hardening is listed in [ROADMAP.md](ROADMAP.md). They are not recommended unattended deployment/backup automation.

## Commit boundary

Validation on 16 October: the full backend suite passed 171 tests / 1,417 assertions with in-memory SQLite and test mail; the staging guard's 75 boundary checks and PHP/Bash syntax checks passed. These checks do not provision staging or send production email.

Use `scripts/latest-release-files.txt` and inspect the staged diff before committing. The manifest deliberately excludes the textbook, archive, runtime data, dependencies and workstation tools. No Git stage, commit, stash, merge, push or production action is performed by this cleanup.
