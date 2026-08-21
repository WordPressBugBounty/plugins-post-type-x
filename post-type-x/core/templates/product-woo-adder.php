<?php
/**
 * Template Name:  Product Template
 *
 * @version     1.1.2
 * @package     ecommerce-product-catalog/templates
 * @author      impleCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

get_header( 'shop' );

// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Legacy hook name is retained for compatibility.
do_action( 'woo-adder-top' );

content_product_adder();

// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Legacy hook name is retained for compatibility.
do_action( 'woo-adder-bottom' );

get_footer( 'shop' );
