<?php
/**
 * Modern grid category template part.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * The template to display product categories as modern grid
 *
 * Copy it to your theme implecode folder to edit the output: your-theme-folder-name/implecode/modern-grid-category.php
 *
 * @version        1.1.2
 * @package        ecommerce-product-catalog/templates/template-parts/product-listing
 * @author        impleCode
 */
$product_cat = ic_get_global( 'ic_current_product_cat' );
if ( empty( $product_cat ) ) {
	return;
}
?>


	<div class="al_archive category-<?php echo esc_attr( $product_cat->term_id ); ?> modern-grid-element <?php echo esc_attr( design_schemes( 'box', 0 ) ); ?> <?php echo esc_attr( product_category_class( $product_cat->term_id ) ); ?>">
		<a class="pseudo-a" href="<?php echo esc_url( ic_get_category_url( $product_cat->term_id ) ); ?>"></a>
		<div class="pseudo"></div>
		<a href="<?php echo esc_url( ic_get_category_url( $product_cat->term_id ) ); ?>">
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: built by ic_get_category_listing_image_html().
		echo ic_get_category_listing_image_html( $product_cat->term_id );
		?>
			<h3 class="product-name <?php echo esc_attr( design_schemes( 'box', 0 ) ); ?>"><?php echo esc_html( $product_cat->name ); ?></h3>
		</a>
	</div>

<?php
