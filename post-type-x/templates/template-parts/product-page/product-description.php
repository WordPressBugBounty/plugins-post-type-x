<?php
/**
 * Product description template part.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * The template to display product description on product page or with a shortcode
 *
 * Copy it to your theme implecode folder to edit the output: your-theme-folder-name/implecode/product-description.php
 *
 * @version        1.1.2
 * @package        ecommerce-product-catalog/templates/template-parts/product-page
 * @author        impleCode
 */
$single_names        = get_single_names();
$product_id          = ic_get_product_id();
$product_description = get_product_description( $product_id );
if ( ! empty( $product_description ) ) {
	?>
	<div id="product_description" class="product-description">
		<?php if ( ! empty( $single_names['product_description'] ) ) { ?>
			<h3 class="catalog-header"><?php echo esc_html( $single_names['product_description'] ); ?></h3>
			<?php
		}
		if ( 'simple' === get_integration_type() && ! is_ic_shortcode_integration() ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: the return value of the 'product_simple_description' filter, which extensions and themes legitimately use to inject their own HTML.
			echo apply_filters( 'product_simple_description', $product_description );
		} else {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: the return value of the 'the_content' filter, which extensions and themes legitimately use to inject their own HTML.
			echo apply_filters( 'the_content', $product_description );
		}
		?>
	</div>
	<?php
}
