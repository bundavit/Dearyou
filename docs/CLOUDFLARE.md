# Cloudflare Free with the existing DigitalOcean host

Updated 16 October 2026. The owner selected Cloudflare Free while keeping DigitalOcean. No DNS, nameserver, Cloudflare, server or deployment change has been performed. The owner commits, pushes and performs external setup.

The [Free website plan](https://www.cloudflare.com/plans/free/) provides free website security/performance features; DigitalOcean hosting and domain renewal remain separate costs. DearYou still needs its Laravel runtime, database, persistent media and workers on DigitalOcean.

## Current release boundary

[Phases 1–4](PHASES_1_TO_4.md) replace public static attachments with private, authorized delivery. The earlier proposed `PUBLIC_MEDIA_URL=https://media.dearyous.app/storage` setup is superseded for letter attachments and avatars. Leave it blank. The legacy media template blocks managed media paths and cannot deliver these private files. Do not restore static access to make playback work.

**Phase 5 remains open before enabling the application proxy.** DearYou supports audio/video. [Cloudflare's video guidance](https://developers.cloudflare.com/fundamentals/reference/policies-compliances/delivering-videos-with-cloudflare/) describes DNS-only subdomains as an alternative delivery route. Bypassing cache still sends bytes through a proxied hostname. The next implementation must provide DNS-only media delivery that enforces current-token, expiry, moderation and owner checks, including ranges and restrictive CORS. It must preserve staging isolation and authenticated owner previews. An ordinary static upload host does not meet that policy.

Until that endpoint is implemented and accepted, keep website records **DNS only**. Cloudflare can manage DNS during this checkpoint, but DNS-only traffic does not receive its proxy/CDN protection. Local templates and tests do not establish a completed Cloudflare rollout.

## DNS preparation

1. Export the complete zone from the current DNS provider. Preserve every MX/TXT/CAA/Resend/SPF/DKIM/DMARC and less-used subdomain record. Public lookups and an automatic scan are not a complete inventory.
2. Add the domain in Cloudflare and choose Free. Compare its proposed zone with the export; start website records DNS only and preserve the actual DigitalOcean addresses. Add IPv6 only where supported.
3. Keep Name.com as registrar. Review existing DNSSEC/DS delegation before a nameserver change; clear the old DS when needed and follow [Cloudflare's full setup](https://developers.cloudflare.com/dns/zone-setups/full-setup/setup/). Use the nameservers assigned to this zone. After validation, use the new [Cloudflare DNSSEC](https://developers.cloudflare.com/dns/dnssec/) values at the registrar.
4. Confirm the existing website, public TLS certificate and Resend records still work before changing proxy status. This DNS checkpoint requires no production content changes or bulk email test.

## Proxy preparation after private media readiness

Preserve the installed Certbot/TLS virtual host. Merge reviewed Nginx locations and headers into installed HTTPS blocks; the production repository file is an HTTP bootstrap template and must not overwrite a working TLS site. Keep the static private-media denials from [the release notes](PHASES_1_TO_4.md).

The [real-IP snippet](../deploy/nginx-cloudflare-realip.conf) trusts Cloudflare CIDRs only. Compare those with [the published list](https://www.cloudflare.com/ips/) at rollout. Include it only in virtual hosts receiving proxied requests. Never trust arbitrary `CF-Connecting-IP`, all source addresses or unrestricted forwarded headers. Verify PHP request addresses against [Cloudflare's restoration guidance](https://developers.cloudflare.com/support/troubleshooting/restoring-visitor-ips/restoring-original-visitor-ips/).

Use [Full (strict)](https://developers.cloudflare.com/ssl/origin-configuration/ssl-modes/full-strict/) and a valid matching origin certificate. Check edge certificate readiness before proxying. Preserve certificate renewal and DNS-only rollback.

Cache reviewed public assets only. Dynamic pages, letter tokens, `/media`, authenticated/admin/API routes, downloads and errors must remain private/no-store. Review existing Cache/Page Rules; avoid Cache Everything or overrides of private/no-store. Purge earlier private-media cache entries. See [Origin Cache Control](https://developers.cloudflare.com/cache/concepts/cache-control/).

The templates cap whole request bodies at `95m`, below the [Free proxied request limit](https://developers.cloudflare.com/cache/concepts/default-cache-behavior/). Effective PHP-FPM and application limits must agree, including multipart overhead and multiple files. This is not a 95 MB single-file guarantee. Verify API/upload/browser workflows before extra challenges or optimization.

A DNS-only media host can reveal the shared origin address. Review origin bypass before firewall restrictions; a blanket Cloudflare-only HTTP/HTTPS rule on a shared address would break direct media, certificate checks and rollback. Preserve SSH access.

## Validation and owner acceptance

The isolated [Nginx harness](../deploy/tests/nginx-templates-test.py) passed 162 assertions on Ubuntu Nginx 1.28.3 using temporary paths/certificates, loopback ports and a mock PHP upstream. It checks IP trust, cache/security headers, staging Basic Auth, body limits, ranges and static legacy-media denials, including normalized/encoded paths. It stops the temporary server without modifying installed Nginx. The staging guard passed 84 boundary assertions. These checks do not implement the future private media subdomain.

```bash
python3 deploy/tests/nginx-templates-test.py --nginx /path/to/nginx
php deploy/tests/check-staging-test.php
```

Phase 5 acceptance must record the exact revision, protected direct-media configuration/TLS, no cache HIT on private responses, link expiry/disable/regeneration, owner/API access, audio/video playback/seeking, visitor IP and spoof rejection, staging isolation, upload boundaries and origin policy. Phase 6 adds isolated staging, real restore/application rehearsal and controlled mail/device acceptance before owner release.

For a proxy regression, switch application records back to DNS only, preserve their origin address and public certificate, and keep private-media denials. Do not restore public attachment URLs or downgrade TLS. A code-only rollback after schema/media changes may be incompatible; use [OPERATIONS.md](OPERATIONS.md) for coordinated recovery. Reversing nameservers requires the saved complete zone and correct DNSSEC/DS delegation.
