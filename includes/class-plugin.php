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
    }

    public static function activate(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta("CREATE TABLE IF NOT EXISTS " . WAA_TABLE_LOGS . " (
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

        dbDelta("CREATE TABLE IF NOT EXISTS " . WAA_TABLE_CONVERSATIONS . " (
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
    }

    public static function deactivate(): void {
        // Per-user rate-limit transients expire automatically after one minute.
    }

    private function register_admin_hooks(): void {
        add_action('admin_init',            [$this, 'maybe_handle_settings_save']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_footer',          [$this, 'inject_mount_point']);
        add_action('admin_menu',            [$this, 'add_settings_page']);
        add_action('wp_dashboard_setup',    [$this, 'add_dashboard_widget']);
        add_action('admin_init',            [$this, 'add_privacy_policy_content']);
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

        wp_redirect(add_query_arg('saved', '1', menu_page_url('wp-admin-agent', false)));
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
        ]);
    }

    public function inject_mount_point(): void {
        if (!current_user_can('manage_options')) return;
        echo '<div id="waa-root"></div>';
    }

    public function add_settings_page(): void {
        add_options_page(
            'WP Admin Agent',
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
            'WP Admin Agent stores administrator conversations and tool execution logs in the WordPress database. When an administrator configures and uses an AI provider, prompts, conversation context, selected site information, and tool schemas may be sent to that provider. The plugin can connect to Anthropic, Google Gemini, or an administrator-configured Ollama server. Optional extensions may connect to additional services that must be disclosed separately.',
            'wp-admin-agent'
        ) . '</p>';
        $content .= '<p>' . esc_html__(
            'Provider credentials are encrypted before storage using WordPress security keys. Site owners should document their selected provider, configure an appropriate retention period, and avoid sending unnecessary personal or confidential information.',
            'wp-admin-agent'
        ) . '</p>';

        wp_add_privacy_policy_content(
            esc_html__('WP Admin Agent', 'wp-admin-agent'),
            wp_kses_post(wpautop($content))
        );
    }
}
