<?php
/**
 * Multilingual plugins: Polylang and WPML. WPML reads the post type from
 * wpml-config.xml.
 *
 * @package Shawesome_Contextual_404
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Current language as used in URLs.
 *
 * @return string Language slug, empty without a multilingual plugin.
 */
function shawesome_c404_current_language() {
	if ( function_exists( 'pll_current_language' ) ) {
		return (string) pll_current_language();
	}

	return (string) apply_filters( 'wpml_current_language', '' );
}

/**
 * Translation of a 404 page into the current language.
 *
 * @param int $page_id 404 page.
 * @return int The published translation, otherwise the page itself.
 */
function shawesome_c404_translate( $page_id ) {
	if ( function_exists( 'pll_get_post' ) ) {
		$translated = (int) pll_get_post( $page_id );
	} else {
		$translated = (int) apply_filters( 'wpml_object_id', $page_id, SHAWESOME_C404_POST_TYPE, true );
	}

	return 'publish' === get_post_status( $translated ) ? $translated : $page_id;
}

/**
 * Make the 404 pages translatable in Polylang.
 *
 * @param string[] $post_types Translatable post types.
 * @return string[]
 */
function shawesome_c404_polylang_post_types( $post_types ) {
	$post_types[ SHAWESOME_C404_POST_TYPE ] = SHAWESOME_C404_POST_TYPE;

	return $post_types;
}
add_filter( 'pll_get_post_types', 'shawesome_c404_polylang_post_types' );

/**
 * Let the Polylang language switcher link to the home page of a language, as
 * the URLs of 404 pages are of no use to visitors.
 *
 * @param string $url  Translation URL.
 * @param string $slug Language slug.
 * @return string
 */
function shawesome_c404_polylang_translation_url( $url, $slug ) {
	return is_singular( SHAWESOME_C404_POST_TYPE ) ? pll_home_url( $slug ) : $url;
}
add_filter( 'pll_translation_url', 'shawesome_c404_polylang_translation_url', 10, 2 );

/**
 * Let the WPML language switcher link to the home page of a language.
 *
 * @param array $languages Languages by code.
 * @return array
 */
function shawesome_c404_wpml_languages( $languages ) {
	if ( is_singular( SHAWESOME_C404_POST_TYPE ) ) {
		foreach ( array_keys( (array) $languages ) as $code ) {
			$languages[ $code ]['url'] = apply_filters( 'wpml_permalink', home_url( '/' ), $code );
		}
	}

	return $languages;
}
add_filter( 'icl_ls_languages', 'shawesome_c404_wpml_languages' );
