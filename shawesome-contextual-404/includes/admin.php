<?php
/**
 * Admin: settings page under shawesome, the column of the 404 pages and the
 * URL test.
 *
 * @package Shawesome_Contextual_404
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/statistics.php';

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
 * The shawesome mark as a data URI for the menu. WordPress repaints it to
 * match the admin color scheme.
 *
 * @return string
 */
function shawesome_c404_menu_icon() {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><g fill="#a7aaad"><rect x="5" y="5" width="17" height="17" rx="4.5"/><rect x="26" y="5" width="17" height="17" rx="4.5"/><rect x="5" y="26" width="17" height="17" rx="4.5"/><rect x="28.5" y="28.5" width="17" height="17" rx="4.5"/></g></svg>';

	return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
}

/**
 * Add the settings page to the "shawesome" menu. The menu is created here
 * unless another shawesome plugin already added it (same slug, so all
 * shawesome plugins share it). The list of 404 pages has no menu item of its
 * own, it is reached by a tab.
 */
function shawesome_c404_admin_menu() {
	global $admin_page_hooks;

	$own_menu = empty( $admin_page_hooks['shawesome'] );
	if ( $own_menu ) {
		add_menu_page( 'shawesome', 'shawesome', 'manage_options', 'shawesome', '__return_null', shawesome_c404_menu_icon(), 81 );
	}

	add_submenu_page(
		'shawesome',
		__( 'Contextual 404', 'shawesome-contextual-404' ),
		__( 'Contextual 404', 'shawesome-contextual-404' ),
		'manage_options',
		'shawesome-c404',
		'shawesome_c404_render_page'
	);

	// WordPress repeats a new menu as its first entry; the menu has no page of its own.
	if ( $own_menu ) {
		remove_submenu_page( 'shawesome', 'shawesome' );
	}
}
add_action( 'admin_menu', 'shawesome_c404_admin_menu' );

/**
 * Load the styles on the plugin page, the list of 404 pages and the
 * dashboard, and the script on the plugin page.
 */
function shawesome_c404_enqueue_assets() {
	global $plugin_page;

	$screen  = get_current_screen();
	$is_page = 'shawesome-c404' === $plugin_page;
	$file    = dirname( __DIR__ ) . '/shawesome-contextual-404.php';

	if ( ! $is_page && ! ( $screen && in_array( $screen->id, array( 'edit-' . SHAWESOME_C404_POST_TYPE, 'dashboard' ), true ) ) ) {
		return;
	}

	wp_enqueue_style( 'shawesome-c404-admin', plugins_url( 'includes/admin.css', $file ), array(), SHAWESOME_C404_VERSION );

	if ( $is_page ) {
		wp_enqueue_script( 'shawesome-c404-admin', plugins_url( 'includes/admin.js', $file ), array( 'jquery', 'jquery-ui-sortable' ), SHAWESOME_C404_VERSION, true );
	}
}
add_action( 'admin_enqueue_scripts', 'shawesome_c404_enqueue_assets' );

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
 * Number of 404 pages, without the trash.
 *
 * @return int
 */
function shawesome_c404_count_pages() {
	$counts = (array) wp_count_posts( SHAWESOME_C404_POST_TYPE );

	unset( $counts['trash'], $counts['auto-draft'] );

	return (int) array_sum( $counts );
}

/**
 * Render the header of all tabs: plugin name, version and tabs.
 *
 * @param string $current Key of the current tab.
 */
