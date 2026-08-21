<?php
/**
 * Compatibility helpers for legacy catalog integrations.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Keeps legacy theme notice hooks available.
 *
 * @return void
 */
function product_adder_theme_check_notice() {
	// Necessary for extensions before v2.7.4 to work.
}

add_action( 'before_product_page', 'set_product_page_image_html' );

/**
 * Sets product page image HTML if it was modified by a third party.
 *
 * @return void
 */
function set_product_page_image_html() {
	if ( has_filter( 'post_thumbnail_html' ) ) {
		add_filter( 'post_thumbnail_html', 'get_default_product_page_image_html', 1 );
		add_filter( 'post_thumbnail_html', 'product_page_image_html', 99 );
	}
}

/**
 * Inserts the default thumbnail HTML into a global cache.
 *
 * @param string $html Thumbnail HTML.
 *
 * @return string
 * @global string $product_page_image_html
 */
function get_default_product_page_image_html( $html ) {
	global $product_page_image_html;
	$product_page_image_html = $html;

	return $html;
}

/**
 * Replaces the product page image HTML with the default
 *
 * @param string $html Filtered thumbnail HTML.
 *
 * @return string
 * @global string $product_page_image_html
 */
function product_page_image_html( $html ) {
	if ( is_ic_product_page() ) {
		global $product_page_image_html;

		return $product_page_image_html;
	}

	return $html;
}

/**
 * Compatibility with PHP <5.3 for ic_lcfirst
 *
 * @param string $text Input text.
 *
 * @return string
 */
function ic_lcfirst( $text ) {
	if ( ic_is_multibyte( $text ) ) {
		$first_char = mb_substr( $text, 0, 1 );
		$then       = mb_substr( $text, 1, null );

		return mb_strtolower( $first_char ) . $then;
	} elseif ( function_exists( 'lcfirst' ) ) {
		return lcfirst( $text );
	} else {
		$text['0'] = strtolower( $text['0'] );

		return $text;
	}
}

if ( ! function_exists( 'ic_ucfirst' ) ) {
	/**
	 * Compatibility with PHP <5.3 for ic_ucfirst
	 *
	 * @param string $text Input text.
	 *
	 * @return string
	 */
	function ic_ucfirst( $text ) {
		if ( ic_is_multibyte( $text ) ) {
			$first_char = mb_substr( $text, 0, 1 );
			$then       = mb_substr( $text, 1, null );

			return mb_strtoupper( $first_char ) . $then;
		} elseif ( function_exists( 'ucfirst' ) ) {
			return ucfirst( $text );
		} else {
			$text['0'] = strtoupper( $text['0'] );

			return $text;
		}
	}
}

/**
 * Compatibility wrapper for ucwords on older PHP versions.
 *
 * @param string $text Input text.
 *
 * @return string
 */
function ic_ucwords( $text ) {
	if ( ic_is_multibyte( $text ) ) {

		return mb_convert_case( $text, MB_CASE_TITLE );
	} elseif ( function_exists( 'ucwords' ) ) {
		return ucwords( $text );
	} else {
		$text['0'] = strtoupper( $text['0'] );

		return $text;
	}
}

if ( ! function_exists( 'ic_is_multibyte' ) ) {
	/**
	 * Checks whether the given string contains multibyte characters.
	 *
	 * @param string $text Input text.
	 *
	 * @return bool
	 */
	function ic_is_multibyte( $text ) {
		if ( function_exists( 'mb_check_encoding' ) ) {
			return ! mb_check_encoding( $text, 'ASCII' ) && mb_check_encoding( $text, 'UTF-8' );
		}

		return false;
	}
}


/**
 * Checks if any post type has the same rewrite parameter.
 *
 * @return bool
 */
function ic_check_rewrite_compatibility() {
	$post_types = get_post_types( array( 'publicly_queryable' => true ), 'object' );
	if ( empty( $post_types['al_product'] ) ) {
		return true;
	}
	$slug = $post_types['al_product']->rewrite['slug'];
	foreach ( $post_types as $post_type => $type ) {
		if ( 'al_product' !== $post_type && isset( $type->rewrite['slug'] ) ) {
			if ( $slug === $type->rewrite['slug'] || '/' . $slug === $type->rewrite['slug'] ) {
				return false;
			}
		}
	}

	return true;
}

/**
 * Checks if any taxonomy has the same rewrite parameter.
 *
 * @return bool
 */
