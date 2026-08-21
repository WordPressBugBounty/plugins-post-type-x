<?php
/**
 * Product settings management.
 *
 * @package ecommerce-product-catalog/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Returns the catalog settings screen instance.
 *
 * @return IC_Settings_Screen
 */
function ic_epc_settings_screen() {

	static $screen = null;

	if ( null === $screen ) {
		$screen = new IC_Settings_Screen(
			array(
				'parent_slug'             => 'edit.php?post_type=al_product',
				'page_title'              => __( 'Settings', 'post-type-x' ),
				'menu_title'              => __( 'Settings', 'post-type-x' ),
				'capability'              => 'manage_product_settings',
				'menu_slug'               => basename( __FILE__ ),
				'title'                   => __( 'Settings', 'post-type-x' ),
				'title_suffix'            => ' - impleCode ' . IC_CATALOG_PLUGIN_NAME,
				'tabs'                    => ic_epc_settings_screen_primary_tabs(),
				'tab_displays'            => ic_epc_settings_screen_tab_displays(),
				'show_logo'               => true,
				'show_unsaved_changes_js' => true,
				'show_sortable_rows_js'   => true,
				'show_nav_compact_js'     => true,
				'compact_reference_id'    => 'general-settings',
				'compact_hide_ids'        => array(
					'import-export-link-page',
					'add-new-product-page',
					'al_categories',
					'al_products',
					'extensions',
					'help',
				),
			)
		);
	}

	return $screen;
}

add_action( 'init', 'ic_epc_settings_screen_pages', 0 );
add_action( 'init', 'ic_epc_settings_screen', 1 );
add_action( 'ic_settings_screen_tab_submenus', 'ic_epc_render_settings_screen_tab_submenus', 10, 4 );
add_action( 'ic_settings_screen_tab_content', 'ic_epc_render_settings_screen_tab_content', 10, 4 );

/**
 * Fires the legacy product settings menu hook.
 *
 * @return void
 */
function register_product_settings_menu() {

	ic_epc_settings_screen()->register_menu();
	do_action( 'product_settings_menu' );
}

add_action( 'admin_menu', 'register_product_settings_menu', 11 );

if ( ! function_exists( 'ic_catalog_settings_list' ) ) {

	add_action( 'admin_init', 'ic_catalog_settings_list', 20 );

	/**
	 * Registers the catalog settings sections.
	 */
	function ic_catalog_settings_list() {
		do_action( 'product-settings-list' );
		do_action( 'ic-catalog-settings-list' );
	}

}

require_once AL_BASE_PATH . '/templates/themes/theme-default.php';
require_once AL_BASE_PATH . '/templates/themes/theme-classic-list.php';
require_once AL_BASE_PATH . '/templates/themes/theme-classic-grid.php';

/**
 * Returns custom top-level tabs for catalog post type screens.
 *
 * @return array
 */
function ic_epc_settings_screen_primary_tabs() {
	return array(
		'al_products'   => array(
			'label'           => get_catalog_names( 'plural' ),
			'menu_item_id'    => 'al_products',
			'url'             => admin_url( 'edit.php?post_type=al_product' ),
			'active_callback' => 'ic_admin_products_tab_active',
		),
		'al_categories' => array(
			'label'           => __( 'Categories', 'post-type-x' ),
			'menu_item_id'    => 'al_categories',
			'url'             => admin_url( 'edit-tags.php?taxonomy=al_product-cat&post_type=al_product' ),
			'active_callback' => 'ic_admin_product_categories_tab_active',
		),
	);
}

/**
 * Registers settings page instances for the catalog settings screen.
 *
 * @return array
 */
function ic_epc_settings_screen_pages() {
	$pages = array(
		ic_epc_general_settings_page(),
	);

	if ( function_exists( 'ic_epc_attributes_settings_page' ) ) {
		$pages[] = ic_epc_attributes_settings_page();
	}

	if ( function_exists( 'ic_epc_shipping_settings_page' ) ) {
		$pages[] = ic_epc_shipping_settings_page();
	}

	$pages[] = ic_epc_shared_archive_design_settings_page();
	$pages[] = ic_epc_shared_single_design_settings_page();
	$pages[] = ic_epc_shared_design_schemes_settings_page();
	$pages[] = ic_epc_single_names_settings_page();
	$pages[] = ic_epc_archive_names_settings_page();

	return $pages;
}

