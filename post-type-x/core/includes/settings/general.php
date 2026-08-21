<?php
/**
 * General catalog settings.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Validates archive multiple settings
 *
 * @param array $new_value New settings value.
 *
 * @return array
 */
function archive_multiple_settings_validation( $new_value ) {
	if ( function_exists( 'ic_force_clear_cache' ) ) {
		ic_force_clear_cache();
	}
	$product_slug = get_product_slug();
	if ( isset( $new_value['category_archive_url'] ) && $new_value['category_archive_url'] === $product_slug ) {
		$new_value['category_archive_url'] = $new_value['category_archive_url'] . '-1';
	}

	return apply_filters( 'ic_archive_multiple_settings_validation', $new_value );
}

/**
 * Validates product currency settings
 *
 * @param array $new_value New currency settings value.
 *
 * @return array
 */
function product_currency_settings_validation( $new_value ) {
	if ( empty( $new_value ) ) {
		return $new_value;
	}
	if ( $new_value['th_sep'] === $new_value['dec_sep'] ) {
		if ( ',' === $new_value['th_sep'] ) {
			$new_value['th_sep'] = '.';
		} else {
			$new_value['th_sep'] = ',';
		}
	}

	return $new_value;
}

add_action( 'init', 'general_options_validation_filters' );

/**
 * Initializes validation filters for general settings
 */
function general_options_validation_filters() {
	add_filter( 'pre_update_option_archive_multiple_settings', 'archive_multiple_settings_validation' );
	add_filter( 'pre_update_option_product_currency_settings', 'product_currency_settings_validation' );
}

add_action( 'ic_settings_page_before_form', 'ic_epc_general_settings_page_before_form', 10, 3 );
add_action( 'ic_settings_page_sections_end', 'ic_epc_general_settings_page_sections_end', 10, 3 );

/**
 * Returns the shared general settings page instance.
 *
 * @return IC_Settings_Page
 */
function ic_epc_general_settings_page() {
	static $page = null;
	if ( null === $page ) {
		$page = new IC_Settings_Page(
			array(
				'title'              => __( 'General Settings', 'post-type-x' ),
				'option_group'       => 'product_settings',
				'option_name'        => 'archive_multiple_settings',
				'registered_options' => array(
					'product_listing_url',
					'product_currency',
					'product_currency_settings',
					'product_archive',
					'enable_product_listing',
					'archive_multiple_settings',
				),
				'submenu'            => array( 'general-settings', '' ),
				'screen'             => 'product-settings.php',
				'screen_tab'         => 'product-settings',
				'screen_tab_label'   => __( 'General Settings', 'post-type-x' ),
				'screen_tab_menu_item_id' => 'general-settings',
				'screen_tab_query_args' => array(
					'submenu' => 'general-settings',
				),
				'screen_tab_default' => true,
				'screen_tab_content_wrapper_class' => 'overall-product-settings',
				'screen_tab_content_wrapper_style' => 'clear:both;',
				'screen_submenu_label' => __( 'General Settings', 'post-type-x' ),
				'submenu_item_id'    => 'general-settings',
				'container_class'    => 'setting-content submenu',
				'settings'           => ic_epc_general_settings_page_settings(),
				'content_settings'   => array(),
				'sections'           => ic_epc_general_settings_page_sections(),
				'helpers'            => array(
					'ic_epc_main_helper',
					array(
						'callback' => 'ic_epc_doc_helper',
						'args'     => array( __( 'shortcode', 'post-type-x' ), 'product-catalog-shortcodes' ),
					),
					array(
						'callback' => 'ic_epc_doc_helper',
						'args'     => array( __( 'sorting', 'post-type-x' ), 'product-order-settings' ),
					),
				),
				'submit_label'       => __( 'Save changes', 'post-type-x' ),
			)
		);
	}

	return $page;
}

/**
 * Returns current general settings values used by the shared settings page.
 *
 * @return array
 */
function ic_epc_general_settings_page_settings() {
	$item_name = ic_catalog_item_name();

	return array(
		'archive_multiple_settings' => get_multiple_settings(),
		'enable_product_listing'    => get_option( 'enable_product_listing', 1 ),
		'product_archive'           => get_product_listing_id(),
		'item_name'                 => $item_name,
		'uc_item_name'              => ic_ucfirst( $item_name ),
	);
}

/**
 * Returns the general settings page sections.
 *
 * @return array
 */
