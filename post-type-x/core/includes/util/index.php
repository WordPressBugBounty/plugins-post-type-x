<?php
/**
 * Utility bootstrap helpers.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( ( 'ic_catalog_widget' ) ) ) {
	require_once __DIR__ . '/class-ic-catalog-widget.php';
}
if ( ! class_exists( ( 'ic_catalog_menu_element' ) ) ) {
	require_once __DIR__ . '/class-ic-catalog-menu-element.php';
}
