<?php
/**
 * Catalog template bootstrap.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/class-ic-catalog-template.php';

global $ic_catalog_template;
$ic_catalog_template = new ic_catalog_template();
