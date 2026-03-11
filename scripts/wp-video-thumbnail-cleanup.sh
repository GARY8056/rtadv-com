#!/bin/zsh
# wp-video-thumbnail-cleanup.sh — 停用 Video Thumbnails 外掛 + 批次刪除媒體
# Usage:
#   ./scripts/wp-video-thumbnail-cleanup.sh              # dry-run (預設，只列出不刪)
#   ./scripts/wp-video-thumbnail-cleanup.sh --execute     # 實際執行刪除
#   ./scripts/wp-video-thumbnail-cleanup.sh --deactivate  # 只停用外掛
#
# Requires: WP_USER, WP_PASS env vars (or source .env)

set -euo pipefail
cd "$(dirname "$0")"

# Load .env if present
if [ -f .env ]; then
  set -a; source .env; set +a
fi

SITE_URL="https://www.rtadv.com"
MODE="${1:---dry-run}"
BATCH_SIZE=50
SLEEP_BETWEEN=1

# ── Auth ──────────────────────────────────────────────
echo "🔑 Logging in..."
eval $(./wp-auth.sh)
echo "✅ Logged in (nonce: ${WP_NONCE:0:6}...)"

# ── Deactivate Plugin ─────────────────────────────────
deactivate_plugin() {
  echo ""
  echo "🔌 Attempting to deactivate Video Thumbnails plugin..."

  # Try via REST API (WP 5.5+)
  RESULT=$(curl -s -b "$COOKIE_FILE" \
    -X POST "${SITE_URL}/wp-json/wp/v2/plugins/video-thumbnails/video-thumbnails" \
    -H "X-WP-Nonce: $WP_NONCE" \
    -H "Content-Type: application/json" \
    -d '{"status":"inactive"}' 2>/dev/null)

  if echo "$RESULT" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d.get('status',''))" 2>/dev/null | grep -q "inactive"; then
    echo "✅ Video Thumbnails plugin deactivated!"
  else
    echo "⚠️  Could not deactivate via REST API."
    echo "   → Please deactivate manually: WP 後台 → 外掛 → Video Thumbnails → 停用"
    echo "   Response: $(echo "$RESULT" | head -c 200)"
  fi
}

# ── Count ─────────────────────────────────────────────
get_total() {
  curl -sI "${SITE_URL}/wp-json/wp/v2/media?per_page=1&search=Video+Thumbnail" \
    -b "$COOKIE_FILE" 2>/dev/null | grep -i '^x-wp-total:' | sed 's/[^0-9]//g'
}

# ── Fetch IDs ─────────────────────────────────────────
fetch_ids() {
  local page=$1
  curl -fsSL "${SITE_URL}/wp-json/wp/v2/media?per_page=${BATCH_SIZE}&page=${page}&search=Video+Thumbnail&_fields=id,title&orderby=date&order=asc" \
    -b "$COOKIE_FILE" 2>/dev/null | \
    python3 -c "
import json, sys
data = json.load(sys.stdin)
for item in data:
    title = item.get('title', {}).get('rendered', '')
    if title.startswith('Video Thumbnail:'):
        print(item['id'])
"
}

# ── Delete one attachment (force=true removes file too) ──
delete_media() {
  local id=$1
  RESP=$(curl -s -o /dev/null -w "%{http_code}" \
    -X DELETE "${SITE_URL}/wp-json/wp/v2/media/${id}?force=true" \
    -b "$COOKIE_FILE" \
    -H "X-WP-Nonce: $WP_NONCE" 2>/dev/null)
  echo "$RESP"
}

# ── Main ──────────────────────────────────────────────

# Always try to deactivate plugin first
if [ "$MODE" = "--deactivate" ]; then
  deactivate_plugin
  rm -f "$COOKIE_FILE"
  exit 0
fi

deactivate_plugin

TOTAL=$(get_total)
echo ""
echo "📊 Found ${TOTAL} Video Thumbnail media items"

if [ "$MODE" = "--dry-run" ]; then
  echo ""
  echo "🔍 DRY RUN — showing first 10 items:"
  curl -fsSL "${SITE_URL}/wp-json/wp/v2/media?per_page=10&search=Video+Thumbnail&_fields=id,date,title,source_url&orderby=date&order=asc" \
    -b "$COOKIE_FILE" 2>/dev/null | \
    python3 -c "
import json, sys
data = json.load(sys.stdin)
for item in data:
    title = item.get('title', {}).get('rendered', '')[:60]
    print(f'  ID={item[\"id\"]}  date={item[\"date\"]}  {title}')
"
  echo ""
  echo "To execute deletion, run:"
  echo "  ./scripts/wp-video-thumbnail-cleanup.sh --execute"
  rm -f "$COOKIE_FILE"
  exit 0
fi

if [ "$MODE" != "--execute" ]; then
  echo "Unknown mode: $MODE"
  echo "Usage: $0 [--dry-run|--execute|--deactivate]"
  rm -f "$COOKIE_FILE"
  exit 1
fi

# ── Execute deletion ──────────────────────────────────
echo ""
echo "🗑️  Starting batch deletion..."

DELETED=0
FAILED=0
PAGE=1

while true; do
  IDS=($(fetch_ids $PAGE))

  if [ ${#IDS[@]} -eq 0 ]; then
    break
  fi

  for id in "${IDS[@]}"; do
    STATUS=$(delete_media "$id")
    if [ "$STATUS" = "200" ]; then
      DELETED=$((DELETED + 1))
    else
      FAILED=$((FAILED + 1))
      echo "  ❌ ID=$id status=$STATUS"
    fi

    # Progress every 50
    if [ $((DELETED % 50)) -eq 0 ] && [ $DELETED -gt 0 ]; then
      echo "  ✅ Deleted: $DELETED / $TOTAL (failed: $FAILED)"
    fi
  done

  sleep $SLEEP_BETWEEN

  # Re-fetch page 1 each time since items are being deleted
  # (don't increment page)
done

echo ""
echo "════════════════════════════════════"
echo "✅ Done!"
echo "   Deleted: $DELETED"
echo "   Failed:  $FAILED"
echo "   Remaining: $(get_total)"
echo "════════════════════════════════════"

rm -f "$COOKIE_FILE"
