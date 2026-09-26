<?php

defined('ABSPATH') || exit;

class WAA_Network_Guard {
    private const METADATA_HOSTS = [
        'metadata.google.internal',
        'metadata.google.com',
        '169.254.169.254',
        '100.100.100.200',
    ];

    public static function public_url(string $url): string|WP_Error {
        $url = esc_url_raw(trim($url));
        if ($url === '' || !wp_http_validate_url($url)) {
            return new WP_Error('unsafe_url', 'The URL is invalid or points to a private/reserved network target.');
        }
        if (self::is_metadata_host((string) wp_parse_url($url, PHP_URL_HOST))) {
            return new WP_Error('metadata_url', 'Cloud metadata endpoints are not allowed.');
        }
        return $url;
    }

    /** Ollama may intentionally run on localhost or a private network. */
    public static function ollama_url(string $url): string|WP_Error {
        $url = esc_url_raw(trim($url));
        $parts = wp_parse_url($url);
        if (!is_array($parts)
            || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])) {
            return new WP_Error('invalid_ollama_url', 'Enter an HTTP(S) Ollama base URL without credentials, query parameters, or fragments.');
        }
        if (self::is_metadata_host((string) $parts['host'])) {
            return new WP_Error('metadata_url', 'Cloud metadata endpoints are never allowed as Ollama servers.');
        }
        return untrailingslashit($url);
    }

    private static function is_metadata_host(string $host): bool {
        $host = strtolower(trim($host, '[]'));
        if (in_array($host, self::METADATA_HOSTS, true) || str_ends_with($host, '.metadata.google.internal')) {
            return true;
        }
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($host);
            return $long !== false && (($long & 0xFFFF0000) === ip2long('169.254.0.0'));
        }
        return false;
    }
}
