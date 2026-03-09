#!/bin/zsh
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
SOURCE_DIR="$ROOT_DIR/wp-content/mu-plugins"
BUILD_ROOT="$ROOT_DIR/build/local"
BUILD_MU_DIR="$BUILD_ROOT/wp-content/mu-plugins"
MANIFEST_FILE="$BUILD_ROOT/manifest.txt"
LINT_ONLY=0

if [[ "${1:-}" == "--lint-only" ]]; then
  LINT_ONLY=1
fi

if [[ ! -d "$SOURCE_DIR" ]]; then
  echo "Source directory not found: $SOURCE_DIR" >&2
  exit 1
fi

if ! command -v php >/dev/null 2>&1; then
  echo "Required command not found: php" >&2
  exit 1
fi

if ! command -v rsync >/dev/null 2>&1; then
  echo "Required command not found: rsync" >&2
  exit 1
fi

echo "==> Lint PHP files"
php_files=("${(@f)$(find "$SOURCE_DIR" -type f -name '*.php' | sort)}")

if [[ ${#php_files[@]} -eq 0 ]]; then
  echo "No PHP files found under $SOURCE_DIR" >&2
  exit 1
fi

for file in "${php_files[@]}"; do
  php -l "$file" >/dev/null
  echo "OK  $file"
done

if [[ "$LINT_ONLY" -eq 1 ]]; then
  echo "Lint-only mode complete."
  exit 0
fi

echo "==> Rebuild local artifact"
rm -rf "$BUILD_ROOT"
mkdir -p "$BUILD_MU_DIR"

rsync -a --delete "$SOURCE_DIR/" "$BUILD_MU_DIR/"

{
  echo "build_time=$(date '+%Y-%m-%d %H:%M:%S %z')"
  echo "source_dir=$SOURCE_DIR"
  echo "build_mu_dir=$BUILD_MU_DIR"
  echo "file_count=$(find "$BUILD_MU_DIR" -type f | wc -l | tr -d ' ')"
} > "$MANIFEST_FILE"

echo "Build complete: $BUILD_ROOT"
echo "Manifest: $MANIFEST_FILE"
