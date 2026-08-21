<?php
/**
 * Qtranslate slug compatibility helpers.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_filter( 'product_query_var', 'ic_qtranslate_mod_queryvar' );

/**
 * Forces the catalog query var when qTranslate is active.
 *
 * @return string
 */
function ic_qtranslate_mod_queryvar() {
	return 'al_product';
}
