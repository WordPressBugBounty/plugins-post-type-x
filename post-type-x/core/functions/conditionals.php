<?php
/**
 * Product catalog conditional helpers.
 *
 * @package Ecommerce_Product_Catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Manages product conditional functions
 *
 * Here all plugin conditional functions are defined and managed.
 *
 * @version        1.0.0
 * @package        ecommerce-product-catalog/functions
 * @author        impleCode
 */
if ( ! function_exists( 'is_ic_admin' ) ) {

	/**
	 * Checks if the current request is an admin-side catalog request.
	 *
	 * @return bool
	 */
	function is_ic_admin() {
		if ( ( is_admin() || wp_doing_cron() ) && ! is_ic_ajax() ) {
			return true;
		}

		return false;
	}

}

if ( ! function_exists( 'is_ic_front_query' ) ) {

	/**
	 * Checks if the current request should be treated as a front-end catalog query.
	 *
	 * @return bool
	 */
	function is_ic_front_query() {
		$is = ic_get_global( 'is_front_query' );
		if ( false !== $is ) {
			if ( ! empty( $is ) ) {
				return true;
			} else {
				return false;
			}
		}
		if ( is_feed() || is_ic_admin() ) {
			ic_save_global( 'is_front_query', 0 );

			return false;
		}
		ic_save_global( 'is_front_query', 1 );

		return true;
	}

}

/**
 * Checks if the current view is any catalog page.
 *
 * @param WP_Query|object|null $query Optional query object.
 *
 * @return bool
 */
function is_ic_catalog_page( $query = null ) {
	if ( ! is_ic_front_query() ) {
		return false;
	}
	if ( empty( $query ) ) {
		$pre_query = ic_get_global( 'pre_shortcode_query' );
		if ( $pre_query ) {
			$query = $pre_query;
		}
	}
	if ( empty( $query ) && ! ic_is_wp_query_available() ) {

		return false;
	}
	if ( is_ic_product_page( $query ) || is_ic_product_listing( $query ) || is_ic_taxonomy_page( $query ) || is_ic_product_search( $query ) ) {

		return true;
	}

	return false;
}

/**
 * Checks if the current view is a catalog archive.
 *
 * @param WP_Query|object|null $query Optional query object.
 *
 * @return bool
 */
function ic_ic_catalog_archive( $query = null ) {
	if ( ! is_ic_front_query() ) {
		return false;
	}
	if ( empty( $query ) ) {
		$pre_query = ic_get_global( 'pre_shortcode_query' );
		if ( $pre_query ) {
			$query = $pre_query;
		}
	}
	if ( ! empty( $query ) ) {
		$this_query = $query;
	} else {
		global $wp_query;
		$this_query = $wp_query;
	}
	$is_wp_query_catalog_archive = 0;
	if ( ! isset( $this_query->ic_ic_catalog_archive ) ) {
		if ( empty( $query ) && ! ic_is_wp_query_available() ) {
			ic_doing_it_wrong( __FUNCTION__, 'Conditional called to early.', '3.0.61' );

			return false;
		}
		if ( is_ic_product_listing( $query ) || is_ic_taxonomy_page( $query ) || is_ic_product_search( $query ) ) {
			$is_wp_query_catalog_archive = 1;
		}
		global $wp_version;
		if ( is_object( $this_query ) && version_compare( $wp_version, 6.1, '>=' ) ) {
			$this_query->ic_ic_catalog_archive = $is_wp_query_catalog_archive;
		}
	} else {
		$is_wp_query_catalog_archive = $this_query->ic_ic_catalog_archive;
	}
	if ( ! empty( $is_wp_query_catalog_archive ) ) {
		return true;
	} else {

		return false;
	}
}

/**
 * Checks if the current view is a catalog taxonomy page.
 *
 * @param WP_Query|object|null $query Optional query object.
 *
 * @return bool
 */
function is_ic_taxonomy_page( $query = null ) {
	if ( ! is_ic_front_query() ) {
		return false;
	}
	if ( empty( $query ) ) {
		$pre_query = ic_get_global( 'pre_shortcode_query' );
		if ( $pre_query ) {
			$query = $pre_query;
		}
	}
	if ( ! empty( $query ) ) {
		$this_query = $query;
	} else {
		global $wp_query;
		$this_query = $wp_query;
	}
	$is_wp_query_taxonomy_page = 0;
	if ( ! isset( $this_query->is_ic_taxonomy_page ) ) {
		if ( empty( $query ) && ! ic_is_wp_query_available() ) {
			ic_doing_it_wrong( __FUNCTION__, 'Conditional called to early.', '3.0.61' );

			return false;
		}
		$taxonomies = product_taxonomy_array();
		if ( ( empty( $query ) || ! is_object( $query ) ) && is_tax( $taxonomies ) ) {
			$is_wp_query_taxonomy_page = 1;
		} elseif ( ! empty( $query ) && is_object( $query ) ) {
			if ( $query->is_tax( $taxonomies ) ) {
				$is_wp_query_taxonomy_page = 1;
			} elseif ( current_filter() === 'parse_tax_query' && ! empty( $query->query ) && is_array( $query->query ) ) {
				$query_keys = array_keys( array_filter( $query->query ) );
				if ( array_intersect( $query_keys, $taxonomies ) ) {
					return true;
				}
			}
		}
		global $wp_version;
		if ( is_object( $this_query ) && version_compare( $wp_version, 6.1, '>=' ) ) {
			$this_query->is_ic_taxonomy_page = $is_wp_query_taxonomy_page;
		}
	} else {
		$is_wp_query_taxonomy_page = $this_query->is_ic_taxonomy_page;
	}
	if ( ! empty( $is_wp_query_taxonomy_page ) ) {
		return true;
	} else {
		return false;
	}
}

/**
 * Checks if current product has certain category assigned or any category if no category is provided
 *
 * @param string|int|array|null $category Category identifier, slug, or term list.
 *
 * @return bool
 */
