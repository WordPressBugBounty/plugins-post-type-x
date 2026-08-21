<?php
/**
 * Product helper wrappers.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'get_product_image' ) ) {

	/**
	 * Returns product image HTML.
	 *
	 * @param int  $product_id   Product ID.
	 * @param bool $show_default Whether to use the default image fallback.
	 *
	 * @return string
	 */
	function get_product_image( $product_id, $show_default = true ) {
		$product = ic_get_product_object( $product_id );

		return $product->image_html( $show_default );
	}

}

if ( ! function_exists( 'get_product_listing_image' ) ) {

	/**
	 * Returns product listing image HTML.
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return string
	 */
	function get_product_listing_image( $product_id ) {
		$product = ic_get_product_object( $product_id );

		return $product->listing_image_html();
	}

}

if ( ! function_exists( 'get_product_image_url' ) ) {

	/**
	 * Returns the product image URL.
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return string
	 */
	function get_product_image_url( $product_id ) {
		$product = ic_get_product_object( $product_id );

		return $product->image_url();
	}

}

if ( ! function_exists( 'get_product_name' ) ) {

	/**
	 * Returns the product name.
	 *
	 * @param int|null $product_id Product ID.
	 *
	 * @return string
	 */
	function get_product_name( $product_id = null ) {
		$product = ic_get_product_object( $product_id );

		return $product->name();
	}

}

if ( ! function_exists( 'get_product_url' ) ) {

	/**
	 * Returns the product URL.
	 *
	 * @param int|null $product_id Product ID.
	 *
	 * @return string
	 */
	function get_product_url( $product_id = null ) {
		$product = ic_get_product_object( $product_id );

		return $product->url();
	}

}

if ( ! function_exists( 'get_product_description' ) ) {

	/**
	 * Returns the product description.
	 *
	 * @param int|null $product_id Product ID.
	 *
	 * @return string
	 */
	function get_product_description( $product_id = null ) {
		$product = ic_get_product_object( $product_id );

		return $product->description();
	}

}

if ( ! function_exists( 'get_product_short_description' ) ) {

	/**
	 * Returns the product short description.
	 *
	 * @param int|null $product_id Product ID.
	 *
	 * @return string
	 */
	function get_product_short_description( $product_id = null ) {
		$product = ic_get_product_object( $product_id );

		return $product->short_description();
	}

}