function shawesome_c404_render_header( $current ) {
	$tabs = array(
		'pages'    => array( __( '404 Pages', 'shawesome-contextual-404' ), admin_url( 'edit.php?post_type=' . SHAWESOME_C404_POST_TYPE ) ),
		'settings' => array( __( 'Settings', 'shawesome-contextual-404' ), shawesome_c404_page_url() ),
		'log'      => array( __( '404 Log', 'shawesome-contextual-404' ), shawesome_c404_page_url( array( 'tab' => 'log' ) ) ),
	);
	?>
	<?php // Same text as the plugin name in the header, so it shares its translation. ?>
	<h1><?php esc_html_e( 'shawesome Contextual 404 – Different 404 Pages by Post Type and URL', 'shawesome-contextual-404' ); ?></h1>
	<?php /* translators: %s: version number of the plugin */ ?>
	<p class="sc404-version"><?php echo esc_html( sprintf( __( 'Version %s', 'shawesome-contextual-404' ), SHAWESOME_C404_VERSION ) ); ?></p>
	<nav class="nav-tab-wrapper sc404-tabs" aria-label="<?php esc_attr_e( 'Contextual 404', 'shawesome-contextual-404' ); ?>">
		<?php foreach ( $tabs as $key => $tab ) : ?>
			<a href="<?php echo esc_url( $tab[1] ); ?>" class="nav-tab<?php echo $key === $current ? ' nav-tab-active' : ''; ?>"<?php echo $key === $current ? ' aria-current="page"' : ''; ?>>
				<?php echo esc_html( $tab[0] ); ?>
				<?php if ( 'pages' === $key ) : ?>
					<span class="sc404-count"><?php echo esc_html( number_format_i18n( shawesome_c404_count_pages() ) ); ?></span>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
	</nav>
	<?php
}

/**
 * Render the head of a card.
 *
 * @param string        $title       Title.
 * @param string        $description Description, may contain links.
 * @param callable|null $actions     Renders buttons on the right.
 */
function shawesome_c404_render_card_head( $title, $description = '', $actions = null ) {
	?>
	<div class="sc404-card__head">
		<div>
			<h2><?php echo esc_html( $title ); ?></h2>
			<?php if ( '' !== $description ) : ?>
				<p><?php echo wp_kses( $description, array( 'a' => array( 'href' => array() ) ) ); ?></p>
			<?php endif; ?>
		</div>
		<?php
		if ( $actions ) {
			call_user_func( $actions );
		}
		?>
	</div>
	<?php
}

/**
 * Render the header and a hint above the list of 404 pages.
 */
function shawesome_c404_render_list_header() {
	$screen = get_current_screen();

	if ( ! $screen || 'edit-' . SHAWESOME_C404_POST_TYPE !== $screen->id ) {
		return;
	}
	?>
	<div class="wrap">
		<?php shawesome_c404_render_header( 'pages' ); ?>
		<p class="sc404-hint">
			<?php
			printf(
				/* translators: %s: link to the tab Settings */
				esc_html__( 'Your own content, shown instead of the 404 page of the theme. Where it applies is set under %s.', 'shawesome-contextual-404' ),
				'<a href="' . esc_url( shawesome_c404_page_url() ) . '">' . esc_html__( 'Settings', 'shawesome-contextual-404' ) . '</a>'
			);
			?>
		</p>
	</div>
	<?php
}
add_action( 'all_admin_notices', 'shawesome_c404_render_list_header' );

/**
 * Where each 404 page is used, according to the saved settings.
 *
 * @return array<int, string[]> 404 page => labels.
 */
function shawesome_c404_page_usages() {
	$settings   = shawesome_c404_get_settings();
	$post_types = shawesome_c404_post_types();
	$usages     = array();

	foreach ( $settings['post_types'] as $name => $page ) {
		if ( isset( $post_types[ $name ] ) ) {
			$usages[ (int) $page ][] = $post_types[ $name ]->labels->name;
		}
	}

	if ( $settings['fallback'] ) {
		$usages[ (int) $settings['fallback'] ][] = __( 'Default', 'shawesome-contextual-404' );
	}

	foreach ( $settings['paths'] as $rule ) {
		$usages[ (int) $rule['page'] ][] = 'regex' === $rule['type'] ? $rule['pattern'] : '/' . $rule['pattern'] . '/*';
	}

	return $usages;
}

