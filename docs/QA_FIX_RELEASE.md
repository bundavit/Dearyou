# QA fix release history

**Historical completed release. The owner already committed and deployed these fixes; do not repeat the old staging/commit or pinned update commands.** Updated 16 October 2026, Asia/Bangkok. Current facts are in [STATUS.md](STATUS.md), with future deployment preparation in [DEPLOYMENT.md](DEPLOYMENT.md).

## Delivered release

The owner published and deployed `8f1c618b40a77d8358b252643cd35c54a49b34e6`, based on `e64a15d`, using `https://github.com/bundavit/Dearyou.git`. Production uses `/var/www/dearyou/backend`; the old root-layout migration must not be repeated. Codex prepared the changes and guidance but did not commit, push or deploy them.

The release addressed seven QA findings:

- Invalid relationship dates retain a usable editor and validation message.
- API image/audio/profile uploads use shared storage and quota handling; transient upload fields are excluded from SQL, and omitted media/response settings retain their values.
- Restoring feedback to New returns to the unread list.
- Draft recovery preserves the optional private-note checkbox.
- Recipient choices are checked against the configured response mode; message mode requires a nonblank reply.
- Long links wrap in recipient letter content.
- Profile, password and deletion errors appear in their corresponding form.

The manifest `scripts/qa-fix-release-files.txt` records that historical 19-file release. It does not include the pending staging infrastructure or later documentation cleanup and should not be reused as a new release manifest. This QA release required no new migration, dependency update or frontend compilation.

## Recorded validation and limits

Local validation passed **171 PHP tests / 1,417 assertions**, an offline Playwright autosave regression and the long-link layout regression at 390, 768 and 1,440 pixels. These are dated release results. A separate focused live post-deployment retest used both supplied accounts and passed all seven known fixes, with 14 recorded checks including cleanup. This was selected coverage, not an exhaustive test of every browser or feature.

The owner later confirmed an active worker, an empty queue and three received feedback messages on 15 October. Account verification was skipped and remains untested. [EMAIL_DELIVERY.md](EMAIL_DELIVERY.md) describes the pending fresh-code inbox test and the deployed feedback protections.

For the next update, review the current local diff, keep secrets and runtime data outside Git, and use owner-controlled commit/push commands for the actual new scope. Preserve production `.env`, APP_KEY, database, uploads and TLS. Do not rerun the archived `e64a15d`/`8f1c618` incident commands or run `deploy/deploy.sh` without a fresh backup and review of its service/permission behavior. See [TRANSFER.md](TRANSFER.md).
