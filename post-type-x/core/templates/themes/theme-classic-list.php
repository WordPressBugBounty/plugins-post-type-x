<?php
/**
 * Manages catalog classic list theme.
 *
 * Here classic list theme is defined and managed.
 *
 * @version 1.2.0
 * @package ecommerce-product-catalog/templates/themes
 * @author  impleCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Shows example classic list in admin.
 */
function example_list_archive_theme() {
	?>
	<div class="archive-listing list example">
		<a href="#list-theme">
			<span class="div-link"></span>
		</a>
		<div class="product-image"
			style="background-image:url('<?php echo esc_url( AL_PLUGIN_BASE_PATH . 'templates/themes/img/example-product.jpg' ); ?>'); background-size: 150px; background-position: center;"></div>
		<div class="product-name">White Lamp</div>
		<div class="product-short-descr"><p>Fusce vestibulum augue ac quam tincidunt ullamcorper. Vestibulum scelerisque
				fermentum congue. Proin convallis dolor ac ipsum congue tincidunt. [...]</p>
		</div>
	</div>
	<?php
}

// Legacy list example implementation is retained in revision history.

/**
 * Returns the classic list element for a given product.
 *
 * @param object $post             Product post object.
 * @param string $archive_template Archive template.
 *
 * @return string
 */
function get_list_archive_theme( $post, $archive_template = null ) {
	$archive_template = isset( $archive_template ) ? $archive_template : get_product_listing_template();
	$return           = '';
	if ( 'list' === $archive_template ) {
		remove_all_filters( 'ic_listing_image_html' );
		add_filter( 'ic_listing_image_html', 'ic_set_classic_list_image_html', 10, 3 );
		ob_start();
		ic_show_template_file( 'product-listing/classic-list.php' );
		$return .= ob_get_clean();
	}

	return $return;
}

/**
 * Returns the classic list element for a given product category.
 *
 * @param object $product_cat      Product category object.
 * @param string $archive_template Archive template.
 *
 * @return string|null
 */
function get_list_category_theme( $product_cat, $archive_template ) {
	if ( 'list' === $archive_template ) {
		$product_cat = ic_set_classic_list_category_image_html( $product_cat );
		ic_save_global( 'ic_current_product_cat', $product_cat );
		ob_start();
		ic_show_template_file( 'product-listing/classic-list-category.php' );
		$return = ob_get_clean();

		return $return;
	}
}

/**
 * Filters the classic list product image HTML.
 *
 * @param string $image_html Existing image HTML.
 * @param int    $product_id Product ID.
 * @param object $product    Product object.
 *
 * @return string
 */
function ic_set_classic_list_image_html( $image_html, $product_id, $product ) {
	$image_id          = $product->image_id();
	$thumbnail_product = wp_get_attachment_image_src( $image_id, 'classic-list-listing' );
	$product_name      = get_product_name();
	if ( $thumbnail_product ) {
		$img_class['alt']   = $product_name;
		$img_class['class'] = 'classic-list-image';
		$image_html         = wp_get_attachment_image( $image_id, 'classic-list-listing', false, $img_class );
	} else {
		$url        = default_product_thumbnail_url();
		$image_html = '<img src="' . $url . '" class="classic-list-image" alt="' . $product_name . '" >';
	}

	return $image_html;
}

/**
 * Saves the classic list category image HTML.
 *
 * @param object $product_cat Product category object.
 *
 * @return object
 */
function ic_set_classic_list_category_image_html( $product_cat ) {
	$image_id = get_product_category_image_id( $product_cat->term_id );
	$url      = wp_get_attachment_url( $image_id, 'classic-list-listing' );
	if ( $url ) {
		$img_class['alt']   = $product_cat->name;
		$img_class['class'] = 'classic-list-image';
		$image              = wp_get_attachment_image( $image_id, 'classic-list-listing', false, $img_class );
	} else {
		$url   = default_product_thumbnail_url();
		$image = '<img src="' . $url . '" class="classic-list-image" alt="' . $product_cat->name . '" >';
	}
	// Listing image HTML is stored through the global helper below.
	ic_save_global( 'ic_category_listing_image_html_' . $product_cat->term_id, $image );

	return $product_cat;
}

/**
 * Returns classic list settings.
 *
 * @return array
 */
function get_classic_list_settings() {
	$settings = wp_parse_args(
		get_option( 'classic_list_settings' ),
		array(
			'attributes'     => 0,
			'attributes_num' => 3,
		)
	);

	return $settings;
}

add_filter( 'ic_listing_template_file_paths', 'ic_add_classic_list_path' );

/**
 * Adds the classic list template path.
 *
 * @param array $paths Template file paths.
 *
 * @return array
 */
function ic_add_classic_list_path( $paths ) {
	$paths['list'] = array(
		'file' => 'product-listing/classic-list.php',
		'base' => '',
	);

	return $paths;
}