function ic_check_tax_rewrite_compatibility() {
	$taxonomies = get_taxonomies( array( 'public' => true ), 'object' );
	if ( isset( $taxonomies['al_product-cat'] ) ) {
		$slug = $taxonomies['al_product-cat']->rewrite['slug'];
		foreach ( $taxonomies as $taxonomy_name => $tax ) {
			if ( 'al_product-cat' !== $taxonomy_name && isset( $tax->rewrite['slug'] ) ) {
				if ( $slug === $tax->rewrite['slug'] || '/' . $slug === $tax->rewrite['slug'] ) {
					return false;
				}
			}
		}
	}

	return true;
}

/**
 * Returns the product image markup with a default fallback.
 *
 * @param int    $product_id Product ID.
 * @param string $size       Requested image size.
 * @param array  $attributes Image attributes.
 *
 * @return string
 */
function ic_get_product_image( $product_id, $size = 'full', $attributes = array() ) {
	$image_id = get_post_thumbnail_id( $product_id );
	if ( empty( $image_id ) ) {
		$image_id = ic_default_product_image_id();
	}
	if ( ! empty( $image_id ) ) {
		$image = wp_get_attachment_image( $image_id, $size, false, $attributes );
	} else {
		$image = '<img alt="default-image" src="' . default_product_thumbnail_url() . '" >';
	}

	return $image;
}

add_action( 'ic_pre_get_products_search', 'ic_product_search_fix' );

/**
 * Normalizes the product search post type input.
 *
 * @param WP_Query $query Query instance.
 *
 * @return void
 */
