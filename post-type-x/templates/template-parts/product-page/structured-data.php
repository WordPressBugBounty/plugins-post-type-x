<?php
/**
 * Structured data template part.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * The template to display structured data on single product page. You can enable it in the catalog general settings.
 * After you enable it please make sure to verify it on https://search.google.com/structured-data/testing-tool and in Google Search Console
 *
 * Copy it to your theme implecode folder to edit the output: your-theme-folder-name/implecode/structured-data.php
 *
 * @version        1.1.2
 * @package        post-type-x/templates/template-parts/product-page
 * @author        impleCode
 */
$this_product_id = ic_get_product_id();
if ( ! is_ic_product_page() || ! function_exists( 'product_price' ) ) {
	return;
}
$price = product_price( $this_product_id );
if ( empty( $price ) ) {
	return;
}
?>
	<script type="application/ld+json">
	{
	"@context": "https://schema.org/",
	"@type": "Product",
	"@id": <?php echo wp_json_encode( get_permalink() . '#product' ); ?>,
	"name": <?php echo wp_json_encode( get_product_name() ); ?>,
	"image": <?php echo wp_json_encode( get_product_image_url( $this_product_id ) ); ?>,
	"description": <?php echo wp_json_encode( wp_strip_all_tags( get_product_short_description( $this_product_id ) ) ); ?>,
	<?php do_action( 'ic_structured_data', $this_product_id ); ?>
	"offers": {
	"@type": "Offer",
	"url": <?php echo wp_json_encode( get_permalink() ); ?>,
	"priceCurrency": <?php echo wp_json_encode( get_product_currency_code() ); ?>,
	"price": <?php echo wp_json_encode( product_price( $this_product_id ) ); ?>,
	"priceValidUntil": <?php echo wp_json_encode( gmdate( 'Y-m-d', strtotime( '+365 day' ) ) ); ?>,
	"itemCondition": "https://schema.org/NewCondition",
	"availability": "https://schema.org/InStock",
	<?php do_action( 'ic_structured_data_offers', $this_product_id ); ?>
	"seller": {
	"@type": "Organization",
	"name": <?php echo wp_json_encode( get_bloginfo( 'name' ) ); ?>
	<?php do_action( 'ic_structured_data_seller', $this_product_id ); ?>
	}
	}
	}

	</script>
<?php
