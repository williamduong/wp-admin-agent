<?php

defined('ABSPATH') || exit;

/** First-run setup and safe, read-only capability checks. */
class WRADMIN_Onboarding {
    private const SLUG = 'wp-admin-agent-setup';
    private const TESTABLE_TOOLS = [
        'get_site_settings' => 'Site settings are readable.',
        'list_plugins' => 'Installed plugins are readable.',
        'get_woocommerce_status' => 'WooCommerce status is readable.',
    ];

    public static function register(): void {
        add_action('admin_menu', [self::class, 'add_page']);
        add_action('admin_init', [self::class, 'maybe_redirect']);
        add_action('admin_notices', [self::class, 'show_notice']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
        add_action('admin_post_wradmin_setup_save', [self::class, 'save']);
        add_action('admin_post_wradmin_setup_finish', [self::class, 'finish']);
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function url(array $args = []): string {
        return add_query_arg(array_merge(['page' => self::SLUG], $args), admin_url('options-general.php'));
    }

    public static function add_page(): void {
        add_options_page(
            __('Set up Admin Agent', 'william-research-admin-agent'),
            __('Agent Setup', 'william-research-admin-agent'),
            'manage_options',
            self::SLUG,
            [self::class, 'render']
        );
    }

    public static function maybe_redirect(): void {
        if (get_option('wradmin_onboarding_status') !== 'pending' || !current_user_can('manage_options')) {
            return;
        }
        if (wp_doing_ajax() || wp_doing_cron() || is_network_admin() || (defined('WP_CLI') && WP_CLI)) {
            return;
        }
        // Only take over the single-plugin activation redirect, never a normal admin visit.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WordPress activation marker, used only for a redirect.
        if (($GLOBALS['pagenow'] ?? '') !== 'plugins.php' || !isset($_GET['activate'])) {
            return;
        }
        wp_safe_redirect(self::url());
        exit;
    }

    public static function show_notice(): void {
        if (get_option('wradmin_onboarding_status') !== 'pending' || !current_user_can('manage_options')) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page selection.
        if (isset($_GET['page']) && sanitize_key(wp_unslash($_GET['page'])) === self::SLUG) {
            return;
        }
        printf(
            '<div class="notice notice-info"><p>%s <a href="%s">%s</a></p></div>',
            esc_html__('Your assistant is ready to meet you.', 'william-research-admin-agent'),
            esc_url(self::url()),
            esc_html__('Start the quick setup', 'william-research-admin-agent')
        );
    }

    public static function enqueue_assets(string $hook_suffix): void {
        if ($hook_suffix !== 'settings_page_' . self::SLUG || !current_user_can('manage_options')) {
            return;
        }
        $base = WRADMIN_PLUGIN_DIR . 'admin/';
        wp_enqueue_style('wradmin-onboarding', WRADMIN_PLUGIN_URL . 'admin/onboarding.css', [], (string) filemtime($base . 'onboarding.css'));
        wp_enqueue_script('wradmin-onboarding', WRADMIN_PLUGIN_URL . 'admin/onboarding.js', [], (string) filemtime($base . 'onboarding.js'), true);
        wp_localize_script('wradmin-onboarding', 'wradminSetupData', [
            'restUrl' => rest_url('wp-admin-agent/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'pricing' => WRADMIN_Pricing::all_for_js(),
            'chatName' => (new WRADMIN_Settings())->get_bot_name(),
            'settingsUrl' => admin_url('options-general.php?page=wp-admin-agent'),
        ]);
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You cannot configure this plugin.', 'william-research-admin-agent'));
        }
        require WRADMIN_PLUGIN_DIR . 'admin/onboarding-page.php';
    }

    public static function save(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You cannot configure this plugin.', 'william-research-admin-agent'), '', ['response' => 403]);
        }
        check_admin_referer('wradmin_setup_save');
        $posted = wp_unslash($_POST);
        $settings = new WRADMIN_Settings();

        $settings->set_bot_name((string) ($posted['wradmin_bot_name'] ?? ''));
        $settings->set_user_title((string) ($posted['wradmin_user_title'] ?? ''));
        $settings->set_bot_style(sanitize_key((string) ($posted['wradmin_bot_style'] ?? 'friendly')));

        $provider = sanitize_key((string) ($posted['wradmin_provider'] ?? ''));
        $valid_provider = in_array($provider, ['anthropic', 'gemini', 'ollama'], true)
            || ($provider === 'fake' && wp_get_environment_type() !== 'production');
        if ($valid_provider) {
            $settings->set_provider($provider);
        }
        if ($valid_provider && isset($posted['wradmin_model'])) {
            $settings->set_model((string) $posted['wradmin_model']);
        }
        if (!empty($posted['wradmin_api_key'])) {
            $settings->set_api_key(sanitize_text_field($posted['wradmin_api_key']));
        }
        if (!empty($posted['wradmin_gemini_key'])) {
            $settings->set_gemini_api_key(sanitize_text_field($posted['wradmin_gemini_key']));
        }
        if (!empty($posted['wradmin_ollama_url'])) {
            $settings->set_ollama_url(esc_url_raw($posted['wradmin_ollama_url']));
        }

        $available = array_column(WRADMIN_REST_API::build_registry()->get_schemas(), 'name');
        $submitted = is_array($posted['wradmin_setup_tools'] ?? null) ? $posted['wradmin_setup_tools'] : [];
        $enabled = array_intersect($available, array_map('sanitize_key', array_filter($submitted, 'is_string')));
        // Preserve choices for extension tools that are temporarily unavailable.
        $unavailable_disabled = array_diff($settings->get_disabled_tools(), $available);
        $settings->set_disabled_tools(array_values(array_merge($unavailable_disabled, array_diff($available, $enabled))));
        if (isset($posted['wradmin_data_retention_days'])) {
            $settings->set_data_retention_days((int) $posted['wradmin_data_retention_days']);
        }

        wp_safe_redirect(self::url(['step' => 'try', 'saved' => '1']));
        exit;
    }

    public static function finish(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You cannot configure this plugin.', 'william-research-admin-agent'), '', ['response' => 403]);
        }
        check_admin_referer('wradmin_setup_finish');
        update_option('wradmin_onboarding_status', 'complete', false);
        wp_safe_redirect(self::url(['step' => 'done']));
        exit;
    }

    public static function register_routes(): void {
        register_rest_route('wp-admin-agent/v1', '/setup/test-tool', [
            'methods' => 'POST',
            'callback' => [self::class, 'test_tool'],
            'permission_callback' => static function (): bool {
                return current_user_can('manage_options') && (new WRADMIN_Rate_Limiter())->check();
            },
        ]);
    }

    public static function test_tool(WP_REST_Request $request): WP_REST_Response {
        $name = sanitize_key((string) $request->get_param('name'));
        if (!isset(self::TESTABLE_TOOLS[$name])) {
            return new WP_REST_Response(['success' => false, 'error' => 'This tool cannot be tested during setup.'], 400);
        }
        $settings = new WRADMIN_Settings();
        $result = WRADMIN_REST_API::build_registry($settings->get_disabled_tools())->execute($name, []);
        if (isset($result['error'])) {
            return new WP_REST_Response(['success' => false, 'error' => $result['error']], 400);
        }
        return new WP_REST_Response(['success' => true, 'message' => self::TESTABLE_TOOLS[$name]]);
    }
}
