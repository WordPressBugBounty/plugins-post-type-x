<?php
/**
 * Block helper functions.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'ic_blocks_context' ) ) {

	/**
	 * Gets the current block rendering context.
	 *
	 * @return array<string,int|string>
	 */
	function ic_blocks_context() {
		$context = array(
			'id'   => apply_filters( 'ic_block_context_id', get_the_ID() ),
			'type' => apply_filters( 'ic_block_context_type', get_post_type(), get_the_ID() ),
		);
		if ( empty( $context['id'] ) ) {
			$context['id'] = 0;
		}
		if ( empty( $context['type'] ) ) {
			$context['type'] = '';
		}

		return $context;
	}
}
