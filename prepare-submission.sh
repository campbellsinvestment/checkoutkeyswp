#!/bin/bash
# WordPress Plugin Submission Helper
# CheckoutKeys License Manager

set -e  # Exit on error

PLUGIN_NAME="checkoutkeys"
VERSION="1.0.0"
RELEASE_DIR="release"

echo "🚀 WordPress Plugin Submission Helper"
echo "======================================"
echo ""

# Step 1: Check if assets exist
echo "📋 Step 1: Checking assets..."
ASSETS_DIR=".wordpress-org"

if [ ! -f "$ASSETS_DIR/icon-256x256.png" ]; then
    echo "❌ Missing: icon-256x256.png"
    echo "   Please add your icon to .wordpress-org/ folder"
    exit 1
fi

if [ ! -f "$ASSETS_DIR/icon-128x128.png" ]; then
    echo "❌ Missing: icon-128x128.png"
    echo "   Please add your icon to .wordpress-org/ folder"
    exit 1
fi

if [ ! -f "$ASSETS_DIR/screenshot-1.png" ]; then
    echo "❌ Missing: screenshot-1.png"
    echo "   Please add your screenshots to .wordpress-org/ folder"
    exit 1
fi

if [ ! -f "$ASSETS_DIR/screenshot-2.png" ]; then
    echo "❌ Missing: screenshot-2.png"
    echo "   Please add your screenshots to .wordpress-org/ folder"
    exit 1
fi

echo "✅ All required assets found!"
echo ""

# Step 2: Verify readme.txt
echo "📋 Step 2: Checking readme.txt..."
if [ ! -f "readme.txt" ]; then
    echo "❌ readme.txt not found!"
    exit 1
fi

# Check for required sections
if ! grep -q "=== CheckoutKeys License Manager ===" readme.txt; then
    echo "⚠️  Plugin name might be incorrect in readme.txt"
fi

echo "✅ readme.txt looks good!"
echo ""

# Step 3: Create release directory
echo "📋 Step 3: Creating release package..."
rm -rf "$RELEASE_DIR"
mkdir -p "$RELEASE_DIR/$PLUGIN_NAME"

# Copy files
echo "   Copying plugin files..."
rsync -av --progress \
    --exclude='.git' \
    --exclude='.gitignore' \
    --exclude='release' \
    --exclude='node_modules' \
    --exclude='.DS_Store' \
    --exclude='WORDPRESS_SUBMISSION.md' \
    --exclude='SUBMISSION_STEPS.md' \
    --exclude='build-release.sh' \
    --exclude='prepare-submission.sh' \
    --exclude='.wordpress-org/ASSETS_NEEDED.md' \
    ./ "$RELEASE_DIR/$PLUGIN_NAME/"

# Step 4: Create ZIP
echo "   Creating ZIP file..."
cd "$RELEASE_DIR"
zip -r "$PLUGIN_NAME-$VERSION.zip" "$PLUGIN_NAME/" -q

echo "✅ Release package created!"
echo ""

# Step 5: Verify ZIP contents
echo "📋 Step 4: Verifying ZIP contents..."
echo ""
unzip -l "$PLUGIN_NAME-$VERSION.zip" | head -20
echo "..."
echo ""

# Step 6: Check file size
FILE_SIZE=$(du -h "$PLUGIN_NAME-$VERSION.zip" | cut -f1)
echo "📦 Package size: $FILE_SIZE"
echo ""

# Step 7: Success message
echo "✅ SUCCESS! Your plugin is ready for submission!"
echo ""
echo "📁 Release package location:"
echo "   $(pwd)/$PLUGIN_NAME-$VERSION.zip"
echo ""
echo "📋 Next Steps:"
echo "   1. Test the plugin on a fresh WordPress install"
echo "   2. Go to https://wordpress.org/plugins/developers/add/"
echo "   3. Upload $PLUGIN_NAME-$VERSION.zip"
echo "   4. Fill out the submission form"
echo "   5. Wait for review (2-14 days)"
echo ""
echo "📚 For detailed instructions, see SUBMISSION_STEPS.md"
echo ""
