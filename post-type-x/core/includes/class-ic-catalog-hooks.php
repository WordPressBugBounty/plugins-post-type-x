<?php
/**
 * Catalog hook dispatcher.
 *
 * @version 1.1.1
 * @package ecommerce-product-catalog
 * @author  impleCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Bridges WordPress hooks into catalog-specific actions and filters.
 */
class IC_Catalog_Hooks {

	/**
	 * Registers hook bridge callbacks.
	 */
	public function __construct() {
		add_action( 'wp', array( $this, 'wp' ), - 1 );
		add_action( 'template_redirect', array( $this, 'template_redirect' ), 999 );
		add_action( 'wp_head', array( $this, 'wp_head_start' ), - 1 );
		add_action( 'wp_head', array( $this, 'wp_head' ), 999 );
		add_filter( 'body_class', array( $this, 'body_class_start' ), - 1 );
		add_filter( 'body_class', array( $this, 'body_class' ), 999 );

		add_filter( 'wp_nav_menu', array( $this, 'nav_menu' ), 999 );
		add_filter( 'wp_page_menu', array( $this, 'nav_menu' ), 999 );
		add_filter( 'wp_get_nav_menu_items', array( $this, 'nav_menu_items' ), 999 );
		add_filter( 'pre_wp_nav_menu', array( $this, 'pre_nav_menu' ), 999 );
		add_filter( 'wp_page_menu_args', array( $this, 'pre_nav_menu' ), 999 );
		add_filter( 'wp_get_nav_menu_object', array( $this, 'pre_nav_menu_items' ), 999 );
	}

	/**
	 * Applies catalog-scoped filters for the provided hook name.
	 *
	 * @param string $name  Hook suffix.
	 * @param mixed  $value Value passed through the filters.
	 *
	 * @return mixed
	 */
	public function filter_hook_template( $name, $value = null ) {
		if ( is_ic_catalog_page() ) {
			$value = apply_filters( 'ic_catalog_' . $name, $value );
			if ( is_ic_product_page() ) {
				$value = apply_filters( 'ic_catalog_single_' . $name, $value );
			} elseif ( is_ic_taxonomy_page() ) {
				$value = apply_filters( 'ic_catalog_tax_' . $name, $value );
			} elseif ( is_ic_product_listing() ) {
				$value = apply_filters( 'ic_catalog_listing_' . $name, $value );
			}
		}

		return $value;
	}

	/**
	 * Fires catalog-scoped actions for the provided hook name.
	 *
	 * @param string $name Hook suffix.
	 *
	 * @return void
	 */
	public function action_hook_template( $name ) {
		if ( is_ic_catalog_page() ) {
			do_action( 'ic_catalog_' . $name );
			if ( is_ic_product_page() ) {
				do_action( 'ic_catalog_single_' . $name );
			} elseif ( ic_ic_catalog_archive() ) {
				do_action( 'ic_catalog_archive_' . $name );
				if ( is_ic_taxonomy_page() ) {
					do_action( 'ic_catalog_tax_' . $name );
				} elseif ( is_ic_product_search() ) {
					do_action( 'ic_catalog_search_' . $name );
				} elseif ( is_ic_product_listing() ) {
					do_action( 'ic_catalog_listing_' . $name );
				}
			}
		}
	}

	/**
	 * Fires the catalog `wp` action bridge.
	 *
	 * @return void
	 */
	public function wp() {
		$this->action_hook_template( 'wp' );
		$this->emit_stats_view_context();
	}

	/** Emit stable owning-module view/search actions for analytics integrations. */
	private function emit_stats_view_context() {
		if ( ! is_ic_catalog_page() ) {
			return;
		}
		// A 404 still resolves as a catalog page, so an unresolved URL used to be
		// recorded as a real catalog view. A missing page is not a view.
		if ( is_404() ) {
			return;
		}
		$query = array();
		foreach ( array( 's', 'post_type', 'product_cat', 'product_tag', 'orderby', 'order' ) as $key ) {
			$value = get_query_var( $key, '' );
			if ( is_scalar( $value ) && '' !== (string) $value ) {
				$query[ $key ] = sanitize_text_field( (string) $value );
			}
		}
		$context = array(
			'query'       => $query,
			'product_id'  => is_ic_product_page() ? absint( get_queried_object_id() ) : 0,
			'category_id' => is_ic_taxonomy_page() ? absint( get_queried_object_id() ) : 0,
			'page_type'   => is_ic_product_page() ? 'product' : ( is_ic_taxonomy_page() ? 'category' : ( is_ic_product_search() ? 'search' : 'catalog' ) ),
			'zero_result' => is_ic_product_search() && isset( $GLOBALS['wp_query'] ) && 0 === (int) $GLOBALS['wp_query']->found_posts,
		);
		if ( is_ic_product_page() ) {
			do_action( 'ic_epc_product_viewed', $context );
		} elseif ( is_ic_taxonomy_page() ) {
			do_action( 'ic_epc_category_viewed', $context );
		} elseif ( is_ic_product_search() ) {
			do_action( 'ic_epc_search_performed', $context );
		} else {
			do_action( 'ic_epc_catalog_viewed', $context );
		}
	}

	/**
	 * Fires the catalog template redirect bridge.
	 *
	 * @return void
	 */
	public function template_redirect() {
		if ( is_ic_catalog_page() ) {
			do_action( 'ic_catalog_template_redirect' );
		}
	}

	/**
	 * Fires the catalog `wp_head_start` bridge.
	 *
	 * @return void
	 */
	public function wp_head_start() {
		$this->action_hook_template( 'wp_head_start' );
	}

	/**
	 * Fires the catalog `wp_head` bridge.
	 *
	 * @return void
	 */
	public function wp_head() {
		$this->action_hook_template( 'wp_head' );
	}

	/**
	 * Applies the early body class bridge.
	 *
	 * @param array $body_class Current body classes.
	 *
	 * @return array
	 */
	public function body_class_start( $body_class ) {
		if ( is_ic_catalog_page() ) {
			$body_class = apply_filters( 'ic_catalog_body_class_start', $body_class );
		}

		return $body_class;
	}

	/**
	 * Applies the body class bridge.
	 *
	 * @param array $body_class Current body classes.
	 *
	 * @return array
	 */
	public function body_class( $body_class ) {
		return $this->filter_hook_template( 'body_class', $body_class );
	}

	/**
	 * Applies the nav menu bridge.
	 *
	 * @param string $nav_menu Menu HTML.
	 *
	 * @return string
	 */
	public function nav_menu( $nav_menu ) {
		return $this->filter_hook_template( 'nav_menu', $nav_menu );
	}

	/**
	 * Applies the pre-nav-menu bridge.
	 *
	 * @param mixed $pre_nav_menu Pre-rendered menu value.
	 *
	 * @return mixed
	 */
	public function pre_nav_menu( $pre_nav_menu ) {
		return $this->filter_hook_template( 'pre_nav_menu', $pre_nav_menu );
	}

	/**
	 * Applies the nav menu items bridge.
	 *
	 * @param array $nav_menu Menu items.
	 *
	 * @return array
	 */
	public function nav_menu_items( $nav_menu ) {
		return $this->filter_hook_template( 'nav_menu_items', $nav_menu );
	}

	/**
	 * Applies the pre-nav-menu-items bridge.
	 *
	 * @param mixed $pre_nav_menu Preloaded menu object.
	 *
	 * @return mixed
	 */
	public function pre_nav_menu_items( $pre_nav_menu ) {
		return $this->filter_hook_template( 'pre_nav_menu_items', $pre_nav_menu );
	}
}

new IC_Catalog_Hooks();
