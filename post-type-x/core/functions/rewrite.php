<?php
/**
 * Listing rewrite bootstrap.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once AL_BASE_PATH . '/functions/class-ic-catalog-listing-modified.php';

$ic_catalog_listing_modified = new IC_Catalog_Listing_Modified();
