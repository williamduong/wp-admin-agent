<?php

defined('ABSPATH') || exit;

class WAA_Encryptor {
    private const LEGACY_CIPHER = 'AES-256-CBC';
    private const CIPHER = 'aes-256-gcm';
    private const PREFIX = 'v2:';

    public function encrypt(string $value): string {
        $iv = random_bytes(12);
        $tag = '';
        $encrypted = openssl_encrypt($value, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($encrypted === false || strlen($tag) !== 16) {
            throw new RuntimeException('Could not encrypt sensitive data.');
        }
        return self::PREFIX . base64_encode($iv . $tag . $encrypted);
    }

    public function decrypt(string $encoded): string {
        if (str_starts_with($encoded, self::PREFIX)) {
            $raw = base64_decode(substr($encoded, strlen(self::PREFIX)), strict: true);
            if ($raw === false || strlen($raw) < 29) return '';
            $iv = substr($raw, 0, 12);
            $tag = substr($raw, 12, 16);
            $encrypted = substr($raw, 28);
            $result = openssl_decrypt($encrypted, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv, $tag);
            return $result !== false ? $result : '';
        }

        // Backward compatibility for values stored by releases before v0.4.0.
        $raw = base64_decode($encoded, strict: true);
        if ($raw === false || strlen($raw) < 17) return '';
        $iv        = substr($raw, 0, 16);
        $encrypted = substr($raw, 16);
        $result    = openssl_decrypt($encrypted, self::LEGACY_CIPHER, $this->legacy_key(), OPENSSL_RAW_DATA, $iv);
        return $result !== false ? $result : '';
    }

    private function key(): string {
        $base_key = $this->legacy_key();
        $salt = defined('SECURE_AUTH_SALT') ? SECURE_AUTH_SALT : wp_salt('secure_auth');
        return hash_hmac('sha256', $base_key, $salt, true);
    }

    private function legacy_key(): string {
        return defined('AUTH_KEY') ? AUTH_KEY : wp_salt('auth');
    }
}
