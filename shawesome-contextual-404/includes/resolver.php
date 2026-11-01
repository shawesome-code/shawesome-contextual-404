<?php
/**
 * Find the 404 page of a request.
 *
 * Order: path rules, query vars left by the rewrite rules, rewrite slugs of
 * the post types, parent pages, the default page. On sites with the permalink
 * structure /%postname%/ a URL such as /xyz says nothing about what was
 * requested, so it ends up at the default page.
 *
 * @package Shawesome_Contextual_404
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Requested path without slashes, language prefix and upper case.
 *
 * @return string
 */
function shawesome_c404_get_path() {
	global $wp;

	$path     = strtolower( trim( rawurldecode( (string) $wp->request ), '/' ) );
	$language = strtolower( shawesome_c404_current_language() );

	if ( '' !== $language && shawesome_c404_path_is_within( $path, $language ) ) {
		$path = ltrim( substr( $path, strlen( $language ) ), '/' );
	}

	return $path;
}

/**
 * Whether a path equals a prefix or lies below it.
 *
 * @param string $path   Path.
 * @param string $prefix Prefix without slashes.
 * @return bool
 */
function shawesome_c404_path_is_within( $path, $prefix ) {
	return $path === $prefix || 0 === strpos( $path, $prefix . '/' );
}

/**
 * Regular expression of a path rule. The control character as delimiter
 * lets the pattern contain every usual delimiter unescaped; it is removed
 * from patterns on saving.
 *
 * @param string $pattern Pattern without delimiters.
 * @return string
 */
function shawesome_c404_regex( $pattern ) {
	return "\x01" . $pattern . "\x01i";
}

/**
 * First matching path rule. Regular expressions are matched against the path
 * with a leading slash.
 *
 * @param array[] $rules Path rules.
 * @param string  $path  Requested path.
 * @return array|null Rule, null if none.
 */
function shawesome_c404_match_path_rules( $rules, $path ) {
	foreach ( $rules as $rule ) {
		if ( 'regex' === $rule['type'] ) {
			// A pattern that fails at runtime counts as no match.
			$matches = 1 === @preg_match( shawesome_c404_regex( $rule['pattern'] ), '/' . $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		} else {
			$matches = shawesome_c404_path_is_within( $path, $rule['pattern'] );
		}

		if ( $matches ) {
			return $rule;
		}
	}

	return null;
}

/**
 * Post types the rewrite rules point to.
 *
 * @param array $vars Query vars of the main query before they were parsed.
 * @return string[]
 */
function shawesome_c404_post_types_from_query( $vars ) {
	$post_types = empty( $vars['post_type'] ) ? array() : (array) $vars['post_type'];

	foreach ( shawesome_c404_post_types() as $name => $object ) {
		if ( $object->query_var && isset( $vars[ $object->query_var ] ) ) {
			$post_types[] = $name;
		}
	}

	foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $taxonomy ) {
		if ( $taxonomy->query_var && isset( $vars[ $taxonomy->query_var ] ) ) {
			$post_types = array_merge( $post_types, $taxonomy->object_type );
		}
	}

	if ( isset( $vars['year'] ) ) {
		$post_types[] = 'post';
	}

	return $post_types;
}

/**
 * URL prefixes of the post types, the longest first, so that /shop/news
 * wins over /shop. Placeholders such as %product_cat% end a prefix.
 *
 * @return array<string, string> Prefix => post type.
 */
