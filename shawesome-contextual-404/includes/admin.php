<?php
/**
 * Admin: settings page under shawesome.
 *
 * @package Shawesome_Contextual_404
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/shawesome-wordpress-admin/loader.php';
require_once __DIR__ . '/statistics.php';

/**
 * Load the bundled translations. Translations from translate.wordpress.org
 * in wp-content/languages/plugins/ take precedence. Runs before the post type
 * is registered, whose labels need them.
 */
function shawesome_c404_load_textdomain() {
	load_plugin_textdomain( 'shawesome-contextual-404', false, dirname( plugin_basename( dirname( __DIR__ ) . '/shawesome-contextual-404.php' ) ) . '/languages' );
}
add_action( 'init', 'shawesome_c404_load_textdomain', 5 );

/**
 * URL of the settings page.
 *
 * @param array $args Query arguments.
 * @return string
 */
function shawesome_c404_page_url( $args = array() ) {
	return add_query_arg( $args, admin_url( 'admin.php?page=shawesome-c404' ) );
}

/**
 * Add the settings page under shawesome. The list of 404 pages has no menu
 * item of its own, it is reached by a tab.
 */
function shawesome_c404_admin_menu() {
	$hook = add_submenu_page(
		'shawesome',
		__( 'Contextual 404', 'shawesome-contextual-404' ),
		__( 'Contextual 404', 'shawesome-contextual-404' ),
		'manage_options',
		'shawesome-c404',
		'shawesome_c404_render_page'
	);

	add_action( 'load-' . $hook, 'shawesome_c404_enqueue_scripts' );
}
add_action( 'admin_menu', 'shawesome_c404_admin_menu' );

/**
 * Load the repeater of the path rules on the settings page.
 */
function shawesome_c404_enqueue_scripts() {
	wp_enqueue_script(
		'shawesome-c404-admin',
		plugins_url( 'assets/admin.js', dirname( __DIR__ ) . '/shawesome-contextual-404.php' ),
		array( 'jquery', 'jquery-ui-sortable' ),
		SHAWESOME_C404_VERSION,
		true
	);
}

/**
 * Whether the current admin screen belongs to the 404 pages.
 *
 * @return bool
 */
function shawesome_c404_is_post_type_screen() {
	$screen = get_current_screen();

	return $screen && SHAWESOME_C404_POST_TYPE === $screen->post_type;
}

/**
 * Keep the shawesome menu open on the screens of the 404 pages.
 *
 * @param string $parent_file Parent menu item.
 * @return string
 */
function shawesome_c404_parent_file( $parent_file ) {
	return shawesome_c404_is_post_type_screen() ? 'shawesome' : $parent_file;
}
add_filter( 'parent_file', 'shawesome_c404_parent_file' );

/**
 * Highlight the menu item of the plugin on the screens of the 404 pages.
 *
 * @param string|null $submenu_file Submenu item.
 * @return string|null
 */
function shawesome_c404_submenu_file( $submenu_file ) {
	return shawesome_c404_is_post_type_screen() ? 'shawesome-c404' : $submenu_file;
}
add_filter( 'submenu_file', 'shawesome_c404_submenu_file' );

/**
 * Render the tabs.
 *
 * @param string $current Key of the current tab.
 */
function shawesome_c404_render_tabs( $current ) {
	$tabs = array(
		'pages'    => array( __( '404 Pages', 'shawesome-contextual-404' ), admin_url( 'edit.php?post_type=' . SHAWESOME_C404_POST_TYPE ) ),
		'settings' => array( __( 'Settings', 'shawesome-contextual-404' ), shawesome_c404_page_url() ),
		'log'      => array( __( '404 Log', 'shawesome-contextual-404' ), shawesome_c404_page_url( array( 'tab' => 'log' ) ) ),
	);
	?>
	<div class="wrap">
		<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Contextual 404', 'shawesome-contextual-404' ); ?>">
			<?php foreach ( $tabs as $key => $tab ) : ?>
				<a href="<?php echo esc_url( $tab[1] ); ?>" class="nav-tab<?php echo $key === $current ? ' nav-tab-active' : ''; ?>"<?php echo $key === $current ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $tab[0] ); ?></a>
			<?php endforeach; ?>
		</nav>
	</div>
	<?php
}

/**
 * Render the tabs above the list of 404 pages.
 */
function shawesome_c404_render_list_tabs() {
	$screen = get_current_screen();

	if ( $screen && 'edit-' . SHAWESOME_C404_POST_TYPE === $screen->id ) {
		shawesome_c404_render_tabs( 'pages' );
	}
}
add_action( 'all_admin_notices', 'shawesome_c404_render_list_tabs' );