function ic_epc_general_settings_page_sections() {
	$page_settings             = ic_epc_general_settings_page_settings();
	$archive_multiple_settings = $page_settings['archive_multiple_settings'];
	$sections                  = array(
		array(
			'title'            => __( 'Catalog Layout Integration', 'post-type-x' ),
			'settings'         => $page_settings,
			'content_callback' => 'ic_epc_render_general_layout_section',
		),
		array(
			'title'       => __( 'Catalog Label', 'post-type-x' ),
			'table_class' => 'IC_Settings_Standard_Table',
			'settings'    => array(
				'rows' => array(
					array(
						'type'  => 'text',
						'label' => __( 'Catalog Singular Name', 'post-type-x' ),
						'name'  => 'archive_multiple_settings[catalog_singular]',
						'value' => $archive_multiple_settings['catalog_singular'],
						'tip'   => __( 'Admin panel customisation setting. Change it to what you sell.', 'post-type-x' ),
					),
					array(
						'type'  => 'text',
						'label' => __( 'Catalog Plural Name', 'post-type-x' ),
						'name'  => 'archive_multiple_settings[catalog_plural]',
						'value' => $archive_multiple_settings['catalog_plural'],
						'tip'   => __( 'Admin panel customisation setting. Change it to what you sell.', 'post-type-x' ),
					),
				),
			),
		),
		array(
			'title'       => __( 'Main listing page', 'post-type-x' ),
			'table_class' => 'IC_Settings_Standard_Table',
			'table_args'  => array(
				'settings' => ic_epc_general_main_listing_table_settings( $page_settings ),
			),
		),
		array(
			'title'           => __( 'Categories Settings', 'post-type-x' ),
			'table_class'     => 'IC_Settings_Standard_Table',
			'table_args'      => array(
				'settings' => ic_epc_general_categories_table_settings( $page_settings ),
			),
			'container_class' => 'ic-settings-section advanced_mode_settings_inline',
		),
		array(
			'title'           => __( 'SEO Settings', 'post-type-x' ),
			'table_class'     => 'IC_Settings_Standard_Table',
			'container_class' => 'ic-settings-section advanced_mode_settings_inline',
			'settings'        => array(
				'rows' => array(
					array(
						'type'  => 'text',
						'label' => __( 'Archive SEO Title', 'post-type-x' ),
						'name'  => 'archive_multiple_settings[seo_title]',
						'value' => $archive_multiple_settings['seo_title'],
						'tip'   => __( 'The title tag for selected product listing page. If you are using separate SEO plugin you should set it there. E.g. in Yoast SEO look for it in Custom Post Types archive titles section.', 'post-type-x' ),
					),
					array(
						'type'  => 'checkbox',
						'label' => __( 'Enable SEO title separator', 'post-type-x' ),
						'name'  => 'archive_multiple_settings[seo_title_sep]',
						'value' => $archive_multiple_settings['seo_title_sep'],
					),
					array(
						'type'  => 'checkbox',
						'label' => __( 'Enable Structured Data', 'post-type-x' ),
						'name'  => 'archive_multiple_settings[enable_structured_data]',
						'value' => $archive_multiple_settings['enable_structured_data'],
						'tip'   => __( 'Enable to show structured data on each single product page. Test it with Google’s Structured Data Testing Tool. You can modify the output with the structured-data.php template file.', 'post-type-x' ),
					),
				),
			),
		),
		array(
			'title'           => __( 'Breadcrumbs Settings', 'post-type-x' ),
			'table_class'     => 'IC_Settings_Standard_Table',
			'container_class' => 'ic-settings-section advanced_mode_settings_inline',
			'settings'        => array(
				'rows' => array(
					array(
						'type'  => 'checkbox',
						'label' => __( 'Enable Catalog Breadcrumbs', 'post-type-x' ),
						'name'  => 'archive_multiple_settings[enable_product_breadcrumbs]',
						'value' => $archive_multiple_settings['enable_product_breadcrumbs'],
						'tip'   => __( 'Shows a path to the currently displayed product catalog page with URLs to parent pages and correct schema markup for SEO.', 'post-type-x' ),
					),
					array(
						'type'  => 'text',
						'label' => __( 'Main listing breadcrumbs title', 'post-type-x' ),
						'name'  => 'archive_multiple_settings[breadcrumbs_title]',
						'value' => $archive_multiple_settings['breadcrumbs_title'],
						'tip'   => __( 'The title for main product listing in breadcrumbs.', 'post-type-x' ),
					),
				),
			),
		),
	);

	if ( file_exists( AL_BASE_PATH . '/modules/cart/index.php' ) ) {
		array_splice(
			$sections,
			1,
			0,
			array(
				array(
					'title'       => __( 'Catalog Mode', 'post-type-x' ),
					'table_class' => 'IC_Settings_Standard_Table',
					'settings'    => array(
						'rows' => array(
							array(
								'type'    => 'radio',
								'label'   => __( 'Catalog Mode', 'post-type-x' ),
								'name'    => 'archive_multiple_settings[catalog_mode]',
								'value'   => $archive_multiple_settings['catalog_mode'],
								'options' => array(
									'store'     => __( 'Web Store', 'post-type-x' ),
									'inquiry'   => __( 'Inquiry Catalog', 'post-type-x' ),
									'affiliate' => __( 'Affiliate Catalog', 'post-type-x' ),
									'simple'    => __( 'Simple Catalog', 'post-type-x' ),
								),
								'tip'     => __( 'Choose your usage scenario.', 'post-type-x' ),
							),
						),
					),
				),
			)
		);
	}

	$sections = apply_filters( 'ic_epc_general_settings_page_sections', $sections, $page_settings );

	return $sections;
}

/**
 * Returns main listing section table settings.
 *
 * @param array $page_settings Page settings.
 *
 * @return array
 */
function ic_epc_general_main_listing_table_settings( $page_settings ) {
	$archive_multiple_settings = $page_settings['archive_multiple_settings'];
	$item_name                 = $page_settings['item_name'];
	$uc_item_name              = $page_settings['uc_item_name'];

	return array(
		'archive_multiple_settings' => $archive_multiple_settings,
		'enable_product_listing'    => $page_settings['enable_product_listing'],
		'product_archive'           => $page_settings['product_archive'],
		'rows'                      => array(
			array(
				'type'  => 'checkbox',
				'label' => __( 'Enable Main Listing Page', 'post-type-x' ),
				'name'  => 'enable_product_listing',
				'value' => $page_settings['enable_product_listing'],
				/* translators: %s: Product catalog shortcode. */
				'tip'   => sprintf( __( 'Disable and use %s shortcode to display the products.', 'post-type-x' ), '[show_products]' ),
			),
			array(
				'callback' => 'ic_epc_render_main_listing_page_row',
				'settings' => $page_settings,
			),
			array(
				'type'  => 'number',
				'label' => __( 'Listing shows at most', 'post-type-x' ),
				'name'  => 'archive_multiple_settings[archive_products_limit]',
				'value' => $archive_multiple_settings['archive_products_limit'],
				'unit'  => $item_name,
				'step'  => 1,
				'min'   => 1,
				/* translators: %s: Product catalog shortcode. */
				'tip'   => __( 'You can also use shortcode with products_limit attribute to set this.', 'post-type-x' ),
			),
			array(
				'type'    => 'radio',
				'label'   => __( 'Main listing shows', 'post-type-x' ),
				'name'    => 'archive_multiple_settings[product_listing_cats]',
				'value'   => $archive_multiple_settings['product_listing_cats'],
				'options' => array(
					'off'              => $uc_item_name,
					'on'               => $uc_item_name . ' & ' . __( 'Main Categories', 'post-type-x' ),
					/* translators: %s: Catalog singular name. */
					'cats_only'        => sprintf( __( 'Main Categories & Uncategorized %s', 'post-type-x' ), $uc_item_name ),
					'forced_cats_only' => __( 'Main Categories', 'post-type-x' ),
				),
			),
			array(
				'type'    => 'radio',
				'label'   => __( 'Default order', 'post-type-x' ),
				'name'    => 'archive_multiple_settings[product_order]',
				'value'   => $archive_multiple_settings['product_order'],
				'options' => get_product_sort_options(),
				'tip'     => __( 'This is also the default setting for sorting drop-down.', 'post-type-x' ),
			),
			array(
				'callback'                  => 'ic_epc_render_product_listing_page_settings_row',
				'archive_multiple_settings' => $archive_multiple_settings,
			),
		),
		'notices'                   => ic_epc_general_main_listing_table_notices(),
	);
}

/**
 * Returns categories section table settings.
 *
 * @param array $page_settings Page settings.
 *
 * @return array
 */