/**
 * Checks whether a settings screen instance is the catalog settings screen.
 *
 * @param mixed $screen Settings screen instance.
 *
 * @return bool
 */
function ic_epc_is_settings_screen_instance( $screen ) {
	if ( ! is_object( $screen ) || ! method_exists( $screen, 'menu_slug' ) ) {
		return false;
	}

	return basename( __FILE__ ) === $screen->menu_slug();
}

/**
 * Renders the settings navigation tabs.
 *
 * @return void
 */
function ic_product_settings_html() {

	ic_epc_settings_screen()->render_tabs( false );
}

/**
 * Renders additional legacy submenu links inside the generated screen tabs.
 *
 * @param string                 $tab_key Screen tab key.
 * @param IC_Settings_Page[]     $pages Registered settings pages.
 * @param IC_Settings_Page|null  $active_page Active page object.
 * @param IC_Settings_Screen     $screen Screen instance.
 *
 * @return void
 */
function ic_epc_render_settings_screen_tab_submenus( $tab_key, $pages, $active_page, $screen ) {
	switch ( $tab_key ) {
		case 'product-settings':
			do_action( 'general_submenu' );
			break;
		case 'attributes-settings':
			do_action( 'attributes_submenu' );
			break;
		case 'shipping-settings':
			do_action( 'shipping_submenu' );
			break;
		case 'design-settings':
			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Existing hook name retained for compatibility.
			do_action( 'custom-design-submenu' );
			break;
		case 'names-settings':
			do_action( 'front_end_labels_submenu' );
			break;
	}
}

/**
 * Fires legacy content hooks for screen tabs after generated page rendering.
 *
 * @param string                 $tab_key Screen tab key.
 * @param IC_Settings_Page|null  $active_page Active page object.
 * @param IC_Settings_Page[]     $pages Registered settings pages.
 * @param IC_Settings_Screen     $screen Screen instance.
 *
 * @return void
 */
function ic_epc_render_settings_screen_tab_content( $tab_key, $active_page, $pages, $screen ) {
	switch ( $tab_key ) {
		case 'product-settings':
			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Existing hook name retained for compatibility.
			do_action( 'product-settings' );
			break;
		case 'attributes-settings':
			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Existing hook name retained for compatibility.
			do_action( 'product-attributes' );
			break;
		case 'shipping-settings':
			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Existing hook name retained for compatibility.
			do_action( 'product-shipping' );
			break;
		case 'design-settings':
			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Existing hook name retained for compatibility.
			do_action( 'custom-design-settings' );
			break;
		case 'names-settings':
			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Existing hook name retained for compatibility.
			do_action( 'names-settings' );
			break;
	}
}

/**
 * Returns the additional tab display locations for the settings screen.
 *
 * @return array
 */
function ic_epc_settings_screen_tab_displays() {
	return array(
		array(
			'hook'               => 'ic_catalog_admin_notices',
			'priority'           => 1,
			'condition_callback' => 'ic_epc_settings_screen_is_product_editor_screen',
			'wrap_class'         => 'wrap',
			'before_html'        => '<style>.wrap h2.ic-nav-tab-wrapper{margin-top:50px;}</style>',
		),
		array(
			'hook'               => 'views_edit-al_product',
			'priority'           => 11,
			'condition_callback' => 'is_ic_product_list_admin_screen',
			'wrap_tag'           => '',
		),
		array(
			'hook'               => 'ic_catalog_admin_notices',
			'priority'           => 99,
			'condition_callback' => 'is_ic_product_categories_admin_screen',
			'wrap_class'         => 'wrap',
			'inner_wrap_tag'     => 'div',
			'inner_wrap_class'   => 'notice ic-transparent-notice',
			'before_html'        => '<style>.ic-transparent-notice{background:transparent;border:none;box-shadow:none;padding:0;}</style>',
		),
		array(
			'hook'               => 'al_product-cat_pre_edit_form',
			'priority'           => 99,
			'condition_callback' => 'is_ic_product_categories_edit_admin_screen',
			'wrap_class'         => 'wrap',
		),
	);
}

