<?php
/**
 * WP-CLI commands for the bundled WP Session compatibility layer.
 *
 * @package Ecommerce_Product_Catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * WP-CLI loader for WP session commands.
 *
 * @package ecommerce-product-catalog
 */

if ( ! class_exists( 'WP_CLI_Command' ) ) {
	return;
}

require_once __DIR__ . '/class-wp-session-command.php';

\WP_CLI::add_command( 'session', 'WP_Session_Command' );