/**
 * Link the plugin on the start page of the shawesome menu to the settings page.
 *
 * @param array<string, string> $pages Plugin file => menu slug.
 * @return array<string, string>
 */
function shawesome_c404_admin_pages( $pages ) {
	$pages[ plugin_basename( dirname( __DIR__ ) . '/shawesome-contextual-404.php' ) ] = 'shawesome-c404';

	return $pages;
}
add_filter( 'shawesome_admin_pages', 'shawesome_c404_admin_pages' );

/**
 * Add a "Settings" link to the plugin row in Plugins.
 *
 * @param string[] $links Action links.
 * @return string[]
 */
function shawesome_c404_action_links( $links ) {
	array_unshift( $links, '<a href="' . esc_url( shawesome_c404_page_url() ) . '">' . esc_html__( 'Settings', 'shawesome-contextual-404' ) . '</a>' );

	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( dirname( __DIR__ ) . '/shawesome-contextual-404.php' ), 'shawesome_c404_action_links' );

/**
 * Sanitize a posted path rule.
 *
 * Regular expressions are stored as entered, since sanitize_text_field()
 * would alter them; only invalid UTF-8 and the delimiter are removed.
 *
 * @param array $rule Posted rule.
 * @return array|null Rule, null if it is empty or invalid.
 */
function shawesome_c404_sanitize_path_rule( $rule ) {
	$type    = isset( $rule['type'] ) && 'regex' === $rule['type'] ? 'regex' : 'prefix';
	$pattern = isset( $rule['pattern'] ) ? (string) $rule['pattern'] : '';
	$page    = isset( $rule['page'] ) ? absint( $rule['page'] ) : 0;

	if ( 'regex' === $type ) {
		$pattern = trim( str_replace( array( "\0", "\x01" ), '', wp_check_invalid_utf8( $pattern, true ) ) );
		$valid   = false !== @preg_match( shawesome_c404_regex( $pattern ), '' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	} else {
		$pattern = strtolower( trim( rawurldecode( sanitize_text_field( $pattern ) ), '/' ) );
		$valid   = true;
	}

	if ( '' === $pattern || ! $valid || ! shawesome_c404_is_404_page( $page ) ) {
		return null;
	}

	return array(
		'type'    => $type,
		'pattern' => $pattern,
		'page'    => $page,
	);
}

/**
 * Save the settings (posted to admin-post.php?action=shawesome_c404_save).
 */
function shawesome_c404_save_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage options for this site.', 'shawesome-contextual-404' ) );
	}
	check_admin_referer( 'shawesome_c404_save' );

	// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
	$posted_types = isset( $_POST['post_types'] ) ? (array) wp_unslash( $_POST['post_types'] ) : array();
	$posted_paths = isset( $_POST['paths'] ) ? (array) wp_unslash( $_POST['paths'] ) : array();
	// phpcs:enable

	$post_types = array();
	foreach ( array_keys( shawesome_c404_post_types() ) as $name ) {
		$page = isset( $posted_types[ $name ] ) ? absint( $posted_types[ $name ] ) : 0;
		if ( shawesome_c404_is_404_page( $page ) ) {
			$post_types[ $name ] = $page;
		}
	}

	$paths   = array();
	$invalid = false;
	foreach ( $posted_paths as $posted ) {
		$posted = (array) $posted;
		$rule   = shawesome_c404_sanitize_path_rule( $posted );

		if ( $rule ) {
			$paths[] = $rule;
		} elseif ( ! empty( $posted['pattern'] ) ) {
			$invalid = true;
		}
	}

	$fallback = isset( $_POST['fallback'] ) ? absint( $_POST['fallback'] ) : 0;

	update_option(
		SHAWESOME_C404_OPTION,
		array(
			'post_types' => $post_types,
			'fallback'   => shawesome_c404_is_404_page( $fallback ) ? $fallback : 0,
			'paths'      => $paths,
			'logging'    => ! empty( $_POST['logging'] ),
		)
	);

	wp_safe_redirect( shawesome_c404_page_url( array( 'updated' => $invalid ? 'invalid' : 'settings' ) ) );
	exit;
}
add_action( 'admin_post_shawesome_c404_save', 'shawesome_c404_save_settings' );

/**
 * Delete the statistics (posted to admin-post.php?action=shawesome_c404_clear_log).
 */
