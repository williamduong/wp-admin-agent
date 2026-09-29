<?php

defined('ABSPATH') || exit;

class WRADMIN_Provider_Factory {
    public static function make(WRADMIN_Settings $settings): WRADMIN_Provider_Base {
        $provider = $settings->get_provider();

        return match ($provider) {
            'gemini' => new WRADMIN_Provider_Gemini(
                $settings->get_gemini_api_key(),
                $settings->get_model()
            ),
            'fake' => new WRADMIN_Provider_Fake(
                $settings->get_model()
            ),
            'ollama' => new WRADMIN_Provider_Ollama(
                $settings->get_ollama_url(),
                $settings->get_model()
            ),
            default  => new WRADMIN_Provider_Anthropic(
                $settings->get_api_key(),
                $settings->get_model()
            ),
        };
    }

    public static function get_available_models(string $provider): array {
        $models = WRADMIN_Pricing::get_models($provider);
        return array_map(fn($m) => $m['label'], $models);
    }
}
