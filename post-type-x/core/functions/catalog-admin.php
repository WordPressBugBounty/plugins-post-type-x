<?php
/**
 * Catalog frontend admin loader.
 *
 * @package ecommerce-product-catalog/functions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/class-ic-catalog-frontend-admin.php';

$ic_catalog_frontend_admin = new IC_Catalog_Frontend_Admin();
