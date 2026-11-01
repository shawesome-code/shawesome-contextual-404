<?php
/**
 * Count 404 errors per month, category and URL.
 *
 * A table with one row per month and URL: repeated errors only raise a
 * counter. Rows older than 12 months are removed. Bots can request any number
 * of different URLs, so that a month holds a limited number of URLs. Beyond
 * it, new URLs only raise a counter of their category, whose row has an empty
 * path.
 *
 * Paths are percent-encoded before they are stored, so that the table cannot
 * contain markup, and the query string with its parameters is left out. All
 * output is escaped as well.
 *
 * @package Shawesome_Contextual_404
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- a counter table of its own, which must not be cached.

/**
 * Name of the table.
 *
 * @return string
 */
function shawesome_c404_log_table() {
	global $wpdb;

	return $wpdb->prefix . 'shawesome_c404_log';
}

/**
 * Create the table. Checked on every request instead of on activation, as
 * activating a plugin for a network does not reach the sites.
 */
function shawesome_c404_maybe_create_log_table() {
	global $wpdb;

	if ( SHAWESOME_C404_DB_VERSION === get_option( 'shawesome_c404_db_version' ) ) {
		return;
	}

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table   = shawesome_c404_log_table();
	$collate = $wpdb->get_charset_collate();

	dbDelta(
		"CREATE TABLE {$table} (
			month mediumint(6) unsigned NOT NULL,
			hash binary(16) NOT NULL,
			category varchar(191) NOT NULL,
			path varchar(255) NOT NULL,
			hits int(10) unsigned NOT NULL,
			PRIMARY KEY  (month,hash)
		) {$collate};"
	);

	update_option( 'shawesome_c404_db_version', SHAWESOME_C404_DB_VERSION );
}
add_action( 'init', 'shawesome_c404_maybe_create_log_table' );

/**
 * The last 12 months, the current one first.
 *
 * @return array<int, string> Month as YYYYMM => label.
 */
function shawesome_c404_months() {
	$month  = new DateTimeImmutable( 'first day of this month', wp_timezone() );
	$months = array();

	for ( $i = 0; $i < 12; $i++ ) {
		$months[ (int) $month->format( 'Ym' ) ] = wp_date( 'F Y', $month->getTimestamp() );

		$month = $month->modify( '-1 month' );
	}

	return $months;
}

/**
 * Percent-encode a path for storage. The result only consists of letters,
 * digits, "-", "_", ".", "~", "%" and "/", whatever was requested.
 *
 * @param string $path Decoded path.
 * @return string At most 255 characters.
 */
function shawesome_c404_encode_path( $path ) {
	return substr( implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) ), 0, 255 );
}

/**
 * Count a 404 error.
 *
 * @param string $path     Requested path.
 * @param string $category Category of the error.
 */
function shawesome_c404_log_request( $path, $category ) {
	global $wpdb;

	$settings = shawesome_c404_get_settings();
	if ( ! $settings['logging'] ) {
		return;
	}

	$table    = shawesome_c404_log_table();
	$month    = (int) wp_date( 'Ym' );
	$path     = shawesome_c404_encode_path( $path );
	$category = mb_substr( $category, 0, 191 );
	$count    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE month = %d', $table, $month ) );

	// The first error of a month removes the month that dropped out.
	if ( 0 === $count ) {
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE month < %d', $table, min( array_keys( shawesome_c404_months() ) ) ) );
	}

	/**
	 * Filters how many rows a month holds before new URLs are only counted
	 * by category.
	 *
	 * @param int $limit Number of rows.
	 */
	if ( $count >= (int) apply_filters( 'shawesome_c404_log_limit', 1000 ) ) {
		$known = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET hits = hits + 1 WHERE month = %d AND hash = UNHEX(%s)',
				$table,
				$month,
				md5( $category . "\n" . $path )
			)
		);

		if ( $known ) {
			return;
		}

		$path = '';
	}

	$wpdb->query(
		$wpdb->prepare(
			'INSERT INTO %i (month, hash, category, path, hits) VALUES (%d, UNHEX(%s), %s, %s, 1) ON DUPLICATE KEY UPDATE hits = hits + 1',
			$table,
			$month,
			md5( $category . "\n" . $path ),
			$category,
			$path
		)
	);
}

/**
 * Delete the statistics of all months.
 */
function shawesome_c404_clear_log() {
	global $wpdb;

	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', shawesome_c404_log_table() ) );
}

/**
 * First month of a statistic. Together with the last month, it covers one
 * month or, as the table can hold a month that dropped out, the last 12.
 *
 * @param int $month Month as YYYYMM, 0 for all months.
 * @return array{0: int, 1: int} First and last month.
 */
function shawesome_c404_month_range( $month ) {
	$months = array_keys( shawesome_c404_months() );

	return $month ? array( $month, $month ) : array( min( $months ), max( $months ) );
}

/**
 * Errors per category, the most frequent first.
 *
 * @param int $month Month as YYYYMM, 0 for all months.
 * @return array[] Rows with category and hits.
 */
function shawesome_c404_get_categories( $month ) {
	global $wpdb;

	list( $first, $last ) = shawesome_c404_month_range( $month );

	return (array) $wpdb->get_results(
		$wpdb->prepare( 'SELECT category, SUM(hits) AS hits FROM %i WHERE month BETWEEN %d AND %d GROUP BY category ORDER BY hits DESC', shawesome_c404_log_table(), $first, $last ),
		ARRAY_A
	);
}

/**
 * Most frequent URLs.
 *
 * @param int $month Month as YYYYMM, 0 for all months.
 * @param int $limit Number of URLs.
 * @return array[] Rows with path, category and hits. An empty path stands for
 *                 URLs beyond the limit of a month.
 */
function shawesome_c404_get_urls( $month, $limit ) {
	global $wpdb;

	list( $first, $last ) = shawesome_c404_month_range( $month );

	return (array) $wpdb->get_results(
		$wpdb->prepare( 'SELECT path, category, SUM(hits) AS hits FROM %i WHERE month BETWEEN %d AND %d GROUP BY path, category ORDER BY hits DESC LIMIT %d', shawesome_c404_log_table(), $first, $last, $limit ),
		ARRAY_A
	);
}
