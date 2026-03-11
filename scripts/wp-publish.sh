#!/bin/zsh
# wp-publish.sh — Publish a post to rtadv.com via REST API
# Requires: COOKIE_FILE and WP_NONCE (from wp-auth.sh)
# Usage: ./scripts/wp-publish.sh <payload.json>
# payload.json: { title, slug, content, excerpt, categories, featured_media }
# Outputs: post_id, url, title

set -euo pipefail

PAYLOAD_FILE="${1:?Usage: wp-publish.sh <payload.json>}"

if [ ! -f "$PAYLOAD_FILE" ]; then
  echo "ERROR: Payload file not found: $PAYLOAD_FILE" >&2
  exit 1
fi

if [ -z "${COOKIE_FILE:-}" ] || [ -z "${WP_NONCE:-}" ]; then
  echo "ERROR: COOKIE_FILE and WP_NONCE required. Run: eval \$(./scripts/wp-auth.sh)" >&2
  exit 1
fi

HTTP_CODE=$(curl -s -w "\n%{http_code}" -b "$COOKIE_FILE" \
  -X POST "https://www.rtadv.com/wp-json/wp/v2/posts" \
  -H "X-WP-Nonce: $WP_NONCE" \
  -H "Content-Type: application/json" \
  -d @"$PAYLOAD_FILE")

RESULT=$(echo "$HTTP_CODE" | sed '$d')
STATUS=$(echo "$HTTP_CODE" | tail -1)

if [ "$STATUS" != "200" ] && [ "$STATUS" != "201" ]; then
  ERROR=$(echo "$RESULT" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('message','Unknown error'))" 2>/dev/null || echo "HTTP $STATUS")
  echo "ERROR: $ERROR" >&2
  exit 1
fi

POST_ID=$(echo "$RESULT" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('id',''))")

if [ -z "$POST_ID" ]; then
  echo "ERROR: No post ID returned" >&2
  exit 1
fi

POST_URL=$(echo "$RESULT" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('link',''))" 2>/dev/null)
POST_TITLE=$(echo "$RESULT" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d['title']['rendered'])" 2>/dev/null)

echo "POST_ID=$POST_ID"
echo "POST_URL=$POST_URL"
echo "POST_TITLE=$POST_TITLE"