/**
 * Add the column "Used for" to the list of 404 pages.
 *
 * @param string[] $columns Columns.
 * @return string[]
 */
function shawesome_c404_list_columns( $columns ) {
	$result = array();

	foreach ( $columns as $key => $label ) {
		$result[ $key ] = $label;
		if ( 'title' === $key ) {
			$result['shawesome_c404_usage'] = __( 'Used for', 'shawesome-contextual-404' );
		}
	}

	return $result;
}
add_filter( 'manage_' . SHAWESOME_C404_POST_TYPE . '_posts_columns', 'shawesome_c404_list_columns' );

/**
 * Render the column "Used for".
 *
 * @param string $column  Column.
 * @param int    $post_id 404 page.
 */
function shawesome_c404_render_list_column( $column, $post_id ) {
	static $usages = null;

	if ( 'shawesome_c404_usage' !== $column ) {
		return;
	}

	if ( null === $usages ) {
		$usages = shawesome_c404_page_usages();
	}

	if ( empty( $usages[ $post_id ] ) ) {
		printf(
			'<span class="sc404-pill sc404-pill--warning">%s · <a href="%s">%s</a></span>',
			esc_html__( 'Not assigned', 'shawesome-contextual-404' ),
			esc_url( shawesome_c404_page_url() ),
			esc_html__( 'assign', 'shawesome-contextual-404' )
		);
		return;
	}

	foreach ( $usages[ $post_id ] as $label ) {
		echo '<span class="sc404-pill">' . esc_html( $label ) . '</span> ';
	}
}
add_action( 'manage_' . SHAWESOME_C404_POST_TYPE . '_posts_custom_column', 'shawesome_c404_render_list_column', 10, 2 );

/**
 * Leave out the month filter on the list of 404 pages, which are few.
 *
 * @param bool   $disable   Whether to leave it out.
 * @param string $post_type Post type of the list.
 * @return bool
 */
function shawesome_c404_disable_months_dropdown( $disable, $post_type ) {
	return SHAWESOME_C404_POST_TYPE === $post_type ? true : $disable;
}
add_filter( 'disable_months_dropdown', 'shawesome_c404_disable_months_dropdown', 10, 2 );

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
		$valid   = shawesome_c404_regex_is_valid( $pattern );
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
 * Sanitize the posted settings. Rows of the path rules arrive in the order of
 * the form, under keys that only tell them apart.
 *
 * @param array $posted Unslashed form data.
 * @return array{0: array, 1: bool} Settings, and whether a rule with a pattern
 *                                  was removed.
 */
function shawesome_c404_sanitize_settings( $posted ) {
	$posted_types = isset( $posted['post_types'] ) ? (array) $posted['post_types'] : array();
	$posted_paths = isset( $posted['paths'] ) ? (array) $posted['paths'] : array();
	$fallback     = isset( $posted['fallback'] ) ? absint( $posted['fallback'] ) : 0;

	$post_types = array();
	foreach ( array_keys( shawesome_c404_post_types() ) as $name ) {
		$page = isset( $posted_types[ $name ] ) ? absint( $posted_types[ $name ] ) : 0;
		if ( shawesome_c404_is_404_page( $page ) ) {
			$post_types[ $name ] = $page;
		}
	}

	$paths   = array();
	$removed = false;
	foreach ( $posted_paths as $posted_rule ) {
		$posted_rule = (array) $posted_rule;
		$rule        = shawesome_c404_sanitize_path_rule( $posted_rule );

		if ( $rule ) {
			$paths[] = $rule;
		} elseif ( ! empty( $posted_rule['pattern'] ) ) {
			$removed = true;
		}
	}

	$settings = array(
		'post_types' => $post_types,
		'fallback'   => shawesome_c404_is_404_page( $fallback ) ? $fallback : 0,
		'paths'      => $paths,
		'logging'    => ! empty( $posted['logging'] ),
	);

	return array( $settings, $removed );
}

/**
 * Save the settings (posted to admin-post.php?action=shawesome_c404_save).
 */