function ic_product_search_fix( $query ) {
	$post_type = filter_input( INPUT_GET, 'post_type', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
	if ( empty( $post_type ) ) {
		$post_type = filter_input( INPUT_GET, 'post_type', FILTER_UNSAFE_RAW );
	}
	if ( ! empty( $post_type ) ) {
		$query->query_vars['post_type'] = is_array( $post_type ) ? array_map( 'sanitize_text_field', $post_type ) : sanitize_text_field( $post_type );
	}
}

/**
 * Gets product terms with catalog-specific compatibility handling.
 *
 * @param array $def_params      Default term query parameters.
 * @param bool  $exact_taxonomy Whether to prevent EPC taxonomy widening.
 *
 * @return array|int[]|string[]|WP_Error|WP_Term[]
 */
function ic_get_terms( $def_params = array(), $exact_taxonomy = false ) {
	if ( is_ic_admin() ) {

		return ic_get_terms_simple( $def_params );
	}
	$params = apply_filters( 'ic_get_terms_params', $def_params );
	if ( ! isset( $params['update_term_meta_cache'] ) ) {
		$params['update_term_meta_cache'] = false;
	}
	if ( isset( $params['fields'] ) && ( 'names' === $params['fields'] || 'ids' === $params['fields'] || 'id=>name' === $params['fields'] ) ) {
		$fields           = $params['fields'];
		$params['fields'] = 'all';
	} else {
		$fields = 'all';
	}
	if ( ! $exact_taxonomy && ! empty( $params['taxonomy'] ) && ! is_array( $params['taxonomy'] ) && ! empty( $params['object_ids'] ) ) {
		$filter_taxonomies = ic_filter_taxonomies( true );
		if ( count( $filter_taxonomies ) > 1 && in_array( $params['taxonomy'], $filter_taxonomies, true ) ) {
			$return_taxonomy = $params['taxonomy'];
			if ( ! empty( $params['number'] ) ) {
				$return_number = $params['number'];
				unset( $params['number'] );
			}
			$params['taxonomy'] = $filter_taxonomies;
			if ( isset( $params['parent'] ) ) {
				$return_parent = $params['parent'];
				unset( $params['parent'] );
			}
			if ( ! empty( $params['orderby'] ) && 'term_id' === $params['orderby'] ) {
				if ( empty( $params['order'] ) || ( ! empty( $params['order'] ) && 'ASC' === $params['order'] ) ) {
					$return_orderby = $params['orderby'];
					unset( $params['orderby'] );
				}
			}
		}
	}
	if ( ! isset( $params['hide_empty'] ) ) {
		$params['hide_empty'] = true;
	}
	if ( ! isset( $params['fields'] ) ) {
		$params['fields'] = 'all';
	}
	if ( ! isset( $params['orderby'] ) ) {
		$params['orderby'] = 'name';
	}
	if ( ! isset( $params['order'] ) ) {
		$params['order'] = 'ASC';
	}
	if ( isset( $params['ic_post_type'] ) && ! empty( $params['object_ids'] ) ) {
		unset( $params['ic_post_type'] );
	}
	$cache_key = 'ic_get_terms_' . md5(
		wp_json_encode(
			array(
				'params'         => $params,
				'exact_taxonomy' => $exact_taxonomy,
			)
		)
	);
	$terms     = ic_get_global( $cache_key );
	if ( false === $terms ) {
		$terms = ic_get_terms_simple( $params );
		ic_save_global( $cache_key, $terms );
	}
	if ( is_wp_error( $terms ) ) {
		return array();
	}
	if ( ! empty( $return_taxonomy ) || isset( $return_parent ) ) {
		$new_terms = array();
		$num       = 0;
		foreach ( $terms as $term ) {
			if ( ! empty( $return_taxonomy ) && $term->taxonomy !== $return_taxonomy ) {
				continue;
			}
			if ( isset( $return_parent ) && $term->parent !== $return_parent ) {
				continue;
			}
			$new_terms[] = $term;
			++$num;
			if ( ! empty( $return_number ) && $return_number === $num ) {
				break;
			}
		}
		$terms = $new_terms;
	}
	if ( ! empty( $return_orderby ) ) {
		usort( $terms, 'ic_compare_term_ids' );
	}
	if ( 'all' !== $fields ) {
		if ( 'names' === $fields ) {
			$fields = 'name';
		} elseif ( 'ids' === $fields ) {
			$fields = 'term_id';
		}
		if ( 'id=>name' === $fields ) {
			$new_terms = array();
			foreach ( $terms as $term ) {
				$new_terms[ $term->term_id ] = $term->name;
			}
			$terms = $new_terms;
		} else {
			$terms = wp_list_pluck( $terms, $fields );
		}
	}

	return $terms;
}

/**
 * Compatibility get_terms wrapper.
 *
 * @param array $params Term query parameters.
 *
 * @return int[]|string|string[]|WP_Error|WP_Term[]
 */
function ic_get_terms_simple( $params ) {
	return get_terms( $params );
}

/**
 * Compares term IDs for sorting.
 *
 * @param WP_Term $term_first  First term.
 * @param WP_Term $term_second Second term.
 *
 * @return int
 */
function ic_compare_term_ids( $term_first, $term_second ) {
	return $term_first->term_id - $term_second->term_id;
}

add_action( 'before_product_page', 'ic_restore_wpautop' );

/**
 * Some themes and plugins remove wpautop, so it is re-added on product pages.
 *
 * @return void
 */
function ic_restore_wpautop() {
	if ( ! has_filter( 'the_content', 'wpautop' ) ) {
		add_filter( 'the_content', 'wpautop' );
	}
}

if ( ! function_exists( 'ic_array_key_last' ) ) {
	/**
	 * Polyfill for array_key_last.
	 *
	 * @param array $items Input array.
	 *
	 * @return int|string|null
	 */
	function ic_array_key_last( $items ) {
		if ( function_exists( 'array_key_last' ) ) {
			return array_key_last( $items );
		}
		if ( ! is_array( $items ) || empty( $items ) ) {
			return null;
		}

		return array_keys( $items )[ count( $items ) - 1 ];
	}
}

add_filter( 'esc_html', 'ic_esc_html', 10, 2 );

/**
 * Preserves catalog search keyword markup in escaped HTML.
 *
 * @param string $safe_text Escaped text.
 * @param string $text      Original text.
 *
 * @return string
 */
function ic_esc_html( $safe_text, $text ) {
	if ( ic_string_contains( $text, 'ic-search-keyword' ) ) {
		return $text;
	}

	return $safe_text;
}

/**
 * Returns HTTP GET parameters with catalog self-submit compatibility.
 *
 * @return array
 */
function ic_http_get() {
	$self_submit_data = filter_input( INPUT_POST, 'self_submit_data', FILTER_UNSAFE_RAW );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only compatibility shim used by sibling plugins to recover catalog query args from a self-submitted Ajax post.
	if ( empty( $_GET ) && null !== $self_submit_data ) {
		$params = array();
		// parse_str() must run before sanitizing: sanitize_text_field() strips the %xx octets the encoded payload depends on.
		parse_str( $self_submit_data, $params );
		$_GET = ic_sanitize( $params );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Returning the current query args; each caller sanitizes what it consumes.
	return $_GET;
}
