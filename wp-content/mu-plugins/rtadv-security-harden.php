<?php
/**
 * Plugin Name: RTADV Security Hardening
 * Description: Disables public user enumeration via REST API and author archives.
 * Author: Codex
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Block unauthenticated access to /wp-json/wp/v2/users
 */
add_filter('rest_endpoints', function ($endpoints) {
	if (! is_user_logged_in()) {
		if (isset($endpoints['/wp/v2/users'])) {
			unset($endpoints['/wp/v2/users']);
		}
		if (isset($endpoints['/wp/v2/users/(?P<id>[\\d]+)'])) {
			unset($endpoints['/wp/v2/users/(?P<id>[\\d]+)']);
		}
	}
	return $endpoints;
});

/**
 * Block author archive enumeration (?author=1)
 */
add_action('template_redirect', function () {
	if (is_author() && ! is_user_logged_in()) {
		wp_safe_redirect(home_url('/'), 301);
		exit;
	}
});
