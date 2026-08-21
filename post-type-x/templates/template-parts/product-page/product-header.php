<?php
/**
 * Product header template part.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * The template to display product header on product page
 *
 * Copy it to your theme implecode folder to edit the output: your-theme-folder-name/implecode/product-header.php
 *
 * @version        1.1.2
 * @package        ecommerce-product-catalog/templates/template-parts/product-page
 * @author        impleCode
 */
global $post;
$single_names = get_single_names();
ob_start();
do_action( 'single_product_header', $post, $single_names );
$header_content = ob_get_clean();
if ( ! empty( $header_content ) ) {
	?>
	<header class="entry-header product-page-header">
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: HTML assembled earlier in this scope, so escaping it here would print the tags.
		echo $header_content;
		?>
	</header>
	<?php
}
