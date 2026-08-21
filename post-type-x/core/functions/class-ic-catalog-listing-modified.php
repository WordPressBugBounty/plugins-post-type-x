<?php
/**
 * Listing rewrite maintenance class.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Defines URL rewrite maintenance hooks for the listing page.
 */
class IC_Catalog_Listing_Modified {

	/**
	 * Registers hooks for listing rewrite maintenance.
	 */
	public function __construct() {
		add_action( 'post_updated', array( $this, 'rewrite_listing' ), 10, 2 );
		add_action( 'delete_post', array( $this, 'remove_listing' ) );
		add_action( 'trashed_post', array( $this, 'remove_listing' ) );
	}

	/**
	 * Enables permalink rewrite when editing the product listing page.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Updated post object.
	 *
	 * @return void
	 */
	public static function rewrite_listing( $post_id, $post = null ) {
		if ( ( isset( $post->post_type ) && 'page' === $post->post_type ) || ! isset( $post->post_type ) ) {
			$id = get_product_listing_id();
			if ( $post_id === $id ) {
				permalink_options_update();
			}
		}
	}

	/**
	 * Removes the stored listing page when the page is deleted.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public static function remove_listing( $post_id ) {
		$id = get_product_listing_id();
		if ( $post_id === $id ) {
			delete_option( 'product_archive_page_id' );
			delete_option( 'product_archive' );
			permalink_options_update();
		}
	}
}
