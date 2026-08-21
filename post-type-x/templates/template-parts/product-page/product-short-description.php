<?php
/**
 * Product short description template part.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * The template to display product short description on product page or with a shortcode
 *
 * Copy it to your theme implecode folder to edit the output: your-theme-folder-name/implecode/product-short-description.php
 *
 * @version        1.1.2
 * @package        ecommerce-product-catalog/templates/template-parts/product-page
 * @author        impleCode
 */
$product_id = ic_get_product_id();
$shortdesc  = apply_filters( 'product_short_description', get_product_short_description( $product_id ) );
if ( ! empty( $shortdesc ) ) {
	?>

	<div class="shortdesc">
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: HTML assembled earlier in this scope, so escaping it here would print the tags.
		echo $shortdesc;
		?>
	</div>
	<?php
}
