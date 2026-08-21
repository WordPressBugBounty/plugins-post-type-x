<?php
/**
 * Product Ajax manager.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles catalog Ajax requests and refreshed widget output.
 */
class IC_Catalog_Ajax {

	/**
	 * Current Ajax request URL.
	 *
	 * @var string
	 */
	private $request_url = '';

	/**
	 * Current Ajax element metadata.
	 *
	 * @var array
	 */
	private $ajax_elements = array();

	/**
	 * Current serialized self-submit payload.
	 *
	 * @var string
	 */
	private $self_submit_data = '';

	/**
	 * Sets up Ajax hooks.
	 */
	public function __construct() {
		add_action( 'wp_loaded', array( $this, 'ajax_get' ), 5 );
		add_action( 'wp_ajax_nopriv_ic_self_submit', array( $this, 'ajax_self_submit' ) );
		add_action( 'wp_ajax_ic_self_submit', array( $this, 'ajax_self_submit' ) );
		add_action( 'register_catalog_styles', array( $this, 'register_styles' ) );
		add_action( 'enqueue_main_catalog_scripts', array( 'IC_Catalog_Ajax', 'enqueue_styles' ) );
		add_filter( 'product-list-attr', array( $this, 'shortcode_query_data' ), 10, 2 );
	}

	/**
	 * Maps serialized admin self-submit data onto the request query vars.
	 *
	 * This replaces the whole `$_GET` superglobal for the rest of the request, so it is limited to
	 * logged-in users presenting a valid `ic_ajax` nonce, exactly like `ajax_self_submit()`.
	 * `is_admin()` alone is not a guard: it is true on `admin-ajax.php` for `nopriv` actions too.
	 *
	 * @return void
	 */
	public function ajax_get() {
		if ( ! is_admin() || ! is_user_logged_in() ) {
			return;
		}
		$security = isset( $_POST['security'] ) ? sanitize_text_field( wp_unslash( $_POST['security'] ) ) : '';
		if ( empty( $security ) || ! wp_verify_nonce( $security, 'ic_ajax' ) ) {
			return;
		}
		if ( ! empty( $_POST['self_submit_data'] ) ) {
			$params = array();
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The serialized payload is sanitized with ic_sanitize() after parse_str() below.
			$this->self_submit_data = wp_unslash( $_POST['self_submit_data'] );
			parse_str( $this->self_submit_data, $params );
			$_GET = array_map( 'ic_sanitize', $params );
		}
	}