/**
 * Returns true when the current screen is the product edit or add screen.
 *
 * @return bool
 */
function ic_epc_settings_screen_is_product_editor_screen() {
	return is_ic_edit_product_screen() || is_ic_new_product_screen();
}

/**
 * Outputs the documentation helper box.
 *
 * @param string      $title Helper title.
 * @param string      $url Documentation URL slug.
 * @param string|null $class Optional CSS class.
 *
 * @return void
 */
function ic_epc_doc_helper( $title, $url, $class = null ) {
	$docs_url = 'https://implecode.com/docs/ecommerce-product-catalog/' . $url . '/#cam=catalog-docs-box&key=' . $url;
	$helper   = new IC_Settings_Helper_Box(
		array(
			'box_type_class' => $class,
			'title'          => sprintf(
				/* translators: %s: settings section title. */
				__( '%s Settings in Docs', 'post-type-x' ),
				ic_ucfirst( $title )
			),
			'descriptions'   => array(
				sprintf(
					/* translators: %s: settings section title. */
					__( 'See %s configuration tips in the impleCode documentation', 'post-type-x' ),
					$title
				),
			),
			'button_label'   => __( 'See in Docs', 'post-type-x' ),
			'button_url'     => $docs_url,
			'background_url' => $docs_url,
			'background_title' => __( 'Click the button to visit impleCode documentation', 'post-type-x' ),
		)
	);
	$helper->render();
}

/**
 * Outputs the did-you-know helper box.
 *
 * @param string      $name Helper key.
 * @param string      $desc Helper description.
 * @param string      $url Destination URL.
 * @param string|null $class Optional CSS class.
 *
 * @return void
 */
function ic_epc_did_know_helper( $name, $desc, $url, $class = null ) {
	$helper = new IC_Settings_Helper_Box(
		array(
			'box_type_class' => $class,
			'title'          => __( 'Did you know?', 'post-type-x' ),
			'descriptions'   => array( $desc . '.' ),
			'button_label'   => __( 'See Now', 'post-type-x' ),
			'button_url'     => $url . '#cam=catalog-know-box&key=' . $name,
			'background_url' => $url . '#cam=catalog-docs-box&key=' . $name,
			'background_title' => __( 'Click the button to visit impleCode website', 'post-type-x' ),
		)
	);
	$helper->render();
}

/**
 * Outputs a generic text helper box.
 *
 * @param string      $title Helper title.
 * @param string      $desc Helper description.
 * @param string|null $class Optional CSS class.
 *
 * @return void
 */
function ic_epc_text_helper( $title, $desc, $class = null ) {
	$helper = new IC_Settings_Helper_Box(
		array(
			'box_type_class' => trim( 'text ' . $class ),
			'title'          => $title,
			'descriptions'   => array( $desc ),
		)
	);
	$helper->render();
}

/**
 * Outputs the review helper box.
 *
 * @return void
 */
function ic_epc_review_helper() {
	$helper = new IC_Settings_Helper_Box(
		array(
			'box_type_class' => 'review',
			'title'          => __( 'Rate this Plugin!', 'post-type-x' ),
			'descriptions'   => array(
				sprintf(
					/* translators: %s: review URL. */
					__( 'Please <a href="%s">rate</a> this plugin and tell us if it works for you or not. It really helps development.', 'post-type-x' ),
					'https://wordpress.org/support/view/plugin-reviews/ecommerce-product-catalog#postform'
				),
			),
		)
	);
	$helper->render();
}

/**
 * Outputs the main help helper box.
 *
 * @return void
 */
