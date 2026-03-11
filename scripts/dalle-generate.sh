#!/bin/zsh
# dalle-generate.sh — Generate image via DALL-E 3 and save to file
# Usage: ./scripts/dalle-generate.sh <prompt> <output_file> [size]
# Sizes: 1792x1024 (landscape, default), 1024x1792 (portrait), 1024x1024 (square)
# Output: PNG file at <output_file>

set -euo pipefail

PROMPT="${1:?Usage: dalle-generate.sh <prompt> <output_file> [size]}"
export OUTPUT_FILE="${2:?Missing output file path}"
SIZE="${3:-1792x1024}"

OPENAI_KEY="${OPENAI_API_KEY:?ERROR: OPENAI_API_KEY env var required}"

PAYLOAD=$(python3 -c "
import json, sys
print(json.dumps({
    'model': 'dall-e-3',
    'prompt': sys.argv[1],
    'n': 1,
    'size': sys.argv[2],
    'quality': 'standard',
    'response_format': 'url'
}, ensure_ascii=False))
" "$PROMPT" "$SIZE")

echo "Generating image..." >&2

RESULT=$(curl -s "https://api.openai.com/v1/images/generations" \
  -H "Authorization: Bearer $OPENAI_KEY" \
  -H "Content-Type: application/json" \
  -d "$PAYLOAD")

# Extract URL and download
echo "$RESULT" | python3 -c "
import sys, json, urllib.request, os
data = json.load(sys.stdin)
output = os.environ['OUTPUT_FILE']
if 'data' in data and len(data['data']) > 0:
    url = data['data'][0]['url']
    urllib.request.urlretrieve(url, output)
    revised = data['data'][0].get('revised_prompt', '')
    print(f'OK: downloaded to {output}')
    if revised:
        print(f'REVISED_PROMPT: {revised[:200]}')
else:
    error = data.get('error', {}).get('message', json.dumps(data))
    print(f'ERROR: {error}', file=sys.stderr)
    sys.exit(1)
"
