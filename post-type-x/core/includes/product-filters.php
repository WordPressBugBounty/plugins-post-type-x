<?php
/**
 * Product catalog filters and session helpers.
 *
 * @package ecommerce-product-catalog/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Manages product catalog filters.
 *
 * @version 1.4.0
 * @author  impleCode
 */
if ( ! function_exists( 'get_product_catalog_session' ) ) {

	/**
	 * Gets the current product catalog session data.
	 *
	 * @return array
	 */
	function get_product_catalog_session() {
		if ( ! is_admin() || is_ic_front_ajax() ) {
			global $IC_Session; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- Legacy global name retained for backward compatibility.
			$ic_session = &$IC_Session; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- Alias references the legacy global name for compatibility.
			$prefix     = ic_get_session_prefix();
			if ( ic_use_php_session() ) {
				if ( ! ic_is_session_started() && ! headers_sent() && ic_ic_cookie_enabled() && ( ! is_admin() || is_ic_front_ajax() ) ) {
					ic_php_session_start();
				}
				if ( ! isset( $_SESSION[ $prefix ] ) || ( isset( $_SESSION[ $prefix ] ) && ! is_array( $_SESSION[ $prefix ] ) ) ) {
					$_SESSION[ $prefix ] = array();
				}
				if ( empty( $ic_session ) ) {
					if ( isset( $_SESSION[ $prefix ] ) ) {
						// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Session data is restored exactly as stored.
						$ic_session[ $prefix ] = $_SESSION[ $prefix ];
					} else {
						$ic_session[ $prefix ] = array();
					}
				}
				$session = $ic_session[ $prefix ];
			} else {
				$implecode_session = ic_get_global( 'ic_session' );
				if ( ! empty( $implecode_session ) ) {
					$ic_session[ $prefix ] = $implecode_session->get();
				} elseif ( empty( $ic_session ) && class_exists( 'WP_Session' ) ) {
					$ic_session = WP_Session::get_instance();
				}
				if ( ! isset( $ic_session[ $prefix ] ) || ( isset( $ic_session[ $prefix ] ) && ! is_array( $ic_session[ $prefix ] ) ) ) {
					$ic_session[ $prefix ] = array();
				}
				$session = $ic_session[ $prefix ];
			}

			if ( empty( $session ) || ! is_array( $session ) ) {
				$session = array();
			}

			return $session;
		}

		return array();
	}

	/**
	 * Gets the catalog session prefix.
	 *
	 * @return string
	 */
	function ic_get_session_prefix() {
		$prefix = 'implecode';
		if ( is_multisite() ) {
			$prefix .= '_' . get_current_blog_id();
		}

		return $prefix;
	}

}
if ( ! function_exists( 'set_product_catalog_session' ) ) {
	/**
	 * Saves product catalog session
	 *
	 * @param array $session Session data.
	 */
	function set_product_catalog_session( $session ) {
		if ( ic_is_read_only_catalog_session_ajax() ) {
			return;
		}
		if ( ! is_admin() || is_ic_front_ajax() ) {
			global $IC_Session; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- Legacy global name retained for backward compatibility.
			$ic_session = &$IC_Session; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- Alias references the legacy global name for compatibility.
			$prefix     = ic_get_session_prefix();
			if ( ic_use_php_session() ) {
				if ( ! headers_sent() && ic_ic_cookie_enabled() && ( ! is_admin() || is_ic_front_ajax() ) ) {
					if ( ic_is_session_started() ) {
						if ( ! has_action( 'send_headers', 'ic_session_save_end' ) ) {
							add_action( 'send_headers', 'ic_session_save_end' );
							add_filter( 'wp_die_ajax_handler', 'ic_ajax_session_save_end' );
							add_filter( 'wp_redirect', 'ic_ajax_session_save_end' );
							add_action( 'shutdown', 'ic_session_save', 10, 0 );
						}
					} else {
						ic_php_session_start();
					}
				}
			} else {
				$implecode_session = ic_get_global( 'ic_session' );
				if ( ! empty( $implecode_session ) ) {
					$implecode_session->replace( $session );
				} elseif ( empty( $ic_session ) && ic_ic_cookie_enabled() && class_exists( 'WP_Session' ) ) {
					$ic_session = WP_Session::get_instance();
				}
			}
			$ic_session[ $prefix ] = $session;
		}
	}
}

