<?php
/**
 * Loads the plugin functions layer.
 *
 * The plugin functions folder is defined and managed here.
 *
 * @version 1.0.0
 * @package ecommerce-product-catalog/functions
 * @author  impleCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once AL_BASE_PATH . '/functions/content-functions.php';

require_once AL_BASE_PATH . '/functions/short-functions.php';
require_once AL_BASE_PATH . '/functions/support.php';
require_once AL_BASE_PATH . '/functions/conditionals.php';
require_once AL_BASE_PATH . '/functions/compatibility.php';
require_once AL_BASE_PATH . '/functions/class-ic-epc-extension-compatibility.php';
// Cached DB helpers stay disabled in this bootstrap file.
require_once AL_BASE_PATH . '/functions/globals.php';
require_once AL_BASE_PATH . '/functions/rewrite.php';
require_once AL_BASE_PATH . '/functions/cached.php';

/**
 * Loads admin-only function files.
 */
function start_admin_only_functions() {
	if ( ! is_admin() && is_user_logged_in() ) {
		require_once AL_BASE_PATH . '/functions/catalog-admin.php';
	} elseif ( is_admin() ) {
		require_once AL_BASE_PATH . '/functions/duplicate.php';
	}
}

add_action( 'init', 'start_admin_only_functions' );
