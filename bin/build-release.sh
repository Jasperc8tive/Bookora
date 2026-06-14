#!/usr/bin/env bash
#
# Build a distributable Bookora plugin ZIP.
#
# Produces dist/bookora.zip containing only runtime files: the built JS/CSS
# bundle, the production (no-dev) Composer autoloader + vendor, PHP source,
# templates, languages, and the readme — everything in .distignore is excluded.
#
# Usage:  bin/build-release.sh
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="bookora"
DIST="${ROOT}/dist"
STAGE="${DIST}/${SLUG}"

cd "${ROOT}"

VERSION="$(grep -oE "BOOKORA_VERSION', '[0-9.]+'" bookora.php | grep -oE '[0-9.]+')"
echo "==> Building ${SLUG} ${VERSION}"

# 1. Front-end production bundle.
echo "==> npm ci && npm run build"
npm ci
npm run build

# 2. Production PHP dependencies (no dev tooling in the package).
echo "==> composer install --no-dev"
composer install --no-dev --optimize-autoloader --classmap-authoritative

# 3. Stage the runtime files.
echo "==> Staging files"
rm -rf "${STAGE}"
mkdir -p "${STAGE}"

# Explicit runtime allowlist — deterministic, never ships dev files.
cp    bookora.php uninstall.php readme.txt README.md composer.json "${STAGE}/"
cp -r app           "${STAGE}/app"
cp -r vendor        "${STAGE}/vendor"
cp -r assets/build  "${STAGE}/assets/build"
[ -d templates ] && cp -r templates "${STAGE}/templates" || true
[ -d languages ] && cp -r languages "${STAGE}/languages" || true

# 4. Zip it.
echo "==> Zipping"
cd "${DIST}"
rm -f "${SLUG}.zip"
zip -rq "${SLUG}.zip" "${SLUG}"

echo "==> Done: dist/${SLUG}.zip ($(du -h "${SLUG}.zip" | cut -f1))"
echo "    Reminder: run 'composer install' to restore dev tooling after packaging."
