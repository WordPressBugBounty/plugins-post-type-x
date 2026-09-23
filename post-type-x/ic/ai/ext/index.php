<?php
/**
 * AI third-party compatibility bootstrap.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

ic_framework_require_once( __DIR__ . '/class-ic-ai-ext-yoast-seo.php' );

new IC_AI_Ext_Yoast_SEO();
add_action(
	'admin_enqueue_scripts',
	static function () {
		if ( ! wp_script_is( 'ic-ai-admin', 'enqueued' ) ) {
			return; }
		$base = __DIR__ . '/js/ic-ai-ext-yoast-seo';
		$file = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? $base . '.js' : $base . '.min.js' );
		if ( file_exists( $file ) ) {
			wp_enqueue_script( 'ic-ai-ext-yoast-seo', plugins_url( 'js/' . basename( $file ), __FILE__ ), array( 'ic-ai-admin', 'wp-data' ), (string) ic_filemtime( $file, true ), true );
		}
	},
	99
);
