#!/usr/bin/env bash
# Build an installable WordPress plugin zip for Order Machine (CI / Unix).
# Usage: bash bin/build-plugin-zip.sh [version]
# Output: dist/orderMachine-<version>.zip

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

PLUGIN_SLUG="orderMachine"
PLUGIN_FILE="orderMachine.php"
VERSION="${1:-}"

# Only ship runtime plugin files.
INCLUDE_PATHS=(
  orderMachine.php
  uninstall.php
  admin
  includes
)

if [[ ! -f "$PLUGIN_FILE" ]]; then
  echo "Main plugin file not found: $PLUGIN_FILE" >&2
  exit 1
fi

header_version="$(sed -nE 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*(\S+).*/\1/p' "$PLUGIN_FILE" | head -n1)"
const_version="$(sed -nE "s/.*define\([[:space:]]*'SOM_VERSION'[[:space:]]*,[[:space:]]*'([^']+)'[[:space:]]*\).*/\1/p" "$PLUGIN_FILE" | head -n1)"

if [[ -z "$VERSION" ]]; then
  VERSION="$header_version"
fi

if [[ -z "$VERSION" ]]; then
  echo "Could not determine version" >&2
  exit 1
fi

if [[ -n "$header_version" && -n "$const_version" && "$header_version" != "$const_version" ]]; then
  echo "Version mismatch: header=$header_version SOM_VERSION=$const_version" >&2
  exit 1
fi

if [[ -n "$header_version" && "$header_version" != "$VERSION" ]]; then
  echo "Requested version $VERSION does not match plugin header $header_version" >&2
  exit 1
fi

if [[ -n "$const_version" && "$const_version" != "$VERSION" ]]; then
  echo "Requested version $VERSION does not match SOM_VERSION $const_version" >&2
  exit 1
fi

for path in "${INCLUDE_PATHS[@]}"; do
  if [[ ! -e "$path" ]]; then
    echo "Required path missing: $path" >&2
    exit 1
  fi
done

OUT_DIR="${OUT_DIR:-$ROOT/dist}"
mkdir -p "$OUT_DIR"
ZIP_PATH="$OUT_DIR/${PLUGIN_SLUG}-${VERSION}.zip"
rm -f "$ZIP_PATH"

STAGE="$(mktemp -d)"
cleanup() { rm -rf "$STAGE"; }
trap cleanup EXIT

mkdir -p "$STAGE/$PLUGIN_SLUG"
for path in "${INCLUDE_PATHS[@]}"; do
  cp -a "$path" "$STAGE/$PLUGIN_SLUG/"
done

(
  cd "$STAGE"
  if command -v zip >/dev/null 2>&1; then
    zip -rq "$ZIP_PATH" "$PLUGIN_SLUG"
  else
    python3 - <<PY
import shutil
from pathlib import Path
stage = Path(r"$STAGE")
zip_base = Path(r"${ZIP_PATH%.zip}")
shutil.make_archive(str(zip_base), "zip", root_dir=stage, base_dir="$PLUGIN_SLUG")
PY
  fi
)

echo "Created $ZIP_PATH"
