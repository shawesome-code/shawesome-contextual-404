<?php
/**
 * The "shawesome" menu in the network admin and the admin of each site, with
 * a start page: the installed shawesome plugins and recommendations from the
 * WordPress plugin directory.
 *
 * Loaded by loader.php, newest copy only. Plugins add their page with
 * add_submenu_page( 'shawesome', ... ), on 'network_admin_menu' for the
 * network admin and on 'admin_menu' for a site. In a network, the menu is not
 * shown without such a page.
 *
 * This code belongs to no single plugin, so it has no text domain: the texts
 * are the ones WordPress translates itself.
 *
 * @package Shawesome_Admin
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.WP.I18n.MissingArgDomain -- texts of WordPress itself.

/**
 * Capability for the start page in the current admin.
 *
 * @return string
 */
function shawesome_admin_capability() {
	return is_network_admin() ? 'manage_network_options' : 'manage_options';
}

/**
 * The Bildmarke as a data URI.
 *
 * @param string $color  Color of the three fixed modules.
 * @param string $accent Color of the offset module.
 * @return string
 */
function shawesome_admin_icon( $color, $accent ) {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><g fill="' . $color . '"><rect x="5" y="5" width="17" height="17" rx="4.5"/><rect x="26" y="5" width="17" height="17" rx="4.5"/><rect x="5" y="26" width="17" height="17" rx="4.5"/></g><rect x="28.5" y="28.5" width="17" height="17" rx="4.5" fill="' . $accent . '"/></svg>';

	return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
}

/**
 * Add the menu after Settings, before the plugins add their pages at the
 * default priority. WordPress repaints the icon to match the admin color
 * scheme, so it is in one color here.
 */
function shawesome_admin_menu() {
	$hook = add_menu_page(
		'shawesome',
		'shawesome',
		shawesome_admin_capability(),
		'shawesome',
		'shawesome_admin_render_page',
		shawesome_admin_icon( '#a7aaad', '#a7aaad' ),
		is_network_admin() ? 30 : 81
	);

	add_submenu_page( 'shawesome', 'shawesome', __( 'Overview' ), shawesome_admin_capability(), 'shawesome' );

	add_action( 'load-' . $hook, 'shawesome_admin_load_page' );
}
add_action( 'network_admin_menu', 'shawesome_admin_menu', 9 );
add_action( 'admin_menu', 'shawesome_admin_menu', 9 );

/**
 * Remove the menu in a network if no plugin added a page to it: network
 * plugins may have no page in the admin of a site. Outside of a network, every
 * plugin bundling this code is active here and the recommendations are useful.
 */
function shawesome_admin_remove_empty_menu() {
	global $submenu;

	if ( is_multisite() && ( empty( $submenu['shawesome'] ) || 1 === count( $submenu['shawesome'] ) ) ) {
		remove_menu_page( 'shawesome' );
		unset( $submenu['shawesome'] );
	}
}
add_action( 'network_admin_menu', 'shawesome_admin_remove_empty_menu', 99 );
add_action( 'admin_menu', 'shawesome_admin_remove_empty_menu', 99 );

/**
 * Prepare the start page: the install buttons and the details modal of the
 * recommended plugins need WordPress' plugin installer.
 */
function shawesome_admin_load_page() {
	require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

	add_thickbox();
	wp_enqueue_script( 'plugin-install' );
	wp_enqueue_style( 'shawesome-admin', plugins_url( 'admin.css', __FILE__ ), array(), $GLOBALS['shawesome_admin']['version'] );
}

/**
 * Plugins by shawesome in the current admin. Active means network-activated in
 * the network admin and active in any way in the admin of a site. Inactive
 * ones are left out in the admin of a site in a network, where plugins are
 * managed in the network admin.
 *
 * @return array<string, array> Plugin file => plugin data, plus 'Active', 'Page',
 *                              the menu slug of its settings page or '', and
 *                              'Update', whether the user can get a new version.
 */
