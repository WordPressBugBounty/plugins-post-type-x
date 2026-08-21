<?php
/**
 * Plugin Name: eCommerce Product Catalog
 * Plugin URI: https://implecode.com/wordpress/product-catalog/#cam=in-plugin-urls&key=plugin-url
 * Description: Easy to use, powerful and beautiful WordPress eCommerce plugin from impleCode. A Great choice if you want to sell easy and quick. Or beautifully present your products on a WordPress website. Full WordPress integration does a great job not only for Merchants but also for Developers and Theme Constructors.
 * Version: 3.5.11
 * Author: impleCode
 * Author URI: https://implecode.com/#cam=in-plugin-urls&key=author-url
 * Text Domain: post-type-x
 * Domain Path: /lang
 *
 * Copyright: 2026 impleCode.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/class-ecommerce-product-catalog.php';

if ( ! function_exists( 'impleCode_EPC' ) ) {

	add_action( 'plugins_loaded', 'impleCode_EPC', - 2 );

	/**
	 * The main function responsible for returning eCommerce_Product_Catalog
	 *
	 * @return eCommerce_Product_Catalog
	 * @since 2.4.7
	 */
	function impleCode_EPC() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid -- Legacy public bootstrap accessor kept for compatibility.
		global $ic_epc_instance;
		if ( empty( $ic_epc_instance ) ) {
			$ic_epc_instance = eCommerce_Product_Catalog::instance();
		}

		return $ic_epc_instance;
	}

}


if ( ! function_exists( 'IC_EPC_install' ) ) {

	register_activation_hook( __FILE__, 'IC_EPC_install' );

	/**
	 * Runs plugin activation tasks.
	 *
	 * @return void
	 */
	function IC_EPC_install() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid -- Legacy activation callback kept for compatibility.
		update_option( 'IC_EPC_install', 1, false );
		eCommerce_Product_Catalog::instance();
		eCommerce_Product_Catalog::content();
		epc_activation_function();
	}

}
