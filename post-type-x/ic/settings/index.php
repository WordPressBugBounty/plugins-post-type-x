<?php
/**
 * Shared settings framework bootstrap.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

ic_framework_require_once( __DIR__ . '/class-ic-settings-section.php' );
ic_framework_require_once( __DIR__ . '/class-ic-settings-section-table.php' );
ic_framework_require_once( __DIR__ . '/class-ic-settings-design-table.php' );
ic_framework_require_once( __DIR__ . '/class-ic-settings-standard-table.php' );
ic_framework_require_once( __DIR__ . '/class-ic-settings-helper-box.php' );
ic_framework_require_once( __DIR__ . '/class-ic-settings-page.php' );
ic_framework_require_once( __DIR__ . '/class-ic-settings-screen.php' );
