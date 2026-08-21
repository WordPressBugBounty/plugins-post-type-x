<?php
/**
 * Page builder compatibility class.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Compatibility wrapper for supported page builders.
 */
class IC_Catalog_Builders_Compat {

	/**
	 * Registers compatibility hooks for supported builders.
	 */
	public function __construct() {
		add_action( 'ic_catalog_wp', array( $this, 'wp' ) );
		add_filter( 'et_builder_post_types', array( $this, 'divi_builder_enable' ) );
		add_filter( 'ic_shortcode_catalog_apply', array( $this, 'disable_shortcode_catalog' ) );
		if ( defined( 'CT_VERSION' ) ) {
			add_action( 'ic_shortcode_catalog_hooks_added', array( $this, 'oxygen' ) );
		}
	}

	/**
	 * Enables the Divi builder for product posts.
	 *
	 * @param array $post_types Enabled post types.
	 *
	 * @return array
	 */
	public function divi_builder_enable( $post_types ) {
		$post_types[] = 'al_product';

		return $post_types;
	}

	/**
	 * Removes Divi stylesheet replacement for catalog pages.
	 *
	 * @return void
	 */
	public function wp() {
		remove_action( 'wp_enqueue_scripts', 'et_divi_replace_stylesheet', 99999998 );
	}

	/**
	 * Disables shortcode rendering when a builder overrides the body layout.
	 *
	 * @param bool $disable Whether shortcode rendering is disabled.
	 *
	 * @return bool
	 */
	public function disable_shortcode_catalog( $disable ) {
		if ( function_exists( 'et_theme_builder_get_template_layouts' ) ) {
			$layouts = et_theme_builder_get_template_layouts();
			if ( ! empty( $layouts['et_body_layout']['enabled'] ) && ! empty( $layouts['et_body_layout']['override'] ) ) {
				$disable = true;
			}
		}

		return $disable;
	}

	/**
	 * Removes conflicting Oxygen hooks from the shortcode catalog instance.
	 *
	 * @param object $shortcode_catalog Shortcode catalog instance.
	 *
	 * @return void
	 */
	public function oxygen( $shortcode_catalog ) {
		remove_action( 'ic_catalog_wp_head_start', array( $shortcode_catalog, 'catalog_query_force' ), -1, 0 );
		remove_action( 'ic_catalog_wp_head_start', array( $shortcode_catalog, 'catalog_query' ), -1, 0 );
		remove_filter( 'single_post_title', array( $shortcode_catalog, 'product_page_title' ), 99, 1 );
	}
}