function shawesome_c404_save_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage options for this site.', 'shawesome-contextual-404' ) );
	}
	check_admin_referer( 'shawesome_c404_save' );

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in shawesome_c404_sanitize_settings().
	list( $settings, $removed ) = shawesome_c404_sanitize_settings( wp_unslash( $_POST ) );

	update_option( SHAWESOME_C404_OPTION, $settings );

	wp_safe_redirect( shawesome_c404_page_url( array( 'updated' => $removed ? 'invalid' : 'settings' ) ) );
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

	wp_safe_redirect(
		shawesome_c404_page_url(
			array(
				'tab'     => 'log',
				'updated' => 'cleared',
			)
		)
	);
	exit;
}
add_action( 'admin_post_shawesome_c404_clear_log', 'shawesome_c404_clear_statistics' );

/**
 * Describe which 404 page a URL gets: the same resolver as on the front end,
 * with the given settings. The URL is assumed to be a 404.
 *
 * @param string $url      URL or path.
 * @param array  $settings Settings, see shawesome_c404_get_settings().
 * @return string Not escaped.
 */
function shawesome_c404_describe_url( $url, $settings ) {
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );

	if ( 0 === strpos( $path, $home ) ) {
		$path = substr( $path, strlen( $home ) );
	}

	$request  = shawesome_c404_normalize_path( $path );
	$segment  = strtok( $request, '/' );
	$language = false !== $segment && in_array( $segment, array_map( 'strtolower', shawesome_c404_languages() ), true ) ? $segment : '';
	$match    = shawesome_c404_find_match( shawesome_c404_strip_language( $request, $language ), shawesome_c404_query_vars_from_path( $request ), $settings );
	// Without a language prefix, the URL is in the default language.
	$page = $match['page'] ? get_the_title( shawesome_c404_translate( $match['page'], '' !== $language ? $language : shawesome_c404_default_language() ) ) : __( 'Theme 404 page', 'shawesome-contextual-404' );

	return sprintf(
		/* translators: 1: category such as "Posts" or "Rule: shop", 2: title of the 404 page */
		__( '%1$s → %2$s', 'shawesome-contextual-404' ),
		shawesome_c404_category_label( $match['category'] ),
		$page
	);
}

/**
 * Test a URL against the unsaved settings and check the regular expressions
 * (AJAX action shawesome_c404_test, posted with the settings form).
 */