function ic_epc_general_categories_table_settings( $page_settings ) {
	$archive_multiple_settings = $page_settings['archive_multiple_settings'];
	$uc_item_name              = $page_settings['uc_item_name'];

	return array(
		'archive_multiple_settings' => $archive_multiple_settings,
		'rows'                      => array(
			array(
				'callback'                  => 'ic_epc_render_category_archive_url_row',
				'archive_multiple_settings' => $archive_multiple_settings,
			),
			array(
				'type'    => 'radio',
				'label'   => __( 'Category Page shows', 'post-type-x' ),
				'name'    => 'archive_multiple_settings[category_top_cats]',
				'value'   => $archive_multiple_settings['category_top_cats'],
				'options' => array(
					'off'                => $uc_item_name,
					'on'                 => $uc_item_name . ' & ' . __( 'Subcategories', 'post-type-x' ),
					'only_subcategories' => __( 'Subcategories', 'post-type-x' ),
				),
				'tip'     => __( 'The main listing can show only products, top-level categories and products or only the categories. With the subcategories option selected the products will show up only if they are directly assigned to the category. If you want to display the products only on the bottom category level, please assign the products only to it (not to all categories in the tree).', 'post-type-x' ),
			),
			array(
				'type'    => 'radio',
				'label'   => __( 'Categories Display', 'post-type-x' ),
				'name'    => 'archive_multiple_settings[cat_template]',
				'value'   => $archive_multiple_settings['cat_template'],
				'options' => array(
					'template' => __( 'Template', 'post-type-x' ),
					'link'     => __( 'URLs', 'post-type-x' ),
				),
				'tip'     => __( 'Template option will display categories with the same listing theme as products. Link option will show categories as simple URLs without image.', 'post-type-x' ),
			),
			array(
				'type'  => 'checkbox',
				'label' => __( 'Disable Image on Category Page', 'post-type-x' ),
				'name'  => 'archive_multiple_settings[cat_image_disabled]',
				'value' => $archive_multiple_settings['cat_image_disabled'],
				'tip'   => __( 'If you disable the image, it will be only used for categories listing.', 'post-type-x' ),
			),
			array(
				'type'    => 'radio',
				'label'   => __( 'Show Related', 'post-type-x' ),
				'name'    => 'archive_multiple_settings[related]',
				'value'   => $archive_multiple_settings['related'],
				'options' => array(
					'products'   => $uc_item_name,
					'categories' => __( 'Categories', 'post-type-x' ),
					'none'       => __( 'Nothing', 'post-type-x' ),
				),
				'tip'     => __( 'The related products or categories will be shown on the bottom of product pages.', 'post-type-x' ),
			),
			array(
				'callback'                  => 'ic_epc_render_product_category_settings_row',
				'archive_multiple_settings' => $archive_multiple_settings,
			),
		),
		'notices'                   => ic_epc_general_categories_table_notices(),
	);
}

/**
 * Renders the general settings action buttons above the shared form.
 *
 * @param string $option_group Option group.
 *
 * @return void
 */
function ic_epc_general_settings_page_before_form( $option_group ) {
	if ( 'product_settings' !== $option_group ) {
		return;
	}
	?>
	<p class="ic-general-settings-actions">
		<a class="button-secondary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=al_product&page=implecode_welcome' ) ); ?>"><?php esc_html_e( 'Configuration Wizard', 'post-type-x' ); ?></a>
		<a class="button-secondary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=al_product&page=system.php&reset_product_settings=1' ) ); ?>"><?php esc_html_e( 'Reset Catalog Settings', 'post-type-x' ); ?></a>
	</p>
	<style>
		.ic-general-settings-actions {
			display: flex;
			flex-wrap: wrap;
			gap: 10px;
			margin: 0 0 16px;
		}
	</style>
	<?php
}

/**
 * Renders the catalog layout integration section.
 *
 * @param array $settings Page settings.
 *
 * @return void
 */
