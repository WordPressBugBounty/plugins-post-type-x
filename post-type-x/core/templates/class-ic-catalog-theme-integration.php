<?php
/**
 * Theme integration helpers.
 *
 * @version 1.1.2
 * @package ecommerce-product-catalog/templates
 * @author  impleCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles theme integration checks and the integration wizard.
 */
class IC_Catalog_Theme_Integration {

	/**
	 * Registers theme integration hooks.
	 */
	public function __construct() {
		add_shortcode( 'theme_integration', array( __CLASS__, 'theme_integration_shortcode' ) );

		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Hooks the runtime integration callbacks.
	 */
	public function init() {
		// The optional footer-based wizard output remains disabled.
		add_action( 'after_product_page', array( __CLASS__, 'theme_integration_wizard' ) );
		add_action( 'wp_ajax_save_wizard', array( __CLASS__, 'save_wizard' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_sample_product_scripts' ), 100 );
		add_action( 'switch_theme', array( __CLASS__, 'erase_integration_type_select' ) );
		add_action( 'admin_init', array( __CLASS__, 'create_sample_product_with_redirect' ) );
	}

	/**
	 * Theme integration shortcode placeholder.
	 */
	public static function theme_integration_shortcode() {
	}

	/**
	 * Returns the `test_advanced` wizard steps that write the integration mode option.
	 *
	 * Every step listed here must be triggered from a nonced link.
	 *
	 * @return string[]
	 */
	public static function mode_switching_wizard_steps() {
		return array( 'ok', 'bad', 'simple' );
	}

	/**
	 * Returns a nonced wizard step URL.
	 *
	 * @param string $step Wizard step passed as the `test_advanced` query arg.
	 * @return string Escaped URL.
	 */
	public static function wizard_step_url( $step ) {
		$url = add_query_arg( 'test_advanced', $step );
		if ( in_array( (string) $step, self::mode_switching_wizard_steps(), true ) ) {
			$url = wp_nonce_url( $url, 'ic_test_advanced' );
		}

		return esc_url( $url );
	}

	/**
	 * Shows the theme integration wizard.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return void
	 */
	public static function theme_integration_wizard( $atts ) {
		$current_mode  = self::get_real_integration_mode();
		$test_advanced = isset( $_GET['test_advanced'] ) ? sanitize_text_field( wp_unslash( $_GET['test_advanced'] ) ) : '';
		if ( is_ic_integration_wizard_page() ) {
			if ( in_array( $test_advanced, self::mode_switching_wizard_steps(), true ) ) {
				// These steps write the integration mode option, so they require a valid nonce.
				check_admin_referer( 'ic_test_advanced' );
			}
			$args            = shortcode_atts(
				array(
					'class' => 'fixed-box',
				),
				$atts
			);
				$class       = esc_attr( $args['class'] );
				$box_content = '<h4>' . __( 'Catalog Configuration', 'post-type-x' ) . '</h4>';
				// The optional scroll helper remains disabled in this wizard.
			if ( '' === $test_advanced ) {
				if ( is_ic_shortcode_integration() ) {
					/* translators: %s: catalog plugin name. */
					$box_content .= '<p>' . sprintf( __( '%s is currently running in Shortcode Mode.', 'post-type-x' ), IC_CATALOG_PLUGIN_NAME ) . '</p>';
					$box_content .= '<p>' . __( 'In Shortcode Mode all the catalog features work fine however your theme might display some unwanted elements on catalog pages.', 'post-type-x' ) . '</p>';
				} elseif ( is_ic_simple_mode() ) {
					/* translators: %s: catalog plugin name. */
					$box_content .= '<p>' . sprintf( __( '%s is currently running in Simple Mode.', 'post-type-x' ), IC_CATALOG_PLUGIN_NAME ) . '</p>';
					$box_content .= '<p>' . __( 'In Simple Mode the product listing, product search and category pages are disabled (please read this Sample Product Page to understand the difference fully).', 'post-type-x' ) . '</p>';
				} else {
					/* translators: %s: catalog plugin name. */
					$box_content .= '<p>' . sprintf( __( '%s is currently running in Advanced Mode.', 'post-type-x' ), IC_CATALOG_PLUGIN_NAME ) . '</p>';
				}

				$box_content .= '<p>' . __( 'Please use the button below to check out how the product page looks in Automatic Advanced Mode.', 'post-type-x' ) . '</p>';
				$box_content .= '<p>' . __( 'The layout might look like broken at first; however, you will be able to adjust it.', 'post-type-x' ) . '</p>';
				$box_content .= '<p class="wp-core-ui"><a href="' . self::wizard_step_url( '1' ) . '" class="button-primary">' . __( 'Start Advanced Mode Test', 'post-type-x' ) . '</a>';
				if ( is_ic_shortcode_integration( null, false ) ) {
					$box_content .= '<a href="' . self::wizard_step_url( 'simple' ) . '" class="button-secondary">' . __( 'Use Shortcode Mode', 'post-type-x' ) . '</a></p>';
				} elseif ( is_ic_simple_mode() ) {
					$box_content .= '<a href="' . self::wizard_step_url( 'simple' ) . '" class="button-secondary">' . __( 'Use Simple Mode', 'post-type-x' ) . '</a></p>';
				}
				if ( 'simple' === $current_mode ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: built by implecode_info(), a framework notice box.
					echo '<div id="integration_wizard" class="' . $class . '">' . implecode_info( $box_content, 0 ) . '</div>';
				}
			} elseif ( '1' === $test_advanced ) {
				// The optional opacity preview remains disabled here.
				if ( is_ic_shortcode_integration( null, false ) ) {
					/* translators: %s: shortcode tag used to restore the catalog listing. */
					$box_content .= '<p class="initial-description">' . __( 'Would you like to remove the shortcode integration and try the configuration wizard?', 'post-type-x' ) . '</p><p><strong>' . sprintf( __( 'If something goes wrong you will have to add %s to the product listing again.', 'post-type-x' ), '[' . ic_catalog_shortcode_name() . ']' ) . '</strong></p><p><strong>' . __( 'Do it only if the catalog layout doesn\'t match your theme or if some unwanted elements from the theme are displayed on catalog pages.', 'post-type-x' ) . '</strong></p>';
					$box_content .= '<p class="wp-core-ui" style="margin-top: 20px;"><a href="' . esc_url( wp_nonce_url( add_query_arg( 'remove_shortcode_integration', '1' ), 'ic_remove_shortcode_integration' ) ) . '" class="button-primary">' . __( 'Remove the Shortcode and Start the Wizard', 'post-type-x' ) . '</a></p>';
				} else {
					$box_content .= '<p class="initial-description">' . __( 'Advanced Mode is temporary enabled for this page now.', 'post-type-x' ) . ' <strong>' . __( "Don't worry if it looks messy.", 'post-type-x' ) . '</strong> ' . __( 'Make some adjustments if necessary (you can change it at any time later).', 'post-type-x' ) . '</p>';
					$box_content .= '<p class="initial-description"><strong>' . __( 'Click start to proceed with layout adjustment.', 'post-type-x' ) . '</strong></p>';
					// The deprecated confirmation note remains disabled.
					$box_content         .= '<p class="wp-core-ui" style="margin-top: 20px;"><button class="button-primary start_section">' . __( 'Start', 'post-type-x' ) . '</button><a target="_blank" href="https://implecode.com/docs/ecommerce-product-catalog/theme-integration-wizard/#cam=default-mode&amp;key=top-message-video" class="button">' . __( 'Video Tutorial', 'post-type-x' ) . '</a></p>';
					$box_content         .= '<table class="styling-adjustments">';
					$integration_settings = self::settings();
					$box_content         .= '<tbody class="section_1 integration-section" style="display: none">';
					/* translators: 1: opening strong tag, 2: closing strong tag. */
					$box_content .= '<tr><td colspan="2"><p style="margin-bottom: 5px">' . sprintf( __( '%1$sDecrease width%2$s to change the product page horizontal size, %1$sincrease padding%2$s to generate space around the content and %1$sset background & text colors%2$s to match your theme style', 'post-type-x' ), '<strong>', '</strong>' ) . ':</p></td></tr>';
					$box_content .= implecode_settings_number( __( 'Width', 'post-type-x' ), 'container_width', $integration_settings['container_width'], '%', 0, null, __( 'In most cases you should decrease this number to match your template container size.', 'post-type-x' ), 0 );
					$box_content .= implecode_settings_text_color( __( 'Background', 'post-type-x' ), 'container_bg', $integration_settings['container_bg'], null, 0, null, '{change: function(event, ui){ var hexcolor = jQuery( this ).wpColorPicker( "color" ); jQuery("#container").css("background", hexcolor); jQuery("#container").css("overflow", "hidden"); jQuery("#container").css("width", jQuery("input[name=\"container_width\"]").val()+"%");}}' );
					$box_content .= implecode_settings_text_color( __( 'Text Color', 'post-type-x' ), 'container_text', $integration_settings['container_text'], null, 0, null, '{change: function(event, ui){ var hexcolor = jQuery( this ).wpColorPicker( "color" ); jQuery("#container *").css("color", hexcolor); }}' );
					$box_content .= implecode_settings_number( __( 'Padding', 'post-type-x' ), 'container_padding', $integration_settings['container_padding'], 'px', 0, null, __( 'Increase this number to make space also on the top and bottom of the container. This is useful also if you are planning to enable the sidebar in next step.', 'post-type-x' ), 0 );
					$box_content .= '</tbody>';

					if ( ! defined( 'AL_SIDEBAR_BASE_URL' ) ) {
						$box_content .= '<tbody class="section_2 integration-section" style="display: none">';
						$box_content .= '<tr><td colspan="2"><p style="margin-bottom: 5px">' . __( 'Select the sidebar side to enable it on your product pages.', 'post-type-x' ) . ':</p></td></tr>';

						$box_content     .= implecode_settings_radio(
							__( 'Default Sidebar', 'post-type-x' ),
							'default_sidebar',
							$integration_settings['default_sidebar'],
							array(
								'none'  => __( 'Disabled', 'post-type-x' ),
								'left'  => __( 'Left', 'post-type-x' ),
								'right' => __( 'Right', 'post-type-x' ),
							),
							0
						);
							$box_content .= '<tr><td colspan="2"><p style="margin-top: 5px; margin-bottom: 5px; display: none;" class="integration-sidebar-info">' . __( 'If the sidebar is to close to the content, you can go back and adjust the padding.', 'post-type-x' ) . '</p></td></tr>';
						if ( 'none' === $integration_settings['default_sidebar'] ) {
							$box_content .= '<style>#catalog_sidebar {display: none;}</style>';
						}
						$box_content .= '</tbody>';
					}
					$box_content .= '<tbody class="section_3 integration-section" style="display: none">';
					$box_content .= '<tr><td colspan="2"><p style="margin-bottom: 5px">' . __( 'Disable unnecessary product page elements', 'post-type-x' ) . ':</p></td></tr>';

					$box_content .= implecode_settings_checkbox( __( 'Disable breadcrumbs', 'post-type-x' ), 'disable_breadcrumbs', $integration_settings['disable_breadcrumbs'], 0 );
					$box_content .= implecode_settings_checkbox( __( 'Disable Name', 'post-type-x' ), 'disable_name', $integration_settings['disable_name'], 0 );
					$box_content .= implecode_settings_checkbox( __( 'Disable Image', 'post-type-x' ), 'disable_image', $integration_settings['disable_image'], 0 );
					if ( function_exists( 'get_currency_settings' ) ) {
						$box_content .= implecode_settings_checkbox( __( 'Disable Price', 'post-type-x' ), 'disable_price', $integration_settings['disable_price'], 0 );
					}
					if ( function_exists( 'is_ic_sku_enabled' ) ) {
						$box_content .= implecode_settings_checkbox( __( 'Disable SKU', 'post-type-x' ), 'disable_sku', $integration_settings['disable_sku'], 0 );
					}
					if ( function_exists( 'is_ic_shipping_enabled' ) ) {
						$box_content .= implecode_settings_checkbox( __( 'Disable Shipping', 'post-type-x' ), 'disable_shipping', $integration_settings['disable_shipping'], 0 );
					}
					if ( function_exists( 'is_ic_attributes_enabled' ) ) {
						$box_content .= implecode_settings_checkbox( __( 'Disable Attributes', 'post-type-x' ), 'disable_attributes', $integration_settings['disable_attributes'], 0 );
					}
					$box_content .= '</tbody>';

					$box_content .= '</table>';

					$box_content .= '<style>#integration_wizard .al-box table tbody, #integration_wizard .al-box table tr, #integration_wizard .al-box table td {border: 0; background: transparent;} #integration_wizard .al-box table.styling-adjustments td, #integration_wizard .al-box table.styling-adjustments td label {vertical-align: middle;font-size: 14px; color: rgb(136, 136, 136) !important; text-align: left;} #integration_wizard .wp-picker-container {padding-top: 5px;}html #integration_wizard.fixed-box .al-box table input.wp-picker-clear {background: #ededed; transition: none; padding: 1px 6px; border: 1px solid #000; color: #000; margin: 0; margin-left: 6px;}#integration_wizard .wp-picker-holder{position: absolute;z-index: 99;}#integration_wizard p, #integration_wizard strong, #integration_wizard td, #integration_wizard h4, #integration_wizard span {color: #000 !important}</style>';

					$box_content .= '<div class="section_last integration-section" style="display: none">';
					$box_content .= '<p>' . __( 'Is everything looking fine now?', 'post-type-x' ) . '</p>';
					$box_content .= '<style>#integration_wizard .ic_spinner{background: url(' . admin_url() . '/images/spinner.gif) no-repeat;display: inline-block;
    opacity: 0.7;
    width: 20px;
    height: 20px;
    margin-left: 2px;
    vertical-align: middle;
    display: none;
	margin-right:3px;
	margin-left: -23px;}</style>';
					$box_content .= '<p class="wp-core-ui"><span class="ic_spinner"></span><a href="' . self::wizard_step_url( 'ok' ) . '" class="button-primary integration-ok">' . __( 'It\'s Fine', 'post-type-x' ) . '</a>';
					if ( is_ic_simple_mode() ) {
						$box_content .= '<a href="' . self::wizard_step_url( 'bad' ) . '" class="button-secondary">' . __( 'It\'s Broken', 'post-type-x' ) . '</a>';
					}
					$box_content .= '<button class="button-secondary show_third switch_section">' . __( 'Go Back', 'post-type-x' ) . '</button></p>';
					$box_content .= '</div>';
					$box_content .= '<p class="wp-core-ui" style="margin-top: 20px;"><button class="button-secondary show_prev_section switch_section" style="display: none">' . __( 'Go Back', 'post-type-x' ) . '</button><button class="button-primary show_next_section switch_section" style="display: none">' . __( 'Next', 'post-type-x' ) . '</button></p>';
				}
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: built by implecode_info(), a framework notice box.
				echo '<div id="integration_wizard" class="integration_start ' . $class . '">' . implecode_info( $box_content, 0 ) . '</div>';
			} elseif ( 'bad' === $test_advanced ) {
				$box_content .= '<p>' . __( 'It seems that Manual Theme Integration is needed in order to use Advanced Mode with your current theme.', 'post-type-x' ) . '</p>';
				$box_content .= '<h4>' . __( 'You Have 3 choices', 'post-type-x' ) . ':</h4>';
				$box_content .= '<ol>';
				$box_content .= '<li>' . __( 'Get the Manual Theme Integration done.', 'post-type-x' ) . '</li>';
				$box_content .= '<li>' . __( 'Keep using Simple Mode which is still functional.', 'post-type-x' ) . '</li>';
				/* translators: %s: catalog shortcode tag. */
				$box_content .= '<li>' . sprintf( __( 'Use %s on your product listing page.', 'post-type-x' ), '[' . ic_catalog_shortcode_name() . ']' ) . '</li>';
				$box_content .= '<li>' . __( 'Switch the theme.', 'post-type-x' ) . '</li>';
				$box_content .= '</ol>';
				$box_content .= '<p>' . __( 'Please make your choice below or switch the theme.', 'post-type-x' ) . '</p>';
				$box_content .= '<p class="wp-core-ui"><a target="_blank" href="https://implecode.com/wordpress/product-catalog/theme-integration-guide/#cam=simple-mode&key=integration-advanced-fail" class="button-primary">' . __( 'Free Theme Integration Guide', 'post-type-x' ) . '</a><a href="' . self::wizard_step_url( 'simple' ) . '" class="button-secondary">' . __( 'Use Simple Mode', 'post-type-x' ) . '</a></p>';
				self::enable_simple_mode();
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: built by implecode_warning(), a framework notice box.
				echo '<div id="integration_wizard" class="' . $class . '">' . implecode_warning( $box_content, 0 ) . '</div>';
			} elseif ( 'ok' === $test_advanced ) {
				/* translators: %s: catalog plugin name. */
				$box_content .= '<p>' . sprintf( __( 'Congratulations! %s is working on Advanced Mode now. You can go to admin and add the products to the catalog.', 'post-type-x' ), IC_CATALOG_PLUGIN_NAME ) . '</p>';
				/* translators: 1: opening link tag, 2: closing link tag. */
				$box_content .= '<p>' . sprintf( __( 'If you are a developer or would like to have full control on the product pages templates, make sure to see the %1$stemplates docs%2$s.', 'post-type-x' ), '<a href="https://implecode.com/docs/ecommerce-product-catalog/product-page-template/#cam=advanced-mode&key=integration-advanced-success-page-template">', '</a>' ) . '</p>';
				// The follow-up settings note remains disabled.
				$box_content .= '<p class="wp-core-ui"><a href="' . admin_url( 'edit.php?post_type=al_product' ) . '" class="button-primary">' . __( 'Go to Admin', 'post-type-x' ) . '</a>';
				// The optional guide link remains disabled here.
				$box_content .= '</p>';
				self::enable_advanced_mode();
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: built by implecode_success(), a framework notice box.
				echo '<div id="integration_wizard" class="' . $class . '">' . implecode_success( $box_content, 0 ) . '</div>';
			} elseif ( 'simple' === $test_advanced ) {
				if ( is_ic_shortcode_integration() ) {
					$box_content .= '<p>' . __( 'You are using shortcode mode now.', 'post-type-x' ) . '</p>';
				} else {
					$box_content .= '<p>' . __( 'You are using simple mode now.', 'post-type-x' ) . '</p>';
				}
				$box_content .= '<p>' . __( 'You can switch between modes at any time in Product Settings.', 'post-type-x' ) . '</p>';
				$box_content .= '<p>' . __( 'Use the buttons below to try the advanced integration again or go to admin and start adding your products.', 'post-type-x' ) . '</p>';
				$box_content .= '<p class="wp-core-ui"><a href="' . admin_url( 'edit.php?post_type=al_product' ) . '" class="button-primary">' . __( 'Go to Admin', 'post-type-x' ) . '</a><a href="' . self::wizard_step_url( '1' ) . '" class="button-secondary">' . __( 'Restart Advanced Mode Test', 'post-type-x' ) . '</a></p>';
				self::enable_simple_mode();
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: built by implecode_success(), a framework notice box.
				echo '<div id="integration_wizard" class="' . $class . '">' . implecode_success( $box_content, 0 ) . '</div>';
			}
		}
	}

	/**
	 * Returns wizard advanced mode settings
	 *
	 * @return array
	 */
	public static function settings() {
		$archive_multiple_settings       = get_multiple_settings();
		$theme                           = get_option( 'template' );
		$settings['container_width']     = isset( $archive_multiple_settings['container_width'][ $theme ] ) ? $archive_multiple_settings['container_width'][ $theme ] : 100;
		$settings['container_bg']        = isset( $archive_multiple_settings['container_bg'][ $theme ] ) ? $archive_multiple_settings['container_bg'][ $theme ] : '';
		$settings['container_padding']   = isset( $archive_multiple_settings['container_padding'][ $theme ] ) ? $archive_multiple_settings['container_padding'][ $theme ] : 0;
		$settings['container_text']      = isset( $archive_multiple_settings['container_text'][ $theme ] ) ? $archive_multiple_settings['container_text'][ $theme ] : '';
		$settings['disable_breadcrumbs'] = isset( $archive_multiple_settings['enable_product_breadcrumbs'] ) && 1 === (int) $archive_multiple_settings['enable_product_breadcrumbs'] ? 0 : 1;
		$settings['disable_name']        = isset( $archive_multiple_settings['disable_name'] ) ? $archive_multiple_settings['disable_name'] : 0;
		$settings['disable_image']       = is_ic_product_gallery_enabled() ? 0 : 1;
		$settings['disable_price']       = function_exists( 'is_ic_price_enabled' ) && is_ic_price_enabled() ? 0 : 1;
		$settings['disable_sku']         = function_exists( 'is_ic_sku_enabled' ) && is_ic_sku_enabled() ? 0 : 1;
		$settings['disable_shipping']    = function_exists( 'is_ic_shipping_enabled' ) && is_ic_shipping_enabled() ? 0 : 1;
		$settings['disable_attributes']  = function_exists( 'is_ic_attributes_enabled' ) && is_ic_attributes_enabled() ? 0 : 1;
		$settings['default_sidebar']     = isset( $archive_multiple_settings['default_sidebar'] ) ? $archive_multiple_settings['default_sidebar'] : 'none';

		return $settings;
	}

	/**
	 * Handles wizard avanced mode settings save.
	 */
	public static function save_wizard() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- Custom capability is registered by the plugin.
		if ( ! empty( $nonce ) && wp_verify_nonce( $nonce, 'ic-ajax-nonce' ) && current_user_can( 'manage_product_settings' ) ) {
			$archive_multiple_settings = get_multiple_settings();
			$product_page_settings     = get_product_page_settings();
			$theme                     = get_option( 'template' );
			$container_width           = isset( $_POST['container_width'] ) ? intval( wp_unslash( $_POST['container_width'] ) ) : 0;
			$container_bg              = isset( $_POST['container_bg'] ) ? sanitize_text_field( wp_unslash( $_POST['container_bg'] ) ) : '';
			$container_padding         = isset( $_POST['container_padding'] ) ? intval( wp_unslash( $_POST['container_padding'] ) ) : 0;
			$container_text            = isset( $_POST['container_text'] ) ? sanitize_text_field( wp_unslash( $_POST['container_text'] ) ) : '';
			$disable_name              = isset( $_POST['disable_name'] ) ? intval( wp_unslash( $_POST['disable_name'] ) ) : 0;
			$disable_sku               = isset( $_POST['disable_sku'] ) ? intval( wp_unslash( $_POST['disable_sku'] ) ) : 0;
			$default_sidebar           = isset( $_POST['default_sidebar'] ) ? sanitize_text_field( wp_unslash( $_POST['default_sidebar'] ) ) : '';
			$breadcrumbs               = isset( $_POST['disable_breadcrumbs'] ) ? intval( wp_unslash( $_POST['disable_breadcrumbs'] ) ) : 0;
			$archive_multiple_settings['container_width'][ $theme ]   = $container_width;
			$archive_multiple_settings['container_bg'][ $theme ]      = $container_bg;
			$archive_multiple_settings['container_padding'][ $theme ] = $container_padding;
			$archive_multiple_settings['container_text'][ $theme ]    = $container_text;
			$archive_multiple_settings['disable_name']                = $disable_name;
			$archive_multiple_settings['disable_sku']                 = $disable_sku;
			$archive_multiple_settings['default_sidebar']             = $default_sidebar;
			if ( 1 === $breadcrumbs ) {
				$archive_multiple_settings['enable_product_breadcrumbs'] = 0;
			} else {
				$archive_multiple_settings['enable_product_breadcrumbs'] = 1;
			}
			update_option( 'archive_multiple_settings', $archive_multiple_settings );

			if ( function_exists( 'get_currency_settings' ) ) {
				$price                     = isset( $_POST['disable_price'] ) ? intval( wp_unslash( $_POST['disable_price'] ) ) : 0;
				$product_currency_settings = get_currency_settings();
				if ( 1 === $price ) {
					$product_currency_settings['price_enable'] = 'off';
					update_option( 'product_currency_settings', $product_currency_settings );
				} else {
					$product_currency_settings['price_enable'] = 'on';
					update_option( 'product_currency_settings', $product_currency_settings );
				}
			}

			$image = isset( $_POST['disable_image'] ) ? intval( wp_unslash( $_POST['disable_image'] ) ) : 0;
			if ( 1 === $image ) {
				$product_page_settings['enable_product_gallery'] = 0;
				update_option( 'multi_single_options', $product_page_settings );
			} else {
				$product_page_settings['enable_product_gallery'] = 1;
				update_option( 'multi_single_options', $product_page_settings );
			}
			if ( function_exists( 'is_ic_shipping_enabled' ) ) {
				$shipping = isset( $_POST['disable_shipping'] ) ? intval( wp_unslash( $_POST['disable_shipping'] ) ) : 0;
				if ( 1 === $shipping ) {
					update_option( 'product_shipping_options_number', 0 );
				} elseif ( ! is_ic_shipping_enabled() ) {
					update_option( 'product_shipping_options_number', 2 );
				}
			}
			if ( function_exists( 'is_ic_attributes_enabled' ) ) {
				$attributes = isset( $_POST['disable_attributes'] ) ? intval( wp_unslash( $_POST['disable_attributes'] ) ) : 0;
				if ( 1 === $attributes ) {
					update_option( 'product_attributes_number', 0 );
				} elseif ( ! is_ic_attributes_enabled() ) {
					update_option( 'product_attributes_number', 3 );
				}
			}
		}

			echo 'done';

			wp_die(); // This is required to terminate immediately and return a proper response.
	}

	/**
	 * Enqueues assets required by the sample product wizard.
	 */
	public static function enqueue_sample_product_scripts() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only wizard state from the current request.
		$test_advanced = isset( $_GET['test_advanced'] ) ? sanitize_text_field( wp_unslash( $_GET['test_advanced'] ) ) : '';
		if ( '' !== $test_advanced ) {
			$product_id = sample_product_id();
			if ( intval( $product_id ) === get_the_ID() ) {
				wp_enqueue_script(
					'iris',
					admin_url( 'js/iris.min.js' ),
					array(
						'jquery-ui-draggable',
						'jquery-ui-slider',
						'jquery-touch-punch',
					),
					IC_EPC_VERSION,
					true
				);
				$picker_deps = array(
					'iris',
				);
				global $wp_version;
				if ( version_compare( $wp_version, 6.3, '>=' ) ) {
					$picker_deps[] = 'wp-blocks';
				}
					wp_enqueue_script( 'wp-color-picker', admin_url( 'js/color-picker.min.js' ), $picker_deps, IC_EPC_VERSION, true );
					$colorpicker_l10n = array(
						'clear'         => __( 'Clear', 'post-type-x' ),
						'defaultString' => __( 'Default', 'post-type-x' ),
						'pick'          => __( 'Select Color', 'post-type-x' ),
						'current'       => __( 'Current Color', 'post-type-x' ),
					);
					wp_localize_script( 'wp-color-picker', 'wpColorPickerL10n', $colorpicker_l10n );
					wp_enqueue_style( 'wp-color-picker' );
			}
		}
	}

