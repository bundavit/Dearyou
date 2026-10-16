#!/usr/bin/env python3
"""Exercise the real Nginx templates in an isolated temporary prefix.

Linux/WSL, Python 3, OpenSSL and Nginx with http_realip/ssl are required.
Run: python3 deploy/tests/nginx-templates-test.py [--nginx /path/to/nginx]
No root privileges, installed configuration, services or network downloads needed.
Fixtures replace production paths, certificates, ports and the PHP-FPM upstream.
"""

import argparse
import base64
import http.client
import ipaddress
import pathlib
import re
import shutil
import socket
import socketserver
import ssl
import struct
import subprocess
import tempfile
import threading
import time


ROOT = pathlib.Path(__file__).resolve().parents[1]
CHECKS = 0


def check(condition, message):
    global CHECKS
    if not condition:
        raise AssertionError(message)
    CHECKS += 1


def free_port():
    with socket.socket() as sock:
        sock.bind(("127.0.0.1", 0))
        return sock.getsockname()[1]


def read_exact(stream, length):
    data = b""
    while len(data) < length:
        part = stream.read(length - len(data))
        if not part:
            raise EOFError("incomplete FastCGI request")
        data += part
    return data


def decode_params(data):
    result = {}
    index = 0
    while index < len(data):
        lengths = []
        for _ in range(2):
            length = data[index]
            if length & 128:
                length = struct.unpack("!I", data[index:index + 4])[0] & 0x7FFFFFFF
                index += 4
            else:
                index += 1
            lengths.append(length)
        name, value = lengths
        result[data[index:index + name].decode()] = data[index + name:index + name + value].decode()
        index += name + value
    return result


class FastCGI(socketserver.StreamRequestHandler):
    """Small deterministic upstream; route/privacy checks use real Nginx."""

    def handle(self):
        params = b""
        while True:
            version, kind, request, length, padding, _ = struct.unpack("!BBHHBB", read_exact(self.rfile, 8))
            content = read_exact(self.rfile, length)
            read_exact(self.rfile, padding)
            if kind == 4:
                params += content
            if kind == 5 and not content:
                break
        values = decode_params(params)
        response = (
            "Status: 200 OK\r\nContent-Type: text/plain\r\n"
            "Cache-Control: public, max-age=86400\r\n"
            "Expires: Wed, 01 Jan 2031 00:00:00 GMT\r\nX-Powered-By: test\r\n\r\n"
            "DYNAMIC:" + values.get("REMOTE_ADDR", "missing")
        ).encode()
        for kind, body in [(6, response), (6, b""), (3, b"\0" * 8)]:
            self.wfile.write(struct.pack("!BBHHBB", 1, kind, request, len(body), 0, 0) + body)


def request(port, path, *, tls=False, headers=None, method="GET", source=None):
    options = {"timeout": 4}
    if source:
        options["source_address"] = (source, 0)
    if tls:
        connection = http.client.HTTPSConnection("127.0.0.1", port, context=ssl._create_unverified_context(), **options)
    else:
        connection = http.client.HTTPConnection("127.0.0.1", port, **options)
    try:
        connection.request(method, path, headers=headers or {})
        response = connection.getresponse()
        return response.status, dict((key.lower(), value) for key, value in response.getheaders()), response.read()
    finally:
        connection.close()


