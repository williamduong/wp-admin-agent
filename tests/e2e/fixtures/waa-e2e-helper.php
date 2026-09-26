<?php

defined('ABSPATH') || exit;

add_filter('waa_fake_provider_fixture_path', static function (string $path, string $slug): string {
    if ($slug !== 'runtime-v1') {
        return $path;
    }

    return WP_PLUGIN_DIR . '/william-research-admin-agent/tests/e2e/fixtures/runtime-e2e.json';
}, 10, 2);

add_filter('waa_admin_agent_tool_instances', static function (array $tools): array {
    if (!class_exists('WAA_Tool_Base')) {
        return $tools;
    }

    $tools[] = new class extends WAA_Tool_Base {
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