	/**
	 * Enables Advanced Mode for the current theme.
	 *
	 * @param int $hide_info Whether to hide the info notice.
	 */
	public static function enable_advanced_mode( $hide_info = 0 ) {
		$archive_multiple_settings = get_multiple_settings();
		$theme                     = get_option( 'template' );
		$archive_multiple_settings['integration_type'][ $theme ] = 'advanced';
		update_option( 'archive_multiple_settings', $archive_multiple_settings );
		if ( 1 === (int) $hide_info ) {
			$current_support_check           = IC_Catalog_Notices::theme_support_check();
			$current_support_check[ $theme ] = $theme;
			update_option( 'product_adder_theme_support_check', $current_support_check );
		}
	}

	/**
	 * Enables Simple Mode for the current theme.
	 */
	public static function enable_simple_mode() {
		$archive_multiple_settings = get_multiple_settings();
		$theme                     = get_option( 'template' );
		$archive_multiple_settings['integration_type'][ $theme ] = 'simple';
		update_option( 'archive_multiple_settings', $archive_multiple_settings );
		$current_support_check           = IC_Catalog_Notices::theme_support_check();
		$current_support_check[ $theme ] = '';
		update_option( 'product_adder_theme_support_check', $current_support_check );
	}

	/**
	 * Returns the stored integration mode for the current theme.
	 *
	 * @return string
	 */
	public static function get_real_integration_mode() {
		$archive_multiple_settings = get_option( 'archive_multiple_settings', get_default_multiple_settings() );
		if ( ! is_array( $archive_multiple_settings ) ) {
			$archive_multiple_settings = array();
		}
		$theme    = get_option( 'template' );
		$prev_int = ( isset( $archive_multiple_settings['integration_type'] ) && ! is_array( $archive_multiple_settings['integration_type'] ) ) ? $archive_multiple_settings['integration_type'] : 'simple';
		if ( isset( $archive_multiple_settings['integration_type'] ) && ! is_array( $archive_multiple_settings['integration_type'] ) ) {
			$archive_multiple_settings['integration_type'] = array();
		}
		$archive_multiple_settings['integration_type'][ $theme ] = isset( $archive_multiple_settings['integration_type'][ $theme ] ) ? $archive_multiple_settings['integration_type'][ $theme ] : $prev_int;

		return $archive_multiple_settings['integration_type'][ $theme ];
	}

