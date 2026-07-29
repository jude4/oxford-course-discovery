<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap for the Course Discovery plugin.
 *
 * For Unit tests:  Brain\Monkey is used to mock WordPress functions.
 *                  No database or WP core required.
 *
 * For Integration tests: The WordPress test suite is loaded. Set the
 *   WP_TESTS_* environment variables (or phpunit.xml envs) before running.
 */

// Load Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// If running Unit tests only (no WP_TESTS_DB_NAME env), skip WP loading.
$suite = $_SERVER['argv'][array_search('--testsuite', $_SERVER['argv']) + 1] ?? 'all';

if (in_array($suite, ['Unit'], true)) {
    // Brain\Monkey bootstrapped per-test via setUp/tearDown trait.
    return;
}

// ── Integration / Feature test bootstrap ──────────────────────────────────────
// Locate the WordPress test library. We support two layouts:
//   1. Installed via wp-cli scaffold plugin-tests (preferred)
//   2. Mounted at /tmp/wordpress-tests-lib in CI containers

$wpTestsDir = getenv('WP_TESTS_DIR') ?: '/tmp/wordpress-tests-lib';

if (! file_exists($wpTestsDir . '/includes/functions.php')) {
    echo "Could not find WordPress test library.\n";
    echo "Set WP_TESTS_DIR or run: bash bin/install-wp-tests.sh\n";
    exit(1);
}

// Give tests access to the WP test functions.
require_once $wpTestsDir . '/includes/functions.php';

/**
 * Manually load the plugin so WP_UnitTestCase can exercise it.
 */
function _manually_load_plugin(): void
{
    require dirname(__DIR__) . '/course-discovery.php';
}

tests_add_filter('muplugins_loaded', '_manually_load_plugin');

require $wpTestsDir . '/includes/bootstrap.php';