function ic_epc_render_general_layout_section( $settings ) {
	$archive_multiple_settings = $settings['archive_multiple_settings'];
	$enable_product_listing    = $settings['enable_product_listing'];
	$product_archive           = $settings['product_archive'];
	$create_new_button         = ic_create_new_listing_page();

	if ( ! is_advanced_mode_forced() || ic_is_woo_template_available() || is_ic_shortcode_integration() ) {
		$integration_panel_shown = 1;
		?>
		<div class="ic-important-settings">
			<?php
			$tip  = __( 'Select a page to display your products.', 'post-type-x' );
			$tip .= ' ' . __( 'The selected page will become a parent for each product.', 'post-type-x' );
			/* translators: %s: Product catalog shortcode. */
			$tip .= ' ' . sprintf( __( 'Place %s on this page.', 'post-type-x' ), ic_catalog_shortcode_name() );
			?>
			<div></div>
			<span><span style="vertical-align: middle;" title="<?php echo esc_attr( $tip ); ?>" class="dashicons dashicons-editor-help ic_tip"></span><?php esc_html_e( 'Main Catalog Page', 'post-type-x' ); ?>: </span>
			<?php
			if ( 1 === (int) $enable_product_listing ) {
				$listing_url = product_listing_url();
				ic_select_page( 'product_archive', __( 'Default', 'post-type-x' ), $product_archive, true, $listing_url, 1, false, $create_new_button );
			} else {
				ic_select_page( 'product_archive', __( 'Default', 'post-type-x' ), $product_archive, true, false, 1, false, $create_new_button );
			}
			do_action( 'ic_after_main_catalog_page_setting_html' );
			?>
			<h4>
				<?php
				$theme = wp_get_theme();
				if ( $theme->exists() ) {
					/* translators: %s: Current theme name. */
					echo esc_html( sprintf( __( 'Catalog Layout Integration with the theme (%s)', 'post-type-x' ), $theme->display( 'Name' ) ) );
				}
				?>
			</h4>
			<?php
			if ( ic_has_listing_shortcode() ) {
				if ( ! is_integraton_file_active() ) {
					/* translators: %s: Product catalog shortcode. */
					$alert_message  = sprintf( __( 'You have to remove the %s from the main catalog page to switch the integration method.', 'post-type-x' ), ic_catalog_shortcode_name() );
					$alert_message .= '\r\n';
					$alert_message .= '\r\n';
					$alert_message .= __( 'How to do it?', 'post-type-x' );
					$alert_message .= '\r\n';
					$alert_message .= '1. ' . __( 'Click the Edit button near the Main Catalog Page option.', 'post-type-x' );
					$alert_message .= '\r\n';
					/* translators: %s: Product catalog shortcode. */
					$alert_message .= '2. ' . sprintf( __( 'Remove the %s on the page edit screen.', 'post-type-x' ), ic_catalog_shortcode_name() );
					$alert_message .= '\r\n';
					/* translators: %s: Product catalog shortcode. */
					$alert_message .= '3. ' . sprintf( __( 'Save the page without the %s', 'post-type-x' ), ic_catalog_shortcode_name() );
					$alert_message .= '\r\n';
					$alert_message .= '4. ' . __( 'Get back here (refresh this page) and switch the layout mode again.', 'post-type-x' );
					?>
					<script>
						jQuery( document ).ready( function () {
							jQuery( 'input[name=ic-fake-empty-feature]' ).click( function () {
								alert( <?php echo wp_json_encode( $alert_message ); ?> );
								jQuery( 'input[name=ic-fake-empty-feature]' ).attr( 'checked', false );
								jQuery( 'input[name=ic-fake-empty-feature]:last-child' ).prop( 'checked', true );
							} );
						} );
					</script>
					<table>
						<?php
						$descriptions = array(
							'simple'   => '<strong>' . __( 'Simple Mode', 'post-type-x' ) . '</strong> (' . __( 'recommended', 'post-type-x' ) . ') - ' . __( 'will use your theme page template with catalog custom styling applied.', 'post-type-x' ),
							'advanced' => '<strong>' . __( 'Advanced Mode', 'post-type-x' ) . '</strong> - ' . __( 'fully customizable layout with the visual configuration wizard.', 'post-type-x' ),
							'theme'    => '<strong>' . __( 'Theme Mode', 'post-type-x' ) . '</strong> - ' . __( 'your theme default template files will be used to display catalog pages.', 'post-type-x' ),
						);
						implecode_settings_radio(
							__( 'Layout mode', 'post-type-x' ),
							'ic-fake-empty-feature',
							'simple',
							array(
								'advanced' => $descriptions['advanced'],
								'theme'    => $descriptions['theme'],
								'simple'   => $descriptions['simple'],
							),
							1,
							__( 'Simple mode is recommended. Choose Advanced Mode if you want more control over the layout or theme mode if your theme should control the layout. ', 'post-type-x' ),
							'<br>',
							'integration-mode-selection'
						);
						?>
					</table>
					<?php
				}
				/* translators: %s: Product catalog shortcode. */
				implecode_info( sprintf( __( 'You are currently using %s on your product listing to integrate the catalog with the theme.', 'post-type-x' ), ic_catalog_shortcode_name() ) . '</p><p>' . sprintf( __( 'If you have any problems with catalog layout, you can remove the %s and use the theme integration wizard to fix it.', 'post-type-x' ), ic_catalog_shortcode_name() ) . '</p>' );
				ic_shortcode_mode_settings();
			} elseif ( ic_is_woo_template_available() ) {
				echo '<p>' . esc_html__( 'If you have any problems with catalog layout, you can use the theme integration wizard to fix it.', 'post-type-x' ) . '</p>';
				echo wp_kses_post( sample_product_button( 'p', __( 'Advanced Mode Visual Wizard', 'post-type-x' ) ) );
			} else {
				$theme        = get_option( 'template' );
				$descriptions = array(
					'simple'   => '<strong>' . __( 'Simple Mode', 'post-type-x' ) . '</strong> (' . __( 'recommended', 'post-type-x' ) . ') - ' . __( 'will use your theme page template with catalog custom styling applied.', 'post-type-x' ),
					'advanced' => '<strong>' . __( 'Advanced Mode', 'post-type-x' ) . '</strong> - ' . __( 'fully customizable layout with the visual configuration wizard.', 'post-type-x' ),
					'theme'    => '<strong>' . __( 'Theme Mode', 'post-type-x' ) . '</strong> - ' . __( 'your theme default template files will be used to display catalog pages.', 'post-type-x' ),
				);
				?>
				<table>
					<?php
					implecode_settings_radio(
						__( 'Layout mode', 'post-type-x' ),
						'archive_multiple_settings[integration_type][' . $theme . ']',
						IC_Catalog_Theme_Integration::get_real_integration_mode(),
						array(
							'advanced' => $descriptions['advanced'],
							'theme'    => $descriptions['theme'],
							'simple'   => $descriptions['simple'],
						),
						1,
						__( 'Simple mode is recommended. Choose Advanced Mode if you want more control over the layout or theme mode if your theme should control the layout. ', 'post-type-x' ),
						'<br>',
						'integration-mode-selection'
					);
					?>
				</table>
				<div class="simple_mode_settings">
					<?php implecode_info( __( 'Use the options below to adjust the output.', 'post-type-x' ), 1, 0, false ); ?>
				</div>
				<div class="advanced_mode_settings" style="display: none">
					<?php
					/* translators: %s: Plugin name. */
					$advanced_mode_intro = sprintf( __( 'In Advanced Mode %s must figure out your theme markup to display products properly.', 'post-type-x' ), esc_html( IC_CATALOG_PLUGIN_NAME ) );
					/* translators: %1$s: Opening guide link. %2$s: Closing guide link. */
					$advanced_mode_guide = sprintf( __( 'If you have access to the server files, you can also use our %1$sTheme Integration Guide%2$s to achieve it quickly.', 'post-type-x' ), '<a href="' . esc_url( 'https://implecode.com/wordpress/product-catalog/theme-integration-guide/#cam=simple-mode&key=general-integration-info' ) . '">', '</a>' );
					/* translators: %s: Product catalog shortcode. */
					$advanced_mode_shortcode = sprintf( __( 'If you have any problems with the integration, please insert %s on any page to display the catalog.', 'post-type-x' ), esc_html( ic_catalog_shortcode_name() ) );
					implecode_info(
						'<p>' . $advanced_mode_intro . '</p>' .
						'<p>' . __( 'Use the button below to begin the easy auto adjustment.', 'post-type-x' ) . '</p>' .
						'<p>' . $advanced_mode_guide . '</p>' .
						'<p>' . $advanced_mode_shortcode . '</p>'
					);
					foreach ( $archive_multiple_settings['integration_type'] as $integration_theme => $value ) {
						if ( $integration_theme === $theme ) {
							continue;
						}
						printf(
							'<input type="hidden" name="%1$s" value="%2$s">',
							esc_attr( 'archive_multiple_settings[integration_type][' . $integration_theme . ']' ),
							esc_attr( $value )
						);
					}
					foreach ( $archive_multiple_settings['container_width'] as $container_width_theme => $value ) {
						if ( $container_width_theme === $theme ) {
							continue;
						}
						printf(
							'<input type="hidden" name="%1$s" value="%2$s">',
							esc_attr( 'archive_multiple_settings[container_width][' . $container_width_theme . ']' ),
							esc_attr( $value )
						);
					}
					foreach ( $archive_multiple_settings['container_bg'] as $container_bg_theme => $value ) {
						if ( $container_bg_theme === $theme ) {
							continue;
						}
						printf(
							'<input type="hidden" name="%1$s" value="%2$s">',
							esc_attr( 'archive_multiple_settings[container_bg][' . $container_bg_theme . ']' ),
							esc_attr( $value )
						);
					}
					foreach ( $archive_multiple_settings['container_padding'] as $container_width_padding => $value ) {
						if ( $container_width_padding === $theme ) {
							continue;
						}
						printf(
							'<input type="hidden" name="%1$s" value="%2$s">',
							esc_attr( 'archive_multiple_settings[container_padding][' . $container_width_padding . ']' ),
							esc_attr( $value )
						);
					}
					?>
					<table class="advanced_mode_settings_hidden">
						<tr>
							<td style="min-width: 250px"></td>
							<td></td>
						</tr>
						<?php
						implecode_settings_number( __( 'Catalog Container Width', 'post-type-x' ), 'archive_multiple_settings[container_width][' . $theme . ']', $archive_multiple_settings['container_width'][ $theme ], '%' );
						implecode_settings_text_color( __( 'Catalog Container Background', 'post-type-x' ), 'archive_multiple_settings[container_bg][' . $theme . ']', $archive_multiple_settings['container_bg'][ $theme ] );
						implecode_settings_number( __( 'Catalog Container Padding', 'post-type-x' ), 'archive_multiple_settings[container_padding][' . $theme . ']', $archive_multiple_settings['container_padding'][ $theme ], 'px' );
						if ( ! defined( 'AL_SIDEBAR_BASE_URL' ) ) {
							implecode_settings_radio(
								__( 'Default Sidebar', 'post-type-x' ),
								'archive_multiple_settings[default_sidebar]',
								$archive_multiple_settings['default_sidebar'],
								array(
									'none'  => __( 'Disabled', 'post-type-x' ),
									'left'  => __( 'Left', 'post-type-x' ),
									'right' => __( 'Right', 'post-type-x' ),
								)
							);
						}
						implecode_settings_checkbox( __( 'Disable Product Name', 'post-type-x' ), 'archive_multiple_settings[disable_name]', $archive_multiple_settings['disable_name'] );
						?>
					</table>
					<?php echo wp_kses_post( sample_product_button( 'p', __( 'Advanced Mode Visual Wizard', 'post-type-x' ) ) ); ?>
				</div>
				<div class="theme_mode_settings" style="display: none">
					<?php
					/* translators: %1$s: Opening guide link. %2$s: Closing guide link. */
					$theme_mode_guide = sprintf( __( 'If you have access to the server files, you can also use our %1$sTheme Integration Guide%2$s to have full control over the layout.', 'post-type-x' ), '<a href="' . esc_url( 'https://implecode.com/wordpress/product-catalog/theme-integration-guide/#cam=simple-mode&key=general-integration-info' ) . '">', '</a>' );
					/* translators: %s: Product catalog shortcode. */
					$theme_mode_shortcode = sprintf( __( 'If you have any problems with the integration, please insert %s on any page to display the catalog.', 'post-type-x' ), esc_html( ic_catalog_shortcode_name() ) );
					implecode_info(
						'<p>' . __( 'In theme mode the catalog will use your theme layout for all the catalog pages.', 'post-type-x' ) . '</p>' .
						'<p>' . $theme_mode_guide . '</p>' .
						'<p>' . $theme_mode_shortcode . '</p>'
					);
					?>
				</div>
				<?php
				ic_shortcode_mode_settings();
			}
			?>
		</div>
		<?php
	} else {
		if ( ! empty( $product_archive ) && 'noid' !== $product_archive ) {
			$url = get_edit_post_link( $product_archive );
		}
		if ( ! empty( $url ) ) {
			/* translators: %1$s: Product catalog shortcode. %2$s: Opening edit link. %3$s: Closing edit link. */
			$message = sprintf( __( 'Add %1$s to the %2$smain catalog page%3$s to have more styling options.', 'post-type-x' ), esc_html( ic_catalog_shortcode_name() ), '<a href="' . esc_url( $url ) . '">', '</a>' );
		} else {
			$url = admin_url( 'post-new.php?post_type=page' );
			/* translators: %1$s: Opening create page link. %2$s: Closing create page link. %3$s: Product catalog shortcode. */
			$message = sprintf( __( '%1$sCreate a main catalog listing page%2$s with %3$s to have more styling options.', 'post-type-x' ), '<a href="' . esc_url( $url ) . '">', '</a>', esc_html( ic_catalog_shortcode_name() ) );
		}
		implecode_info( $message );
	}

	do_action( 'ic_after_layout_integration_setting_html', $archive_multiple_settings );
}