	/**
	 * Handles Ajax self-submissions and returns refreshed catalog fragments.
	 *
	 * @return void
	 */
	public function ajax_self_submit() {
		$ic_nonce = isset( $_POST['ic_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ic_nonce'] ) ) : '';
		if ( empty( $ic_nonce ) || ! wp_verify_nonce( $ic_nonce, 'ic_catalog_self_submit' ) ) {
			wp_die( -1, 403 );
		}
		if ( is_user_logged_in() && ! current_user_can( 'read' ) ) {
			wp_die( -1, 403 );
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The serialized payload is sanitized after parsing.
		$this->self_submit_data = isset( $_POST['self_submit_data'] ) ? wp_unslash( $_POST['self_submit_data'] ) : '';
		$security               = isset( $_POST['security'] ) ? sanitize_text_field( wp_unslash( $_POST['security'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON is decoded and sanitized before use.
		$query_vars_json   = isset( $_POST['query_vars'] ) ? wp_unslash( $_POST['query_vars'] ) : '';
		$this->request_url = isset( $_POST['request_url'] ) ? esc_url_raw( wp_unslash( $_POST['request_url'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Ajax element metadata is normalized before use.
		$this->ajax_elements = isset( $_POST['ajax_elements'] ) && is_array( $_POST['ajax_elements'] ) ? wp_unslash( $_POST['ajax_elements'] ) : array();

		if ( ! empty( $this->self_submit_data ) && ! empty( $security ) && wp_verify_nonce( $security, 'ic_ajax' ) ) {
			ic_set_time_limit( 3 );
			remove_filter( 'parse_tax_query', 'exclude_products_from_child_cat' );
			$params = array();
			parse_str( $this->self_submit_data, $params );
			$params = array_map( 'ic_sanitize', $params );
			$_GET   = $params;
			global $ic_ajax_query_vars;
			$ic_ajax_query_vars = apply_filters( 'ic_catalog_query', json_decode( $query_vars_json, true ) );
			if ( ! empty( $ic_ajax_query_vars ) ) {
				$ic_ajax_query_vars = ic_sanitize( $ic_ajax_query_vars );
			}
			$pre_ic_ajax_query_vars = $ic_ajax_query_vars;
			unset( $ic_ajax_query_vars['pagename'] );
			unset( $ic_ajax_query_vars['page_id'] );
			do_action( 'ic_ajax_self_submit_init', $ic_ajax_query_vars, $params, $pre_ic_ajax_query_vars );
			if ( isset( $ic_ajax_query_vars['post_type'] ) && ! is_ic_valid_post_type( $ic_ajax_query_vars['post_type'] ) ) {
				wp_die();

				return;
			}

			do_action( 'ic_ajax_self_submit', $ic_ajax_query_vars, $params );

			if ( isset( $params['s'] ) ) {
				$ic_ajax_query_vars['s'] = $params['s'];
				foreach ( $ic_ajax_query_vars as $query_var_key => $query_var_value ) {
					unset( $query_var_value );
					if ( ic_string_contains( $query_var_key, 'al_product-cat' ) ) {
						unset( $ic_ajax_query_vars[ $query_var_key ] );
					}
				}
				$ic_ajax_query_vars = array_merge( $ic_ajax_query_vars, $params );
			}
			if ( isset( $params['page'] ) ) {
				$ic_ajax_query_vars['paged'] = $params['page'];
			}
			if ( ! empty( $ic_ajax_query_vars['post_type'] ) && ! ic_string_contains( $ic_ajax_query_vars['post_type'], 'al_product' ) ) {
				$_GET['post_type'] = $ic_ajax_query_vars['post_type'];
			}
			$ic_ajax_query_vars['post_status'] = ic_visible_product_status();
			if ( ! empty( $ic_ajax_query_vars['posts_per_page'] ) ) {
				remove_action( 'ic_pre_get_products', 'set_products_limit', 99 );
				remove_action( 'pre_get_posts', 'set_multi_products_limit', 99 );
			}
			add_filter( 'parse_tax_query', 'exclude_products_from_child_cat' );
			$posts = apply_filters( 'ic_catalog_ajax_posts', '', $ic_ajax_query_vars );
			if ( empty( $posts ) ) {
				foreach ( $ic_ajax_query_vars as $query_var_key => $query_var_value ) {
					$GLOBALS['wp_query']->set( $query_var_key, $query_var_value );
				}
				$GLOBALS['wp_query']->get_posts();
				$posts = $GLOBALS['wp_query'];
			}
			$response = array();
			if ( ! empty( $ic_ajax_query_vars['paged'] ) && $ic_ajax_query_vars['paged'] > 1 && empty( $posts->post ) ) {
				unset( $ic_ajax_query_vars['paged'] );
				$GLOBALS['wp_query']->set( 'paged', false );
				unset( $_GET['page'] );
				$response['remove_pagination'] = 1;
				$GLOBALS['wp_query']->get_posts();
				$posts = $GLOBALS['wp_query'];
			}
			remove_filter( 'parse_tax_query', 'exclude_products_from_child_cat' );
			if ( ! empty( $posts->query['post_type'] ) && 2 === count( $posts->query ) && ic_string_contains( $posts->query['post_type'], 'al_product' ) ) {
				$posts->is_post_type_archive = true;
			}
			if ( ! empty( $_POST['ic_shortcode'] ) ) {
				global $shortcode_query;
				$shortcode_query = $posts;
			}
			if ( ! empty( $ic_ajax_query_vars['archive_template'] ) ) {
				$archive_template = $ic_ajax_query_vars['archive_template'];
			} else {
				$archive_template = get_product_listing_template();
			}
			$multiple_settings = get_multiple_settings();
			remove_all_actions( 'before_product_list' );
			ob_start();
			do_action( 'before_ajax_product_list', $GLOBALS['wp_query'] );
			ic_product_listing_products( $archive_template, $multiple_settings );
			$response['product-listing'] = ob_get_clean();
			$old_request_url             = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			$_SERVER['REQUEST_URI']      = $this->get_ajax_request_url();
			ob_start();
			add_filter( 'get_pagenum_link', array( $this, 'pagenum_link' ) );
			product_archive_pagination();
			remove_filter( 'get_pagenum_link', array( $this, 'pagenum_link' ) );
			$response['product-pagination'] = ob_get_clean();
			if ( ! empty( $this->ajax_elements['product-category-filter-container'] ) ) {
				$category_filter_settings                      = $this->get_ajax_widget_settings( 'product-category-filter-container' );
				$response['product-category-filter-container'] = $this->capture_ajax_output(
					'product-category-filter-container',
					function () use ( $category_filter_settings ) {
						the_widget( 'Product_Category_Filter', $category_filter_settings['instance'], $category_filter_settings['args'] );
					}
				);
			}
			if ( ! empty( $this->ajax_elements['price-filter'] ) ) {
				$price_filter_settings    = $this->get_ajax_widget_settings( 'price-filter' );
				$response['price-filter'] = $this->capture_ajax_output(
					'price-filter',
					function () use ( $price_filter_settings ) {
						the_widget( 'product_price_filter', $price_filter_settings['instance'], $price_filter_settings['args'] );
					}
				);
			}
			if ( ! empty( $this->ajax_elements['product-size-filter-container'] ) ) {
				$response['product-size-filter-container'] = $this->capture_ajax_output(
					'product-size-filter-container',
					function () {
						the_widget( 'IC_Product_Size_Filter' );
					}
				);
			}
			if ( ! empty( $this->ajax_elements['product_order'] ) ) {
				$response['product_order'] = $this->capture_ajax_output(
					'product_order',
					function () {
						the_widget( 'product_sort_filter' );
					}
				);
			}
			if ( ! empty( $this->ajax_elements['product-sort-bar'] ) ) {
				$response['product-sort-bar'] = $this->capture_ajax_output(
					'product-sort-bar',
					function () use ( $archive_template, $multiple_settings ) {
						show_product_sort_bar( $archive_template, $multiple_settings );
					}
				);
			}
			if ( ! empty( $this->ajax_elements['ic-active-filters'] ) ) {
				$response['ic-active-filters'] = $this->capture_ajax_value(
					'ic-active-filters',
					function () {
						return ic_get_active_filters_html();
					},
					''
				);
			}
			$response               = $this->apply_ajax_return_filters( $response );
			$_SERVER['REQUEST_URI'] = $old_request_url;
			$encoded                = array();
			foreach ( $response as $key => $string ) {
				if ( function_exists( 'mb_convert_encoding' ) ) {
					$encoded[ $key ] = mb_convert_encoding( $string, 'UTF-8', 'UTF-8' );
				} elseif ( function_exists( 'iconv' ) ) {
					$encoded[ $key ] = iconv( 'UTF-8', 'UTF-8', $string );
				} else {
					$encoded[ $key ] = $string;
				}
			}
			echo wp_json_encode( $encoded );
		}
		wp_die();
	}

	/**
	 * Safely captures dynamic Ajax HTML output.
	 *
	 * @param string   $context  Output context label for logging.
	 * @param callable $callback Output callback.
	 * @param string   $fallback Optional fallback output.
	 * @return string
	 */
	public function capture_ajax_output( $context, $callback, $fallback = '' ) {
		ob_start();
		try {
			call_user_func( $callback );

			return ob_get_clean();
		} catch ( Throwable $throwable ) {
			ob_end_clean();
			$this->log_ajax_refresh_error( $context, $throwable );
		}

		return $fallback;
	}

	/**
	 * Safely captures a computed Ajax value.
	 *
	 * @param string   $context  Value context label for logging.
	 * @param callable $callback Value callback.
	 * @param mixed    $fallback Optional fallback value.
	 * @return mixed
	 */
	public function capture_ajax_value( $context, $callback, $fallback = '' ) {
		try {
			return call_user_func( $callback );
		} catch ( Throwable $throwable ) {
			$this->log_ajax_refresh_error( $context, $throwable );
		}

		return $fallback;
	}

	/**
	 * Applies Ajax return filters safely.
	 *
	 * @param array $response Current Ajax response payload.
	 * @return array
	 */
	public function apply_ajax_return_filters( $response ) {
		try {
			$filtered = apply_filters( 'ic_ajax_self_submit_return', $response );
			if ( is_array( $filtered ) ) {
				return $filtered;
			}
		} catch ( Throwable $throwable ) {
			$this->log_ajax_refresh_error( 'ic_ajax_self_submit_return', $throwable );
		}

		return $response;
	}

	/**
	 * Normalizes widget refresh settings from Ajax element metadata.
	 *
	 * @param string $element_name Ajax element name.
	 * @return array
	 */
	public function get_ajax_widget_settings( $element_name ) {
		if ( empty( $this->ajax_elements[ $element_name ] ) ) {
			return array(
				'instance' => array(),
				'args'     => array(),
			);
		}

		// The payload is client supplied, so wrapper markup and instance values are sanitized here.
		return ic_sanitize_widget_ajax_settings( $this->ajax_elements[ $element_name ] );
	}

	/**
	 * Logs Ajax refresh failures when debug logging is enabled.
	 *
	 * @param string    $context   Failing Ajax refresh context.
	 * @param Throwable $throwable Captured throwable.
	 * @return void
	 */
	public function log_ajax_refresh_error( $context, $throwable ) {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			ic_error_log(
				sprintf(
					'ic_catalog_ajax %1$s failed: %2$s in %3$s:%4$d',
					$context,
					$throwable->getMessage(),
					$throwable->getFile(),
					$throwable->getLine()
				)
			);
		}
	}

	/**
	 * Registers the front-end Ajax script.
	 *
	 * @return void
	 */
	public function register_styles() {
		wp_register_script(
			'ic_product_ajax',
			AL_PLUGIN_BASE_PATH . 'js/product-ajax.min.js',
			array( 'al_product_scripts' ),
			ic_filemtime( AL_BASE_PATH . '/js/product-ajax.min.js', true ),
			true
		);
	}

	/**
	 * Enqueues and localizes the front-end Ajax script.
	 *
	 * @return void
	 */
	public static function enqueue_styles() {
		wp_enqueue_script( 'ic_product_ajax' );
		global $post, $wp_query;
		$query_vars = ic_get_catalog_query_vars( false, true );
		if ( empty( $query_vars ) ) {
			$catalog_query = ic_get_catalog_query();
			if ( isset( $catalog_query->query ) ) {
				$query_vars = $catalog_query->query;
			} elseif ( isset( $wp_query->query ) ) {
				$query_vars = $wp_query->query;
			}
		}
		$query_vars = apply_filters( 'ic_product_ajax_query_vars', $query_vars );
		if ( is_ic_catalog_page() ) {
			if ( ( empty( $query_vars ) && is_home_archive() ) || ( is_ic_shortcode_integration() && is_ic_product_listing() ) ) {
				$query_vars['post_type'] = get_current_screen_post_type();
			}
			if ( empty( $query_vars['post_type'] ) ) {
				$post_type = get_post_type();
				if ( ! ic_string_contains( $post_type, 'al_product' ) ) {
					$post_type = 'al_product';
				}
				$query_vars['ic_post_type'] = get_current_screen_post_type();
			}
		} elseif ( isset( $post->post_content ) && ic_has_page_catalog_shortcode( $post ) ) {
			$query_vars['post_type'] = 'al_product';
		}
		$active_filters = get_active_product_filters();
		wp_localize_script(
			'ic_product_ajax',
			'ic_ajax',
			array(
				'query_vars'        => wp_json_encode( $query_vars ),
				'request_url'       => esc_url( remove_query_arg( $active_filters, get_pagenum_link( 1, false ) ) ),
				'filters_reset_url' => get_filters_bar_reset_url(),
				'is_search'         => is_search(),
				'nonce'             => wp_create_nonce( 'ic_ajax' ),
				'ic_nonce'          => wp_create_nonce( 'ic_catalog_self_submit' ),
			)
		);
	}

	/**
	 * Adds serialized shortcode query data to the product list markup.
	 *
	 * @param string $attr  Current HTML attributes.
	 * @param mixed  $query Current query context.
	 * @return string
	 */
	public function shortcode_query_data( $attr, $query ) {
		unset( $query );
		global $shortcode_query;
		if ( ! empty( $shortcode_query->query ) ) {
			unset( $shortcode_query->query['post_status'] );
			$attr .= " data-ic_ajax_query='" . esc_attr( wp_json_encode( $shortcode_query->query ) ) . "'";
		}

		return $attr;
	}

	/**
	 * Rebuilds pagination links for Ajax responses.
	 *
	 * @param string $link Original pagination link.
	 * @return string
	 */
	public function pagenum_link( $link ) {
		if ( is_ic_ajax() ) {
			global $wp_rewrite;
			$query_string = str_replace( '?', '', strstr( $link, '?' ) );
			parse_str( $query_string, $params );
			$pagenum        = isset( $params['paged'] ) ? (int) $params['paged'] : 0;
			$request        = remove_query_arg( array( 'paged' ), $this->get_ajax_request_url() );
			$active_filters = get_active_product_filters( true, true );
			$request        = add_query_arg( $active_filters, $request );
			if ( ! empty( $this->ajax_elements ) ) {
				if ( '' !== $this->self_submit_data ) {
					parse_str( $this->self_submit_data, $submit_params );
					if ( isset( $submit_params['s'] ) && isset( $submit_params['post_type'] ) ) {
						$request = add_query_arg( $submit_params, $request );
					}
				}
			}
			$home_root = wp_parse_url( home_url() );
			$home_root = isset( $home_root['path'] ) ? $home_root['path'] : '';
			$home_root = preg_quote( $home_root, '|' );

			$request = preg_replace( '|^' . $home_root . '|i', '', $request );
			$request = preg_replace( '|^/+|', '', $request );

			if ( ! $wp_rewrite->using_permalinks() ) {
				$base = trailingslashit( get_bloginfo( 'url' ) );

				if ( $pagenum > 1 ) {
					$result = add_query_arg( 'paged', $pagenum, $base . $request );
				} else {
					$result = $base . $request;
				}
			} else {
				$qs_regex = '|\?.*?$|';
				preg_match( $qs_regex, $request, $qs_match );

				if ( ! empty( $qs_match[0] ) ) {
					$query_string = $qs_match[0];
					$request      = preg_replace( $qs_regex, '', $request );
				} elseif ( ! empty( $pagenum ) ) {
					$query_string = str_replace( 'paged=' . $pagenum, '', $query_string );
				}

				$request = preg_replace( "|$wp_rewrite->pagination_base/\d+/?$|", '', $request );
				$request = preg_replace( '|^' . preg_quote( $wp_rewrite->index, '|' ) . '|i', '', $request );
				$request = ltrim( $request, '/' );

				$base = trailingslashit( get_bloginfo( 'url' ) );

				if ( $wp_rewrite->using_index_permalinks() && ( $pagenum > 1 || '' !== $request ) ) {
					$base .= $wp_rewrite->index . '/';
				}

				if ( $pagenum > 1 ) {
					$request = ( ! empty( $request ) ? trailingslashit( $request ) : $request ) . user_trailingslashit( $wp_rewrite->pagination_base . '/' . $pagenum, 'paged' );
				}
				$query_string = str_replace( '#038;', '&', $query_string );
				$result       = $request . $query_string;
			}

			return $result;
		}

		return $link;
	}

	/**
	 * Returns the verified Ajax request URL.
	 *
	 * @return string
	 */
	public function get_ajax_request_url() {
		return $this->request_url;
	}
}