function shawesome_c404_clear_statistics() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage options for this site.', 'shawesome-contextual-404' ) );
	}
	check_admin_referer( 'shawesome_c404_clear_log' );

	shawesome_c404_clear_log();

	wp_safe_redirect( shawesome_c404_page_url( array( 'updated' => 'cleared' ) ) );
	exit;
}
add_action( 'admin_post_shawesome_c404_clear_log', 'shawesome_c404_clear_statistics' );

/**
 * Render a selection of the 404 pages.
 *
 * @param string    $name     Field name.
 * @param int       $selected Selected page.
 * @param WP_Post[] $pages    404 pages.
 * @param string    $none     Label of the empty option.
 */
function shawesome_c404_render_select( $name, $selected, $pages, $none ) {
	?>
	<select name="<?php echo esc_attr( $name ); ?>">
		<option value="0"><?php echo esc_html( $none ); ?></option>
		<?php foreach ( $pages as $page ) : ?>
			<option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $selected, $page->ID ); ?>><?php echo esc_html( get_the_title( $page ) ); ?></option>
		<?php endforeach; ?>
	</select>
	<?php
}

/**
 * Render a row of the path rules.
 *
 * @param int|string $index Row number, or a placeholder in the template.
 * @param array      $rule  Path rule.
 * @param WP_Post[]  $pages 404 pages.
 */
function shawesome_c404_render_path_rule( $index, $rule, $pages ) {
	?>
	<tr>
		<td><span class="dashicons dashicons-menu shawesome-c404-handle" style="cursor:move;" title="<?php esc_attr_e( 'Drag to sort', 'shawesome-contextual-404' ); ?>" aria-hidden="true"></span></td>
		<td>
			<select name="paths[<?php echo esc_attr( $index ); ?>][type]" aria-label="<?php esc_attr_e( 'Type', 'shawesome-contextual-404' ); ?>">
				<option value="prefix" <?php selected( $rule['type'], 'prefix' ); ?>><?php esc_html_e( 'Prefix', 'shawesome-contextual-404' ); ?></option>
				<option value="regex" <?php selected( $rule['type'], 'regex' ); ?>><?php esc_html_e( 'Regular expression', 'shawesome-contextual-404' ); ?></option>
			</select>
		</td>
		<td><input type="text" class="regular-text code" name="paths[<?php echo esc_attr( $index ); ?>][pattern]" value="<?php echo esc_attr( $rule['pattern'] ); ?>" aria-label="<?php esc_attr_e( 'Pattern', 'shawesome-contextual-404' ); ?>"></td>
		<td><?php shawesome_c404_render_select( 'paths[' . $index . '][page]', (int) $rule['page'], $pages, __( 'Select', 'shawesome-contextual-404' ) ); ?></td>
		<td>
			<button type="button" class="button button-small shawesome-c404-up" aria-label="<?php esc_attr_e( 'Move up', 'shawesome-contextual-404' ); ?>"><span aria-hidden="true">&uarr;</span></button>
			<button type="button" class="button button-small shawesome-c404-down" aria-label="<?php esc_attr_e( 'Move down', 'shawesome-contextual-404' ); ?>"><span aria-hidden="true">&darr;</span></button>
			<button type="button" class="button button-small shawesome-c404-remove"><?php esc_html_e( 'Remove', 'shawesome-contextual-404' ); ?></button>
		</td>
	</tr>
	<?php
}

/**
 * Render the tab selected with ?tab=.
 */
function shawesome_c404_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

	if ( 'log' === $tab ) {
		shawesome_c404_render_tabs( 'log' );
		shawesome_c404_render_log();
	} else {
		shawesome_c404_render_tabs( 'settings' );
		shawesome_c404_render_settings();
	}
}

/**
 * Render the settings.
 */