/**
 * Returns main listing table notices.
 *
 * @return array
 */
function ic_epc_general_main_listing_table_notices() {
	if ( ic_check_rewrite_compatibility() ) {
		return array();
	}

	return array(
		array(
			'type'    => 'warning',
			'message' => __( 'It seems that this page is already set to be a listing for different elements. Please change the product listing page to make sure that product pages work fine.<br><br>This is probably caused by other plugin being set to show items on the same page.', 'post-type-x' ),
		),
	);
}

/**
 * Renders the conditional main listing page selector row.
 *
 * @param array $row Callback row data.
 *
 * @return void
 */
function ic_epc_render_main_listing_page_row( $row ) {
	$settings = $row['settings'];

	if ( ! is_advanced_mode_forced() || ic_is_woo_template_available() || is_ic_shortcode_integration() ) {
		return;
	}
	?>
	<tr>
		<td>
			<span title="<?php echo esc_attr__( 'The page where the main product listing shows. Also this page slug will be included in product url.', 'post-type-x' ); ?>" class="dashicons dashicons-editor-help ic_tip"></span>
			<?php esc_html_e( 'Choose Main Listing Page', 'post-type-x' ); ?>:
		</td>
		<td>
			<?php
			if ( 1 === (int) $settings['enable_product_listing'] ) {
				$listing_url = product_listing_url();
				ic_select_page( 'product_archive', __( 'Default', 'post-type-x' ), $settings['product_archive'], true, $listing_url );
			} else {
				ic_select_page( 'product_archive', __( 'Default', 'post-type-x' ), $settings['product_archive'], true );
			}
			?>
		</td>
	</tr>
	<?php
}

/**
 * Renders additional listing page settings rows.
 *
 * @param array $row Callback row data.
 *
 * @return void
 */
function ic_epc_render_product_listing_page_settings_row( $row ) {
	do_action( 'product_listing_page_settings' );
}

/**
 * Returns categories table notices.
 *
 * @return array
 */
function ic_epc_general_categories_table_notices() {
	if ( ic_check_tax_rewrite_compatibility() ) {
		return array();
	}

	return array(
		array(
			'type'    => 'warning',
			'message' => __( 'It seems that this categories parent URL is already set to be a parent for different elements. Please change the Categories Parent URL to make sure that product category pages work fine.<br><br>This is probably caused by other plugin being set to show categories with the same parent.', 'post-type-x' ),
		),
	);
}

/**
 * Renders the category archive URL row.
 *
 * @param array $row Callback row data.
 *
 * @return void
 */
function ic_epc_render_category_archive_url_row( $row ) {
	if ( ! is_ic_permalink_product_catalog() ) {
		return;
	}

	$archive_multiple_settings = $row['archive_multiple_settings'];
	$site_url                  = site_url();
	$urllen                    = strlen( $site_url );
	if ( $urllen > 25 ) {
		$site_url = ic_substr( $site_url, 0, 11 ) . '...' . ic_substr( $site_url, $urllen - 11, $urllen );
	}
	?>
	<tr>
		<td>
			<span title="<?php /* translators: %s: extension name. */ echo esc_attr( sprintf( __( 'By default, the category parent slug cannot be the same as for products. If you want them to be the same, please look for %s extension in the catalog extensions menu.', 'post-type-x' ), 'Smarter Product URLs' ) ); ?>" class="dashicons dashicons-editor-help ic_tip"></span>
			<?php esc_html_e( 'Categories Parent URL', 'post-type-x' ); ?>:
		</td>
		<td class="longer">
			<?php echo esc_html( $site_url ); ?>/<input type="text" name="archive_multiple_settings[category_archive_url]" title="<?php /* translators: %s: product listing slug. */ echo esc_attr( sprintf( __( 'Cannot be the same as product listing page slug (%s).', 'post-type-x' ), get_product_slug() ) ); ?>" id="category_archive_url" value="<?php echo esc_attr( urldecode( sanitize_title( $archive_multiple_settings['category_archive_url'] ) ) ); ?>"/>/<?php esc_html_e( 'category-name', 'post-type-x' ); ?>/
		</td>
	</tr>
	<?php
}