function ic_epc_main_helper() {
	$helper = new IC_Settings_Helper_Box(
		array(
			'box_type_class'     => 'main',
			'title'              => __( 'Need Help?', 'post-type-x' ),
			'content_callback'   => 'ic_epc_main_helper_content',
		)
	);
	$helper->render();
}

/**
 * Renders the main help helper box content.
 *
 * @return void
 */
function ic_epc_main_helper_content() {
	?>
	<div class="doc-description">
		<form role="search" method="get" class="search-form" action="https://implecode.com/docs/">
			<label>
				<span class="screen-reader-text">Search for:</span>
				<input type="hidden" value="al_doc" name="post_type">
				<input type="search" class="search-field" placeholder="Search Docs ..." value="" name="s" title="Search for:">
			</label>
			<input type="submit" class="button-primary" value="Search">
		</form>
	</div>
	<?php
}

/**
 * Generates a bug report box.
 *
 * @return void
 */
function ic_epc_bug_report_helper() {
	$helper = new IC_Settings_Helper_Box(
		array(
			'box_type_class' => 'bug-report',
			'title'          => __( 'Do you have a problem?', 'post-type-x' ),
			'descriptions'   => array(
				__( 'All bug reports and support tickets are tracked on a daily basis.', 'post-type-x' ),
				sprintf(
					/* translators: %s: plugin name. */
					__( 'Feel free to submit a ticket if you think that you found a bug or you have a problem while using %s.', 'post-type-x' ),
					IC_CATALOG_PLUGIN_NAME
				),
			),
			'button_label'   => __( 'Report a Problem', 'post-type-x' ),
			'button_url'     => 'https://wordpress.org/support/plugin/ecommerce-product-catalog',
			'background_url' => 'https://wordpress.org/support/plugin/ecommerce-product-catalog',
			'background_title' => __( 'Click the button to visit the support forum.', 'post-type-x' ),
		)
	);
	$helper->render();
}

if ( ! function_exists( 'main_helper' ) ) {
	/**
	 * Legacy alias for external plugin compatibility.
	 *
	 * @return void
	 */
	function main_helper() {
		ic_epc_main_helper();
	}
}

/**
 * Returns all eCommerce Product Catalog option names.
 *
 * @param string $which Option subset selector.
 * @return array
 */
function all_ic_options( $which = 'all' ) {
	$options = array(
		'product_adder_theme_support_check',
		'product_attributes_number',
		'al_display_attributes',
		'product_attribute',
		'product_attribute_label',
		'product_attribute_unit',
		'archive_template',
		'modern_grid_settings',
		'classic_grid_settings',
		'catalog_lightbox',
		'catalog_magnifier',
		'multi_single_options',
		'default_product_thumbnail',
		'ic_default_product_image_id',
		'design_schemes',
		'archive_names',
		'single_names',
		'product_listing_url',
		'product_currency',
		'product_currency_settings',
		'product_archive',
		'enable_product_listing',
		'archive_multiple_settings',
		'product_shipping_options_number',
		'display_shipping',
		'product_shipping_cost',
		'product_shipping_label',
		'product_archive_page_id',
	);
	$tools   = array(
		'ic_epc_tracking_last_send',
		'ic_epc_tracking_notice',
		'ic_epc_allow_tracking',
		'ic_delete_products_uninstall',
		'ecommerce_product_catalog_ver',
		'sample_product_id',
		'al_permalink_options_update',
		'custom_license_code',
		'implecode_license_owner',
		'no_implecode_license_error',
		'license_active_plugins',
		'product_adder_theme_support_check',
		'implecode_hide_plugin_review_info_count',
		'hide_empty_bar_message',
		'ic_hidden_notices',
		'ic_hidden_boxes',
		'old_sort_bar',
		'first_activation_version',
		'ic_allow_woo_template_file',
		'ic_block_woo_template_file',
	);
	if ( 'all' === $which ) {
		return array_merge( $options, $tools );
	} elseif ( 'tools' === $which ) {
		return $tools;
	} else {
		return $options;
	}
}
