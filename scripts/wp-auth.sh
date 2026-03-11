#!/bin/zsh
# wp-auth.sh — Login to rtadv.com WordPress and export COOKIE_FILE + WP_NONCE
# Usage: eval $(./scripts/wp-auth.sh)
# After: use $COOKIE_FILE and $WP_NONCE in curl calls
# Cleanup: rm -f "$COOKIE_FILE"

set -euo pipefail

WP_LOGIN_URL="https://www.rtadv.com/rtadvlogin/"
WP_USER="${WP_USER:?ERROR: WP_USER env var required}"
WP_PASS="${WP_PASS:?ERROR: WP_PASS env var required}"

COOKIE_FILE=$(mktemp)

# Login
curl -s -c "$COOKIE_FILE" -b "$COOKIE_FILE" -L \
  -X POST "$WP_LOGIN_URL" \
  --data-urlencode "log=$WP_USER" \
  --data-urlencode "pwd=$WP_PASS" \
  --data-urlencode "wp-submit=Log In" \
  --data-urlencode "redirect_to=/wp-admin/" \
  --data-urlencode "testcookie=1" \
  -H "Cookie: wordpress_test_cookie=WP+Cookie+check" \
  -o /dev/null 2>/dev/null

# Verify login
if ! grep -q "wordpress_logged_in" "$COOKIE_FILE" 2>/dev/null; then
  echo "echo 'ERROR: WordPress login failed'" >&2
  rm -f "$COOKIE_FILE"
  exit 1
fi

# Get REST API nonce
WP_NONCE=$(curl -s -b "$COOKIE_FILE" \
  "https://www.rtadv.com/wp-admin/admin-ajax.php?action=rest-nonce")

if [ -z "$WP_NONCE" ] || [ ${#WP_NONCE} -lt 5 ]; then
  echo "echo 'ERROR: Failed to get REST nonce'" >&2
  rm -f "$COOKIE_FILE"
  exit 1
fi

# Export variables for eval
echo "export COOKIE_FILE='$COOKIE_FILE'"
echo "export WP_NONCE='$WP_NONCE'"
