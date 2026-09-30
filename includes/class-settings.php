<?php

defined('ABSPATH') || exit;

class WRADMIN_Settings {
    private WRADMIN_Encryptor $enc;

    public function __construct() {
        $this->enc = new WRADMIN_Encryptor();
    }

    // --- Provider ---

    public function get_provider(): string {
        return get_option('wradmin_provider', 'anthropic');
    }

    public function set_provider(string $provider): void {
        $allowed = ['anthropic', 'gemini', 'ollama', 'fake'];
        if (in_array($provider, $allowed, true)) {
            update_option('wradmin_provider', $provider);
        }
    }

    // --- Model ---

    public function get_model(): string {
        $defaults = [
            'anthropic' => 'claude-haiku-4-5',
            'gemini'    => 'gemini-2.5-flash',
            'ollama'    => 'qwen2.5:3b',
            'fake'      => 'runtime-v1',
        ];
        return get_option('wradmin_model', $defaults[$this->get_provider()] ?? 'claude-haiku-4-5');
    }

    public function set_model(string $model): void {
        $model = sanitize_text_field($model);
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._:+\/-]{0,119}$/', $model) === 1) {
            update_option('wradmin_model', $model);
        }
    }

    // --- Anthropic ---

    public function get_api_key(): string {
        return $this->get_encrypted_option('wradmin_api_key_enc');
    }

    public function set_api_key(string $key): void {
        update_option('wradmin_api_key_enc', $this->enc->encrypt($key), false);
    }

    // --- Gemini ---

    public function get_gemini_api_key(): string {
        return $this->get_encrypted_option('wradmin_gemini_key_enc');
    }

    public function set_gemini_api_key(string $key): void {
        update_option('wradmin_gemini_key_enc', $this->enc->encrypt($key), false);
    }

    // --- Ollama ---

    public function get_ollama_url(): string {
        return get_option('wradmin_ollama_url', 'http://localhost:11434');
    }

    public function set_ollama_url(string $url): void {
        $validated = WRADMIN_Network_Guard::ollama_url($url);
        if (!is_wp_error($validated)) {
            update_option('wradmin_ollama_url', $validated);
        }
    }

    // --- Custom rules (appended to system prompt) ---

    public function get_custom_rules(): string {
        return get_option('wradmin_custom_rules', '');
    }

    public function set_custom_rules(string $rules): void {
        update_option('wradmin_custom_rules', sanitize_textarea_field($rules));
    }

    public function get_bot_name(): string {
        $name = trim((string) get_option('wradmin_bot_name', 'William Research Admin Agent'));
        return $name !== '' ? $name : 'William Research Admin Agent';
    }

    public function set_bot_name(string $name): void {
        $name = trim(wp_html_excerpt(sanitize_text_field($name), 40, ''));
        update_option('wradmin_bot_name', $name !== '' ? $name : 'William Research Admin Agent', false);
    }

    public function get_user_title(): string {
        return (string) get_option('wradmin_user_title', '');
    }

    public function set_user_title(string $title): void {
        update_option('wradmin_user_title', wp_html_excerpt(sanitize_text_field($title), 40, ''), false);
    }

    public function get_bot_style(): string {
        $style = (string) get_option('wradmin_bot_style', 'friendly');
        return in_array($style, ['friendly', 'concise', 'professional', 'coach'], true) ? $style : 'friendly';
    }

    public function set_bot_style(string $style): void {
        if (in_array($style, ['friendly', 'concise', 'professional', 'coach'], true)) {
            update_option('wradmin_bot_style', $style, false);
        }
    }

    // --- Disabled tools ---

    public function get_disabled_tools(): array {
        return (array) get_option('wradmin_disabled_tools', []);
    }

    public function set_disabled_tools(array $names): void {
        update_option('wradmin_disabled_tools', array_map('sanitize_key', $names));
    }

    // --- Pexels (image search) ---

    public function get_pexels_api_key(): string {
        return $this->get_encrypted_option('wradmin_pexels_key_enc');
    }

    public function set_pexels_api_key(string $key): void {
        update_option('wradmin_pexels_key_enc', $this->enc->encrypt($key), false);
    }

    // --- Debug mode ---

    public function get_debug_mode(): string {
        return get_option('wradmin_debug_mode', 'off');
    }

    public function set_debug_mode(string $mode): void {
        $allowed = ['off', 'compact', 'full'];
        if (in_array($mode, $allowed, true)) {
            update_option('wradmin_debug_mode', $mode);
        }
    }

    public function should_delete_data_on_uninstall(): bool {
        return (bool) get_option('wradmin_delete_data_on_uninstall', false);
    }

    public function set_delete_data_on_uninstall(bool $delete): void {
        update_option('wradmin_delete_data_on_uninstall', $delete, false);
    }

    public function get_data_retention_days(): int {
        return max(7, min(365, (int) get_option('wradmin_data_retention_days', 30)));
    }

    public function set_data_retention_days(int $days): void {
        update_option('wradmin_data_retention_days', max(7, min(365, $days)), false);
    }

    // --- Misc ---

    public function get_max_tokens(): int {
        return (int) get_option('wradmin_max_tokens', 4096);
    }

    public function has_active_credential(): bool {
        return match ($this->get_provider()) {
            'gemini'    => !empty($this->get_gemini_api_key()),
            'ollama'    => true,   // no key needed
            'fake'      => true,
            default     => !empty($this->get_api_key()),
        };
    }

    private function get_encrypted_option(string $option_name): string {
        $encrypted = (string) get_option($option_name, '');
        if ($encrypted === '') {
            return '';
        }
        $decrypted = $this->enc->decrypt($encrypted);
        if ($decrypted !== '' && !str_starts_with($encrypted, 'v2:')) {
            update_option($option_name, $this->enc->encrypt($decrypted), false);
        }
        return $decrypted;
    }
}
