<?php
/**
 * Plugin Name: RTADV Easy TOC Posts Only
 * Description: Forces Easy Table of Contents to render only on blog posts so Divi pages stay untouched.
 * Author: Codex
 */

if (! defined('ABSPATH')) {
	exit;
}

if (! class_exists('RTADV_Easy_TOC_Posts_Only')) {
	final class RTADV_Easy_TOC_Posts_Only {
		public static function boot() {
			add_filter('eztoc_get_option_enabled_post_types', array(__CLASS__, 'force_posts_only'), 20, 3);
			add_filter('eztoc_get_option_auto_insert_post_types', array(__CLASS__, 'force_posts_only'), 20, 3);
			add_filter('eztoc_get_option_sticky-post-types', array(__CLASS__, 'force_posts_only'), 20, 3);

			add_filter('eztoc_maybe_apply_the_content_filter', array(__CLASS__, 'disable_toc_filter_on_non_posts'), 20);
			add_filter('eztoc_do_shortcode', array(__CLASS__, 'allow_only_blog_posts'), 20);
			add_filter('eztoc_shortcode_final_toc_html', array(__CLASS__, 'suppress_non_post_shortcode'), 20);
		}

		public static function force_posts_only($value, $key, $default) {
			if (! self::should_enforce_frontend_rules()) {
				return $value;
			}

			return array('post');
		}

		public static function disable_toc_filter_on_non_posts($apply) {
			if (! self::should_enforce_frontend_rules()) {
				return $apply;
			}

			return self::is_blog_post_request() ? $apply : false;
		}

		public static function allow_only_blog_posts($is_eligible) {
			if (! self::should_enforce_frontend_rules()) {
				return $is_eligible;
			}

			return self::is_blog_post_request();
		}

		public static function suppress_non_post_shortcode($html) {
			if (! self::should_enforce_frontend_rules()) {
				return $html;
			}

			return self::is_blog_post_request() ? $html : '';
		}

		private static function should_enforce_frontend_rules() {
			if (is_admin()) {
				return false;
			}

			if (wp_doing_ajax() || wp_doing_cron()) {
				return false;
			}

			return true;
		}

		private static function is_blog_post_request() {
			$post_id = get_queried_object_id();
			if (! $post_id) {
				$post_id = get_the_ID();
			}

			$post = $post_id ? get_post($post_id) : null;

			return $post instanceof WP_Post && 'post' === $post->post_type;
		}
	}

	RTADV_Easy_TOC_Posts_Only::boot();
}
