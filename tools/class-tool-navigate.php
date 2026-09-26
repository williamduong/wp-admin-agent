<?php

defined('ABSPATH') || exit;

class WAA_Tool_Navigate extends WAA_Tool_Base {
    public function get_name(): string { return 'navigate'; }

    public function get_description(): string {
        return 'Prepare a user-approved navigation link to a page inside this site\'s WordPress admin. Use relative paths like "plugins.php", "edit.php", or "options-general.php".';
    }

    public function get_input_schema(): array {
        return [
            'type'       => 'object',
            'properties' => [
                'page' => [
                    'type'        => 'string',
                    'description' => 'wp-admin relative path, for example "plugins.php" or "edit.php?post_type=page". External URLs are rejected.',
                ],
                'focus_selector' => [
                    'type'        => 'string',
                    'description' => 'Optional CSS ID selector to scroll to after navigation (e.g. "#timezone_string", "#blogname"). Must start with #.',
                ],
            ],
            'required' => ['page'],
        ];
    }

    public function execute(array $input): array {
        $page = trim($input['page'] ?? '');
        if (!$page) {
            return ['success' => false, 'error' => 'page is required.'];
        }

        $url = filter_var($page, FILTER_VALIDATE_URL) ? esc_url_raw($page) : admin_url(ltrim($page, '/'));
        $admin = wp_parse_url(admin_url());
        $target = wp_parse_url($url);
        $same_origin = is_array($target)
            && strtolower((string) ($target['scheme'] ?? '')) === strtolower((string) ($admin['scheme'] ?? ''))
            && strtolower((string) ($target['host'] ?? '')) === strtolower((string) ($admin['host'] ?? ''))
            && (int) ($target['port'] ?? 0) === (int) ($admin['port'] ?? 0);
        $admin_path = trailingslashit((string) ($admin['path'] ?? '/wp-admin/'));
        $target_path = (string) ($target['path'] ?? '');
        if (!$same_origin || !str_starts_with(trailingslashit($target_path), $admin_path)) {
            return ['success' => false, 'error' => 'Only pages inside this site\'s WordPress admin are allowed.'];
        }

        // Append CSS ID hash for native browser scroll
        $focus = trim($input['focus_selector'] ?? '');
        if ($focus && preg_match('/^#[A-Za-z][A-Za-z0-9_-]*$/', $focus) && !str_contains($url, '#')) {
            $url .= $focus;
        }

        return [
            'success'        => true,
            '_navigate_url'  => esc_url_raw($url),
            'message'        => "Navigation is ready: $url",
        ];
    }
}
