<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Plugin Name: Product Catalog Simple
 * Plugin URI: https://implecode.com/wordpress/product-catalog/#cam=in-plugin-urls&key=plugin-url
 * Description: A minimalistic, modular catalog tool which comes with fully customizable, responsive front-end design, search and categories.
 * Version: 1.8.1
 * Author: impleCode
 * Author URI: https://implecode.com/#cam=in-plugin-urls&key=author-url
 * Text Domain: post-type-x
 * Domain Path: /lang/
 *
 * Copyright: 2025 impleCode.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html */
if ( ! ( is_admin() && isset( $_GET['action'] ) && $_GET['action'] == 'activate' && isset( $_GET['plugin'] ) && $_GET['plugin'] == 'post-type-x/post-type-x.php' ) ) {
	add_action( 'plugins_loaded', 'start_post_type_x', - 1 );
}

function start_post_type_x() {
	if ( ! defined( 'AL_BASE_PATH' ) ) {
		if ( ! defined( 'DEF_CATALOG_SINGULAR' ) ) {
			define( 'DEF_CATALOG_SINGULAR', __( 'Item', 'post-type-x' ) );
		}
		if ( ! defined( 'DEF_CATALOG_PLURAL' ) ) {
			define( 'DEF_CATALOG_PLURAL', __( 'Catalog', 'post-type-x' ) );
		}
		if ( ! defined( 'AL_BASE_TEMPLATES_PATH' ) ) {
			define( 'AL_BASE_TEMPLATES_PATH', dirname( __FILE__ ) );
		}
		if ( ! defined( 'IC_CATALOG_PLUGIN_NAME' ) ) {
			define( 'IC_CATALOG_PLUGIN_NAME', 'Product Catalog Simple' );
		}
		if ( ! defined( 'IC_CATALOG_PLUGIN_SLUG' ) ) {
			define( 'IC_CATALOG_PLUGIN_SLUG', 'post-type-x' );
		}
		if ( ! defined( 'AL_PLUGIN_MAIN_FILE' ) ) {
			define( 'AL_PLUGIN_MAIN_FILE', __FILE__ );
		}
		if ( ! defined( 'IC_EPC_VERSION' ) ) {
			define( 'IC_EPC_VERSION', '3.3.11' );
		}
		if ( ! defined( 'IC_CATALOG_VERSION' ) ) {
			if ( function_exists( 'get_file_data' ) ) {
				$default_headers = array(
					'Version' => 'Version',
				);
				$plugin_data     = get_file_data( AL_PLUGIN_MAIN_FILE, $default_headers, 'plugin' );
			}
			if ( ! empty( $plugin_data["Version"] ) ) {
				define( 'IC_CATALOG_VERSION', $plugin_data["Version"] );
			} else {
				define( 'IC_CATALOG_VERSION', '1.6.11' );
			}
		}
		require_once( dirname( __FILE__ ) . '/core/index.php' );
		remove_action( 'plugins_loaded', 'impleCode_EPC', - 2 );
		$ic_epc_instance = impleCode_EPC();
		remove_action( 'init', array( $ic_epc_instance, 'load_textdomain' ) );
		add_action( 'admin_init', 'post_type_x_upgrade' );
		//add_filter( 'ic_extensions_remote_url', 'ic_post_type_x_extensions' );
		//add_action( 'ic_before_extensions_list', 'type_x_free_extensions' );
		add_filter( 'ic_cat_extensions', 'type_x_free_extensions' );
		add_action( 'after_setup_theme', 'ic_post_type_x_addons', - 2 );
	}
}

function ic_post_type_x_addons() {
	remove_action( 'admin_init', 'ecommerce_product_catalog_upgrade' );
	if ( ! is_network_admin() ) {
		do_action( 'post_type_x_addons' );
	}
}

/**
 * Generates extension param for Post Type X
 *
 */
function ic_post_type_x_extensions( $param ) {
	$param .= '&extensions_ptx=1';

	return $param;
}

/**
 * Shows Post Type X free extensions
 *
 */
function type_x_free_extensions( $existing_extensions = null ) {
	if ( false === ( $extensions = get_site_transient( 'implecode_free_extensions_data' ) ) ) {
		$extensions = wp_remote_get( 'https://app.implecode.com/index.php?provide_extensions&free=1' );
		if ( ! is_wp_error( $extensions ) && 200 == wp_remote_retrieve_response_code( $extensions ) ) {
			$extensions = json_decode( wp_remote_retrieve_body( $extensions ), true );
			if ( $extensions ) {
				set_site_transient( 'implecode_free_extensions_data', $extensions, 60 * 60 * 24 * 7 );
			}
		} else {
			$extensions = implecode_x_free_extensions();
		}
	}

	/*
	  $all_ic_plugins = '';
	  if ( function_exists( 'get_free_implecode_active_plugins' ) ) {
	  $all_ic_plugins = get_free_implecode_active_plugins();
	  }
	  $not_active_ic_plugins = get_implecode_free_not_active_plugins();
	  echo '<div class="free-extensions">';

	  foreach ( $extensions as $extension ) {
	  $extension[ 'type' ] = isset( $extension[ 'type' ] ) ? $extension[ 'type' ] : 'premium';
	  //echo extension_box( $extension[ 'name' ], $extension[ 'url' ], $extension[ 'desc' ], $extension[ 'comp' ], $extension[ 'slug' ], $all_ic_plugins, $not_active_ic_plugins, $extension[ 'type' ] );
	  }
	  echo '</div>';
	 *
	 */

	return array_merge( $extensions, $existing_extensions );
}

