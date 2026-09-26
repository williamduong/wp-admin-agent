<?php

defined('WP_UNINSTALL_PLUGIN') || exit;

function waa_uninstall_current_site(): void {
    if (!(bool) get_option('waa_delete_data_on_uninstall', false)) {
        return;
    }

    global $wpdb;
    $logs_table = $wpdb->prefix . 'waa_logs';
    $conversations_table = $wpdb->prefix . 'waa_conversations';
    $options_table = $wpdb->options;

    $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $logs_table));
    $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $conversations_table));

    $pattern = $wpdb->esc_like('waa_') . '%';
    $wpdb->query($wpdb->prepare(
        'DELETE FROM %i WHERE option_name LIKE %s',
        $options_table,
        $pattern
    ));
}

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
                waa_uninstall_current_site();
            } finally {
                restore_current_blog();
            }
            $processed[] = $site_id;
        }
    } while (count($site_ids) === $batch_size);
} else {
    waa_uninstall_current_site();
}
