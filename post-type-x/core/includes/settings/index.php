<?php
/**
 * Loads the plugin settings layer.
 *
 * The plugin settings folder is defined and managed here.
 *
 * @version 1.0.0
 * @package ecommerce-product-catalog/includes/settings
 * @author  impleCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once AL_BASE_PATH . '/includes/settings/settings-functions.php';
require_once AL_BASE_PATH . '/includes/settings/tooltips.php';

require_once AL_BASE_PATH . '/includes/settings/general.php';
// Attribute settings remain disabled in this bootstrap file.
// Shipping settings remain disabled in this bootstrap file.
require_once AL_BASE_PATH . '/includes/settings/custom-design.php';
require_once AL_BASE_PATH . '/includes/settings/custom-names.php';
require_once AL_BASE_PATH . '/includes/settings/csv.php';
require_once AL_BASE_PATH . '/includes/settings/image-sizes.php';
require_once AL_BASE_PATH . '/includes/settings/extension-info.php';
require_once AL_BASE_PATH . '/includes/settings/search.php';