function shawesome_c404_render_settings() {
	$settings = shawesome_c404_get_settings();
	$default  = __( 'Default', 'shawesome-contextual-404' );
	$theme    = __( 'Theme 404 page', 'shawesome-contextual-404' );
	$pages    = get_posts(
		array(
			'post_type'   => SHAWESOME_C404_POST_TYPE,
			'post_status' => 'publish',
			'numberposts' => -1,
			'orderby'     => 'title',
			'order'       => 'ASC',
			'lang'        => '', // Polylang: every language, translations are resolved on output.
		)
	);

	$empty_rule = array(
		'type'    => 'prefix',
		'pattern' => '',
		'page'    => 0,
	);
	$rules      = $settings['paths'] ? $settings['paths'] : array( $empty_rule );

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
	$updated = isset( $_GET['updated'] ) ? sanitize_key( wp_unslash( $_GET['updated'] ) ) : '';
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Contextual 404', 'shawesome-contextual-404' ); ?></h1>

		<?php if ( 'settings' === $updated ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'shawesome-contextual-404' ); ?></p></div>
		<?php elseif ( 'invalid' === $updated ) : ?>
			<div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'Settings saved. Path rules with an invalid regular expression or without a 404 page were removed.', 'shawesome-contextual-404' ); ?></p></div>
		<?php elseif ( 'cleared' === $updated ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Statistics deleted.', 'shawesome-contextual-404' ); ?></p></div>
		<?php endif; ?>

		<?php if ( ! $pages ) : ?>
			<div class="notice notice-info inline"><p>
				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . SHAWESOME_C404_POST_TYPE ) ); ?>"><?php esc_html_e( 'Create your first 404 page', 'shawesome-contextual-404' ); ?></a>
			</p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php?action=shawesome_c404_save' ) ); ?>">
			<?php wp_nonce_field( 'shawesome_c404_save' ); ?>

			<h2><?php esc_html_e( 'Post types', 'shawesome-contextual-404' ); ?></h2>
			<p><?php esc_html_e( 'Pages match URLs below an existing page, post types match their URL prefix, their archive and their taxonomies.', 'shawesome-contextual-404' ); ?></p>
			<table class="form-table" role="presentation">
				<?php foreach ( shawesome_c404_post_types() as $name => $object ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $object->labels->name ); ?></th>
						<td>
							<?php
							shawesome_c404_render_select(
								'post_types[' . $name . ']',
								isset( $settings['post_types'][ $name ] ) ? (int) $settings['post_types'][ $name ] : 0,
								$pages,
								__( 'Use default', 'shawesome-contextual-404' )
							);
							?>
						</td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<th scope="row"><?php echo esc_html( $default ); ?></th>
					<td>
						<?php shawesome_c404_render_select( 'fallback', (int) $settings['fallback'], $pages, $theme ); ?>
						<p class="description"><?php esc_html_e( 'With the permalink structure /%postname%/, a URL such as /xyz does not reveal whether a post or a page was requested, so it ends up here.', 'shawesome-contextual-404' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Path rules', 'shawesome-contextual-404' ); ?></h2>
			<p><?php esc_html_e( 'Path rules come first, the first match wins. A prefix such as shop/sale matches this path and everything below it. A regular expression is entered without delimiters, ignores case and is matched against the path with a leading slash, e.g. ^/(sale|offers)/. The language prefix is not part of the path.', 'shawesome-contextual-404' ); ?></p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><span class="screen-reader-text"><?php esc_html_e( 'Drag to sort', 'shawesome-contextual-404' ); ?></span></th>
						<th><?php esc_html_e( 'Type', 'shawesome-contextual-404' ); ?></th>
						<th><?php esc_html_e( 'Pattern', 'shawesome-contextual-404' ); ?></th>
						<th><?php esc_html_e( '404 page', 'shawesome-contextual-404' ); ?></th>
						<th><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'shawesome-contextual-404' ); ?></span></th>
					</tr>
				</thead>
				<tbody id="shawesome-c404-rules">
					<?php
					foreach ( $rules as $index => $rule ) {
						shawesome_c404_render_path_rule( $index, $rule, $pages );
					}
					?>
				</tbody>
			</table>
			<template id="shawesome-c404-rule-template">
				<?php shawesome_c404_render_path_rule( '__index__', $empty_rule, $pages ); ?>
			</template>
			<p><button type="button" class="button" id="shawesome-c404-add-rule"><?php esc_html_e( 'Add rule', 'shawesome-contextual-404' ); ?></button></p>

			<h2><?php esc_html_e( 'Statistics', 'shawesome-contextual-404' ); ?></h2>
			<p><label><input type="checkbox" name="logging" value="1" <?php checked( $settings['logging'] ); ?>> <?php esc_html_e( 'Count 404 errors', 'shawesome-contextual-404' ); ?></label></p>

			<?php submit_button(); ?>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php?action=shawesome_c404_clear_log' ) ); ?>">
			<?php wp_nonce_field( 'shawesome_c404_clear_log' ); ?>
			<p class="description"><?php esc_html_e( 'Deletes the counted 404 errors of all months. This cannot be undone.', 'shawesome-contextual-404' ); ?></p>
			<p><button type="submit" class="button" onclick="return confirm( '<?php echo esc_js( __( 'Delete the statistics of all months? This cannot be undone.', 'shawesome-contextual-404' ) ); ?>' );"><?php esc_html_e( 'Delete statistics', 'shawesome-contextual-404' ); ?></button></p>
		</form>
	</div>
	<?php
}
