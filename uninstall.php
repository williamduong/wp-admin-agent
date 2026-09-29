<?php

defined('WP_UNINSTALL_PLUGIN') || exit;

function wradmin_uninstall_current_site(): void {
    if (!(bool) get_option('wradmin_delete_data_on_uninstall', get_option('waa_delete_data_on_uninstall', false))) {
        return;
    }

    global $wpdb;
    $logs_table = $wpdb->prefix . 'wradmin_logs';
    $conversations_table = $wpdb->prefix . 'wradmin_conversations';
    $options_table = $wpdb->options;

    $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $logs_table));
    $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $conversations_table));
    $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $wpdb->prefix . 'waa_logs'));
    $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $wpdb->prefix . 'waa_conversations'));

    $pattern = $wpdb->esc_like('wradmin_') . '%';
    $wpdb->query($wpdb->prepare(
        'DELETE FROM %i WHERE option_name LIKE %s',
        $options_table,
        $pattern
    ));
    foreach ([
        'db_version', 'provider', 'model', 'api_key_enc', 'gemini_key_enc',
        'ollama_url', 'custom_rules', 'disabled_tools', 'pexels_key_enc',
        'debug_mode', 'delete_data_on_uninstall', 'data_retention_days',
        'max_tokens', 'xmlrpc_disabled', 'hide_wp_version',
    ] as $suffix) {
        delete_option('waa_' . $suffix);
    }
    $legacy_pending_pattern = $wpdb->esc_like('waa_pending_action_') . '%';
    $wpdb->query($wpdb->prepare(
        'DELETE FROM %i WHERE option_name LIKE %s',
        $options_table,
        $legacy_pending_pattern
    ));
}

function wradmin_uninstall_all_sites(): void {
    if (is_multisite()) {
        $batch_size = 100;
        $processed = [];
        do {
            $query = [
                'fields' => 'ids',
                'number' => $batch_size,
            ];
            if ($processed !== []) {
                $query['site__not_in'] = $processed;
            }
            $site_ids = get_sites($query);
            foreach ($site_ids as $site_id) {
                $site_id = (int) $site_id;
                switch_to_blog($site_id);
                try {
                    wradmin_uninstall_current_site();
                } finally {
                    restore_current_blog();
                }
                $processed[] = $site_id;
            }
        } while (count($site_ids) === $batch_size);
    } else {
        wradmin_uninstall_current_site();
    }
}

wradmin_uninstall_all_sites();
