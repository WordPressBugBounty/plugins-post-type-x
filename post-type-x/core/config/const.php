<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Manages plugin parameters
 *
 * Here plugin parameters are defined and managed.
 *
 * @version        1.0.0
 * @package        post-type-x/core/functions
 * @author        impleCode
 */
define( 'DEF_SHIPPING_OPTIONS_NUMBER', '1' );
define( 'DEF_ATTRIBUTES_OPTIONS_NUMBER', '3' );
define( 'DEF_VALUE', '0' );
if ( ! defined( 'DEF_CATALOG_SINGULAR' ) ) {
	define( 'DEF_CATALOG_SINGULAR', __( 'Product', 'post-type-x' ) );
}
if ( ! defined( 'DEF_CATALOG_PLURAL' ) ) {
	define( 'DEF_CATALOG_PLURAL', __( 'Products', 'post-type-x' ) );
}

if ( ! defined( 'IC_CATALOG_PLUGIN_NAME' ) ) {
	define( 'IC_CATALOG_PLUGIN_NAME', 'eCommerce Product Catalog' );
}

if ( ! defined( 'IC_CATALOG_PLUGIN_SLUG' ) ) {
	define( 'IC_CATALOG_PLUGIN_SLUG', 'post-type-x' );
}