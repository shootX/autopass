#!/bin/sh
# Production files for FTP. No compiler.
set -eu
cd "$(dirname "$0")"
rm -rf dist
mkdir -p dist
cp index.html business.html styles.css responsive.css config.js logged-in-redirect.js site.js home.js business.js site.webmanifest dist/
cp -R content fonts img icons dist/
echo "Upload the contents of lending/dist/ to the site root."
