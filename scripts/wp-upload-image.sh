#!/bin/zsh
# wp-upload-image.sh — Upload image to WordPress media library
# Requires: COOKIE_FILE and WP_NONCE (from wp-auth.sh)
# Usage: ./scripts/wp-upload-image.sh <image_file> <filename> [alt_text] [caption] [title] [description]
# Outputs: MEDIA_ID and MEDIA_URL

set -euo pipefail

IMAGE_FILE="${1:?Usage: wp-upload-image.sh <image_file> <filename> [alt_text] [caption] [title] [description]}"
FILENAME="${2:?Missing filename (e.g. paper-box-hero.png)}"
ALT_TEXT="${3:-}"
CAPTION="${4:-}"
IMG_TITLE="${5:-}"
IMG_DESC="${6:-}"

if [ ! -f "$IMAGE_FILE" ]; then
  echo "ERROR: Image file not found: $IMAGE_FILE" >&2
  exit 1
fi

if [ -z "${COOKIE_FILE:-}" ] || [ -z "${WP_NONCE:-}" ]; then
  echo "ERROR: COOKIE_FILE and WP_NONCE required. Run: eval \$(./scripts/wp-auth.sh)" >&2
  exit 1
fi

# Detect mime type
case "${FILENAME##*.}" in
  png)  MIME="image/png" ;;
  jpg|jpeg) MIME="image/jpeg" ;;
  webp) MIME="image/webp" ;;
  *)    MIME="image/png" ;;
esac

RESULT=$(curl -s -b "$COOKIE_FILE" \
  -X POST "https://www.rtadv.com/wp-json/wp/v2/media" \
  -H "X-WP-Nonce: $WP_NONCE" \
  -H "Content-Disposition: attachment; filename=$FILENAME" \
  -H "Content-Type: $MIME" \
  --data-binary @"$IMAGE_FILE")

MEDIA_ID=$(echo "$RESULT" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('id',''))" 2>/dev/null)

if [ -z "$MEDIA_ID" ]; then
  ERROR=$(echo "$RESULT" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('message','Unknown error'))" 2>/dev/null)
  echo "ERROR: $ERROR" >&2
  exit 1
fi

MEDIA_URL=$(echo "$RESULT" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('source_url',''))" 2>/dev/null)

# Set alt text, caption, title, description if provided
if [ -n "$ALT_TEXT" ] || [ -n "$CAPTION" ] || [ -n "$IMG_TITLE" ] || [ -n "$IMG_DESC" ]; then
  UPDATE_PAYLOAD=$(python3 -c "
import json, sys
d = {}
if sys.argv[1]: d['alt_text'] = sys.argv[1]
if sys.argv[2]: d['caption'] = sys.argv[2]
if sys.argv[3]: d['title'] = sys.argv[3]
if sys.argv[4]: d['description'] = sys.argv[4]
print(json.dumps(d, ensure_ascii=False))
" "$ALT_TEXT" "$CAPTION" "$IMG_TITLE" "$IMG_DESC")
  curl -s -b "$COOKIE_FILE" \
    -X POST "https://www.rtadv.com/wp-json/wp/v2/media/$MEDIA_ID" \
    -H "X-WP-Nonce: $WP_NONCE" \
    -H "Content-Type: application/json" \
    -d "$UPDATE_PAYLOAD" -o /dev/null
fi

echo "MEDIA_ID=$MEDIA_ID"
echo "MEDIA_URL=$MEDIA_URL"
