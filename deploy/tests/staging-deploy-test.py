#!/usr/bin/env python3
"""Run the staging updater with synthetic files and mocked external writers.

Linux/WSL: python3 deploy/tests/staging-deploy-test.py
The production script keeps its fixed paths. Only a temporary COPY is rewritten
to fixture paths; Git, PHP, Composer, npm, sudo and systemd are all mocked. No
database, live service, network or actual checkout is used by this harness.
"""
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

DEPLOY = Path(__file__).resolve().parents[1]
REVISION = "b" * 40
STAGING_UNITS = (
    "dearyou-staging-worker.service",
    "dearyou-staging-scheduler.service",
    "dearyou-staging-scheduler.timer",
)

MOCK = r'''#!/usr/bin/env python3
import json, os, pathlib, sys
tool = pathlib.Path(sys.argv[0]).name
args = sys.argv[1:]
case = os.environ.get("FAIL_CASE", "")
state_file = pathlib.Path(os.environ["MOCK_STATE"])
state = json.loads(state_file.read_text()) if state_file.exists() else {}
def save(): state_file.write_text(json.dumps(state))
with open(os.environ["EVENT_LOG"], "a") as log:
    log.write(json.dumps([tool, *args]) + "\n")
if tool == "sudo":
    if args[:1] == ["-n"]: args = args[1:]
    if args[:1] == ["-u"]:
        os.environ["MOCK_SERVICE_USER"] = args[1]
        args = args[2:]
    if args == ["true"]: sys.exit(42 if case == "sudo" else 0)
    os.execvp(args[0], args)
if tool == "git":
    if "--show-toplevel" in args:
        print(os.environ["FIXTURE_REPO"] + ("/parent" if case == "nested-repository" else ""))
    elif "rev-parse" in args:
        print(("a" if case == "wrong-revision" else "b") * 40)
    elif "status" in args:
        if case == "dirty" or (case == "modified-build" and state.get("built")): print(" M application.php")
elif tool == "systemctl":
    unit = args[1] if len(args) > 1 else ""
    if args[:1] == ["show"]:
        prop = next(arg.split("=", 1)[1] for arg in args if arg.startswith("--property="))
        php = os.environ["FIXTURE_PHP"]
        app = os.environ["FIXTURE_REPO"] + "/backend"
        command = php + " " + app + "/artisan " + (
            "queue:work --sleep=3 --tries=3 --backoff=10 --timeout=60 --max-time=3600"
            if unit == "dearyou-staging-worker.service" else "schedule:run")
        values = {"LoadState": "loaded", "FragmentPath": os.environ["FIXTURE_UNITS"] + "/" + unit,
                  "NeedDaemonReload": "no", "WorkingDirectory": app, "User": "www-data", "Group": "www-data",
                  "ExecStart": "{ path=" + php + " ; argv[]=" + command + " ; ignore_errors=no ; start_time=[] ; }",
                  "Unit": "dearyou-staging-scheduler.service"}
        if case == "unit-dropin": values["DropInPaths"] = "/a/drop-in.conf"
        if case == "unit-command": values["ExecStart"] = "{ path=/production/php ; argv[]=/production/php artisan ; ignore_errors=no ; }"
        if case == "unit-environment": values["Environment"] = "APP_ENV=production"
        if case == "unit-location": values["FragmentPath"] = "/unexpected/unit"
        if case == "timer-target": values["Unit"] = "dearyou-scheduler.service"
        print(values.get(prop, ""))
    elif args[:1] == ["stop"]:
        for name in args[1:]: state[name] = False
        save()
    elif args[:1] == ["start"]:
        if case == "timer-start" and "dearyou-staging-scheduler.timer" in args: sys.exit(42)
        if case == "worker-start" and "dearyou-staging-worker.service" in args: sys.exit(42)
        for name in args[1:]: state[name] = True
        save()
    elif args[:1] == ["is-active"]:
        if case == "worker-inactive" and args[-1] == "dearyou-staging-worker.service": sys.exit(3)
        if case == "timer-inactive" and args[-1] == "dearyou-staging-scheduler.timer": sys.exit(3)
        sys.exit(0 if state.get(args[-1], False) else 3)
elif tool == "composer":
    if case == "broken-bootstrap":
        pathlib.Path("storage/framework/down").unlink(missing_ok=True)
        state["broken"] = True; save(); sys.exit(42)
    if case == "composer": sys.exit(42)
elif tool == "npm":
    if "build" in args: state["built"] = True; save()
    if case == "npm": sys.exit(42)
elif tool == "php":
    if args[-1].endswith("check-staging.php"):
        service_user = os.environ.get("MOCK_SERVICE_USER") == "www-data"
        if case == "initial-preflight" or (case == "service-preflight" and service_user): sys.exit(42)
        if state.get("up_called") and (case == "post-up-preflight" or (case == "post-up-service-preflight" and service_user)): sys.exit(42)
    if args[:2] == ["artisan", "down"]:
        if state.get("broken"): sys.exit(42)
        pathlib.Path("storage/framework/down").write_text('{"status":503}')
    if args == ["artisan", "up"]:
        pathlib.Path("storage/framework/down").unlink(missing_ok=True)
        state["up_called"] = True; save()
        if case == "up": sys.exit(42)
    failures = {"package-discovery": "package:discover", "migrate": "migrate",
                "media-cleanup": "dearyou:retry-media-deletions", "media-migration": "dearyou:secure-media",
                "config-cache": "config:cache", "route-cache": "route:cache"}
    if len(args) > 1 and args[0] == "artisan" and failures.get(case) == args[1]: sys.exit(42)
'''


class StagingDeploymentTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix="dearyou-staging-deploy-test-")
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.repo = self.root / "staging checkout"
        self.app = self.repo / "backend"
        self.units = self.root / "installed units"
        self.bin = self.root / "bin"
        for directory in (self.repo / "deploy", self.app / "storage/framework", self.app / "bootstrap/cache", self.units, self.bin):
            directory.mkdir(parents=True, exist_ok=True)
        self.php = self.bin / "php"
        source = (DEPLOY / "deploy-staging.sh").read_text()
        self.assertIn("readonly STAGING_REPO=/var/www/dearyou-staging", source)
        # Deliberate fixture-only transformation, never an environment bypass in
        # the real script. Quote the assignment because the fixture has spaces.
        source = source.replace("readonly STAGING_REPO=/var/www/dearyou-staging", f'readonly STAGING_REPO="{self.repo}"')
        source = source.replace("/var/www/dearyou-staging", str(self.repo))
        source = source.replace("readonly STAGING_PHP=/usr/bin/php", f'readonly STAGING_PHP="{self.php}"')
        source = source.replace("/usr/bin/php", str(self.php)).replace("/etc/systemd/system", str(self.units))
        self.script = self.repo / "deploy/deploy-staging.sh"
        self.script.write_text(source)
        for unit in STAGING_UNITS:
            shutil.copyfile(DEPLOY / unit, self.repo / "deploy" / unit)
            shutil.copyfile(DEPLOY / unit, self.units / unit)
        (self.app / "bootstrap/cache/config.php").write_text("<?php // synthetic config only")
        (self.app / "bootstrap/cache/config.php").chmod(0o644)
        for tool in ("php", "composer", "npm", "sudo", "systemctl", "git"):
            path = self.bin / tool
            path.write_text(MOCK)
            path.chmod(0o700)
        self.log = self.root / "events"
        self.log.touch()
        self.env = dict(os.environ, PATH=f"{self.bin}:{os.environ['PATH']}", REVISION=REVISION,
                        EVENT_LOG=str(self.log), MOCK_STATE=str(self.root / "state.json"),
                        FIXTURE_REPO=str(self.repo), FIXTURE_PHP=str(self.php), FIXTURE_UNITS=str(self.units))

    def run_script(self, success=True, args=(), script=None, **overrides):
        result = subprocess.run(["bash", str(script or self.script), *args], env=dict(self.env, **overrides), capture_output=True, text=True)
        self.assertEqual(result.returncode == 0, success, result.stdout + result.stderr)
        for event in self.events():
            if event[0] == "systemctl":
                self.assertNotIn("reload", event)
                self.assertNotIn("restart", event)
                self.assertTrue(all(arg in STAGING_UNITS for arg in event[1:] if arg.endswith((".service", ".timer"))), event)
            if event[0] == "php":
                self.assertFalse(any("check-production" in arg or arg in ("db:seed", "key:generate") for arg in event))
            if event[0] == "git":
                self.assertFalse(any(arg in ("fetch", "checkout", "pull", "reset", "clean") for arg in event))
        return result

    def events(self):
        return [json.loads(line) for line in self.log.read_text().splitlines()]

    def mutations(self):
        return [event for event in self.events() if event[0] in ("composer", "npm")
                or (event[0] == "systemctl" and event[1] in ("start", "stop"))
                or (event[0] == "php" and event[1:2] == ["artisan"])]

    def test_requires_pinned_clean_independent_checkout_before_any_mutation(self):
        for overrides in ({"REVISION": "main"}, {"REVISION": ""}, {"FAIL_CASE": "wrong-revision"},
                          {"FAIL_CASE": "dirty"}, {"FAIL_CASE": "nested-repository"}):
            with self.subTest(overrides=overrides):
                self.run_script(success=False, **overrides)
                self.assertEqual(self.mutations(), [])

    def test_refuses_arguments_and_execution_outside_fixed_checkout(self):
        self.run_script(success=False, args=("--production",))
        outside = self.root / "outside.sh"
        shutil.copyfile(self.script, outside)
        self.run_script(success=False, script=outside)
        self.assertEqual(self.mutations(), [])

    def test_refuses_symlinked_application_directory(self):
        moved = self.repo / "elsewhere"
        self.app.rename(moved)
        self.app.symlink_to(moved, target_is_directory=True)
        self.run_script(success=False)
        self.assertEqual(self.mutations(), [])

    def test_preflight_and_all_unit_guards_precede_service_stops(self):
        for case in ("initial-preflight", "service-preflight", "unit-dropin", "unit-command", "unit-environment", "unit-location", "timer-target", "sudo"):
            with self.subTest(case=case):
                self.run_script(success=False, FAIL_CASE=case)
                self.assertEqual(self.mutations(), [])

    def test_rejects_unit_file_drift_before_service_stops(self):
        (self.units / STAGING_UNITS[0]).write_text("unexpected override")
        self.run_script(success=False)
        self.assertEqual(self.mutations(), [])

    def test_success_installs_without_scripts_and_migrates_private_media_before_reopening(self):
        self.run_script()
        events = self.events()
        self.assertTrue(any(event[0] == "composer" and "--no-scripts" in event for event in events))
        self.assertIn(["npm", "--prefix", str(self.app), "--global=false", "ci", "--ignore-scripts"], events)
        commands = [["php", "artisan", "down", "--retry=60"],
                    ["php", "artisan", "package:discover", "--ansi"],
                    ["php", "artisan", "migrate", "--force"],
                    ["php", "artisan", "dearyou:retry-media-deletions"],
                    ["php", "artisan", "dearyou:secure-media", "--apply", "--remove-public"],
                    ["php", "artisan", "config:cache"],
                    ["systemctl", "start", STAGING_UNITS[0]],
                    ["php", "artisan", "up"],
                    ["systemctl", "start", STAGING_UNITS[2]]]
        positions = [events.index(command) for command in commands]
        self.assertEqual(positions, sorted(positions))
        self.assertIn(["sudo", "-n", "-u", "www-data", str(self.php), "artisan", "dearyou:secure-media", "--apply", "--remove-public"], events)
        self.assertIn(["sudo", "-n", "-u", "www-data", str(self.php), "artisan", "dearyou:retry-media-deletions"], events)
        self.assertGreater(sum(event[0] == "git" and "status" in event for event in events), 1)
        self.assertEqual((self.app / "bootstrap/cache/config.php").stat().st_mode & 0o777, 0o640)
        self.assertFalse((self.app / "storage/framework/down").exists())
        up = events.index(["php", "artisan", "up"])
        self.assertEqual(sum(event[0] == "php" and event[-1].endswith("check-staging.php") for event in events[up:]), 2)

    def test_failures_during_update_keep_maintenance_and_stop_only_staging_writers(self):
        for case in ("composer", "npm", "modified-build", "package-discovery", "migrate", "media-cleanup", "media-migration", "config-cache", "route-cache", "worker-start", "worker-inactive"):
            with self.subTest(case=case):
                self.log.write_text("")
                (self.root / "state.json").unlink(missing_ok=True)
                self.run_script(success=False, FAIL_CASE=case)
                events = self.events()
                self.assertTrue((self.app / "storage/framework/down").exists())
                self.assertNotIn(["php", "artisan", "up"], events)
                self.assertGreaterEqual(events.count(["systemctl", "stop", STAGING_UNITS[2], STAGING_UNITS[1], STAGING_UNITS[0]]), 2)

    def test_late_failures_restore_maintenance_after_up_and_stop_started_services(self):
        for case in ("up", "timer-start", "timer-inactive", "post-up-preflight", "post-up-service-preflight"):
            with self.subTest(case=case):
                self.log.write_text("")
                self.run_script(success=False, FAIL_CASE=case)
                events = self.events()
                up = events.index(["php", "artisan", "up"])
                self.assertIn(["php", "artisan", "down", "--retry=60"], events[up:])
                self.assertIn(["systemctl", "stop", STAGING_UNITS[2], STAGING_UNITS[1], STAGING_UNITS[0]], events[up:])
                self.assertTrue((self.app / "storage/framework/down").exists())
                state = json.loads((self.root / "state.json").read_text())
                self.assertFalse(state.get(STAGING_UNITS[0]))
                self.assertFalse(state.get(STAGING_UNITS[2]))
                # Reset the mock's late-failure indicator for the next deployment.
                (self.root / "state.json").unlink()

    def test_broken_artisan_bootstrap_keeps_a_fallback_maintenance_marker(self):
        self.run_script(success=False, FAIL_CASE="broken-bootstrap")
        self.assertEqual(json.loads((self.app / "storage/framework/down").read_text()), {"retry": 60, "status": 503})


if __name__ == "__main__":
    unittest.main(verbosity=2)
