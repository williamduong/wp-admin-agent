<?php

defined('ABSPATH') || exit;

class WAA_Tool_Create_Draft_Post extends WAA_Tool_Base {
    public function get_name(): string { return 'create_draft_post'; }

    public function get_description(): string {
        return 'Create a WordPress post draft for administrator review. This Free tool never publishes content.';
    }

    public function get_input_schema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string', 'description' => 'Draft title.'],
                'content' => ['type' => 'string', 'description' => 'Draft body as plain text or safe HTML.'],
                'categories' => ['type' => 'array', 'items' => ['type' => 'string']],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['title', 'content'],
        ];
    }

    public function execute(array $input): array {
        $post_id = wp_insert_post([
            'post_title' => sanitize_text_field($input['title']),
            'post_content' => wp_kses_post($input['content']),
            'post_status' => 'draft',
            'post_type' => 'post',
        ], true);

        if (is_wp_error($post_id)) {
            return ['success' => false, 'error' => $post_id->get_error_message()];
        }

        if (!empty($input['categories']) && is_array($input['categories'])) {
            $category_ids = [];
            foreach ($input['categories'] as $name) {
                $name = sanitize_text_field((string) $name);
                $term = term_exists($name, 'category');
                if ($term && !is_wp_error($term)) {
                    $category_ids[] = (int) (is_array($term) ? $term['term_id'] : $term);
                }
            }
            if ($category_ids) {
                wp_set_post_categories($post_id, $category_ids);
            }
        }

        if (!empty($input['tags']) && is_array($input['tags'])) {
            wp_set_post_tags($post_id, array_map('sanitize_text_field', $input['tags']));
        }

        return [
            'success' => true,
            'post_id' => $post_id,
            'status' => 'draft',
            'edit_url' => get_edit_post_link($post_id, 'raw'),
        ];
    }
}
