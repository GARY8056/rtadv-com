<?php
/**
 * Plugin Name: RTADV SEO Audit Endpoint
 * Description: REST API endpoint to audit Rank Math SEO meta coverage
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
    register_rest_route('rtadv/v1', '/seo-audit', [
        'methods'  => 'GET',
        'callback' => 'rtadv_seo_audit_callback',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
        'args' => [
            'page'     => ['default' => 1, 'type' => 'integer'],
            'per_page' => ['default' => 100, 'type' => 'integer'],
            'filter'   => ['default' => 'missing', 'type' => 'string'], // missing | all
        ],
    ]);
});

function rtadv_seo_audit_callback($request) {
    $page     = $request->get_param('page');
    $per_page = min($request->get_param('per_page'), 100);
    $filter   = $request->get_param('filter');

    $args = [
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'orderby'        => 'date',
        'order'          => 'ASC',
    ];

    $query = new WP_Query($args);
    $results = [];
    $missing_count = 0;

    foreach ($query->posts as $post) {
        $focus_kw = get_post_meta($post->ID, 'rank_math_focus_keyword', true);
        $rm_title = get_post_meta($post->ID, 'rank_math_title', true);
        $rm_desc  = get_post_meta($post->ID, 'rank_math_description', true);

        $has_kw    = !empty($focus_kw);
        $has_title = !empty($rm_title);
        $has_desc  = !empty($rm_desc);
        $complete  = $has_kw && $has_title && $has_desc;

        if (!$complete) $missing_count++;

        if ($filter === 'all' || !$complete) {
            $results[] = [
                'id'        => $post->ID,
                'slug'      => $post->post_name,
                'title'     => $post->post_title,
                'has_focus_kw' => $has_kw,
                'has_rm_title' => $has_title,
                'has_rm_desc'  => $has_desc,
                'focus_kw'  => $focus_kw ?: null,
                'rm_title'  => $rm_title ?: null,
                'rm_desc'   => $rm_desc ?: null,
            ];
        }
    }

    return [
        'page'          => $page,
        'per_page'      => $per_page,
        'total_posts'   => (int) $query->found_posts,
        'total_pages'   => (int) $query->max_num_pages,
        'missing_count' => $missing_count,
        'results'       => $results,
    ];
}
