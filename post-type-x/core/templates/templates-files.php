<?php
/**
 * Template file path helpers.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Gets the product adder template path.
 *
 * @param bool $auto Whether to use the auto product adder file.
 * @param bool $even_if_empty Whether to return the theme path even if missing.
 * @return string
 */
function get_product_adder_path( $auto = false, $even_if_empty = false ) {
	if ( $auto ) {
		if ( $even_if_empty ) {
			return get_stylesheet_directory() . '/auto-product-adder.php';
		}

		return locate_template( array( 'auto-product-adder.php' ) );
	} else {
		if ( $even_if_empty ) {
			return get_stylesheet_directory() . '/product-adder.php';
		}

		return locate_template( array( 'product-adder.php' ) );
	}
}

if ( ! function_exists( 'get_custom_templates_folder' ) ) {

	/**
	 * Gets the custom templates folder path.
	 *
	 * @return string
	 */
	function get_custom_templates_folder() {
		return get_stylesheet_directory() . '/implecode/';
	}

}

/**
 * Gets the custom product page template path.
 *
 * @return string
 */
function get_custom_product_page_path() {
	$folder = get_custom_templates_folder();

	return $folder . 'product-page.php';
}

/**
 * Gets the custom inner product page template path.
 *
 * @return string
 */
function get_custom_product_page_inside_path() {
	$folder = get_custom_templates_folder();

	return $folder . 'product-page-inside.php';
}

/**
 * Gets the custom product listing template path.
 *
 * @return string
 */
function get_custom_product_listing_path() {
	$folder = get_custom_templates_folder();

	return $folder . 'product-listing.php';
}

/**
 * Gets the active page.php path.
 *
 * @return string
 */
function get_page_php_path() {
	if ( file_exists( get_stylesheet_directory() . '/page.php' ) ) {
		$path = get_stylesheet_directory() . '/page.php';
	} else {
		$path = get_theme_root() . '/' . get_template() . '/page.php';
	}

	return $path;
}

/**
 * Gets the active index.php path.
 *
 * @return string
 */
function get_index_php_path() {
	if ( file_exists( get_stylesheet_directory() . '/index.php' ) ) {
		$path = get_stylesheet_directory() . '/index.php';
	} else {
		$path = get_theme_root() . '/' . get_template() . '/index.php';
	}

	return $path;
}

/**
 * Gets the listing template path.
 *
 * @param string|null $template_name Template key.
 * @return string|null
 */
function ic_get_listing_template_path( $template_name = null ) {
	if ( empty( $template_name ) ) {
		$template_name = get_product_listing_template();
	}
	$template_files = apply_filters( 'ic_listing_template_file_paths', array() );
	if ( ! empty( $template_files[ $template_name ] ) ) {
		return $template_files[ $template_name ];
	}
}
