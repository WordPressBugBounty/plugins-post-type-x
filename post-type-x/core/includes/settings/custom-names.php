<?php
/**
 * Custom names settings.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'ic_settings_page_content', 'ic_epc_custom_names_page_content', 10, 3 );

/**
 * Manages custom names settings
 *
 * Here custom names settings are defined and managed.
 *
 * @version        1.1.4
 * @package        ecommerce-product-catalog/functions
 * @author        impleCode
 */
function default_single_names() {
	if ( is_plural_form_active() ) {
		$names        = get_catalog_names();
		$single_names = array(
			/* translators: %s: Singular catalog item name. */
			'product_price'       => sprintf( __( '%s Price', 'post-type-x' ), ic_ucfirst( $names['singular'] ) ),
			'product_sku'         => __( 'SKU:', 'post-type-x' ),
			'product_mpn'         => __( 'MPN:', 'post-type-x' ),
			/* translators: %s: Singular catalog item name. */
			'product_shipping'    => sprintf( __( '%s Shipping', 'post-type-x' ), ic_ucfirst( $names['singular'] ) ),
			/* translators: %s: Singular catalog item name. */
			'product_features'    => sprintf( __( '%s Features', 'post-type-x' ), ic_ucfirst( $names['singular'] ) ),
			'other_categories'    => __( 'See also different:', 'post-type-x' ),
			/* translators: %s: Plural catalog item name. */
			'return_to_archive'   => sprintf( __( '<< return to %s', 'post-type-x' ), ic_lcfirst( $names['plural'] ) ),
			'free'                => __( 'Free', 'post-type-x' ),
			'free_shipping'       => __( 'Free', 'post-type-x' ),
			/* translators: %s: Singular catalog item name. */
			'product_description' => sprintf( __( '%s Description', 'post-type-x' ), ic_ucfirst( $names['singular'] ) ),
			'after_price'         => '',
		);
	} else {
		$single_names = array(
			'product_price'       => __( 'Price:', 'post-type-x' ),
			'product_sku'         => __( 'SKU:', 'post-type-x' ),
			'product_mpn'         => __( 'MPN:', 'post-type-x' ),
			'product_shipping'    => __( 'Shipping', 'post-type-x' ),
			'product_features'    => __( 'Features', 'post-type-x' ),
			'other_categories'    => __( 'See also different:', 'post-type-x' ),
			'return_to_archive'   => __( '<< return to listing', 'post-type-x' ),
			'free'                => __( 'Free', 'post-type-x' ),
			'free_shipping'       => __( 'Free', 'post-type-x' ),
			'product_description' => __( 'Description', 'post-type-x' ),
			'after_price'         => '',
		);
	}

	return apply_filters( 'ic_default_single_names', $single_names );
}

/**
 * Defines default labels for product listing pages
 *
 * @return array
 */
function default_archive_names() {
	if ( is_plural_form_active() ) {
		$names         = get_catalog_names();
		$archive_names = array(
			/* translators: %s: Plural catalog item name. */
			'all_products'        => sprintf( __( 'All %s', 'post-type-x' ), ic_ucfirst( $names['plural'] ) ),
			'all_prefix'          => __( 'All', 'post-type-x' ),
			'all_main_categories' => __( 'Main Categories', 'post-type-x' ),
			'all_subcategories'   => '[product_category_name] ' . __( 'Subcategories', 'post-type-x' ),
			'category_products'   => '[product_category_name] ' . ic_ucfirst( $names['plural'] ),
			'next_products'       => __( 'Next Page »', 'post-type-x' ),
			'previous_products'   => __( '« Previous Page', 'post-type-x' ),
			'bread_home'          => __( 'Home', 'post-type-x' ),
		);
	} else {
		$archive_names = array(
			'all_products'        => __( 'All Products', 'post-type-x' ),
			'all_prefix'          => __( 'All', 'post-type-x' ),
			'all_main_categories' => __( 'Main Categories', 'post-type-x' ),
			'all_subcategories'   => '[product_category_name] ' . __( 'Subcategories', 'post-type-x' ),
			'category_products'   => '[product_category_name] ' . __( 'Products', 'post-type-x' ),
			'next_products'       => __( 'Next Page »', 'post-type-x' ),
			'previous_products'   => __( '« Previous Page', 'post-type-x' ),
			'bread_home'          => __( 'Home', 'post-type-x' ),
		);
	}

	return apply_filters( 'ic_default_archive_names', $archive_names );
}

