<?php
/**
 * Plugin Name:       William Research Admin Agent
 * Plugin URI:        https://github.com/williamduong/wp-admin-agent
 * Description:       A privacy-conscious AI assistant for safe WordPress administration and draft workflows.
 * Version:           0.4.4
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Author:            William Duong
 * Author URI:        https://williamresearch.com/about/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       william-research-admin-agent
 */

defined('ABSPATH') || exit;

if (!function_exists('wradmin_fs')) {
    /** Return the Freemius instance used only for optional opt-in and account access. */
    function wradmin_fs() {
        global $wradmin_fs;

        if (isset($wradmin_fs)) {
            return $wradmin_fs;
        }

        if (!function_exists('fs_dynamic_init')) {
            $sdk_start = __DIR__ . '/vendor/freemius/start.php';
            if (!file_exists($sdk_start)) {
                return null;
            }

            require_once $sdk_start;
            if (!function_exists('fs_dynamic_init')) {
                return null;
            }
        }

        $wradmin_fs = fs_dynamic_init([
            'id'               => '40099',
            'slug'             => 'william-research-admin-agent',
            'type'             => 'plugin',
            'public_key'       => 'pk_e5a299b6a3d29cfb5f9c5e832f928',
            'is_premium'       => false,
            // WordPress.org Free must never discover, download, install, or update Pro.
            'has_addons'       => false,
            'has_paid_plans'   => false,
            'is_org_compliant' => true,
            'menu'             => [
                'slug'       => 'wp-admin-agent',
                'account'    => true,
                'addons'     => false,
                'pricing'    => false,
                'contact'    => false,
                'support'    => false,
                'parent'     => [
                    'slug' => 'options-general.php',
                ],
            ],
        ]);

        return $wradmin_fs;
    }

    wradmin_fs();
    do_action('wradmin_fs_loaded');
}

define('WRADMIN_VERSION',             '0.4.4');
define('WRADMIN_PLUGIN_DIR',          plugin_dir_path(__FILE__));
define('WRADMIN_PLUGIN_URL',          plugin_dir_url(__FILE__));
define('WRADMIN_TABLE_LOGS',          $GLOBALS['wpdb']->prefix . 'wradmin_logs');
define('WRADMIN_TABLE_CONVERSATIONS', $GLOBALS['wpdb']->prefix . 'wradmin_conversations');
define('WRADMIN_MAX_TOOL_ITERATIONS', 10);
define('WRADMIN_RATE_LIMIT',          30);

spl_autoload_register(function (string $class): void {
    $prefixes = [
        'WRADMIN_Tool_' => WRADMIN_PLUGIN_DIR . 'tools/class-tool-',
        'WRADMIN_'      => WRADMIN_PLUGIN_DIR . 'includes/class-',
    ];
    foreach ($prefixes as $prefix => $base) {
        if (!str_starts_with($class, $prefix)) continue;
        $suffix = strtolower(str_replace('_', '-', substr($class, strlen($prefix))));
        $file   = $base . $suffix . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

require_once WRADMIN_PLUGIN_DIR . 'includes/class-plugin.php';

register_activation_hook(__FILE__,   ['WRADMIN_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['WRADMIN_Plugin', 'deactivate']);

add_action('plugins_loaded', function () {
    WRADMIN_Plugin::get_instance()->init();

    // Read migrated settings after the upgrade has completed.
    if (get_option('wradmin_xmlrpc_disabled')) {
        add_filter('xmlrpc_enabled', '__return_false');
    }
    if (get_option('wradmin_hide_wp_version')) {
        add_filter('the_generator', '__return_empty_string');
        remove_action('wp_head', 'wp_generator');
    }
});
