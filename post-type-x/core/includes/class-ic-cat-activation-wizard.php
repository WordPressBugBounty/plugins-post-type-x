<?php
/**
 * Activation wizard.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles the catalog activation wizard flow.
 */
class IC_Cat_Activation_Wizard extends IC_Activation_Wizard {

	/**
	 * Whether the notice should be displayed.
	 *
	 * @var bool
	 */
	private $display_notice = false;

	/**
	 * Buffered notice markup.
	 *
	 * @var string
	 */
	private $notices = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'in_admin_header', array( $this, 'disable_welcome_notices' ) );
		add_action( 'ic_epc_mode_selected', array( $this, 'activation_notices' ) );
		add_action( 'product_settings_menu', array( $this, 'welcome_screen_page' ) );
		add_action( 'admin_init', array( $this, 'handle_mode' ) );
		add_action( 'admin_init', array( $this, 'welcome_redirect' ) );
		add_action( 'ic_settings_top', array( $this, 'manage_general_tooltips' ) );
		add_action( 'shopping-cart-settings', array( $this, 'manage_cart_tooltips' ) );
		add_action( 'product-variations-settings', array( $this, 'manage_variations_tooltips' ) );
		add_action( 'product-attributes', array( $this, 'manage_attribute_tooltips' ) );
		add_action( 'front_end_labels_submenu', array( $this, 'manage_labels_tooltips' ) );
		add_action( 'product_listing_front_end_labels_settings', array( $this, 'manage_listing_labels_tooltips' ) );
		add_action( 'custom-design-submenu', array( $this, 'manage_design_tooltips' ) );
		add_action( 'single_product_design', array( $this, 'manage_single_design_tooltips' ) );
		add_action( 'ic_cat_extensions_page_start', array( $this, 'manage_extensions_tooltips' ) );
		add_action( 'ic_extensions_page_help_text', array( $this, 'manage_help_tooltips' ) );
		add_action( 'ic_products_edit_screen', array( $this, 'manage_products_tooltips' ) );
		add_filter( 'admin_product_details', array( $this, 'manage_product_tooltips' ) );
		add_action( 'ic_product_cat_fields', array( $this, 'manage_category_tooltips' ) );
		add_action( 'ic_affiliate_button_settings', array( $this, 'manage_affiliate_button_tooltips' ) );
	}

	/**
	 * Register the welcome screen submenu page.
	 */
	public function welcome_screen_page() {
		add_submenu_page(
			'edit.php?post_type=al_product',
			__( 'Getting Started', 'post-type-x' ),
			__( 'Getting Started', 'post-type-x' ),
			apply_filters( 'see_product_settings_cap', 'manage_product_settings' ),
			'implecode_welcome',
			array(
				$this,
				'welcome_screen_content',
			)
		);
	}

	/**
	 * Render the welcome screen content.
	 */
	public function welcome_screen_content() {
		require_once AL_BASE_PATH . '/includes/welcome/welcome-screen.php';
	}

	/**
	 * Handle the selected catalog mode.
	 */
	public function handle_mode() {
		if ( ! isset( $_GET['page'] ) ) {
			return;
		}
		if ( 'implecode_welcome' !== $_GET['page'] ) {
			return;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'ic_catalog_mode_selection' ) ) {
			return;
		}
		if ( ! empty( $_GET['mode'] ) ) {
			$mode = sanitize_text_field( wp_unslash( $_GET['mode'] ) );
			$this->update_mode( $mode );
			$redirect_url = add_query_arg( 'selected_mode', $mode, admin_url( 'edit.php?post_type=al_product&page=implecode_welcome' ) );
			if ( 'store' === $mode ) {
				$redirect_url = add_query_arg( 'ic_catalog_activation_choice', 'price-on', $redirect_url );
			}
			$redirect_url = add_query_arg( '_wpnonce', wp_create_nonce( 'ic_catalog_activation_choice' ), $redirect_url );

			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Disable regular admin notices on the welcome screen.
	 */
	public function disable_welcome_notices() {
		if ( is_ic_welcome_page() ) {
			remove_all_actions( 'admin_notices' );
		}
	}

	/**
	 * Update the selected catalog mode.
	 *
	 * @param string $mode Selected catalog mode.
	 */
	public function update_mode( $mode ) {
		$settings = get_option( 'archive_multiple_settings', get_default_multiple_settings() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$settings['catalog_mode'] = $mode;
		update_option( 'archive_multiple_settings', $settings );
		$this->update_shipping( $mode );
		$this->update_price( $mode );
		$this->reset_wizard();
		if ( 'store' === $mode ) {
			update_option( 'IC_EPC_activation_message', 0, false );
		}
		$this->add_tooltips( $mode );
	}

	/**
	 * Register onboarding tooltips for the selected mode.
	 *
	 * @param string $mode Selected catalog mode.
	 */
	public function add_tooltips( $mode ) {
		implecode_wp_tooltip_add( __( 'Catalog Configuration', 'post-type-x' ), __( 'Catalog settings are located here. Click on the settings link to finish the catalog configuration.', 'post-type-x' ), 'menu-posts-al_product ul li:nth-child(5)' );
		implecode_wp_tooltip_add( __( 'Important Settings', 'post-type-x' ), __( 'The most important general catalog settings are located here.', 'post-type-x' ), 'general-settings' );
		if ( 'store' === $mode || 'inquiry' === $mode ) {
			implecode_wp_tooltip_add( __( 'Cart Configuration', 'post-type-x' ), __( 'Click here to configure your cart settings, so it works correctly. Select the cart, checkout and thank you page. Set the email address for order notifications.', 'post-type-x' ), 'shopping-cart-settings' );
		}
		if ( 'affiliate' === $mode ) {
			implecode_wp_tooltip_add( __( 'Affiliate Button', 'post-type-x' ), __( 'Click here to configure your button settings, so it works as expected.', 'post-type-x' ), 'affiliate-button-settings' );
		}
	}

	/**
	 * Register general settings tooltips.
	 */
	public function manage_general_tooltips() {
		implecode_wp_tooltip_hide( 'menu-posts-al_product ul li:nth-child(5)' );
		implecode_wp_tooltip_hide( 'general-settings' );
		implecode_wp_tooltip_add( __( 'Catalog Categories', 'post-type-x' ), __( 'Here you can manage categories and subcategories. You can add an unlimited number of categories and multiple levels of subcategories.', 'post-type-x' ), 'al_categories', true );
		implecode_wp_tooltip_add( __( 'Catalog Products', 'post-type-x' ), __( 'Here you can add, edit, and remove products. You can have an unlimited number of products.', 'post-type-x' ), 'al_products', true );
		implecode_wp_tooltip_add( __( 'Catalog Labels', 'post-type-x' ), __( 'Here you can change catalog text output.', 'post-type-x' ), 'names-settings', true );
		implecode_wp_tooltip_add( __( 'Catalog Design', 'post-type-x' ), __( 'Here you can configure the catalog design. Select the listing template and product page template. Change gallery options.', 'post-type-x' ), 'design-settings', true );
		implecode_wp_tooltip_add( __( 'Attributes Configuration', 'post-type-x' ), __( 'Here you can configure the attributes. Set the default values.', 'post-type-x' ), 'attributes-settings', true );
		implecode_wp_tooltip_add( __( 'Catalog Configuration', 'post-type-x' ), __( 'Set the currency custom symbol.', 'post-type-x' ), 'implecode_settings input[name="product_currency_settings[custom_symbol]"]', true );
		implecode_wp_tooltip_add( __( 'Catalog Listing Configuration', 'post-type-x' ), __( 'Use this option to select what should be shown on your main catalog page.', 'post-type-x' ), 'implecode_settings input[name="archive_multiple_settings[product_listing_cats]"]', true );
		implecode_wp_tooltip_add( __( 'Catalog Listing Configuration', 'post-type-x' ), __( 'Use the Show main catalog page content everywhere option if you are using a page builder to build your catalog design.', 'post-type-x' ), 'implecode_settings input[name="archive_multiple_settings[shortcode_mode][show_everywhere]"]', true );
		implecode_wp_tooltip_add( __( 'Catalog Listing Configuration', 'post-type-x' ), __( 'Select catalog template here.', 'post-type-x' ), 'implecode_settings select[name="archive_multiple_settings[shortcode_mode][template]"]', true );
		implecode_wp_tooltip_add( __( 'Catalog Listing Configuration', 'post-type-x' ), __( 'Select or view your main catalog page here', 'post-type-x' ), 'product_archive', true );

		implecode_wp_tooltip_add( __( 'Catalog Addons', 'post-type-x' ), __( 'Here you can find many useful add-ons and integrations.', 'post-type-x' ), 'extensions' );
		implecode_wp_tooltip_add( __( 'Help', 'post-type-x' ), __( 'Here you can can get some help.', 'post-type-x' ), 'help' );
	}

	/**
	 * Hide the attributes settings tooltip after it is shown.
	 */
	public function manage_attribute_tooltips() {
		implecode_wp_tooltip_hide( 'attributes-settings' );
	}

	/**
	 * Register design settings tooltips.
	 */
	public function manage_design_tooltips() {
		implecode_wp_tooltip_hide( 'design-settings' );
		implecode_wp_tooltip_add( __( 'Catalog Design', 'post-type-x' ), __( 'Here you can configure the individual product page design and gallery settings.', 'post-type-x' ), 'single-design', true );
	}

	/**
	 * Hide the single design tooltip after it is shown.
	 */
	public function manage_single_design_tooltips() {
		implecode_wp_tooltip_hide( 'single-design' );
	}

	/**
	 * Register label tooltips.
	 */
	public function manage_labels_tooltips() {
		implecode_wp_tooltip_hide( 'names-settings' );
		implecode_wp_tooltip_add( __( 'Catalog Labels', 'post-type-x' ), __( 'Here you can change the labels that appear on the product listing.', 'post-type-x' ), 'archive-names', true );
	}

	/**
	 * Hide the archive names tooltip after it is shown.
	 */
	public function manage_listing_labels_tooltips() {
		implecode_wp_tooltip_hide( 'archive-names' );
	}

	/**
	 * Register cart settings tooltips.
	 */
	public function manage_cart_tooltips() {
		implecode_wp_tooltip_hide( 'shopping-cart-settings' );
		implecode_wp_tooltip_add( __( 'Variations Configuration', 'post-type-x' ), __( 'Here you can configure the variations.', 'post-type-x' ), 'product-variations-settings', true );
		implecode_wp_tooltip_add( __( 'Cart Configuration', 'post-type-x' ), __( 'Set the notification email address. The email to receive and send notifications can be the same.', 'post-type-x' ), 'implecode_settings input[name="shopping_cart_settings[receive_cart]"]', true );
		implecode_wp_tooltip_add( __( 'Cart Configuration', 'post-type-x' ), __( 'Select your cart pages. Each dropdown should point to a different page.', 'post-type-x' ), 'implecode_settings select[name="shopping_cart_settings[shopping_cart_page]"]', true );
		implecode_wp_tooltip_add( __( 'Product Variations', 'post-type-x' ), __( 'Here you can insert product variations so the user can choose additional product features when buying or asking for a quote.', 'post-type-x' ), 'al_cart_variations' );
	}

	/**
	 * Hide the product variations tooltip after it is shown.
	 */
	public function manage_variations_tooltips() {
		implecode_wp_tooltip_hide( 'product-variations-settings' );
	}

	/**
	 * Hide the extensions tooltip after it is shown.
	 */
	public function manage_extensions_tooltips() {
		implecode_wp_tooltip_hide( 'extensions' );
	}

	/**
	 * Hide the help tooltip after it is shown.
	 */
	public function manage_help_tooltips() {
		implecode_wp_tooltip_hide( 'help' );
	}

	/**
	 * Register product screen tooltips.
	 */
	public function manage_products_tooltips() {
		implecode_wp_tooltip_hide( 'al_products' );
		implecode_wp_tooltip_add( __( 'Add new product', 'post-type-x' ), __( 'Click here to add new product.', 'post-type-x' ), 'add-new-product-page' );
		implecode_wp_tooltip_add( __( 'Add new product', 'post-type-x' ), __( 'Click here to add new product.', 'post-type-x' ), 'menu-posts-al_product ul li:nth-child(3)' );
		implecode_wp_tooltip_add( __( 'Products', 'post-type-x' ), __( 'Here all the products will show up. Use the Add new product button on the top to add a product.', 'post-type-x' ), 'posts-filter .wp-list-table #title' );
		implecode_wp_tooltip_add( __( 'Help Docs', 'post-type-x' ), __( 'Search for help here.', 'post-type-x' ), 'implecode_settings input[name="ic-settings-search"]' );
		implecode_wp_tooltip_add( __( 'Help from devs', 'post-type-x' ), __( 'Use the forum to get help on the integration, configuration, or any problem that you face.', 'post-type-x' ), 'implecode_settings .ic-settings-search .button-secondary' );
	}

	/**
	 * Register product editor tooltips.
	 *
	 * @param mixed $product_details Product details payload.
	 *
	 * @return mixed
	 */
	public function manage_product_tooltips( $product_details ) {
		implecode_wp_tooltip_hide( 'add-new-product-page' );
		implecode_wp_tooltip_hide( 'menu-posts-al_product ul li:nth-child(3)' );
		implecode_wp_tooltip_add( __( 'Publish', 'post-type-x' ), __( 'Save the product so you can see how it shows up on the website.', 'post-type-x' ), 'publishing-action', true );
		implecode_wp_tooltip_add( __( 'Categories', 'post-type-x' ), __( 'Assign or add the product categories and subcategories here.', 'post-type-x' ), 'al_product-catdiv', true );
		implecode_wp_tooltip_add( __( 'Product Image', 'post-type-x' ), __( 'Here you can insert the product image.', 'post-type-x' ), 'postimagediv', true );
		implecode_wp_tooltip_add( __( 'Attributes', 'post-type-x' ), __( 'Here you can insert product attributes like color, size, or any other feature that the product has. With some add-ons, you will be able to sort, filter, and search products by these values.', 'post-type-x' ), 'al_product_attributes table.attributes', true );
		implecode_wp_tooltip_add( __( 'Media', 'post-type-x' ), __( 'Here you can add images, videos, or any other media to the description.', 'post-type-x' ), 'al_product_desc #wp-content-media-buttons button', true );
		implecode_wp_tooltip_add( __( 'Description', 'post-type-x' ), __( 'Insert the product description here. It will show up in a separate section or tab in the middle of the product page.', 'post-type-x' ), 'al_product_desc #content_ifr', true );
		implecode_wp_tooltip_add( __( 'Short Description', 'post-type-x' ), __( 'Insert the product short description here. It will show up on the top of the product page.', 'post-type-x' ), 'al_product_short_desc #excerpt_ifr', true );
		implecode_wp_tooltip_add( __( 'Product name', 'post-type-x' ), __( 'Insert the product name here.', 'post-type-x' ), 'post-body-content #titlewrap', true );

		return $product_details;
	}

	/**
	 * Hide the categories tooltip after it is shown.
	 */
	public function manage_category_tooltips() {
		implecode_wp_tooltip_hide( 'al_categories' );
	}

	/**
	 * Register affiliate button tooltips.
	 */
	public function manage_affiliate_button_tooltips() {
		implecode_wp_tooltip_hide( 'affiliate-button-settings' );
		implecode_wp_tooltip_add( __( 'Button URL', 'post-type-x' ), __( 'Check this checkbox if you want to define a separate button URL for each product.', 'post-type-x' ), 'implecode_settings input[name="ic_catalog_button[individual]"]', true );
	}

	/**
	 * Enable the price configuration for the selected mode.
	 *
	 * @param string $mode Selected catalog mode.
	 */
	public function update_price( $mode ) {
		if ( ! function_exists( 'get_currency_settings' ) ) {
			$this->add_recommended_extension( 'price-field' );

			return;
		}
		$currency_settings                 = get_currency_settings();
		$currency_settings['price_enable'] = 'on';
		update_option(
			'product_currency_settings',
			$currency_settings
		);
	}

	/**
	 * Toggle shipping support for the selected mode.
	 *
	 * @param string $mode Selected catalog mode.
	 */
	public function update_shipping( $mode ) {
		if ( ! function_exists( 'is_ic_shipping_enabled' ) ) {
			return;
		}
		$enable_shipping = false;
		if ( 'store' === $mode ) {
			$enable_shipping = true;
		}
		if ( $enable_shipping && ! is_ic_shipping_enabled() ) {
			update_option( 'product_shipping_options_number', 2 );
		} else {
			update_option( 'product_shipping_options_number', 0 );
		}
	}

	/**
	 * Reset wizard-related options.
	 */
	public function reset_wizard() {
		update_option( 'IC_EPC_activation_message', 1, false );
		delete_option( 'ic_cat_wizard_woo_choice' );
		delete_option( 'ic_hidden_notices' );
		delete_option( 'ic_hidden_boxes' );
		delete_option( 'ic_cat_recommended_extensions' );
		delete_option( 'implecode_wp_hidden_tooltips' );
		delete_option( 'implecode_wp_tooltips' );
	}

	/**
	 * Buffer activation notices for later output.
	 */
	public function notices() {
		ob_start();
		$this->activation_notices();
		$this->notices = ob_get_clean();
		if ( $this->display_notice ) {
			remove_all_actions( 'ic_catalog_admin_notices' );
		}
		add_action( 'ic_catalog_admin_priority_notices', array( $this, 'activation_notices' ), -1 );
	}

	/**
	 * Output activation notices when needed.
	 */
	public function activation_notices() {
		if ( ! empty( $this->notices ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: HTML assembled earlier in this scope, so escaping it here would print the tags.
			echo $this->notices;

			return;
		}
		$this->display_notice = false;
		if ( is_ic_activation_notice() && $this->get_notice_status( 'notice-ic-catalog-activation' ) ) {
			delete_option( 'IC_EPC_activation_message' );
		}
		$response = array();
		if ( is_ic_activation_notice() && ! $this->get_notice_status( 'notice-ic-catalog-activation' ) ) {
			$this->display_notice = true;
			$not_complete         = true;
			$response             = $this->get_choice_response();
			$questions            = $this->response_to_question( $response );
			if ( ! empty( $questions ) ) {
				$header_name   = IC_CATALOG_PLUGIN_NAME;
				$selected_mode = filter_input( INPUT_GET, 'selected_mode', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				if ( is_ic_welcome_page() && ! empty( $selected_mode ) ) {
					$header_name = '';
				}
				if ( ! empty( $header_name ) ) {
					/* translators: %s: plugin name. */
					$this->box_header( sprintf( __( '%s is active now!', 'post-type-x' ), $header_name ) );
				}
				if ( empty( $response['question'] ) ) {
					$this->box_paragraph( __( 'Make a choice below to continue with 1-minute catalog setup.', 'post-type-x' ) );
				}
			}
			if ( ! empty( $response['question'] ) ) {
				$this->box_paragraph( $response['question'] );
			}
			$form = false;
			if ( 1 === count( $questions ) ) {
				$form = $response['next_one'];
			}

			if ( empty( $questions ) ) {
				update_option( 'IC_EPC_activation_message_done', 1, false );
				$not_complete = false;

				$complete = apply_filters( 'ic_cat_activation_wizard_complete', true, $questions, $response );
				if ( $complete ) {
					if ( $this->any_recommended_extensions() && ! $this->get_notice_status( 'notice-ic-catalog-recommended' ) ) {
						if ( $this->show_woocommerce_notice() && 'woo-design' === $this->get_woo_choice() ) {
							remove_action(
								'ic_cat_activation_wizard_bottom',
								array(
									'IC_Catalog_Notices',
									'getting_started_docs_info',
								)
							);
						}
						$this->recommended_extensions_box( false );
					} else {
						delete_option( 'IC_EPC_activation_message' );
						delete_option( 'IC_EPC_activation_message_done' );
						$catalog_names = get_catalog_names();
						/* translators: %s: catalog plural label. */
						$this->box_header( sprintf( __( "Congratulations! Your're ready to add %s.", 'post-type-x' ), $catalog_names['plural'] ) );

						$questions = array(
							/* translators: %s: catalog singular label. */
							admin_url( 'post-new.php?post_type=al_product' )                                                        => sprintf( __( 'Add First %s', 'post-type-x' ), $catalog_names['singular'] ),
							/* translators: %s: catalog plural label. */
							admin_url( 'edit.php?post_type=al_product&page=product-settings.php&tab=product-settings&submenu=csv' ) => sprintf( __( 'Import %s', 'post-type-x' ), $catalog_names['plural'] ),
						);
					}
				}
			}
			if ( ! empty( $questions ) ) {
				$this->box_choice( $questions, $form );
			}
			if ( ! empty( $not_complete ) ) {
				$this->box_paragraph( __( 'You will be able to change your choice later in catalog settings or by reactivating the plugin.', 'post-type-x' ), true );
			}
			$this->wizard_box();
		} elseif ( is_ic_catalog_admin_page() && $this->any_recommended_extensions() && ! $this->get_notice_status( 'notice-ic-catalog-recommended' ) ) {
			$this->display_notice = true;
			$this->recommended_extensions_box();
		} elseif ( is_ic_new_product_screen() ) {
			$count         = ic_products_count();
			$sample_exists = ic_sample_page_exists();
			if ( ( $sample_exists && 1 === $count ) || ( ! $sample_exists && empty( $count ) ) ) {
				$this->display_notice = true;
				$catalog_names        = get_catalog_names();
				/* translators: %s: catalog singular label. */
				$this->box_header( sprintf( __( 'Add your first %s here.', 'post-type-x' ), $catalog_names['singular'] ) );
				$this->box_paragraph( __( 'By default you should see a two-column layout here:', 'post-type-x' ) );
				$optional   = '( ' . __( 'optional', 'post-type-x' ) . ' )';
				$left_side  = __( 'on the left side', 'post-type-x' );
				$right_side = __( 'on the right side', 'post-type-x' );
				$strong_op  = '<strong>';
				$strong_cl  = '</strong>';
				$list       = array(
					$strong_op . __( 'Name field', 'post-type-x' ) . $strong_cl . ' - ' . $left_side,
					$strong_op . __( 'Short description field', 'post-type-x' ) . $strong_cl . ' - ' . $left_side,
					$strong_op . __( 'Long Description field', 'post-type-x' ) . $strong_cl . ' - ' . $left_side,
					$strong_op . __( 'Attributes box', 'post-type-x' ) . $strong_cl . ' ' . $optional . ' - ' . $left_side . ' - ' . __( 'you can define their number in catalog settings.', 'post-type-x' ),
					$strong_op . __( 'Image box', 'post-type-x' ) . $strong_cl . ' - ' . $right_side,
					$strong_op . __( 'Publish box', 'post-type-x' ) . $strong_cl . ' - ' . $right_side,
					$strong_op . __( 'Categories box', 'post-type-x' ) . $strong_cl . ' - ' . $right_side,
					$strong_op . __( 'Price & SKU box', 'post-type-x' ) . $strong_cl . ' ' . $optional . ' - ' . $right_side,
				);
				$this->box_list( $list );
				$this->box_paragraph( __( 'You can move all the boxes around to better suit your needs.', 'post-type-x' ) );
				$this->box_paragraph( __( 'This help box will disappear once you add your first product.', 'post-type-x' ), true );
				$this->wizard_box( '', 'style = "text-align: left;"' );
			}
		} elseif ( is_ic_edit_product_screen() && ! $this->get_notice_status( 'notice-ic-catalog-activation' ) ) {
			$product_id = get_the_ID();
			if ( ! empty( $product_id ) && is_ic_product( $product_id ) && ic_product_exists( $product_id ) ) {
				$product_url          = get_permalink( $product_id );
				$url                  = admin_url( 'customize.php?autofocus[ control ] = ic_pc_integration_template  &url = ' . rawurldecode( $product_url ) . '&return = ' . rawurldecode( admin_url( 'edit.php?post_type = al_product' ) ) );
				$this->display_notice = true;
				$catalog_names        = get_catalog_names();
				/* translators: %s: catalog singular label. */
				$this->box_header( sprintf( __( "Let's customize your %s page layout.", 'post-type-x' ), $catalog_names['singular'] ) );
				$questions = array(
					/* translators: %s: catalog singular label. */
					$url => sprintf( __( 'Customize %s Page Layout', 'post-type-x' ), $catalog_names['singular'] ),
				);
				$this->box_choice( $questions );
				$this->wizard_box( 'notice-ic-catalog-activation' );
			}
		} elseif ( is_ic_product_list_admin_screen() && ! $this->get_notice_status( 'notice-ic-catalog-activation' ) ) {
			$this->display_notice = true;
			$listing_id           = intval( get_product_listing_id() );
			$catalog_names        = get_catalog_names();

			if ( ! empty( $listing_id ) ) {
				$listing_url = get_permalink( $listing_id );
				$url         = admin_url( 'customize.php?autofocus[control]=ic_pc_archive_template&url=' . rawurlencode( $listing_url ) . '&return=' . rawurlencode( admin_url( 'edit.php?post_type=al_product&page=product-settings.php' ) ) );
				/* translators: %s: catalog singular label. */
				$message            = sprintf( __( 'Your main %s listing page is defined.', 'post-type-x' ), $catalog_names['singular'] );
				$message           .= ' ' . __( 'Template files will be used to display catalog pages.', 'post-type-x' );
				$button_label       = __( 'Customize Listing Layout', 'post-type-x' );
				$url_two            = admin_url( 'post.php?post=' . $listing_id . '&action=edit' );
				$button_label_two   = __( 'Edit Main Listing', 'post-type-x' );
				$url_three          = $listing_url;
				$button_label_three = __( 'Visit Main Listing', 'post-type-x' );
			} else {
				/* translators: %s: catalog singular label. */
				$message = sprintf( __( 'Your main %s listing page is not selected. You can use shortcodes to display your products, but in most cases, it will be more convenient to use the templates.', 'post-type-x' ), $catalog_names['singular'] );
				$url     = admin_url( 'edit.php?post_type=al_product&page=product-settings.php' );
				/* translators: %s: catalog singular label. */
				$button_label = sprintf( __( 'Select Main %s Listing Page', 'post-type-x' ), $catalog_names['singular'] );
			}
			$this->box_paragraph( $message );
			$questions = array(
				$url => $button_label,
			);
			if ( ! empty( $button_label_two ) && ! empty( $url_two ) ) {
				$questions[ $url_two ] = $button_label_two;
			}
			if ( ! empty( $button_label_three ) && ! empty( $url_three ) ) {
				$questions[ $url_three ] = $button_label_three;
			}
			$this->box_choice( $questions );
			$this->wizard_box( 'notice-ic-catalog-activation' );
		} elseif ( is_ic_catalog_admin_page() && ! $this->get_notice_status( 'notice-ic-catalog-activation' ) ) {
			$this->display_notice = true;

			$this->box_header( __( 'Great! It looks like you are good to go with your catalog adventure.', 'post-type-x' ) );
			/* translators: 1: opening support link, 2: closing support link. */
			$this->box_paragraph( sprintf( __( 'If you have any questions or issues, feel free to post a %1$ssupport ticket%2$s.', 'post-type-x' ), '<a href="https://implecode.com/support/#cam=simple-mode&key=support-top">', '</a>' ) );
			/* translators: 1: opening theme link, 2: closing theme link. */
			$this->box_paragraph( sprintf( __( 'If you are looking for a customizable product theme, the free %1$sCatalog Me! theme%2$s is the way to go.', 'post-type-x' ), '<a href="' . admin_url( 'theme-install.php?search=Catalog+me%21' ) . '">', '</a>' ) );
			$this->box_paragraph( __( 'Make sure to visit the documentation for more tweaks and tricks.', 'post-type-x' ) );
			$questions  = array(
				'https://implecode.com/docs/ecommerce-product-catalog/getting-started/#cam=default-mode&key=getting-started' => __( 'Getting Started Guide', 'post-type-x' ),
				'https://implecode.com/docs/ecommerce-product-catalog/#cam=default-mode&key=docs'                            => __( 'Documentation', 'post-type-x' ),
			);
			$listing_id = intval( get_product_listing_id() );
			if ( ! empty( $listing_id ) ) {
				$listing_url               = get_permalink( $listing_id );
				$questions[ $listing_url ] = __( 'Main Catalog Listing', 'post-type-x' );
			}
			$this->box_choice( apply_filters( 'ic_cat_activation_wizard_final_questions', $questions ) );

			$this->wizard_box( 'notice-ic-catalog-activation' );
		}
	}

	/**
	 * Check whether the activation message flow is complete.
	 *
	 * @return bool
	 */
	public function activation_message_done() {
		$done = get_option( 'IC_EPC_activation_message_done', 0 );
		if ( ! empty( $done ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Build the response for the current wizard choice.
	 *
	 * @param string|null $answer Current answer key.
	 *
	 * @return array|string
	 */
	public function get_choice_response( $answer = null ) {
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'ic_catalog_activation_choice' ) ) {
			return 'Missing nonce';
		}

		$selected_mode = isset( $_GET['selected_mode'] ) ? sanitize_text_field( wp_unslash( $_GET['selected_mode'] ) ) : '';
		$response      = array();
		if ( $this->activation_message_done() ) {
			$answer = 'complete';
		} elseif ( empty( $answer ) && ! empty( $_GET['ic_catalog_activation_choice'] ) ) {
			$answer = sanitize_text_field( wp_unslash( $_GET['ic_catalog_activation_choice'] ) );
		}
		switch ( $answer ) {
			case 'price-on':
				if ( function_exists( 'get_currency_settings' ) ) {
					$currency_settings = get_currency_settings();
				} else {
					$this->add_recommended_extension( 'price-field' );
				}
				if ( ! empty( $_GET['product_currency'] ) ) {
					if ( function_exists( 'get_currency_settings' ) ) {
						$currency_code = sanitize_text_field( wp_unslash( $_GET['product_currency'] ) );
						update_option( 'product_currency', $currency_code );
						$symbol = ic_cat_get_currency_symbol( $currency_code );
						if ( ! empty( $symbol ) ) {
							$currency_settings['custom_symbol'] = $symbol;
						}
					}
					if ( ! empty( $selected_mode ) ) {
						$response = $this->catalog_names_choice_response();
					} else {
						$response = $this->shipping_choice_response();
					}
				} elseif ( function_exists( 'get_currency_settings' ) ) {
					$currency_settings['price_enable'] = 'on';
					$response['one']                   = ic_cat_get_currency_switcher();
					$response['next_one']              = 'price-on';
					$response['question']              = __( 'Select your currency. You can also set a custom currency in catalog settings later.', 'post-type-x' );
				} elseif ( ! empty( $selected_mode ) ) {
					$response = $this->catalog_names_choice_response();
				} else {
					$response = $this->shipping_choice_response();
				}
				if ( function_exists( 'get_currency_settings' ) ) {
					update_option( 'product_currency_settings', $currency_settings );
				}
				break;
			case 'price-off':
				if ( function_exists( 'get_currency_settings' ) ) {
					$currency_settings                 = get_currency_settings();
					$currency_settings['price_enable'] = 'off';
					update_option( 'product_currency_settings', $currency_settings );
				}
				if ( ! empty( $selected_mode ) ) {
					$response = $this->catalog_names_choice_response();
				} else {
					$response = $this->shipping_choice_response();
				}
				break;
			case 'shipping-on':
				if ( function_exists( 'is_ic_shipping_enabled' ) ) {
					update_option( 'product_shipping_options_number', 1 );
				}
				$response = $this->catalog_names_choice_response();
				$this->add_recommended_extension( 'shipping-options' );

				break;
			case 'woo-design':
				$this->add_recommended_extension( 'catalog-booster-for-woocommerce' );
				update_option( 'ic_cat_wizard_woo_choice', 'woo-design' );

				break;
			case 'woo-separate':
				$response = $this->price_choice_response();
				update_option( 'ic_cat_wizard_woo_choice', 'woo-separate' );

				break;
			case 'shipping-off':
				if ( function_exists( 'is_ic_shipping_enabled' ) ) {
					update_option( 'product_shipping_options_number', 0 );
				}
				$response = $this->catalog_names_choice_response();
				break;
			case 'complete':
				if ( ! empty( $_GET['catalog_singular'] ) || ! empty( $_GET['catalog_plural'] ) ) {
					$archive_multiple_settings = get_multiple_settings();
					if ( ! empty( $_GET['catalog_singular'] ) ) {
						$archive_multiple_settings['catalog_singular'] = sanitize_text_field( wp_unslash( $_GET['catalog_singular'] ) );
					}
					if ( ! empty( $_GET['catalog_plural'] ) ) {
						$archive_multiple_settings['catalog_plural'] = sanitize_text_field( wp_unslash( $_GET['catalog_plural'] ) );
					}
					update_option( 'archive_multiple_settings', $archive_multiple_settings );
				}
				break;
			default:
				$response = apply_filters( 'ic_cat_activation_wizard_default_response', false );
				if ( ! $response ) {
					if ( $this->show_woocommerce_notice() ) {
						remove_action(
							'ic_cat_activation_wizard_bottom',
							array(
								'IC_Catalog_Notices',
								'getting_started_docs_info',
							)
						);
						$response = $this->woo_choice_response();
					} else {
						$response = $this->price_choice_response();
					}
				}
		}

		return $response;
	}

	/**
	 * Build the shipping choice response.
	 *
	 * @return array
	 */
	public function shipping_choice_response() {
		$response['one']      = __( 'Shipping enabled for all or some products', 'post-type-x' );
		$response['next_one'] = 'shipping-on';
		$response['two']      = __( 'Shipping disabled completely', 'post-type-x' );
		$response['next_two'] = 'shipping-off';

		return $response;
	}

	/**
	 * Build the price choice response.
	 *
	 * @return array
	 */
	public function price_choice_response() {
		$response['one']      = __( 'Price enabled for all or some products', 'post-type-x' );
		$response['next_one'] = 'price-on';
		$response['two']      = __( 'Price disabled completely', 'post-type-x' );
		$response['next_two'] = 'price-off';

		return $response;
	}

	/**
	 * Build the WooCommerce choice response.
	 *
	 * @return array
	 */
	public function woo_choice_response() {
		$response['one']      = __( 'Create Separate Catalog', 'post-type-x' );
		$response['next_one'] = 'woo-separate';
		/* translators: %s: WooCommerce brand name. */
		$response['two']      = sprintf( __( 'Modify %s Design', 'post-type-x' ), 'WooCommerce' );
		$response['next_two'] = 'woo-design';
		/* translators: %s: WooCommerce brand name. */
		$response['question'] = sprintf( __( 'It looks like you also have %s active. Make a choice below for correct setup.', 'post-type-x' ), 'WooCommerce' );
		/* translators: %s: catalog plugin name. */
		$response['question'] .= '<br><br>' . sprintf( __( 'I would like to use %s to:', 'post-type-x' ), IC_CATALOG_PLUGIN_NAME );

		return $response;
	}

	/**
	 * Build the catalog names choice response.
	 *
	 * @return array
	 */
	public function catalog_names_choice_response() {
		$archive_multiple_settings = get_multiple_settings();
		$one                       = '<table style="margin:0 auto;"><tr>';
		$one                      .= implecode_settings_text( __( 'Catalog Singular Name', 'post-type-x' ), 'catalog_singular', $archive_multiple_settings['catalog_singular'], null, 0, null, __( 'Admin panel customisation setting. Change it to what you sell.', 'post-type-x' ) . ' ' . __( 'Examples: Service, Part, Flower, Photo', 'post-type-x' ) );
		$one                      .= implecode_settings_text( __( 'Catalog Plural Name', 'post-type-x' ), 'catalog_plural', $archive_multiple_settings['catalog_plural'], null, 0, null, __( 'Admin panel customisation setting. Change it to what you sell.', 'post-type-x' ) . ' ' . __( 'Examples: Services, Parts, Flowers, Photos', 'post-type-x' ) );
		$one                      .= '</tr></table>';
		$response['one']           = $one;
		$response['next_one']      = 'complete';
		$response['question']      = __( 'How would you like to name the products section in admin?', 'post-type-x' );
		$response['question']     .= '<br>' . __( 'This will personalize the admin experience. Leave the default values if you are not sure.', 'post-type-x' );

		return $response;
	}

	/**
	 * Get the stored WooCommerce integration choice.
	 *
	 * @return mixed
	 */
	public static function get_woo_choice() {
		$choice = get_option( 'ic_cat_wizard_woo_choice' );

		return $choice;
	}

	/**
	 * Redirect to the welcome screen after activation.
	 */
	public function welcome_redirect() {
		if ( ! get_transient( '_ic_welcome_screen_activation_redirect' ) ) {
			return;
		}

		delete_transient( '_ic_welcome_screen_activation_redirect' );

		if ( is_network_admin() || filter_input( INPUT_GET, 'activate-multi', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'edit.php?post_type=al_product&page=implecode_welcome' ) );
		exit;
	}
}

if ( ! class_exists( 'ic_cat_activation_wizard', false ) ) {
	class_alias( 'IC_Cat_Activation_Wizard', 'ic_cat_activation_wizard' );
}
