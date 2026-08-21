<?php
/**
 * Product Catalog Simple IC bootstrap.
 *
 * @package post-type-x
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ic_framework_require_once' ) ) {
	require_once __DIR__ . '/functions.php';
}
if ( ! defined( 'IC_FRAMEWORK_BOOTSTRAPPED' ) ) {
	define( 'IC_FRAMEWORK_BOOTSTRAPPED', true );
}

ic_framework_require_once( __DIR__ . '/conditionals.php' );
ic_framework_require_once( __DIR__ . '/class-ic-html-util.php' );
ic_framework_require_once( __DIR__ . '/settings/index.php' );
