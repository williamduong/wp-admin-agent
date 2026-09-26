<?php

class PublicToolContractTest extends WP_UnitTestCase {
    /** @return WAA_Tool_Base[] */
    private function public_tools(): array {
        return [
            new WAA_Tool_Get_Settings(),
            new WAA_Tool_List_Plugins(),
            new WAA_Tool_List_Themes(),
            new WAA_Tool_Search_Themes(),
            new WAA_Tool_List_Users(),
            new WAA_Tool_List_Posts(),
            new WAA_Tool_Create_Draft_Post(),
            new WAA_Tool_Navigate(),
            new WAA_Tool_Search_Icon(),
            new WAA_Tool_Get_WooCommerce_Status(),
            new WAA_Tool_List_WooCommerce_Products(),
            new WAA_Tool_List_WooCommerce_Orders(),
            new WAA_Tool_Fetch_Rss(),
            new WAA_Tool_Wordfence_Get_Settings(),
            new WAA_Tool_Wordfence_Get_Scan_Results(),
        ];
    }

    public function test_every_public_tool_has_the_expected_capability_and_schema(): void {
        $expected_capabilities = [
            'get_site_settings' => 'manage_options',
            'list_plugins' => 'activate_plugins',
            'list_themes' => 'switch_themes',
            'search_themes' => 'install_themes',
            'list_users' => 'list_users',
            'list_posts' => 'edit_posts',
            'create_draft_post' => 'edit_posts',
            'navigate' => 'manage_options',
            'search_icon' => 'manage_options',
            'get_woocommerce_status' => 'manage_options',
            'list_woocommerce_products' => 'manage_woocommerce',
            'list_woocommerce_orders' => 'manage_woocommerce',
            'fetch_rss' => 'manage_options',
            'wordfence_get_settings' => 'manage_options',
            'wordfence_get_scan_results' => 'manage_options',
        ];

        $tools = $this->public_tools();
        $this->assertCount(count($expected_capabilities), $tools);

        foreach ($tools as $tool) {
            $name = $tool->get_name();
            $schema = $tool->get_schema();

            $this->assertArrayHasKey($name, $expected_capabilities, "Unexpected public tool: {$name}");
            $this->assertSame($expected_capabilities[$name], $tool->get_required_capability(), "Wrong capability for {$name}");
            $this->assertSame($name, $schema['name']);
            $this->assertNotSame('', trim($schema['description']), "Missing description for {$name}");
            $this->assertSame('object', $schema['input_schema']['type'] ?? null, "Invalid root schema for {$name}");
            $this->assertArrayHasKey('properties', $schema['input_schema'], "Schema must explicitly declare properties for {$name}");
        }
    }

    public function test_every_public_tool_rejects_unauthorized_and_unknown_input(): void {
        $registry = WAA_REST_API::build_registry();
        $subscriber_id = self::factory()->user->create(['role' => 'subscriber']);
        wp_set_current_user($subscriber_id);

        foreach ($this->public_tools() as $tool) {
            $name = $tool->get_name();
            $denied = $registry->execute($name, []);
            $this->assertSame('Insufficient permissions for this operation.', $denied['error'] ?? null, "Permission gate failed for {$name}");
        }

        $admin_id = self::factory()->user->create(['role' => 'administrator']);
        if (is_multisite()) {
            grant_super_admin($admin_id);
        }
        wp_set_current_user($admin_id);

        foreach ($this->public_tools() as $tool) {
            $name = $tool->get_name();
            $invalid = $tool->validate_input(['unexpected_private_field' => true]);
            $this->assertWPError($invalid, "Unknown input was accepted by {$name}");
            $this->assertSame('tool_input_unknown', $invalid->get_error_code(), "Wrong validation error for {$name}");
        }
    }

    public function test_required_and_bounded_inputs_fail_before_execution(): void {
        foreach ([
            new WAA_Tool_Create_Draft_Post(),
            new WAA_Tool_Navigate(),
            new WAA_Tool_Search_Themes(),
            new WAA_Tool_Search_Icon(),
        ] as $tool) {
            $invalid = $tool->validate_input([]);
            $this->assertWPError($invalid, "Missing required input was accepted by {$tool->get_name()}");
            $this->assertSame('tool_input_required', $invalid->get_error_code());
        }

        $scan_tool = new WAA_Tool_Wordfence_Get_Scan_Results();
        $this->assertSame('tool_input_minimum', $scan_tool->validate_input(['limit' => 0])->get_error_code());
        $this->assertSame('tool_input_maximum', $scan_tool->validate_input(['limit' => 101])->get_error_code());
    }

    public function test_optional_integrations_return_safe_dependency_states(): void {
        if (class_exists('WooCommerce') || class_exists('wfConfig')) {
            $this->markTestSkipped('Dependency-absent error coverage requires the normal CI fixture.');
        }

        $woo_status = (new WAA_Tool_Get_WooCommerce_Status())->execute([]);
        $woo_products = (new WAA_Tool_List_WooCommerce_Products())->execute([]);
        $woo_orders = (new WAA_Tool_List_WooCommerce_Orders())->execute([]);
        $wordfence_settings = (new WAA_Tool_Wordfence_Get_Settings())->execute([]);
        $wordfence_scan = (new WAA_Tool_Wordfence_Get_Scan_Results())->execute([]);

        $this->assertTrue($woo_status['success']);
        $this->assertFalse($woo_status['active']);
        $this->assertFalse($woo_products['success']);
        $this->assertFalse($woo_orders['success']);
        $this->assertFalse($wordfence_settings['success']);
        $this->assertFalse($wordfence_scan['success']);
    }
}
