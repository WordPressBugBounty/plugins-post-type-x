<?php
/**
 * Custom design settings configuration.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'ic_settings_page_content', 'ic_epc_render_design_settings_page_content', 10, 3 );

/**
 * Returns the listing design page settings.
 *
 * @return array
 */
function ic_epc_listing_design_page_settings() {
	return array(
		'archive_template'      => get_product_listing_template(),
		'modern_grid_settings'  => get_modern_grid_settings(),
		'classic_grid_settings' => get_classic_grid_settings(),
		'classic_list_settings' => get_classic_list_settings(),
		'item_name'             => ic_catalog_item_name(),
	);
}

/**
 * Returns the single page design settings.
 *
 * @return array
 */
function ic_epc_single_design_page_settings() {
	return array(
		'enable_catalog_lightbox'  => get_option( 'catalog_lightbox', 1 ),
		'enable_catalog_magnifier' => get_option( 'catalog_magnifier', 1 ),
		'single_options'           => get_product_page_settings(),
	);
}

/**
 * Returns the design schemes page settings.
 *
 * @return array
 */
function ic_epc_design_schemes_page_settings() {
	return array(
		'design_schemes' => ic_get_design_schemes(),
	);
}

/**
 * Returns a sanitized custom design query argument.
 *
 * @param string $key Query arg key.
 * @return string
 */
