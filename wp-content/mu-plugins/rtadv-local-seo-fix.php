<?php
/**
 * Plugin Name: RTADV Local SEO Schema Fix
 * Description: Fixes PrintShop schema: correct email and phone.
 * Version: 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Method 1: Try Rank Math filter
add_filter( 'rank_math/json_ld', 'rtadv_fix_local_schema_rm', 99, 2 );

function rtadv_fix_local_schema_rm( $data, $jsonld ) {
	foreach ( $data as $key => &$entity ) {
		if ( ! is_array( $entity ) || empty( $entity['@type'] ) ) {
			continue;
		}
		if ( 'PrintShop' === $entity['@type'] || 'LocalBusiness' === $entity['@type'] ) {
			$entity['telephone'] = '+886-2-2245-5586';
			$entity['email']     = 'service@rtadv.com';
		}
	}
	return $data;
}

// Method 2: Output buffer fallback for all pages
add_action( 'wp_loaded', 'rtadv_local_seo_ob_start' );

function rtadv_local_seo_ob_start() {
	if ( is_admin() ) {
		return;
	}
	ob_start( 'rtadv_local_seo_replace' );
}

function rtadv_local_seo_replace( $html ) {
	$html = str_replace( '"+886-2-2228-5138"', '"+886-2-2245-5586"', $html );
	$html = str_replace( '"info@rtadv.com"', '"service@rtadv.com"', $html );
	return $html;
}
