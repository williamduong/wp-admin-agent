<?php
/**
 * Plugin Name:       William Research Admin Agent
 * Plugin URI:        https://github.com/williamduong/wp-admin-agent
 * Description:       A privacy-conscious AI assistant for safe WordPress administration and draft workflows.
 * Version:           0.4.1
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Author:            William Duong
 * Author URI:        https://williamresearch.com/about/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       william-research-admin-agent
 */

defined('ABSPATH') || exit;

if (!function_exists('wraa_fs')) {
    /** Return the Freemius instance used for opt-in, account, and add-on discovery. */
    function wraa_fs() {
        global $wraa_fs;

        if (isset($wraa_fs)) {
            return $wraa_fs;
        }

        $sdk_start = __DIR__ . '/vendor/freemius/start.php';
        if (!file_exists($sdk_start)) {
            return null;
        }

        require_once $sdk_start;
        if (!function_exists('fs_dynamic_init')) {
            return null;
        }

        $wraa_fs = fs_dynamic_init([
            'id'               => '40099',
            'slug'             => 'william-research-admin-agent',
            'premium_slug'     => 'william-research-admin-agent-pro',
            'type'             => 'plugin',
            'public_key'       => 'pk_e5a299b6a3d29cfb5f9c5e832f928',
            'is_premium'       => false,
            'has_addons'       => true,
            'has_paid_plans'   => false,
            'is_org_compliant' => true,
            'menu'             => [
                'slug'       => 'wp-admin-agent',
                'account'    => true,
                'contact'    => false,
                'support'    => false,
                'parent'     => [
                    'slug' => 'options-general.php',
                ],
            ],
        ]);

        return $wraa_fs;
    }

    wraa_fs();
    do_action('wraa_fs_loaded');
}

define('WAA_VERSION',             '0.4.1');
define('WAA_PLUGIN_DIR',          plugin_dir_path(__FILE__));
define('WAA_PLUGIN_URL',          plugin_dir_url(__FILE__));
define('WAA_TABLE_LOGS',          $GLOBALS['wpdb']->prefix . 'waa_logs');
define('WAA_TABLE_CONVERSATIONS', $GLOBALS['wpdb']->prefix . 'waa_conversations');
define('WAA_MAX_TOOL_ITERATIONS', 10);
define('WAA_RATE_LIMIT',          30);

spl_autoload_register(function (string $class): void {
    $prefixes = [
        'WAA_Tool_' => WAA_PLUGIN_DIR . 'tools/class-tool-',
        'WAA_'      => WAA_PLUGIN_DIR . 'includes/class-',
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

require_once WAA_PLUGIN_DIR . 'includes/class-plugin.php';

register_activation_hook(__FILE__,   ['WAA_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['WAA_Plugin', 'deactivate']);

add_action('plugins_loaded', function () {
    WAA_Plugin::get_instance()->init();
});

// Runtime security hooks (activated via security_harden tool)
if (get_option('waa_xmlrpc_disabled')) {
    add_filter('xmlrpc_enabled', '__return_false');
}
if (get_option('waa_hide_wp_version')) {
    add_filter('the_generator', '__return_empty_string');
    remove_action('wp_head', 'wp_generator');
}
