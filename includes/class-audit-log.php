<?php

defined('ABSPATH') || exit;

class WRADMIN_Audit_Log {
    public function write(string $tool, array $params, array $result, array $meta = []): void {
        global $wpdb;
        $wpdb->insert(WRADMIN_TABLE_LOGS, [
            'user_id'       => get_current_user_id(),
            'tool_name'     => $tool,
            'params'        => wp_json_encode(WRADMIN_Data_Sanitizer::sanitize($params)),
            'result'        => wp_json_encode(WRADMIN_Data_Sanitizer::sanitize($result)),
            'status'        => isset($result['error']) ? 'error' : 'success',
            'provider'      => $meta['provider']      ?? '',
            'model'         => $meta['model']         ?? '',
            'input_tokens'  => $meta['input_tokens']  ?? 0,
            'output_tokens' => $meta['output_tokens'] ?? 0,
            'created_at'    => current_time('mysql'),
        ], ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s']);
    }

    public static function cleanup_expired(): void {
        global $wpdb;
        $days = (new WRADMIN_Settings())->get_data_retention_days();
        $cutoff = wp_date('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS), wp_timezone());
        $wpdb->query($wpdb->prepare(
            'DELETE FROM %i WHERE created_at < %s',
            WRADMIN_TABLE_LOGS,
            $cutoff
        ));
        $wpdb->query($wpdb->prepare(
            'DELETE FROM %i WHERE updated_at < %s',
            WRADMIN_TABLE_CONVERSATIONS,
            $cutoff
        ));

        $like = $wpdb->esc_like('wradmin_pending_action_') . '%';
        $pending = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name, option_value FROM %i WHERE option_name LIKE %s",
            $wpdb->options,
            $like
        ), ARRAY_A);
        foreach ($pending as $row) {
            $payload = maybe_unserialize($row['option_value']);
            if (!is_array($payload) || (int) ($payload['expires_at'] ?? 0) < time()) {
                delete_option($row['option_name']);
            }
        }
    }

    public static function get_recent(int $limit = 10): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM %i ORDER BY created_at DESC LIMIT %d",
            WRADMIN_TABLE_LOGS,
            $limit
        ));
    }

    public static function get_stats(string $period = '30'): array {
        global $wpdb;
        $days = (int) $period;

        // Aggregate totals
        $totals = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*)             AS total_calls,
                SUM(input_tokens)    AS total_input,
                SUM(output_tokens)   AS total_output,
                SUM(CASE WHEN status='error' THEN 1 ELSE 0 END) AS total_errors
             FROM %i
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            WRADMIN_TABLE_LOGS,
            $days
        ), ARRAY_A);

        // By model
        $by_model = $wpdb->get_results($wpdb->prepare(
            "SELECT
                provider, model,
                COUNT(*)          AS calls,
                SUM(input_tokens) AS input_tokens,
                SUM(output_tokens) AS output_tokens
             FROM %i
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
               AND model != ''
             GROUP BY provider, model
             ORDER BY calls DESC",
            WRADMIN_TABLE_LOGS,
            $days
        ), ARRAY_A);

        // Per-day (last 7 days)
        $daily = $wpdb->get_results($wpdb->prepare(
            "SELECT
                DATE(created_at) AS day,
                COUNT(*)          AS calls,
                SUM(input_tokens) AS input_tokens,
                SUM(output_tokens) AS output_tokens
             FROM %i
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY DATE(created_at)
             ORDER BY day ASC",
            WRADMIN_TABLE_LOGS
        ),
            ARRAY_A
        );

        // Top tools
        $top_tools = $wpdb->get_results($wpdb->prepare(
            "SELECT tool_name, COUNT(*) AS calls
             FROM %i
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY tool_name
             ORDER BY calls DESC
             LIMIT 10",
            WRADMIN_TABLE_LOGS,
            $days
        ), ARRAY_A);

        // Enrich by_model with cost
        $settings = new WRADMIN_Settings();
        foreach ($by_model as &$row) {
            $row['cost_usd'] = WRADMIN_Pricing::calculate(
                $row['provider'],
                $row['model'],
                (int) $row['input_tokens'],
                (int) $row['output_tokens']
            );
        }
        unset($row);

        $total_cost = array_sum(array_column($by_model, 'cost_usd'));

        return [
            'period_days'  => $days,
            'totals'       => array_map('intval', $totals ?? []),
            'total_cost'   => round($total_cost, 6),
            'by_model'     => $by_model,
            'daily'        => $daily,
            'top_tools'    => $top_tools,
        ];
    }
}
