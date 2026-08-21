<?php
/**
 * Elementor integration loader.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if (version_compare(PHP_VERSION, '7.0.0', '>')) {
	require_once AL_BASE_PATH . '/ext-comp/elementor/class-implecode-elementor-widgets.php';

	$imple_code_elementor_widgets = new ImpleCode_Elementor_Widgets();
}

