#!/usr/bin/env bash
# =============================================================================
# install-wp-tests.sh
# Downloads WordPress core and the WP test suite into /tmp so PHPUnit
# integration tests can bootstrap WordPress without a full install.
#
# Usage:
#   bash bin/install-wp-tests.sh <db-name> <db-user> <db-password> [db-host] [wp-version]
#
# Example:
#   bash bin/install-wp-tests.sh wordpress_tests wordpress wordpress 127.0.0.1 latest
# =============================================================================

set -euo pipefail

DB_NAME="${1:-wordpress_tests}"
DB_USER="${2:-wordpress}"
DB_PASS="${3:-wordpress}"
DB_HOST="${4:-127.0.0.1}"
WP_VERSION="${5:-latest}"

WP_TESTS_DIR="${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}"
WP_CORE_DIR="${WP_CORE_DIR:-/tmp/wordpress}"

install_wp() {
    if [ -d "$WP_CORE_DIR" ]; then
        echo "WordPress already installed at $WP_CORE_DIR"
        return
    fi

    if [ "$WP_VERSION" = "latest" ]; then
        local archive_name="latest.tar.gz"
    else
        local archive_name="wordpress-${WP_VERSION}.tar.gz"
    fi

    mkdir -p "$WP_CORE_DIR"
    curl -s "https://wordpress.org/${archive_name}" | tar --strip-components=1 -zxmf - -C "$WP_CORE_DIR"
}

install_test_suite() {
    if [ -d "$WP_TESTS_DIR" ]; then
        echo "Test suite already installed at $WP_TESTS_DIR"
        return
    fi

    mkdir -p "$WP_TESTS_DIR"

    local tag
    if [ "$WP_VERSION" = "latest" ]; then
        tag="trunk"
    else
        tag="tags/${WP_VERSION}"
    fi

    svn export --quiet --ignore-externals \
        "https://develop.svn.wordpress.org/${tag}/tests/phpunit/includes/" \
        "${WP_TESTS_DIR}/includes"

    svn export --quiet --ignore-externals \
        "https://develop.svn.wordpress.org/${tag}/tests/phpunit/data/" \
        "${WP_TESTS_DIR}/data"

    curl -s "https://develop.svn.wordpress.org/${tag}/wp-tests-config-sample.php" \
        > "${WP_TESTS_DIR}/wp-tests-config.php"

    # Patch the config file
    sed -i "s|dirname( __FILE__ ) . '/src/'|'${WP_CORE_DIR}/'|" \
        "${WP_TESTS_DIR}/wp-tests-config.php"
    sed -i "s/youremptytestdbnamehere/${DB_NAME}/"   "${WP_TESTS_DIR}/wp-tests-config.php"
    sed -i "s/yourusernamehere/${DB_USER}/"           "${WP_TESTS_DIR}/wp-tests-config.php"
    sed -i "s/yourpasswordhere/${DB_PASS}/"           "${WP_TESTS_DIR}/wp-tests-config.php"
    sed -i "s|localhost|${DB_HOST}|"                  "${WP_TESTS_DIR}/wp-tests-config.php"
}

create_db() {
    mysqladmin create "$DB_NAME" \
        --user="$DB_USER" \
        --password="$DB_PASS" \
        --host="$DB_HOST" \
        --protocol=tcp \
        2>/dev/null || true
}

echo "Installing WordPress core..."
install_wp

echo "Installing WP test suite..."
install_test_suite

echo "Creating test database '${DB_NAME}'..."
create_db

echo "Done. Run: vendor/bin/phpunit --testsuite Integration"
