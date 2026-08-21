<?php
/**
 * Catalog image size settings.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'admin_init', 'ic_add_wp_screens_settings' );

/**
 * Registers the catalog image settings section on the media screen.
 *
 * @return void
 */
function ic_add_wp_screens_settings() {
	add_settings_section( 'default', __( 'Product Catalog Images', 'post-type-x' ), 'ic_catalog_image_sizes_settings', 'media' );
}

/**
 * Renders the catalog image size settings table.
 *
 * @return void
 */
function ic_catalog_image_sizes_settings() {
	$images = ic_get_catalog_image_sizes();
	/* translators: %s: Regenerate Thumbnails plugin link. */
	implecode_info( sprintf( __( 'Please use the %s plugin to apply the size changes to existing images.', 'post-type-x' ), '<a href="' . esc_url( admin_url( 'plugin-install.php?s=Regenerate+Thumbnails&tab=search&type=term' ) ) . '">Regenerate Thumbnails</a>' ) );
	?>
	<table class="form-table">
		<tbody>
		<tr>
			<th scope="row"><?php esc_html_e( 'Catalog Single Page Image', 'post-type-x' ); ?></th>
			<td>
				<fieldset>
					<legend class="screen-reader-text"><span>Large size</span></legend>
					<label for="product_page_image_w"><?php esc_html_e( 'Max Width', 'post-type-x' ); ?></label>
					<input name="catalog_image_sizes[product_page_image_w]" type="number" step="1" min="0"
							id="product_page_image_w" value="<?php echo esc_attr( $images['product_page_image_w'] ); ?>"
							class="small-text">
					<label for="product_page_image_h"><?php esc_html_e( 'Max Height', 'post-type-x' ); ?></label>
					<input name="catalog_image_sizes[product_page_image_h]" type="number" step="1" min="0"
							id="product_page_image_h" value="<?php echo esc_attr( $images['product_page_image_h'] ); ?>"
							class="small-text">
				</fieldset>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Catalog Category Page Image', 'post-type-x' ); ?></th>
			<td>
				<fieldset>
					<legend class="screen-reader-text"><span>Large size</span></legend>
					<label for="product_category_page_image_w"><?php esc_html_e( 'Max Width', 'post-type-x' ); ?></label>
					<input name="catalog_image_sizes[product_category_page_image_w]" type="number" step="1" min="0"
							id="product_category_page_image_w"
							value="<?php echo esc_attr( $images['product_category_page_image_w'] ); ?>" class="small-text">
					<label for="product_category_page_image_h"><?php esc_html_e( 'Max Height', 'post-type-x' ); ?></label>
					<input name="catalog_image_sizes[product_category_page_image_h]" type="number" step="1" min="0"
							id="product_category_page_image_h"
							value="<?php echo esc_attr( $images['product_category_page_image_h'] ); ?>" class="small-text">
				</fieldset>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Modern Grid Image', 'post-type-x' ); ?></th>
			<td>
				<fieldset>
					<legend class="screen-reader-text"><span>Large size</span></legend>
					<label for="modern_grid_image_w"><?php esc_html_e( 'Max Width', 'post-type-x' ); ?></label>
					<input name="catalog_image_sizes[modern_grid_image_w]" type="number" step="1" min="0"
							id="product_page_image_w" value="<?php echo esc_attr( $images['modern_grid_image_w'] ); ?>"
							class="small-text">
					<label for="modern_grid_image_h"><?php esc_html_e( 'Max Height', 'post-type-x' ); ?></label>
					<input name="catalog_image_sizes[modern_grid_image_h]" type="number" step="1" min="0"
							id="product_page_image_h" value="<?php echo esc_attr( $images['modern_grid_image_h'] ); ?>"
							class="small-text"><br>
				</fieldset>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Classic Grid Image', 'post-type-x' ); ?></th>
			<td>
				<fieldset>
					<legend class="screen-reader-text">
						<span><?php esc_html_e( 'Classic Grid Image', 'post-type-x' ); ?></span></legend>
					<label for="classic_grid_image_w"><?php esc_html_e( 'Max Width', 'post-type-x' ); ?></label>
					<input name="catalog_image_sizes[classic_grid_image_w]" type="number" step="1" min="0"
							id="product_page_image_w" value="<?php echo esc_attr( $images['classic_grid_image_w'] ); ?>"
							class="small-text">
					<label for="classic_grid_image_h"><?php esc_html_e( 'Max Height', 'post-type-x' ); ?></label>
					<input name="catalog_image_sizes[classic_grid_image_h]" type="number" step="1" min="0"
							id="product_page_image_h" value="<?php echo esc_attr( $images['classic_grid_image_h'] ); ?>"
							class="small-text">
				</fieldset>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Classic List Image', 'post-type-x' ); ?></th>
			<td>
				<fieldset>
					<legend class="screen-reader-text">
						<span><?php esc_html_e( 'Classic List Image', 'post-type-x' ); ?></span></legend>
					<label for="classic_list_image_w"><?php esc_html_e( 'Max Width', 'post-type-x' ); ?></label>
					<input name="catalog_image_sizes[classic_list_image_w]" type="number" step="1" min="0"
							id="product_page_image_w" value="<?php echo esc_attr( $images['classic_list_image_w'] ); ?>"
							class="small-text">
					<label for="classic_list_image_h"><?php esc_html_e( 'Max Height', 'post-type-x' ); ?></label>
					<input name="catalog_image_sizes[classic_list_image_h]" type="number" step="1" min="0"
							id="product_page_image_h" value="<?php echo esc_attr( $images['classic_list_image_h'] ); ?>"
							class="small-text">
				</fieldset>
			</td>
		</tr>
		<?php do_action( 'catalog_image_sizes_settings', $images ); ?>
		</tbody>
	</table>
	<?php
}

