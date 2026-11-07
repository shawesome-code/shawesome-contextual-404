<?php
/**
 * Remove the settings, the 404 pages and the statistics of every site on
 * uninstall.
 *
 * @package Shawesome_Contextual_404
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove the data of the current site.
 */
function shawesome_c404_uninstall_site() {
	global $wpdb;

	delete_option( 'shawesome_c404_settings' );
	delete_option( 'shawesome_c404_db_version' );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'shawesome_c404_log' ) );

	$pages = get_posts(
		array(
			'post_type'   => 'shawesome_404',
			'post_status' => array( 'publish', 'future', 'draft', 'pending', 'private', 'trash' ),
			'numberposts' => -1,
			'fields'      => 'ids',
			'lang'        => '', // Polylang: every language.
		)
	);

	foreach ( $pages as $page_id ) {
		wp_delete_post( $page_id, true );
	}
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'number' => 0,
			'fields' => 'ids',
		)
	) as $shawesome_c404_site_id ) {
		switch_to_blog( $shawesome_c404_site_id );
		shawesome_c404_uninstall_site();
		restore_current_blog();
	}
} else {
	shawesome_c404_uninstall_site();
}
