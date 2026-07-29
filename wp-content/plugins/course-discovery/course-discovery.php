<?php

declare(strict_types=1);

/**
 * Plugin Name:       Course Discovery
 * Plugin URI:        https://github.com/oxford/course-discovery
 * Description:       A scalable, strictly-typed Course Discovery system built with DDD principles.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Author:            Oxford International
 * Author URI:        https://www.oxfordinternational.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       course-discovery
 * Domain Path:       /languages
 */

namespace Oxford\CourseDiscovery;

// Prevent direct file access.
if (! defined('ABSPATH')) {
    exit;
}

// ── Autoloader ────────────────────────────────────────────────────────────────
// Loaded via Composer PSR-4. The autoloader must be present before any class
// resolution occurs.
$autoloader = __DIR__ . '/vendor/autoload.php';

if (! file_exists($autoloader)) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p>'
            . esc_html__(
                'Course Discovery: Composer dependencies are missing. '
                . 'Please run <code>composer install</code> inside the plugin directory.',
                'course-discovery'
            )
            . '</p></div>';
    });
    return;
}

require_once $autoloader;

// ── Constants ─────────────────────────────────────────────────────────────────
define('COURSE_DISCOVERY_VERSION', '1.0.0');
define('COURSE_DISCOVERY_DIR',     plugin_dir_path(__FILE__));
define('COURSE_DISCOVERY_URL',     plugin_dir_url(__FILE__));
define('COURSE_DISCOVERY_FILE',    __FILE__);

// ── Bootstrap ─────────────────────────────────────────────────────────────────
// Delegate all initialisation to the Plugin class which owns the DI container
// and hook registration. Nothing else is permitted at the global scope.
use Oxford\CourseDiscovery\Infrastructure\Plugin;

Plugin::getInstance()->boot();
