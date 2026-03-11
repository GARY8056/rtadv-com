<?php
/**
 * Plugin Name: RTADV Block Video Thumbnails
 * Description: Removes residual Video Thumbnails cron hooks and blocks creation of "Video Thumbnail:" attachments.
 * Version: 1.0.0
 * Author: RTADV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 1) On every request, clear any residual video-thumbnail cron hooks.
 *    Runs once per day via a transient guard to avoid repeated wp_next_scheduled lookups.
 */
add_action( 'init', function () {
	$transient_key = 'rtadv_vt_cron_cleaned';
	if ( get_transient( $transient_key ) ) {
		return;
	}

	$hooks_to_remove = array(
		'video_thumbnails_cron',
		'video_thumbnail_cron',
		'video_thumbnails_scan',
		'video_thumbnail_scan',
		'video_thumbnails_batch',
		'video_thumbnails',
		'video_thumbnail',
	);

	$cron_array = _get_cron_array();
	if ( ! is_array( $cron_array ) ) {
		set_transient( $transient_key, 1, DAY_IN_SECONDS );
		return;
	}

	$dirty = false;
	foreach ( $cron_array as $timestamp => $hooks ) {
		if ( ! is_array( $hooks ) ) {
			continue;
		}
		foreach ( $hooks as $hook => $events ) {
			// Match any hook containing "video" AND "thumb"
			$hook_lower = strtolower( $hook );
			$exact_match = in_array( $hook, $hooks_to_remove, true );
			$pattern_match = ( false !== strpos( $hook_lower, 'video' ) && false !== strpos( $hook_lower, 'thumb' ) );

			if ( $exact_match || $pattern_match ) {
				unset( $cron_array[ $timestamp ][ $hook ] );
				$dirty = true;
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[rtadv-block-video-thumbnails] Removed cron hook: ' . $hook . ' @ ' . $timestamp );
				}
			}
		}

		// Clean up empty timestamp entries.
		if ( empty( $cron_array[ $timestamp ] ) ) {
			unset( $cron_array[ $timestamp ] );
		}
	}

	if ( $dirty ) {
		_set_cron_array( $cron_array );
	}

	set_transient( $transient_key, 1, DAY_IN_SECONDS );
}, 1 );

/**
 * 2) Block creation of any attachment whose title starts with "Video Thumbnail:".
 *    Hooks into wp_insert_attachment_data which filters the post data before INSERT.
 */
add_filter( 'wp_insert_attachment_data', function ( $data, $postarr ) {
	$title = isset( $data['post_title'] ) ? $data['post_title'] : '';

	if ( 0 === strpos( $title, 'Video Thumbnail:' ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[rtadv-block-video-thumbnails] Blocked attachment: ' . $title );
		}
		// Set status to trash so it never appears in the media library.
		$data['post_status'] = 'trash';
		// Clear content to avoid storing the file reference.
		$data['post_content'] = '';
	}

	return $data;
}, 10, 2 );

/**
 * 3) Also intercept at wp_insert_post_data for extra safety
 *    (some plugins insert attachments via wp_insert_post directly).
 */
add_filter( 'wp_insert_post_data', function ( $data, $postarr ) {
	if ( ! isset( $data['post_type'] ) || 'attachment' !== $data['post_type'] ) {
		return $data;
	}

	$title = isset( $data['post_title'] ) ? $data['post_title'] : '';

	if ( 0 === strpos( $title, 'Video Thumbnail:' ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[rtadv-block-video-thumbnails] Blocked attachment (post_data): ' . $title );
		}
		$data['post_status'] = 'trash';
		$data['post_content'] = '';
	}

	return $data;
}, 10, 2 );