function shawesome_c404_ajax_test() {
	check_ajax_referer( 'shawesome_c404_save' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( null, 403 );
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in shawesome_c404_sanitize_settings().
	list( $settings ) = shawesome_c404_sanitize_settings( wp_unslash( $_POST ) );

	$invalid = array();
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only type and pattern are read, the pattern is not output.
	foreach ( isset( $_POST['paths'] ) ? (array) wp_unslash( $_POST['paths'] ) : array() as $key => $rule ) {
		if ( isset( $rule['type'], $rule['pattern'] ) && 'regex' === $rule['type'] && '' !== $rule['pattern'] && ! shawesome_c404_regex_is_valid( (string) $rule['pattern'] ) ) {
			$invalid[] = (string) $key;
		}
	}

	// A path without a leading slash would be taken for a host, see admin.js.
	$url = isset( $_POST['test_url'] ) ? sanitize_url( wp_unslash( $_POST['test_url'] ) ) : '';

	wp_send_json_success(
		array(
			'result'  => '' === $url ? '' : shawesome_c404_describe_url( $url, $settings ),
			'invalid' => $invalid,
		)
	);
}
add_action( 'wp_ajax_shawesome_c404_test', 'shawesome_c404_ajax_test' );

/**
 * Render a selection of the 404 pages.
 *
 * @param string    $name     Field name.
 * @param int       $selected Selected page.
 * @param WP_Post[] $pages    404 pages.
 * @param string    $none     Label of the empty option.
 * @param string    $label    Accessible name, empty if a label element names it.
 * @param string    $id       ID, empty for none.
 */
function shawesome_c404_render_select( $name, $selected, $pages, $none, $label = '', $id = '' ) {
	?>
	<select name="<?php echo esc_attr( $name ); ?>"<?php echo $id ? ' id="' . esc_attr( $id ) . '"' : ''; ?><?php echo $label ? ' aria-label="' . esc_attr( $label ) . '"' : ''; ?>>
		<option value="0"><?php echo esc_html( $none ); ?></option>
		<?php foreach ( $pages as $page ) : ?>
			<option value="<?php echo esc_attr( (string) $page->ID ); ?>" <?php selected( $selected, $page->ID ); ?>><?php echo esc_html( get_the_title( $page ) ); ?></option>
		<?php endforeach; ?>
	</select>
	<?php
}

/**
 * Render a row of the path rules.
 *
 * @param string    $key   Key that tells the row apart, or a placeholder in the template.
 * @param array     $rule  Path rule.
 * @param WP_Post[] $pages 404 pages.
 */
function shawesome_c404_render_path_rule( $key, $rule, $pages ) {
	$name     = 'paths[' . $key . ']';
	$is_regex = 'regex' === $rule['type'];
	?>
	<li class="sc404-rule<?php echo $is_regex ? ' is-regex' : ''; ?>" data-key="<?php echo esc_attr( $key ); ?>">
		<span class="dashicons dashicons-menu sc404-handle" title="<?php esc_attr_e( 'Drag to sort', 'shawesome-contextual-404' ); ?>" aria-hidden="true"></span>
		<span class="sc404-rule__number" aria-hidden="true"></span>
		<fieldset class="sc404-segment">
			<legend class="screen-reader-text"><?php esc_html_e( 'Type', 'shawesome-contextual-404' ); ?></legend>
			<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[type]" value="prefix" <?php checked( ! $is_regex ); ?>><span><?php esc_html_e( 'Prefix', 'shawesome-contextual-404' ); ?></span></label>
			<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[type]" value="regex" <?php checked( $is_regex ); ?>><span><?php esc_html_e( 'Regex', 'shawesome-contextual-404' ); ?></span></label>
		</fieldset>
		<span class="sc404-pattern">
			<span class="sc404-pattern__slash" aria-hidden="true">/</span>
			<input type="text" class="code" name="<?php echo esc_attr( $name ); ?>[pattern]" value="<?php echo esc_attr( $rule['pattern'] ); ?>" placeholder="<?php echo esc_attr( $is_regex ? '^/(sale|offers)/' : 'shop/sale' ); ?>" aria-label="<?php esc_attr_e( 'Pattern', 'shawesome-contextual-404' ); ?>">
		</span>
		<?php shawesome_c404_render_select( $name . '[page]', (int) $rule['page'], $pages, __( 'Select page…', 'shawesome-contextual-404' ), __( '404 page', 'shawesome-contextual-404' ) ); ?>
		<span class="sc404-rule__actions">
			<button type="button" class="button sc404-icon sc404-up" aria-label="<?php esc_attr_e( 'Move up', 'shawesome-contextual-404' ); ?>"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></button>
			<button type="button" class="button sc404-icon sc404-down" aria-label="<?php esc_attr_e( 'Move down', 'shawesome-contextual-404' ); ?>"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>
			<button type="button" class="button sc404-icon sc404-remove" aria-label="<?php esc_attr_e( 'Remove', 'shawesome-contextual-404' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
		</span>
	</li>
	<?php
}

/**
 * Render the tab selected with ?tab= and the result of the last action.
 */
function shawesome_c404_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
	$tab     = isset( $_GET['tab'] ) && 'log' === $_GET['tab'] ? 'log' : 'settings';
	$updated = isset( $_GET['updated'] ) ? sanitize_key( wp_unslash( $_GET['updated'] ) ) : '';
	// phpcs:enable

	$notices = array(
		'settings' => array( 'success', __( 'Settings saved.', 'shawesome-contextual-404' ) ),
		'invalid'  => array( 'warning', __( 'Settings saved. Path rules with an invalid regular expression or without a 404 page were removed.', 'shawesome-contextual-404' ) ),
		'cleared'  => array( 'success', __( 'Statistics deleted.', 'shawesome-contextual-404' ) ),
	);
	?>
	<div class="wrap sc404-wrap">
		<?php shawesome_c404_render_header( $tab ); ?>
		<?php if ( isset( $notices[ $updated ] ) ) : ?>
			<div class="notice notice-<?php echo esc_attr( $notices[ $updated ][0] ); ?> is-dismissible"><p><?php echo esc_html( $notices[ $updated ][1] ); ?></p></div>
		<?php endif; ?>
		<?php
		if ( 'log' === $tab ) {
			shawesome_c404_render_log();
		} else {
			shawesome_c404_render_settings( 'settings' === $updated || 'invalid' === $updated );
		}
		?>
	</div>
	<?php
}