function implecode_x_free_extensions() {
	$extensions = array(
		'price-field'      => array(
			'url'  => 'price-field',
			'name' => 'Price Field',
			'desc' => 'Adds price support for Product Catalog Simple items. Use it for all your priced products and services.',
			'comp' => 'simple',
			'slug' => 'price-field',
			'type' => 'free'
		),
		'attributes-table' => array(
			'url'  => 'attributes-table',
			'name' => 'Attributes Table',
			'desc' => 'Adds attributes support for Product Catalog Simple items. Attributes will let you display some additional data about the item in a convienient way.',
			'comp' => 'simple',
			'slug' => 'attributes-table',
			'type' => 'free'
		),
		'shipping-options' => array(
			'url'  => 'shipping-options',
			'name' => 'Shipping Options',
			'desc' => 'Add shipping support for your Product Catalog Simple items. Use it for your physical products.',
			'comp' => 'simple',
			'slug' => 'shipping-options',
			'type' => 'free'
		),
	);

	return $extensions;
}

/**
 * Applies Post Type X upgrade functions
 *
 */
function post_type_x_upgrade() {
	if ( is_admin() ) {
		$plugin_data             = get_plugin_data( AL_PLUGIN_MAIN_FILE );
		$plugin_version          = $plugin_data["Version"];
		$database_plugin_version = get_option( 'post_type_x_ver', $plugin_version );
		add_filter( 'ic_plugin_database_version', 'set_post_type_x_system_db_ver' );
		if ( $database_plugin_version != $plugin_version ) {
			update_option( 'post_type_x_ver', $plugin_version );
			$first_version               = (string) get_option( 'first_activation_version', $plugin_version );
			$epc_database_plugin_version = get_option( 'ecommerce_product_catalog_ver', $plugin_version );
			if ( version_compare( $first_version, '1.1.0' ) < 0 && version_compare( $database_plugin_version, '1.1.0' ) < 0 && version_compare( $epc_database_plugin_version, '2.5.0' ) < 0 ) {
				save_default_multiple_settings();

				if ( false !== get_transient( 'implecode_hide_plugin_review_info' ) ) {
					set_site_transient( 'implecode_hide_plugin_review_info', 1 );
				}
				if ( false !== get_transient( 'implecode_hide_plugin_translation_info' ) ) {
					set_site_transient( 'implecode_hide_plugin_translation_info', 1 );
				}

				$single_options             = get_product_page_settings();
				$single_options['template'] = 'plain';
				update_option( 'multi_single_options', $single_options );
			} else if ( version_compare( $database_plugin_version, '1.7.7' ) < 0 ) {
				$csv_temp   = wp_upload_dir( null, false );
				$csv_folder = $csv_temp['basedir'];
				if ( file_exists( $csv_folder . '/simple-products.csv' ) ) {
					unlink( $csv_folder . '/simple-products.csv' );
				}
				if ( file_exists( $csv_folder . '/products_product.csv' ) ) {
					unlink( $csv_folder . '/products_product.csv' );
				}
				$product_catalogs = get_post_types( array( 'capability_type' => 'product' ) );
				foreach ( $product_catalogs as $product_catalog ) {
					if ( file_exists( $csv_folder . '/products_' . $product_catalog . '.csv' ) ) {
						unlink( $csv_folder . '/products_' . $product_catalog . '.csv' );
					}
				}
			}
			//flush_rewrite_rules();
		} else if ( ! get_option( 'post_type_x_ver' ) ) {
			update_option( 'post_type_x_ver', $plugin_version, false );
		}
	}
}

/**
 * Returns Post Type X db version
 *
 * @return type
 */
function set_post_type_x_system_db_ver() {
	$plugin_data    = get_plugin_data( AL_PLUGIN_MAIN_FILE );
	$plugin_version = $plugin_data["Version"];

	return get_option( 'post_type_x_ver', $plugin_version );
}

register_activation_hook( __FILE__, 'IC_EPC_install' );

if ( ! function_exists( 'IC_EPC_install' ) ) {

	function IC_EPC_install() {
		start_post_type_x();
		eCommerce_Product_Catalog::instance();
		update_option( 'IC_EPC_install', 1 );
		if ( function_exists( 'epc_activation_function' ) ) {
			epc_activation_function();
		}
	}

}