function has_ic_product_category( $category = null ) {
	if ( has_term( $category, 'al_product-cat' ) ) {
		return true;
	}

	return false;
}

/**
 * Checks if product category page is being displayed
 *
 * @param string|int|array|null $category Category identifier, slug, or term list.
 *
 * @return bool
 */
function is_ic_product_category( $category = null ) {
	$taxonomies = product_taxonomy_array();
	if ( is_tax( $taxonomies, $category ) ) {
		return true;
	} elseif ( ! empty( $category ) && ! is_array( $category ) ) {
		$cache_meta = 'get_term' . $category;
		$term       = ic_get_global( $cache_meta );
		if ( false === $term ) {
			$term = get_term( $category, '' );
			ic_save_global( $cache_meta, $term );
		}
		if ( ! empty( $term ) && ! is_wp_error( $term ) && ! empty( $term->taxonomy ) && in_array( $term->taxonomy, $taxonomies, true ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Checks if current page is main product listing
 *
 * @param WP_Query|object|null $query Optional query object.
 *
 * @return bool
 */
function is_ic_product_listing( $query = null ) {
	if ( ! is_ic_front_query() ) {

		return false;
	}
	if ( empty( $query ) || is_array( $query ) ) {
		$pre_query = ic_get_global( 'pre_shortcode_query' );
		if ( $pre_query ) {
			$query = $pre_query;
		}
	}
	if ( is_array( $query ) ) {

		return false;
	}
	if ( ! empty( $query ) && ! is_array( $query ) ) {
		$this_query = $query;
	} else {
		global $wp_query;
		$this_query = $wp_query;
	}
	$is_wp_query_product_listing = 0;
	if ( ! isset( $this_query->is_ic_product_listing ) ) {
		if ( empty( $query ) && ! ic_is_wp_query_available() ) {
			ic_doing_it_wrong( __FUNCTION__, 'Conditional called to early.', '3.0.61' );

			return false;
		}
		if ( empty( $query ) && ( ( is_post_type_archive( product_post_type_array() ) && ! is_search() ) || is_home_archive() || is_custom_product_listing_page() ) ) {
			$is_wp_query_product_listing = 1;
		} elseif ( ! empty( $query ) && is_object( $query ) && ( ( $query->is_post_type_archive( product_post_type_array() ) && ! $query->is_search() && ! is_ic_taxonomy_page( $query ) && ! is_ic_product_search( $query ) ) || is_home_archive( $query ) || is_custom_product_listing_page( $query ) ) ) {
			$is_wp_query_product_listing = 1;
		}
		global $wp_version;
		if ( version_compare( $wp_version, 6.1, '>=' ) ) {
			$this_query->is_ic_product_listing = $is_wp_query_product_listing;
		}
	} else {
		$is_wp_query_product_listing = $this_query->is_ic_product_listing;
	}
	if ( ! empty( $is_wp_query_product_listing ) ) {
		return true;
	} else {
		return false;
	}
}

/**
 * Checks if selected page for product listing is being displayed
 *
 * @param WP_Query|object|null $query Optional query object.
 *
 * @return bool
 */
function is_custom_product_listing_page( $query = null ) {
	if ( ! empty( $query ) && ( $query->is_search() || is_ic_taxonomy_page( $query ) ) ) {
		return false;
	}
	$listing_id = get_product_listing_id();
	if ( ! empty( $listing_id ) && is_ic_product_listing_enabled() ) {
		if ( empty( $query ) && is_ic_page( $listing_id ) ) {

			return true;
		}
		if ( ! empty( $query->query_vars['page_id'] ) ) {
			$page_id = intval( $query->query_vars['page_id'] );
		} elseif ( ! empty( $query->queried_object_id ) ) {
			$page_id = intval( $query->queried_object_id );
		} elseif ( ! empty( $query->query['pagename'] ) ) {
			$page = get_page_by_path( $query->query['pagename'] );
			if ( ! empty( $page->ID ) ) {
				$page_id = intval( $page->ID );
			}
		} elseif ( ! empty( $query->query_vars['pagename'] ) ) {
			$page = get_page_by_path( $query->query_vars['pagename'] );
			if ( ! empty( $page->ID ) ) {
				$page_id = intval( $page->ID );
			}
		}
	}
	if ( ! empty( $page_id ) && (int) $page_id === (int) $listing_id ) {
		return true;
	}

	return apply_filters( 'is_custom_product_listing_page', false, $query );
}

/**
 * Wrapper for checking whether the given page is the current page.
 *
 * @param int|string $page_id Optional page ID.
 *
 * @return bool
 */
function ic_is_page( $page_id = '' ) {
	return is_ic_page( $page_id );
}

/**
 * Checks if the global query object is available.
 *
 * @return bool
 */
function ic_is_wp_query_available() {
	global $wp_query;
	if ( empty( $wp_query->request ) && ! ic_is_wp_object_available() ) {

		return false;
	}

	return true;
}

/**
 * Checks if the queried object is available.
 *
 * @return bool
 */
function ic_is_wp_object_available() {
	$object = ic_get_queried_object();
	if ( empty( $object ) ) {
		return false;
	}

	return true;
}

/**
 * Checks if product search screen is active
 *
 * @param WP_Query|object|null $query Optional query object.
 *
 * @return bool
 */
function is_ic_product_search( $query = null ) {
	if ( ! is_ic_front_query() ) {
		return false;
	}
	if ( empty( $query ) ) {
		$pre_query = ic_get_global( 'pre_shortcode_query' );
		if ( $pre_query ) {
			$query = $pre_query;
		}
	}
	if ( ! empty( $query->query_vars['ic_search_parsed'] ) ) {
		return true;
	}
	$post_type = null;
	if ( isset( $_GET['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only conditional check.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only conditional check.
		$post_type = sanitize_key( wp_unslash( $_GET['post_type'] ) );
	}
	if ( ( ( empty( $query ) && is_search() ) || ( ! empty( $query ) && is_object( $query ) && $query->is_search() ) ) && null !== $post_type && is_ic_valid_post_type( $post_type ) ) {
		return true;
	}

	return false;
}

if ( ! function_exists( 'is_ic_product_page' ) ) {

	/**
	 * Checks if a product page is displayed
	 *
	 * @return boolean
	 */
	function is_ic_product_page() {
		global $wp_query;
		if ( empty( $wp_query ) || ! is_ic_front_query() ) {
			return false;
		}
		if ( is_ic_shortcode_integration() ) {
			$query = ic_get_global( 'pre_shortcode_query' );
			if ( $query ) {
				$prev_query = $wp_query;
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Temporary query swap is restored before returning.
				$wp_query = $query;
			}
		}
		if ( ! empty( $wp_query ) && $wp_query->is_singular && in_array( $wp_query->get( 'post_type' ), product_post_type_array(), true ) ) {
			$return = true;
		} else {
			$return = false;
		}
		if ( ! empty( $prev_query ) ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Temporary query swap is restored before returning.
			$wp_query = $prev_query;
		}

		return $return;
	}

}

/**
 * Checks if the given page is currently being displayed.
 *
 * @param int|string $page_id Optional page ID.
 *
 * @return bool
 */
function is_ic_page( $page_id = '' ) {
	if ( ! ic_is_wp_object_available() ) {
		return false;
	}
	if ( is_ic_shortcode_integration() ) {
		$query = ic_get_global( 'pre_shortcode_query' );
		if ( $query ) {
			global $wp_query;
			$prev_query = $wp_query;
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Temporary query swap is restored before returning.
			$wp_query = $query;
		}
	}
	if ( is_page( apply_filters( 'ic_is_page_id', $page_id ) ) ) {
		$return = true;
	} else {
		$return = false;
	}
	if ( ! empty( $prev_query ) ) {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Temporary query swap is restored before returning.
		$wp_query = $prev_query;
	}

	return $return;
}

if ( ! function_exists( 'is_ic_admin_page' ) ) {

	/**
	 * Checks if the current screen is a catalog admin page.
	 *
	 * @param bool $check_activation_notice Whether to include activation notice screens.
	 *
	 * @return bool
	 */
	function is_ic_admin_page( $check_activation_notice = true ) {
		$page = null;
		if ( isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only conditional check.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only conditional check.
			$page = sanitize_key( wp_unslash( $_GET['page'] ) );
		}
		if ( is_ic_catalog_admin_page( $check_activation_notice ) || 'implecode-settings' === $page ) {
			return true;
		}

		return false;
	}

}
if ( ! function_exists( 'is_ic_catalog_admin_page' ) ) {

	/**
	 * Checks if the current screen belongs to the catalog admin UI.
	 *
	 * @param bool $check_activation_notice Whether to include activation notice screens.
	 *
	 * @return bool
	 */
	function is_ic_catalog_admin_page( $check_activation_notice = true ) {
		if ( is_admin() && function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( ( isset( $screen->id ) && ic_string_contains( $screen->id, 'al_product' ) ) || ( function_exists( 'is_ic_activation_notice' ) && $check_activation_notice && is_ic_activation_notice() ) ) {
				return true;
			}

			return apply_filters( 'is_ic_catalog_admin_page', false, $screen );
		}

		return false;
	}

}

/**
 * Checks if product listing pages are enabled.
 *
 * @return bool
 */
function is_ic_product_listing_enabled() {
	$enable_product_listing = get_option( 'enable_product_listing', 1 );
	if ( 1 === (int) $enable_product_listing ) {
		return true;
	}

	return false;
}

if ( ! function_exists( 'is_ic_activation_notice' ) ) {

	/**
	 * Checks if the activation notice is active.
	 *
	 * @return bool
	 */
	function is_ic_activation_notice() {
		$enable = get_option( 'IC_EPC_activation_message', 0 );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only conditional check.
		if ( ( $enable && ( is_ic_admin_page( false ) || is_ic_welcome_page() || isset( $_GET['plugin_status'] ) ) ) || isset( $_GET['ic_catalog_activation_choice'] ) ) {
			return true;
		}

		return false;
	}

}

if ( ! function_exists( 'is_ic_welcome_page' ) ) {

	/**
	 * Checks if the current admin page is the welcome screen.
	 *
	 * @return bool
	 */
	function is_ic_welcome_page() {
		$page = null;
		if ( isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only conditional check.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only conditional check.
			$page = sanitize_key( wp_unslash( $_GET['page'] ) );
		}
		if ( 'implecode_welcome' === $page ) {
			return true;
		}

		return false;
	}

}
if ( ! function_exists( 'ic_string_contains' ) ) {

	/**
	 * Checks whether a string or string array contains the given value.
	 *
	 * @param string|array $subject        String or string array to search.
	 * @param string|int   $contains       Value to find.
	 * @param bool         $case_sensitive Whether the comparison is case-sensitive.
	 * @param bool         $test_array     Whether to iterate over array values.
	 *
	 * @return bool
	 */
	function ic_string_contains( $subject, $contains, $case_sensitive = true, $test_array = false ) {
		if ( ! is_string( $subject ) && ! $test_array ) {
			return false;
		} elseif ( is_array( $subject ) && $test_array ) {
			foreach ( $subject as $str ) {
				if ( ic_string_contains( $str, $contains, $case_sensitive, false ) ) {
					return true;
				}
			}

			return false;
		}
		if ( '' === $subject || '' === $contains ) {
			return false;
		}
		if ( ! is_string( $contains ) ) {
			if ( is_array( $contains ) ) {
				return false;
			}
			$contains = strval( $contains );
		}
		if ( $case_sensitive && false !== strpos( $subject, $contains ) ) {
			return true;
		} elseif ( ! $case_sensitive && false !== stripos( $subject, $contains ) ) {
			return true;
		}

		return false;
	}

}

/**
 * Checks if new entry screen is being displayed
 *
 * @return boolean
 */
function is_ic_new_product_screen() {
	if ( is_admin() && function_exists( 'get_current_screen' ) ) {
		$screen = get_current_screen();
		if ( ! empty( $screen->action ) && 'add' === $screen->action && is_ic_catalog_admin_page() ) {
			return true;
		}
	}

	return false;
}

/**
 * Checks if the edit product admin screen is active.
 *
 * @return bool
 */
function is_ic_edit_product_screen() {
	if ( is_admin() && function_exists( 'get_current_screen' ) ) {
		$post_id = null;
		$action  = null;
		if ( isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only conditional check.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only conditional check.
			$post_id = absint( wp_unslash( $_GET['post'] ) );
		}
		if ( isset( $_GET['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only conditional check.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only conditional check.
			$action = sanitize_key( wp_unslash( $_GET['action'] ) );
		}
		if ( ! empty( $post_id ) && ic_product_exists( $post_id ) && 'edit' === $action && is_ic_catalog_admin_page() ) {
			return true;
		}
	}

	return false;
}

/**
 * Checks if the product list admin screen is active.
 *
 * @return bool
 */
function is_ic_product_list_admin_screen() {
	if ( is_admin() && function_exists( 'get_current_screen' ) ) {
		$screen = get_current_screen();
		if ( isset( $screen->id ) && ic_string_contains( $screen->id, 'edit-al_product' ) && is_ic_catalog_admin_page() && ! is_ic_product_categories_admin_screen() && ! is_ic_product_categories_edit_admin_screen() ) {
			return true;
		}
	}

	return false;
}

/**
 * Checks if the product categories list admin screen is active.
 *
 * @return bool
 */
function is_ic_product_categories_admin_screen() {
	if ( is_admin() && function_exists( 'get_current_screen' ) ) {
		$screen = get_current_screen();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only conditional check.
		if ( isset( $screen->id ) && ic_string_contains( $screen->id, 'edit-al_product-cat' ) && empty( $_GET['tag_ID'] ) && is_ic_catalog_admin_page() ) {
			return true;
		}
	}

	return false;
}

/**
 * Checks if the product category edit admin screen is active.
 *
 * @return bool
 */
function is_ic_product_categories_edit_admin_screen() {
	if ( is_admin() && function_exists( 'get_current_screen' ) ) {
		$screen = get_current_screen();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only conditional check.
		if ( isset( $screen->id ) && ic_string_contains( $screen->id, 'edit-al_product-cat' ) && ! empty( $_GET['tag_ID'] ) && is_ic_catalog_admin_page() ) {
			return true;
		}
	}

	return false;
}

/**
 * Checks if product gallery should be enabled
 *
 * @return boolean
 */
function is_ic_product_gallery_enabled() {
	$single_options = get_product_page_settings();
	if ( 1 === (int) $single_options['enable_product_gallery'] ) {
		return true;
	}

	return false;
}

/**
 * Checks if current product category has children
 *
 * @param WP_Term|object|null $category Category term object.
 *
 * @return bool
 */
function has_category_children( $category = null ) {
	if ( empty( $category ) ) {
		$taxonomy = get_query_var( 'taxonomy' );
		// Use the queried term object when no category is passed in.
		$category = ic_get_queried_object();
	} else {
		$taxonomy = $category->taxonomy;
	}
	if ( empty( $taxonomy ) ) {
		return false;
	}
	$children = get_term_children( $category->term_id, $taxonomy );
	if ( count( $children ) > 0 ) {
		return true;
	} else {
		return false;
	}
}

/**
 * Checks if product has short description
 *
 * @param int $product_id Product ID.
 *
 * @return bool
 */
function has_product_short_description( $product_id ) {
	$desc = get_product_short_description( $product_id );
	if ( ! empty( $desc ) ) {
		return true;
	} else {
		return false;
	}
}

/**
 * Checks if product has long description
 *
 * @param int $product_id Product ID.
 *
 * @return bool
 */
function has_product_description( $product_id ) {
	$desc = get_product_description( $product_id );
	if ( ! empty( $desc ) ) {
		return true;
	} else {
		return apply_filters( 'ic_has_product_description', false, $product_id );
	}
}

/**
 * Ckecks if product has image attached
 *
 * @param int $product_id Product ID.
 *
 * @return bool
 */
function has_product_image( $product_id ) {
	if ( has_post_thumbnail( $product_id ) ) {
		return true;
	} else {
		return false;
	}
}

/**
 * Checks if current view is triggered by shortcode
 *
 * @return bool
 * @global type $shortcode_query
 * @global type $cat_shortcode_query
 */
function is_ic_shortcode_query() {
	global $cat_shortcode_query, $shortcode_query;
	$is_shortcode_request = false;
	$shortcode_request    = filter_input( INPUT_POST, 'ic_shortcode', FILTER_DEFAULT );
	if ( is_string( $shortcode_request ) ) {
		$is_shortcode_request = ! in_array( sanitize_text_field( $shortcode_request ), array( '', '0' ), true );
	}
	if ( ( isset( $cat_shortcode_query['enable'] ) && 'yes' === $cat_shortcode_query['enable'] ) || isset( $shortcode_query ) || $is_shortcode_request ) {
		return true;
	} else {
		return false;
	}
}

/**
 * Checks if a plural catalog name should be used
 *
 * @return boolean
 */
function is_plural_form_active() {
	$lang = get_locale();
	if ( strpos( $lang, 'en_' ) !== false ) {
		return true;
	} else {
		return false;
	}
}

/**
 * Checks if WordPress language is english
 *
 * @return boolean
 */
function is_english_catalog_active() {
	$lang = get_locale();
	if ( strpos( $lang, 'en_' ) !== false ) {
		return true;
	} else {
		return false;
	}
}

/**
 * Checks if permalinks are enabled
 *
 * @return boolean
 */
function is_ic_permalink_product_catalog() {
	if ( get_option( 'permalink_structure' ) ) {
		return true;
	} else {
		return false;
	}
}

/**
 * Checks if only categories should be showed
 *
 * @param WP_Query|object|null $query Optional query object.
 *
 * @return bool
 */
function is_ic_only_main_cats( $query = null ) {
	global $wp_query;
	if ( ! empty( $query ) ) {
		$this_query = $query;
	} else {
		$this_query = $wp_query;
	}
	$is_wp_query_only_main_cats = 0;
	if ( ! isset( $this_query->is_ic_only_main_cats ) ) {
		if ( empty( $query ) && ! ic_is_wp_query_available() ) {
			ic_doing_it_wrong( __FUNCTION__, 'Conditional called to early.', '3.0.61' );

			return false;
		}
		$multiple_settings = get_multiple_settings();
		if ( is_ic_product_listing( $query ) && 'cats_only' === $multiple_settings['product_listing_cats'] ) {
			$is_wp_query_only_main_cats = 1;
		} elseif ( is_ic_taxonomy_page( $query ) && 'only_subcategories' === $multiple_settings['category_top_cats'] ) {
			$term = ic_get_queried_object();
			if ( ic_has_category_children( $term ) ) {
				$is_wp_query_only_main_cats = 1;
			}
		}
		global $wp_version;
		if ( version_compare( $wp_version, 6.1, '>=' ) ) {
			$this_query->is_ic_only_main_cats = $is_wp_query_only_main_cats;
		}
	} else {
		$is_wp_query_only_main_cats = $this_query->is_ic_only_main_cats;
	}
	if ( ! empty( $is_wp_query_only_main_cats ) ) {

		return true;
	} else {

		return false;
	}
}

/**
 * Checks if the current term has child categories.
 *
 * @param WP_Term|object $term Term object.
 *
 * @return bool
 */
function ic_has_category_children( $term ) {
	if ( ! empty( $term->term_id ) ) {
		$children = ic_catalog_get_categories( $term->term_id );
		if ( ! empty( $children ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Checks if product listing is showign product categories
 *
 * @return boolean
 */
function is_ic_product_listing_showing_cats() {
	$multiple_settings = get_multiple_settings();
	if ( 'on' === $multiple_settings['category_top_cats'] || 'only_subcategories' === $multiple_settings['category_top_cats'] ) {
		if ( ! is_tax() || ( is_tax() && has_category_children() ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Checks if category image is enabled on category page
 *
 * @return boolean
 */
function is_ic_category_image_enabled() {
	$multiple_settings = get_multiple_settings();
	if ( 1 !== (int) $multiple_settings['cat_image_disabled'] ) {
		return true;
	}

	return false;
}

/**
 * Checks if product name on product page is enabled
 *
 * @return boolean
 */
function is_ic_product_name_enabled() {
	$multiple_settings = get_multiple_settings();
	if ( 1 === (int) $multiple_settings['disable_name'] ) {
		return false;
	}

	return true;
}

/**
 * Checks if product details metabox should be visible
 *
 * @return type
 */
function ic_product_details_box_visible() {
	$visible = false;
	if ( ( function_exists( 'is_ic_price_enabled' ) && is_ic_price_enabled() ) || ( function_exists( 'is_ic_sku_enabled' ) && is_ic_sku_enabled() ) ) {
		$visible = true;
	}

	return apply_filters( 'product_details_box_visible', $visible );
}

/**
 * Checks if theme default sidebar should be enabled on product pages
 *
 * @return boolean
 */
function is_ic_default_theme_sidebar_active() {
	$settings = get_multiple_settings();
	if ( isset( $settings['default_sidebar'] ) && 1 === (int) $settings['default_sidebar'] ) {
		return true;
	}

	return false;
}

/**
 * Checks if theme default sidebar catalog styled should be enabled on product pages
 *
 * @return boolean
 */
function is_ic_default_theme_sided_sidebar_active() {
	$settings = get_multiple_settings();
	if ( isset( $settings['default_sidebar'] ) && ( 'left' === $settings['default_sidebar'] || 'right' === $settings['default_sidebar'] ) ) {
		return true;
	}

	return false;
}

/**
 * Checks if current page is integration wizard page
 *
 * @param bool $check_shortcode_integration Whether shortcode integration should be considered.
 *
 * @return bool
 */
function is_ic_integration_wizard_page( $check_shortcode_integration = true ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only conditional check.
	$has_test_advanced = ! empty( $_GET['test_advanced'] );
	// phpcs:ignore WordPress.WP.Capabilities.Unknown -- Custom plugin capability.
	if ( current_user_can( 'manage_product_settings' ) && ( ! is_archive() && ( ( sample_product_id() && (int) sample_product_id() === (int) get_the_ID() ) || $has_test_advanced ) ) && ( ! is_advanced_mode_forced( false ) || ic_is_woo_template_available() ) ) {
		if ( $check_shortcode_integration && is_ic_shortcode_integration( null, false ) && ! $has_test_advanced ) {
			return false;
		}

		return true;
	}

	return false;
}

/**
 * Checks if current page is home catalog listing
 *
 * @param WP_Query|object|null $query Optional query object.
 *
 * @return bool
 */
function is_home_archive( $query = null ) {

	if ( ! is_ic_product_listing_enabled() ) {
		return false;
	}
	if ( ! is_object( $query ) ) {
		if ( ! ic_is_wp_object_available() ) {
			return false;
		}
		if ( is_front_page() && is_product_listing_home_set() ) {
			return true;
		}
	} elseif ( is_object( $query ) && (int) $query->get( 'page_id' ) === (int) get_option( 'page_on_front' ) && is_product_listing_home_set() ) {
		return true;
	}

	return false;
}

/**
 * Checks if the home page catalog listing configuration is active
 *
 * @return bool
 */
function is_product_listing_home_set() {
	$frontpage          = get_option( 'page_on_front' );
	$product_listing_id = get_product_listing_id();
	if ( ! empty( $frontpage ) && ! empty( $product_listing_id ) && (int) $frontpage === (int) $product_listing_id ) {
		return true;
	}

	return false;
}

/**
 * Checks if sort drop down should be shown
 *
 * @return bool
 * @global object $wp_query
 * @global int $product_sort
 */
function is_product_sort_bar_active() {
	global $product_sort, $wp_query;
	if ( 'simple' !== get_integration_type() && ( ( ! is_ic_in_shortcode() && is_product_filters_active() ) || ( is_ic_in_shortcode() && isset( $product_sort ) && 1 === (int) $product_sort ) || ( ! is_ic_in_shortcode() && ( $wp_query->max_num_pages > 1 || $wp_query->found_posts > 0 ) ) ) ) {
		return true;
	}

	return false;
}

/**
 * Checks if any filter is active now
 *
 * @param array $exclude Filter keys or values to ignore.
 *
 * @return bool
 */
function is_product_filters_active( $exclude = array() ) {
	$session = get_product_catalog_session();

	if ( isset( $session['filters'] ) && ! empty( $session['filters'] ) ) {
		// Ignore synthetic filter bookkeeping keys.
		$exclude[] = 'filtered-url';
		$exclude[] = 'all';
		foreach ( $session['filters'] as $filter_name => $filter_value ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only conditional check.
			if ( ! in_array( $filter_name, $exclude, true ) && ! in_array( $filter_value, $exclude, true ) && ( empty( $_GET[ $filter_name ] ) || ! in_array( $_GET[ $filter_name ], $exclude, true ) ) ) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Checks if product filter is active
 *
 * @param string      $filter_name Filter name.
 * @param string|null $value       Optional expected value.
 *
 * @return bool
 */
function is_product_filter_active( $filter_name, $value = null ) {
	$session = get_product_catalog_session();
	if ( isset( $session['filters'][ $filter_name ] ) && ! empty( $session['filters'][ $filter_name ] ) ) {
		if ( isset( $value ) && ( ( is_array( $session['filters'][ $filter_name ] ) && in_array( $value, $session['filters'][ $filter_name ], true ) ) || $session['filters'][ $filter_name ] === $value ) ) {
			return true;
		} elseif ( ! isset( $value ) ) {
			return true;
		}
	}

	return apply_filters( 'ic_is_product_filter_active', false, $filter_name, $value );
}

/**
 * Checks if currently the filter bar is being displayed
 *
 * @return boolean
 * @global boolean $is_filter_bar
 */
function is_filter_bar() {
	global $is_filter_bar;
	if ( isset( $is_filter_bar ) && $is_filter_bar ) {
		return true;
	}

	return false;
}

/**
 * Checks if current page has show_products shortcode
 *
 * @return boolean
 * @global type $post
 */
function has_show_products_shortcode() {
	global $post;

	$is_shortcode_request = false;
	$shortcode_request    = filter_input( INPUT_POST, 'ic_shortcode', FILTER_DEFAULT );
	if ( is_string( $shortcode_request ) ) {
		$is_shortcode_request = '' !== sanitize_text_field( $shortcode_request );
	}
	if ( is_ic_ajax() && $is_shortcode_request ) {
		return true;
	} elseif ( ! is_ic_ajax() && is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'show_products' ) || ( function_exists( 'has_block' ) && has_block( 'ic-epc/show-products', $post ) ) ) ) {
		return true;
	}

	return apply_filters( 'ic_has_show_products_shortcode', false );
}

/**
 * Checks if product exists
 *
 * @param int $product_id Product ID.
 *
 * @return bool
 */
function ic_product_exists( $product_id ) {
	$status = get_post_status( $product_id );
	if ( false === $status || 'auto-draft' === $status ) {
		return false;
	}

	return true;
}

/**
 * Checks if default image should be displayed for product
 *
 * @param int $product_id Product ID.
 *
 * @return bool
 */
function is_ic_default_image( $product_id ) {
	if ( has_post_thumbnail( $product_id ) ) {
		return false;
	} else {
		return true;
	}
}

if ( ! function_exists( 'ic_use_php_session' ) ) {

	/**
	 * Check if PHP sessions are available
	 *
	 * @return boolean
	 */
	function ic_use_php_session() {
		if ( ! defined( 'IC_USE_PHP_SESSIONS' ) ) {
			return false;
		} elseif ( IC_USE_PHP_SESSIONS && ( function_exists( 'session_start' ) && ( ! is_admin() || is_ic_front_ajax() ) ) ) {
				return true;
		} else {
			return false;
		}
	}

}

if ( ! function_exists( 'ic_is_session_started' ) ) {

	/**
	 * Checks if a PHP session has already been started.
	 *
	 * @return bool
	 */
	function ic_is_session_started() {
		if ( version_compare( phpversion(), '5.4.0', '>=' ) ) {
			return session_status() === PHP_SESSION_ACTIVE ? true : false;
		} else {
			return session_id() === '' ? false : true;
		}
	}

}

/**
 * Checks if provided ID is a product
 *
 * @param int $product_id Product ID.
 *
 * @return bool
 */
function is_ic_product( $product_id ) {
	if ( intval( $product_id ) ) {
		if ( function_exists( 'get_product_listing_id' ) ) {
			$listing_id = get_product_listing_id();
			if ( (int) $listing_id === (int) $product_id ) {
				return false;
			}
		}
		$post_type = ic_get_post_type( $product_id );
		if ( is_ic_catalog_post_type( $post_type ) ) {

			return true;
		}
	}

	return apply_filters( 'is_ic_product', false, $product_id );
}

/**
 * Checks if the post type belongs to the catalog.
 *
 * @param string $post_type Post type key.
 *
 * @return bool
 */
function is_ic_catalog_post_type( $post_type ) {
	$post_types = product_post_type_array();
	if ( in_array( $post_type, $post_types, true ) ) {
		return true;
	}

	return false;
}

/**
 * Checks if product has meta value set
 *
 * @param int    $product_id Product ID.
 * @param string $meta_name  Meta key.
 *
 * @return bool
 */
function has_ic_product_meta( $product_id, $meta_name ) {
	$custom_keys = get_post_custom_keys( $product_id );
	if ( is_array( $custom_keys ) && in_array( $meta_name, $custom_keys, true ) ) {
		return true;
	}

	return false;
}

if ( ! function_exists( 'is_ic_ajax' ) ) {

	/**
	 * Checks if the current request is an AJAX call, optionally for a specific action.
	 *
	 * @param string|null $action Optional AJAX action.
	 *
	 * @return bool
	 */
	function is_ic_ajax( $action = null ) {
		if ( ! is_admin() ) {
			return false;
		}

		$return = false;
		if ( function_exists( 'wp_doing_ajax' ) ) {
			$doing = wp_doing_ajax();
			if ( $doing ) {
				$return = true;
			}
		} elseif ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			$return = true;
		}
		$posted_action = filter_input( INPUT_POST, 'action', FILTER_DEFAULT );
		if ( is_string( $posted_action ) ) {
			$posted_action = sanitize_key( $posted_action );
		} else {
			$posted_action = null;
		}
		if ( $return && 'heartbeat' === $posted_action ) {
			$return = false;
		}
		if ( $return && ! empty( $action ) && $posted_action !== $action ) {
			$return = false;
		}

		return $return;
	}

}
if ( ! function_exists( 'is_ic_front_ajax' ) ) {
	/**
	 * Checks if the current AJAX request is available on the front end.
	 *
	 * @return bool
	 */
	function is_ic_front_ajax() {
		if ( is_ic_ajax() ) {
			$action = null;
			if ( isset( $_REQUEST['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only conditional check.
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only conditional check.
				$action = sanitize_key( wp_unslash( $_REQUEST['action'] ) );
			}
			if ( ! empty( $action ) && has_action( 'wp_ajax_nopriv_' . $action ) ) {
				return true;
			}
		}

		return false;
	}
}


/**
 * Checks if the catalog block is currently being rendered in REST.
 *
 * @return bool
 */
function ic_is_rendering_catalog_block() {
	global $ic_rendering_catalog_block;
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST && ! empty( $ic_rendering_catalog_block ) ) {
		return true;
	}

	return false;
}

/**
 * Checks if the products block is currently being rendered in REST.
 *
 * @return bool
 */
function ic_is_rendering_products_block() {
	global $ic_rendering_products_block;
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST && ! empty( $ic_rendering_products_block ) ) {
		return true;
	}

	return false;
}

if ( ! function_exists( 'ic_is_rendering_block' ) ) {
	/**
	 * Checks if a catalog-related block is currently being rendered.
	 *
	 * @return bool
	 */
	function ic_is_rendering_block() {
		$context = null;
		if ( isset( $_REQUEST['context'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only conditional check.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only conditional check.
			$context = sanitize_text_field( wp_unslash( $_REQUEST['context'] ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only conditional check.
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST && ! empty( $_REQUEST ) ) || ( ! empty( $context ) && 'edit' === $context && ! empty( $_REQUEST['attributes'] ) ) ) {
			return true;
		}

		return false;
	}
}


/**
 * Checks if the catalog is currently rendering inside a shortcode.
 *
 * @return bool
 */
function is_ic_in_shortcode() {
	$in_shortcode = ic_get_global( 'in_shortcode' );

	return $in_shortcode;
}

if ( ! function_exists( 'is_ic_shortcode_integration' ) ) {

	/**
	 * Checks if shortcode integration is active for the selected listing.
	 *
	 * @param int|null $listing_id           Optional listing page ID.
	 * @param bool     $check_simple_mode    Whether simple mode should be considered.
	 *
	 * @return bool
	 */
	function is_ic_shortcode_integration( $listing_id = null, $check_simple_mode = true ) {
		$cache_name = 'is_ic_shortcode_integration' . $listing_id;
		$return     = ic_get_global( $cache_name );
		if ( false !== $return ) {
			return $return;
		}
		if ( empty( $listing_id ) ) {
			$listing_id = intval( get_product_listing_id() );
		}
		if ( $check_simple_mode ) {
			$save_cache = true;
		}
		if ( ic_has_listing_shortcode( $listing_id ) || ( $check_simple_mode && IC_Catalog_Theme_Integration::get_real_integration_mode() === 'simple' && ! is_advanced_mode_forced( false ) && ! is_ic_integration_wizard_page( false ) ) ) {
			$return = apply_filters( 'is_ic_shortcode_integration', true );
			if ( ! empty( $save_cache ) ) {
				ic_save_global( $cache_name, $return );
			}

			return $return;
		}
		if ( ! empty( $save_cache ) ) {
			ic_save_global( $cache_name, 0 );
		}

		return false;
	}

}

/**
 * Checks if the listing page contains the catalog shortcode.
 *
 * @param int|null $listing_id Optional listing page ID.
 *
 * @return bool
 */
function ic_has_listing_shortcode( $listing_id = null ) {
	if ( empty( $listing_id ) ) {
		$listing_id = intval( get_product_listing_id() );
	}
	if ( ! empty( $listing_id ) ) {
		$post = get_post( $listing_id );
		if ( isset( $post->post_content ) && ic_has_page_catalog_shortcode( $post ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Checks if the provided post contains the catalog shortcode or block.
 *
 * @param WP_Post|object $post Post object.
 *
 * @return bool
 */
function ic_has_page_catalog_shortcode( $post ) {
	if ( has_shortcode( $post->post_content, 'show_product_catalog' ) || ( function_exists( 'has_block' ) && has_block( 'ic-epc/show-catalog', $post->post_content ) ) ) {
		return true;
	} else {
		return false;
	}
}

/**
 * Checks if the current loop is a catalog loop.
 *
 * @return bool
 */
function in_the_ic_loop() {
	if ( is_ic_shortcode_integration() ) {
		$in_the_loop = ic_get_global( 'in_the_loop' );
		if ( ! empty( $in_the_loop ) ) {
			return true;
		}
	} elseif ( in_the_loop() ) {
		return true;
	}

	return false;
}

/**
 * Checks if the catalog lightbox is enabled.
 *
 * @return bool
 */
function is_lightbox_enabled() {
	$enable_catalog_lightbox = get_option( 'catalog_lightbox', 1 );
	$return                  = false;
	if ( 1 === (int) $enable_catalog_lightbox ) {
		$return = true;
	}

	return apply_filters( 'is_lightbox_enabled', $return );
}

/**
 * Checks if the catalog magnifier is enabled.
 *
 * @return bool
 */
function is_ic_magnifier_enabled() {
	$enable_catalog_magnifier = get_option( 'catalog_magnifier', 1 );
	$return                   = false;
	if ( 1 === (int) $enable_catalog_magnifier ) {
		$return = true;
	}

	return apply_filters( 'is_magnifier_enabled', $return );
}

/**
 * Checks if the sample product page exists.
 *
 * @return bool
 */
function ic_sample_page_exists() {
	if ( sample_product_id() ) {
		return true;
	}

	return false;
}

/**
 * Checks if the current request is running during plugin activation.
 *
 * @return bool
 */
function is_ic_activation_hook() {
	if ( get_option( 'IC_EPC_install', 0 ) ) {
		return true;
	}

	return false;
}

if ( ! function_exists( 'ic_ic_cookie_enabled' ) ) {

	/**
	 * Checks if catalog cookies are enabled.
	 *
	 * @return bool
	 */
	function ic_ic_cookie_enabled() {
		if ( ! defined( 'IC_USE_COOKIES' ) ) {
			define( 'IC_USE_COOKIES', false );
		}

		return IC_USE_COOKIES;
	}

}

/**
 * Checks if the simple integration mode is active.
 *
 * @return bool
 */
function is_ic_simple_mode() {
	if ( get_integration_type() === 'simple' ) {
		return true;
	}

	return false;
}

/**
 * Checks if the theme integration mode is active.
 *
 * @return bool
 */
function is_ic_theme_mode() {
	if ( get_integration_type() === 'theme' ) {
		return true;
	}

	return false;
}

/**
 * Checks if the boxed template is enabled for product pages.
 *
 * @return bool
 */
function is_ic_template_boxed() {
	$single_options = get_product_page_settings();
	if ( 'boxed' === $single_options['template'] ) {
		return true;
	}

	return false;
}

/**
 * Checks if SKU is enabled
 *
 * @return boolean
 */
function is_ic_sku_enabled() {
	if ( ! function_exists( 'get_product_sku' ) ) {
		return false;
	}
	$archive_multiple_settings = get_multiple_settings();
	if ( 1 !== (int) $archive_multiple_settings['disable_sku'] ) {
		return true;
	}

	return false;
}

/**
 * Checks if MPN is enabled
 *
 * @return boolean
 */
function is_ic_mpn_enabled() {
	if ( ! function_exists( 'get_product_mpn' ) ) {
		return false;
	}
	$archive_multiple_settings = get_multiple_settings();
	if ( 1 !== (int) $archive_multiple_settings['disable_mpn'] ) {
		return true;
	}

	return false;
}

/**
 * Checks if the provided query should be treated as the main catalog query.
 *
 * @param WP_Query|object|null $query Optional query object.
 *
 * @return bool
 */
function ic_is_main_query( $query = null ) {
	if ( empty( $query ) ) {
		global $wp_query;
		$query = $wp_query;
	}
	if ( ! empty( $query->query['ic_current_products'] ) ) {
		return false;
	}
	if ( $query->is_main_query() ) {
		return true;
	}

	return false;
}

/**
 * Checks if breadcrumbs are enabled.
 *
 * @return bool
 */
function is_ic_breadcrumbs_enabled() {
	$archive_multiple_settings = get_multiple_settings();
	if ( empty( $archive_multiple_settings['enable_product_breadcrumbs'] ) ) {
		return false;
	}

	return true;
}

/**
 * Checks if catalog assets should be enqueued.
 *
 * @return bool
 */
function ic_maybe_engueue_all() {
	if ( is_admin() || is_ic_catalog_page() ) {
		return true;
	}

	return apply_filters( 'ic_maybe_engueue_all', false );
}

/**
 * Checks if the provided post type or post type list is valid for the catalog.
 *
 * @param string|array $post_type Post type key or keys.
 *
 * @return bool
 */
function is_ic_valid_post_type( $post_type ) {
	if ( empty( $post_type ) ) {
		return false;
	}
	if ( is_array( $post_type ) && ! array_diff( $post_type, product_post_type_array() ) ) {
		return true;
	}
	if ( ! is_array( $post_type ) && in_array( $post_type, product_post_type_array(), true ) ) {
		return true;
	}

	return false;
}

/**
 * Checks if a taxonomy query should be excluded from search filtering.
 *
 * @param string      $taxonomy Taxonomy name.
 * @param string|null $label    Optional label.
 *
 * @return bool
 */
function ic_exclude_tax_query( $taxonomy, $label = null ) {
	$return = false;
	if ( 'al_product-attributes' === $taxonomy ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only conditional check.
		if ( ! is_ic_product_search() || ! empty( $_GET['s'] ) ) {
			$return = true;
		}
	}

	return apply_filters( 'ic_exclude_tax_query', $return, $taxonomy, $label );
}

/**
 * Checks if the provided meta key supports multiple values.
 *
 * @param string $meta_key Meta key.
 *
 * @return bool
 */
function is_ic_multiple_key( $meta_key ) {
	$support_multiple = apply_filters( 'ic_support_multiple_meta_keys', array() );
	if ( in_array( $meta_key, $support_multiple, true ) ) {
		return true;
	}

	return false;
}

/**
 * Checks if execution is currently inside the filters bar.
 *
 * @return bool
 */
function is_ic_inside_filters_bar() {
	global $is_filter_bar;

	return ! empty( $is_filter_bar );
}
