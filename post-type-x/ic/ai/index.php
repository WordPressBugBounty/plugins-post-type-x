<?php
/**
 * AI framework bootstrap.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

ic_framework_require_once( __DIR__ . '/class-ic-ai-integration.php' );
ic_framework_require_once( __DIR__ . '/class-ic-ai-target.php' );
ic_framework_require_once( __DIR__ . '/class-ic-ai-client.php' );
ic_framework_require_once( __DIR__ . '/class-ic-ai-settings.php' );
ic_framework_require_once( __DIR__ . '/class-ic-ai-admin.php' );
ic_framework_require_once( __DIR__ . '/ext/index.php' );
ic_framework_require_once( __DIR__ . '/class-ic-ai-manager.php' );

if ( ! function_exists( 'ic_ai_manager' ) ) {
	/**
	 * Returns the shared AI manager instance.
	 *
	 * @return IC_AI_Manager
	 */
	function ic_ai_manager() {
		return IC_AI_Manager::instance();
	}
}
if ( ! function_exists( 'ic_ai_enhancement_state' ) ) {
	/**
	 * Returns the enhancement state of one registered-target object.
	 *
	 * Resolves to 'none' (never enhanced, or the target is not registered),
	 * 'current' (enhanced and unchanged since) or 'stale' (enhanced, then edited).
	 *
	 * @param int    $object_id  Post ID or term ID.
	 * @param string $target_key Registered target key.
	 * @return string 'none', 'current' or 'stale'.
	 */
	function ic_ai_enhancement_state( $object_id, $target_key ) {
		$target = ic_ai_manager()->target( (string) $target_key );
		if ( ! $target instanceof IC_AI_Target ) {
			return 'none';
		}

		return ic_ai_manager()->admin()->enhancement_state( absint( $object_id ), $target );
	}
}
if ( ! function_exists( 'ic_ai_stats_target_available' ) ) {
	/**
	 * Check whether a target is available for Stats inventory.
	 *
	 * @param string $post_type Target post type.
	 * @return bool
	 */
	function ic_ai_stats_target_available( $post_type = '' ) {
		return (bool) apply_filters( 'ic_ai_stats_target_available', false, sanitize_key( $post_type ) ); } }
if ( ! function_exists( 'ic_ai_stats_inventory' ) ) {
	/**
	 * Return safe current/enhanced inventory counts for a registered target.
	 *
	 * @param string $target_key Registered target key.
	 * @return array
	 */
	function ic_ai_stats_inventory( $target_key = '' ) {
		$target_key = sanitize_key( $target_key );
		$result     = apply_filters(
			'ic_ai_stats_inventory',
			array(
				'current'  => 0,
				'enhanced' => 0,
				'quality'  => 'unavailable',
			),
			$target_key
		);
		$result     = is_array( $result ) ? $result : array();
		return array(
			'current'  => absint( $result['current'] ?? 0 ),
			'enhanced' => absint( $result['enhanced'] ?? 0 ),
			'quality'  => sanitize_key( $result['quality'] ?? 'unknown' ),
		);
	}
}
if ( ! function_exists( 'ic_ai_stats_scope' ) ) {
	/**
	 * Read or set the request-scoped AI operation scope.
	 *
	 * Bulk list tasks mark the request once; every event recorded afterwards in
	 * that request carries the marker, so single and bulk operations split on one
	 * dimension instead of on two separate hooks.
	 *
	 * @param string|null $scope Optional scope to set: 'single' or 'bulk'.
	 * @return string
	 */
	function ic_ai_stats_scope( $scope = null ) {
		static $current = 'single';
		if ( null !== $scope ) {
			$current = 'bulk' === sanitize_key( (string) $scope ) ? 'bulk' : 'single'; }
		return $current; } }
if ( ! function_exists( 'ic_ai_stats_record_event' ) ) {
	/**
	 * Record an allowlisted AI Stats event without unsafe payload fields.
	 *
	 * @param string $event   Event key.
	 * @param array  $context Safe event context.
	 * @return bool
	 */
	function ic_ai_stats_record_event( $event, $context = array() ) {
		$allowed = array( 'ai_request', 'ai_preview', 'ai_apply', 'ai_failure', 'ai_rate', 'ai_timing' );
		$event   = sanitize_key( $event );
		if ( ! in_array( $event, $allowed, true ) ) {
			return false;
		} $safe = array();
		foreach ( (array) $context as $key => $value ) {
			if ( in_array( sanitize_key( $key ), array( 'prompt', 'response', 'content', 'payload', 'uuid', 'error' ), true ) ) {
				continue;
			} if ( is_scalar( $value ) ) {
				$safe[ sanitize_key( $key ) ] = sanitize_text_field( (string) $value );
			}
		} if ( ! isset( $safe['scope'] ) ) {
			$safe['scope'] = ic_ai_stats_scope();
		} do_action( 'ic_ai_stats_event', $event, $safe );
		return true; } }
