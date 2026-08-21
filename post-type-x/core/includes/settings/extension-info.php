<?php
/**
 * Extension info settings bootstrap loader.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once AL_BASE_PATH . '/includes/settings/class-ic-extension-settings-info.php';

$ic_extension_settings_info = new IC_Extension_Settings_Info();
