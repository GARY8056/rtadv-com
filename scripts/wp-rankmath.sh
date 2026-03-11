#!/bin/zsh
# wp-rankmath.sh — Set Rank Math SEO meta for a post
# Requires: COOKIE_FILE and WP_NONCE (from wp-auth.sh)
# Usage: ./scripts/wp-rankmath.sh <post_id> <focus_keywords> <seo_title> <seo_description>
# Example: ./scripts/wp-rankmath.sh 56025 "紙盒印刷,彩盒印刷" "SEO標題｜圓廣印刷" "SEO描述..."

set -euo pipefail

POST_ID="${1:?Usage: wp-rankmath.sh <post_id> <focus_kw> <seo_title> <seo_desc>}"
FOCUS_KW="${2:?Missing focus keywords}"
SEO_TITLE="${3:?Missing SEO title}"
SEO_DESC="${4:?Missing SEO description}"

if [ -z "${COOKIE_FILE:-}" ] || [ -z "${WP_NONCE:-}" ]; then
  echo "ERROR: COOKIE_FILE and WP_NONCE required. Run: eval \$(./scripts/wp-auth.sh)" >&2
  exit 1
fi

PAYLOAD=$(python3 -c "
import json, sys
print(json.dumps({
    'objectType': 'post',
    'objectID': int(sys.argv[1]),
    'meta': {
        'rank_math_focus_keyword': sys.argv[2],
        'rank_math_title': sys.argv[3],
        'rank_math_description': sys.argv[4]
    }
}, ensure_ascii=False))
" "$POST_ID" "$FOCUS_KW" "$SEO_TITLE" "$SEO_DESC")

RESULT=$(curl -s -b "$COOKIE_FILE" \
  -X POST "https://www.rtadv.com/wp-json/rankmath/v1/updateMeta" \
  -H "X-WP-Nonce: $WP_NONCE" \
  -H "Content-Type: application/json" \
  -d "$PAYLOAD")

if echo "$RESULT" | python3 -c "import sys,json; d=json.load(sys.stdin); assert d.get('slug') == True" 2>/dev/null; then
  echo "OK: Rank Math meta updated for post $POST_ID"
else
  echo "ERROR: $RESULT" >&2
  exit 1
fi