/**
 * Renders additional category settings rows.
 *
 * @param array $row Callback row data.
 *
 * @return void
 */
function ic_epc_render_product_category_settings_row( $row ) {
	do_action( 'product_category_settings', $row['archive_multiple_settings'] );
}

/**
 * Renders additional general settings registered on the legacy hook.
 *
 * @param string $option_group Option group.
 * @param array  $settings Page settings.
 *
 * @return void
 */
function ic_epc_general_settings_page_sections_end( $option_group, $settings ) {
	if ( 'product_settings' !== $option_group || ! has_action( 'general-settings' ) ) {
		return;
	}

	// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Existing hook name retained for compatibility.
	do_action( 'general-settings', $settings['archive_multiple_settings'] );
}

/**
 * Returns shortcode integration mode settings.
 *
 * @return array
 */
function ic_get_shortcode_mode_settings() {
	$settings                                      = get_multiple_settings();
	$settings['shortcode_mode']['show_everywhere'] = isset( $settings['shortcode_mode']['show_everywhere'] ) ? $settings['shortcode_mode']['show_everywhere'] : 0;
	$settings['shortcode_mode']['force_name']      = isset( $settings['shortcode_mode']['force_name'] ) ? $settings['shortcode_mode']['force_name'] : 0;
	$settings['shortcode_mode']['force_category_name'] = isset( $settings['shortcode_mode']['force_category_name'] ) ? $settings['shortcode_mode']['force_category_name'] : 0;
	$settings['shortcode_mode']['move_breadcrumbs']    = isset( $settings['shortcode_mode']['move_breadcrumbs'] ) ? $settings['shortcode_mode']['move_breadcrumbs'] : 0;
	$settings['shortcode_mode']['template']            = isset( $settings['shortcode_mode']['template'] ) ? $settings['shortcode_mode']['template'] : 0;

	return $settings['shortcode_mode'];
}

/**
 * Renders shortcode mode settings fields.
 */
function ic_shortcode_mode_settings() {
	$settings = ic_get_shortcode_mode_settings();
	if ( is_ic_shortcode_integration() ) {
		echo '<table class="simple_mode_settings">';
		implecode_settings_checkbox( __( 'Show main catalog page content everywhere', 'post-type-x' ), 'archive_multiple_settings[shortcode_mode][show_everywhere]', $settings['show_everywhere'], 1, __( 'Check this if you want to display main catalog page content on every catalog page. For example, if you are using page builder on the main catalog page to design your catalog.', 'post-type-x' ) );
		implecode_settings_checkbox( __( 'Force product name display', 'post-type-x' ), 'archive_multiple_settings[shortcode_mode][force_name]', $settings['force_name'], 1, __( 'On some themes, the product name is missing on the product page, so you can use this to restore it. Uncheck this if you see duplicated product name on the product page.', 'post-type-x' ) );
		implecode_settings_checkbox( __( 'Force category name display', 'post-type-x' ), 'archive_multiple_settings[shortcode_mode][force_category_name]', $settings['force_category_name'], 1, __( 'On some themes, the category name is missing on the category page, so you can use this to restore it. Uncheck this if you see duplicated category name on the category page.', 'post-type-x' ) );
		if ( is_ic_breadcrumbs_enabled() ) {
			implecode_settings_checkbox( __( 'Move breadcrumbs to the top', 'post-type-x' ), 'archive_multiple_settings[shortcode_mode][move_breadcrumbs]', $settings['move_breadcrumbs'], 1, __( 'Breadcrumbs will be displayed before the page title. It may require some additional styling.', 'post-type-x' ) );
		}
			do_action( 'ic_shortcode_mode_settings_html', $settings );
			echo '</table>';
	} else {
		foreach ( $settings as $name => $value ) {
			printf(
				'<input type="hidden" name="%1$s" value="%2$s">',
				esc_attr( 'archive_multiple_settings[shortcode_mode][' . $name . ']' ),
				esc_attr( $value )
			);
		}
	}
}

/**
 * Returns the default archive settings.
 *
 * @return array
 */
function get_default_multiple_settings() {
	return array(
		'archive_products_limit'     => 12,
		'category_archive_url'       => 'products-category',
		'enable_product_breadcrumbs' => 0,
		'breadcrumbs_title'          => '',
		'seo_title'                  => '',
		'seo_title_sep'              => 1,
	);
}

/**
 * Returns archive settings.
 *
 * @return array
 */
