#!/usr/bin/env bash
#
# Install the WordPress PHPUnit test library, a WordPress core checkout and
# WooCommerce so the integration testsuite can run.
#
# Usage: bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [wc-version]
#
#   wp-version  "latest" (default) or an exact release such as "6.2" / "7.1.2".
#   wc-version  "latest" (default) or an exact WooCommerce release.
#
# Unlike the classic wp-cli scaffold script this needs no svn: the test
# library is pulled from the wordpress-develop GitHub tag tarball.
#
# Then run: WP_TESTS_DIR=$WP_TESTS_DIR vendor/bin/phpunit --testsuite integration

set -euo pipefail

if [ $# -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [wc-version]"
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}
WC_VERSION=${6-latest}

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo "$TMPDIR" | sed -e "s/\/$//")
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress}

# Resolve "latest" to a concrete version so core and the test library match.
if [ "$WP_VERSION" = "latest" ]; then
	WP_VERSION=$(curl -fsSL https://api.wordpress.org/core/version-check/1.7/ | php -r '$j = json_decode( stream_get_contents( STDIN ), true ); echo $j["offers"][0]["current"];')
fi

# wordpress.org names x.y.0 releases "x.y"; wordpress-develop tags them "x.y.0".
DEV_TAG=$WP_VERSION
if [[ "$DEV_TAG" =~ ^[0-9]+\.[0-9]+$ ]]; then
	DEV_TAG="$DEV_TAG.0"
fi

echo "Installing WordPress $WP_VERSION into $WP_CORE_DIR"
if [ ! -f "$WP_CORE_DIR/wp-settings.php" ]; then
	mkdir -p "$WP_CORE_DIR"
	curl -fsSL "https://wordpress.org/wordpress-$WP_VERSION.tar.gz" | tar -xz --strip-components=1 -C "$WP_CORE_DIR"
fi

echo "Installing WordPress test library ($WP_VERSION) into $WP_TESTS_DIR"
if [ ! -f "$WP_TESTS_DIR/includes/bootstrap.php" ]; then
	mkdir -p "$WP_TESTS_DIR"
	curl -fsSL "https://github.com/WordPress/wordpress-develop/archive/refs/tags/$DEV_TAG.tar.gz" \
		| tar -xz --strip-components=1 -C "$WP_TESTS_DIR" \
			--wildcards '*/tests/phpunit/includes' '*/tests/phpunit/data' '*/wp-tests-config-sample.php'
	mv "$WP_TESTS_DIR/tests/phpunit/includes" "$WP_TESTS_DIR/includes"
	mv "$WP_TESTS_DIR/tests/phpunit/data" "$WP_TESTS_DIR/data"
	rm -rf "$WP_TESTS_DIR/tests"
fi

if [ ! -f "$WP_TESTS_DIR/wp-tests-config.php" ]; then
	sed -e "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR/':" \
		-e "s:__DIR__ . '/src/':'$WP_CORE_DIR/':" \
		-e "s/youremptytestdbnamehere/$DB_NAME/" \
		-e "s/yourusernamehere/$DB_USER/" \
		-e "s/yourpasswordhere/$DB_PASS/" \
		-e "s|localhost|$DB_HOST|" \
		"$WP_TESTS_DIR/wp-tests-config-sample.php" > "$WP_TESTS_DIR/wp-tests-config.php"
fi

echo "Installing WooCommerce $WC_VERSION"
if [ ! -f "$WP_CORE_DIR/wp-content/plugins/woocommerce/woocommerce.php" ]; then
	if [ "$WC_VERSION" = "latest" ]; then
		WC_ZIP=https://downloads.wordpress.org/plugin/woocommerce.zip
	else
		WC_ZIP="https://downloads.wordpress.org/plugin/woocommerce.$WC_VERSION.zip"
	fi
	curl -fsSL "$WC_ZIP" -o "$TMPDIR/woocommerce.zip"
	unzip -q -o "$TMPDIR/woocommerce.zip" -d "$WP_CORE_DIR/wp-content/plugins/"
	rm -f "$TMPDIR/woocommerce.zip"
fi

echo "Done. WP_TESTS_DIR=$WP_TESTS_DIR"
