<?php
/**
 * Product category taxonomy registration and query helpers.
 *
 * @version 1.0.0
 * @package ecommerce-product-catalog
 * @author  impleCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
add_action( 'init', 'ic_create_product_categories', 4 );

/**
 * Registers product categories
 */
function ic_create_product_categories() {
	$archive_multiple_settings = get_multiple_settings();
	$category_enable           = true;
	if ( 'simple' === get_integration_type() ) {
		$category_enable = false;
	}
	if ( is_plural_form_active() ) {
		$names             = get_catalog_names();
		$names['singular'] = ic_ucfirst( $names['singular'] );
		$labels            = array(
			/* translators: %s: singular catalog name. */
			'name'              => sprintf( __( '%s Categories', 'post-type-x' ), $names['singular'] ),
			/* translators: %s: singular catalog name. */
			'singular_name'     => sprintf( __( '%s Category', 'post-type-x' ), $names['singular'] ),
			/* translators: %s: singular catalog name. */
			'search_items'      => sprintf( __( 'Search %s Categories', 'post-type-x' ), $names['singular'] ),
			/* translators: %s: singular catalog name. */
			'all_items'         => sprintf( __( 'All %s Categories', 'post-type-x' ), $names['singular'] ),
			/* translators: %s: singular catalog name. */
			'parent_item'       => sprintf( __( 'Parent %s Category', 'post-type-x' ), $names['singular'] ),
			/* translators: %s: singular catalog name. */
			'parent_item_colon' => sprintf( __( 'Parent %s Category:', 'post-type-x' ), $names['singular'] ),
			/* translators: %s: singular catalog name. */
			'edit_item'         => sprintf( __( 'Edit %s Category', 'post-type-x' ), $names['singular'] ),
			/* translators: %s: singular catalog name. */
			'update_item'       => sprintf( __( 'Update %s Category', 'post-type-x' ), $names['singular'] ),
			/* translators: %s: singular catalog name. */
			'add_new_item'      => sprintf( __( 'Add New %s Category', 'post-type-x' ), $names['singular'] ),
			/* translators: %s: singular catalog name. */
			'new_item_name'     => sprintf( __( 'New %s Category', 'post-type-x' ), $names['singular'] ),
			/* translators: %s: singular catalog name. */
			'menu_name'         => sprintf( __( '%s Categories', 'post-type-x' ), $names['singular'] ),
		);
	} else {
		$labels = array(
			'name'              => __( 'Categories', 'post-type-x' ),
			'singular_name'     => __( 'Category', 'post-type-x' ),
			'search_items'      => __( 'Search Categories', 'post-type-x' ),
			'all_items'         => __( 'All Categories', 'post-type-x' ),
			'parent_item'       => __( 'Parent Category', 'post-type-x' ),
			'parent_item_colon' => __( 'Parent Category:', 'post-type-x' ),
			'edit_item'         => __( 'Edit Category', 'post-type-x' ),
			'update_item'       => __( 'Update Category', 'post-type-x' ),
			'add_new_item'      => __( 'Add New Category', 'post-type-x' ),
			'new_item_name'     => __( 'New Category', 'post-type-x' ),
			'menu_name'         => __( 'Categories', 'post-type-x' ),
		);
	}

	$args = array(
		'public'            => $category_enable,
		'show_in_rest'      => true,
		'hierarchical'      => true,
		'labels'            => $labels,
		'show_ui'           => true,
		'show_admin_column' => true,
		'query_var'         => true,
		'rewrite'           => array(
			'hierarchical' => true,
			'slug'         => apply_filters( 'product_category_slug_value_register', urldecode( sanitize_title( $archive_multiple_settings['category_archive_url'] ) ) ),
			'with_front'   => false,
		),
		'capabilities'      => array(
			'manage_terms' => 'manage_product_categories',
			'edit_terms'   => 'edit_product_categories',
			'delete_terms' => 'delete_product_categories',
			'assign_terms' => 'assign_product_categories',
		),
	);

	register_taxonomy( 'al_product-cat', 'al_product', $args );
}


/**
 * Updates product category term counts.
 *
 * @return void
 */
function ic_update_category_count() {
	$update_taxonomy = 'al_product-cat';
	$get_terms_args  = array(
		'taxonomy'   => $update_taxonomy,
		'fields'     => 'ids',
		'hide_empty' => false,
	);

	$update_terms = ic_get_terms( $get_terms_args );
	wp_update_term_count_now( $update_terms, $update_taxonomy );
}


add_action( 'ic_pre_get_products_tax', 'ic_limit_products_to_current_cat' );

/**
 * Limits product listing queries to the current category only.
 *
 * @param WP_Query $query Query object.
 *
 * @return void
 */
function ic_limit_products_to_current_cat( $query ) {
	$multiple_settings = get_multiple_settings();
	if ( 'only_subcategories' !== $multiple_settings['category_top_cats'] ) {
		return;
	}
	if ( ! empty( $query->tax_query->queries ) ) {
		$tax_query = $query->get( 'tax_query' );
		if ( empty( $tax_query ) ) {
			$tax_query = array();
		}
		foreach ( $query->tax_query->queries as $querie ) {
			if ( isset( $querie['include_children'] ) ) {
				$querie['include_children'] = 0;
			}
			$tax_query[] = $querie;
		}
		$query->set( 'tax_query', $tax_query );
	}
}


add_action( 'ic_pre_get_products_listing', 'ic_limit_products_to_loose' );

/**
 * Limits product listings to uncategorized products when configured.
 *
 * @param WP_Query $query Query object.
 *
 * @return void
 */
function ic_limit_products_to_loose( $query ) {
	$multiple_settings = get_multiple_settings();
	if ( 'cats_only' === $multiple_settings['product_listing_cats'] && ic_is_main_query( $query ) ) {
		$tax_query = $query->get( 'tax_query' );
		if ( empty( $tax_query ) ) {
			$tax_query = array();
		}
		$tax_query[] = ic_get_limit_loose_products_args();
		$query->set( 'tax_query', $tax_query );
	}
}


add_filter( 'home_product_listing_query', 'ic_limit_products_to_loose_home' );

/**
 * Limits home listing queries to uncategorized products when configured.
 *
 * @param array $query Query arguments.
 *
 * @return array
 */
function ic_limit_products_to_loose_home( $query ) {
	$multiple_settings = get_multiple_settings();
	if ( 'cats_only' === $multiple_settings['product_listing_cats'] ) {
		if ( empty( $query['tax_query'] ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Cats-only catalog mode requires this taxonomy constraint to preserve its configured result set.
			$query['tax_query'] = array();
		}
		$query['tax_query'][] = ic_get_limit_loose_products_args();
	}

	return $query;
}

/**
 * Returns the tax query that excludes categorized products.
 *
 * @return array
 */
function ic_get_limit_loose_products_args() {
	return array(
		'taxonomy' => get_current_screen_tax(),
		'field'    => 'term_id',
		'operator' => 'NOT EXISTS',
		'terms'    => array( '' ),
	);
}

add_filter( 'wp_terms_checklist_args', 'ic_disable_checked_on_top' );

/**
 * Keeps selected terms in hierarchy order for product categories.
 *
 * @param array $args Checklist arguments.
 *
 * @return array
 */
function ic_disable_checked_on_top( $args ) {
	if ( ! empty( $args['taxonomy'] ) && 'al_product-cat' === $args['taxonomy'] ) {
		// Necessary to maintain the categories hierarchy in the metabox on the product edit screen.
		$args['checked_ontop'] = false;
	}

	return $args;
}
