#!/bin/bash

# Build script for CheckoutKeys WordPress Plugin
# Usage: ./build-release.sh 1.0.0

set -e

VERSION=${1:-1.0.0}
PLUGIN_NAME="checkoutkeys"
BUILD_DIR="build"
RELEASE_DIR="$BUILD_DIR/$PLUGIN_NAME"

echo "Building $PLUGIN_NAME v$VERSION"
echo ""

# Clean previous build
echo "Cleaning previous build..."
rm -rf "$BUILD_DIR"
mkdir -p "$RELEASE_DIR"

# Copy plugin files
echo "Copying plugin files..."
rsync -av --progress \
  --exclude="$BUILD_DIR" \
  --exclude=".git" \
  --exclude=".gitignore" \
  --exclude="node_modules" \
  --exclude=".DS_Store" \
  --exclude="*.md" \
  --exclude="build-release.sh" \
  --exclude="test-*" \
  ./ "$RELEASE_DIR/"

# Update version in files
echo "Updating version numbers..."
if [[ "$OSTYPE" == "darwin"* ]]; then
  # macOS
  sed -i '' "s/Version: .*/Version: $VERSION/" "$RELEASE_DIR/checkoutkeys.php"
  sed -i '' "s/Stable tag: .*/Stable tag: $VERSION/" "$RELEASE_DIR/readme.txt"
else
  # Linux
  sed -i "s/Version: .*/Version: $VERSION/" "$RELEASE_DIR/checkoutkeys.php"
  sed -i "s/Stable tag: .*/Stable tag: $VERSION/" "$RELEASE_DIR/readme.txt"
fi

# Create ZIP
echo "Creating ZIP archive..."
cd "$BUILD_DIR"
zip -r "../$PLUGIN_NAME-$VERSION.zip" "$PLUGIN_NAME" > /dev/null
cd ..

# Calculate size
SIZE=$(du -h "$PLUGIN_NAME-$VERSION.zip" | cut -f1)

echo ""
echo "Build complete!"
echo ""
echo "Package: $PLUGIN_NAME-$VERSION.zip"
echo "Size: $SIZE"
echo ""
echo "Next steps:"
echo "1. Test the ZIP by uploading to WordPress"
echo "2. Create GitHub release: git tag -a v$VERSION -m 'Version $VERSION'"
echo "3. Push tag: git push origin v$VERSION"
echo "4. Upload ZIP to GitHub releases"
echo ""