function get_multiple_settings() {
	$archive_multiple_settings = get_option( 'archive_multiple_settings', get_default_multiple_settings() );
    $default_multiple_settings = get_default_multiple_settings();
	if ( empty( $archive_multiple_settings ) || ! is_array( $archive_multiple_settings ) ) {
		$archive_multiple_settings = $default_multiple_settings;
	}
	foreach ( $archive_multiple_settings as $settings_key => $settings_value ) {
		if ( ! is_array( $settings_value ) ) {
			$archive_multiple_settings[ $settings_key ] = sanitize_text_field( $settings_value );
		}
	}
    foreach ($default_multiple_settings as $default_settings_key => $default_settings_value) {
        if (!isset($archive_multiple_settings[$default_settings_key])) {
            $archive_multiple_settings[$default_settings_key] = $default_settings_value;
        }
    }
	$theme    = get_option( 'template' );
	$prev_int = 'simple';
	if ( ! isset( $archive_multiple_settings['integration_type'] ) || ! is_array( $archive_multiple_settings['integration_type'] ) ) {
		if ( class_exists( 'IC_Catalog_Notices', false ) ) {
			$support_check = IC_Catalog_Notices::theme_support_check();
		}
		if ( ! empty( $support_check[ $theme ] ) ) {
			$prev_int = isset( $archive_multiple_settings['integration_type'] ) ? $archive_multiple_settings['integration_type'] : 'simple';
		}
		$archive_multiple_settings['integration_type'] = array();
	}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading admin preview state.
		$test_advanced = isset( $_GET['test_advanced'] ) ? sanitize_text_field( wp_unslash( $_GET['test_advanced'] ) ) : '';
	if ( is_advanced_mode_forced() || '1' === $test_advanced || 'ok' === $test_advanced ) {
		$archive_multiple_settings['integration_type'][ $theme ] = 'advanced';
	} else {
		$archive_multiple_settings['integration_type'][ $theme ] = isset( $archive_multiple_settings['integration_type'][ $theme ] ) ? $archive_multiple_settings['integration_type'][ $theme ] : $prev_int;
	}

	$archive_multiple_settings['catalog_mode']               = isset( $archive_multiple_settings['catalog_mode'] ) ? $archive_multiple_settings['catalog_mode'] : 'simple';
	$archive_multiple_settings['disable_sku']                = isset( $archive_multiple_settings['disable_sku'] ) ? $archive_multiple_settings['disable_sku'] : '';
	$archive_multiple_settings['disable_mpn']                = isset( $archive_multiple_settings['disable_mpn'] ) ? $archive_multiple_settings['disable_mpn'] : '';
	$archive_multiple_settings['seo_title_sep']              = isset( $archive_multiple_settings['seo_title_sep'] ) ? $archive_multiple_settings['seo_title_sep'] : '';
	$archive_multiple_settings['seo_title']                  = isset( $archive_multiple_settings['seo_title'] ) ? $archive_multiple_settings['seo_title'] : '';
	$archive_multiple_settings['category_archive_url']       = isset( $archive_multiple_settings['category_archive_url'] ) ? $archive_multiple_settings['category_archive_url'] : 'products-category';
	$archive_multiple_settings['category_archive_url']       = empty( $archive_multiple_settings['category_archive_url'] ) ? 'products-category' : $archive_multiple_settings['category_archive_url'];
	$archive_multiple_settings['product_listing_cats']       = isset( $archive_multiple_settings['product_listing_cats'] ) ? $archive_multiple_settings['product_listing_cats'] : 'on';
	$archive_multiple_settings['category_top_cats']          = isset( $archive_multiple_settings['category_top_cats'] ) ? $archive_multiple_settings['category_top_cats'] : 'on';
	$archive_multiple_settings['cat_template']               = isset( $archive_multiple_settings['cat_template'] ) ? $archive_multiple_settings['cat_template'] : 'template';
	$archive_multiple_settings['product_order']              = isset( $archive_multiple_settings['product_order'] ) ? $archive_multiple_settings['product_order'] : 'newest';
	$archive_multiple_settings['catalog_plural']             = ! empty( $archive_multiple_settings['catalog_plural'] ) ? $archive_multiple_settings['catalog_plural'] : DEF_CATALOG_PLURAL;
	$archive_multiple_settings['catalog_singular']           = ! empty( $archive_multiple_settings['catalog_singular'] ) ? $archive_multiple_settings['catalog_singular'] : DEF_CATALOG_SINGULAR;
	$archive_multiple_settings['cat_image_disabled']         = isset( $archive_multiple_settings['cat_image_disabled'] ) ? $archive_multiple_settings['cat_image_disabled'] : '';
	$archive_multiple_settings['container_width']            = isset( $archive_multiple_settings['container_width'] ) ? $archive_multiple_settings['container_width'] : 100;
	$archive_multiple_settings['container_bg']               = isset( $archive_multiple_settings['container_bg'] ) ? $archive_multiple_settings['container_bg'] : '';
	$archive_multiple_settings['container_padding']          = isset( $archive_multiple_settings['container_padding'] ) ? $archive_multiple_settings['container_padding'] : 0;
	$archive_multiple_settings['container_text']             = isset( $archive_multiple_settings['container_text'] ) ? $archive_multiple_settings['container_text'] : '';
	$archive_multiple_settings['disable_name']               = isset( $archive_multiple_settings['disable_name'] ) ? $archive_multiple_settings['disable_name'] : '';
	$archive_multiple_settings['default_sidebar']            = isset( $archive_multiple_settings['default_sidebar'] ) ? $archive_multiple_settings['default_sidebar'] : 'none';
	$archive_multiple_settings['related']                    = isset( $archive_multiple_settings['related'] ) ? $archive_multiple_settings['related'] : 'products';
	$archive_multiple_settings['breadcrumbs_title']          = isset( $archive_multiple_settings['breadcrumbs_title'] ) ? $archive_multiple_settings['breadcrumbs_title'] : $archive_multiple_settings['catalog_plural'];
	$archive_multiple_settings['enable_product_breadcrumbs'] = isset( $archive_multiple_settings['enable_product_breadcrumbs'] ) ? $archive_multiple_settings['enable_product_breadcrumbs'] : '';
	$archive_multiple_settings['enable_structured_data']     = isset( $archive_multiple_settings['enable_structured_data'] ) ? $archive_multiple_settings['enable_structured_data'] : '';

	$prev_container_width   = ! is_array( $archive_multiple_settings['container_width'] ) ? $archive_multiple_settings['container_width'] : 100;
	$prev_container_bg      = ! is_array( $archive_multiple_settings['container_bg'] ) ? $archive_multiple_settings['container_bg'] : '';
	$prev_container_padding = ! is_array( $archive_multiple_settings['container_padding'] ) ? $archive_multiple_settings['container_padding'] : 0;

	if ( ! is_array( $archive_multiple_settings['container_width'] ) ) {
		$archive_multiple_settings['container_width'] = array();
	}
	if ( ! is_array( $archive_multiple_settings['container_bg'] ) ) {
		$archive_multiple_settings['container_bg'] = array();
	}
	if ( ! is_array( $archive_multiple_settings['container_padding'] ) ) {
		$archive_multiple_settings['container_padding'] = array();
	}
	if ( ! is_array( $archive_multiple_settings['container_text'] ) ) {
		$archive_multiple_settings['container_text'] = array();
	}

	if ( ! isset( $archive_multiple_settings['container_width'][ $theme ] ) ) {
		$archive_multiple_settings['container_width'][ $theme ] = $prev_container_width;
	}
	if ( ! isset( $archive_multiple_settings['container_bg'][ $theme ] ) ) {
		$archive_multiple_settings['container_bg'][ $theme ] = $prev_container_bg;
	}
	if ( ! isset( $archive_multiple_settings['container_padding'][ $theme ] ) ) {
		$archive_multiple_settings['container_padding'][ $theme ] = $prev_container_padding;
	}
	if ( ! isset( $archive_multiple_settings['container_text'][ $theme ] ) ) {
		$archive_multiple_settings['container_text'][ $theme ] = $prev_container_padding;
	}

	return apply_filters( 'catalog_multiple_settings', $archive_multiple_settings );
}

/**
 * Returns catalog singular and plural names.
 *
 * @param string|null $which Requested name key.
 *
 * @return array|string
 */
function get_catalog_names( $which = null ) {
	$multiple_settings = get_multiple_settings();
	$names['singular'] = $multiple_settings['catalog_singular'];
	$names['plural']   = $multiple_settings['catalog_plural'];
	$names             = apply_filters( 'product_catalog_names', $names );
	if ( ! empty( $which ) && isset( $names[ $which ] ) ) {
		return $names[ $which ];
	}

	return $names;
}

/**
 * Returns the current integration type.
 *
 * @return string
 */
function get_integration_type() {
	$type = ic_get_global( 'ic_integration_type' );
	if ( empty( $type ) ) {
		$settings = get_multiple_settings();
		$theme    = get_option( 'template' );
		$type     = apply_filters( 'ic_catalog_integration_type', $settings['integration_type'][ $theme ] );
		ic_save_global( 'ic_integration_type', $type );
	}

	return $type;
}

/**
 * Returns product sort options.
 *
 * @return array
 */
function get_product_sort_options() {
	return apply_filters(
		'product_sort_options',
		array(
			'newest'       => __( 'Sort by Newest', 'post-type-x' ),
			'product-name' => __( 'Sort by Name', 'post-type-x' ),
		)
	);
}

/**
 * Returns the main product listing page ID.
 *
 * @return int|string
 */
