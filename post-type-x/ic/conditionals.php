<?php
/**
 * Shared conditional helpers.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'is_ic_class_initiated' ) ) {
	/**
	 * Checks whether the framework class instance has been initialized.
	 *
	 * @param string $class_name Class name.
	 *
	 * @return bool
	 */
	function is_ic_class_initiated( $class_name ) {
		if ( class_exists( $class_name ) ) {
			global $implecode_ic;
			if ( isset( $implecode_ic[ $class_name ] ) ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'is_ic_admin' ) ) {
	/**
	 * Checks whether the current request is an impleCode admin request.
	 *
	 * @return bool
	 */
	function is_ic_admin() {
		if ( ( is_admin() || wp_doing_cron() ) && ! is_ic_ajax() ) {
			return true;
		}

		return false;
	}
}

if ( ! function_exists( 'is_ic_ajax' ) ) {
	/**
	 * Checks whether the current admin request is an AJAX request.
	 *
	 * @param string|null $action Optional AJAX action to match.
	 *
	 * @return bool
	 */
	function is_ic_ajax( $action = null ) {
		if ( ! is_admin() ) {
			return false;
		}

		$return = false;
		if ( function_exists( 'wp_doing_ajax' ) ) {
			$return = wp_doing_ajax();
		} elseif ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			$return = true;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only request inspection.
		$post_action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';

		if ( true === $return && 'heartbeat' === $post_action ) {
			$return = false;
		}
		if ( true === $return && ! empty( $action ) && $post_action !== $action ) {
			$return = false;
		}

		return $return;
	}
}

if ( ! function_exists( 'ic_is_rendering_block' ) ) {
	/**
	 * Checks whether the current request is rendering a block preview.
	 *
	 * @return bool
	 */
	function ic_is_rendering_block() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request inspection.
		$request_context = isset( $_REQUEST['context'] ) ? sanitize_key( wp_unslash( $_REQUEST['context'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request inspection.
		$has_attributes = isset( $_REQUEST['attributes'] );

		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( 'edit' === $request_context && true === $has_attributes ) ) {
			return true;
		}

		return false;
	}
}

if ( ! function_exists( 'is_ic_front_ajax' ) ) {
	/**
	 * Checks whether the current AJAX request is available on the frontend.
	 *
	 * @return bool
	 */
	function is_ic_front_ajax() {
		if ( is_ic_ajax() ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request inspection.
			$request_action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
			if ( ! empty( $request_action ) && has_action( 'wp_ajax_nopriv_' . $request_action ) ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'ic_is_multibyte' ) ) {
	/**
	 * Checks whether the provided string contains multibyte characters.
	 *
	 * @param string $text Text to inspect.
	 *
	 * @return bool
	 */
	function ic_is_multibyte( $text ) {
		if ( function_exists( 'mb_check_encoding' ) ) {
			return ! mb_check_encoding( $text, 'ASCII' ) && mb_check_encoding( $text, 'UTF-8' );
		}

		return false;
	}
}
