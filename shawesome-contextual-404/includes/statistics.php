<?php
/**
 * Admin: bar of the 404 errors by category, the tab 404 Log and the
 * dashboard widget.
 *
 * @package Shawesome_Contextual_404
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Label of a category.
 *
 * @param string $category 'default', 'post_type:<name>' or 'rule:<pattern>'.
 * @return string Not escaped.
 */
function shawesome_c404_category_label( $category ) {
	if ( 0 === strpos( $category, 'post_type:' ) ) {
		$name   = substr( $category, 10 );
		$object = get_post_type_object( $name );

		return $object ? $object->labels->name : $name;
	}

	if ( 0 === strpos( $category, 'rule:' ) ) {
		/* translators: %s: pattern of a path rule */
		return sprintf( __( 'Rule: %s', 'shawesome-contextual-404' ), substr( $category, 5 ) );
	}

	return __( 'Default', 'shawesome-contextual-404' );
}

/**
 * Group of a category in the bar: all path rules form one group.
 *
 * @param string $category Category.
 * @return string
 */
function shawesome_c404_category_group( $category ) {
	return 0 === strpos( $category, 'rule:' ) ? 'rule' : $category;
}

/**
 * Segments of the bar, the most frequent first. Default, posts, pages and
 * path rules have fixed colors, other post types get the extra colors; what
 * is left forms "Other categories".
 *
 * @param array[] $categories Rows with category and hits.
 * @return array<string, array{label: string, hits: int, color: string}> Group => segment.
 */
function shawesome_c404_chart_segments( $categories ) {
	$fixed  = array(
		'default'        => '#2271b1',
		'post_type:post' => '#d63638',
		'post_type:page' => '#dba617',
		'rule'           => '#00a32a',
	);
	$extra  = array( '#8a4baf', '#e26f56', '#007c89' );
	$groups = array();

	foreach ( $categories as $row ) {
		$group = shawesome_c404_category_group( $row['category'] );

		$groups[ $group ] = ( isset( $groups[ $group ] ) ? $groups[ $group ] : 0 ) + (int) $row['hits'];
	}

	arsort( $groups );

	$segments = array();
	foreach ( $groups as $group => $hits ) {
		if ( isset( $fixed[ $group ] ) || $extra ) {
			$segments[ $group ] = array(
				'label' => 'rule' === $group ? __( 'Path rule', 'shawesome-contextual-404' ) : shawesome_c404_category_label( $group ),
				'hits'  => $hits,
				'color' => isset( $fixed[ $group ] ) ? $fixed[ $group ] : array_shift( $extra ),
			);
		} else {
			$segments['other'] = array(
				'label' => __( 'Other categories', 'shawesome-contextual-404' ),
				'hits'  => ( isset( $segments['other'] ) ? $segments['other']['hits'] : 0 ) + $hits,
				'color' => '#8c8f94',
			);
		}
	}

	return $segments;
}

/**
 * Render the total, the stacked bar and its legend. The legend carries the
 * numbers, so the bar is hidden from screen readers.
 *
 * @param array[] $categories Rows with category and hits.
 */
function shawesome_c404_render_overview( $categories ) {
	$segments = shawesome_c404_chart_segments( $categories );
	$total    = (int) array_sum( wp_list_pluck( $segments, 'hits' ) );

	if ( ! $total ) {
		echo '<p>' . esc_html__( 'No 404 errors recorded.', 'shawesome-contextual-404' ) . '</p>';
		return;
	}
	?>
	<p class="sc404-total"><strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong> <?php esc_html_e( '404 errors', 'shawesome-contextual-404' ); ?></p>
	<div class="sc404-bar" aria-hidden="true">
		<?php foreach ( $segments as $segment ) : ?>
			<span style="<?php echo esc_attr( sprintf( 'width:%.3F%%;background:%s', 100 * $segment['hits'] / $total, $segment['color'] ) ); ?>"></span>
		<?php endforeach; ?>
	</div>
	<ul class="sc404-legend">
		<?php foreach ( $segments as $segment ) : ?>
			<li>
				<span class="sc404-swatch" style="<?php echo esc_attr( 'background:' . $segment['color'] ); ?>" aria-hidden="true"></span>
				<span><?php echo esc_html( $segment['label'] ); ?></span>
				<span class="sc404-num"><?php echo esc_html( number_format_i18n( $segment['hits'] ) . ' · ' . number_format_i18n( 100 * $segment['hits'] / $total ) . ' %' ); ?></span>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php
}

/**
 * Sort the URLs of the log. This happens here and not in the query, as the
 * table shows the most frequent URLs in any order, and as categories are
 * sorted by their label.
 *
 * @param array[] $urls    Rows with path, category and hits.
 * @param string  $orderby 'path', 'category' or 'hits'.
 * @param string  $order   'asc' or 'desc'.
 * @return array[]
 */
