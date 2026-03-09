#!/bin/zsh
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
BUILD_ROOT="$ROOT_DIR/build/local"
BUILD_MU_DIR="$BUILD_ROOT/wp-content/mu-plugins"
ENV_FILE="$ROOT_DIR/.env"

if [[ ! -d "$BUILD_MU_DIR" ]]; then
  echo "Build artifact not found. Run ./scripts/build-local.sh first." >&2
  exit 1
fi

if ! command -v rsync >/dev/null 2>&1; then
  echo "Required command not found: rsync" >&2
  exit 1
fi

if [[ -f "$ENV_FILE" ]]; then
  set -a
  source "$ENV_FILE"
  set +a
fi

TARGET_DIR="${LOCAL_MU_PLUGIN_DIR:-}"
if [[ -z "$TARGET_DIR" && -n "${LOCAL_WP_ROOT:-}" ]]; then
  TARGET_DIR="$LOCAL_WP_ROOT/wp-content/mu-plugins"
fi

if [[ -z "$TARGET_DIR" ]]; then
  echo "Set LOCAL_WP_ROOT or LOCAL_MU_PLUGIN_DIR in .env" >&2
  exit 1
fi

if [[ ! -d "$TARGET_DIR" ]]; then
  echo "Target mu-plugins directory does not exist: $TARGET_DIR" >&2
  exit 1
fi

case "$TARGET_DIR" in
  *"/wp-content/mu-plugins") ;;
  *)
    echo "Refusing to deploy: target does not look like a mu-plugins directory: $TARGET_DIR" >&2
    exit 1
    ;;
esac

BACKUP_BASE="${LOCAL_DEPLOY_BACKUP_DIR:-$ROOT_DIR/.deploy-backups}"
STAMP="$(date '+%Y%m%d-%H%M%S')"
BACKUP_DIR="$BACKUP_BASE/$STAMP"

mkdir -p "$BACKUP_DIR"

echo "==> Backup current local mu-plugins"
rsync -a "$TARGET_DIR/" "$BACKUP_DIR/"

echo "==> Deploy built artifact to local WordPress"
rsync -a --delete "$BUILD_MU_DIR/" "$TARGET_DIR/"

echo "Deploy complete."
echo "Target: $TARGET_DIR"
echo "Backup: $BACKUP_DIR"