function ic_get_custom_design_query_arg( $key ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading current admin screen state.
	if ( ! isset( $_GET[ $key ] ) ) {
		return '';
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading current admin screen state.
	return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
}

/**
 * Returns the listing design shared settings page.
 *
 * @return IC_Settings_Page
 */
function ic_epc_shared_archive_design_settings_page() {
	static $page = null;
	if ( null === $page ) {
		$page_settings = ic_epc_listing_design_page_settings();

		$page = new IC_Settings_Page(
			array(
				'title'              => __( 'Design Settings', 'post-type-x' ),
				'option_group'       => 'product_design',
				'option_name'        => 'archive_template',
				'registered_options' => array(
					'archive_template',
					'modern_grid_settings',
					'classic_grid_settings',
					'classic_list_settings',
				),
				'submenu'            => 'archive-design',
				'screen'             => 'product-settings.php',
				'screen_tab'         => 'design-settings',
				'screen_tab_label'   => __( 'Catalog Design', 'post-type-x' ),
				'screen_tab_menu_item_id' => 'design-settings',
				'screen_tab_query_args' => array(
					'submenu' => 'archive-design',
				),
				'screen_tab_content_wrapper_class' => 'design-product-settings',
				'screen_submenu_label' => __( 'Listing Design', 'post-type-x' ),
				'submenu_item_id'    => 'archive-design',
				'container_class'    => 'setting-content submenu',
				'settings'           => $page_settings,
				'content_settings'   => array(),
				'sections'           => array(
					array(
						'title'       => __( 'Listing Design', 'post-type-x' ),
						'table_class' => 'IC_Settings_Design_Table',
						'table_args'  => array(
							'settings' => ic_epc_listing_design_table_settings( $page_settings ),
						),
					),
				),
				'helpers'            => array(
					'ic_epc_main_helper',
				),
				'submit_label'       => __( 'Save changes', 'post-type-x' ),
			)
		);
	}

	return $page;
}

/**
 * Returns the single page design shared settings page.
 *
 * @return IC_Settings_Page
 */
function ic_epc_shared_single_design_settings_page() {
	static $page = null;
	if ( null === $page ) {
		$page_settings = ic_epc_single_design_page_settings();

		$page = new IC_Settings_Page(
			array(
				'title'              => __( 'Design Settings', 'post-type-x' ),
				'option_group'       => 'single_design',
				'option_name'        => 'multi_single_options',
				'registered_options' => array(
					'catalog_lightbox',
					'catalog_magnifier',
					'multi_single_options',
					'default_product_thumbnail',
					'ic_default_product_image_id',
				),
				'submenu'            => 'single-design',
				'screen'             => 'product-settings.php',
				'screen_tab'         => 'design-settings',
				'screen_tab_label'   => __( 'Catalog Design', 'post-type-x' ),
				'screen_tab_menu_item_id' => 'design-settings',
				'screen_tab_query_args' => array(
					'submenu' => 'archive-design',
				),
				'screen_tab_content_wrapper_class' => 'design-product-settings',
				'screen_submenu_label' => __( 'Single Page Design', 'post-type-x' ),
				'submenu_item_id'    => 'single-design',
				'container_class'    => 'setting-content submenu',
				'settings'           => $page_settings,
				'content_settings'   => array(),
				'sections'           => ic_epc_single_design_settings_page_sections( $page_settings ),
				'helpers'            => array(
					'ic_epc_main_helper',
					array(
						'callback' => 'ic_epc_doc_helper',
						'args'     => array( __( 'gallery', 'post-type-x' ), 'product-gallery' ),
					),
				),
				'submit_label'       => __( 'Save changes', 'post-type-x' ),
			)
		);
	}

	return $page;
}

/**
 * Returns the design schemes shared settings page.
 *
 * @return IC_Settings_Page
 */
function ic_epc_shared_design_schemes_settings_page() {
	static $page = null;
	if ( null === $page ) {
		$page_settings = ic_epc_design_schemes_page_settings();

		$page = new IC_Settings_Page(
			array(
				'title'              => __( 'Design Settings', 'post-type-x' ),
				'option_group'       => 'design_schemes',
				'option_name'        => 'design_schemes',
				'registered_options' => array(
					'design_schemes',
				),
				'submenu'            => 'design-schemes',
				'screen'             => 'product-settings.php',
				'screen_tab'         => 'design-settings',
				'screen_tab_label'   => __( 'Catalog Design', 'post-type-x' ),
				'screen_tab_menu_item_id' => 'design-settings',
				'screen_tab_query_args' => array(
					'submenu' => 'archive-design',
				),
				'screen_tab_content_wrapper_class' => 'design-product-settings',
				'screen_submenu_label' => __( 'Design Schemes', 'post-type-x' ),
				'submenu_item_id'    => 'design-schemes',
				'container_class'    => 'setting-content submenu',
				'settings'           => $page_settings,
				'content_settings'   => array(),
				'sections'           => ic_epc_design_schemes_settings_page_sections( $page_settings ),
				'helpers'            => array(
					'ic_epc_main_helper',
				),
				'submit_label'       => __( 'Save changes', 'post-type-x' ),
			)
		);
	}

	return $page;
}

/**
 * Returns listing design table settings.
 *
 * @param array $page_settings Listing design page settings.
 *
 * @return array
 */
function ic_epc_listing_design_table_settings( $page_settings ) {
	return array(
		'input_name'            => 'archive_template',
		'selected'              => $page_settings['archive_template'],
		'setting_label'         => __( 'Listing Design', 'post-type-x' ),
		'table_class'           => 'design-table',
		'designs'               => array(
			array(
				'row_id'            => 'default-theme',
				'label'             => __( 'Modern Grid', 'post-type-x' ),
				'value'             => 'default',
				'preview_callback'  => 'ic_epc_render_modern_grid_design_preview',
				'settings'          => ic_epc_modern_grid_design_setting_rows( $page_settings ),
				'settings_callback' => 'ic_epc_render_modern_grid_design_settings',
			),
			array(
				'row_id'            => 'list-theme',
				'label'             => __( 'Classic List', 'post-type-x' ),
				'value'             => 'list',
				'preview_callback'  => 'ic_epc_render_classic_list_design_preview',
				'settings_callback' => 'ic_epc_render_classic_list_design_settings',
			),
			array(
				'row_id'            => 'grid-theme',
				'label'             => __( 'Classic Grid', 'post-type-x' ),
				'value'             => 'grid',
				'preview_callback'  => 'ic_epc_render_classic_grid_design_preview',
				'settings'          => ic_epc_classic_grid_design_setting_rows( $page_settings ),
				'settings_callback' => 'ic_epc_render_classic_grid_design_settings',
			),
		),
		'rows_after_callback'   => 'ic_epc_render_listing_design_theme_rows',
		'after_table_callback'  => 'ic_epc_render_listing_design_table_after',
		'modern_grid_settings'  => $page_settings['modern_grid_settings'],
		'classic_grid_settings' => $page_settings['classic_grid_settings'],
		'classic_list_settings' => $page_settings['classic_list_settings'],
		'item_name'             => $page_settings['item_name'],
	);
}

/**
 * Renders the listing design table from page settings.
 *
 * @param array $page_settings Listing design page settings.
 *
 * @return void
 */
function ic_epc_render_listing_design_table( $page_settings ) {
	$table = new IC_Settings_Design_Table(
		array(
			'settings' => ic_epc_listing_design_table_settings( $page_settings ),
		)
	);
	$table->render();
}

/**
 * Returns Modern Grid generated design setting rows.
 *
 * @param array $page_settings Listing design page settings.
 *
 * @return array
 */
function ic_epc_modern_grid_design_setting_rows( $page_settings ) {
	$modern_grid_settings = $page_settings['modern_grid_settings'];
	$item_name            = $page_settings['item_name'];

	return array(
		array(
			'type'  => 'number',
			'label' => __( 'Per row', 'post-type-x' ),
			'name'  => 'modern_grid_settings[per-row]',
			'value' => $modern_grid_settings['per-row'],
			'unit'  => $item_name,
			'step'  => 1,
			'min'   => 1,
			'max'   => 5,
		),
		array(
			'type'  => 'number',
			'label' => __( 'Per row', 'post-type-x' ),
			'name'  => 'modern_grid_settings[per-row-categories]',
			'value' => $modern_grid_settings['per-row-categories'],
			'unit'  => __( 'categories', 'post-type-x' ),
			'step'  => 1,
			'min'   => 1,
			'max'   => 5,
		),
	);
}

/**
 * Returns Classic Grid generated design setting rows.
 *
 * @param array $page_settings Listing design page settings.
 *
 * @return array
 */
function ic_epc_classic_grid_design_setting_rows( $page_settings ) {
	$classic_grid_settings = $page_settings['classic_grid_settings'];
	$item_name             = $page_settings['item_name'];
	$tip                   = esc_attr__( 'The product listing element width will adjust accordingly to your theme content width.', 'post-type-x' );

	return array(
		array(
			'type'  => 'number',
			'label' => __( 'Per row', 'post-type-x' ),
			'name'  => 'classic_grid_settings[entries]',
			'value' => $classic_grid_settings['entries'],
			'unit'  => $item_name,
			'step'  => 1,
			'min'   => 1,
			'tip'   => $tip,
		),
		array(
			'type'  => 'number',
			'label' => __( 'Per row', 'post-type-x' ),
			'name'  => 'classic_grid_settings[per-row-categories]',
			'value' => $classic_grid_settings['per-row-categories'],
			'unit'  => __( 'categories', 'post-type-x' ),
			'step'  => 1,
			'min'   => 1,
			'tip'   => $tip,
		),
	);
}

/**
 * Renders the modern grid preview.
 *
 * @return void
 */
function ic_epc_render_modern_grid_design_preview() {
	example_default_archive_theme();
}

/**
 * Renders the modern grid additional settings.
 *
 * @param array $design Design row configuration.
 * @param array $settings Design table settings.
 *
 * @return void
 */
function ic_epc_render_modern_grid_design_settings( $design, $settings ) {
	$modern_grid_settings = $settings['modern_grid_settings'];
	do_action( 'modern_grid_additional_settings', $modern_grid_settings, 'modern_grid' );
}

/**
 * Renders the classic list preview.
 *
 * @return void
 */
function ic_epc_render_classic_list_design_preview() {
	example_list_archive_theme();
}

/**
 * Renders the classic list additional settings.
 *
 * @param array $design Design row configuration.
 * @param array $settings Design table settings.
 *
 * @return void
 */
function ic_epc_render_classic_list_design_settings( $design, $settings ) {
	do_action( 'classic_list_additional_settings', $settings['classic_list_settings'], 'classic_list' );
}

/**
 * Renders the classic grid preview.
 *
 * @return void
 */
function ic_epc_render_classic_grid_design_preview() {
	example_grid_archive_theme();
}

/**
 * Renders the classic grid additional settings.
 *
 * @param array $design Design row configuration.
 * @param array $settings Design table settings.
 *
 * @return void
 */
function ic_epc_render_classic_grid_design_settings( $design, $settings ) {
	$classic_grid_settings = $settings['classic_grid_settings'];
	do_action( 'classic_grid_additional_settings', $classic_grid_settings, 'classic_grid' );
}

/**
 * Renders additional listing theme rows inside the design table body.
 *
 * @param array $settings Design table settings.
 *
 * @return void
 */
function ic_epc_render_listing_design_theme_rows( $settings ) {
	do_action( 'product_listing_theme_settings', $settings['selected'] );
}

/**
 * Renders additional listing design content after the design table.
 *
 * @param array $settings Design table settings.
 *
 * @return void
 */
function ic_epc_render_listing_design_table_after( $settings ) {
	do_action( 'ic_product_listing_settings', $settings['selected'] );
}

/**
 * Renders the shared listing design settings body.
 *
 * @param string $archive_template Current listing template.
 * @param array  $modern_grid_settings Modern grid settings.
 * @param array  $classic_grid_settings Classic grid settings.
 * @param array  $classic_list_settings Classic list settings.
 * @param string $item_name Catalog item label.
 *
 * @return void
 */
function ic_epc_shared_render_listing_design_settings_body( $archive_template, $modern_grid_settings, $classic_grid_settings, $classic_list_settings, $item_name ) {
	ic_epc_render_listing_design_table(
		array(
			'archive_template'      => $archive_template,
			'modern_grid_settings'  => $modern_grid_settings,
			'classic_grid_settings' => $classic_grid_settings,
			'classic_list_settings' => $classic_list_settings,
			'item_name'             => $item_name,
		)
	);
}

/**
 * Renders design settings content placed before sections.
 *
 * @param string $option_group Option group.
 * @param array  $settings Page settings.
 *
 * @return void
 */
function ic_epc_render_design_settings_page_content( $option_group, $settings ) {
	if ( 'product_design' === $option_group ) {
		do_action(
			'listing_design_settings_start',
			$settings['archive_template'],
			$settings['modern_grid_settings'],
			$settings['classic_grid_settings'],
			$settings['classic_list_settings'],
			$settings['item_name']
		);
	} elseif ( 'single_design' === $option_group ) {
		do_action( 'page_design_settings_start', $settings['single_options'], $settings['enable_catalog_lightbox'] );
	} elseif ( 'design_schemes' === $option_group ) {
		do_action( 'ic_catalog_design_schemes_top', $settings['design_schemes'] );
	}
}

/**
 * Outputs listing design settings.
 *
 * @return void
 */
function ic_listing_design_settings() {
	$archive_template      = get_product_listing_template();
	$modern_grid_settings  = get_modern_grid_settings();
	$classic_grid_settings = get_classic_grid_settings();
	$classic_list_settings = get_classic_list_settings();
	$item_name             = ic_catalog_item_name();
	ic_register_setting( __( 'Listing Design', 'post-type-x' ), 'archive_template' );
	do_action( 'listing_design_settings_start', $archive_template, $modern_grid_settings, $classic_grid_settings, $classic_list_settings, $item_name );
	?>
	<h3><?php esc_html_e( 'Listing Design', 'post-type-x' ); ?></h3>
	<?php
	ic_epc_shared_render_listing_design_settings_body( $archive_template, $modern_grid_settings, $classic_grid_settings, $classic_list_settings, $item_name );
}

/**
 * Outputs single product page design settings.
 *
 * @return void
 */
function ic_product_page_design_settings() {
	$page = ic_epc_shared_single_design_settings_page();
	?>
	<h2><?php esc_html_e( 'Design Settings', 'post-type-x' ); ?></h2>
	<?php
	$page->render_body();
}

/**
 * Returns the single design page sections.
 *
 * @param array $page_settings Single design page settings.
 *
 * @return array
 */
function ic_epc_single_design_settings_page_sections( $page_settings ) {
	return array(
		array(
			'title'       => __( 'Default Product Image', 'post-type-x' ),
			'table_class' => 'IC_Settings_Standard_Table',
			'table_args'  => array(
				'settings' => ic_epc_default_product_image_table_settings(),
			),
		),
		array(
			'title'       => __( 'Product Gallery', 'post-type-x' ),
			'table_class' => 'IC_Settings_Standard_Table',
			'table_args'  => array(
				'settings' => ic_epc_product_gallery_table_settings( $page_settings ),
			),
		),
		array(
			'title'       => __( 'Single Page Template', 'post-type-x' ),
			'table_class' => 'IC_Settings_Standard_Table',
			'table_args'  => array(
				'settings' => ic_epc_single_page_template_table_settings( $page_settings ),
			),
		),
	);
}

/**
 * Returns the design schemes page sections.
 *
 * @param array $page_settings Design schemes page settings.
 *
 * @return array
 */
function ic_epc_design_schemes_settings_page_sections( $page_settings ) {
	return array(
		array(
			'title'       => __( 'Design Schemes', 'post-type-x' ),
			'table_class' => 'IC_Settings_Standard_Table',
			'table_args'  => array(
				'settings' => ic_epc_design_schemes_table_settings( $page_settings ),
			),
		),
	);
}

/**
 * Returns default product image table settings.
 *
 * @return array
 */
function ic_epc_default_product_image_table_settings() {
	return array(
		'rows' => array(
			array(
				'type'            => 'upload_image',
				'label'           => __( 'Default Image', 'post-type-x' ),
				'button_label'    => __( 'Default Image', 'post-type-x' ),
				'name'            => 'ic_default_product_image_id',
				'value'           => ic_default_product_image_id(),
				'default_image'   => default_product_thumbnail_url( true, false ),
				'upload_image_id' => 'id',
			),
		),
	);
}

/**
 * Returns design schemes table settings.
 *
 * @param array $page_settings Design schemes page settings.
 *
 * @return array
 */
function ic_epc_design_schemes_table_settings( $page_settings ) {
	$design_schemes = isset( $page_settings['design_schemes'] ) ? $page_settings['design_schemes'] : array();
	$rows           = apply_filters( 'ic_epc_design_schemes_table_rows', array(), $design_schemes, $page_settings );
	$legacy_rows    = ic_epc_get_legacy_design_schemes_table_rows( $design_schemes );

	if ( '' !== $legacy_rows ) {
		$rows[] = array(
			'type' => 'html',
			'html' => $legacy_rows,
		);
	}

	$rows[] = ic_epc_box_color_scheme_table_row( $design_schemes );
	ic_register_setting( __( 'Boxes Color', 'post-type-x' ), 'design_schemes[box-color]' );

	return array(
		'table_class'          => 'wp-list-table widefat product-settings-table',
		'headings'             => array(
			__( 'Setting', 'post-type-x' ),
			__( 'Value', 'post-type-x' ),
			__( 'Example Effect', 'post-type-x' ),
			__( 'Impact', 'post-type-x' ),
		),
		'notices'              => array(
			array(
				'type'        => 'info',
				'dismissible' => false,
				'message'     => '<p>' . esc_html__( 'Changing design schemes has almost always impact on various elements. For example, changing price color has an impact on the single product page and archive page price color.', 'post-type-x' ) . '</p><p>' . esc_html__( "You can figure it out by checking the 'impact' column.", 'post-type-x' ) . '</p>',
			),
		),
		'after_table_callback' => 'ic_epc_render_design_schemes_table_after',
		'rows'                 => $rows,
	);
}

/**
 * Returns product gallery table settings.
 *
 * @param array $page_settings Single design page settings.
 *
 * @return array
 */
function ic_epc_product_gallery_table_settings( $page_settings ) {
	$single_options = $page_settings['single_options'];

	return array(
		'single_options'       => $single_options,
		'rows'                 => array(
			array(
				'type'  => 'checkbox',
				'label' => __( 'Enable image', 'post-type-x' ),
				'name'  => 'multi_single_options[enable_product_gallery]',
				'value' => $single_options['enable_product_gallery'],
				'tip'   => __( 'The image will be used only on the listing when unchecked.', 'post-type-x' ),
			),
			array(
				'type'  => 'checkbox',
				'label' => __( 'Enable lightbox gallery', 'post-type-x' ),
				'name'  => 'catalog_lightbox',
				'value' => $page_settings['enable_catalog_lightbox'],
				'tip'   => __( 'The image on a single page will not be linked when unchecked.', 'post-type-x' ),
			),
			array(
				'type'  => 'checkbox',
				'label' => __( 'Enable image magnifier', 'post-type-x' ),
				'name'  => 'catalog_magnifier',
				'value' => $page_settings['enable_catalog_magnifier'],
				'tip'   => __( 'The image on a single page will be magnified when pointed with a mouse cursor.', 'post-type-x' ),
			),
			array(
				'type'  => 'checkbox',
				'label' => __( 'Enable image only when inserted', 'post-type-x' ),
				'name'  => 'multi_single_options[enable_product_gallery_only_when_exist]',
				'value' => $single_options['enable_product_gallery_only_when_exist'],
				'tip'   => __( 'The default image will be used on the listing only when unchecked.', 'post-type-x' ),
			),
		),
		'after_table_callback' => 'ic_epc_render_product_gallery_table_after',
	);
}

/**
 * Returns single page template table settings.
 *
 * @param array $page_settings Single design page settings.
 *
 * @return array
 */
function ic_epc_single_page_template_table_settings( $page_settings ) {
	$template_options = apply_filters(
		'ic_catalog_single_template_available_options',
		array(
			'boxed' => __( 'Formatted', 'post-type-x' ),
			'plain' => __( 'Plain', 'post-type-x' ),
		)
	);

	return array(
		'rows'                 => array(
			array(
				'type'    => 'radio',
				'label'   => __( 'Select template', 'post-type-x' ),
				'name'    => 'multi_single_options[template]',
				'value'   => $page_settings['single_options']['template'],
				'options' => $template_options,
			),
		),
		'after_table_callback' => 'ic_epc_render_single_page_template_table_after',
	);
}

/**
 * Renders additional gallery settings after the standard table.
 *
 * @param array $settings Standard table settings.
 *
 * @return void
 */
function ic_epc_render_product_gallery_table_after( $settings ) {
	do_action( 'ic_product_gallery_settings', $settings['single_options'] );
}

/**
 * Renders additional single template settings after the standard table.
 *
 * @return void
 */
function ic_epc_render_single_page_template_table_after() {
	do_action( 'single_product_design' );
}

/**
 * Returns legacy action-generated design schemes rows HTML.
 *
 * @param array $design_schemes Current design schemes.
 *
 * @return string
 */
function ic_epc_get_legacy_design_schemes_table_rows( $design_schemes ) {
	ob_start();
	do_action( 'inside_color_schemes_settings_table', $design_schemes );

	return trim( ob_get_clean() );
}

/**
 * Returns the built-in box color scheme row.
 *
 * @param array $design_schemes Current design schemes.
 *
 * @return array
 */
function ic_epc_box_color_scheme_table_row( $design_schemes ) {
	$box_color = isset( $design_schemes['box-color'] ) ? $design_schemes['box-color'] : '';

	return array(
		'type'  => 'cells',
		'cells' => array(
			array(
				'content' => esc_html__( 'Boxes Color', 'post-type-x' ),
			),
			array(
				'content' => ic_epc_design_schemes_box_color_select_html( $box_color ),
			),
			array(
				'content' => ic_epc_design_schemes_box_example_html(),
			),
			array(
				'content' => esc_html__( 'product archive title', 'post-type-x' ) . ', ' . esc_html__( 'archive pagination', 'post-type-x' ),
			),
		),
	);
}

/**
 * Returns the box color select HTML.
 *
 * @param string $box_color Selected box color.
 *
 * @return string
 */
function ic_epc_design_schemes_box_color_select_html( $box_color ) {
	return implecode_settings_dropdown(
		'',
		'design_schemes[box-color]',
		$box_color,
		array(
			'red-box'    => __( 'Red', 'post-type-x' ),
			'orange-box' => __( 'Orange', 'post-type-x' ),
			'green-box'  => __( 'Green', 'post-type-x' ),
			'blue-box'   => __( 'Blue', 'post-type-x' ),
			'grey-box'   => __( 'Grey', 'post-type-x' ),
		),
		0,
		'id="box_schemes"'
	);
}

/**
 * Returns the box example HTML.
 *
 * @return string
 */
function ic_epc_design_schemes_box_example_html() {
	ob_start();
	?>
	<div class="product-name example <?php design_schemes( 'box' ); ?>">Exclusive Red Lamp</div>
	<?php

	return trim( ob_get_clean() );
}

/**
 * Renders additional design scheme settings after the table.
 *
 * @return void
 */
function ic_epc_render_design_schemes_table_after() {
	do_action( 'color_schemes_settings' );
}

if ( ! function_exists( 'ic_get_design_schemes' ) ) {

	/**
	 * Returns available design schemes.
	 *
	 * @return array
	 */
	function ic_get_design_schemes() {
		$design_schemes = get_option( 'design_schemes' );
		if ( ! is_array( $design_schemes ) ) {
			$design_schemes = array();
		}
		$design_schemes['price-color'] = isset( $design_schemes['price-color'] ) ? $design_schemes['price-color'] : 'red-price';
		$design_schemes['price-size']  = isset( $design_schemes['price-size'] ) ? $design_schemes['price-size'] : 'big-price';
		$design_schemes['box-color']   = isset( $design_schemes['box-color'] ) ? $design_schemes['box-color'] : 'green-box';

		return apply_filters( 'ic_catalog_design_schemes', $design_schemes );
	}

}

/**
 * Returns the default product image path.
 *
 * @return string
 */
function get_default_product_image_path() {
	$default_image = AL_BASE_PATH . '/img/no-default-thumbnail.png';
	$defined_image = get_option( 'default_product_thumbnail' );
	if ( ! empty( $defined_image ) ) {
		$upload_dir = wp_get_upload_dir();
		if ( ! empty( $upload_dir['baseurl'] ) && ic_string_contains( $defined_image, $upload_dir['baseurl'] ) ) {
			$defined_image = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $defined_image );
		}
	}
	$defined_image = empty( $defined_image ) ? $default_image : $defined_image;

	return $defined_image;
}

