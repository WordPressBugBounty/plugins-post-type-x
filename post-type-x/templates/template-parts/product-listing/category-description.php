<?php
/**
 * Category description template part.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * The template to display product category page description
 *
 * Copy it to your theme implecode folder to edit the output: your-theme-folder-name/implecode/category-description.php
 *
 * @version     1.1.2
 * @package     ecommerce-product-catalog/templates/template-parts/product-listing
 * @author      impleCode
 */
$term_description = term_description();

if ( ! empty( $term_description ) ) {
	?>

	<div class="taxonomy-description">
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: the return value of the 'ic_product_cat_desc' filter, which extensions and themes legitimately use to inject their own HTML.
	echo apply_filters( 'ic_product_cat_desc', $term_description );
	?>
	</div>


	<?php
}
