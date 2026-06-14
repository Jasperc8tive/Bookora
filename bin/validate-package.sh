#!/usr/bin/env bash
#
# Validate the distributable plugin ZIP produced by bin/build-release.sh.
#
# Asserts the package contains required runtime files and excludes all
# development artifacts. Exits non-zero on any violation so CI can gate on it.
#
# Usage:  bin/validate-package.sh [path/to/bookora.zip]
set -euo pipefail

ZIP=${1:-dist/bookora.zip}

if [ ! -f "$ZIP" ]; then
	echo "::error::Package not found: $ZIP"
	exit 1
fi

LIST=$(unzip -Z1 "$ZIP")
fail=0

require() {
	if ! grep -qE "^bookora/$1" <<<"$LIST"; then
		echo "::error::Missing required entry: $1"
		fail=1
	fi
}

forbid() {
	if grep -qE "^bookora/$1" <<<"$LIST"; then
		echo "::error::Forbidden entry present in package: $1"
		fail=1
	fi
}

# Required runtime files.
require "bookora.php"
require "uninstall.php"
require "readme.txt"
require "app/"
require "vendor/autoload.php"
require "assets/build/"

# Development artifacts that must never ship.
forbid "tests/"
forbid "node_modules/"
forbid "assets/src/"
forbid ".git/"
forbid "docs/"
forbid "phpunit.xml.dist"
forbid "phpcs.xml.dist"
forbid "phpstan.neon.dist"
forbid ".github/"

# No dev dependencies leaked into the production autoloader.
if unzip -p "$ZIP" "bookora/vendor/composer/installed.json" 2>/dev/null | grep -q "phpunit/phpunit"; then
	echo "::error::Dev dependency phpunit leaked into the package vendor/."
	fail=1
fi

if [ "$fail" -ne 0 ]; then
	echo "Package validation FAILED."
	exit 1
fi

echo "Package validation PASSED: $ZIP"
unzip -l "$ZIP" | tail -1
