<?php
/**
 * WooCommerce compatibility template helpers.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WP Product template functions
 *
 * Here all plugin template functions are defined.
 *
 * @version     1.1.3
 * @package     ecommerce-product-catalog/
 * @author      impleCode
 */
if ( ! function_exists( 'is_product' ) ) {

	/**
	 * Determines whether the current page is a product page.
	 *
	 * @return bool
	 */
	function is_product() {
		return is_ic_product_page();
	}

}

if ( ! function_exists( 'is_shop' ) ) {

	/**
	 * Determines whether the current page is a shop page.
	 *
	 * @return bool
	 */
	function is_shop() {
		return is_ic_product_listing();
	}

}
if ( ! function_exists( 'is_product_taxonomy' ) ) {

	/**
	 * Determines whether the current page is a product taxonomy page.
	 *
	 * @return bool
	 */
	function is_product_taxonomy() {
		return is_ic_taxonomy_page();
	}

}
if ( ! function_exists( 'is_product_category' ) ) {

	/**
	 * Determines whether the current page is a product category page.
	 *
	 * @return bool
	 */
	function is_product_category() {
		return is_ic_taxonomy_page();
	}

}
if ( ! function_exists( 'is_product_tag' ) ) {

	/**
	 * Determines whether the current page is a product tag page.
	 *
	 * @return bool
	 */
	function is_product_tag() {
		return false;
	}

}
if ( ! function_exists( 'is_cart' ) ) {

	/**
	 * Determines whether the current page is a cart page.
	 *
	 * @return bool
	 */
	function is_cart() {
		return false;
	}

}
if ( ! function_exists( 'is_checkout' ) ) {

	/**
	 * Determines whether the current page is a checkout page.
	 *
	 * @return bool
	 */
	function is_checkout() {
		return false;
	}

}
if ( ! function_exists( 'is_checkout_pay_page' ) ) {

	/**
	 * Determines whether the current page is a checkout pay page.
	 *
	 * @return bool
	 */
	function is_checkout_pay_page() {
		return false;
	}

}
if ( ! function_exists( 'woocommerce_get_sidebar' ) ) {

	/**
	 * Outputs the current theme sidebar.
	 */
	function woocommerce_get_sidebar() {
		get_sidebar();
	}

}

if ( ! function_exists( 'wc_get_page_id' ) ) {

	/**
	 * Returns a default WooCommerce page ID fallback.
	 *
	 * @return int
	 */
	function wc_get_page_id() {
		return -1;
	}

}

if ( ! function_exists( 'woocommerce_page_title' ) ) {

	/**
	 * Outputs or returns the current page title.
	 *
	 * @param bool $echo Whether to echo the title.
	 * @return string|null
	 */
	function woocommerce_page_title( $echo = true ) {
		$title = get_the_title();
		if ( $echo ) {
			echo esc_html( $title );
		} else {
			return $title;
		}
	}

}

if ( ! function_exists( 'woocommerce_template_single_title' ) ) {

	/**
	 * Outputs the single product title markup.
	 */
	function woocommerce_template_single_title() {
		the_title( '<h1 class="product_title entry-title">', '</h1>' );
	}

}
