#!/bin/sh
# Production files for FTP. No compiler.
set -eu
cd "$(dirname "$0")"
rm -rf dist
mkdir -p dist
cp index.html styles.css config.js site.js site.webmanifest dist/
cp -R fonts img icons dist/
echo "Upload the contents of lending/dist/ to the site root."
