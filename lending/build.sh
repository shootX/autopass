#!/bin/sh
# Production files for FTP. No compiler.
set -eu
cd "$(dirname "$0")"
rm -rf dist
mkdir -p dist
cp index.html business.html styles.css responsive.css config.js site.js home.js business.js site.webmanifest dist/
cp -R content fonts img icons dist/
# The Android app for direct download (autopass.ge/autopass.apk). The file is copied to the server by hand and
# kept out of git; each build carries it over into dist/.
if [ -f autopass.apk ]; then cp autopass.apk dist/; fi
echo "Upload the contents of lending/dist/ to the site root."
