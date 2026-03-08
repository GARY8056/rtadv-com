<?php
/**
 * Plugin Name: RTADV SEO H1 Fix
 * Description: Injects missing H1 headings on taxonomy archive pages and special pages
 *              identified in the Ubersuggest no_h1_heading.csv audit.
 *              Targets: /project_category/*, /project_tag/*, /search/, /cart/
 *              Does NOT touch Divi service pages.
 * Author: rtadv.com
 * Version: 1.0.0
 *
 * Pages fixed (from Ubersuggest audit - no_h1_heading.csv):
 *   - /project_category/*  (packaging box type archives)
 *   - /project_tag/*       (project tag archives)
 *   - /search/             (WordPress search results)
 *   - /cart/               (WooCommerce cart)
 *
 * Implementation notes:
 *   - Uses Divi's `et_before_main_content` hook as primary injection point.
 *     Safe to use without modifying any Divi theme options or builder content.
 *   - No Divi settings (et_divi / et_theme_options) are modified.
 *   - All output is escaped with esc_html().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register H1 injection after main query resolves so conditional tags are safe.
 */
add_action( 'wp', 'rtadv_seo_h1_fix_register' );

function rtadv_seo_h1_fix_register() {
	// Taxonomy archives: project_category and project_tag
	if ( is_tax( 'project_category' ) || is_tax( 'project_tag' ) ) {
		add_action( 'et_before_main_content', 'rtadv_seo_h1_taxonomy', 5 );
		return;
	}

	// WordPress search results page
	if ( is_search() ) {
		add_action( 'et_before_main_content', 'rtadv_seo_h1_search', 5 );
		return;
	}

	// WooCommerce cart page
	if ( function_exists( 'is_cart' ) && is_cart() ) {
		add_action( 'et_before_main_content', 'rtadv_seo_h1_cart', 5 );
		return;
	}
}

/**
 * H1 for project_category and project_tag taxonomy archive pages.
 */
function rtadv_seo_h1_taxonomy() {
	$term = get_queried_object();
	if ( ! $term || ! isset( $term->name ) ) {
		return;
	}

	$taxonomy = isset( $term->taxonomy ) ? $term->taxonomy : '';
	if ( 'project_category' === $taxonomy ) {
		$prefix = '作品分類：';
	} elseif ( 'project_tag' === $taxonomy ) {
		$prefix = '作品標籤：';
	} else {
		$prefix = '';
	}

	rtadv_seo_h1_output_styles();
	echo '<div class="rtadv-seo-h1-wrapper rtadv-seo-h1-taxonomy" data-rtadv-seo="h1-fix">';
	echo '<h1 class="rtadv-seo-h1">';
	if ( $prefix ) {
		echo '<span class="rtadv-seo-h1-prefix">' . esc_html( $prefix ) . '</span>';
	}
	echo esc_html( $term->name );
	echo '</h1>';
	echo '</div>' . "\n";
}

/**
 * H1 for the WordPress search results page.
 */
function rtadv_seo_h1_search() {
	$query = get_search_query();
	$title = $query !== '' ? sprintf( '搜尋結果：%s', $query ) : '搜尋結果';

	rtadv_seo_h1_output_styles();
	echo '<div class="rtadv-seo-h1-wrapper rtadv-seo-h1-search" data-rtadv-seo="h1-fix">';
	echo '<h1 class="rtadv-seo-h1">' . esc_html( $title ) . '</h1>';
	echo '</div>' . "\n";
}

/**
 * H1 for the WooCommerce cart page.
 */
function rtadv_seo_h1_cart() {
	rtadv_seo_h1_output_styles();
	echo '<div class="rtadv-seo-h1-wrapper rtadv-seo-h1-cart" data-rtadv-seo="h1-fix">';
	echo '<h1 class="rtadv-seo-h1">購物車</h1>';
	echo '</div>' . "\n";
}

/**
 * Output minimal inline CSS once per page load.
 */
function rtadv_seo_h1_output_styles() {
	static $printed = false;
	if ( $printed ) {
		return;
	}
	$printed = true;

	echo '<style id="rtadv-seo-h1-styles">
.rtadv-seo-h1-wrapper {
    width: 100%;
    text-align: center;
    padding: 40px 20px 10px;
    box-sizing: border-box;
}
.rtadv-seo-h1-wrapper .rtadv-seo-h1 {
    font-size: 2em;
    line-height: 1.3;
    color: #272727;
    margin: 0 auto 0.5em;
    font-weight: 700;
    max-width: 900px;
}
.rtadv-seo-h1-prefix {
    display: block;
    font-size: 0.6em;
    font-weight: 400;
    color: #666;
    margin-bottom: 4px;
    letter-spacing: 0.02em;
}
</style>' . "\n";
}
