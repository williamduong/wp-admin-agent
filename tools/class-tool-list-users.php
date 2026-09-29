<?php

defined('ABSPATH') || exit;

class WRADMIN_Tool_List_Users extends WRADMIN_Tool_Base {
    public function get_name(): string { return 'list_users'; }

    public function get_description(): string {
        return 'List WordPress user IDs, display names, and roles, optionally filtered by role. Email addresses and login names are intentionally excluded.';
    }

    public function get_input_schema(): array {
        return [
            'type'       => 'object',
            'properties' => [
                'role'   => [
                    'type'        => 'string',
                    'description' => 'Filter by role: administrator, editor, author, contributor, subscriber',
                ],
                'number' => [
                    'type'        => 'integer',
                    'default'     => 20,
                    'description' => 'Max users to return (1–100)',
                ],
            ],
        ];
    }

    public function execute(array $input): array {
        $args = [
            'number' => min((int) ($input['number'] ?? 20), 100),
            'fields' => ['ID', 'display_name'],
        ];

        if (!empty($input['role'])) {
            $args['role'] = sanitize_text_field($input['role']);
        }

        $users = get_users($args);

        return [
            'users' => array_map(function ($u) {
                return [
                    'id'           => $u->ID,
                    'display_name' => $u->display_name,
                    'roles'        => get_userdata($u->ID)->roles,
                ];
            }, $users),
            'total' => count($users),
        ];
    }
}
