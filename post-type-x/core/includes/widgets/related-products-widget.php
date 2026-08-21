<?php
/**
 * Related products widget.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
require_once AL_BASE_PATH . '/includes/widgets/class-related-products-widget.php';

add_action( 'implecode_register_widgets', 'register_related_products_widget' );

/**
 * Registers the related products widget.
 *
 * @return void
 */
function register_related_products_widget() {
	register_widget( 'Related_Products_Widget' );
}
