<?php

defined('ABSPATH') || exit;

/**
 * Server-side, single-use storage for confirmation-gated tool calls.
 *
 * The browser receives only an opaque action ID. Tool names and arguments are
 * recovered from WordPress storage after the action has been claimed for the
 * same user and conversation.
 */
class WAA_Pending_Action {
    private const OPTION_PREFIX = 'waa_pending_action_';
    private const TTL_SECONDS = 10 * MINUTE_IN_SECONDS;

    public static function create(
        string $tool_name,
        string $tool_use_id,
        array $tool_input,
        int $conversation_id
    ): string {
        $action_id = bin2hex(random_bytes(24));
        $payload = [
            'user_id' => get_current_user_id(),
            'conversation_id' => $conversation_id,
            'tool_name' => $tool_name,
            'tool_use_id' => $tool_use_id,
            'tool_input' => $tool_input,
            'expires_at' => time() + self::TTL_SECONDS,
        ];

        if (!add_option(self::option_name($action_id), $payload, '', false)) {
            throw new RuntimeException('Could not create a pending action. Please try again.');
        }

        return $action_id;
    }

    /**
     * Atomically claims a pending action. A successful claim deletes it before
     * execution so concurrent requests and replays cannot run it twice.
     */
    public static function consume(string $action_id, int $conversation_id): array|WP_Error {
        if (!preg_match('/^[a-f0-9]{48}$/', $action_id)) {
            return new WP_Error('invalid_pending_action', 'The pending action is invalid. Please try again.');
        }

        $option_name = self::option_name($action_id);
        $payload = get_option($option_name, null);
        if (!is_array($payload)) {
            return new WP_Error('missing_pending_action', 'The pending action is unavailable or has already been used.');
        }

        if ((int) ($payload['expires_at'] ?? 0) < time()) {
            delete_option($option_name);
            return new WP_Error('expired_pending_action', 'The pending action has expired. Please request it again.');
        }

        if ((int) ($payload['user_id'] ?? 0) !== get_current_user_id()
            || (int) ($payload['conversation_id'] ?? 0) !== $conversation_id) {
            return new WP_Error('pending_action_mismatch', 'The pending action does not belong to this session.');
        }

        global $wpdb;
        $deleted = $wpdb->delete(
            $wpdb->options,
            ['option_name' => $option_name],
            ['%s']
        );

        if ($deleted !== 1) {
            return new WP_Error('pending_action_claimed', 'The pending action has already been used.');
        }

        wp_cache_delete($option_name, 'options');

        return $payload;
    }

    private static function option_name(string $action_id): string {
        return self::OPTION_PREFIX . $action_id;
    }
}
