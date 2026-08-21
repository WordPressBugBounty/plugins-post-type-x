<?php
/**
 * Block catalog template registration.
 *
 * @version 1.0.0
 * @package ecommerce-product-catalog/templates
 * @author  impleCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers catalog block templates.
 */
class IC_Catalog_Block_Templates {
	/**
	 * Hooks template registration.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_block_templates' ) );
	}

	/**
	 * Registers the plugin block templates.
	 */
	public function register_block_templates() {
		static $registered = false;
		if ( $registered ) {
			return;
		}
		if ( function_exists( 'register_block_template' ) ) {
			// plugin-check:ignore wp_function_not_compatible_with_requires_wp -- The function_exists() guard skips catalog template registration on WordPress before 6.7.
			register_block_template(
				'ecommerce-product-catalog//archive-al_product',
				array(
					'title'      => __( 'Main Catalog Page', 'post-type-x' ),
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a local block template file bundled with the plugin.
					'content'    => file_get_contents( AL_BASE_PATH . '/templates/block/archive-al_product.html' ),
					'post_types' => array( 'al_product' ),
				)
			);
			// plugin-check:ignore wp_function_not_compatible_with_requires_wp -- The function_exists() guard skips product template registration on WordPress before 6.7.
			register_block_template(
				'ecommerce-product-catalog//single-al_product',
				array(
					'title'      => __( 'Single Catalog Page', 'post-type-x' ),
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a local block template file bundled with the plugin.
					'content'    => file_get_contents( AL_BASE_PATH . '/templates/block/single-al_product.html' ),
					'post_types' => array( 'al_product' ),
				)
			);
			// plugin-check:ignore wp_function_not_compatible_with_requires_wp -- The function_exists() guard skips category template registration on WordPress before 6.7.
			register_block_template(
				'ecommerce-product-catalog//taxonomy-al_product-cat',
				array(
					'title'      => __( 'Catalog Category Page', 'post-type-x' ),
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a local block template file bundled with the plugin.
					'content'    => file_get_contents( AL_BASE_PATH . '/templates/block/taxonomy-al_product-cat.html' ),
					'post_types' => array( 'al_product' ),
				)
			);
			$registered = true;
		}
	}
}

new IC_Catalog_Block_Templates();
