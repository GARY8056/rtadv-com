<?php
/**
 * Plugin Name: RTADV SEO Noindex Private Endpoints
 * Description: Adds noindex headers to private, admin, REST, and tool endpoints that should not enter Google's index.
 * Author: Codex
 */

if (! defined('ABSPATH')) {
	exit;
}

if (! function_exists('rtadv_seo_noindex_private_endpoints')) {
	function rtadv_seo_noindex_private_endpoints() {
		$request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
		$request_path = (string) wp_parse_url($request_uri, PHP_URL_PATH);
		$tool_path = (string) wp_parse_url(home_url('/rtadv-pruning-tool/'), PHP_URL_PATH);
		$page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

		$is_rest = defined('REST_REQUEST') && REST_REQUEST;
		$is_pruning_tool = untrailingslashit($request_path) === untrailingslashit($tool_path)
			|| (isset($_GET['rtadv_pruning_tool']) && '1' === (string) wp_unslash($_GET['rtadv_pruning_tool']))
			|| in_array($page, array('rtadv-pruning-tool-direct', 'rtadv-content-pruning-sync', 'rtadv-content-pruning'), true);

		if (! $is_rest && ! $is_pruning_tool) {
			return;
		}

		header('X-Robots-Tag: noindex, nofollow, noarchive', true);
	}

	add_action('send_headers', 'rtadv_seo_noindex_private_endpoints');
}
