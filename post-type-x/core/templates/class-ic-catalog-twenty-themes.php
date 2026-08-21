<?php
/**
 * Twenty theme integration template functions.
 *
 * @version 1.1.3
 * @package ecommerce-product-catalog
 * @author  impleCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles integration with Twenty themes.
 */
class IC_Catalog_Twenty_Themes {

	/**
	 * Hooks the Twenty theme integration callbacks.
	 */
	public function __construct() {
		add_filter( 'theme_mod_page_layout', array( $this, 'twentyseventeen_layout' ) );
		// Default shortcode integration remains disabled here.
	}

	/**
	 * Forces the one-column layout on integrated catalog pages.
	 *
	 * @param string $value Theme layout value.
	 * @return string
	 */
	public function twentyseventeen_layout( $value ) {
		if ( is_ic_catalog_page() && is_ic_shortcode_integration() ) {
			return 'one-column';
		}
		return $value;
	}

	/**
	 * Keeps the default listing content disabled.
	 *
	 * @return string
	 */
	public function default_listing_content() {
		return ''; // Disabled default shortcode integration.
	}
}

$ic_catalog_twenty_themes = new IC_Catalog_Twenty_Themes();
