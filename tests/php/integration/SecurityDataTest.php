<?php

class SecurityDataTest extends WP_UnitTestCase {
    private int $admin_id;

    public function set_up(): void {
        parent::set_up();
        $this->admin_id = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($this->admin_id);
    }

    public function test_data_sanitizer_redacts_sensitive_keys_and_values(): void {
        $sanitized = WRADMIN_Data_Sanitizer::sanitize([
            'api_key' => 'sk-secret-value-123456789',
            'profile' => ['email' => 'person@example.com'],
            'message' => 'Contact person@example.com using Bearer abc.def.ghi',
            'url' => 'https://example.com/?key=' . 'AIza' . 'VerySecretValue123456789',
        ]);

        $this->assertSame('[redacted]', $sanitized['api_key']);
        $this->assertSame('[redacted]', $sanitized['profile']['email']);
        $this->assertStringNotContainsString('person@example.com', $sanitized['message']);
        $this->assertStringNotContainsString('abc.def.ghi', $sanitized['message']);
        $this->assertStringNotContainsString('AIzaVerySecretValue', $sanitized['url']);
    }

    public function test_authenticated_encryption_rejects_tampering(): void {
        $encryptor = new WRADMIN_Encryptor();
        $ciphertext = $encryptor->encrypt('sensitive conversation');

        $this->assertStringStartsWith('v2:', $ciphertext);
        $this->assertSame('sensitive conversation', $encryptor->decrypt($ciphertext));

        $tampered = substr($ciphertext, 0, -1) . ($ciphertext[-1] === 'A' ? 'B' : 'A');
        $this->assertSame('', $encryptor->decrypt($tampered));
    }

    public function test_conversation_decoder_reads_legacy_json(): void {
        $api = new WRADMIN_REST_API();
        $legacy = wp_json_encode(['messages' => [['role' => 'user', 'content' => 'legacy']]]);
        $decoded = $api->decode_conversation_payload($legacy);

        $this->assertSame('legacy', $decoded['messages'][0]['content']);
    }