	/**
	 * Clears saved integration data on theme switch.
	 */
	public static function erase_integration_type_select() {
		$archive_multiple_settings = get_option( 'archive_multiple_settings', get_default_multiple_settings() );
		if ( isset( $archive_multiple_settings['integration_type'] ) && ! is_array( $archive_multiple_settings['integration_type'] ) ) {
			unset( $archive_multiple_settings['integration_type'] );
			unset( $archive_multiple_settings['container_width'] );
			unset( $archive_multiple_settings['container_bg'] );
			unset( $archive_multiple_settings['disable_name'] );
			unset( $archive_multiple_settings['container_padding'] );
			unset( $archive_multiple_settings['default_sidebar'] );
			update_option( 'archive_multiple_settings', $archive_multiple_settings );
			delete_option( 'product_adder_theme_support_check' );
			permalink_options_update();
		}
	}

	/**
	 * Creates the sample product and redirects to the wizard.
	 */
	public static function create_sample_product_with_redirect() {
		// Plain flag read; the nonce it carries is verified by check_admin_referer() below.
		if ( ! isset( $_GET['create_sample_product_page'] ) ) {
			return;
		}
		check_admin_referer( 'ic_create_sample_product_page' );
		$sample_product_id = create_sample_product();
		$url               = get_permalink( $sample_product_id );
		$url               = esc_url_raw( add_query_arg( 'test_advanced', 1, $url ) );
		wp_safe_redirect( $url );
		exit();
	}
}

$ic_catalog_theme_integration = new IC_Catalog_Theme_Integration();
