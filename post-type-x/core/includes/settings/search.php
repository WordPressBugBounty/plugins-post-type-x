<?php
/**
 * Loads the settings search helper.
 *
 * @version 1.0.0
 * @package ecommerce-product-catalog/includes/settings
 * @author  impleCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/class-ic-settings-search.php';

global $ic_settings_search;
if ( ! $ic_settings_search instanceof IC_Settings_Search ) {
	$ic_settings_search = new IC_Settings_Search();
}
