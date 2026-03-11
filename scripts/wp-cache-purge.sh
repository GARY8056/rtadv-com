#!/bin/zsh
# wp-cache-purge.sh — Purge WP Rocket cache for a specific post
# Requires: COOKIE_FILE (from wp-auth.sh)
# Usage: ./scripts/wp-cache-purge.sh <post_id>

set -euo pipefail

POST_ID="${1:?Usage: wp-cache-purge.sh <post_id>}"

if [ -z "${COOKIE_FILE:-}" ]; then
  echo "ERROR: COOKIE_FILE required. Run: eval \$(./scripts/wp-auth.sh)" >&2
  exit 1
fi

HTTP_CODE=$(curl -s -w "%{http_code}" -b "$COOKIE_FILE" -L \
  "https://www.rtadv.com/?wprocket-purge-post=$POST_ID" \
  -o /dev/null)

if [ "$HTTP_CODE" != "200" ]; then
  echo "ERROR: Cache purge failed with HTTP $HTTP_CODE for post $POST_ID" >&2
  exit 1
fi

echo "OK: Cache purged for post $POST_ID"