/**
 * Render the settings.
 *
 * A rule and a test URL can be handed over with ?rule= and ?test= (from the
 * log); they are added to the form unsaved.
 *
 * @param bool $saved Whether the settings were just saved.
 */
function shawesome_c404_render_settings( $saved ) {
	$settings = shawesome_c404_get_settings();
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

	$descriptions = array(
		'post' => __( 'Categories, tags, date archives and post URLs the permalink structure identifies, e.g. /2026/10/xyz.', 'shawesome-contextual-404' ),
		'page' => __( 'URLs below an existing page, e.g. /about/xyz.', 'shawesome-contextual-404' ),
	);

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only, the form is not saved by it.
	$prefill_rule = isset( $_GET['rule'] ) ? sanitize_text_field( wp_unslash( $_GET['rule'] ) ) : '';
	$prefill_test = isset( $_GET['test'] ) ? sanitize_text_field( wp_unslash( $_GET['test'] ) ) : '';
	// phpcs:enable

	$empty_rule = array(
		'type'    => 'prefix',
		'pattern' => '',
		'page'    => 0,
	);
	?>
	<?php if ( ! $pages ) : ?>
		<div class="notice notice-info inline"><p>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . SHAWESOME_C404_POST_TYPE ) ); ?>"><?php esc_html_e( 'Create your first 404 page', 'shawesome-contextual-404' ); ?></a>
		</p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php?action=shawesome_c404_save' ) ); ?>" id="sc404-settings" autocomplete="off" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-prefill-rule="<?php echo esc_attr( $prefill_rule ); ?>">
		<?php wp_nonce_field( 'shawesome_c404_save' ); ?>

		<section class="sc404-card">
			<?php shawesome_c404_render_card_head( __( '1 · By post type', 'shawesome-contextual-404' ), __( 'Applies to URLs below the URL prefix, the archive and the taxonomies of a post type.', 'shawesome-contextual-404' ) ); ?>
			<div class="sc404-types">
				<?php foreach ( shawesome_c404_post_types() as $name => $object ) : ?>
					<div class="sc404-type">
						<div>
							<label for="sc404-type-<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $object->labels->name ); ?></label>
							<?php if ( isset( $descriptions[ $name ] ) ) : ?>
								<p><?php echo esc_html( $descriptions[ $name ] ); ?></p>
							<?php endif; ?>
						</div>
						<?php shawesome_c404_render_select( 'post_types[' . $name . ']', isset( $settings['post_types'][ $name ] ) ? (int) $settings['post_types'][ $name ] : 0, $pages, __( 'Same as “Default”', 'shawesome-contextual-404' ), '', 'sc404-type-' . $name ); ?>
					</div>
				<?php endforeach; ?>
				<div class="sc404-type">
					<div>
						<label for="sc404-type-fallback"><?php esc_html_e( 'Default', 'shawesome-contextual-404' ); ?></label>
						<p><?php esc_html_e( 'Everything that cannot be assigned to a post type – with the permalink structure /%postname%/ e.g. /xyz.', 'shawesome-contextual-404' ); ?></p>
					</div>
					<?php shawesome_c404_render_select( 'fallback', (int) $settings['fallback'], $pages, __( 'Theme 404 page', 'shawesome-contextual-404' ), '', 'sc404-type-fallback' ); ?>
				</div>
			</div>
		</section>

		<section class="sc404-card">
			<?php
			shawesome_c404_render_card_head(
				__( '2 · Path rules', 'shawesome-contextual-404' ),
				__( 'Take precedence over post types. Checked from top to bottom – the first match wins. The language prefix is not part of the path.', 'shawesome-contextual-404' ),
				function () {
					echo '<button type="button" class="button" id="sc404-add-rule">' . esc_html__( '+ Add rule', 'shawesome-contextual-404' ) . '</button>';
				}
			);
			?>
			<p class="sc404-empty"<?php echo $settings['paths'] ? ' hidden' : ''; ?>>
				<?php
				printf(
					/* translators: 1: example prefix, 2: example path, 3: example regular expression */
					esc_html__( 'No rules yet. Example: the prefix %1$s matches %2$s and everything below it; the regular expression %3$s is entered without delimiters and ignores case.', 'shawesome-contextual-404' ),
					'<code>shop</code>',
					'<code>/shop</code>',
					'<code>^/(sale|offers)/</code>'
				);
				?>
			</p>
			<ol class="sc404-rules" id="sc404-rules">
				<?php
				foreach ( $settings['paths'] as $index => $rule ) {
					shawesome_c404_render_path_rule( (string) $index, $rule, $pages );
				}
				?>
			</ol>
			<template id="sc404-rule-template">
				<?php shawesome_c404_render_path_rule( '__key__', $empty_rule, $pages ); ?>
			</template>
			<div class="sc404-card__foot sc404-test">
				<label for="sc404-test-url"><?php esc_html_e( 'Test URL', 'shawesome-contextual-404' ); ?></label>
				<input type="text" class="code" id="sc404-test-url" name="test_url" value="<?php echo esc_attr( $prefill_test ); ?>" placeholder="/shop/xyz">
				<output class="sc404-result" for="sc404-test-url" aria-live="polite"></output>
			</div>
		</section>

		<section class="sc404-card">
			<?php shawesome_c404_render_card_head( __( '3 · Statistics', 'shawesome-contextual-404' ) ); ?>
			<div class="sc404-card__body">
				<label><input type="checkbox" name="logging" value="1" <?php checked( $settings['logging'] ); ?>> <?php esc_html_e( 'Count 404 errors', 'shawesome-contextual-404' ); ?></label>
				<p class="description">
					<?php
					printf(
						/* translators: %s: link to the tab 404 Log */
						esc_html__( 'Stores URL and category per month for the %s. No IP addresses.', 'shawesome-contextual-404' ),
						'<a href="' . esc_url( shawesome_c404_page_url( array( 'tab' => 'log' ) ) ) . '">' . esc_html__( '404 Log', 'shawesome-contextual-404' ) . '</a>'
					);
					?>
				</p>
			</div>
		</section>

		<div class="sc404-savebar">
			<span class="sc404-status" data-dirty="<?php esc_attr_e( 'Unsaved changes', 'shawesome-contextual-404' ); ?>"><?php $saved ? esc_html_e( 'Saved', 'shawesome-contextual-404' ) : esc_html_e( 'No changes', 'shawesome-contextual-404' ); ?></span>
			<a href="<?php echo esc_url( shawesome_c404_page_url() ); ?>" class="sc404-discard" hidden><?php esc_html_e( 'Discard', 'shawesome-contextual-404' ); ?></a>
			<?php submit_button( __( 'Save Changes', 'shawesome-contextual-404' ), 'primary', 'submit', false ); ?>
		</div>
	</form>
	<?php
}
