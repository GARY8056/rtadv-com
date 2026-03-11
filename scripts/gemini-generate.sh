#!/bin/bash
# gemini-generate.sh — 使用 Google Gemini API 生成圖片
#
# 用法：
#   ./scripts/gemini-generate.sh "<prompt>" <output.png> [aspect]
#
# 範例：
#   ./scripts/gemini-generate.sh "A realistic photo of a kraft paper bag" /tmp/hero.png

set -euo pipefail

GEMINI_API_KEY="${GEMINI_API_KEY:?ERROR: GEMINI_API_KEY env var required}"
MODEL="${GEMINI_MODEL:-gemini-3-pro-image-preview}"
ENDPOINT="https://generativelanguage.googleapis.com/v1beta/models/${MODEL}:generateContent?key=${GEMINI_API_KEY}"

PROMPT="$1"
OUTPUT="${2:-/tmp/gemini-output.png}"

if [ -z "$PROMPT" ]; then
  echo "Usage: $0 \"<prompt>\" <output.png>" >&2
  exit 1
fi

TMPDIR="${TMPDIR:-/tmp}"
REQ_FILE="$TMPDIR/gemini-req-$$.json"
RESP_FILE="$TMPDIR/gemini-resp-$$.json"

# Build request JSON (use python3 to properly escape the prompt)
python3 -c "
import json, sys
req = {
    'contents': [{'parts': [{'text': 'Generate an image: ' + sys.argv[1]}]}],
    'generationConfig': {'responseModalities': ['TEXT', 'IMAGE']}
}
with open(sys.argv[2], 'w') as f:
    json.dump(req, f)
" "$PROMPT" "$REQ_FILE"

echo "Generating image with Gemini ($MODEL)..." >&2

# Call API, write response directly to file
HTTP_CODE=$(curl -s -w "%{http_code}" -X POST "$ENDPOINT" \
  -H "Content-Type: application/json" \
  -d "@$REQ_FILE" \
  -o "$RESP_FILE")

rm -f "$REQ_FILE"

if [ "$HTTP_CODE" != "200" ]; then
  echo "HTTP $HTTP_CODE error:" >&2
  python3 -c "
import json, sys
with open(sys.argv[1]) as f:
    data = json.load(f)
msg = data.get('error', {}).get('message', 'Unknown error')
print(msg, file=sys.stderr)
" "$RESP_FILE" 2>&1
  rm -f "$RESP_FILE"
  exit 1
fi

# Extract image from response file
python3 -c "
import json, base64, sys, os

with open(sys.argv[1]) as f:
    data = json.load(f)

candidates = data.get('candidates', [])
if not candidates:
    print('No candidates in response', file=sys.stderr)
    sys.exit(1)

parts = candidates[0].get('content', {}).get('parts', [])
for part in parts:
    if 'inlineData' in part:
        img_data = part['inlineData']['data']
        mime = part['inlineData'].get('mimeType', 'image/png')
        raw = base64.b64decode(img_data)
        output = sys.argv[2]
        with open(output, 'wb') as f:
            f.write(raw)
        size_kb = len(raw) // 1024
        print(f'✓ Image saved: {output} ({size_kb} KB, {mime})', file=sys.stderr)
        # Print output path to stdout for scripting
        print(output)
        sys.exit(0)

# No image found
for part in parts:
    if 'text' in part:
        print(f'Text response (no image): {part[\"text\"][:300]}', file=sys.stderr)

print('No image data in response', file=sys.stderr)
sys.exit(1)
" "$RESP_FILE" "$OUTPUT"

EXIT_CODE=$?
rm -f "$RESP_FILE"

if [ $EXIT_CODE -ne 0 ]; then
  exit $EXIT_CODE
fi

# ── Auto-convert to optimised JPEG if output is PNG and > 70KB ──
FILE_SIZE=$(stat -f%z "$OUTPUT" 2>/dev/null || stat -c%s "$OUTPUT" 2>/dev/null || echo 0)
MAX_BYTES=$((70 * 1024))

if [ "$FILE_SIZE" -gt "$MAX_BYTES" ]; then
  JPG_OUTPUT="${OUTPUT%.png}.jpg"
  if [ "$JPG_OUTPUT" = "$OUTPUT" ]; then
    JPG_OUTPUT="${OUTPUT}.jpg"
  fi
  # Convert PNG→JPEG with quality 82 (good balance of quality/size)
  if command -v sips >/dev/null 2>&1; then
    sips -s format jpeg -s formatOptions 82 "$OUTPUT" --out "$JPG_OUTPUT" >/dev/null 2>&1
  elif command -v convert >/dev/null 2>&1; then
    convert "$OUTPUT" -quality 82 "$JPG_OUTPUT" 2>/dev/null
  else
    echo "⚠ Image is $(( FILE_SIZE / 1024 ))KB but no converter available (install ImageMagick)" >&2
    exit 0
  fi

  if [ -f "$JPG_OUTPUT" ]; then
    NEW_SIZE=$(stat -f%z "$JPG_OUTPUT" 2>/dev/null || stat -c%s "$JPG_OUTPUT" 2>/dev/null)
    NEW_KB=$((NEW_SIZE / 1024))
    OLD_KB=$((FILE_SIZE / 1024))
    rm -f "$OUTPUT"
    mv "$JPG_OUTPUT" "${OUTPUT%.png}.jpg"
    OUTPUT="${OUTPUT%.png}.jpg"
    echo "✓ Compressed: ${OLD_KB}KB → ${NEW_KB}KB (JPEG q82)" >&2
    echo "$OUTPUT"
  fi
fi