    public function test_mcp_exposes_only_read_only_tools(): void {
        $registry = new WRADMIN_Tool_Registry();
        $registry->register(new WRADMIN_Tool_Get_Settings());
        $registry->register(new WRADMIN_Tool_Create_Draft_Post());
        $server = new WRADMIN_MCP_Server($registry);

        $list_request = new WP_REST_Request('POST', '/wp-admin-agent/v1/mcp');
        $list_request->set_header('content-type', 'application/json');
        $list_request->set_body(wp_json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']));
        $list = $server->handle($list_request)->get_data();
        $names = array_column($list['result']['tools'], 'name');
        $this->assertContains('get_site_settings', $names);
        $this->assertNotContains('create_draft_post', $names);

        $call_request = new WP_REST_Request('POST', '/wp-admin-agent/v1/mcp');
        $call_request->set_header('content-type', 'application/json');
        $call_request->set_body(wp_json_encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => ['name' => 'create_draft_post', 'arguments' => ['title' => 'Blocked']],
        ]));
        $call = $server->handle($call_request)->get_data();
        $this->assertTrue($call['result']['isError']);
    }

    public function test_user_listing_omits_login_and_email(): void {
        self::factory()->user->create([
            'role' => 'editor',
            'user_login' => 'private-login',
            'user_email' => 'private@example.com',
            'display_name' => 'Public Display Name',
        ]);

        $result = (new WRADMIN_Tool_List_Users())->execute(['number' => 100]);
        $serialized = wp_json_encode($result);

        $this->assertStringNotContainsString('private-login', $serialized);
        $this->assertStringNotContainsString('private@example.com', $serialized);
        $this->assertStringContainsString('Public Display Name', $serialized);
    }

    public function test_tool_capability_can_be_restricted_by_policy_filter(): void {
        $tool = new WRADMIN_Tool_List_Users();
        $this->assertTrue($tool->check_permission());

        $filter = static fn(string $capability, string $name): string => $name === 'list_users'
            ? 'do_not_allow'
            : $capability;
        add_filter('wradmin_tool_required_capability', $filter, 10, 2);

        try {
            $this->assertFalse($tool->check_permission());
        } finally {
            remove_filter('wradmin_tool_required_capability', $filter, 10);
        }
    }

    public function test_admin_bundle_is_browser_ready_without_commonjs_runtime(): void {
        $plugin = WRADMIN_Plugin::get_instance();
        $plugin->enqueue_assets();

        $registered = wp_scripts()->registered['wradmin-admin-agent'] ?? null;
        $this->assertInstanceOf(_WP_Dependency::class, $registered);
        $this->assertSame(['wp-element'], $registered->deps);

        $bundle = file_get_contents(WRADMIN_PLUGIN_DIR . 'assets/js/admin-agent.js');
        $this->assertIsString($bundle);
        $this->assertSame(0, preg_match('/\brequire\s*\(/', $bundle));

        wp_dequeue_script('wradmin-admin-agent');
        wp_deregister_script('wradmin-admin-agent');
    }

    public function test_activation_creates_current_site_tables(): void {
        WRADMIN_Plugin::activate(false);

        global $wpdb;
        $this->assertSame($wpdb->prefix . 'wradmin_logs', $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix . 'wradmin_logs')));
        $this->assertSame($wpdb->prefix . 'wradmin_conversations', $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix . 'wradmin_conversations')));
    }

    public function test_prefix_upgrade_preserves_options_and_table_rows(): void {
        global $wpdb;

        $prefix = $wpdb->prefix . 'wradmin_migration_test_';
        $old = $prefix . 'waa_logs';
        $new = $prefix . 'wradmin_logs';
        $secret = (new WRADMIN_Encryptor())->encrypt('existing-secret');
        $wpdb->query($wpdb->prepare('CREATE TABLE %i (id BIGINT NOT NULL PRIMARY KEY)', $old));
        $wpdb->query($wpdb->prepare('CREATE TABLE %i (id BIGINT NOT NULL PRIMARY KEY)', $new));
        $wpdb->insert($old, ['id' => 42], ['%d']);
        update_option('waa_db_version', '0.4.3');
        update_option('waa_api_key_enc', $secret);
        delete_option('wradmin_api_key_enc');

        try {
            $this->assertSame('42', (string) $wpdb->get_var($wpdb->prepare('SELECT id FROM %i', $old)));
            $method = new ReflectionMethod(WRADMIN_Plugin::class, 'migrate_legacy_data');
            $this->assertTrue($method->invoke(null, $prefix));
            $this->assertSame('existing-secret', (new WRADMIN_Settings())->get_api_key());
            $this->assertSame('42', (string) $wpdb->get_var($wpdb->prepare('SELECT id FROM %i', $new)));
        } finally {
            $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $old));
            $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $new));
            delete_option('waa_db_version');
            delete_option('waa_api_key_enc');
            delete_option('wradmin_api_key_enc');
        }
    }

    public function test_network_activation_creates_tables_for_each_site(): void {
        if (!is_multisite()) {
            $this->markTestSkipped('Multisite-only activation coverage.');
        }

        $site_id = self::factory()->blog->create();
        $site = get_site($site_id);
        $this->assertInstanceOf(WP_Site::class, $site);

        $known_site_ids = array_map('intval', get_sites(['fields' => 'ids', 'number' => 100]));
        $this->assertContains($site_id, $known_site_ids, 'The new site must be discoverable during network activation.');

        $create_queries = [];
        $capture_queries = static function (array $queries) use (&$create_queries): array {
            $create_queries = array_merge($create_queries, array_values($queries));
            return $queries;
        };
        add_filter('dbdelta_create_queries', $capture_queries);
        try {
            WRADMIN_Plugin::activate(true);
        } finally {
            remove_filter('dbdelta_create_queries', $capture_queries);
        }

        global $wpdb;
        $ddl = implode("\n", $create_queries);
        $main_prefix = $wpdb->get_blog_prefix(get_main_site_id());
        $site_prefix = $wpdb->get_blog_prefix($site_id);

        $this->assertStringContainsString("CREATE TABLE {$main_prefix}wradmin_logs", $ddl);
        $this->assertStringContainsString("CREATE TABLE {$main_prefix}wradmin_conversations", $ddl);
        $this->assertStringContainsString("CREATE TABLE {$site_prefix}wradmin_logs", $ddl);
        $this->assertStringContainsString("CREATE TABLE {$site_prefix}wradmin_conversations", $ddl);
    }
}
