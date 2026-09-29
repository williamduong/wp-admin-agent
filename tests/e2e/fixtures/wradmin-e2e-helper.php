<?php

defined('ABSPATH') || exit;

add_filter('wradmin_fake_provider_fixture_path', static function (string $path, string $slug): string {
    if ($slug !== 'runtime-v1') {
        return $path;
    }

    return WP_PLUGIN_DIR . '/william-research-admin-agent/tests/e2e/fixtures/runtime-e2e.json';
}, 10, 2);

add_filter('wradmin_admin_agent_tool_instances', static function (array $tools): array {
    if (!class_exists('WRADMIN_Tool_Base')) {
        return $tools;
    }

    $tools[] = new class extends WRADMIN_Tool_Base {
        public function get_name(): string {
            return 'update_site_settings';
        }

        public function get_description(): string {
            return 'E2E-only protected settings action.';
        }

        public function get_input_schema(): array {
            return [
                'type' => 'object',
                'properties' => [
                    'updates' => ['type' => 'object'],
                ],
                'required' => ['updates'],
            ];
        }

        public function execute(array $input): array {
            return ['success' => true, 'message' => 'E2E protected action completed.'];
        }
    };

    return $tools;
});

add_action('wp_ajax_wradmin_test_prefix_upgrade', static function (): void {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Forbidden', 403);
    }

    global $wpdb;
    $prefix = $wpdb->prefix . 'migration_e2e_';
    $old = $prefix . 'waa_logs';
    $new = $prefix . 'wradmin_logs';
    $secret = (new WRADMIN_Encryptor())->encrypt('preserved-key');

    $wpdb->query($wpdb->prepare('CREATE TABLE %i (id BIGINT NOT NULL PRIMARY KEY)', $old));
    $wpdb->query($wpdb->prepare('CREATE TABLE %i (id BIGINT NOT NULL PRIMARY KEY)', $new));
    $wpdb->insert($old, ['id' => 42], ['%d']);
    update_option('waa_db_version', '0.4.3');
    update_option('waa_api_key_enc', $secret);
    delete_option('wradmin_api_key_enc');

    try {
        $method = new ReflectionMethod(WRADMIN_Plugin::class, 'migrate_legacy_data');
        $migrated = $method->invoke(null, $prefix);
        $result = [
            'migrated' => $migrated,
            'key' => (new WRADMIN_Settings())->get_api_key(),
            'row' => (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM %i', $new)),
        ];
    } finally {
        $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $old));
        $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $new));
        delete_option('waa_db_version');
        delete_option('waa_api_key_enc');
        delete_option('wradmin_api_key_enc');
    }
    wp_send_json_success($result);
});