function shawesome_c404_sort_urls( $urls, $orderby, $order ) {
	usort(
		$urls,
		function ( $a, $b ) use ( $orderby, $order ) {
			if ( 'hits' === $orderby ) {
				$result = (int) $a['hits'] - (int) $b['hits'];
			} elseif ( 'category' === $orderby ) {
				$result = strnatcasecmp( shawesome_c404_category_label( $a['category'] ), shawesome_c404_category_label( $b['category'] ) );
			} else {
				$result = strnatcasecmp( $a['path'], $b['path'] );
			}

			return 'desc' === $order ? -$result : $result;
		}
	);

	return $urls;
}

/**
 * Render a sortable column header of the log.
 *
 * @param string $column  Column: 'path', 'category' or 'hits'.
 * @param string $label   Label.
 * @param int    $month   Selected month.
 * @param string $orderby Current sort column.
 * @param string $order   Current sort order.
 */
function shawesome_c404_render_sort_header( $column, $label, $month, $orderby, $order ) {
	$current = $column === $orderby;
	$first   = 'hits' === $column ? 'desc' : 'asc';
	$next    = $current ? ( 'asc' === $order ? 'desc' : 'asc' ) : $first;
	$url     = shawesome_c404_page_url(
		array(
			'tab'     => 'log',
			'month'   => $month,
			'orderby' => $column,
			'order'   => $next,
		)
	);
	?>
	<th scope="col" class="manage-column <?php echo esc_attr( $current ? 'sorted ' . $order : 'sortable ' . $first ); ?>"<?php echo $current ? ' aria-sort="' . esc_attr( 'asc' === $order ? 'ascending' : 'descending' ) . '"' : ''; ?>>
		<a href="<?php echo esc_url( $url ); ?>">
			<span><?php echo esc_html( $label ); ?></span>
			<span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span class="sorting-indicator desc" aria-hidden="true"></span></span>
		</a>
	</th>
	<?php
}

/**
 * Render the tab 404 Log for the month selected with ?month=, by default for
 * all months, and the order selected with ?orderby= and ?order=.
 */
