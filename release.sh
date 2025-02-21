#!/bin/bash

VERSION=$1

if [ -z "$VERSION" ]; then
    echo "Please provide a version number (e.g. ./release.sh 1.2.3)"
    exit 1
fi

# Update version in main plugin file
sed -i "s/Version: .*$/Version: $VERSION/" wp-custom-login-manager.php
sed -i "s/define( 'WPCLM_VERSION'.*$/define( 'WPCLM_VERSION', '$VERSION' );/" wp-custom-login-manager.php

# Update readme.txt
sed -i "s/Stable tag: .*$/Stable tag: $VERSION/" readme.txt

# Commit changes
git add .
git commit -m "Release version $VERSION"

# Create and push tag
git tag -a "v$VERSION" -m "Version $VERSION"
git push && git push --tags