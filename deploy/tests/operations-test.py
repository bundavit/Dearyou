#!/usr/bin/env python3
"""Isolated operations regression tests. Every DB/Git/service/network writer is mocked.

Run on Linux/WSL: python3 deploy/tests/operations-test.py
Real gzip/tar/hash utilities handle synthetic files in a private temporary directory.
"""
import json
import os
from pathlib import Path
import subprocess
import tarfile
import tempfile
import time
import unittest

DEPLOY = Path(__file__).resolve().parents[1]
CURRENT = "a" * 40
REVISION = "b" * 40

MOCK = r'''#!/usr/bin/env python3
import json, os, pathlib, sys
tool = pathlib.Path(sys.argv[0]).name
args = sys.argv[1:]
case = os.environ.get("FAIL_CASE", "")
with open(os.environ["EVENT_LOG"], "a") as log:
    log.write(json.dumps([tool, *args]) + "\n")
if tool == "sudo":
    if args[:1] == ["-n"]: args = args[1:]
    if args[:1] == ["-u"]:
        os.environ["MOCK_SERVICE_USER"] = args[1]
        args = args[2:]
    if args == ["true"]: sys.exit(0)
    os.execvp(args[0], args)
if tool == "git":
    if "status" in args:
        if case == "dirty": print(" M modified.php")
    elif "rev-parse" in args:
        print(("b" if "FETCH_HEAD^{commit}" in args else "a") * 40)
elif tool == "systemctl":
    if "show" in args: print("active" if case == "active-writer" else "inactive")
    if args[:1] == ["restart"] and case == "worker-restart": sys.exit(42)
    if args[:1] == ["is-active"] and args[-1] == "dearyou-worker.service" and case == "worker-inactive": sys.exit(3)
    if args[:1] == ["start"] and case == "timer-start": sys.exit(42)
elif tool == "mysqldump":
    if case == "dump": sys.exit(42)
    print("CREATE TABLE users(id INT); CREATE TABLE letters(id INT); CREATE TABLE migrations(id INT);")
elif tool == "mysql":
    query = next((a.split("=", 1)[1] for a in args if a.startswith("--execute=")), None)
    if query and query.startswith("CREATE DATABASE") and case == "existing-restore": sys.exit(42)
    if query and "COUNT(*)" in query:
        print("999" if case == "row-count" else "12" if "`users`" in query else "21" if "`letters`" in query else "3")
    elif not query: sys.stdin.buffer.read()
elif tool == "composer" and case == "composer": sys.exit(42)
elif tool == "npm" and case == "npm": sys.exit(42)
elif tool == "php":
    if args == ["artisan", "up"]:
        pathlib.Path("storage/framework/down").unlink(missing_ok=True)
    if case == "migrate" and args[:2] == ["artisan", "migrate"]: sys.exit(42)
    if case == "health" and args[-1].endswith("check-production.php"): sys.exit(42)
    if case == "service-health" and args[-1].endswith("check-production.php") and os.environ.get("MOCK_SERVICE_USER") == "www-data": sys.exit(42)
    if case == "media-migration" and args[:2] == ["artisan", "dearyou:secure-media"]: sys.exit(42)
    if case == "media-cleanup" and args[:2] == ["artisan", "dearyou:retry-media-deletions"]: sys.exit(42)
'''


class OperationsTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix="dearyou-operations-test-")
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.repo = self.root / "repository with spaces"
        self.app = self.repo / "backend"
        for path in ("storage/app/public/letters", "storage/app/private/media/letters", "storage/framework", "bootstrap/cache"):
            (self.app / path).mkdir(parents=True, exist_ok=True)
        (self.app / "artisan").touch()
        (self.app / "storage/framework/down").write_text('{"status":503}')
        (self.app / "storage/app/public/letters/old.jpg").write_bytes(b"synthetic legacy upload")
        (self.app / "storage/app/private/media/letters/new.jpg").write_bytes(b"synthetic private upload")
        self.executed = self.root / "env-was-executed"
        (self.app / ".env").write_text(f'APP_NAME=$(touch "{self.executed}")\nSYNTHETIC_SECRET=do-not-print-fixture\n')
        self.options = self.root / "mysql.cnf"
        self.options.write_text("[client]\nuser=synthetic\npassword=not-a-real-password\n")
        self.options.chmod(0o600)
        self.bin = self.root / "bin"
        self.bin.mkdir()
        for name in ("git", "php", "composer", "npm", "sudo", "systemctl", "mysql", "mysqldump"):
            file = self.bin / name
            file.write_text(MOCK)
            file.chmod(0o700)
        self.log = self.root / "events"
        self.log.touch()
        self.env = dict(os.environ, PATH=f"{self.bin}:{os.environ['PATH']}", APP_DIR=str(self.app), REPO_DIR=str(self.repo),
                        BACKUP_DIR=str(self.root / "backups"), MYSQL_DEFAULTS_FILE=str(self.options), DB_NAME="dearyou_synthetic",
                        WRITERS_PAUSED="1", EVENT_LOG=str(self.log), REVISION=REVISION)

    def run_script(self, script, success=True, **overrides):
        result = subprocess.run(["bash", str(DEPLOY / script)], env=dict(self.env, **overrides), capture_output=True, text=True)
        self.assertEqual(result.returncode == 0, success, result.stdout + result.stderr)
        self.assertNotIn("not-a-real-password", result.stdout + result.stderr)
        self.assertNotIn("do-not-print-fixture", result.stdout + result.stderr)
        return result

    def events(self):
        return [json.loads(line) for line in self.log.read_text().splitlines()]

    def backup(self):
        self.run_script("backup.sh")
        bundle = next((self.root / "backups").iterdir())
        self.env["BACKUP_BUNDLE"] = str(bundle)
        return bundle

    def verified_backup(self):
        bundle = self.backup()
        self.run_script("verify-backup.sh", RESTORE_DB="dearyou_restore_synthetic")
        return bundle

    def resign(self, bundle):
        result = subprocess.run(["sha256sum", "--", "database.sql.gz", "uploads.tar.gz", "environment.env", "metadata"], cwd=bundle, capture_output=True, text=True, check=True)
        (bundle / "SHA256SUMS").write_text(result.stdout)

    def test_backup_preserves_private_and_legacy_media_without_executing_env(self):
        bundle = self.backup()
        self.assertFalse(self.executed.exists())
        self.assertEqual((bundle / "environment.env").read_bytes(), (self.app / ".env").read_bytes())
        with tarfile.open(bundle / "uploads.tar.gz") as archive:
            self.assertIn("public/letters/old.jpg", archive.getnames())
            self.assertIn("private/media/letters/new.jpg", archive.getnames())
        self.assertEqual(bundle.stat().st_mode & 0o777, 0o700)
        self.assertTrue(all(file.stat().st_mode & 0o777 == 0o600 for file in bundle.iterdir()))
        for event in self.events():
            if event[0] in ("mysql", "mysqldump"):
                self.assertTrue(event[1].startswith("--defaults-extra-file="))
                self.assertFalse(any(arg.startswith("--password") for arg in event))

    def test_backup_refuses_unacknowledged_or_active_writers(self):
        for variables in ({"WRITERS_PAUSED": ""}, {"FAIL_CASE": "active-writer"}):
            with self.subTest(variables=variables):
                self.run_script("backup.sh", success=False, **variables)
        self.assertFalse(any(event[0] == "mysqldump" for event in self.events()))

    def test_backup_refuses_missing_maintenance(self):
        (self.app / "storage/framework/down").unlink()
        self.run_script("backup.sh", success=False)

    def test_backup_refuses_public_credentials(self):
        self.options.chmod(0o644)
        self.run_script("backup.sh", success=False)

    def test_backup_dump_failure_retains_incomplete_bundle_without_receipt(self):
        self.run_script("backup.sh", success=False, FAIL_CASE="dump")
        self.assertFalse(any((self.root / "backups").rglob("SHA256SUMS")))

    def test_backup_refuses_media_symlinks(self):
        (self.app / "storage/app/public/letters/linked").symlink_to(self.options)
        self.run_script("backup.sh", success=False)

    def test_restore_verification_creates_only_new_scratch_database_and_receipt(self):
        bundle = self.verified_backup()
        self.assertTrue((bundle / "restore-verified").exists())
        creates = [arg for event in self.events() for arg in event if arg.startswith("--execute=CREATE DATABASE")]
        self.assertEqual(creates, ["--execute=CREATE DATABASE `dearyou_restore_synthetic`"])
        self.assertFalse(any("DROP DATABASE" in arg for event in self.events() for arg in event))

    def test_restore_refuses_source_or_unscoped_database(self):
        bundle = self.backup()
        for database in ("dearyou_synthetic", "production", "dearyou_restore_bad`name"):
            self.run_script("verify-backup.sh", success=False, RESTORE_DB=database)
        self.assertFalse((bundle / "restore-verified").exists())

    def test_restore_failure_never_writes_receipt(self):
        bundle = self.backup()
        for case in ("existing-restore", "row-count"):
            with self.subTest(case=case):
                self.run_script("verify-backup.sh", success=False, RESTORE_DB="dearyou_restore_synthetic", FAIL_CASE=case)
                self.assertFalse((bundle / "restore-verified").exists())

    def test_checksum_failure_prevents_restore(self):
        bundle = self.backup()
        (bundle / "environment.env").write_text("tampered")
        self.run_script("verify-backup.sh", success=False, RESTORE_DB="dearyou_restore_synthetic")
        self.assertFalse(any("CREATE DATABASE" in arg for event in self.events() for arg in event))

    def test_deploy_requires_pinned_revision_clean_checkout_and_stopped_writers(self):
        self.verified_backup()
        for overrides in ({"REVISION": "main"}, {"FAIL_CASE": "dirty"}, {"FAIL_CASE": "active-writer"}):
            with self.subTest(overrides=overrides):
                self.run_script("deploy.sh", success=False, **overrides)
        self.assertFalse(any("checkout" in event for event in self.events()))

    def test_deploy_success_orders_checks_before_reopening(self):
        self.verified_backup()
        self.log.write_text("")
        self.run_script("deploy.sh")
        events = self.events()
        self.assertIn(["git", "fetch", "--no-tags", "origin", REVISION], events)
        self.assertIn(["git", "checkout", "--detach", REVISION], events)
        self.assertTrue(any(event[0] == "composer" and "--no-scripts" in event for event in events))
        self.assertIn(["npm", "ci", "--ignore-scripts"], events)
        self.assertFalse(any("pull" in event or "seed" in event or "key:generate" in event for event in events))
        active = events.index(["systemctl", "is-active", "--quiet", "dearyou-worker.service"])
        self.assertLess(active, events.index(["php", "artisan", "up"]))
        self.assertEqual(sum(event[0] == "php" and event[-1].endswith("check-production.php") for event in events), 3)
        migrated = events.index(["php", "artisan", "migrate", "--force"])
        cleanup = events.index(["php", "artisan", "dearyou:retry-media-deletions"])
        private_migration = events.index(["php", "artisan", "dearyou:secure-media", "--apply", "--remove-public"])
        service_check = ["sudo", "-n", "-u", "www-data", "env", "DB_NAME=dearyou_synthetic", "php", str(self.repo / "deploy/check-production.php")]
        self.assertIn(service_check, events)
        self.assertIn(["sudo", "-n", "-u", "www-data", "php", "artisan", "dearyou:secure-media", "--apply", "--remove-public"], events)
        self.assertLess(migrated, cleanup)
        self.assertLess(cleanup, private_migration)
        self.assertLess(private_migration, events.index(service_check))
        self.assertLess(events.index(service_check), active)
        self.assertLess(private_migration, events.index(["php", "artisan", "up"]))

    def test_deploy_refuses_missing_or_mismatched_receipt(self):
        bundle = self.backup()
        self.run_script("deploy.sh", success=False)
        self.run_script("verify-backup.sh", RESTORE_DB="dearyou_restore_synthetic")
        (bundle / "restore-verified").write_text("RESTORE_VERSION=1\nBACKUP_MANIFEST_SHA256=wrong\n")
        self.run_script("deploy.sh", success=False)

    def test_deploy_refuses_old_backup_even_with_fresh_restore(self):
        bundle = self.backup()
        metadata = bundle / "metadata"
        lines = [f"CREATED_AT={int(time.time()) - 7200}" if line.startswith("CREATED_AT=") else line for line in metadata.read_text().splitlines()]
        metadata.write_text("\n".join(lines) + "\n")
        self.resign(bundle)
        self.run_script("verify-backup.sh", RESTORE_DB="dearyou_restore_synthetic")
        self.run_script("deploy.sh", success=False)

    def test_deploy_refuses_changed_configuration(self):
        self.verified_backup()
        (self.app / ".env").write_text("changed after backup")
        self.run_script("deploy.sh", success=False)

    def test_deploy_refuses_reopened_then_repaused_application(self):
        self.verified_backup()
        marker = self.app / "storage/framework/down"
        marker.unlink()
        marker.write_text('{"status":503}')
        self.run_script("deploy.sh", success=False)

    def test_deploy_failure_retains_maintenance_and_stops_writers(self):
        self.verified_backup()
        for case in ("composer", "npm", "migrate", "health", "media-cleanup", "media-migration", "service-health", "worker-restart", "worker-inactive", "timer-start"):
            with self.subTest(case=case):
                self.log.write_text("")
                self.run_script("deploy.sh", success=False, FAIL_CASE=case)
                self.assertTrue((self.app / "storage/framework/down").exists())
                events = self.events()
                self.assertIn(["systemctl", "stop", "dearyou-scheduler.timer", "dearyou-scheduler.service", "dearyou-worker.service"], events)
                if case != "timer-start":
                    self.assertNotIn(["php", "artisan", "up"], events)

    def test_worker_configuration_keeps_timeout_below_queue_reservation(self):
        for name in ("dearyou-worker.service", "dearyou-staging-worker.service"):
            unit = (DEPLOY / name).read_text()
            for setting in ("StartLimitIntervalSec=0", "RestartSec=10", "TimeoutStopSec=120", "KillSignal=SIGTERM", "UMask=0027", "--backoff=10", "--timeout=60"):
                self.assertIn(setting, unit)
        staging = (DEPLOY / "deploy-staging.sh").read_text()
        self.assertIn("--tries=3 --backoff=10 --timeout=60 --max-time=3600", staging)


if __name__ == "__main__":
    unittest.main(verbosity=2)