function shawesome_c404_render_log() {
	$months = array( 0 => __( 'All months', 'shawesome-contextual-404' ) ) + shawesome_c404_months();

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
	$month   = isset( $_GET['month'] ) ? absint( $_GET['month'] ) : 0;
	$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '';
	$order   = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : '';
	// phpcs:enable

	if ( ! isset( $months[ $month ] ) ) {
		$month = 0;
	}

	if ( ! in_array( $orderby, array( 'path', 'category', 'hits' ), true ) ) {
		$orderby = 'hits';
		$order   = 'desc';
	}

	$order      = 'desc' === $order ? 'desc' : 'asc';
	$categories = shawesome_c404_get_categories( $month );
	$segments   = shawesome_c404_chart_segments( $categories );
	$urls       = shawesome_c404_sort_urls( shawesome_c404_get_urls( $month, 100 ), $orderby, $order );
	$settings   = shawesome_c404_get_settings();
	?>
	<div class="sc404-section-head">
		<div>
			<h2><?php esc_html_e( '404 Log', 'shawesome-contextual-404' ); ?></h2>
			<p><?php esc_html_e( 'Which category caught the requests that were not found?', 'shawesome-contextual-404' ); ?></p>
		</div>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" id="sc404-month-form">
			<input type="hidden" name="page" value="shawesome-c404">
			<input type="hidden" name="tab" value="log">
			<input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>">
			<input type="hidden" name="order" value="<?php echo esc_attr( $order ); ?>">
			<label class="screen-reader-text" for="sc404-month"><?php esc_html_e( 'Month', 'shawesome-contextual-404' ); ?></label>
			<select name="month" id="sc404-month">
				<?php foreach ( $months as $value => $label ) : ?>
					<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $month, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<noscript><?php submit_button( __( 'Show', 'shawesome-contextual-404' ), 'secondary', '', false ); ?></noscript>
		</form>
	</div>

	<?php if ( ! $settings['logging'] ) : ?>
		<div class="notice notice-warning inline"><p><?php esc_html_e( 'Counting is switched off, so no new 404 errors are recorded.', 'shawesome-contextual-404' ); ?></p></div>
	<?php endif; ?>

	<section class="sc404-card">
		<div class="sc404-card__body">
			<?php shawesome_c404_render_overview( $categories ); ?>
		</div>
	</section>

	<?php if ( $urls ) : ?>
		<section class="sc404-card">
			<?php shawesome_c404_render_card_head( __( 'Most frequent URLs', 'shawesome-contextual-404' ), __( 'The 100 most frequent. Frequent URLs in “Default” are candidates for a path rule.', 'shawesome-contextual-404' ) ); ?>
			<div class="sc404-table-wrap">
				<table class="sc404-urls">
					<thead>
						<tr>
							<?php
							shawesome_c404_render_sort_header( 'path', __( 'URL', 'shawesome-contextual-404' ), $month, $orderby, $order );
							shawesome_c404_render_sort_header( 'category', __( 'Category', 'shawesome-contextual-404' ), $month, $orderby, $order );
							shawesome_c404_render_sort_header( 'hits', __( 'Hits', 'shawesome-contextual-404' ), $month, $orderby, $order );
							?>
							<td><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'shawesome-contextual-404' ); ?></span></td>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $urls as $row ) : ?>
							<?php
							$group = shawesome_c404_category_group( $row['category'] );
							$color = isset( $segments[ $group ] ) ? $segments[ $group ]['color'] : '#8c8f94';
							$path  = '/' . rawurldecode( $row['path'] );
							// Invalid UTF-8 and control characters stay encoded.
							$shown = '' !== wp_check_invalid_utf8( $path ) && ! preg_match( '/[\x00-\x1F\x7F]/', $path ) ? $path : '/' . $row['path'];
							?>
							<tr>
								<td class="sc404-url">
									<?php if ( '' === $row['path'] ) : ?>
										<em><?php esc_html_e( 'Other URLs', 'shawesome-contextual-404' ); ?></em>
									<?php else : ?>
										<code title="<?php echo esc_attr( '/' . $row['path'] ); ?>"><?php echo esc_html( $shown ); ?></code>
									<?php endif; ?>
								</td>
								<td><span class="sc404-swatch sc404-swatch--dot" style="<?php echo esc_attr( 'background:' . $color ); ?>" aria-hidden="true"></span><?php echo esc_html( shawesome_c404_category_label( $row['category'] ) ); ?></td>
								<td class="sc404-num"><?php echo esc_html( number_format_i18n( (int) $row['hits'] ) ); ?></td>
								<td>
									<?php if ( '' !== $row['path'] ) : ?>
										<?php
										$link = shawesome_c404_page_url(
											array(
												'rule' => rawurlencode( (string) strtok( $path, '/' ) ),
												'test' => rawurlencode( $path ),
											)
										);
										/* translators: %s: requested path */
										$label = sprintf( __( 'Add a path rule for %s', 'shawesome-contextual-404' ), $shown );
										?>
										<a href="<?php echo esc_url( $link ); ?>" class="button button-small" aria-label="<?php echo esc_attr( $label ); ?>"><?php esc_html_e( '+ Path rule', 'shawesome-contextual-404' ); ?></a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>
	<?php endif; ?>

	<section class="sc404-card">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php?action=shawesome_c404_clear_log' ) ); ?>" class="sc404-card__foot sc404-clear">
			<?php wp_nonce_field( 'shawesome_c404_clear_log' ); ?>
			<p><?php esc_html_e( 'Deletes the counted 404 errors of all months. This cannot be undone.', 'shawesome-contextual-404' ); ?></p>
			<button type="submit" class="button sc404-button-danger" data-confirm="<?php esc_attr_e( 'Delete the statistics of all months? This cannot be undone.', 'shawesome-contextual-404' ); ?>"><?php esc_html_e( 'Delete statistics', 'shawesome-contextual-404' ); ?></button>
		</form>
	</section>
	<?php
}

/**
 * Add the dashboard widget for administrators, as it belongs to the settings.
 */
function shawesome_c404_add_dashboard_widget() {
	if ( current_user_can( 'manage_options' ) ) {
		wp_add_dashboard_widget( 'shawesome_c404_statistics', __( '404 Errors', 'shawesome-contextual-404' ), 'shawesome_c404_render_dashboard_widget' );
	}
}
add_action( 'wp_dashboard_setup', 'shawesome_c404_add_dashboard_widget' );

/**
 * Render the dashboard widget: the current month.
 */
function shawesome_c404_render_dashboard_widget() {
	$months = shawesome_c404_months();
	$month  = (int) key( $months );
	?>
	<p><strong><?php echo esc_html( $months[ $month ] ); ?></strong></p>
	<?php shawesome_c404_render_overview( shawesome_c404_get_categories( $month ) ); ?>
	<p><a href="<?php echo esc_url( shawesome_c404_page_url( array( 'tab' => 'log' ) ) ); ?>"><?php esc_html_e( 'View all months', 'shawesome-contextual-404' ); ?></a></p>
	<?php
}