function get_product_listing_id() {
	if ( get_post_type() ) {
		$cache_key = 'listing_id';
	} else {
		$cache_key = 'pre_listing_id';
	}
	$cached = ic_get_global( $cache_key );
	if ( false !== $cached ) {
		return $cached;
	}
	$product_archive_created = get_option( 'product_archive_page_id', '0' );
	if ( ! empty( $product_archive_created ) && false === get_post_status( $product_archive_created ) ) {
		$product_archive_created = '0';
		update_option( 'product_archive_page_id', $product_archive_created );
	}
	$listing_id        = get_option( 'product_archive', $product_archive_created );
	$return_listing_id = apply_filters( 'product_listing_id', $listing_id );
	ic_save_global( $cache_key, $return_listing_id );

	return $return_listing_id;
}

/**
 * Returns product listing URL
 *
 * @return string
 */
function product_listing_url() {
	$listing_url = '';
	$page_id     = get_product_listing_id();
	if ( 'noid' !== $page_id ) {
		if ( ! empty( $page_id ) ) {
			$listing_url = ic_get_permalink( $page_id );
		}
	}
	if ( empty( $listing_url ) ) {
		$listing_url = get_post_type_archive_link( 'al_product' );
	}

	return apply_filters( 'product_listing_url', $listing_url );
}

/**
 * Returns the product listing page status.
 *
 * @return string
 */
function ic_get_product_listing_status() {
	$page_id = get_product_listing_id();
	if ( 'noid' !== $page_id ) {
		if ( ! empty( $page_id ) ) {
			$status = get_post_status( $page_id );

			return $status;
		}
	}

	return 'publish';
}

/**
 * Returns the product slug.
 *
 * @return string
 */
function get_product_slug() {
	$page_id = get_product_listing_id();
	if ( 'noid' !== $page_id ) {
		$slug = urldecode( untrailingslashit( get_page_uri( $page_id ) ) );
	}
	if ( empty( $slug ) ) {
		$settings = get_multiple_settings();
		$slug     = ic_sanitize_title( $settings['catalog_plural'] );
	}

	return apply_filters( 'product_slug', $slug );
}

/**
 * Sanitizes a title while preserving multibyte characters.
 *
 * @param string $str Source string.
 * @param bool   $keep_percent_sign Whether to preserve percent signs.
 * @param bool   $force_lowercase Whether to lowercase the string.
 * @param bool   $sanitize_slugs Whether to sanitize as a slug.
 *
 * @return string
 */
function ic_sanitize_title( $str, $keep_percent_sign = false, $force_lowercase = true, $sanitize_slugs = true ) {
	if ( ! ic_is_multibyte( $str ) ) {
		return sanitize_title( $str );
	}
		// Remove accents and entities.
	$clean = remove_accents( $str );
	$clean = str_replace( array( '&lt', '&gt', '&amp' ), '', $clean );

	$percent_sign   = ( $keep_percent_sign ) ? '\%' : '';
	$sanitize_regex = "/[^\p{Xan}a-zA-Z0-9{$percent_sign}\/_\.|+, -]/ui";
	$clean          = preg_replace( $sanitize_regex, '', $clean );
	$clean          = ( $force_lowercase ) ? strtolower( $clean ) : $clean;

		// Remove ampersands.
	$clean = str_replace( array( '%26', '&' ), '', $clean );

		// Remove special characters.
	if ( false !== $sanitize_slugs ) {
		$clean = preg_replace( '/[\s|+-]+/', '-', $clean );
		$clean = preg_replace( '/[,]+/', '', $clean );
		$clean = preg_replace( '/([\.]+)(?![a-z]{3,4}$)/i', '', $clean );
		$clean = preg_replace( '/([-\s+]\/[-\s+])/', '-', $clean );
	} else {
		$clean = preg_replace( '/[\s]+/', '-', $clean );
	}

		// Remove widow and duplicated slashes.
	$clean = preg_replace( '/([-]*[\/]+[-]*)/', '/', $clean );
	$clean = preg_replace( '/([\/]+)/', '/', $clean );

		// Trim slashes, dashes, and whitespaces.
	$clean = trim( $clean, ' /-' );

	return $clean;
}

add_action( 'updated_option', 'rewrite_permalinks_after_update', 10, 3 );

/**
 * Flushes rewrite settings after listing changes.
 *
 * @param string $option Updated option name.
 * @param mixed  $old_value Previous value.
 * @param mixed  $new_value New value.
 */
function rewrite_permalinks_after_update( $option, $old_value, $new_value ) {
	if ( 'product_archive' === $option || 'archive_multiple_settings' === $option ) {
		if ( 'product_archive' === $option ) {
			$old_id = intval( $old_value );
			$new_id = intval( $new_value );
			if ( ! empty( $new_id ) && $old_id !== $new_id ) {
				$auto_add = false;

				if ( ! empty( $old_id ) ) {
					$old_post = get_post( $old_id );
					if ( ! empty( $old_post->post_content ) && ic_has_page_catalog_shortcode( $old_post ) ) {
						$auto_add = true;
					}
				} elseif ( ! empty( $new_id ) ) {
					$auto_add = true;
				}
				if ( $auto_add && ! empty( $new_id ) ) {
					$new_post = get_post( $new_id );
					if ( isset( $new_post->post_content ) && ! ic_has_page_catalog_shortcode( $new_post ) ) {
						$new_post->post_content = $new_post->post_content . ic_catalog_shortcode();
						wp_update_post( $new_post );
					}
				}
			}
		}
		permalink_options_update();
	}
}

/**
 * Returns the catalog shortcode label.
 *
 * @return string
 */
function ic_catalog_shortcode_name() {
	return apply_filters( 'ic_catalog_shortcode_name', '[show_product_catalog]' );
}

/**
 * Returns the default catalog shortcode.
 *
 * @return string
 */
function ic_catalog_shortcode() {
	return apply_filters( 'ic_catalog_default_listing_content', '[show_product_catalog]' );
}

/**
 * Returns the create-listing-page button HTML.
 *
 * @return string
 */
function ic_create_new_listing_page() {
	$button = '';
	if ( ! empty( $_GET['create_new_listing_page'] ) ) {
		// Creating the listing page inserts a post, so the request must come from the nonced button.
		check_admin_referer( 'ic_create_new_listing_page' );
		create_products_page();
	}
	$listing_id = get_product_listing_id();
	if ( empty( $listing_id ) || 'noid' === $listing_id ) {
		$button = ' <a class="button button-small" style="vertical-align: middle;" href="' . esc_url( wp_nonce_url( admin_url( 'edit.php?post_type=al_product&page=product-settings.php&create_new_listing_page=1' ), 'ic_create_new_listing_page' ) ) . '">' . esc_html__( 'Create New', 'post-type-x' ) . '</a>';
	}

	return $button;
}

if (!function_exists('general_settings_content')) {
	/**
	 * A backward compatibility function for the general settings content. Can be removed in the future.
	 *
	 * @return void
	 */
    function general_settings_content() {}
}