/**
 * Returns modern grid settings.
 *
 * @return array
 */
function get_modern_grid_settings() {
	$settings = wp_parse_args(
		get_option( 'modern_grid_settings' ),
		apply_filters(
			'ic_modern_grid_defaults',
			array(
				'attributes'         => 0,
				'per-row'            => 2,
				'per-row-categories' => 2,
				'attributes_num'     => 6,
			)
		)
	);

	return $settings;
}

/**
 * Returns product page settings.
 *
 * @return array
 */
function get_product_page_settings() {
	$multiple_settings = get_multiple_settings();
	$default_template  = 'boxed';
	if ( ! empty( $multiple_settings['edit_mode'] ) && 'classic' !== $multiple_settings['edit_mode'] ) {
		$default_template = 'plain';
	}
	$single_options                           = get_option(
		'multi_single_options',
		array(
			'enable_product_gallery' => 1,
			'template'               => $default_template,
		)
	);
	$single_options['enable_product_gallery'] = isset( $single_options['enable_product_gallery'] ) ? $single_options['enable_product_gallery'] : '';
	$single_options['enable_product_gallery_only_when_exist'] = isset( $single_options['enable_product_gallery_only_when_exist'] ) ? $single_options['enable_product_gallery_only_when_exist'] : '';
	$single_options['template']                               = isset( $single_options['template'] ) ? $single_options['template'] : $default_template;

	return apply_filters( 'ic_product_page_settings', $single_options );
}

/**
 * Returns currently selected product listing settings
 *
 * @return type
 */
function get_current_product_listing_settings() {
	$archive_template = get_product_listing_template();
	$settings         = '';
	if ( 'default' === $archive_template ) {
		$settings = get_modern_grid_settings();
	} elseif ( 'grid' === $archive_template ) {
		$settings = get_classic_grid_settings();
	} elseif ( 'list' === $archive_template ) {
		$settings = get_classic_list_settings();
	}

	return apply_filters( 'current_product_listing_settings', $settings, $archive_template );
}

/**
 * Returns currently selected listing design per row for categories
 *
 * @return int
 */
function get_current_category_per_row() {
	$settings = get_current_product_listing_settings();
	if ( ! empty( $settings['per-row-categories'] ) ) {
		return $settings['per-row-categories'];
	}

	return '';
}
