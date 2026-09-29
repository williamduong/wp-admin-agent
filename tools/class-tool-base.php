<?php

defined('ABSPATH') || exit;

abstract class WRADMIN_Tool_Base {
    abstract public function get_name(): string;
    abstract public function get_description(): string;
    abstract public function get_input_schema(): array;
    abstract public function execute(array $input): array;

    public function check_permission(): bool {
        return current_user_can($this->get_required_capability());
    }

    public function get_required_capability(): string {
        $name = $this->get_name();
        $capability = match (true) {
            $name === 'list_users' => 'list_users',
            $name === 'update_user_role' => 'promote_users',
            $name === 'list_posts' => 'edit_posts',
            $name === 'list_plugins' => 'activate_plugins',
            $name === 'list_themes' => 'switch_themes',
            $name === 'search_themes' => 'install_themes',
            $name === 'get_woocommerce_status' => 'manage_options',
            in_array($name, ['install_plugin', 'install_theme'], true) => str_ends_with($name, 'plugin') ? 'install_plugins' : 'install_themes',
            in_array($name, ['activate_plugin', 'deactivate_plugin'], true) => 'activate_plugins',
            $name === 'switch_theme' => 'switch_themes',
            in_array($name, ['create_draft_post', 'create_post', 'create_rich_post', 'create_simple_post', 'update_post'], true) => 'edit_posts',
            in_array($name, ['generate_image', 'resolve_image', 'set_post_image', 'search_images'], true) => 'upload_files',
            str_contains($name, 'woocommerce_') => 'manage_woocommerce',
            default => 'manage_options',
        };

        return (string) apply_filters('wradmin_tool_required_capability', $capability, $name, $this);
    }

    public function validate_input(array $input): array|WP_Error {
        $error = $this->validate_schema_value($input, $this->get_input_schema(), 'input', 0);
        if (is_wp_error($error)) {
            return $error;
        }
        return $input;
    }

    private function validate_schema_value(mixed $value, array $schema, string $path, int $depth): true|WP_Error {
        if ($depth > 6) {
            return new WP_Error('tool_input_depth', "$path exceeds the maximum nesting depth.");
        }

        $type = $schema['type'] ?? null;
        $valid_type = match ($type) {
            'object' => is_array($value) && ($value === [] || !array_is_list($value)),
            'array' => is_array($value) && array_is_list($value),
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            null => true,
            default => false,
        };
        if (!$valid_type) {
            return new WP_Error('tool_input_type', "$path must be of type $type.");
        }

        if (isset($schema['enum']) && !in_array($value, (array) $schema['enum'], true)) {
            return new WP_Error('tool_input_enum', "$path is not an allowed value.");
        }

        if ($type === 'string') {
            $minimum = max(0, (int) ($schema['minLength'] ?? 0));
            $limit = min(10000, (int) ($schema['maxLength'] ?? 10000));
            if (strlen($value) < $minimum) {
                return new WP_Error('tool_input_length', "$path is shorter than the minimum length.");
            }
            if (strlen($value) > $limit) {
                return new WP_Error('tool_input_length', "$path exceeds the maximum length.");
            }
        }

        if ($type === 'integer' || $type === 'number') {
            if (isset($schema['minimum']) && $value < $schema['minimum']) {
                return new WP_Error('tool_input_minimum', "$path is below the minimum value.");
            }
            if (isset($schema['maximum']) && $value > $schema['maximum']) {
                return new WP_Error('tool_input_maximum', "$path exceeds the maximum value.");
            }
        }

        if ($type === 'array') {
            $max_items = min(100, (int) ($schema['maxItems'] ?? 100));
            if (count($value) > $max_items) {
                return new WP_Error('tool_input_items', "$path contains too many items.");
            }
            if (isset($schema['items']) && is_array($schema['items'])) {
                foreach ($value as $index => $item) {
                    $error = $this->validate_schema_value($item, $schema['items'], "$path.$index", $depth + 1);
                    if (is_wp_error($error)) return $error;
                }
            }
        }

        if ($type === 'object') {
            foreach ((array) ($schema['required'] ?? []) as $required) {
                if (!array_key_exists($required, $value)) {
                    return new WP_Error('tool_input_required', "$path.$required is required.");
                }
            }
            $has_properties = array_key_exists('properties', $schema);
            $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
            if ($has_properties) {
                foreach ($value as $key => $item) {
                    if (!isset($properties[$key])) {
                        return new WP_Error('tool_input_unknown', "$path.$key is not allowed.");
                    }
                    $error = $this->validate_schema_value($item, $properties[$key], "$path.$key", $depth + 1);
                    if (is_wp_error($error)) return $error;
                }
            }
        }

        return true;
    }

    final public function get_schema(): array {
        return [
            'name'         => $this->get_name(),
            'description'  => $this->get_description(),
            'input_schema' => $this->get_input_schema(),
        ];
    }
}
