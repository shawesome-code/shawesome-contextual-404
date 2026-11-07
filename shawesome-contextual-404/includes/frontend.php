<?php
/**
 * Show the 404 page.
 *
 * The main query is replaced by the 404 page, so that themes, the block
 * editor and Elementor render it like any other page. The status stays 404.
 * While the <head> is printed, the query reports a 404 again, so that
 * WordPress and SEO plugins treat the request as the error it is: no
 * canonical URL, no hreflang, no indexing.
 *
 * @package Shawesome_Contextual_404
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Run the redirects of WordPress that depend on is_404(), which is no longer
 * true afterwards, and take them out of 'template_redirect'. They exit if
 * they find a target.
 */
function shawesome_c404_run_core_redirects() {
	$redirects = array(
		'wp_old_slug_redirect'        => 10,
		'redirect_canonical'          => 10,
		'wp_redirect_admin_locations' => 1000,
	);

	foreach ( $redirects as $redirect => $priority ) {
		call_user_func( $redirect );
		remove_action( 'template_redirect', $redirect, $priority );
	}
}

/**
 * Replace the main query by a 404 page.
 *
 * @param int $page_id 404 page.
 * @return bool Whether the page could be loaded.
 */
function shawesome_c404_replace_query( $page_id ) {
	$query = new WP_Query(
		array(
			'p'                => $page_id,
			'post_type'        => SHAWESOME_C404_POST_TYPE,
			'no_found_rows'    => true,
			'suppress_filters' => true, // WPML: the page may be a fallback in another language.
			'lang'             => '', // Polylang: the same.
		)
	);

	if ( ! $query->have_posts() ) {
		return false;
	}

	// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- this is the purpose of the function.
	$GLOBALS['wp_query']     = $query;
	$GLOBALS['wp_the_query'] = $query;
	$GLOBALS['post']         = $query->post;
	// phpcs:enable

	setup_postdata( $query->post );

	return true;
}

/**
 * Let the main query report a 404 or the 404 page.
 *
 * @param bool $is_404 Whether to report a 404.
 */
function shawesome_c404_set_is_404( $is_404 ) {
	global $wp_query;

	$wp_query->is_404      = $is_404;
	$wp_query->is_single   = ! $is_404;
	$wp_query->is_singular = ! $is_404;
}

/**
 * Report a 404.
 */
function shawesome_c404_report_404() {
	shawesome_c404_set_is_404( true );
}

/**
 * Report the 404 page.
 */
function shawesome_c404_report_page() {
	shawesome_c404_set_is_404( false );
}

/**
 * Hide the 404 pages from visitors and show the 404 page of a request.
 *
 * Runs before priority 10, as Elementor decides there, based on
 * is_singular(), whether to load the styles of a page.
 */
function shawesome_c404_template_redirect() {
	global $wp_query;

	if ( is_singular( SHAWESOME_C404_POST_TYPE ) && ! current_user_can( 'edit_post', get_queried_object_id() ) ) {
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	if ( ! is_404() ) {
		return;
	}

	// First, as a request that gets redirected is no 404 to count.
	shawesome_c404_run_core_redirects();

	$path  = shawesome_c404_get_path();
	$match = shawesome_c404_find_match( $path, $wp_query->query, shawesome_c404_get_settings() );

	shawesome_c404_log_request( $path, $match['category'] );

	$page_id = shawesome_c404_get_page_id( $match['page'], $path );

	if ( ! $page_id || ! shawesome_c404_replace_query( $page_id ) ) {
		return;
	}

	// These would announce the URL of the 404 page in the HTTP headers.
	remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
	remove_action( 'template_redirect', 'rest_output_link_header', 11 );

	header( 'X-Robots-Tag: noindex' );
	add_filter( 'wp_robots', 'wp_robots_no_robots' );

	// SEO plugins read the query from the title on, Elementor needs the page
	// while scripts and styles are enqueued, which happens within 'wp_head'.
	add_action( 'wp_head', 'shawesome_c404_report_404', PHP_INT_MIN );
	add_action( 'wp_enqueue_scripts', 'shawesome_c404_report_page', PHP_INT_MIN );
	add_action( 'wp_enqueue_scripts', 'shawesome_c404_report_404', PHP_INT_MAX );
	add_action( 'wp_head', 'shawesome_c404_report_page', PHP_INT_MAX );
}
add_action( 'template_redirect', 'shawesome_c404_template_redirect', 9 );

/**
 * Prefer the page template over the single post template, which usually
 * shows the date and the author. A theme can still add its own
 * single-shawesome_404.php.
 *
 * @param string[] $templates Template hierarchy.
 * @return string[]
 */
function shawesome_c404_template_hierarchy( $templates ) {
	if ( is_singular( SHAWESOME_C404_POST_TYPE ) ) {
		array_unshift( $templates, 'single-' . SHAWESOME_C404_POST_TYPE . '.php', 'page.php' );
	}

	return $templates;
}
add_filter( 'single_template_hierarchy', 'shawesome_c404_template_hierarchy' );
