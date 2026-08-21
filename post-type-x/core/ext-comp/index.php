<?php
/**
 * Loads external compatibility integrations.
 *
 * @version 1.0.0
 * @package ecommerce-product-catalog/ext-comp
 * @author  impleCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'ic_epc_loaded', 'run_ext_comp_files' );

/**
 * Loads the available compatibility files.
 */
function run_ext_comp_files() {
	require_once AL_BASE_PATH . '/ext-comp/class-ic-catalog-builders-compat.php';

	$ic_catalog_builders_compat = new IC_Catalog_Builders_Compat();
	if ( class_exists( 'Polylang' ) || function_exists( 'icl_object_id' ) ) {
		require_once AL_BASE_PATH . '/ext-comp/class-ic-catalog-multilingual.php';

		global $ic_catalog_multilingual;
		$ic_catalog_multilingual = new IC_Catalog_Multilingual();
	}

	if ( defined( 'WPSEO_VERSION' ) ) {
		require_once AL_BASE_PATH . '/ext-comp/wpseo.php';
	}

	if ( defined( 'QTS_VERSION' ) ) {
		require_once AL_BASE_PATH . '/ext-comp/qtranslate-slug.php';
	}

	if ( class_exists( 'Jetpack' ) ) {
		require_once AL_BASE_PATH . '/ext-comp/jetpack.php';
	}
	if ( did_action( 'elementor/loaded' ) ) {
		add_action( 'elementor/init', 'ic_load_elementor_integration' );
		if ( ! function_exists( 'ic_load_elementor_integration' ) ) {
			/**
			 * Loads the Elementor integration bootstrap.
			 */
			function ic_load_elementor_integration() {
				require_once AL_BASE_PATH . '/ext-comp/elementor/index.php';
			}
		}
	}

	if ( class_exists( 'WooCommerce' ) ) {
		require_once AL_BASE_PATH . '/ext-comp/class-ic-catalog-woocommerce.php';

		new IC_Catalog_Woocommerce();
	}
}

if ( ! function_exists( 'ic_is_xml_sitemap_request' ) ) {
	/**
	 * Detects public XML sitemap requests by their request path.
	 *
	 * Supports provider sitemap files used by common SEO plugins, their sitemap
	 * index, and WordPress core sitemap files. Query strings do not participate
	 * in matching.
	 *
	 * @return bool
	 */
	function ic_is_xml_sitemap_request() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The request URI is parsed as a path and matched against fixed filename patterns only.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path        = wp_parse_url( $request_uri, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			return false;
		}

		$filename = strtolower( wp_basename( $path ) );

		if ( empty($filename) || ! ic_string_contains($filename, '.xml' ) ) {
			return false;
		}

		if ( 'sitemap_index.xml' === $filename ) {
			return true;
		}

		if ( preg_match( '/^wp-sitemap(?:-[a-z0-9_-]+)*\.xml$/i', $filename ) ) {
			return true;
		}

		return 1 === preg_match( '/^[a-z0-9_-]+-sitemap[0-9]*\.xml$/i', $filename );
	}
}

if ( ! function_exists( 'run_ic_session' ) ) {
	add_action( 'ic_epc_included', 'run_ic_session', - 1 );
	add_filter( 'wp_session_expiration_variant', 'ic_wp_session_expiration_variant', 999999 );
	add_filter( 'wp_session_expiration', 'ic_wp_session_expiration', 999999 );

	/**
	 * Boots the session layer used by the catalog.
	 */
	function run_ic_session() {
		if ( ic_is_xml_sitemap_request() ) {
			return;
		}

		if ( is_admin() && ! is_ic_ajax() ) {

			return;
		}
		// Keep PHP sessions disabled here until the newer front AJAX path is adopted.
		if ( ! class_exists( 'IC_Session', false ) && ! ic_use_php_session() ) {
			require_once AL_BASE_PATH . '/includes/class-ic-session.php';
			$ic_session  = new IC_Session();
			$initialized = $ic_session->init();
			if ( $initialized ) {
				if ( ic_ic_cookie_enabled() ) {
					$ic_session->set_customer_session_cookie();
				}
				ic_save_global( 'ic_session', $ic_session );
			} else {
				if ( ! defined( 'WP_SESSION_COOKIE' ) ) {
					define( 'WP_SESSION_COOKIE', '_wp_session' );
				}
				if ( ! class_exists( 'WP_Session' ) ) {
					if ( version_compare( PHP_VERSION, '8.0.0', '<' ) ) {
						require_once AL_BASE_PATH . '/ext-comp/wp_session/class-wp-session-old-php.php';
					} else {
						require_once AL_BASE_PATH . '/ext-comp/wp_session/class-wp-session.php';
					}
				}
				if ( ! function_exists( 'wp_session_cache_expire' ) ) {
					require_once AL_BASE_PATH . '/ext-comp/wp_session/wp-session.php';
				}
			}
		}
		get_product_catalog_session();
	}

	/**
	 * Filters the session expiration variant value.
	 *
	 * @return int
	 */
	function ic_wp_session_expiration_variant() {
		return 30 * 60 * 23;
	}

	/**
	 * Filters the session expiration value.
	 *
	 * @return int
	 */
	function ic_wp_session_expiration() {
		return 30 * 60 * 24;
	}

}
