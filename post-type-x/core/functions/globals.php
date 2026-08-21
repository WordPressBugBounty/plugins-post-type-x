<?php
/**
 * Catalog global state helpers.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'ic_get_global' ) ) {

	/**
	 * Returns an impleCode global value.
	 *
	 * @param string|null $name   Global name.
	 * @param bool        $cached Whether to read from the object cache.
	 *
	 * @return array|mixed
	 * @global array $implecode
	 */
	function ic_get_global( $name = null, $cached = false ) {
		global $implecode;
		if ( ! empty( $name ) ) {
			if ( isset( $implecode[ $name ] ) ) {
				return $implecode[ $name ];
			} else {
				if ( $cached ) {
					$cached_value = wp_cache_get( $name, 'implecode' );
					if ( false !== $cached_value ) {
						return $cached_value;
					}
				}
				$fallback = apply_filters( 'ic_get_global', false, $name, $cached );
				if ( false !== $fallback ) {
					if ( $cached ) {
						wp_cache_set( $name, $fallback, 'implecode' );
					}

					return $fallback;
				}

				return false;
			}
		}

		return $implecode;
	}

}

if ( ! function_exists( 'ic_delete_global' ) ) {

	/**
	 * Deletes an impleCode global value.
	 *
	 * @param string|null $name Global name.
	 *
	 * @return void
	 * @global array $implecode
	 */
	function ic_delete_global( $name = null ) {
		global $implecode;
		if ( ! empty( $name ) ) {
			do_action( 'ic_delete_global', $name );
			unset( $implecode[ $name ] );
		} else {
			unset( $implecode );
		}
	}

}

if ( ! function_exists( 'ic_save_global' ) ) {

	/**
	 * Saves an impleCode global value.
	 *
	 * @param string $name                    Global name.
	 * @param mixed  $value                   Global value.
	 * @param bool   $product_listing_globals Whether the name should be reset with listing globals.
	 * @param bool   $cached                  Whether the value should be cached.
	 * @param bool   $admin                   Whether admin execution should be allowed.
	 *
	 * @return bool
	 * @global array $implecode
	 */
	function ic_save_global( $name, $value, $product_listing_globals = false, $cached = false, $admin = false ) {
		if ( ! $admin && is_ic_admin() && ! ic_is_rendering_block() ) {
			return false;
		}
		global $implecode;
		if ( ! empty( $name ) ) {
			if ( $cached ) {
				wp_cache_set( $name, $value, 'implecode' );
			}
			if ( null === $value ) {
				$value = '';
			}
			$implecode[ $name ] = $value;
			do_action( 'ic_save_global', $name, $value, $product_listing_globals, $cached );
			if ( $product_listing_globals ) {
				if ( empty( $implecode['product_listing_globals'] ) ) {
					$implecode['product_listing_globals'] = array();
				}
				if ( ! in_array( $name, $implecode['product_listing_globals'], true ) ) {
					$implecode['product_listing_globals'][] = $name;
				}
			}

			return true;
		}

		return false;
	}

}

if ( ! function_exists( 'ic_reset_listing_globals' ) ) {

	/**
	 * Resets globals stored for the current listing context.
	 *
	 * @return void
	 */
	function ic_reset_listing_globals() {
		global $implecode;
		if ( empty( $implecode['product_listing_globals'] ) ) {
			$implecode['product_listing_globals'] = array();
		}
		foreach ( $implecode['product_listing_globals'] as $global_name ) {
			if ( ! empty( $global_name ) ) {
				ic_delete_global( $global_name );
			}
		}
		$implecode['product_listing_globals'] = array();
	}

}

if ( ! function_exists( 'ic_set_product_id' ) ) {

	/**
	 * Stores the current product ID in global state.
	 *
	 * @param int  $product_id      Product ID.
	 * @param bool $product_listing Whether the value belongs to listing globals.
	 * @param bool $cached          Whether the value should be cached.
	 * @param bool $admin           Whether admin execution should be allowed.
	 *
	 * @return void
	 */
	function ic_set_product_id( $product_id, $product_listing = false, $cached = false, $admin = false ) {
		$initial_product_id = ic_get_global( 'prev_product_id' );
		$prev_product_id    = ic_get_global( 'product_id' );
		if ( empty( $initial_product_id ) && ! empty( $prev_product_id ) ) {
			ic_save_global( 'prev_product_id', intval( $prev_product_id ), $product_listing, $cached, $admin );
		}
		ic_save_global( 'product_id', intval( $product_id ), $product_listing, $cached, $admin );
	}
}

if ( ! function_exists( 'ic_reset_product_id' ) ) {

	/**
	 * Restores the previous product ID from global state.
	 *
	 * @return void
	 */
	function ic_reset_product_id() {
		$prev_product_id = ic_get_global( 'prev_product_id' );
		if ( ! empty( $prev_product_id ) ) {
			ic_save_global( 'product_id', intval( $prev_product_id ) );
		} else {
			ic_delete_global( 'product_id' );
		}
	}
}
