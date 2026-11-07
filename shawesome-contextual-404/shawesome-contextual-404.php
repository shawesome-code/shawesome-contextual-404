<?php
/**
 * Plugin Name:         shawesome Contextual 404 – Different 404 Pages by Post Type and URL
 * Plugin URI:          https://github.com/shawesome-code/shawesome-contextual-404
 * Description:         Shows a different 404 page depending on the requested URL: by post type, by taxonomy or by your own path rules. 404 pages are edited with the block editor or Elementor.
 * Version:             1.0.0
 * Requires at least:   7.1
 * Requires PHP:        7.4
 * Author:              shawesome
 * Author URI:          https://shawesome.de/
 * License:             GPL-2.0-or-later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:         shawesome-contextual-404
 * Domain Path:         /languages
 *
 * Copyright © shawesome.
 *
 * @package Shawesome_Contextual_404
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SHAWESOME_C404_VERSION', '1.0.0' );
define( 'SHAWESOME_C404_OPTION', 'shawesome_c404_settings' );
define( 'SHAWESOME_C404_POST_TYPE', 'shawesome_404' );
define( 'SHAWESOME_C404_DB_VERSION', '1' );

/**
 * Settings, merged with the defaults.
 *
 * - post_types: 404 page per post type.
 * - fallback: default 404 page, 0 keeps the 404 page of the theme.
 * - paths: rules with type ('prefix' or 'regex'), pattern and page, in order
 *   of precedence.
 * - logging: whether 404 errors are counted.
 *
 * @return array{post_types: array<string, int>, fallback: int, paths: array[], logging: bool}
 */
function shawesome_c404_get_settings() {
	return wp_parse_args(
		(array) get_option( SHAWESOME_C404_OPTION, array() ),
		array(
			'post_types' => array(),
			'fallback'   => 0,
			'paths'      => array(),
			'logging'    => true,
		)
	);
}

/**
 * Public post types a 404 page can be assigned to.
 *
 * @return WP_Post_Type[] Post type objects by name.
 */
function shawesome_c404_post_types() {
	$post_types = get_post_types( array( 'public' => true ), 'objects' );
	unset( $post_types['attachment'] );

	return $post_types;
}

/**
 * Register the post type of the 404 pages.
 *
 * It is not public, which keeps it out of sitemaps and SEO plugins, but
 * queryable, because the previews of the block editor and Elementor need a
 * URL. Visitors get a 404 there, see shawesome_c404_template_redirect().
 */
function shawesome_c404_register_post_type() {
	register_post_type(
		SHAWESOME_C404_POST_TYPE,
		array(
			'labels'              => array(
				'name'          => __( '404 Pages', 'shawesome-contextual-404' ),
				'singular_name' => __( '404 Page', 'shawesome-contextual-404' ),
				'add_new_item'  => __( 'Add 404 Page', 'shawesome-contextual-404' ),
				'edit_item'     => __( 'Edit 404 Page', 'shawesome-contextual-404' ),
				'not_found'     => __( 'No 404 pages found.', 'shawesome-contextual-404' ),
			),
			'public'              => false,
			'publicly_queryable'  => true,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'show_in_rest'        => true,
			'show_in_nav_menus'   => false,
			'capability_type'     => 'page',
			'map_meta_cap'        => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'delete_with_user'    => false,
			'supports'            => array( 'title', 'editor', 'revisions', 'elementor' ),
		)
	);
}
add_action( 'init', 'shawesome_c404_register_post_type' );

require_once __DIR__ . '/includes/compatibility.php';
require_once __DIR__ . '/includes/resolver.php';
require_once __DIR__ . '/includes/log.php';
require_once __DIR__ . '/includes/frontend.php';

if ( is_admin() ) {
	require_once __DIR__ . '/includes/admin.php';
}