def privacy_headers(headers, *, staging=False, media=False):
    check(headers.get("cache-control") == "private, no-store", "private response must not be cached")
    check(headers.get("x-content-type-options") == "nosniff", "nosniff must survive every location")
    check(headers.get("x-frame-options") == ("DENY" if media else "SAMEORIGIN"), "frame header must survive")
    if staging:
        check(headers.get("x-robots-tag") == "noindex, nofollow, noarchive", "staging must keep noindex")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--nginx", default=shutil.which("nginx"))
    args = parser.parse_args()
    if not args.nginx:
        parser.error("Nginx is required; provide --nginx /path/to/nginx")
    binary = str(pathlib.Path(args.nginx).resolve())
    official = (ROOT / "nginx-cloudflare-realip.conf").read_text()
    networks = [ipaddress.ip_network(value) for value in re.findall(r"^set_real_ip_from\s+(\S+);", official, re.M)]
    check(len(networks) == 22, "expected the reviewed 15 IPv4 and 7 IPv6 Cloudflare networks")
    check(all(network.prefixlen > 0 for network in networks), "never trust all addresses")
    check(not any(ipaddress.ip_address("127.0.0.1") in network for network in networks if network.version == 4), "loopback must remain untrusted")

    with tempfile.TemporaryDirectory(prefix="dearyou-nginx-test-") as temporary:
        base = pathlib.Path(temporary)
        public = base / "app/public"
        stage = base / "staging/public"
        disk = base / "app/storage/app/public"
        for directory in [public, stage, disk, base / "snippets", base / "logs", base / "acme"]:
            directory.mkdir(parents=True, exist_ok=True)
        for app in [public, stage]:
            for name in ["index.php", "evil.php", ".env", "assets/app.css", "build/app.js", "vendor/app.css", "assets/.hidden.css", "assets/evil.php.js", "storage/photo.jpg", "storage/movie.mp4", "storage/letters/movie.mp4", "storage/avatars/profile.jpg", "storage/memories/old.jpg", "storage/evil.php", "storage/.env", "storage/.well-known/secret"]:
                target = app / name
                target.parent.mkdir(parents=True, exist_ok=True)
                target.write_bytes(b"FIXTURE:" + name.encode())
        for name in ["photo.jpg", "movie.mp4", "letters/movie.mp4", "avatars/profile.jpg", "memories/old.jpg", "evil.php", ".env", ".well-known/secret"]:
            target = disk / name
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(b"0123456789")
        (disk.parent / "private").mkdir()
        (disk.parent / "private/secret.txt").write_text("PRIVATE")
        (base / "snippets/dearyou-cloudflare-realip.conf").write_text(official)
        (base / "snippets/fastcgi-php.conf").write_text(
            "fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;\n"
            "fastcgi_param REQUEST_METHOD $request_method;\n"
            "fastcgi_param QUERY_STRING $query_string;\n"
            "fastcgi_param REMOTE_ADDR $remote_addr;\n"
        )
        cert, key = base / "cert.pem", base / "key.pem"
        subprocess.run(["openssl", "req", "-x509", "-newkey", "rsa:2048", "-nodes", "-days", "1", "-subj", "/CN=localhost", "-keyout", str(key), "-out", str(cert)], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        password = subprocess.check_output(["openssl", "passwd", "-apr1", "test-password"], text=True).strip()
        (base / "htpasswd").write_text("tester:" + password + "\n")
        auth = {"Authorization": "Basic " + base64.b64encode(b"tester:test-password").decode()}
        ports = {name: free_port() for name in ["app", "stage-http", "stage", "media-http", "media", "trusted"]}

        with socketserver.ThreadingTCPServer(("127.0.0.1", 0), FastCGI) as upstream:
            threading.Thread(target=upstream.serve_forever, daemon=True).start()

            def prepare(filename, mapping):
                text = (ROOT / filename).read_text()
                text = re.sub(r"^\s*listen \[::\]:\d+[^;]*;\n", "\n", text, flags=re.M)
                for port, name in mapping.items():
                    text = re.sub(r"listen " + str(port) + r"( ssl)?;", lambda match: f"listen 127.0.0.1:{ports[name]}{match.group(1) or ''};", text)
                replacements = {
                    "/var/www/dearyou/backend/storage/app/public": str(disk),
                    "/var/www/dearyou/backend/public": str(public),
                    "/var/www/dearyou-staging/backend/public": str(stage),
                    "/var/www/letsencrypt-dearyou-staging": str(base / "acme"),
                    "/var/www/letsencrypt-dearyou-media": str(base / "acme"),
                    "/etc/nginx/.htpasswd-dearyou-staging": str(base / "htpasswd"),
                    "unix:/run/php/php8.4-fpm.sock": "127.0.0.1:" + str(upstream.server_address[1]),
                }
                for before, after in replacements.items():
                    text = text.replace(before, after)
                text = re.sub(r"/etc/letsencrypt/live/[^/]+/fullchain.pem", str(cert), text)
                text = re.sub(r"/etc/letsencrypt/live/[^/]+/privkey.pem", str(key), text)
                text = re.sub(r"/var/log/nginx/([^;]+)", str(base / "logs") + r"/\1", text)
                return text.replace("# include snippets/dearyou-cloudflare-realip.conf;", "include snippets/dearyou-cloudflare-realip.conf;")

            app_config = prepare("nginx-dearyou.conf", {80: "app"})
            trusted_config = app_config.replace(str(ports["app"]), str(ports["trusted"]), 1).replace("include snippets/dearyou-cloudflare-realip.conf;", "include snippets/dearyou-cloudflare-realip.conf;\nset_real_ip_from 127.0.0.1;", 1)
            config = base / "nginx.conf"
            config.write_text(
                f"pid {base}/nginx.pid;\nerror_log {base}/error.log;\n"
                "events {}\nhttp {\naccess_log off;\n"
                + "\n".join(f"{kind}_temp_path {base}/{kind};" for kind in ["client_body", "fastcgi", "proxy", "scgi", "uwsgi"])
                + "\n" + app_config + trusted_config
                + prepare("nginx-dearyou-staging.conf", {80: "stage-http", 443: "stage"})
                + prepare("nginx-dearyou-media.conf", {80: "media-http", 443: "media"}) + "\n}\n"
            )
            command = [binary, "-p", str(base) + "/", "-c", str(config)]
            subprocess.run(command + ["-t"], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE)
            process = subprocess.Popen(command + ["-g", "daemon off;"], stdout=subprocess.DEVNULL, stderr=subprocess.PIPE)
            try:
                for _ in range(50):
                    try:
                        request(ports["app"], "/")
                        break
                    except (OSError, http.client.HTTPException):
                        if process.poll() is not None:
                            raise RuntimeError(process.stderr.read().decode())
                        time.sleep(0.05)
                else:
                    raise RuntimeError("Nginx did not start")

                status, headers, body = request(ports["app"], "/letter/test.jpg")
                check(status == 200 and body.startswith(b"DYNAMIC:"), "dynamic image-like routes must still reach Laravel")
                privacy_headers(headers)
                check("expires" not in headers and "x-powered-by" not in headers, "upstream privacy/cache headers must be normalized")
                for path in ["/assets/app.css", "/build/app.js", "/vendor/app.css"]:
                    status, headers, _ = request(ports["app"], path)
                    check(status == 200 and headers.get("cache-control", "").startswith("public,"), "only reviewed public assets get public caching")
                    check(headers.get("x-content-type-options") == "nosniff" and headers.get("x-frame-options") == "SAMEORIGIN", "asset location must retain security headers")
                for path in ["/assets/missing.css", "/build/missing.js", "/vendor/missing", "/storage/missing.jpg"]:
                    status, _, body = request(ports["app"], path)
                    check(status == 404 and not body.startswith(b"DYNAMIC:"), "missing assets/media cannot fall back to PHP")
                for path in ["/storage/photo.jpg", "/storage/movie.mp4"]:
                    status, headers, _ = request(ports["app"], path)
                    check(status == 200, "same-origin media must work before opt-in")
                    privacy_headers(headers)
                for path in ["/.env", "/storage/.env", "/storage/.well-known/secret", "/assets/.hidden.css", "/storage/evil.php", "/assets/evil.php.js", "/evil.php", "/index.php/path"]:
                    status, _, body = request(ports["app"], path)
                    check(status in (403, 404) and b"FIXTURE" not in body, "PHP/dotfile guards must win over static prefixes: " + path)

                for name, tls, auth_headers in [("app", False, {}), ("stage", True, auth), ("media", True, {})]:
                    for path in ["/storage/letters/movie.mp4", "/storage/avatars/profile.jpg", "/storage/memories/old.jpg", "/storage/%6c%65tters/movie.mp4", "/storage/x/../letters/movie.mp4"]:
                        status, headers, body = request(ports[name], path, tls=tls, headers=auth_headers)
                        check(status == 404 and b"FIXTURE" not in body and b"0123456789" not in body, "legacy managed media must never be served statically: " + name + path)
                        privacy_headers(headers, staging=name == "stage", media=name == "media")

                for name, source, expected in [("app", None, b"127.0.0.1"), ("trusted", None, b"198.51.100.10"), ("trusted", "127.0.0.2", b"127.0.0.2")]:
                    status, _, body = request(ports[name], "/client-ip", source=source, headers={"CF-Connecting-IP": "198.51.100.10", "X-Forwarded-For": "203.0.113.99"})
                    check(status == 200 and body == b"DYNAMIC:" + expected, "only a trusted TCP peer may set the restored visitor IP")
                _, _, body = request(ports["trusted"], "/client-ip", headers={"X-Forwarded-For": "203.0.113.99"})
                check(body == b"DYNAMIC:127.0.0.1", "X-Forwarded-For must never restore an address")

                for name, tls, extra in [("app", False, {}), ("stage", True, auth)]:
                    status, headers, _ = request(ports[name], "/upload", tls=tls, method="POST", headers={**extra, "Content-Length": str(95 * 1024 * 1024 + 1)})
                    check(status == 413, "oversized request must be rejected from headers without sending a body")
                    privacy_headers(headers, staging=name == "stage")

                for path in ["/", "/assets/app.css", "/storage/movie.mp4"]:
                    status, headers, _ = request(ports["stage"], path, tls=True)
                    check(status == 401, "staging must require Basic Auth on dynamic/assets/uploads")
                    privacy_headers(headers, staging=True)
                    status, headers, _ = request(ports["stage"], path, tls=True, headers=auth)
                    check(status == 200, "authorized staging route should work")
                    privacy_headers(headers, staging=True)
                status, _, _ = request(ports["stage"], "/assets/missing.css", tls=True, headers=auth)
                check(status == 404, "staging missing asset cannot execute PHP")

                status, headers, body = request(ports["media"], "/storage/movie.mp4", tls=True, headers={"Range": "bytes=2-5", "Origin": "https://dearyous.app"})
                check(status == 206 and body == b"2345" and headers.get("content-range") == "bytes 2-5/10", "DNS-only media must support native video range GETs")
                privacy_headers(headers, media=True)
                check(headers.get("access-control-allow-origin") == "https://dearyous.app" and "access-control-allow-credentials" not in headers, "media CORS must be apex-only without credentials")
                status, headers, body = request(ports["media"], "/storage/photo.jpg", tls=True, method="HEAD")
                check(status == 200 and not body and headers.get("content-length") == "10", "media HEAD must work")
                for path in ["/photo.jpg", "/.env", "/storage/.env", "/storage/.well-known/secret", "/storage/evil.php", "/storage/../private/secret.txt", "/storage/%2e%2e/private/secret.txt", "/storage/", "/index.php"]:
                    status, _, body = request(ports["media"], path, tls=True)
                    check(status in (403, 404) and b"PRIVATE" not in body and b"0123456789" not in body, "media host must expose only safe public-disk file paths: " + path)
                for method in ["POST", "OPTIONS"]:
                    status, _, _ = request(ports["media"], "/storage/movie.mp4", tls=True, method=method)
                    check(status == 403, "media must only permit GET and HEAD")

                status, headers, _ = request(ports["stage-http"], "/private")
                check(status == 301 and headers.get("location") == "https://staging.dearyous.app/private", "staging HTTP must redirect to its own HTTPS")
                status, headers, _ = request(ports["media-http"], "/storage/movie.mp4")
                check(status == 301 and headers.get("location") == "https://media.dearyous.app/storage/movie.mp4", "media HTTP must redirect to media HTTPS")
            finally:
                process.terminate()
                try:
                    process.communicate(timeout=5)
                except subprocess.TimeoutExpired:
                    process.kill()
                    process.communicate()
                upstream.shutdown()
    print(f"Nginx template integration passed: {CHECKS} assertions")


if __name__ == "__main__":
    main()