/**
 * Returns the default catalog image sizes.
 *
 * @return array
 */
function ic_get_default_catalog_image_sizes() {
	$image_sizes['product_page_image_w']          = 600;
	$image_sizes['product_page_image_h']          = 600;
	$image_sizes['product_category_page_image_w'] = 600;
	$image_sizes['product_category_page_image_h'] = 600;
	$image_sizes['classic_grid_image_w']          = 600;
	$image_sizes['classic_grid_image_h']          = 600;
	$image_sizes['classic_list_image_w']          = 280;
	$image_sizes['classic_list_image_h']          = 160;
	$image_sizes['modern_grid_image_w']           = 600;
	$image_sizes['modern_grid_image_h']           = 384;

	return apply_filters( 'default_catalog_image_sizes', $image_sizes );
}

/**
 * Returns catalog image sizes array
 *
 * @return type
 */
function ic_get_catalog_image_sizes() {
	$image_sizes = ic_get_global( 'catalog_image_sizes' );
	if ( ! $image_sizes ) {
		$default     = ic_get_default_catalog_image_sizes();
		$image_sizes = wp_parse_args( get_option( 'catalog_image_sizes', $default ), $default );
		ic_save_global( 'catalog_image_sizes', $image_sizes );
	}

	return $image_sizes;
}

add_action( 'product-settings-list', 'ic_register_image_setting' );

/**
 * Registers catalog image sizes
 */
function ic_register_image_setting() {
	register_setting( 'media', 'catalog_image_sizes', array( 'sanitize_callback' => 'ic_sanitize_catalog_image_sizes' ) );
}

/**
 * Sanitizes image dimensions while preserving extension-added size keys.
 *
 * @param mixed $settings Submitted image settings.
 * @return array
 */
function ic_sanitize_catalog_image_sizes( $settings ) {
	if ( ! is_array( $settings ) ) {
		return array();
	}
	$clean = array();
	foreach ( $settings as $key => $value ) {
		$clean[ $key ] = is_array( $value ) ? ic_sanitize_catalog_image_sizes( $value ) : sanitize_text_field( (string) $value );
		if ( preg_match( '/_(w|h)$/', (string) $key ) ) {
			$clean[ $key ] = max( 0, intval( $value ) );
		}
	}
	return $clean;
}

add_action( 'ic_epc_loaded', 'ic_add_catalog_image_sizes' );

/**
 * Adds image size for classic grid product listing
 */
function ic_add_catalog_image_sizes() {
	$image_sizes = ic_get_catalog_image_sizes();
	add_image_size( 'classic-grid-listing', $image_sizes['classic_grid_image_w'], $image_sizes['classic_grid_image_h'] );
	add_image_size( 'classic-list-listing', $image_sizes['classic_list_image_w'], $image_sizes['classic_list_image_h'] );
	add_image_size( 'modern-grid-listing', $image_sizes['modern_grid_image_w'], $image_sizes['modern_grid_image_h'], apply_filters( 'ic_modern_grid_crop', true ) );
	add_image_size( 'product-page-image', $image_sizes['product_page_image_w'], $image_sizes['product_page_image_h'] );
	add_image_size( 'product-category-page-image', $image_sizes['product_category_page_image_w'], $image_sizes['product_category_page_image_h'] );
	do_action( 'add_catalog_image_sizes', $image_sizes );
}

/**
 * Generates image size settings table tr
 *
 * @param string $label Row label.
 * @param string $name  Settings key.
 *
 * @return void
 */
function ic_image_sizes_settings_tr( $label, $name ) {
	$images = ic_get_catalog_image_sizes();
	?>
	<tr>
	<th scope="row"><?php echo esc_html( $label ); ?></th>
	<td>
		<fieldset>
			<legend class="screen-reader-text"><span><?php echo esc_html( $label ); ?></span></legend>
			<label for="<?php echo esc_attr( $name . '_w' ); ?>"><?php esc_html_e( 'Max Width', 'post-type-x' ); ?></label>
			<input name="catalog_image_sizes[<?php echo esc_attr( $name ); ?>_w]" type="number" step="1" min="0"
					id="<?php echo esc_attr( $name . '_w' ); ?>" value="<?php echo esc_attr( $images[ $name . '_w' ] ); ?>" class="small-text">
			<label for="<?php echo esc_attr( $name . '_h' ); ?>"><?php esc_html_e( 'Max Height', 'post-type-x' ); ?></label>
			<input name="catalog_image_sizes[<?php echo esc_attr( $name ); ?>_h]" type="number" step="1" min="0"
					id="<?php echo esc_attr( $name . '_h' ); ?>" value="<?php echo esc_attr( $images[ $name . '_h' ] ); ?>" class="small-text">
		</fieldset>
		<?php
		if ( isset( $images[ $name . '_crop' ] ) ) {
			?>
			<input name="catalog_image_sizes[<?php echo esc_attr( $name ); ?>_crop]" type="hidden" value="0">
			<input name="catalog_image_sizes[<?php echo esc_attr( $name ); ?>_crop]" type="checkbox"
					id="<?php echo esc_attr( $name ); ?>_crop" value="1" <?php checked( '1', $images[ $name . '_crop' ] ); ?>>
			<label for="<?php echo esc_attr( $name ); ?>_crop"><?php esc_html_e( 'Crop thumbnail to exact dimensions (normally thumbnails are proportional)', 'post-type-x' ); ?></label>
			<?php
		}
		?>
	</td>
	</tr>
	<?php
}
