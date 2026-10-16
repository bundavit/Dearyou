# Moving DearYou with USB and Git

Git transfers committed, pushed source files. A USB copy can also preserve local edits, class references, private configuration and local runtime data. They serve different purposes.

## Personal complete copy

Copy the entire `D:\Dearyou` folder to a private USB backup, including hidden `.git` if you want Git history, saved stashes and the existing remote configuration. Copy from a stable state; stop local application/database writers before taking a consistent data backup.

Keep the following together when restoring an existing installation:

- `backend/.env`, including the matching APP_KEY. Do not replace it with an example or regenerate the key for existing data.
- Uploaded files under `backend/storage/app` and any deliberately external storage directories.
- The actual local database. A stopped SQLite database may be a file inside `backend/database`; MySQL or the Docker `dearyou-mysql` named volume needs a separate export/restore. Copying the project folder does not copy that volume or the production database.
- The class textbook and any private archive/evidence you want to retain. These remain outside Git.

The copy contains private material and is a personal backup. For class sharing, use source and synthetic examples with environment examples, not the raw runtime backup.

## On the destination computer

Work from a copy on the computer's internal drive. Check `git status` before pulling; preserve or deliberately commit local work before integrating remote changes. Use `https://github.com/bundavit/Dearyou.git` as the repository and do not force-push to resolve divergence.

To run the application, install PHP 8.4.1+, Composer and Node compatible with the lock file (Node 20.19+ or 22.12+), or use the supplied Docker stack. Install from the existing lock files with `composer install` and `npm ci` rather than updating dependency versions. Copied dependency folders are not a portable replacement for the destination runtime.

After preserving the environment and restoring the intended database/uploads, inspect and recreate `backend/public/storage` for the new absolute path if needed. Run reviewed pending migrations only against that intended local database. Avoid first-install key generation or seeding during an existing-data restore.

For a fresh installation with fresh data, use the README's local setup instead.

## What the next source commit includes

The selected release manifest includes the staging setup, updated operational docs, the class plan and the two partial draft notes. The complete textbook is kept in the USB folder, while the plan links to its expected local filename. `.env`, databases, uploads, local archives, tool configuration, dependencies and private QA evidence remain ignored.

The historical manifests `scripts/email-release-files.txt` and `scripts/qa-fix-release-files.txt` describe old releases. Use `scripts/latest-release-files.txt` for this cleanup/staging/documentation commit.

After the owner pushes, a clone or pull can retrieve those selected source documents and setup files. It still will not provision the staging server, restore runtime data or deliver the local textbook.
