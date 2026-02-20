#!/bin/bash
# Build a clean WordPress.org submission zip for Client Document Manager.
# Usage: ./bin/build.sh
# Output: dist/document-manager-<version>.zip

set -e

PLUGIN_SLUG="document-manager"
PLUGIN_FILE="document-manager.php"
DIST_DIR="$(dirname "$0")/../dist"
SRC_DIR="$(dirname "$0")/.."

# Read version from plugin header
VERSION=$(grep -m1 "Version:" "$SRC_DIR/$PLUGIN_FILE" | awk '{print $NF}')
if [ -z "$VERSION" ]; then
    echo "Error: could not read version from $PLUGIN_FILE"
    exit 1
fi

ZIP_NAME="$PLUGIN_SLUG-$VERSION.zip"
ZIP_PATH="$DIST_DIR/$ZIP_NAME"

echo "Building $ZIP_NAME..."

# Prepare staging directory
STAGE_DIR=$(mktemp -d)
trap 'rm -rf "$STAGE_DIR"' EXIT

rsync -a \
    --exclude='.git' \
    --exclude='.github' \
    --exclude='.claude' \
    --exclude='.gitignore' \
    --exclude='.DS_Store' \
    --exclude='*.zip' \
    --exclude='README.md' \
    --exclude='bin/' \
    --exclude='dist/' \
    "$SRC_DIR/" "$STAGE_DIR/$PLUGIN_SLUG/"

mkdir -p "$DIST_DIR"
(cd "$STAGE_DIR" && zip -r "$ZIP_PATH" "$PLUGIN_SLUG/")

echo "Done: $ZIP_PATH"
echo "Size: $(du -sh "$ZIP_PATH" | cut -f1)"