/**
 * Detects read-only frontend AJAX requests that should never persist session changes.
 *
 * These requests only render cart or icon fragments. Saving the full session snapshot
 * during them can clobber a real cart update happening concurrently in another request.
 *
 * @return bool
 */
function ic_is_read_only_catalog_session_ajax() {
	if ( ! is_ic_front_ajax() ) {
		return false;
	}

	$read_only_actions = array(
		'get_quote_cart_button',
		'ic_get_cart_button',
		'ic_get_sitewide_icons',
		'ic_get_cart_content',
	);

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only AJAX action detection checks the current request context.
	$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

	return in_array( $action, $read_only_actions, true );
}

/**
 * Flushes the in-memory session before the handler runs.
 *
 * @param mixed $handler Redirect or die handler.
 * @return mixed
 */
function ic_ajax_session_save_end( $handler ) {
	ic_session_save_end();

	return $handler;
}

/**
 * Starts the PHP session used by the catalog.
 *
 * @return void
 */
function ic_php_session_start() {
	session_start();
	add_action( 'shutdown', 'ic_session_save', 10, 0 );
	add_action( 'shutdown', 'session_write_close', 99, 0 );
	add_action( 'send_headers', 'ic_session_save_end' );
	add_filter( 'wp_die_ajax_handler', 'ic_ajax_session_save_end' );
	add_filter( 'wp_redirect', 'ic_ajax_session_save_end' );
	add_action( 'ic_session_save_end', 'session_write_close', 10, 0 );
	add_action( 'requests-curl.before_request', 'session_write_close', 10, 0 );
}

/**
 * Saves the in-memory catalog session to PHP session storage.
 *
 * @return void
 */
