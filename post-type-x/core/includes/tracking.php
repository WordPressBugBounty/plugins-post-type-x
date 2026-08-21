<?php
/**
 * Loads the plugin tracking integration.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once AL_BASE_PATH . '/includes/class-ic-epc-tracking.php';

add_action( 'admin_init', 'ic_epc_get_actions', 99 );

/**
 * Dispatches tracking actions from the query string.
 *
 * @return void
 */
function ic_epc_get_actions() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public action router; the value is sanitized with sanitize_key() on this line.
	$action = isset( $_GET['ic_epc_action'] ) ? sanitize_key( wp_unslash( $_GET['ic_epc_action'] ) ) : '';

	if ( ! empty( $action ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The raw query args are handed to the hook payload; each listener sanitizes what it consumes.
		do_action( 'ic_epc_' . $action, $_GET );
	}
}

$ic_epc_tracking = new IC_EPC_Tracking();
