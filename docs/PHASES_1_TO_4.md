# Phases 1–4: local implementation and release notes

Prepared 16 October 2026. These changes are local and uncommitted. The owner commits, pushes, deploys and changes DNS. Production has not been updated by this work. See [ROADMAP.md](ROADMAP.md) for the remaining Cloudflare and staging phases.

## Implemented

| Phase | Result |
| --- | --- |
| 1. Security and recovery | Compatible dependency patches; browser/remember-session and API-token revocation after password changes/resets; scoped API responses; canonical email identity with ambiguous/deleted identity rejection; reset authorization bound to account/password/version; expired/superseded queued reset mail suppressed; queue failure restores retryable reset state |
| 2. Media and storage | Owner row serializes quota checks, uploads and persistence; failed writes remove newly created bytes and preserve old files; private attachment delivery checks current link/owner rules; bounded keepsake generation; durable deletion retries and pending-byte accounting; cleanup warning grace begins after a delivery-capable mail transport accepts the message |
| 3. Features and UI | Adjacent memory movement; same-origin, role-aware intended login destinations; clipboard/manual-copy and browser draft-storage failure feedback; admin logo opens `/admin/platform`; responsive populated layouts and mobile navigation fixes |
| 4. Admin and operations | Deleted-owner moderation; safe trashed-admin purge and fresh deletion checks; unblocking requires creator republishing; configured queue/private-storage health; production seed guard; protected backups, isolated restore verification and exact-commit deployment; worker restart/backoff/graceful-stop settings |

## Attachment policy

New media uses `backend/storage/app/private/media`. The historical disk name `public` and stored relative paths remain for compatibility; they no longer imply public access. Owners use authenticated `/media/letters/{letter}/{sha256(path)}` routes, and their avatar uses `/media/avatar`. API clients need `letters:read` and ownership at `GET /api/letters/{letter}/media/{sha256(path)}`. Public recipients use `/l/{current-token}/media/{sha256(path)}`. Responses support native byte ranges and use private/no-store headers.

Every new public media request checks publication, letter/link expiry, link activation, moderation and owner availability. Regeneration invalidates the previous media URL. Owners can still view their own draft/expired attachments while signed in. Already received or exported bytes cannot be recalled; no-store and future request denial do not erase a recipient's saved copy.

Legacy files can be read through the same authorization gates during migration. The Nginx production, staging and legacy media templates, plus Apache `.htaccess`, now deny `/storage/letters/`, `/storage/avatars/` and `/storage/memories/`. **Install these denials in every existing HTTP/HTTPS/media virtual host before reopening this release.** Keep them installed after migration because unreferenced historical files are not copied or inventoried by the migration command. Purge any earlier private-media CDN caches. A repository template change alone does not change installed Nginx.

`PUBLIC_MEDIA_URL` is retained only as a legacy static-disk configuration value. Letter pages, editors, profiles and response fragments ignore it and use authorized application routes. The earlier static media-host rollout cannot serve these private attachments. [Cloudflare Phase 5](CLOUDFLARE.md) must reconcile DNS-only audio/video delivery with this policy before application proxy activation.

## Owner release requirements

Review the complete diff, the three new database migrations and both lockfiles. Follow [OPERATIONS.md](OPERATIONS.md) for the quiescent backup, isolated restore receipt and pinned release. Preserve `.env`, `APP_KEY`, database, media, certificates and existing settings. Do not seed sample data on production.

The migrations add `users.session_version`, the independent `media_deletions` retry manifest, and warning delivery state. The warning migration clears previous enqueue-only warning/deadline timestamps; a fresh accepted warning is required before destructive cleanup. Existing version-zero browser sessions remain usable until the next password change/reset. Apply all three migrations before serving the new application.

Provision the private media root outside all web/symlink targets, with the intended shared runtime group and no world access. The private disk uses group-controlled `0660` files and `0770` directories; runtime parent directories should preserve their shared group with setgid. Check service-account access as well as deployment-account access. Existing files may need an owner-reviewed permission adjustment; this release does not apply recursive permission changes.

With the site and writers paused, preview the media migration using the new code and the correct production environment:

```bash
cd /var/www/dearyou/backend
sudo -u www-data php artisan dearyou:secure-media
```

The preview reads/checks referenced paths without writing. Missing files or checksum conflicts fail and need review. The updated production deploy helper runs deletion retries and `dearyou:secure-media --apply --remove-public` after database migrations and before reopening. It streams each legacy copy to private storage, verifies SHA-256, refuses conflicting private bytes, and removes the legacy copy only after verification. It is repeatable. The command includes soft-deleted owners/letters and never rewrites stored paths. A normal database migration does not move files.

If deploying manually, perform the same sequence in maintenance: migrate, retry recorded deletions, preview and apply the secure-media command, rebuild caches, verify service-account readiness, restart the correct worker, and then reopen. Do not run the production updater against staging; use [STAGING.md](STAGING.md) with its separate data/storage/services.

## Cleanup and warning behavior

Failed explicit removals persist a path manifest independent of deleted account/letter rows. Hourly `dearyou:retry-media-deletions` verifies physical absence before completing it; unresolved bytes continue to count toward the owner's quota. Letter deletion clears its media references in the same transaction so a restored letter cannot reuse files awaiting permanent deletion. Moderation-only soft deletion retains attachments and their quota for restoration.

Expired automatic cleanup retains references and warning state if a file cannot be deleted, reports no unverified freed space, and retries during the daily storage run after rechecking current expiry/usage under the owner lock. Republished media is not subject to an unconditional expiry-deletion job. Physical files and SQL cannot form one atomic transaction; storage/database errors require inspection of the logs and state before manual repair. A completion-email enqueue failure is logged after cleanup commits and cannot undo its cleanup record.

Warning enqueue failure remains retryable. Accepted but undelivered warnings are reissued after 24 hours with a new issuance token; old queued notices cannot start grace. A non-null successful mail result and a delivery-capable configured transport are required to start the deadline. Log/array mailers and failover chains containing them do not qualify; canceled messages do not qualify. Transport acceptance establishes handoff, not inbox receipt. Queue retries and provider/inbox acceptance still need operational monitoring.

## Validation and remaining acceptance

Final full suite: **276 tests / 2,233 assertions**, on local PHP 8.5.2 with isolated SQLite/storage. Composer validation/platform checks, Pint, frontend build and JavaScript syntax checks passed. Root and backend npm audits and the locked Composer audit reported zero known advisories at validation time.

Permanent PHP regressions cover recovery/session transports/scopes, role routing, media revocation, quotas, write/delete/mail failures and admin lifecycle/configuration. The independent concurrency harness uses real API requests in two PHP processes with one temporary SQLite database and private storage; one of two 700 KiB uploads under a 1 MiB allowance succeeds, and the other receives a quota error. Its late database-failure probe verifies the original path/checksum and removal of the replacement.

Offline browser tests cover 11 clipboard/autosave recovery scenarios and the existing draft/wrapping checks. Populated responsive and routing acceptance recorded earlier remains local evidence. Nginx integration passed 162 assertions on Ubuntu Nginx 1.28.3, including legacy-media denial, normalized/encoded paths, ranges, cache/security headers and staging access. Operations guards passed 18 isolated tests; their database/service/Git actions are mocked. Fresh Composer and backend npm audits reported zero known advisories at validation time.

These checks do not establish live rollout, actual MySQL restore privileges, real mail inbox delivery, installed service/Nginx state, all browser/media codecs or every device. Phase 5 remains Cloudflare readiness; Phase 6 remains isolated staging, real restore/application rehearsal, controlled mail/media acceptance and owner release.
