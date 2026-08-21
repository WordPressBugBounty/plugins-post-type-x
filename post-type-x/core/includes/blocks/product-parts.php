<?php
/**
 * Product-part block rendering helpers.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_filter( 'ic_block_content', 'ic_blocks_product_parts', 10, 4 );

/**
 * Filters block content for product-part blocks.
 *
 * @param string $block_content Existing block content.
 * @param int    $product_id    Product ID.
 * @param string $block_name    Registered block name suffix.
 * @param array  $attr          Block attributes.
 *
 * @return string
 */
function ic_blocks_product_parts( $block_content, $product_id, $block_name, $attr ) {
	if ( 'image-gallery' === $block_name ) {
		if ( isset( $attr['metaField'] ) ) {
			$new_thumbnail_id = $attr['metaField'];
			add_filter(
				'post_thumbnail_id',
				function ( $thumbnail_id, $post ) use ( $new_thumbnail_id, $product_id ) {
					if ( $post->ID === $product_id ) {
						return $new_thumbnail_id;
					}

					return $thumbnail_id;
				},
				10,
				2
			);
		}
		$block_content = get_product_gallery( $product_id );
	} elseif ( 'name' === $block_name ) {
		$block_content = get_product_name( $product_id );
	} elseif ( 'regular-price' === $block_name ) {
		$block_content = price_format( product_price( $product_id, true ) );
	} elseif ( 'short-description' === $block_name ) {
		ob_start();
		show_short_desc( $product_id );
		$block_content = ob_get_clean();
	}

	return $block_content;
}
