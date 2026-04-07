<?php
/**
 * Plugin Name: RTADV Call Line Redirect
 * Description: Exact-match redirect for /rtadv-call/ to the LINE call URL.
 * Version: 1.0.0
 * Author: rtadv.com
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	if ( is_admin() || wp_doing_cron() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
		return;
	}

	$request_path = trim( (string) parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ), '/' );
	if ( 'rtadv-call' !== $request_path ) {
		return;
	}

	header( 'HTTP/1.1 301 Moved Permanently' );
	header( 'Location: https://line.me/R/oa/call/@568lyext?confirmation=true&from=call_url' );
	header( 'X-Redirect-By: RTADV-Call-Line' );
	exit;
}, 0 );