function shawesome_c404_post_type_prefixes() {
	global $wp_rewrite;

	$front    = trim( (string) $wp_rewrite->front, '/' );
	$prefixes = array();

	foreach ( shawesome_c404_post_types() as $name => $object ) {
		if ( is_array( $object->rewrite ) && ! empty( $object->rewrite['slug'] ) ) {
			$slug = trim( (string) strtok( $object->rewrite['slug'], '%' ), '/' );

			$prefixes[ $slug ] = $name;
			if ( '' !== $front && ! empty( $object->rewrite['with_front'] ) ) {
				$prefixes[ $front . '/' . $slug ] = $name;
			}
		}

		// WooCommerce: /shop/ is the archive of products in /product/.
		if ( is_string( $object->has_archive ) ) {
			$prefixes[ trim( $object->has_archive, '/' ) ] = $name;
		}
	}

	if ( '' !== $front ) {
		$prefixes[ $front ] = 'post';
	}

	unset( $prefixes[''] );
	uksort(
		$prefixes,
		function ( $a, $b ) {
			return strlen( $b ) - strlen( $a );
		}
	);

	return $prefixes;
}

/**
 * Post type whose URL prefix a path starts with.
 *
 * @param string $path Requested path.
 * @return string Post type, empty if none.
 */
function shawesome_c404_post_type_from_path( $path ) {
	foreach ( shawesome_c404_post_type_prefixes() as $prefix => $name ) {
		if ( shawesome_c404_path_is_within( $path, strtolower( $prefix ) ) ) {
			return $name;
		}
	}

	return '';
}

/**
 * Whether a path lies below an existing page. Only the first levels are
 * checked, as every level is a database query and 404s are often bots.
 *
 * @param string $path Requested path.
 * @return bool
 */
function shawesome_c404_has_parent_page( $path ) {
	$segments = array_slice( explode( '/', $path ), 0, 5 );

	while ( count( $segments ) > 1 ) {
		array_pop( $segments );
		if ( get_page_by_path( implode( '/', $segments ) ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Whether an ID belongs to a 404 page.
 *
 * @param int $page_id Post ID.
 * @return bool
 */
function shawesome_c404_is_404_page( $page_id ) {
	return SHAWESOME_C404_POST_TYPE === get_post_type( $page_id );
}

/**
 * 404 page configured for a path and the category the statistics count the
 * request in: the path rule or post type that applies, otherwise the default.
 *
 * @param string $path Requested path.
 * @param array  $vars Query vars of the main query before they were parsed.
 * @return array{page: int, category: string} 404 page, 0 if none, and
 *               category 'rule:<pattern>', 'post_type:<name>' or 'default'.
 */
function shawesome_c404_find_match( $path, $vars ) {
	$settings = shawesome_c404_get_settings();

	$rule = shawesome_c404_match_path_rules( $settings['paths'], $path );
	if ( $rule ) {
		return array(
			'page'     => (int) $rule['page'],
			'category' => 'rule:' . $rule['pattern'],
		);
	}

	$post_types   = shawesome_c404_post_types_from_query( $vars );
	$post_types[] = shawesome_c404_post_type_from_path( $path );

	if ( ! empty( $settings['post_types']['page'] ) && shawesome_c404_has_parent_page( $path ) ) {
		$post_types[] = 'page';
	}

	foreach ( $post_types as $post_type ) {
		if ( ! empty( $settings['post_types'][ $post_type ] ) ) {
			return array(
				'page'     => (int) $settings['post_types'][ $post_type ],
				'category' => 'post_type:' . $post_type,
			);
		}
	}

	return array(
		'page'     => (int) $settings['fallback'],
		'category' => 'default',
	);
}

/**
 * Published 404 page to show, in the current language.
 *
 * @param int    $page_id Configured 404 page.
 * @param string $path    Requested path.
 * @return int 404 page, 0 keeps the 404 page of the theme.
 */
function shawesome_c404_get_page_id( $page_id, $path ) {
	/**
	 * Filters the 404 page of a request.
	 *
	 * @param int    $page_id 404 page, 0 keeps the 404 page of the theme.
	 * @param string $path    Requested path without language prefix.
	 */
	$page_id = (int) apply_filters( 'shawesome_c404_page_id', $page_id, $path );

	if ( ! shawesome_c404_is_404_page( $page_id ) || 'publish' !== get_post_status( $page_id ) ) {
		return 0;
	}

	return shawesome_c404_translate( $page_id );
}
