<?php
/**
 * Plugin Name: RTADV SEO Rank Math Author Sitemap Fix
 * Description: Removes author archives from Rank Math sitemap output and noindexes author archives.
 * Author: Codex
 */

if (! defined('ABSPATH')) {
	exit;
}

if (! function_exists('rtadv_seo_rankmath_author_sitemap_fix')) {
	function rtadv_seo_rankmath_author_sitemap_fix() {
		if (! defined('RANK_MATH_VERSION')) {
			return;
		}

		$request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
		$request_path = (string) wp_parse_url($request_uri, PHP_URL_PATH);

		if ('/author-sitemap.xml' === untrailingslashit($request_path) || '/author-sitemap.xml' === $request_path) {
			status_header(410);
			header('Content-Type: application/xml; charset=UTF-8', true);
			header('X-Robots-Tag: noindex, nofollow, noarchive', true);
			echo '<?xml version="1.0" encoding="UTF-8"?><error><message>Gone</message></error>';
			exit;
		}

		if ('/sitemap_index.xml' === untrailingslashit($request_path) || '/sitemap_index.xml' === $request_path) {
			ob_start(
				static function ($buffer) {
					return (string) preg_replace(
						'#<sitemap>\s*<loc>https://www\.rtadv\.com/author-sitemap\.xml</loc>.*?</sitemap>#s',
						'',
						(string) $buffer
					);
				}
			);
		}

		add_filter('rank_math/sitemap/enable_caching', '__return_false');

		add_filter(
			'rank_math/sitemap/author/query',
			static function ($args) {
				$args['include'] = array(0);
				return $args;
			}
		);

		add_filter(
			'rank_math/frontend/robots',
			static function ($robots) {
				if (! is_author()) {
					return $robots;
				}

				$robots['index'] = 'noindex';
				$robots['follow'] = 'follow';
				return $robots;
			}
		);

		add_action(
			'send_headers',
			static function () {
				if (! is_author()) {
					return;
				}

				header('X-Robots-Tag: noindex, follow, noarchive', true);
			}
		);
	}

	add_action('plugins_loaded', 'rtadv_seo_rankmath_author_sitemap_fix', 20);
}
