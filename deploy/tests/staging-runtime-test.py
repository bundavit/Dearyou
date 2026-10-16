#!/usr/bin/env python3
"""Bounded read-only runtime acceptance with synthetic files and mocked services.

Run on Linux/WSL: python3 deploy/tests/staging-runtime-test.py
Only a temporary copy has its fixed paths/root identity redirected to fixtures.
No live service, network request, certificate, credentials or database is used.
"""
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

DEPLOY = Path(__file__).resolve().parents[1]
UNITS = ("dearyou-staging-worker.service", "dearyou-staging-scheduler.service", "dearyou-staging-scheduler.timer")

MOCK = r'''#!/usr/bin/env python3
import json, os, pathlib, sys
UNITS = __UNIT_NAMES__
tool, args = pathlib.Path(sys.argv[0]).name, sys.argv[1:]
case = os.environ.get("FAIL_CASE", "")
with open(os.environ["EVENT_LOG"], "a") as log:
    log.write(json.dumps([tool, *args]) + "\n")
if tool == "sudo":
    if args[:1] == ["-n"]: args = args[1:]
    if args[:1] == ["-u"]:
        os.environ["FIXTURE_SERVICE_USER"] = args[1]
        args = args[2:]
    if args[:1] == ["-H"]: args = args[1:]
    os.execvp(args[0], args)
elif tool == "git":
    if case == "git-error": sys.exit(42)
    if case == "dirty-source": print(" M synthetic-source.php")
elif tool == "php":
    if case == "preflight" or (case == "service-preflight" and os.environ.get("FIXTURE_SERVICE_USER") == "www-data"):
        sys.exit(42)
elif tool == "nginx":
    if case == "nginx-error": sys.exit(42)
    print("conflicting server name" if case == "nginx-conflict" else "configuration test is successful")
elif tool == "openssl":
    if (case == "wrong-certificate" and "-checkhost" in args) or (case == "expiring-certificate" and "-checkend" in args):
        sys.exit(42)
elif tool == "curl":
    if args[-1] == "https://dearyous.app/":
        print("503" if case == "production-down" else "200", end="")
    else:
        if case == "tls-error": sys.exit(60)
        print("HTTP/2 " + ("200" if case == "gate-disabled" else "401"))
        if case != "wrong-realm": print('WWW-Authenticate: Basic realm="DearYou staging"')
        if case != "indexable": print("X-Robots-Tag: noindex, nofollow, noarchive")
        if case != "cacheable": print("Cache-Control: private, no-store")
elif tool == "systemctl":
    if args[0] != "show": sys.exit("A mutation was attempted")
    unit = args[1]
    prop = next(arg.split("=", 1)[1] for arg in args if arg.startswith("--property="))
    php, app = os.environ["FIXTURE_PHP"], os.environ["FIXTURE_REPO"] + "/backend"
    command = php + " " + app + "/artisan " + (
        "queue:work --sleep=3 --tries=3 --backoff=10 --timeout=60 --max-time=3600"
        if unit == UNITS[0] else "schedule:run")
    values = {"LoadState": "loaded", "FragmentPath": os.environ["FIXTURE_UNITS"] + "/" + unit,
              "NeedDaemonReload": "no", "WorkingDirectory": app, "User": "www-data", "Group": "www-data",
              "ExecStart": "{ path=" + php + " ; argv[]=" + command + " ; ignore_errors=no ; start_time=[] ; }",
              "Unit": UNITS[1], "ActiveState": "active", "UnitFileState": "enabled",
              "LastTriggerUSecMonotonic": "1000", "ExecMainExitTimestampMonotonic": "1001",
              "Result": "success", "ExecMainStatus": "0"}
    if case == "unit-dropin" and unit == UNITS[0]: values["DropInPaths"] = "/synthetic/override.conf"
    if case == "unit-environment" and unit == UNITS[0]: values["Environment"] = "APP_ENV=production"
    if case == "unit-command" and unit == UNITS[0]: values["ExecStart"] = "{ path=/production/php ; argv[]=/production/php artisan queue:work ; }"
    if case == "daemon-reload": values["NeedDaemonReload"] = "yes"
    if case == "timer-target": values["Unit"] = "dearyou-scheduler.service"
    if case == "worker-inactive" and unit == UNITS[0]: values["ActiveState"] = "inactive"
    if case == "worker-disabled" and unit == UNITS[0]: values["UnitFileState"] = "disabled"
    if case == "timer-never-triggered": values["LastTriggerUSecMonotonic"] = "0"
    if case == "scheduler-never-ran": values["ExecMainExitTimestampMonotonic"] = "0"
    if case == "scheduler-failed" and unit == UNITS[1]: values["Result"], values["ExecMainStatus"] = "exit-code", "1"
    if case == "renewal-timer-missing" and unit not in UNITS: values["LoadState"] = "not-found"
    print(values.get(prop, ""))
else:
    sys.exit("Forbidden executable: " + tool)
'''.replace("__UNIT_NAMES__", repr(UNITS))


class RuntimeAcceptanceTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix="dearyou-runtime-test-")
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.repo = self.root / "staging checkout"
        self.bin, self.units = self.root / "bin", self.root / "units"
        self.config, self.nginx = self.root / "letsencrypt", self.root / "nginx"
        self.root_config = self.root / "root-config"
        for directory in (self.repo / "deploy", self.repo / "backend", self.bin, self.units,
                          self.nginx / "sites-available", self.nginx / "sites-enabled", self.config / "renewal", self.root_config):
            directory.mkdir(parents=True, exist_ok=True)
        self.script = self.repo / "deploy/check-staging-runtime.sh"
        source = (DEPLOY / self.script.name).read_text()
        self.assertIn("readonly STAGING_REPO=/var/www/dearyou-staging", source)
        self.assertIn("[[ $EUID -eq 0 ]]", source)
        # Test-only substitutions; the distributed checker has no path/UID overrides.
        source = source.replace("readonly STAGING_REPO=/var/www/dearyou-staging", f'readonly STAGING_REPO="{self.repo}"')
        source = source.replace("[[ $EUID -eq 0 ]]", '[[ "${FIXTURE_UID:-0}" -eq 0 ]]')
        source = source.replace("/etc/systemd/system", str(self.units)).replace("/etc/nginx", str(self.nginx))
        source = source.replace("/etc/letsencrypt", str(self.config)).replace("/root/.config/letsencrypt", str(self.root_config))
        self.php = self.bin / "php"
        source = source.replace("readonly STAGING_PHP=/usr/bin/php", f'readonly STAGING_PHP="{self.php}"')
        source = source.replace("readonly STAGING_NGINX=/usr/sbin/nginx", f'readonly STAGING_NGINX="{self.bin / "nginx"}"')
        source = source.replace('"0:$(id -g www-data):640"', '"$(id -u):$(id -g):640"')
        self.script.write_text(source)
        for unit in UNITS:
            shutil.copyfile(DEPLOY / unit, self.repo / "deploy" / unit)
            shutil.copyfile(DEPLOY / unit, self.units / unit)
        self.site = self.nginx / "sites-available/dearyou-staging"
        shutil.copyfile(DEPLOY / "nginx-dearyou-staging.conf", self.site)
        shutil.copyfile(self.site, self.repo / "deploy/nginx-dearyou-staging.conf")
        (self.nginx / "sites-enabled/dearyou-staging").symlink_to(self.site)
        self.password = self.nginx / ".htpasswd-dearyou-staging"
        self.password.write_text("do-not-print-synthetic-password-hash")
        self.password.chmod(0o640)
        self.renewal = self.config / "renewal/staging.dearyous.app.conf"
        self.renewal.write_text("[renewalparams]\nauthenticator = webroot\nwebroot_path = /var/www/letsencrypt-dearyou-staging,\naccount = do-not-print-synthetic-account\n")
        for tool in ("sudo", "git", "php", "nginx", "openssl", "curl", "systemctl", "certbot", "mysql", "mysqldump"):
            path = self.bin / tool
            path.write_text(MOCK)
            path.chmod(0o700)
        self.log = self.root / "events"
        self.log.touch()
        self.env = dict(os.environ, PATH=f"{self.bin}:{os.environ['PATH']}", EVENT_LOG=str(self.log),
                        FIXTURE_REPO=str(self.repo), FIXTURE_UNITS=str(self.units), FIXTURE_PHP=str(self.php))

    def run_check(self, success=True, args=(), script=None, **overrides):
        result = subprocess.run(["bash", str(script or self.script), *args], env=dict(self.env, **overrides), capture_output=True, text=True)
        self.assertEqual(result.returncode == 0, success, result.stdout + result.stderr)
        self.assertNotIn("do-not-print", result.stdout + result.stderr)
        self.assert_read_only()
        return result

    def events(self):
        return [json.loads(line) for line in self.log.read_text().splitlines()]

    def assert_read_only(self):
        for event in self.events():
            tool, *args = event
            self.assertNotIn(tool, ("certbot", "mysql", "mysqldump"))
            if tool == "systemctl": self.assertEqual(args[0], "show")
            if tool == "git":
                self.assertIn("status", args)
                self.assertIn("--no-optional-locks", args)
            if tool == "php": self.assertEqual(args, [str(self.repo / "deploy/check-staging.php")])
            if tool == "nginx": self.assertEqual(args, ["-t"])
            if tool == "curl":
                self.assertIn("--head", args)
                self.assertIn("--max-time", args)
                self.assertIn(args[-1], ("https://staging.dearyous.app/", "https://dearyous.app/"))
                self.assertFalse(any(arg in args for arg in ("-k", "--insecure", "-u", "--user", "-d", "--data")))

    def test_valid_runtime_and_review_items(self):
        result = self.run_check()
        self.assertIn("runtime checks passed", result.stdout)
        self.assertIn("REVIEW", result.stdout)
        self.assertEqual(result.stdout.count("PASS "), 25)

    def test_fixed_location_arguments_and_root_guard(self):
        self.run_check(success=False, args=("production",))
        self.run_check(success=False, FIXTURE_UID="1000")
        other = self.root / "misplaced.sh"
        shutil.copyfile(self.script, other)
        self.run_check(success=False, script=other)
        self.assertEqual(self.events(), [])

    def test_service_health_and_effective_unit_failures(self):
        for case in ("unit-dropin", "unit-environment", "unit-command", "daemon-reload", "timer-target",
                     "worker-inactive", "worker-disabled", "timer-never-triggered", "scheduler-never-ran", "scheduler-failed", "renewal-timer-missing"):
            with self.subTest(case=case): self.run_check(success=False, FAIL_CASE=case)

    def test_network_certificate_configuration_and_git_failures(self):
        for case in ("git-error", "dirty-source", "preflight", "service-preflight", "nginx-error", "nginx-conflict",
                     "wrong-certificate", "expiring-certificate", "tls-error", "gate-disabled", "wrong-realm", "indexable", "cacheable", "production-down"):
            with self.subTest(case=case): self.run_check(success=False, FAIL_CASE=case)

    def test_edited_unit_or_site_is_rejected(self):
        unit = self.units / UNITS[0]
        original = unit.read_bytes()
        unit.write_text("[Service]\nWorkingDirectory=/production\n")
        self.run_check(success=False)
        unit.write_bytes(original)
        self.site.write_text("server { listen 443; }\n")
        self.run_check(success=False)

    def test_password_permissions_and_symlink_are_rejected(self):
        self.password.chmod(0o644)
        self.run_check(success=False)
        self.password.unlink()
        target = self.root / "password"
        target.write_text("do-not-print-synthetic-password")
        target.chmod(0o640)
        self.password.symlink_to(target)
        self.run_check(success=False)

    def test_wrong_renewal_authenticator_root_or_installer_is_rejected(self):
        for content in ("authenticator = nginx\nwebroot_path = /var/www/letsencrypt-dearyou-staging,\n",
                        "authenticator = webroot\nwebroot_path = /var/www/production,\n",
                        "authenticator = webroot\nwebroot_path = /var/www/letsencrypt-dearyou-staging,\ninstaller = nginx\n",
                        "authenticator = webroot\nauthenticator = webroot\nwebroot_path = /var/www/letsencrypt-dearyou-staging,\n"):
            with self.subTest(content=content):
                self.renewal.write_text(content)
                self.run_check(success=False)

    def test_webroot_map_and_none_installer_are_accepted(self):
        self.renewal.write_text("[renewalparams]\nauthenticator = webroot\ninstaller = None\n[[webroot_map]]\nstaging.dearyous.app = /var/www/letsencrypt-dearyou-staging\n")
        self.run_check()

    def test_hooks_are_counted_without_execution_or_printing_their_contents(self):
        marker = self.root / "hook-was-executed"
        for directory in (self.config / "renewal-hooks/pre", self.config / "renewal-hooks/deploy", self.config / "renewal-hooks/post"):
            directory.mkdir(parents=True)
            hook = directory / "synthetic-hook"
            hook.write_text(f'#!/bin/sh\ntouch "{marker}"\necho do-not-print-hook-secret\n')
            hook.chmod(0o700)
        (self.config / "cli.ini").write_text("pre-hook = echo do-not-print-cli-secret\n")
        self.renewal.write_text(self.renewal.read_text() + "post_hook = echo do-not-print-lineage-secret\n")
        result = self.run_check()
        self.assertIn("Saved staging hook fields: 1; system CLI hook fields: 1", result.stdout)
        self.assertIn("Executable Certbot deploy directory hooks: 1", result.stdout)
        self.assertFalse(marker.exists())


if __name__ == "__main__":
    unittest.main(verbosity=2)
