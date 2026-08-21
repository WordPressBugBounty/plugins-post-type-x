<?php
/**
 * Product category widget.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
require_once AL_BASE_PATH . '/includes/widgets/class-product-cat-widget.php';
require_once AL_BASE_PATH . '/includes/widgets/class-ic-cat-walker-categorydropdown.php';

add_action( 'wp', 'ic_category_none_redirect' );

/**
 * Redirects the "none" category selection back to the listing page.
 *
 * @return void
 */
function ic_category_none_redirect() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only front-end redirect parameter.
	if ( is_ic_product_listing_enabled() && isset( $_GET['al_product-cat'] ) && '-1' === sanitize_text_field( wp_unslash( $_GET['al_product-cat'] ) ) ) {
		$listing_url = product_listing_url();
		if ( ! empty( $listing_url ) ) {
			wp_safe_redirect( $listing_url );
			exit;
		}
	}
}
