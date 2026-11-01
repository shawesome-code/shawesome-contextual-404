<?php
/**
 * Admin: pie chart of the 404 errors, the tab 404 Log and the dashboard
 * widget.
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
 * Slices of the pie chart: at most six, as more cannot be told apart.
 *
 * @param array[] $categories Rows with category and hits, the most frequent first.
 * @return array[] Slices with label, hits and color.
 */
function shawesome_c404_chart_slices( $categories ) {
	$colors = array( '#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300' );
	$others = 6 < count( $categories ) ? array_splice( $categories, 5 ) : array();
	$slices = array();

	foreach ( array_values( $categories ) as $index => $row ) {
		$slices[] = array(
			'label' => shawesome_c404_category_label( $row['category'] ),
			'hits'  => (int) $row['hits'],
			'color' => $colors[ $index ],
		);
	}

	if ( $others ) {
		$slices[] = array(
			'label' => __( 'Other categories', 'shawesome-contextual-404' ),
			'hits'  => (int) array_sum( wp_list_pluck( $others, 'hits' ) ),
			'color' => '#787774',
		);
	}

	return $slices;
}

/**
 * SVG path of a slice of the pie, which starts at 12 o'clock and runs
 * clockwise. A path cannot draw a full circle.
 *
 * @param float $start Start as a share of the circle, from 0 to 1.
 * @param float $end   End as a share of the circle, from 0 to 1.
 * @return string
 */
function shawesome_c404_slice_path( $start, $end ) {
	$point = function ( $share ) {
		$angle = 2 * M_PI * $share - M_PI / 2;

		return sprintf( '%.3F %.3F', 50 + 49.8 * cos( $angle ), 50 + 49.8 * sin( $angle ) );
	};

	return sprintf( 'M 50 50 L %s A 49.8 49.8 0 %d 1 %s Z', $point( $start ), $end - $start > 0.5 ? 1 : 0, $point( $end ) );
}

/**
 * Render the pie chart with its legend. A thin stroke in the color of a slice
 * covers the seam that antialiasing leaves between adjacent slices.
 *
 * @param array[] $categories Rows with category and hits, the most frequent first.
 */
function shawesome_c404_render_chart( $categories ) {
	$total = (int) array_sum( wp_list_pluck( $categories, 'hits' ) );

	if ( ! $total ) {
		echo '<p>' . esc_html__( 'No 404 errors recorded.', 'shawesome-contextual-404' ) . '</p>';
		return;
	}

	$slices = shawesome_c404_chart_slices( $categories );
	$offset = 0;
	/* translators: %s: number of 404 errors */
	$summary = sprintf( __( 'Total: %s', 'shawesome-contextual-404' ), number_format_i18n( $total ) );
	?>
	<div style="display:flex;flex-wrap:wrap;gap:16px;align-items:center;">
		<svg viewBox="0 0 100 100" width="140" height="140" role="img" aria-label="<?php echo esc_attr( $summary ); ?>" style="flex:none;">
			<?php foreach ( $slices as $slice ) : ?>
				<?php $title = $slice['label'] . ': ' . number_format_i18n( $slice['hits'] ); ?>
				<?php if ( 1 === count( $slices ) ) : ?>
					<circle cx="50" cy="50" r="49.8" fill="<?php echo esc_attr( $slice['color'] ); ?>"><title><?php echo esc_html( $title ); ?></title></circle>
				<?php else : ?>
					<?php $end = $offset + $slice['hits'] / $total; ?>
					<path d="<?php echo esc_attr( shawesome_c404_slice_path( $offset, $end ) ); ?>" fill="<?php echo esc_attr( $slice['color'] ); ?>" stroke="<?php echo esc_attr( $slice['color'] ); ?>" stroke-width="0.3" stroke-linejoin="round"><title><?php echo esc_html( $title ); ?></title></path>
					<?php $offset = $end; ?>
				<?php endif; ?>
			<?php endforeach; ?>
		</svg>
		<div style="flex:1;min-width:180px;">
			<ul style="margin:0;">
				<?php foreach ( $slices as $slice ) : ?>
					<li style="display:flex;gap:8px;align-items:center;margin:0 0 4px;">
						<span aria-hidden="true" style="flex:none;width:12px;height:12px;border-radius:2px;background:<?php echo esc_attr( $slice['color'] ); ?>;"></span>
						<span style="flex:1;overflow-wrap:anywhere;"><?php echo esc_html( $slice['label'] ); ?></span>
						<span style="font-variant-numeric:tabular-nums;"><?php echo esc_html( number_format_i18n( $slice['hits'] ) ); ?> (<?php echo esc_html( number_format_i18n( 100 * $slice['hits'] / $total ) ); ?>&nbsp;%)</span>
					</li>
				<?php endforeach; ?>
			</ul>
			<p style="margin:8px 0 0;"><strong><?php echo esc_html( $summary ); ?></strong></p>
		</div>
	</div>
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

	$order    = 'desc' === $order ? 'desc' : 'asc';
	$urls     = shawesome_c404_sort_urls( shawesome_c404_get_urls( $month, 100 ), $orderby, $order );
	$settings = shawesome_c404_get_settings();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( '404 Log', 'shawesome-contextual-404' ); ?></h1>

		<?php if ( ! $settings['logging'] ) : ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'Counting is switched off, so no new 404 errors are recorded.', 'shawesome-contextual-404' ); ?></p></div>
		<?php endif; ?>

		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="shawesome-c404">
			<input type="hidden" name="tab" value="log">
			<input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>">
			<input type="hidden" name="order" value="<?php echo esc_attr( $order ); ?>">
			<label for="shawesome-c404-month"><?php esc_html_e( 'Month', 'shawesome-contextual-404' ); ?></label>
			<select name="month" id="shawesome-c404-month">
				<?php foreach ( $months as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $month, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Show', 'shawesome-contextual-404' ), 'secondary', '', false ); ?>
		</form>

		<div style="margin:16px 0;max-width:600px;">
			<?php shawesome_c404_render_chart( shawesome_c404_get_categories( $month ) ); ?>
		</div>

		<?php if ( $urls ) : ?>
			<h2><?php esc_html_e( 'Most frequent URLs', 'shawesome-contextual-404' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Shows the 100 most frequent URLs. Sorting only changes the order of these URLs.', 'shawesome-contextual-404' ); ?></p>
			<table class="widefat striped">
				<thead>
					<tr>
						<?php
						shawesome_c404_render_sort_header( 'path', __( 'URL', 'shawesome-contextual-404' ), $month, $orderby, $order );
						shawesome_c404_render_sort_header( 'category', __( 'Category', 'shawesome-contextual-404' ), $month, $orderby, $order );
						shawesome_c404_render_sort_header( 'hits', __( 'Hits', 'shawesome-contextual-404' ), $month, $orderby, $order );
						?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $urls as $row ) : ?>
						<tr>
							<td>
								<?php if ( '' === $row['path'] ) : ?>
									<em><?php esc_html_e( 'Other URLs', 'shawesome-contextual-404' ); ?></em>
								<?php else : ?>
									<code><?php echo esc_html( '/' . $row['path'] ); ?></code>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( shawesome_c404_category_label( $row['category'] ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (int) $row['hits'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
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
	<?php shawesome_c404_render_chart( shawesome_c404_get_categories( $month ) ); ?>
	<p><a href="<?php echo esc_url( shawesome_c404_page_url( array( 'tab' => 'log' ) ) ); ?>"><?php esc_html_e( 'View all months', 'shawesome-contextual-404' ); ?></a></p>
	<?php
}
