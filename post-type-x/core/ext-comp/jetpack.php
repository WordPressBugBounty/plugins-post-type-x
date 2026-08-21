<?php
/**
 * Jetpack compatibility helpers.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'before_product_list', 'ic_catalog_restore_jetpack_featured_image' );
add_action( 'before_ajax_product_list', 'ic_catalog_restore_jetpack_featured_image' );

/**
 * Restores the Jetpack featured image metadata filter.
 *
 * @return void
 */
function ic_catalog_restore_jetpack_featured_image() {
	remove_filter( 'get_post_metadata', 'jetpack_featured_images_remove_post_thumbnail', true, 4 );
}

add_action( 'single_product_begin', 'ic_catalog_jetpack_remove_content_sharing' );

/**
 * Removes Jetpack sharing from single product content.
 *
 * @return void
 */
function ic_catalog_jetpack_remove_content_sharing() {
	if ( class_exists( 'Jetpack_Likes' ) ) {
		remove_filter( 'the_content', 'sharing_display', 19 );
		remove_filter( 'the_content', array( Jetpack_Likes::init(), 'post_likes' ), 30, 1 );
	}
}

add_filter( 'jetpack_relatedposts_filter_options', 'ic_exclude_jetpack_related_from_products' );

/**
 * Disables Jetpack related posts on catalog pages.
 *
 * @param array $options Related posts options.
 *
 * @return array
 */
function ic_exclude_jetpack_related_from_products( $options ) {
	if ( is_ic_catalog_page() ) {
		$options['enabled'] = false;
	}

	return $options;
}

add_filter( 'infinite_scroll_archive_supported', 'ic_jetpack_infinite_scroll_disable' );

/**
 * Disables jetpack infinite scroll on product pages
 *
 * @param bool $is_enabled Whether infinite scroll is enabled.
 *
 * @return bool
 */
function ic_jetpack_infinite_scroll_disable( $is_enabled ) {
	if ( is_ic_product_listing() || is_ic_taxonomy_page() || is_ic_product_search() ) {
		return false;
	}

	return $is_enabled;
}
