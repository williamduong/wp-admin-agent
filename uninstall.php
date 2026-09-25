<?php

defined('WP_UNINSTALL_PLUGIN') || exit;

if (!(bool) get_option('waa_delete_data_on_uninstall', false)) {
    return;
}

global $wpdb;

$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}waa_logs");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}waa_conversations");

$pattern = $wpdb->esc_like('waa_') . '%';
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
    $pattern
));
