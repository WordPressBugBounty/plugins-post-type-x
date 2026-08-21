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

if ( ! function_exists( 'ic_error_log' ) ) {

	/**
	 * Writes debug information to the PHP error log in WP_DEBUG mode.
	 *
	 * @param mixed       $what  Logged value.
	 * @param string|bool $param Optional GET flag name that enables logging.
	 * @param bool        $cron  Whether to log during cron execution.
	 *
	 * @return void
	 */
	function ic_error_log( $what, $param = false, $cron = false ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Debug helper only reads presence of query flags.
		$global_debug_enabled = defined( 'IC_FORCE_DEBUG_LOG' ) && IC_FORCE_DEBUG_LOG;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Debug helper only reads presence of query flags.
		$request_debug_enabled = isset( $_GET['ic_debug_log'] ) || isset( $_GET['ic-error-log'] );
		if ( ! $global_debug_enabled && ! $request_debug_enabled && ! $param ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Debug logging is opt-in and read-only.
		if ( $param && ! isset( $_GET[ $param ] ) && ! is_ic_front_ajax() ) {
			return;
		}
		if ( $cron || ! wp_doing_cron() ) {
			$prefix = '';
			if ( is_ic_ajax() ) {
				$prefix = 'ajax ';
			}
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r,WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging runs only in WP_DEBUG mode.
			error_log( $prefix . print_r( $what, 1 ) );
		}
	}
}
