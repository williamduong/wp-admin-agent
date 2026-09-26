<?php

defined('ABSPATH') || exit;

/** Minimizes operational records before they are persisted. */
class WAA_Data_Sanitizer {
    private const REDACTED = '[redacted]';

    public static function sanitize(mixed $value, int $string_limit = 2000, string $key = ''): mixed {
        if ($key !== '' && self::is_sensitive_key($key)) {
            return self::REDACTED;
        }
        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $child_key => $item) {
                $sanitized[$child_key] = self::sanitize($item, $string_limit, (string) $child_key);
            }
            return $sanitized;
        }
        if (is_object($value)) {
            return self::sanitize((array) $value, $string_limit, $key);
        }
        if (!is_string($value)) {
            return $value;
        }

        $value = wp_check_invalid_utf8($value);
        $value = preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', '[redacted-email]', $value) ?? $value;
        $value = preg_replace('/\bBearer\s+[A-Za-z0-9._~+\/-]+=*/i', 'Bearer ' . self::REDACTED, $value) ?? $value;
        $value = preg_replace('/([?&](?:key|api_key|token|secret|password)=)[^&#\s]+/i', '$1' . rawurlencode(self::REDACTED), $value) ?? $value;
        $value = preg_replace('/\b(?:sk-|ghp_|github_pat_|AIza)[A-Za-z0-9_-]{12,}\b/', self::REDACTED, $value) ?? $value;

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($value) > $string_limit ? mb_substr($value, 0, $string_limit) . '…' : $value;
        }
        return strlen($value) > $string_limit ? substr($value, 0, $string_limit) . '…' : $value;
    }

    private static function is_sensitive_key(string $key): bool {
        $normalized = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $key) ?? $key);
        return (bool) preg_match(
            '/(^|_)(password|passwd|pwd|secret|token|api_key|apikey|authorization|cookie|credential|email|billing|payment|customer)(_|$)/',
            $normalized
        );
    }
}