function ic_session_save() {
	global $IC_Session; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- Legacy global name retained for backward compatibility.
	$ic_session = &$IC_Session; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- Alias references the legacy global name for compatibility.
	$prefix     = ic_get_session_prefix();
	if ( ic_is_session_started() && isset( $ic_session[ $prefix ] ) ) {
		if ( empty( $_SESSION[ $prefix ] ) ) {
			$_SESSION[ $prefix ] = array();
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Session data is restored exactly as stored.
		$_SESSION[ $prefix ] = $ic_session[ $prefix ];
	}
}

/**
 * Saves the session and triggers the custom save-end hook.
 *
 * @return void
 */
function ic_session_save_end() {
	ic_session_save();
	do_action( 'ic_session_save_end' );
}

/**
 * Builds the anchor HTML for a single filter option.
 *
 * @param int|string  $id        Filter value.
 * @param string      $what      Filter name.
 * @param string      $label     Link label.
 * @param string|null $css_class Optional CSS classes.
 * @return string
 */
function product_filter_element( $id, $what, $label, $css_class = null ) {
	$category_id = $id;
	$css_class   = isset( $css_class ) ? 'filter-url ' . $css_class : 'filter-url';
	if ( is_product_filter_active( $what ) ) {
		if ( is_product_filter_active( $what, $id ) ) {
			$css_class .= ' active-filter';
			$id         = 'clear';
		} else {
			$css_class .= ' not-active-filter';
		}
	}
	$attr = '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Frontend pagination state is read from the current request.
	if ( is_ic_ajax() && ! empty( $_GET['page'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Frontend pagination state is read from the current request.
		$attr = 'data-page="' . absint( wp_unslash( $_GET['page'] ) ) . '"';
	}
	$final_url = ic_filter_url( $what, $id );

	return apply_filters( 'ic_category_filter_element', '<a class="' . $css_class . '" href="' . esc_url( $final_url ) . '" ' . $attr . '>' . $label . '</a>', $label, $category_id );
}

/**
 * Builds a category filter element for the provided term.
 *
 * @param WP_Term    $category    Category term.
 * @param array|null $posts       Optional posts set.
 * @param bool       $show_count  Whether to show the count.
 * @param bool       $check_count Whether to verify the count.
 * @return string|null
 */
function get_product_category_filter_element( $category, $posts = null, $show_count = true, $check_count = true ) {
	if ( empty( $category->term_id ) ) {
		return '';
	}
	if ( false && is_ic_product_listing() && ! empty( $category->count ) && ! is_product_filters_active() && ! apply_filters( 'ic_force_query_count_calculation', false ) ) {
		$count = $category->count;
	} elseif ( $check_count ) {
		$count = total_product_category_count( $category->term_id, $category->taxonomy, $posts );
	} else {
		$count      = 1;
		$show_count = false;
	}
	if ( empty( $count ) && ! $check_count ) {
		$show_count = false;
	}
	if ( $count > 0 || ! $check_count ) {
		$name = $category->name;
		if ( $show_count ) {
			$name .= ' <span class="ic-catalog-category-count">(' . $count . ')</span>';
		}

		return product_filter_element( $category->term_id, 'product_category', $name );
	}
}

add_action( 'wp_loaded', 'set_product_filter' );

/**
 * Sets up active filters from the current request.
 *
 * @return void
 */
function set_product_filter() {
	if ( is_ic_admin() || ( is_ic_ajax() && ! is_ic_front_ajax() ) ) {

		return;
	}
	$session = get_product_catalog_session();
	$save    = false;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, bookmarkable catalog filter parameter that only affects the current visitor's own catalog session.
	if ( isset( $_GET['product_category'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Public catalog filter parameter affecting only the current visitor's session; the raw value is intval()'d on the next line.
		$product_category = wp_unslash( $_GET['product_category'] );
		$filter_value     = apply_filters( 'ic_catalog_save_product_filter_value', intval( $product_category ), $product_category );
		if ( ! empty( $filter_value ) ) {
			if ( ! isset( $session['filters'] ) ) {
				$session['filters'] = array();
			}
			$session['filters']['product_category'] = $filter_value;
			$save                                   = true;
		} elseif ( isset( $session['filters']['product_category'] ) ) {
			unset( $session['filters']['product_category'] );
			$save = true;
		}
	} elseif ( isset( $session['filters']['product_category'] ) ) {
		unset( $session['filters']['product_category'] );
		$save = true;
	}
	if ( $save ) {
		set_product_catalog_session( $session );
	}
	do_action( 'ic_set_product_filters', $session );
	$session = get_product_catalog_session();
	if ( ! empty( $session['filters'] ) && empty( $session['filters']['filtered-url'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public catalog Ajax passes the current frontend URL; it is only stored in the caller's own catalog session.
		if ( isset( $_POST['request_url'] ) && is_ic_ajax() ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public catalog Ajax passes the current frontend URL; esc_url_raw() sanitizes it and it is only stored in the caller's own catalog session.
			$session['filters']['filtered-url'] = esc_url_raw( wp_unslash( $_POST['request_url'] ) );
		} else {
			$active_filters                     = get_active_product_filters();
			$session['filters']['filtered-url'] = remove_query_arg( $active_filters, get_pagenum_link( 1, false ) );
		}
		if ( ic_string_contains( $session['filters']['filtered-url'], '&s=' ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Presence-only read of a public catalog Ajax flag that only shapes the caller's own session URL.
			if ( is_ic_ajax() && empty( $_POST['is_search'] ) ) {
				$session['filters']['filtered-url'] = remove_query_arg(
					array(
						's',
						'post_type',
					),
					$session['filters']['filtered-url']
				);
			} else {
				$session['filters']['filtered-url'] = add_query_arg( 'reset_filters', 'y', $session['filters']['filtered-url'] );
			}
		}
		if ( is_ic_shortcode_query() ) {
			$session['filters']['filtered-url'] = add_query_arg( 'reset_filters', 'y', $session['filters']['filtered-url'] );
		}
		if ( ic_string_contains( $session['filters']['filtered-url'], '/wp-admin/' ) || ic_string_contains( $session['filters']['filtered-url'], '/wp-json/' ) ) {
			$session['filters']['filtered-url'] = '';
		} else {
			$session['filters']['filtered-url'] = $session['filters']['filtered-url'] . '#product_filters_bar';
		}
		set_product_catalog_session( $session );
	}
}

add_action( 'wp_loaded', 'delete_product_filters', 11 );

/**
 * Clears current filters if there is a page reload without new filter assignment
 */
function delete_product_filters() {
	if ( is_ic_ajax() ) {
		return;
	}
	$keep_filters              = true;
	$force_remove_filtered_url = false;
	if ( ! is_product_filters_active() ) {
		$keep_filters              = false;
		$force_remove_filtered_url = true;
		// The session is cleared below after permanent filters are preserved.
	} elseif ( ! is_search() || isset( $_GET['reset_filters'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, bookmarkable catalog filter parameter that only affects the current visitor's own catalog session.
		$active_filters = get_active_product_filters();
		$keep_filters   = false;
		foreach ( $active_filters as $filter ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, bookmarkable catalog filter parameter that only affects the current visitor's own catalog session.
			if ( isset( $_GET[ $filter ] ) ) {
				$keep_filters = true;
				break;
			}
		}
	}
	if ( ! $keep_filters ) {
		$session = get_product_catalog_session();
		if ( ! isset( $session['permanent-filters'] ) || ! is_array( $session['permanent-filters'] ) ) {
			$session['permanent-filters'] = array();
		}
		if ( ! empty( $session['filters'] ) ) {
			foreach ( $session['filters'] as $filter_name => $filter_value ) {
				if ( ! in_array( $filter_name, $session['permanent-filters'], true ) ) {
					unset( $session['filters'][ $filter_name ] );
				}
			}
		}
		if ( $force_remove_filtered_url || ( count( $session['filters'] ) === 1 && isset( $session['filters']['filtered-url'] ) ) ) {
			unset( $session['filters']['filtered-url'] );
		}
		if ( empty( $session['filters'] ) ) {
			unset( $session['filters'] );
		}
		set_product_catalog_session( $session );
	}
}

/**
 * Defines active product filters
 *
 * @param bool $values Whether to return filter values.
 * @param bool $encode Whether to encode returned values.
 * @return array
 */
function get_active_product_filters( $values = false, $encode = false ) {
	$active_filters = ic_get_global( 'ic_active_product_filters' );
	if ( false === $active_filters ) {
		$active_filters = apply_filters(
			'active_product_filters',
			array(
				'product_category',
				'min-price',
				'max-price',
				'product_order',
			)
		);
		ic_save_global( 'ic_active_product_filters', $active_filters, false, false, true );
	}

	if ( $values ) {
		$filters = array();
		foreach ( $active_filters as $filter_name ) {
			$filter_value = get_product_filter_value( $filter_name );
			if ( ! empty( $filter_value ) ) {
				if ( $encode ) {
					$filter_value = ic_urlencode( $filter_value );
				}
				$filters[ $filter_name ] = $filter_value;
			}
		}

		return $filters;
	}

	return $active_filters;
}

/**
 * URL-encodes scalars and arrays recursively.
 *
 * @param mixed $data Data to encode.
 * @return mixed
 */
function ic_urlencode( $data ) {
	if ( is_array( $data ) ) {
		$data = array_map( 'ic_urlencode', $data );
	} else {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode -- Legacy encoding behavior is preserved for compatibility.
		$data = urlencode( $data );
	}

	return $data;
}

/**
 * Gets the current value for a filter.
 *
 * @param string $filter_name Filter name.
 * @param bool   $encode      Whether to encode the returned value.
 * @return mixed
 */
function get_product_filter_value( $filter_name, $encode = false ) {
	$filter_value = '';
	if ( is_product_filter_active( $filter_name ) ) {
		$session = get_product_catalog_session();
		if ( isset( $session['filters'][ $filter_name ] ) ) {
			if ( $encode ) {
				$filter_value = htmlentities( $session['filters'][ $filter_name ] );
			} else {
				$filter_value = $session['filters'][ $filter_name ];
			}
		}
	}

	return apply_filters( 'ic_epc_filter_value', $filter_value, $filter_name );
}

add_action( 'ic_pre_get_products_only', 'apply_product_filters', 20 );

/**
 * Applies current filters to the query
 *
 * @param object $query Query object.
 */
function apply_product_filters( $query ) {
	if ( ! empty( $query->query['pagename'] ) ) {
		return;
	}
	ic_set_pre_filters_query_vars( $query->query );
	global $ic_product_filters_query;
	if ( is_product_filters_active() ) {
		do_action( 'apply_product_filters_active_start', $query );
		if ( is_product_filter_active( 'product_category' ) ) {
			$category_id = get_product_filter_value( 'product_category' );
			$taxonomy    = get_current_screen_tax();
			if ( empty( $query->query['ic_exclude_tax'] ) || ( ! empty( $query->query['ic_exclude_tax'] ) && ! in_array( $taxonomy, $query->query['ic_exclude_tax'], true ) ) ) {
				$tax_query = $query->get( 'tax_query' );
				if ( empty( $tax_query ) ) {
					$tax_query = array();
				}
				if ( is_array( $category_id ) ) {
					$category_id = array_values( $category_id );
				}
					$filter_tax_query = array(
						'taxonomy' => $taxonomy,
						'terms'    => $category_id,
					);
					if ( ! in_array( $filter_tax_query, $tax_query, true ) ) {
						$tax_query[] = $filter_tax_query;
					}
					$query->set( 'tax_query', apply_filters( 'ic_catalog_product_category_filter_query', $tax_query ) );
			}
		}
	}
	do_action( 'apply_product_filters', $query );
	if ( is_product_filters_active() ) {
		$ic_product_filters_query = $query;
	}
}

add_filter( 'ic_filterable_query', 'apply_product_category_filter' );
add_filter( 'shortcode_query', 'apply_product_category_filter' );
add_filter( 'home_product_listing_query', 'apply_product_category_filter' );
add_filter( 'category_count_query', 'apply_product_category_filter', 10, 2 );

/**
 * Applies product category filter to shortcode query
 *
 * @param array      $shortcode_query Shortcode query args.
 * @param string|int $taxonomy        Optional taxonomy constraint.
 *
 * @return array
 */
function apply_product_category_filter( $shortcode_query, $taxonomy = null ) {
	if ( ! empty( $taxonomy ) && ! is_array( $taxonomy ) && ic_string_contains( $taxonomy, 'al_product-cat' ) ) {
		return $shortcode_query;
	}
	ic_set_pre_filters_query_vars( $shortcode_query, true );
	if ( is_product_filter_active( 'product_category' ) ) {
		$category_id = get_product_filter_value( 'product_category' );
		if ( empty( $shortcode_query['tax_query'] ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- This sets WP_Query arguments for catalog filtering.
			$shortcode_query['tax_query'] = array();
		}
		if ( is_array( $category_id ) ) {
			$category_id = array_values( $category_id );
		}
			$tax_query = apply_filters(
				'ic_catalog_product_category_filter_query',
				array(
					'taxonomy' => get_current_screen_tax(),
					'terms'    => $category_id,
				)
			);
		if ( ! in_array( $tax_query, $shortcode_query['tax_query'], true ) ) {
			$shortcode_query['tax_query'][] = $tax_query;
		}
	}

	return apply_filters( 'apply_shortcode_product_filters', $shortcode_query, $taxonomy );
}

add_filter( 'product_search_button_text', 'modify_search_widget_filter' );

/**
 * Deletes search button text in filter bar
 *
 * @param string $text Search button text.
 *
 * @return string
 */
function modify_search_widget_filter( $text ) {
	if ( is_filter_bar() ) {
		$text = '';
	}

	return $text;
}

add_action( 'wp_ajax_hide_empty_bar_message', 'hide_empty_bar_message' );

/**
 * Ajax receiver to hide the filters bar empty message
 */
function hide_empty_bar_message() {
	$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	// phpcs:ignore WordPress.WP.Capabilities.Unknown -- Custom capability is registered by the plugin.
	if ( ! empty( $nonce ) && wp_verify_nonce( $nonce, 'ic-ajax-nonce' ) && current_user_can( 'manage_product_settings' ) ) {
		update_option( 'hide_empty_bar_message', 1, false );
	}
	wp_die();
}

/**
 * Returns the URL to redirect after filters reset
 *
 * @return type
 */
function get_filters_bar_reset_url() {
	if ( ! is_product_filters_active() ) {

		return '';
	}
	$session = get_product_catalog_session();
	if ( ! empty( $session['filters']['filtered-url'] ) ) {
		return $session['filters']['filtered-url'];
	}

	return '';
}

add_action( 'ic_catalog_wp', 'ic_set_catalog_query', 9 );
add_action( 'before_ajax_product_list', 'ic_set_catalog_query', 9 );

/**
 * Stores the current catalog query in global state.
 *
 * @return void
 */
function ic_set_catalog_query() {
	$catalog_query = ic_get_catalog_query();
	if ( ! $catalog_query ) {
		if ( is_home_archive() || ( ! more_products() && is_custom_product_listing_page() ) ) {
			$catalog_query = ic_set_home_listing_query();
			if ( ! empty( $catalog_query ) && ! empty( $catalog_query->query_vars ) ) {
				ic_save_global( 'catalog_query', $catalog_query );
			}
		} elseif ( ! empty( $GLOBALS['wp_query'] ) && ! empty( $GLOBALS['wp_query']->query_vars ) ) {
			ic_save_global( 'catalog_query', $GLOBALS['wp_query'] );
		}
		ic_get_product_id();
		ic_get_current_category_id();
	}
}

/**
 * Gets the stored catalog query.
 *
 * @param bool $fallback_to_global Whether to fall back to the global query.
 * @return mixed
 */
function ic_get_catalog_query( $fallback_to_global = false ) {
	$catalog_query = ic_get_global( 'catalog_query' );
	if ( $fallback_to_global && empty( $catalog_query ) ) {
		global $wp_query;
		$catalog_query = $wp_query;
	}

	return $catalog_query;
}

/**
 * Stores query vars before product filters are applied.
 *
 * @param array|null $query_vars Query vars.
 * @param bool       $force_save Whether to overwrite existing stored vars.
 * @return void
 */
function ic_set_pre_filters_query_vars( $query_vars = null, $force_save = false ) {
	if ( ! ic_get_global( 'catalog_pre_filters_query' ) || $force_save ) {
		if ( empty( $query_vars ) ) {
			global $wp_query;
			$query_vars = $wp_query->query;
		}
		ic_save_global( 'catalog_pre_filters_query_vars', $query_vars );
	}
}

/**
 * Gets stored catalog query vars.
 *
 * @param bool $fallback_to_global Whether to fall back to the global query.
 * @param bool $pre_filters        Whether to return pre-filter query vars.
 * @return array
 */
function ic_get_catalog_query_vars( $fallback_to_global = false, $pre_filters = false ) {
	if ( $pre_filters ) {
		$catalog_query_vars = ic_get_global( 'catalog_pre_filters_query_vars' );
	}
	if ( empty( $catalog_query_vars ) ) {
		$catalog_query = ic_get_catalog_query( $fallback_to_global );
		if ( ! empty( $catalog_query->query ) ) {
			$catalog_query_vars = $catalog_query->query;
		}
	}
	if ( empty( $catalog_query_vars ) ) {
		$catalog_query_vars = array();
	}

	return $catalog_query_vars;
}

/**
 * Gets filter taxonomies keyed by taxonomy name.
 *
 * @param bool $only_tax Whether to return only taxonomy names.
 * @return array
 */
function ic_filter_taxonomies( $only_tax = false ) {
	$filter_taxonomies = apply_filters( 'ic_filter_taxonomies', array( 'al_product-cat' => 'product_category' ) );
	if ( $only_tax ) {
		return array_keys( $filter_taxonomies );
	}

	return $filter_taxonomies;
}

/**
 * Builds the active filters HTML.
 *
 * @return string
 */
function ic_get_active_filters_html() {
	$inside = '';
	if ( is_product_filters_active() ) {
		$active_filters = get_active_product_filters( true );
		foreach ( $active_filters as $filter_name => $filter_value ) {
			if ( 'all' === $filter_value ) {
				continue;
			}
			$inside .= ic_get_active_filter_html( $filter_name, $filter_value );
		}
	}
	$out  = '<div class="ic-active-filters ic_ajax" data-ic_responsive_label="' . __( 'Active Filters', 'post-type-x' ) . '" data-ic_ajax="ic-active-filters">';
	$out .= $inside;
	$out .= '</div>';

	return $out;
}

/**
 * Builds a single active filter HTML element.
 *
 * @param string     $filter_name  Filter name.
 * @param mixed      $filter_value Filter value.
 * @param array|bool $new_value    Optional updated filter value.
 * @return string
 */
function ic_get_active_filter_html( $filter_name, $filter_value = null, $new_value = false ) {
	if ( is_array( $filter_value ) && empty( $new_value ) && ! ic_string_contains( $filter_name, '_size_' ) ) {
		$out = '';
		foreach ( $filter_value as $key => $val ) {
			if ( is_array( $val ) ) {
				continue;
			}
			if ( '' === $filter_name ) {
				$new_filter_value = array();
			} else {
				$new_filter_value = $filter_value;
				unset( $new_filter_value[ $key ] );
			}
			$out .= ic_get_active_filter_html( $filter_name, $val, $new_filter_value );
		}
		if ( ! empty( $out ) ) {
			return $out;
		}
	}
	if ( ! is_product_filter_active( $filter_name, $filter_value ) && apply_filters( 'ic_active_filter_html_check_active', true, $filter_name, $filter_value ) ) {
		return '';
	}
	$out = apply_filters( 'ic_get_active_filter_html', '', $filter_name, $new_value );
	if ( empty( $out ) ) {
		$out                   = '<div class="ic-active-filter"><span class="ic-active-filter-name">' . esc_html( ic_filter_friendly_name( $filter_name ) ) . '</span>';
		$friendly_filter_value = ic_filter_friendly_value( $filter_value, $filter_name );
		if ( ! empty( $friendly_filter_value ) && ! is_numeric( $friendly_filter_value ) ) {
			if ( 'Ic_zero_count' === $friendly_filter_value ) {
				$out = str_replace( 'class="ic-active-filter"', 'class="ic-active-filter ic-active-filter-zero-count"', $out );
			} elseif ( ic_string_contains( $friendly_filter_value, '(0)' ) ) {
				$out = str_replace( 'class="ic-active-filter"', 'class="ic-active-filter ic-active-filter-zero-count-visible"', $out );
			}
			$out .= '<span class="ic-active-filter-value">: ' . esc_html( $friendly_filter_value ) . '</span>';
		}
		if ( ! empty( $new_value ) && is_array( $new_value ) ) {
			foreach ( $new_value as $new_value_key => $new_value_val ) {
				$friendly_value = ic_filter_friendly_value( $new_value_val, $filter_name );
				if ( 'Ic_zero_count' === $friendly_value || ic_string_contains( $friendly_value, '(0)' ) ) {
					unset( $new_value[ $new_value_key ] );
				}
			}
		}
		$out .= '<a class="ic-remove-active-filter" href="' . esc_url( ic_filter_url( $filter_name, $new_value ) ) . '"><span class="dashicons dashicons-no-alt"></span></a>';
		$out .= '</div>';
	}

	return $out;
}

/**
 * Converts a filter slug into a human-readable label.
 *
 * @param string $filter_name Filter name.
 * @return string
 */
function ic_filter_friendly_name( $filter_name ) {
	$friendly_name = str_replace(
		array(
			'_',
			'-',
			'product',
		),
		' ',
		apply_filters( 'ic_filter_friendly_name', $filter_name )
	);

	return trim( ucwords( $friendly_name ) );
}

/**
 * Converts a filter value into a human-readable value.
 *
 * @param mixed       $filter_value Filter value.
 * @param string|bool $filter_name  Filter name.
 * @return string
 */
function ic_filter_friendly_value( $filter_value, $filter_name = false ) {
	if ( is_numeric( $filter_value ) ) {
		$friendly_value = ic_filter_numeric_friendly_value( $filter_value );
	} elseif ( is_array( $filter_value ) ) {
		$friendly_value = array();
		foreach ( $filter_value as $val ) {
			if ( is_numeric( $val ) ) {
				$friendly_value[] = ic_filter_numeric_friendly_value( $val );
			}
		}
	} elseif ( $filter_name ) {
		$friendly_value = apply_filters( 'ic_filter_friendly_value', array(), $filter_value, $filter_name );
	}
	if ( ! empty( $friendly_value ) && is_array( $friendly_value ) ) {
		$friendly_value = array_filter( $friendly_value );
	}
	if ( ! empty( $friendly_value ) ) {
		$friendly_value = is_array( $friendly_value ) ? implode( ', ', $friendly_value ) : $friendly_value;
	} else {
		$friendly_value = is_array( $filter_value ) ? implode( ', ', $filter_value ) : $filter_value;
	}

	return ucwords( $friendly_value );
}

/**
 * Converts a numeric filter value into a label.
 *
 * @param mixed $filter_value   Filter value.
 * @param bool  $check_if_empty Whether to flag zero-count terms.
 * @return string
 */
function ic_filter_numeric_friendly_value( $filter_value, $check_if_empty = false ) {
	$friendly_value = '';
	if ( is_numeric( $filter_value ) ) {
		$term = get_term( $filter_value );
		if ( empty( $term ) || is_wp_error( $term ) || empty( $term->name ) || ! ic_string_contains( $term->taxonomy, 'al_product' ) ) {
			return '';
		}
			$count = total_product_category_count( $term->term_id, $term->taxonomy );
		if ( $check_if_empty && empty( $count ) ) {
			$friendly_value = 'Ic_zero_count';
		} else {
			$friendly_value = $term->name . ' (' . $count . ')';
		}
	}

	return $friendly_value;
}

/**
 * Builds a filter URL preserving active filters.
 *
 * @param string     $filter_name  Filter name.
 * @param array|bool $filter_value Filter value.
 * @return string
 */
function ic_filter_url( $filter_name, $filter_value = false ) {
	$original_filter_name = $filter_name;
	$filter_name          = apply_filters( 'ic_filter_url_filter_name', $filter_name );
	if ( ! is_ic_catalog_page() && ! ic_get_global( 'inside_show_catalog_shortcode' ) ) {
		$url = product_listing_url();
	} elseif ( is_paged() ) {
		if ( is_ic_permalink_product_catalog() ) {
			$url = remove_query_arg( $filter_name );
		} else {
			$url = remove_query_arg( array( 'paged', $filter_name ) );
		}
	} elseif ( is_ic_ajax() ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Frontend AJAX requests preserve current filter query arguments.
		$url = add_query_arg( wp_unslash( $_GET ) );
		$url = remove_query_arg( array( $filter_name ), $url );
	} else {
		$url = remove_query_arg( array( $filter_name ) );
	}

	$active_filters = get_active_product_filters( true );
	unset( $active_filters[ $filter_name ] );
	$active_filters = apply_filters( 'ic_filter_url_active_filters', $active_filters, $original_filter_name, $filter_value );
	if ( ! empty( $active_filters ) && is_array( $active_filters ) ) {
		foreach ( $active_filters as $active_filter_name => $active_filter_value ) {
			if ( is_array( $active_filter_value ) ) {
				foreach ( $active_filter_value as $active_filter_value_key => $active_filter_value_val ) {
					$friendly_value = ic_filter_friendly_value( $active_filter_value_val, $active_filter_name );
					if ( 'Ic_zero_count' === $friendly_value || ic_string_contains( $friendly_value, '(0)' ) || ! is_product_filter_active( $active_filter_name, $active_filter_value_val ) ) {
						if ( ! is_array( $active_filters[ $active_filter_name ][ $active_filter_value_key ] ) ) {
							unset( $active_filters[ $active_filter_name ][ $active_filter_value_key ] );
						}
					}
				}
			} else {
				$friendly_value = ic_filter_friendly_value( $active_filter_value, $active_filter_name );
				if ( 'Ic_zero_count' === $friendly_value || ic_string_contains( $friendly_value, '(0)' ) || ! is_product_filter_active( $active_filter_name, $active_filter_value ) ) {
					unset( $active_filters[ $active_filter_name ] );
				}
			}
		}
	}
	if ( false === $filter_value ) {
		$filter_value = 'all';
	}
	if ( $filter_value ) {
		$final_url = add_query_arg( array_merge( array( $filter_name => ic_urlencode( $filter_value ) ), ic_urlencode( $active_filters ) ), $url );
	} else {
		$final_url = add_query_arg( ic_urlencode( $active_filters ), $url );
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Search query args are preserved from the current frontend request.
	if ( ! empty( $_GET['s'] ) && ! empty( $_GET['post_type'] ) ) {
		$final_url = add_query_arg(
			array(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Search query args are preserved from the current frontend request.
				's'         => ic_urlencode( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ),
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Search query args are preserved from the current frontend request.
				'post_type' => ic_urlencode( sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) ),
			),
			$final_url
		);
	}

	return $final_url;
}
