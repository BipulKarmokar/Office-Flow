#!/bin/bash

# Plugin Name
PLUGIN_NAME="office-utilities"
MAIN_FILE="office-utilities.php"

# 1. Update Version in Main File
# Extract current version
CURRENT_VERSION=$(grep "Version:" $MAIN_FILE | head -1 | awk '{print $3}')
echo "Current Version: $CURRENT_VERSION"

# Increment version (Patch level)
IFS='.' read -r -a parts <<< "$CURRENT_VERSION"
major="${parts[0]}"
minor="${parts[1]}"
patch="${parts[2]}"
new_patch=$((patch + 1))
NEW_VERSION="$major.$minor.$new_patch"

echo "Updating to Version: $NEW_VERSION"

# Update version in file header and constant
# Use perl for in-place editing to handle cross-platform differences better than sed
perl -i -pe "s/Version:     $CURRENT_VERSION/Version:     $NEW_VERSION/" $MAIN_FILE
perl -i -pe "s/const VERSION = '$CURRENT_VERSION';/const VERSION = '$NEW_VERSION';/" $MAIN_FILE

# Build Directory
BUILD_DIR="build"

# Remove existing build directory
rm -rf $BUILD_DIR
rm -f "$PLUGIN_NAME.zip"

# Create build directory
mkdir -p "$BUILD_DIR/$PLUGIN_NAME"

# Copy files to build directory
# Excluding dev files like node_modules, resources, .git, etc.
rsync -av --progress . "$BUILD_DIR/$PLUGIN_NAME" \
    --exclude 'node_modules' \
    --exclude 'resources' \
    --exclude 'build' \
    --exclude '.git' \
    --exclude '.gitignore' \
    --exclude 'package.json' \
    --exclude 'package-lock.json' \
    --exclude 'vite.config.js' \
    --exclude 'postcss.config.js' \
    --exclude 'tailwind.config.js' \
    --exclude 'README.md' \
    --exclude '.DS_Store' \
    --exclude '*.zip' \
    --exclude '*.sh'

# Create Zip file
cd $BUILD_DIR
zip -r "../$PLUGIN_NAME.zip" "$PLUGIN_NAME"

# Cleanup
cd ..
rm -rf $BUILD_DIR

echo "Build Complete! Plugin zip created: $PLUGIN_NAME.zip"
