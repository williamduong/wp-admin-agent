<?php

defined('ABSPATH') || exit;

class WRADMIN_Rate_Limiter {
    public function check(): bool {
        $user_id = get_current_user_id();
        $key     = "wradmin_rate_{$user_id}";
        $count   = (int) get_transient($key);

        if ($count >= WRADMIN_RATE_LIMIT) return false;

        set_transient($key, $count + 1, MINUTE_IN_SECONDS);
        return true;
    }
}
