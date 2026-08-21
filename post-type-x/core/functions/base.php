<?php
/**
 * WordPress core field helpers.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_filter( 'enter_title_here', 'al_enter_title_here' );

/**
 * Modifies the product name field placeholder.
 *
 * @param string $message Placeholder text.
 *
 * @return string
 */
function al_enter_title_here( $message ) {
	if ( is_admin() ) {
		$screen = get_current_screen();
		if ( ic_string_contains( $screen->id, 'al_product' ) ) {
			if ( is_plural_form_active() ) {
				$names = get_catalog_names();
				/* translators: %s: Singular catalog label. */
				$message = sprintf( __( 'Enter %s name here', 'post-type-x' ), ic_strtolower( $names['singular'] ) );
			} else {
				$message = __( 'Enter item name here', 'post-type-x' );
			}
		}
	}

	return $message;
}