function shawesome_admin_plugins() {
	global $submenu;

	/**
	 * Filter the settings pages of the plugins on the start page.
	 *
	 * @param array<string, string> $pages Plugin file => menu slug.
	 */
	$pages   = (array) apply_filters( 'shawesome_admin_pages', array() );
	$slugs   = isset( $submenu['shawesome'] ) ? wp_list_pluck( $submenu['shawesome'], 2 ) : array();
	$in_site = is_multisite() && ! is_network_admin();
	$updates = current_user_can( 'update_plugins' ) && ! $in_site ? get_site_transient( 'update_plugins' ) : null;
	$plugins = array();

	foreach ( get_plugins() as $file => $data ) {
		$active = is_network_admin() ? is_plugin_active_for_network( $file ) : is_plugin_active( $file );

		if ( ( ! $active && $in_site ) || 0 !== strcasecmp( $data['Author'], 'shawesome' ) ) {
			continue;
		}

		// Unlike get_plugins(), this translates the name and the description.
		$plugins[ $file ]           = get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false );
		$plugins[ $file ]['Active'] = $active;
		$plugins[ $file ]['Page']   = isset( $pages[ $file ] ) && in_array( $pages[ $file ], $slugs, true ) ? $pages[ $file ] : '';
		$plugins[ $file ]['Update'] = isset( $updates->response[ $file ] );
	}

	return $plugins;
}

/**
 * Plugins by shawesome in the WordPress plugin directory, except for the given
 * ones. The last result is kept if the directory cannot be reached. Empty for
 * users who cannot install plugins, and in the admin of a site in a network,
 * where plugins cannot be installed.
 *
 * @param string[] $exclude Plugin slugs.
 * @return array[] Plugin data of the directory.
 */
function shawesome_admin_recommended_plugins( $exclude ) {
	if ( ! current_user_can( 'install_plugins' ) || ( is_multisite() && ! is_network_admin() ) ) {
		return array();
	}

	$key = 'shawesome_admin_recommended_' . get_user_locale();

	// The transient only says when to ask again, the option holds the result.
	if ( false === get_site_transient( $key ) ) {
		$response = plugins_api(
			'query_plugins',
			array(
				'author'   => 'shawesome',
				'per_page' => 30,
				'fields'   => array( 'icons' => true ),
			)
		);
		$failed   = is_wp_error( $response );

		if ( ! $failed ) {
			update_site_option( $key, (array) $response->plugins );
		}

		set_site_transient( $key, 1, $failed ? HOUR_IN_SECONDS : DAY_IN_SECONDS );
	}

	$recommended = array();
	foreach ( (array) get_site_option( $key, array() ) as $plugin ) {
		$plugin = (array) $plugin;

		if ( ! in_array( $plugin['slug'], $exclude, true ) ) {
			$recommended[] = $plugin;
		}
	}

	return $recommended;
}

/**
 * Render a plugin card. A name of the form "shawesome Name – Subtitle" is shown
 * as the title "Name" with the subtitle below it.
 *
 * @param string   $name        Name, plain text.
 * @param string   $icon        Icon URL or data URI.
 * @param string   $description Description, plain text.
 * @param string[] $actions     Action links and buttons, escaped.
 * @param string   $meta        Line below the description, plain text.
 */
function shawesome_admin_render_card( $name, $icon, $description, $actions, $meta = '' ) {
	$parts = explode( ' – ', preg_replace( '/^shawesome /i', '', $name ), 2 );
	?>
	<div class="shawesome-admin-card">
		<img src="<?php echo esc_attr( $icon ); ?>" class="shawesome-admin-card-icon" alt="">
		<div class="shawesome-admin-card-text">
			<h3><?php echo esc_html( $parts[0] ); ?></h3>
			<?php if ( isset( $parts[1] ) ) : ?>
				<p class="shawesome-admin-card-subtitle"><?php echo esc_html( $parts[1] ); ?></p>
			<?php endif; ?>
			<p><?php echo esc_html( $description ); ?></p>
			<?php if ( '' !== $meta ) : ?>
				<p class="shawesome-admin-card-meta"><?php echo esc_html( $meta ); ?></p>
			<?php endif; ?>
		</div>
		<?php if ( $actions ) : ?>
			<div class="shawesome-admin-card-actions">
				<?php echo implode( '', $actions ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the callers. ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Render the card of an installed plugin. Updates and activation are left to
 * WordPress' plugins screen.
 *
 * @param string $file   Plugin file.
 * @param array  $plugin Plugin data.
 */
function shawesome_admin_render_installed_card( $file, $plugin ) {
	$actions = array();

	if ( $plugin['Update'] ) {
		$actions[] = sprintf(
			'<a class="button button-primary button-compact" href="%s">%s</a>',
			esc_url( self_admin_url( 'plugins.php?plugin_status=upgrade' ) ),
			esc_html_x( 'Update Now', 'plugin' )
		);
	}

	if ( ! $plugin['Active'] && current_user_can( 'activate_plugin', $file ) ) {
		$actions[] = sprintf(
			'<a class="button button-primary button-compact" href="%s">%s</a>',
			esc_url( wp_nonce_url( self_admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $file ) ), 'activate-plugin_' . $file ) ),
			is_network_admin() ? esc_html_x( 'Network Activate', 'plugin' ) : esc_html_x( 'Activate', 'plugin' )
		);
	}

	if ( '' !== $plugin['Page'] ) {
		$actions[] = sprintf(
			'<a class="button button-compact" href="%s">%s</a>',
			esc_url( self_admin_url( 'admin.php?page=' . $plugin['Page'] ) ),
			esc_html__( 'Settings' )
		);
	}

	shawesome_admin_render_card(
		$plugin['Name'],
		shawesome_admin_icon( '#1f2328', '#0366d6' ),
		$plugin['Description'],
		$actions,
		/* translators: %s: Plugin version number. */
		sprintf( __( 'Version %s' ), $plugin['Version'] )
	);
}

