<?php
/**
 * Yoast SEO compatibility helpers.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Enables compatible Yoast behavior on catalog edit screens.
 *
 * @return void
 */
function implecode_wpseo_compatible() {
	$post_type = get_quasi_post_type();
	if ( 'al_product' === $post_type ) {
		add_filter( 'wpseo_metabox_prio', 'implecode_wpseo_compatible_priority' );
	}
}

add_action( 'add_meta_boxes', 'implecode_wpseo_compatible' );

/**
 * Lowers the Yoast metabox priority on products.
 *
 * @return string
 */
function implecode_wpseo_compatible_priority() {
	return 'low';
}

add_action( 'wp', 'remove_default_catalog_title', 100 );

/**
 * Allows product listing title tags to be managed by Yoast SEO.
 *
 * @return void
 */
function remove_default_catalog_title() {
	remove_filter( 'wp_title', 'product_archive_title', 99, 3 );
}

add_action( 'add_meta_boxes', 'product_listing_remove_wpseo', 16 );

/**
 * Removes the WPSEO metabox from the product listing edit screen.
 *
 * The title and description are managed from WPSEO settings.
 *
 * @return void
 */
function product_listing_remove_wpseo() {
	$id = get_product_listing_id();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin screen routing parameter.
	$post_id = absint( filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT ) );

	if ( is_admin() && $id === $post_id && ! is_ic_shortcode_integration() ) {
		remove_meta_box( 'wpseo_meta', 'page', 'normal' );
	}
}

/**
 * Removes the Yoast SEO script to avoid JavaScript errors on product listing edit screens.
 *
 * @return void
 */
function product_listing_remove_wpseo_js() {
	$id = get_product_listing_id();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin screen routing parameter.
	$post_id = absint( filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT ) );

	if ( is_admin() && $id === $post_id && ! is_ic_shortcode_integration() ) {
		wp_deregister_script( 'yoast-seo' );
	}
}

add_action( 'admin_print_footer_scripts', 'product_listing_remove_wpseo_js', 1 );

add_filter( 'wpseo_title', 'ic_remove_seo_archives', 20 );

/**
 * Removes unnecessary archives element from title
 *
 * @param string $title Existing title.
 *
 * @return string
 */
function ic_remove_seo_archives( $title ) {
	if ( is_ic_admin() ) {
		return $title;
	}

	return str_replace(
		array(
			/* translators: Archive title suffix used by Yoast SEO. */
			// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- This label belongs to Yoast SEO and must use Yoast's translation catalogue.
			' ' . __( 'Archives', 'wordpress-seo' ),
			/* translators: Archive title suffix used by Yoast SEO. */
			// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- This label belongs to Yoast SEO and must use Yoast's translation catalogue.
			' ' . __( 'Archive', 'wordpress-seo' ),
		),
		'',
		$title
	);
}
