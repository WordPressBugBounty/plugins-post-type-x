<?php
/**
 * Activation wizard bootstrap.
 *
 * Loads and initializes the activation wizard.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/class-ic-cat-activation-wizard.php';

$ic_cat_activation_wizard = new IC_Cat_Activation_Wizard();
