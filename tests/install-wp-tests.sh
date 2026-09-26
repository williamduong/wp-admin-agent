#!/usr/bin/env bash
set -euo pipefail

DB_NAME="${1:-wordpress_test}"
DB_USER="${2:-root}"
DB_PASS="${3:-root}"
DB_HOST="${4:-127.0.0.1}"
WP_VERSION="${5:-latest}"
WP_MULTISITE="${6:-false}"
WP_CORE_DIR="${WP_CORE_DIR:-/tmp/wordpress}"
WP_TESTS_DIR="${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}"

if [[ ! -d "${WP_CORE_DIR}/wp-includes" ]]; then
    mkdir -p "${WP_CORE_DIR}"
    if [[ "${WP_VERSION}" == "latest" ]]; then
        archive_url="https://wordpress.org/latest.tar.gz"
    else
        archive_url="https://wordpress.org/wordpress-${WP_VERSION}.tar.gz"
    fi
    curl --fail --silent --show-error --location "${archive_url}" | tar --strip-components=1 -xz -C "${WP_CORE_DIR}"
fi

if [[ ! -d "${WP_TESTS_DIR}/includes" ]]; then
    mkdir -p "${WP_TESTS_DIR}"
    if [[ "${WP_VERSION}" == "latest" ]]; then
        resolved_version="$(php -r "include '${WP_CORE_DIR}/wp-includes/version.php'; echo \$wp_version;")"
    else
        resolved_version="${WP_VERSION}"
    fi
    tests_archive="https://codeload.github.com/WordPress/wordpress-develop/tar.gz/refs/tags/${resolved_version}"
    curl --fail --silent --show-error --location "${tests_archive}" \
        | tar -xz -C "${WP_TESTS_DIR}" --strip-components=3 --wildcards \
            '*/tests/phpunit/includes/*' \
            '*/tests/phpunit/data/*'
fi

cat > "${WP_TESTS_DIR}/wp-tests-config.php" <<PHP
<?php
define( 'DB_NAME', '${DB_NAME}' );
define( 'DB_USER', '${DB_USER}' );
define( 'DB_PASSWORD', '${DB_PASS}' );
define( 'DB_HOST', '${DB_HOST}' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'William Research Admin Agent Tests' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
define( 'WP_DEBUG', true );
define( 'WP_TESTS_MULTISITE', ${WP_MULTISITE} );
define( 'ABSPATH', '${WP_CORE_DIR}/' );
\$table_prefix = 'wptests_';
PHP
