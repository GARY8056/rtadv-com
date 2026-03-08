<?php
/**
 * Plugin Name: RTADV SEO Article Schema Enhancer
 * Description: Adds breadcrumb and FAQ structured data to article posts without touching Divi content.
 * Author: Codex
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'rank_math/json_ld', 'rtadv_seo_enhance_article_schema', 20, 2 );

function rtadv_seo_enhance_article_schema( $data, $jsonld ) {
	if ( ! is_singular( 'post' ) || ! is_array( $data ) ) {
		return $data;
	}

	$post = get_queried_object();
	if ( ! ( $post instanceof WP_Post ) ) {
		return $data;
	}

	if ( rtadv_seo_is_divi_built_post( $post ) ) {
		return $data;
	}

	if ( ! rtadv_seo_schema_has_type( $data, 'BreadcrumbList' ) ) {
		$data['rtadvBreadcrumb'] = rtadv_seo_build_breadcrumb_schema( $post );
	}

	if ( ! rtadv_seo_schema_has_type( $data, 'FAQPage' ) ) {
		$faq_schema = rtadv_seo_build_faq_schema( $post );
		if ( ! empty( $faq_schema ) ) {
			$data['rtadvFaq'] = $faq_schema;
		}
	}

	foreach ( $data as $key => $entity ) {
		if ( ! is_array( $entity ) ) {
			continue;
		}

		$types = isset( $entity['@type'] ) ? (array) $entity['@type'] : array();
		if ( ! array_intersect( array( 'Article', 'BlogPosting', 'NewsArticle' ), $types ) ) {
			continue;
		}

		$data[ $key ] = rtadv_seo_enrich_article_entity( $entity, $post );
	}

	return $data;
}

function rtadv_seo_is_divi_built_post( WP_Post $post ) {
	$uses_builder = get_post_meta( $post->ID, '_et_pb_use_builder', true );
	if ( 'on' === $uses_builder || '1' === (string) $uses_builder ) {
		return true;
	}

	return false !== strpos( (string) $post->post_content, '[et_pb_' );
}

function rtadv_seo_schema_has_type( array $data, $wanted_type ) {
	foreach ( $data as $entity ) {
		if ( ! is_array( $entity ) || empty( $entity['@type'] ) ) {
			continue;
		}

		$types = (array) $entity['@type'];
		if ( in_array( $wanted_type, $types, true ) ) {
			return true;
		}
	}

	return false;
}

function rtadv_seo_build_breadcrumb_schema( WP_Post $post ) {
	$items = array(
		array(
			'@type'    => 'ListItem',
			'position' => 1,
			'name'     => html_entity_decode( wp_strip_all_tags( get_bloginfo( 'name' ) ), ENT_QUOTES, 'UTF-8' ),
			'item'     => home_url( '/' ),
		),
	);

	$categories = get_the_category( $post->ID );
	if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
		$primary_category = $categories[0];
		$items[]          = array(
			'@type'    => 'ListItem',
			'position' => 2,
			'name'     => html_entity_decode( wp_strip_all_tags( $primary_category->name ), ENT_QUOTES, 'UTF-8' ),
			'item'     => get_category_link( $primary_category->term_id ),
		);
	}

	$items[] = array(
		'@type'    => 'ListItem',
		'position' => count( $items ) + 1,
		'name'     => html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES, 'UTF-8' ),
		'item'     => get_permalink( $post ),
	);

	return array(
		'@type'           => 'BreadcrumbList',
		'@id'             => trailingslashit( get_permalink( $post ) ) . '#breadcrumb',
		'itemListElement' => $items,
	);
}

function rtadv_seo_enrich_article_entity( array $entity, WP_Post $post ) {
	$content = wp_strip_all_tags( (string) $post->post_content );
	$content = trim( preg_replace( '/\s+/u', ' ', $content ) );

	$categories = get_the_category( $post->ID );
	if ( empty( $entity['articleSection'] ) && ! empty( $categories ) && ! is_wp_error( $categories ) ) {
		$entity['articleSection'] = html_entity_decode( wp_strip_all_tags( $categories[0]->name ), ENT_QUOTES, 'UTF-8' );
	}

	$focus_keyword = trim( (string) get_post_meta( $post->ID, 'rank_math_focus_keyword', true ) );
	if ( '' !== $focus_keyword && empty( $entity['keywords'] ) ) {
		$entity['keywords'] = $focus_keyword;
	}

	if ( '' !== $content ) {
		$calculated_count = rtadv_seo_calculate_word_count( $content );
		$current_count    = isset( $entity['wordCount'] ) ? (int) $entity['wordCount'] : 0;
		$has_cjk_content  = 1 === preg_match( '/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]/u', $content );

		if ( 0 === $current_count || ( $has_cjk_content && $current_count < 10 ) ) {
			$entity['wordCount'] = $calculated_count;
		}
	}

	if ( empty( $entity['description'] ) ) {
		$excerpt = get_the_excerpt( $post );
		if ( '' !== trim( $excerpt ) ) {
			$entity['description'] = html_entity_decode( wp_strip_all_tags( $excerpt ), ENT_QUOTES, 'UTF-8' );
		}
	}

	if ( empty( $entity['mainEntityOfPage'] ) ) {
		$entity['mainEntityOfPage'] = array(
			'@id' => trailingslashit( get_permalink( $post ) ) . '#webpage',
		);
	}

	return $entity;
}

function rtadv_seo_build_faq_schema( WP_Post $post ) {
	$faq_items = rtadv_seo_extract_faq_items_from_html( (string) $post->post_content );
	if ( count( $faq_items ) < 2 ) {
		return array();
	}

	return array(
		'@type'      => 'FAQPage',
		'@id'        => trailingslashit( get_permalink( $post ) ) . '#faq',
		'mainEntity' => $faq_items,
	);
}

function rtadv_seo_extract_faq_items_from_html( $html ) {
	$html = trim( (string) $html );
	if ( '' === $html ) {
		return array();
	}

	if ( ! class_exists( 'DOMDocument' ) ) {
		return array();
	}

	$wrapped = '<!DOCTYPE html><html><body>' . $html . '</body></html>';
	$dom     = new DOMDocument();

	libxml_use_internal_errors( true );
	$loaded = $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $wrapped );
	libxml_clear_errors();

	if ( ! $loaded ) {
		return array();
	}

	$body = $dom->getElementsByTagName( 'body' )->item( 0 );
	if ( ! $body ) {
		return array();
	}

	$faq_items       = array();
	$inside_faq_zone = false;
	$current_name    = '';
	$current_answer  = array();

	foreach ( $body->childNodes as $node ) {
		if ( ! ( $node instanceof DOMElement ) ) {
			continue;
		}

		$tag  = strtolower( $node->tagName );
		$text = rtadv_seo_normalize_schema_text( $node->textContent );

		if ( in_array( $tag, array( 'h2', 'h3', 'h4' ), true ) && preg_match( '/faq|常見問題/u', $text ) ) {
			$inside_faq_zone = true;
			$current_name    = '';
			$current_answer  = array();
			continue;
		}

		if ( ! $inside_faq_zone ) {
			continue;
		}

		if ( 'h2' === $tag && ! preg_match( '/faq|常見問題/u', $text ) ) {
			break;
		}

		if ( in_array( $tag, array( 'h3', 'h4' ), true ) ) {
			if ( '' !== $current_name && ! empty( $current_answer ) ) {
				$faq_items[] = rtadv_seo_format_faq_item( $current_name, $current_answer );
			}

			$current_name   = $text;
			$current_answer = array();
			continue;
		}

		if ( '' === $current_name ) {
			continue;
		}

		if ( in_array( $tag, array( 'p', 'ul', 'ol', 'table', 'blockquote' ), true ) ) {
			$answer_text = rtadv_seo_normalize_schema_text( $node->textContent );
			if ( '' !== $answer_text ) {
				$current_answer[] = $answer_text;
			}
		}
	}

	if ( '' !== $current_name && ! empty( $current_answer ) ) {
		$faq_items[] = rtadv_seo_format_faq_item( $current_name, $current_answer );
	}

	return array_values( array_filter( $faq_items ) );
}

function rtadv_seo_format_faq_item( $question, array $answer_parts ) {
	$question = trim( preg_replace( '/^Q\d*[\s:：.-]*/u', '', (string) $question ) );
	$answer   = trim( implode( "\n", array_filter( $answer_parts ) ) );

	if ( '' === $question || '' === $answer ) {
		return array();
	}

	return array(
		'@type'          => 'Question',
		'name'           => $question,
		'acceptedAnswer' => array(
			'@type' => 'Answer',
			'text'  => $answer,
		),
	);
}

function rtadv_seo_normalize_schema_text( $text ) {
	$text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' );
	$text = preg_replace( '/\s+/u', ' ', $text );

	return trim( (string) $text );
}

function rtadv_seo_calculate_word_count( $content ) {
	$content = trim( (string) $content );
	if ( '' === $content ) {
		return 0;
	}

	if ( preg_match_all( '/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]|[A-Za-z0-9_-]+/u', $content, $matches ) ) {
		return count( $matches[0] );
	}

	return str_word_count( $content );
}
