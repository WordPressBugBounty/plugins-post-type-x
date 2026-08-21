<?php
/**
 * Product settings defaults.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * Manages product settings defaults
 *
 * Here product settings defaults are defined and managed.
 *
 * @version     1.4.0
 * @package     ecommerce-product-catalog/includes
 * @author      impleCode
 */
define(
	'DEFAULT_ARCHIVE_MULTIPLE_SETTINGS',
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Stored option defaults use the plugin's legacy serialized format.
	serialize(
		array(
			'archive_products_limit'     => 12,
			'category_archive_url'       => 'product-category',
			'enable_product_breadcrumbs' => 0,
			'breadcrumbs_title'          => '',
			'seo_title'                  => '',
			'seo_title_sep'              => 1,
		)
	)
);

define( 'DEFAULT_ARCHIVE_TEMPLATE', 'default' );

define(
	'DEFAULT_DESIGN_SCHEMES',
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Stored option defaults use the plugin's legacy serialized format.
	serialize(
		array(
			'price-size'  => 'big-price',
			'price-color' => 'red-price',
			'box-color'   => 'green-box',
		)
	)
);

define( 'ENABLE_CATALOG_LIGHTBOX', 1 );

define(
	'MULTI_SINGLE_OPTIONS',
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Stored option defaults use the plugin's legacy serialized format.
	serialize(
		array(
			'enable_product_gallery' => 1,
			'template'               => 'boxed',
		)
	)
);
