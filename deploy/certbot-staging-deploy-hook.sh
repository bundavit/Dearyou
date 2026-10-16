#!/usr/bin/env bash
# Optional Certbot deploy hook. Other certificate lineages cause no action.
set -euo pipefail

[[ "${RENEWED_LINEAGE:-}" == /etc/letsencrypt/live/staging.dearyous.app ]] || exit 0
[[ $# -eq 0 && $EUID -eq 0 ]] || { printf '%s\n' 'Staging certificate hook requires root and no arguments.' >&2; exit 1; }
[[ "${RENEWED_DOMAINS:-}" == staging.dearyous.app ]] || { printf '%s\n' 'Unexpected staging certificate domain set; reload refused.' >&2; exit 1; }

nginx_check="$(/usr/sbin/nginx -t 2>&1)" || { printf '%s\n' 'Nginx validation failed; staging certificate reload refused.' >&2; exit 1; }
[[ "$nginx_check" != *'conflicting server name'* ]] || { printf '%s\n' 'Conflicting Nginx server name; staging certificate reload refused.' >&2; exit 1; }
/usr/bin/systemctl reload nginx
/usr/bin/systemctl is-active --quiet nginx
printf '%s\n' 'Nginx reloaded after staging certificate renewal.'
