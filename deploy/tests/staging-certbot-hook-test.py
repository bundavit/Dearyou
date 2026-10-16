#!/usr/bin/env python3
"""Exercise the scoped certificate hook with synthetic Nginx/systemd commands."""
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

DEPLOY = Path(__file__).resolve().parents[1]
MOCK = r'''#!/usr/bin/env python3
import json, os, pathlib, sys
tool, args = pathlib.Path(sys.argv[0]).name, sys.argv[1:]
with open(os.environ["EVENT_LOG"], "a") as log: log.write(json.dumps([tool, *args]) + "\n")
case = os.environ.get("FAIL_CASE", "")
if tool == "nginx":
    if case == "nginx-error": sys.exit(42)
    print("conflicting server name" if case == "nginx-conflict" else "configuration is valid")
elif args == ["reload", "nginx"] and case == "reload-error": sys.exit(42)
elif args == ["is-active", "--quiet", "nginx"] and case == "nginx-inactive": sys.exit(3)
'''


class ScopedHookTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix="dearyou-cert-hook-")
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.log = self.root / "events"
        self.log.touch()
        for tool in ("nginx", "systemctl"):
            path = self.root / tool
            path.write_text(MOCK)
            path.chmod(0o700)
        source = (DEPLOY / "certbot-staging-deploy-hook.sh").read_text()
        self.assertIn("$EUID -eq 0", source)
        source = source.replace("$EUID -eq 0", '"${FIXTURE_UID:-0}" -eq 0')
        source = source.replace("/usr/sbin/nginx", f'"{self.root / "nginx"}"')
        source = source.replace("/usr/bin/systemctl", f'"{self.root / "systemctl"}"')
        self.script = self.root / "hook.sh"
        self.script.write_text(source)
        self.env = dict(os.environ, EVENT_LOG=str(self.log), RENEWED_LINEAGE="/etc/letsencrypt/live/staging.dearyous.app", RENEWED_DOMAINS="staging.dearyous.app")

    def run_hook(self, success=True, args=(), **overrides):
        self.log.write_text("")
        result = subprocess.run(["bash", str(self.script), *args], env=dict(self.env, **overrides), capture_output=True, text=True)
        self.assertEqual(result.returncode == 0, success, result.stdout + result.stderr)
        return [json.loads(line) for line in self.log.read_text().splitlines()]

    def test_only_the_exact_staging_lineage_reloads_nginx(self):
        self.assertEqual(self.run_hook(), [["nginx", "-t"], ["systemctl", "reload", "nginx"], ["systemctl", "is-active", "--quiet", "nginx"]])
        for lineage in ("", "/etc/letsencrypt/live/dearyous.app", "/etc/letsencrypt/live/staging.dearyous.app/", "/etc/letsencrypt/live/staging.dearyous.app-other"):
            with self.subTest(lineage=lineage): self.assertEqual(self.run_hook(RENEWED_LINEAGE=lineage), [])

    def test_unexpected_domains_uid_or_arguments_refuse_before_commands(self):
        for domains in ("", "dearyous.app", "staging.dearyous.app dearyous.app"):
            with self.subTest(domains=domains): self.assertEqual(self.run_hook(success=False, RENEWED_DOMAINS=domains), [])
        self.assertEqual(self.run_hook(success=False, FIXTURE_UID="1000"), [])
        self.assertEqual(self.run_hook(success=False, args=("production",)), [])

    def test_invalid_nginx_never_reloads(self):
        for case in ("nginx-error", "nginx-conflict"):
            with self.subTest(case=case): self.assertEqual(self.run_hook(success=False, FAIL_CASE=case), [["nginx", "-t"]])

    def test_reload_and_inactive_failures_are_not_reported_as_success(self):
        self.assertEqual(self.run_hook(success=False, FAIL_CASE="reload-error"), [["nginx", "-t"], ["systemctl", "reload", "nginx"]])
        self.assertEqual(self.run_hook(success=False, FAIL_CASE="nginx-inactive"), [["nginx", "-t"], ["systemctl", "reload", "nginx"], ["systemctl", "is-active", "--quiet", "nginx"]])


if __name__ == "__main__":
    unittest.main(verbosity=2)
