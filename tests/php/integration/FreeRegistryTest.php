<?php

class FreeRegistryTest extends WP_UnitTestCase {
    public function test_free_registry_exposes_safe_core_tools(): void {
        $names = array_column(WRADMIN_REST_API::build_registry()->get_schemas(), 'name');

        $this->assertContains('get_site_settings', $names);
        $this->assertContains('list_plugins', $names);
        $this->assertContains('create_draft_post', $names);

        if (!defined('WRADMIN_PRO_VERSION')) {
            $this->assertCount(15, $names);
            $this->assertNotContains('install_plugin', $names);
            $this->assertNotContains('update_user_role', $names);
            $this->assertNotContains('update_woocommerce_order_status', $names);
        }
    }

    public function test_free_content_tool_always_creates_a_draft(): void {
        $user_id = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);

        $tool = new WRADMIN_Tool_Create_Draft_Post();
        $result = $tool->execute([
            'title' => 'Free tool draft test',
            'content' => '<p>Draft body.</p>',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('draft', get_post_status($result['post_id']));
    }
}
