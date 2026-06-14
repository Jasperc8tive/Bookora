#!/usr/bin/env bash
#
# Install the WordPress test library + a throwaway WordPress install for PHPUnit.
#
# Usage:
#   bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-db-create]
#
# Sets up:
#   - WordPress core at $WP_CORE_DIR        (default /tmp/wordpress/)
#   - the test library at $WP_TESTS_DIR     (default /tmp/wordpress-tests-lib/)
#
# Export WP_TESTS_DIR to point the plugin bootstrap at the test library.
#
# Adapted from the canonical wp-cli / WP_UnitTestCase scaffold.
set -euo pipefail

if [ $# -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-db-create]"
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}
SKIP_DB_CREATE=${6-false}

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo "$TMPDIR" | sed -e "s/\/$//")
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress}

download() {
	if command -v curl >/dev/null 2>&1; then
		curl -s "$1" >"$2"
	elif command -v wget >/dev/null 2>&1; then
		wget -nv -O "$2" "$1"
	else
		echo "Neither curl nor wget is available." >&2
		exit 1
	fi
}

# Resolve the WordPress version to install.
if [[ "$WP_VERSION" == "latest" ]]; then
	download http://api.wordpress.org/core/version-check/1.7/ /tmp/wp-latest.json
	LATEST_VERSION=$(grep -o '"version":"[^"]*' /tmp/wp-latest.json | sed 's/"version":"//' | head -1)
	if [[ -z "$LATEST_VERSION" ]]; then
		echo "Could not resolve the latest WordPress version." >&2
		exit 1
	fi
	WP_VERSION_TAG=$LATEST_VERSION
else
	WP_VERSION_TAG=$WP_VERSION
fi

install_wp() {
	if [ -d "$WP_CORE_DIR" ]; then
		return
	fi
	mkdir -p "$WP_CORE_DIR"
	local archive="$TMPDIR/wordpress-${WP_VERSION_TAG}.tar.gz"
	download "https://wordpress.org/wordpress-${WP_VERSION_TAG}.tar.gz" "$archive"
	tar --strip-components=1 -zxmf "$archive" -C "$WP_CORE_DIR"
	download https://raw.githubusercontent.com/markoheijnen/wp-mysqli/master/db.php "$WP_CORE_DIR/wp-content/db.php"
}

install_test_suite() {
	# Portable in-place sed.
	if [[ $(uname -s) == 'Darwin' ]]; then
		local ioption='-i .bak'
	else
		local ioption='-i'
	fi

	if [ ! -d "$WP_TESTS_DIR" ]; then
		mkdir -p "$WP_TESTS_DIR"
		svn export --quiet "https://develop.svn.wordpress.org/tags/${WP_VERSION_TAG}/tests/phpunit/includes/" "$WP_TESTS_DIR/includes" 2>/dev/null \
			|| svn export --quiet "https://develop.svn.wordpress.org/trunk/tests/phpunit/includes/" "$WP_TESTS_DIR/includes"
		svn export --quiet "https://develop.svn.wordpress.org/tags/${WP_VERSION_TAG}/tests/phpunit/data/" "$WP_TESTS_DIR/data" 2>/dev/null \
			|| svn export --quiet "https://develop.svn.wordpress.org/trunk/tests/phpunit/data/" "$WP_TESTS_DIR/data"
	fi

	if [ ! -f "$WP_TESTS_DIR/wp-tests-config.php" ]; then
		download "https://develop.svn.wordpress.org/tags/${WP_VERSION_TAG}/wp-tests-config-sample.php" "$WP_TESTS_DIR/wp-tests-config.php" \
			|| download "https://develop.svn.wordpress.org/trunk/wp-tests-config-sample.php" "$WP_TESTS_DIR/wp-tests-config.php"
		local cfg="$WP_TESTS_DIR/wp-tests-config.php"
		# shellcheck disable=SC2086
		sed $ioption "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR/':" "$cfg"
		# Replace each DB define deterministically (independent of the sample's
		# placeholder text), so DB_HOST is never left as the default 'localhost'
		# (which makes mysqli try a non-existent unix socket).
		# shellcheck disable=SC2086
		sed $ioption "s/define( *'DB_NAME'.*/define( 'DB_NAME', '${DB_NAME}' );/" "$cfg"
		# shellcheck disable=SC2086
		sed $ioption "s/define( *'DB_USER'.*/define( 'DB_USER', '${DB_USER}' );/" "$cfg"
		# shellcheck disable=SC2086
		sed $ioption "s/define( *'DB_PASSWORD'.*/define( 'DB_PASSWORD', '${DB_PASS}' );/" "$cfg"
		# shellcheck disable=SC2086
		sed $ioption "s/define( *'DB_HOST'.*/define( 'DB_HOST', '${DB_HOST}' );/" "$cfg"
	fi
}

install_db() {
	if [ "$SKIP_DB_CREATE" = "true" ]; then
		return
	fi
	local host=$DB_HOST
	local port=''
	if [[ "$DB_HOST" == *":"* ]]; then
		host=$(echo "$DB_HOST" | cut -d: -f1)
		port=$(echo "$DB_HOST" | cut -d: -f2)
	fi
	local mysql_args=("--user=$DB_USER" "--password=$DB_PASS" "--host=$host")
	if [ -n "$port" ]; then
		mysql_args+=("--port=$port" "--protocol=tcp")
	fi
	mysqladmin create "$DB_NAME" "${mysql_args[@]}" 2>/dev/null || true
}

install_wp
install_test_suite
install_db

echo "WordPress ${WP_VERSION_TAG} test environment ready."
echo "WP_TESTS_DIR=$WP_TESTS_DIR"
echo "WP_CORE_DIR=$WP_CORE_DIR"