/**
 * Render the card of a recommended plugin.
 *
 * @param array $plugin Plugin data of the directory.
 */
function shawesome_admin_render_recommended_card( $plugin ) {
	$name    = html_entity_decode( wp_strip_all_tags( $plugin['name'] ), ENT_QUOTES, 'UTF-8' );
	$details =self_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $plugin['slug'] . '&TB_iframe=true&width=600&height=550' );

	if ( ! empty( $plugin['icons']['svg'] ) ) {
		$icon = $plugin['icons']['svg'];
	} elseif ( ! empty( $plugin['icons']['2x'] ) ) {
		$icon = $plugin['icons']['2x'];
	} elseif ( ! empty( $plugin['icons']['1x'] ) ) {
		$icon = $plugin['icons']['1x'];
	} else {
		$icon = isset( $plugin['icons']['default'] ) ? $plugin['icons']['default'] : '';
	}

	$actions = array(
		wp_get_plugin_action_button(
			$name,
			$plugin,
			is_php_version_compatible( isset( $plugin['requires_php'] ) ? $plugin['requires_php'] : null ),
			is_wp_version_compatible( isset( $plugin['requires'] ) ? $plugin['requires'] : null )
		),
		sprintf(
			'<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s" data-title="%s">%s</a>',
			esc_url( $details ),
			/* translators: %s: Plugin name and version. */
			esc_attr( sprintf( __( 'More information about %s' ), $name ) ),
			esc_attr( $name ),
			esc_html__( 'More Details' )
		),
	);

	shawesome_admin_render_card(
		$name,
		esc_url( $icon ),
		wp_strip_all_tags( $plugin['short_description'] ),
		array_filter( $actions )
	);
}

/**
 * Render the start page.
 */
function shawesome_admin_render_page() {
	if ( ! current_user_can( shawesome_admin_capability() ) ) {
		return;
	}

	$plugins     = shawesome_admin_plugins();
	$active      = wp_list_filter( $plugins, array( 'Active' => true ) );
	$installed   = wp_list_filter( $plugins, array( 'Active' => false ) );
	$recommended = shawesome_admin_recommended_plugins( array_map( 'dirname', array_keys( $plugins ) ) );
	?>
	<div class="wrap shawesome-admin">
		<h1>shawesome</h1>

		<?php if ( $active ) : ?>
			<h2><?php echo esc_html_x( 'Active', 'plugin' ); ?></h2>
			<div class="shawesome-admin-cards">
				<?php
				foreach ( $active as $file => $plugin ) {
					shawesome_admin_render_installed_card( $file, $plugin );
				}
				?>
			</div>
		<?php endif; ?>

		<?php if ( $installed ) : ?>
			<h2><?php echo esc_html_x( 'Installed', 'plugin' ); ?></h2>
			<div class="shawesome-admin-cards">
				<?php
				foreach ( $installed as $file => $plugin ) {
					shawesome_admin_render_installed_card( $file, $plugin );
				}
				?>
			</div>
		<?php endif; ?>

		<?php if ( $recommended ) : ?>
			<h2><?php echo esc_html_x( 'Recommended', 'Plugin Installer' ); ?></h2>
			<div class="shawesome-admin-cards">
				<?php
				foreach ( $recommended as $plugin ) {
					shawesome_admin_render_recommended_card( $plugin );
				}
				?>
			</div>
		<?php endif; ?>
	</div>
	<?php
}
