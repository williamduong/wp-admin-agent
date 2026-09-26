<?php

defined('ABSPATH') || exit;

class WAA_Plugin {
    private static ?self $instance = null;

    public static function get_instance(): static {
        return static::$instance ??= new static();
    }

    public function init(): void {
        new WAA_REST_API();
        $this->register_admin_hooks();
        add_action('waa_cleanup_agent_data', ['WAA_Audit_Log', 'cleanup_expired']);
        add_action('wp_initialize_site', [self::class, 'initialize_new_site'], 20, 1);
        if (!wp_next_scheduled('waa_cleanup_agent_data')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'waa_cleanup_agent_data');
        }
    }

    public static function activate(bool $network_wide = false): void {
        if (is_multisite() && $network_wide) {
            self::for_each_site(static function (int $site_id): void {
                switch_to_blog((int) $site_id);
                try {
                    self::install_for_current_site($site_id);
                } finally {
                    restore_current_blog();
                }
            });
            return;
        }

        self::install_for_current_site();
    }

    public static function initialize_new_site(WP_Site $site): void {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        if (!is_plugin_active_for_network(plugin_basename(WAA_PLUGIN_DIR . 'wp-admin-agent.php'))) {
            return;
        }

        switch_to_blog((int) $site->blog_id);
        try {
            self::install_for_current_site((int) $site->blog_id);
        } finally {
            restore_current_blog();
        }
    }

    private static function install_for_current_site(?int $site_id = null): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $prefix = $site_id !== null ? $wpdb->get_blog_prefix($site_id) : $wpdb->prefix;
        $logs_table = $prefix . 'waa_logs';
        $conversations_table = $prefix . 'waa_conversations';
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta("CREATE TABLE {$logs_table} (
            id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id        BIGINT UNSIGNED NOT NULL,
            tool_name      VARCHAR(100)    NOT NULL,
            params         LONGTEXT,
            result         LONGTEXT,
            status         VARCHAR(20)     DEFAULT 'success',
            provider       VARCHAR(50)     DEFAULT '',
            model          VARCHAR(100)    DEFAULT '',
            input_tokens   INT UNSIGNED    DEFAULT 0,
            output_tokens  INT UNSIGNED    DEFAULT 0,
            created_at     DATETIME        DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id  (user_id),
            KEY idx_created  (created_at),
            KEY idx_model    (provider, model)
        ) $charset;");

        dbDelta("CREATE TABLE {$conversations_table} (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id     BIGINT UNSIGNED NOT NULL,
            title       VARCHAR(255),
            messages    LONGTEXT,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id)
        ) $charset;");

        update_option('waa_db_version', WAA_VERSION);
        if (!wp_next_scheduled('waa_cleanup_agent_data')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'waa_cleanup_agent_data');
        }
    }

    private static function for_each_site(callable $callback): void {
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
                $callback($site_id);
                $processed[] = $site_id;
            }
        } while (count($site_ids) === $batch_size);
    }

    public static function deactivate(bool $network_wide = false): void {
        if (is_multisite() && $network_wide) {
            self::for_each_site(static function (int $site_id): void {
                switch_to_blog((int) $site_id);
                try {
                    wp_clear_scheduled_hook('waa_cleanup_agent_data');
                } finally {
                    restore_current_blog();
                }
            });
            return;
        }

        wp_clear_scheduled_hook('waa_cleanup_agent_data');
    }

    private function register_admin_hooks(): void {
        add_action('admin_init',            [$this, 'maybe_handle_settings_save']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_footer',          [$this, 'inject_mount_point']);
        add_action('admin_menu',            [$this, 'add_settings_page']);
        add_action('wp_dashboard_setup',    [$this, 'add_dashboard_widget']);
        add_action('admin_init',            [$this, 'add_privacy_policy_content']);
        add_filter('wp_privacy_personal_data_exporters', [$this, 'register_privacy_exporter']);
        add_filter('wp_privacy_personal_data_erasers', [$this, 'register_privacy_eraser']);
    }

    public function maybe_handle_settings_save(): void {
        $request_method = isset($_SERVER['REQUEST_METHOD'])
            ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']))
            : '';
        if (!is_admin() || $request_method !== 'POST') {
            return;
        }

        $posted = wp_unslash($_POST);
        $page = isset($posted['page'])
            ? sanitize_key($posted['page'])
            : (isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '');
        $nonce = isset($posted['_wpnonce']) ? sanitize_text_field($posted['_wpnonce']) : '';
        if ($nonce === '' || $page !== 'wp-admin-agent') {
            return;
        }

        if (!current_user_can('manage_options') || !wp_verify_nonce($nonce, 'waa_settings')) {
            return;
        }

        $settings = new WAA_Settings();

        if (!empty($posted['waa_provider'])) {
            $settings->set_provider(sanitize_text_field($posted['waa_provider']));
        }

        if (!empty($posted['waa_model'])) {
            $settings->set_model(sanitize_text_field($posted['waa_model']));
        }

        if (!empty($posted['waa_api_key']) && $posted['waa_api_key'] !== '••••••••') {
            $settings->set_api_key(sanitize_text_field($posted['waa_api_key']));
        }

        if (!empty($posted['waa_gemini_key']) && $posted['waa_gemini_key'] !== '••••••••') {
            $settings->set_gemini_api_key(sanitize_text_field($posted['waa_gemini_key']));
        }

        if (!empty($posted['waa_ollama_url'])) {
            $settings->set_ollama_url(esc_url_raw($posted['waa_ollama_url']));
        }

        if (isset($posted['waa_debug_mode'])) {
            $settings->set_debug_mode(sanitize_key($posted['waa_debug_mode']));
        }

        $tab = isset($posted['tab']) ? sanitize_key($posted['tab']) : '';
        if ($tab === 'provider') {
            $settings->set_delete_data_on_uninstall(isset($posted['waa_delete_data_on_uninstall']));
            if (isset($posted['waa_data_retention_days'])) {
                $settings->set_data_retention_days((int) $posted['waa_data_retention_days']);
            }
        }

        // Custom rules (textarea — may be empty, that's valid)
        if (isset($posted['waa_custom_rules'])) {
            $settings->set_custom_rules(sanitize_textarea_field($posted['waa_custom_rules']));
        }

        // Disabled tools are updated only when the Tools tab is submitted.
        // Otherwise, preserve the existing tool enable/disable state.
        if ($tab === 'tools') {
            $submitted_enabled = array_keys(array_filter($posted, fn($k) => str_starts_with(sanitize_key($k), 'waa_tool_'), ARRAY_FILTER_USE_KEY));
            $enabled_names     = array_map(fn($k) => substr($k, strlen('waa_tool_')), $submitted_enabled);
            $all_tools         = array_column(WAA_REST_API::build_registry()->get_schemas(), 'name');
            $disabled          = array_values(array_diff($all_tools, $enabled_names));
            $settings->set_disabled_tools($disabled);
        }

        do_action('waa_admin_agent_save_settings', $tab, $settings, map_deep($posted, 'sanitize_text_field'));

        wp_safe_redirect(add_query_arg('saved', '1', menu_page_url('wp-admin-agent', false)));
        exit;
    }

    public function enqueue_assets(): void {
        if (!current_user_can('manage_options')) return;

        $js_path = WAA_PLUGIN_DIR . 'assets/js/admin-agent.js';
        $version = file_exists($js_path) ? filemtime($js_path) : WAA_VERSION;

        wp_enqueue_script(
            'waa-admin-agent',
            WAA_PLUGIN_URL . 'assets/js/admin-agent.js',
            [],
            $version,
            true
        );

        $css_path = WAA_PLUGIN_DIR . 'assets/css/admin-agent.css';
        if (file_exists($css_path)) {
            wp_enqueue_style(
                'waa-admin-agent',
                WAA_PLUGIN_URL . 'assets/css/admin-agent.css',
                [],
                $version
            );
        }

        $settings = new WAA_Settings();
        wp_localize_script('waa-admin-agent', 'waaData', [
            'nonce'       => wp_create_nonce('wp_rest'),
            'restUrl'     => rest_url('wp-admin-agent/v1/'),
            'currentUser' => [
                'id'   => get_current_user_id(),
                'name' => wp_get_current_user()->display_name,
            ],
            'siteUrl'     => get_site_url(),
            'version'     => WAA_VERSION,
            'provider'    => $settings->get_provider(),
            'model'       => $settings->get_model(),
            'pricing'     => WAA_Pricing::all_for_js(),
            'debugMode'   => $settings->get_debug_mode(),
            'isPro'       => defined('WAA_PRO_VERSION'),
        ]);
    }

    public function inject_mount_point(): void {
        if (!current_user_can('manage_options')) return;
        echo '<div id="waa-root"></div>';
    }

    public function add_settings_page(): void {
        add_options_page(
            'William Research Admin Agent',
            'Admin Agent',
            'manage_options',
            'wp-admin-agent',
            function () {
                require_once WAA_PLUGIN_DIR . 'admin/settings-page.php';
            }
        );
    }

    public function add_dashboard_widget(): void {
        if (!current_user_can('manage_options')) return;
        wp_add_dashboard_widget(
            'waa_recent_actions',
            'Recent Agent Actions',
            function () {
                require_once WAA_PLUGIN_DIR . 'admin/dashboard-widget.php';
                waa_render_dashboard_widget();
            }
        );
    }

    public function add_privacy_policy_content(): void {
        if (!function_exists('wp_add_privacy_policy_content')) {
            return;
        }

        $content = '<p>' . esc_html__(
            'William Research Admin Agent stores administrator conversations and tool execution logs in the WordPress database. When an administrator configures and uses an AI provider, prompts, conversation context, selected site information, and tool schemas may be sent to that provider. The plugin can connect to Anthropic, Google Gemini, or an administrator-configured Ollama server. Optional extensions may connect to additional services that must be disclosed separately.',
            'william-research-admin-agent'
        ) . '</p>';
        $content .= '<p>' . esc_html__(
            'Provider credentials are encrypted before storage using WordPress security keys. Site owners should document their selected provider, configure an appropriate retention period, and avoid sending unnecessary personal or confidential information.',
            'william-research-admin-agent'
        ) . '</p>';

        wp_add_privacy_policy_content(
            esc_html__('William Research Admin Agent', 'william-research-admin-agent'),
            wp_kses_post(wpautop($content))
        );
    }

    public function register_privacy_exporter(array $exporters): array {
        $exporters['william-research-admin-agent'] = [
            'exporter_friendly_name' => esc_html__('William Research Admin Agent', 'william-research-admin-agent'),
            'callback' => [$this, 'export_personal_data'],
        ];
        return $exporters;
    }

    public function export_personal_data(string $email_address, int $page = 1): array {
        $user = get_user_by('email', $email_address);
        if (!$user) {
            return ['data' => [], 'done' => true];
        }

        global $wpdb;
        $limit = 50;
        $offset = max(0, ($page - 1) * $limit);
        $conversations = $wpdb->get_results($wpdb->prepare(
            "SELECT id, title, messages, created_at, updated_at FROM %i WHERE user_id = %d ORDER BY id LIMIT %d OFFSET %d",
            WAA_TABLE_CONVERSATIONS,
            $user->ID,
            $limit,
            $offset
        ), ARRAY_A);
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT id, tool_name, params, result, status, provider, model, created_at FROM %i WHERE user_id = %d ORDER BY id LIMIT %d OFFSET %d",
            WAA_TABLE_LOGS,
            $user->ID,
            $limit,
            $offset
        ), ARRAY_A);

        $data = [];
        $rest_api = new WAA_REST_API();
        foreach ($conversations as $row) {
            $conversation_data = $rest_api->decode_conversation_payload((string) $row['messages']);
            $data[] = [
                'group_id' => 'william-research-admin-agent-conversations',
                'group_label' => esc_html__('Admin Agent conversations', 'william-research-admin-agent'),
                'item_id' => 'conversation-' . $row['id'],
                'data' => [
                    ['name' => 'Title', 'value' => $row['title']],
                    ['name' => 'Conversation data', 'value' => wp_json_encode($conversation_data)],
                    ['name' => 'Created', 'value' => $row['created_at']],
                    ['name' => 'Updated', 'value' => $row['updated_at']],
                ],
            ];
        }
        foreach ($logs as $row) {
            $data[] = [
                'group_id' => 'william-research-admin-agent-logs',
                'group_label' => esc_html__('Admin Agent audit records', 'william-research-admin-agent'),
                'item_id' => 'audit-' . $row['id'],
                'data' => array_map(
                    static fn(string $name, mixed $value): array => ['name' => $name, 'value' => (string) $value],
                    array_keys($row),
                    array_values($row)
                ),
            ];
        }

        return ['data' => $data, 'done' => count($conversations) < $limit && count($logs) < $limit];
    }

    public function register_privacy_eraser(array $erasers): array {
        $erasers['william-research-admin-agent'] = [
            'eraser_friendly_name' => esc_html__('William Research Admin Agent', 'william-research-admin-agent'),
            'callback' => [$this, 'erase_personal_data'],
        ];
        return $erasers;
    }

    public function erase_personal_data(string $email_address, int $page = 1): array {
        $user = get_user_by('email', $email_address);
        if (!$user) {
            return ['items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true];
        }

        global $wpdb;
        $conversations = $wpdb->delete(WAA_TABLE_CONVERSATIONS, ['user_id' => $user->ID], ['%d']);
        $logs = $wpdb->delete(WAA_TABLE_LOGS, ['user_id' => $user->ID], ['%d']);

        return [
            'items_removed' => ($conversations + $logs) > 0,
            'items_retained' => false,
            'messages' => [],
            'done' => true,
        ];
    }
}