add_action( 'ic_settings_page_sections_end', 'ic_epc_custom_names_page_sections_end', 10, 3 );

/**
 * Returns the single page labels shared settings page.
 *
 * @return IC_Settings_Page
 */
function ic_epc_single_names_settings_page() {
	static $page = null;
	if ( null === $page ) {
		$single_names = get_single_names();
		$sections     = array(
			array(
				'title'       => __( 'Single Item Page Labels', 'post-type-x' ),
				'table_class' => 'IC_Settings_Standard_Table',
				'table_args'  => array(
					'settings' => array(
						'headings'    => array(
							__( 'Front-end Element', 'post-type-x' ),
							__( 'Front-end Text', 'post-type-x' ),
						),
						'table_class' => 'wp-list-table widefat product-settings-table',
						'rows'        => ic_epc_single_names_table_rows( $single_names ),
					),
				),
			),
		);

		$page = new IC_Settings_Page(
			array(
				'title'              => __( 'Front-end Labels', 'post-type-x' ),
				'option_group'       => 'product_names_single',
				'option_name'        => 'single_names',
				'registered_options' => array(
					'single_names',
				),
				'submenu'            => 'single-names',
				'screen'             => 'product-settings.php',
				'screen_tab'         => 'names-settings',
				'screen_tab_label'   => __( 'Front-end Labels', 'post-type-x' ),
				'screen_tab_menu_item_id' => 'names-settings',
				'screen_tab_query_args' => array(
					'submenu' => 'single-names',
				),
				'screen_tab_content_wrapper_class' => 'names-product-settings',
				'screen_submenu_label' => __( 'Single Item Page', 'post-type-x' ),
				'submenu_item_id'    => 'single-names',
				'container_class'    => 'setting-content submenu',
				'settings'           => array(
					'single_names' => $single_names,
				),
				'content_settings'   => array(),
				'sections'           => $sections,
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
 * Returns the archive labels shared settings page.
 *
 * @return IC_Settings_Page
 */
function ic_epc_archive_names_settings_page() {
	static $page = null;
	if ( null === $page ) {
		$archive_names = get_archive_names();
		$disabled      = '';
		if ( 'simple' === get_integration_type() ) {
			$disabled = 'disabled';
		}

		$sections = array(
			array(
				'title'       => __( 'Listing Pages Labels', 'post-type-x' ),
				'table_class' => 'IC_Settings_Standard_Table',
				'table_args'  => array(
					'settings' => array(
						'headings'    => array(
							__( 'Front-end Element', 'post-type-x' ),
							array(
								'label' => __( 'Front-end Text', 'post-type-x' ),
								'width' => '69%',
							),
						),
						'table_class' => 'wp-list-table widefat product-settings-table',
						'notices'     => ic_epc_archive_names_table_notices(),
						'rows'        => ic_epc_archive_names_table_rows( $archive_names, $disabled ),
					),
				),
			),
		);

		$page = new IC_Settings_Page(
			array(
				'title'              => __( 'Front-end Labels', 'post-type-x' ),
				'option_group'       => 'product_names_archive',
				'option_name'        => 'archive_names',
				'registered_options' => array(
					'archive_names',
				),
				'submenu'            => 'archive-names',
				'screen'             => 'product-settings.php',
				'screen_tab'         => 'names-settings',
				'screen_tab_label'   => __( 'Front-end Labels', 'post-type-x' ),
				'screen_tab_menu_item_id' => 'names-settings',
				'screen_tab_query_args' => array(
					'submenu' => 'single-names',
				),
				'screen_tab_content_wrapper_class' => 'names-product-settings',
				'screen_submenu_label' => __( 'Listing Pages', 'post-type-x' ),
				'submenu_item_id'    => 'archive-names',
				'container_class'    => 'setting-content submenu',
				'settings'           => array(
					'archive_names' => $archive_names,
					'disabled'      => $disabled,
				),
				'content_settings'   => array(),
				'sections'           => $sections,
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
 * Renders custom names page content before sections.
 *
 * @param string $option_group Option group.
 *
 * @return void
 */
function ic_epc_custom_names_page_content( $option_group ) {
	if ( 'product_names_archive' !== $option_group ) {
		return;
	}
	?>
	<style>
		.names-product-settings .setting-content th {
			text-align: left;
		}
	</style>
	<?php
}

/**
 * Returns single label settings table rows.
 *
 * @param array $single_names Single labels.
 *
 * @return array
 */
function ic_epc_single_names_table_rows( $single_names ) {
	$rows = array(
		array(
			'callback'     => 'ic_epc_render_single_names_table_start_row',
			'single_names' => $single_names,
		),
		array(
			'type'  => 'text',
			'label' => __( 'Description Label', 'post-type-x' ),
			'name'  => 'single_names[product_description]',
			'value' => $single_names['product_description'],
		),
		array(
			'type'  => 'text',
			'label' => __( 'Features Label', 'post-type-x' ),
			'name'  => 'single_names[product_features]',
			'value' => $single_names['product_features'],
		),
		array(
			'type'  => 'text',
			'label' => __( 'Another Categories Label', 'post-type-x' ),
			'name'  => 'single_names[other_categories]',
			'value' => $single_names['other_categories'],
		),
		array(
			'type'  => 'text',
			'label' => __( 'Return to Products Label', 'post-type-x' ),
			'name'  => 'single_names[return_to_archive]',
			'value' => $single_names['return_to_archive'],
		),
		array(
			'callback'     => 'ic_epc_render_single_names_table_end_row',
			'single_names' => $single_names,
		),
	);

	return apply_filters( 'ic_epc_single_names_table_rows', $rows, $single_names );
}

/**
 * Returns archive label settings table rows.
 *
 * @param array  $archive_names Archive labels.
 * @param string $disabled Disabled attribute string.
 *
 * @return array
 */
function ic_epc_archive_names_table_rows( $archive_names, $disabled ) {
	$rows = array(
		array(
			'type'     => 'text',
			'label'    => __( 'Main Listing Title Label', 'post-type-x' ),
			'name'     => 'archive_names[all_products]',
			'value'    => $archive_names['all_products'],
			'class'    => 'wide',
			'disabled' => $disabled,
		),
		array(
			'type'     => 'text',
			'label'    => __( 'Categories Header Label', 'post-type-x' ),
			'name'     => 'archive_names[all_main_categories]',
			'value'    => $archive_names['all_main_categories'],
			'class'    => 'wide',
			'disabled' => $disabled,
		),
		array(
			'type'     => 'text',
			'label'    => __( 'Subcategories Header Label', 'post-type-x' ),
			'name'     => 'archive_names[all_subcategories]',
			'value'    => $archive_names['all_subcategories'],
			'class'    => 'wide',
			'disabled' => $disabled,
		),
		array(
			'type'     => 'text',
			'label'    => __( 'Category Prefix Label', 'post-type-x' ),
			'name'     => 'archive_names[all_prefix]',
			'value'    => $archive_names['all_prefix'],
			'class'    => 'wide',
			'disabled' => $disabled,
		),
		array(
			'type'     => 'text',
			'label'    => __( 'Category Products Header Label', 'post-type-x' ),
			'name'     => 'archive_names[category_products]',
			'value'    => $archive_names['category_products'],
			'class'    => 'wide',
			'disabled' => $disabled,
		),
		array(
			'type'     => 'text',
			'label'    => __( 'Next Page Label', 'post-type-x' ),
			'name'     => 'archive_names[next_products]',
			'value'    => $archive_names['next_products'],
			'class'    => 'wide',
			'disabled' => $disabled,
		),
		array(
			'type'     => 'text',
			'label'    => __( 'Previous Page Label', 'post-type-x' ),
			'name'     => 'archive_names[previous_products]',
			'value'    => $archive_names['previous_products'],
			'class'    => 'wide',
			'disabled' => $disabled,
		),
		array(
			'type'     => 'text',
			'label'    => __( 'Breadcrumbs Home Label', 'post-type-x' ),
			'name'     => 'archive_names[bread_home]',
			'value'    => $archive_names['bread_home'],
			'class'    => 'wide',
			'disabled' => $disabled,
		),
	);

	$rows = apply_filters( 'ic_epc_archive_names_table_rows', $rows, $archive_names, $disabled );

	$rows[] = array(
		'callback'      => 'ic_epc_render_archive_names_table_end_row',
		'archive_names' => $archive_names,
		'disabled'      => $disabled,
	);

	return $rows;
}

/**
 * Renders single names extension rows before built-in rows.
 *
 * @param array $row Callback row data.
 *
 * @return void
 */
function ic_epc_render_single_names_table_start_row( $row ) {
	do_action( 'single_names_table_start', $row['single_names'] );
}

/**
 * Renders single names extension rows after built-in rows.
 *
 * @param array $row Callback row data.
 *
 * @return void
 */
function ic_epc_render_single_names_table_end_row( $row ) {
	do_action( 'single_names_table', $row['single_names'] );
}

/**
 * Returns archive names table notices.
 *
 * @return array
 */
function ic_epc_archive_names_table_notices() {
	if ( 'simple' === get_integration_type() ) {
		if ( is_integration_mode_selected() ) {
			return array(
				array(
					'type'    => 'warning',
					/* translators: %s: Theme integration guide URL. */
					'message' => sprintf( __( 'Product listing pages are disabled with simple theme integration. See <a href="%s">Theme Integration Guide</a> to enable product listing pages.', 'post-type-x' ), 'https://implecode.com/wordpress/product-catalog/theme-integration-guide/#cam=simple-mode&key=front-labels' ),
				),
			);
		}

		return array(
			array(
				'type'    => 'warning',
				/* translators: %s: Example product button HTML. */
				'message' => sprintf( __( 'Product listing pages are disabled due to a lack of theme integration.%s', 'post-type-x' ), sample_product_button( 'p' ) ),
			),
		);
	}

	return array();
}

/**
 * Renders archive names extension rows after built-in rows.
 *
 * @param array $row Callback row data.
 *
 * @return void
 */
function ic_epc_render_archive_names_table_end_row( $row ) {
	do_action( 'archive_names_table', $row['archive_names'], $row['disabled'] );
}

/**
 * Renders additional labels settings registered on the legacy hooks.
 *
 * @param string $option_group Option group.
 * @param array  $settings Page settings.
 *
 * @return void
 */
function ic_epc_custom_names_page_sections_end( $option_group, $settings ) {
	if ( 'product_names_single' === $option_group && has_action( 'product_page_front_end_labels_settings' ) ) {
		do_action( 'product_page_front_end_labels_settings', $settings['single_names'] );

		return;
	}

	if ( 'product_names_archive' === $option_group && has_action( 'product_listing_front_end_labels_settings' ) ) {
		do_action( 'product_listing_front_end_labels_settings', $settings['archive_names'], $settings['disabled'] );
	}
}

/**
 * Returns archive names settings.
 *
 * @return array
 */
function get_archive_names() {
	$archive_names = ic_get_global( 'archive_names' );
	if ( ! $archive_names ) {
		$default_archive_names = default_archive_names();
		$archive_names         = apply_filters( 'ic_get_archive_names', wp_parse_args( get_option( 'archive_names' ), $default_archive_names ) );
		ic_save_global( 'archive_names', $archive_names );
	}

	return $archive_names;
}

/**
 * Returns single names settings
 *
 * @return type
 */
function get_single_names() {
	$single_names = ic_get_global( 'single_names' );
	if ( ! $single_names ) {
		$default_single_names = default_single_names();
		$single_names         = get_option( 'single_names', $default_single_names );
		if ( ! is_array( $single_names ) ) {
			$single_names = array();
		}
		foreach ( $default_single_names as $key => $value ) {
			$single_names[ $key ] = isset( $single_names[ $key ] ) ? $single_names[ $key ] : $value;
		}
		ic_save_global( 'single_names', $single_names );
	}

	return apply_filters( 'ic_get_single_names', $single_names );
}
