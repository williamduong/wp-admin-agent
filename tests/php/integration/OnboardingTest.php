<?php

class OnboardingTest extends WP_UnitTestCase {
    public function set_up(): void {
        parent::set_up();
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
    }

    public function test_identity_is_sanitized_and_used_in_the_runtime_prompt(): void {
        $settings = new WRADMIN_Settings();
        $settings->set_bot_name('  Orbit <script>  ');
        $settings->set_user_title('William');
        $settings->set_bot_style('coach');

        $this->assertSame('Orbit', $settings->get_bot_name());
        $this->assertSame('William', $settings->get_user_title());
        $this->assertSame('coach', $settings->get_bot_style());

        $prompt = WRADMIN_Agent::build_system_prompt(
            WRADMIN_Provider_Factory::make($settings),
            WRADMIN_REST_API::build_registry(),
            $settings
        );
        $this->assertStringContainsString('Your name: Orbit', $prompt);
        $this->assertStringContainsString('Address the administrator as: William', $prompt);
        $this->assertStringContainsString('help the user learn', $prompt);
    }

    public function test_setup_tool_checks_allow_only_read_only_registered_tools(): void {
        $request = new WP_REST_Request('POST', '/wp-admin-agent/v1/setup/test-tool');
        $request->set_param('name', 'get_site_settings');
        $this->assertTrue(WRADMIN_Onboarding::test_tool($request)->get_data()['success']);

        $request->set_param('name', 'create_draft_post');
        $this->assertFalse(WRADMIN_Onboarding::test_tool($request)->get_data()['success']);

        (new WRADMIN_Settings())->set_disabled_tools(['get_site_settings']);
        $request->set_param('name', 'get_site_settings');
        $this->assertFalse(WRADMIN_Onboarding::test_tool($request)->get_data()['success']);
    }
}
