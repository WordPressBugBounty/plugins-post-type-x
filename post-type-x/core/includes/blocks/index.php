<?php
/**
 * Loads the catalog blocks bootstrap.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once AL_BASE_PATH . '/includes/blocks/class-ic-epc-blocks.php';

$ic_epc_blocks = new IC_EPC_Blocks();
