<?php
/**
 * Plugin Name: RTADV SEO Slug Fix
 * Description: One-time: updates 6 post slugs to clean English SEO-friendly URLs.
 *              WordPress automatically saves the old slug to _wp_old_slug and
 *              handles 301 redirects for the old URLs.
 * Version: 1.0.0
 * Author: rtadv.com
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'rtadv_seo_slug_fix_run', 20 );

function rtadv_seo_slug_fix_run() {
	if ( get_option( 'rtadv_slug_fix_done_v1' ) ) {
		return;
	}

	$updates = [
		25209 => 'sleeve-box-packaging',
		21720 => 'unboxing-packaging-design',
		21727 => 'packaging-details-customer-loyalty',
		42124 => 'paper-grain-direction',
		42200 => 'ai-packaging-design',
		47608 => 'packaging-testing-methods',
	];

	$log   = [];
	$log[] = '[rtadv-slug-fix] started at ' . date( 'Y-m-d H:i:s' );

	foreach ( $updates as $post_id => $new_slug ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			$log[] = "  ID=$post_id — NOT FOUND, skipped";
			continue;
		}

		$old_slug = $post->post_name;

		// wp_update_post automatically saves old slug to _wp_old_slug
		$result = wp_update_post( [
			'ID'        => $post_id,
			'post_name' => $new_slug,
		], true );

		if ( is_wp_error( $result ) ) {
			$log[] = "  ID=$post_id old=$old_slug — ERROR: " . $result->get_error_message();
		} else {
			$log[] = "  ID=$post_id old=$old_slug → new=$new_slug ✓";
		}
	}

	update_option( 'rtadv_slug_fix_done_v1', true );
	$log[] = '[rtadv-slug-fix] completed';

	$log_file = WP_CONTENT_DIR . '/rtadv-seo-slug-fix.log';
	file_put_contents( $log_file, implode( "\n", $log ) . "\n" );

	@unlink( __FILE__ );
}
