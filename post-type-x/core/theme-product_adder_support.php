<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase, WordPress.Files.FileName.InvalidClassFileName -- Legacy bootstrap filename is retained to avoid rename scope.

/**
 * Theme product adder support.
 *
 * @package Ecommerce_Product_Catalog
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Plugin compatibility checker
 *
 * Here current theme is checked for compatibility with WP PRODUCT ADDER.
 *
 * @version        1.1.2
 * @package        ecommerce-product-catalog/
 * @author        impleCode
 */
class IC_Catalog_Notices extends IC_Activation_Wizard {

	/**
	 * Registers notice hooks.
	 *
	 * @param bool $run Whether to register hooks.
	 */
	public function __construct( $run = false ) {
		if ( $run ) {
			add_action( 'in_admin_header', array( $this, 'notices' ), 9 );

			add_filter(
				'plugin_action_links_' . plugin_basename( AL_PLUGIN_MAIN_FILE ),
				array(
					$this,
					'catalog_links',
				)
			);

			add_action( 'wp_ajax_hide_review_notice', array( $this, 'ajax_hide_review_notice' ) );
			add_action( 'wp_ajax_hide_ic_notice', array( $this, 'ajax_hide_ic_notice' ) );
			add_action( 'wp_ajax_hide_translate_notice', array( $this, 'ajax_hide_translation_notice' ) );
			add_action( 'wp_ajax_ic_add_catalog_shortcode', array( $this, 'add_catalog_shortcode' ) );

			add_action( 'wp', array( $this, 'remove_catalog_shortcode' ) );

			add_action( 'edit_form_top', array( $this, 'listing_page_info' ) );
			add_action( 'page_attributes_meta_box_template', array( $this, 'listing_template_info' ), 10, 2 );
			add_action( 'edit_form_before_permalink', array( $this, 'listing_slug_info' ) );

			add_action( 'ic_cat_activation_wizard_bottom', array( __CLASS__, 'getting_started_docs_info' ) );

			add_action( 'admin_footer', array( __CLASS__, 'add_catalog_shortcode_script' ) );
		}
	}

	/**
	 * Registers admin notices on plugin pages.
	 *
	 * @return void
	 */
	public function notices() {
		if ( is_ic_admin_page() ) {
			remove_all_actions( 'admin_notices' );
			add_action( 'admin_notices', array( $this, 'catalog_admin_priority_notices' ), - 2 );
			add_action( 'admin_notices', array( $this, 'catalog_admin_notices' ), 9 );
			add_action( 'ic_catalog_admin_notices', array( $this, 'admin_notices' ) );
			add_action( 'ic_catalog_admin_notices', array( $this, 'woocommerce_notice' ) );
		}
	}

	/**
	 * Outputs the getting started guide link.
	 *
	 * @return void
	 */
	public static function getting_started_docs_info() {
		$getting_started_url = apply_filters( 'ic_cat_getting_started_url', 'https://implecode.com/docs/ecommerce-product-catalog/getting-started/#cam=default-mode&key=getting-started' );
		?>
		<p class="bottom-container">
			<a rel="noopener" target="_blank" href="<?php echo esc_url( $getting_started_url ); ?>">
				<?php esc_html_e( 'Getting started guide with step by step instructions', 'post-type-x' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Outputs listing page information.
	 *
	 * @param WP_Post $post Current post object.
	 *
	 * @return void
	 */
	public function listing_page_info( $post ) {
		$listing_id = get_product_listing_id();
		if ( (string) $listing_id === (string) $post->ID ) {
			/* translators: 1: Opening catalog settings link tag. 2: Closing catalog settings link tag. */
			implecode_info( sprintf( __( 'This page is defined as the main catalog listing. You can change this in %1$scatalog settings%2$s.', 'post-type-x' ), '<a href="' . esc_url( admin_url( 'edit.php?post_type=al_product&page=product-settings.php' ) ) . '">', '</a>' ) );
			if ( ! is_ic_shortcode_integration() ) {
				echo '<div></div>';
				/* translators: %s: Catalog shortcode. */
				implecode_info( sprintf( __( 'If you have any problems with your catalog display, please add %s to the page content and save.', 'post-type-x' ), ic_catalog_shortcode_name() ) );
			}
		}
	}

	/**
	 * Outputs listing slug information.
	 *
	 * @param WP_Post $post Current post object.
	 *
	 * @return void
	 */
	public function listing_slug_info( $post ) {
		$listing_id = get_product_listing_id();
		if ( (string) $listing_id === (string) $post->ID && ! is_product_listing_home_set() ) {
			implecode_info( __( 'Use the permalink option below to define the parent URL for all catalog pages.', 'post-type-x' ) );
		}
	}

	/**
	 * Outputs listing template information.
	 *
	 * @param string  $template Current template.
	 * @param WP_Post $post     Current post object.
	 *
	 * @return void
	 */
	public function listing_template_info( $template, $post ) {
		if ( ! is_ic_shortcode_integration() ) {
			return;
		}
		$listing_id = get_product_listing_id();
		if ( (string) $listing_id === (string) $post->ID ) {
			implecode_info( __( 'Use the dropdown below to define the template for all catalog pages.', 'post-type-x' ) );
		}
	}

	/**
	 * Fires priority admin notices.
	 *
	 * @return void
	 */
	public function catalog_admin_priority_notices() {
		if ( is_ic_admin_page() ) {
			do_action( 'ic_catalog_admin_priority_notices', $this );
		}
	}

	/**
	 * Fires catalog admin notices.
	 *
	 * @return void
	 */
	public function catalog_admin_notices() {
		if ( is_ic_admin_page() ) {
			do_action( 'ic_catalog_admin_notices', $this );
		}
	}

	/**
	 * Outputs admin notices.
	 *
	 * @return void
	 */
	public function admin_notices() {
		if ( current_user_can( 'activate_plugins' ) ) {
			if ( ! is_advanced_mode_forced() || ic_get_product_listing_status() ) {
				$template      = get_option( 'template' );
				$current_check = $this->theme_support_check();
				if ( ! empty( $_GET['hide_al_product_adder_support_check'] ) ) {
					// The dismissal writes a site option, so the request must come from the nonced link.
					check_admin_referer( 'ic_hide_theme_support_check' );
					$current_check[ $template ] = $template;
					update_option( 'product_adder_theme_support_check', $current_check );

					return;
				}
				// phpcs:ignore WordPress.WP.Capabilities.Unknown -- Custom catalog capability is registered by the plugin.
				if ( empty( $current_check[ $template ] ) && current_user_can( 'manage_product_settings' ) ) {
					$this->theme_check_notice();
				}
			}
			if ( is_ic_catalog_admin_page() ) {
				$product_count = ic_products_count();
				if ( $product_count > 5 ) {
					if ( false === get_site_transient( 'implecode_hide_plugin_review_info' ) && $this->get_notice_status( 'ic-catalog-review' ) === 0 ) {
						$this->review_notice();
					} elseif ( false === get_site_transient( 'implecode_hide_plugin_translation_info' ) && ! is_english_catalog_active() ) {
						$this->translation_notice();
					}
				} elseif ( false === get_site_transient( 'implecode_hide_plugin_review_info' ) ) {
					set_site_transient( 'implecode_hide_plugin_review_info', 1, WEEK_IN_SECONDS );
				}
			}
		}
	}

	/**
	 * Outputs the theme check notice.
	 *
	 * @return void
	 */
	public function theme_check_notice() {
		if ( $this->get_notice_status( 'notice-ic-catalog-welcome' ) || ! is_ic_admin_page() ) {
			return;
		}
		if ( is_integration_mode_selected() && 'simple' === get_integration_type() ) {
			/* translators: 1: Opening theme install link tag. 2: Closing theme install link tag. */
			$catalog_me_theme_notice = sprintf( __( 'You can also use awesome %1$sCatalog Me! theme%2$s.', 'post-type-x' ), '<a href="' . esc_url( admin_url( 'theme-install.php?search=Catalog%20me' ) ) . '">', '</a>' );
			?>
			<div class="notice notice-updated is-dismissible ic-notice" data-ic_dismissible="notice-ic-catalog-welcome">
				<div class="squeezer">
					<?php /* translators: %s: Plugin name. */ ?>
					<h4><?php printf( esc_html__( 'You are currently using %s in Simple Mode. It is perfectly fine to use it this way, however some features are limited.', 'post-type-x' ), esc_html( IC_CATALOG_PLUGIN_NAME ) ); ?></h4>
					<?php /* translators: %s: Catalog shortcode. */ ?>
					<h4><?php printf( esc_html__( 'To switch to Advanced Mode, please add %s to your product listing page.', 'post-type-x' ), esc_html( ic_catalog_shortcode_name() ) ); ?></h4>
					<h4>
					<?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: HTML assembled earlier in this scope, so escaping it here would print the tags.
					echo $catalog_me_theme_notice;
					?>
					</h4>
					<p class="submit">
						<a class="button-primary add-catalog-shortcode"><?php esc_html_e( 'Add Shortcode Now', 'post-type-x' ); ?></a>
						<a class="skip button"
							href="<?php echo esc_url( admin_url( 'edit.php?post_type=al_product&page=product-settings.php&tab=product-settings&submenu=support' ) ); ?>"><?php esc_html_e( 'Plugin Support', 'post-type-x' ); ?></a>
						<a class="skip button"
							href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'hide_al_product_adder_support_check', 'true' ), 'ic_hide_theme_support_check' ) ); ?>"><?php esc_html_e( 'I know, don\'t bug me', 'post-type-x' ); ?></a>
					</p>
				</div>
			</div>
			<div class="clear"></div>
			<?php
		} elseif ( is_integration_mode_selected() && 'advanced' === get_integration_type() && ! is_ic_shortcode_integration() ) {

			$template      = get_option( 'template' );
			$current_check = $this->theme_support_check();
			if ( empty( $current_check[ $template ] ) ) {
				$current_check[ $template ] = $template;
				update_option( 'product_adder_theme_support_check', $current_check );
			}
		} else {
			return; // Default notice disabled because of activation info.
		}
	}

	/**
	 * Outputs the WooCommerce compatibility notice.
	 *
	 * @return void
	 */
	public function woocommerce_notice() {
		if ( $this->get_notice_status( 'notice-ic-woocommerce-compat' ) || ! is_ic_admin_page() ) {
			return;
		}
		if ( $this->show_woocommerce_notice() ) {
			/* translators: 1: Plugin name. 2: WooCommerce. 3: Opening import screen link tag. 4: Closing import screen link tag. */
			$woocommerce_import_notice = sprintf( __( 'go to %1$s %3$simport screen%4$s to transfer all %2$s products to %1$s and remove %2$s after that', 'post-type-x' ), esc_html( IC_CATALOG_PLUGIN_NAME ), 'WooCommerce', '<a href="' . esc_url( admin_url( 'edit.php?post_type=al_product&page=product-settings.php&tab=product-settings&submenu=csv' ) ) . '">', '</a>' );
			/* translators: 1: Extension name. 2: Opening catalog extensions link tag. 3: Closing catalog extensions link tag. */
			$woocommerce_design_notice = sprintf( __( 'install free %1$s plugin from %2$scatalog extensions menu%3$s', 'post-type-x' ), 'WooCommerce Catalog', '<a href="' . esc_url( admin_url( 'edit.php?post_type=al_product&page=extensions.php&tab=product-extensions' ) ) . '">', '</a>' );
			?>
					<div class="notice notice-info is-dismissible ic-notice" data-ic_dismissible="notice-ic-woocommerce-compat">
						<?php /* translators: 1: WooCommerce. 2: Plugin name. */ ?>
						<h4><?php printf( esc_html__( 'Hey! It looks like you have %1$s installed. You can use %2$s with or without %1$s.', 'post-type-x' ), 'WooCommerce', esc_html( IC_CATALOG_PLUGIN_NAME ) ); ?> <?php esc_html_e( 'See the usage scenarios below:', 'post-type-x' ); ?></h4>
					<ul style="list-style: initial;list-style-position: inside;">
						<?php /* translators: 1: WooCommerce. 2: Plugin name. */ ?>
						<li><?php printf( esc_html__( '%1$s and %2$s separately for different product catalogs', 'post-type-x' ), 'WooCommerce', esc_html( IC_CATALOG_PLUGIN_NAME ) ); ?>
							- <?php esc_html_e( "doesn't need any specific configuration", 'post-type-x' ); ?></li>
						<?php /* translators: 1: Plugin name. 2: WooCommerce. */ ?>
						<li><?php printf( esc_html__( '%1$s as an alternative to %2$s', 'post-type-x' ), esc_html( IC_CATALOG_PLUGIN_NAME ), 'WooCommerce' ); ?>
							- 
							<?php
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: HTML assembled earlier in this scope, so escaping it here would print the tags.
							echo $woocommerce_import_notice;
							?>
							</li>
						<?php /* translators: 1: WooCommerce. 2: Plugin name. */ ?>
						<li><?php printf( esc_html__( '%1$s with %2$s design', 'post-type-x' ), 'WooCommerce', esc_html( IC_CATALOG_PLUGIN_NAME ) ); ?>
							- 
							<?php
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: HTML assembled earlier in this scope, so escaping it here would print the tags.
							echo $woocommerce_design_notice;
							?>
							</li>
				</ul>
			</div>
			<?php
		}
	}

	/**
	 * Outputs the simple mode warning.
	 *
	 * @return void
	 */
	public static function simple_mode_notice() {
		if ( get_integration_type() !== 'simple' ) {
			return;
		}
		$listing_status = ic_get_product_listing_status();
		if ( 'publish' !== $listing_status ) {
			/* translators: %s: Listing page button HTML. */
			implecode_warning( sprintf( __( 'Disabled due to a lack of main catalog listing.%s', 'post-type-x' ), self::create_listing_page_button() ) );
		} else {
			/* translators: 1: Catalog shortcode. 2: Opening main listing link tag. 3: Closing main listing link tag. */
			implecode_warning( sprintf( __( 'Disabled in simple mode. Add %1$s to your %2$smain catalog listing page%3$s to enable it.', 'post-type-x' ), ic_catalog_shortcode_name(), '<a href="' . esc_url( product_listing_url() ) . '">', '</a>' ) );
		}
	}

	/**
	 * Returns the create listing page button HTML.
	 *
	 * @param string|null $label          Button label.
	 * @param bool        $listing_button Whether to append the listing button.
	 * @param string      $primary_class  Button class.
	 *
	 * @return string
	 */
	public static function create_listing_page_button( $label = null, $listing_button = false, $primary_class = 'button-primary' ) {
		ob_start();
		if ( $listing_button && 'button-primary' !== $primary_class ) {
			self::main_listing_button();
			$listing_button = false;
		}
		$listing_status = ic_get_product_listing_status();
		if ( 'publish' !== $listing_status ) {
			if ( empty( $label ) ) {
				$label = __( 'Create Main Catalog Listing', 'post-type-x' );
			}
			?>
			<a class="<?php echo esc_attr( $primary_class ); ?> add-catalog-shortcode"><?php echo esc_html( $label ); ?></a>
			<?php
		}
		if ( $listing_button ) {
			self::main_listing_button();
		}

		return ob_get_clean();
	}

	/**
	 * Outputs the add catalog shortcode script.
	 *
	 * @return void
	 */
	public static function add_catalog_shortcode_script() {
		$screen = get_current_screen();
		if ( empty( $screen->id ) || 'widgets' !== $screen->id ) {
			return;
		}
		?>
		<script>jQuery(".add-catalog-shortcode").on("click", function (event) {
				event.preventDefault();
				var data = {
					'action': 'ic_add_catalog_shortcode',
					'nonce': '<?php echo esc_js( wp_create_nonce( 'ic-ajax-nonce' ) ); ?>'
				};
				jQuery(this).prop("disabled", true);
				jQuery.post(ajaxurl, data, function (response) {
					jQuery('<a style="margin-left: 5px;" href="' + response + '" class="button-primary"><?php echo esc_js( __( 'See Your Product Listing', 'post-type-x' ) ); ?></a>').insertAfter(".add-catalog-shortcode");
					jQuery(".add-catalog-shortcode").replaceWith("<?php echo esc_js( __( 'The listing has been added successfully!', 'post-type-x' ) ); ?>");
					jQuery(".skip.button").remove();
				});
			});</script>
		<?php
	}

	/**
	 * Outputs the main listing button.
	 *
	 * @return void
	 */
	public static function main_listing_button() {
		$listing_url = product_listing_url();
		if ( ! empty( $listing_url ) ) {
			if ( ! is_integration_mode_selected() && ! is_ic_shortcode_integration() ) {
				return;
			}
			?>
			<a href="<?php echo esc_url( $listing_url ); ?>"
				class="button-secondary"><?php esc_html_e( 'See Main Catalog Listing', 'post-type-x' ); ?></a>
			<?php
		}
	}

	/**
	 * Filters plugin action links.
	 *
	 * @param array $links Existing action links.
	 *
	 * @return array
	 */
	public function catalog_links( $links ) {
		if ( function_exists( 'get_admin_url' ) ) {
			$links['extensions'] = '<a href="' . esc_url( get_admin_url( null, 'edit.php?post_type=al_product&page=extensions.php' ) ) . '">' . esc_html__( 'Add-ons & Integrations', 'post-type-x' ) . '</a>';
			$links['settings']   = '<a href="' . esc_url( get_admin_url( null, 'edit.php?post_type=al_product&page=product-settings.php' ) ) . '">' . esc_html__( 'Settings', 'post-type-x' ) . '</a>';
		}

		return apply_filters( 'ic_epc_links', array_reverse( $links ) );
	}

	/**
	 * Outputs the review notice.
	 *
	 * @return void
	 */
	public function review_notice() {
		$hidden = get_user_meta( get_current_user_id(), 'ic_review_hidden', true );
		if ( $hidden ) {
			return;
		}
		/* translators: 1: Plugin name. 2: Review URL. 3: Plugin name. */
		$text = apply_filters( 'ic_review_notice_text', sprintf( __( '%1$s is free software. Would you mind taking <strong>5 seconds</strong> to <a target="_blank" href="%2$s">rate the plugin</a> for us, please? Your comments <strong>help others know what to expect</strong> when they install %3$s.', 'post-type-x' ), IC_CATALOG_PLUGIN_NAME, 'https://wordpress.org/support/plugin/' . IC_CATALOG_PLUGIN_SLUG . '/reviews/#new-post', IC_CATALOG_PLUGIN_NAME ) );
		/* translators: %s: Review URL. */
		$review_thanks_text = sprintf( __( 'Thank you for <a target="_blank" href="%s">your rating</a>! We appreciate your time and input.', 'post-type-x' ), 'https://wordpress.org/support/view/plugin-reviews/ecommerce-product-catalog#new-post' );
		?>
		<div class="notice notice-warning implecode-review ic-notice is-dismissible"
			data-ic_dismissible="ic-catalog-review"
			data-ic_dismissible_type="temp">
			<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: HTML assembled earlier in this scope, so escaping it here would print the tags. ?>
			<p><?php echo $text . ' ' . __( 'A <strong>huge thank you</strong> from impleCode and WordPress community in advance!', 'post-type-x' ); ?></p>
			<p><a target="_blank"
					href="https://wordpress.org/support/view/plugin-reviews/ecommerce-product-catalog#new-post"
					class="button-primary ic-user-dismiss"><?php esc_html_e( 'Rate Now & Hide Forever', 'post-type-x' ); ?></a>
		</div>
		<div class="update-nag notice notice-warning inline implecode-review-thanks"
			style="display: none">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: HTML assembled earlier in this scope, so escaping it here would print the tags.
			echo $review_thanks_text;
			?>
		<span class="dashicons dashicons-yes"></span></div>
		<?php
	}

	/**
	 * Outputs the translation notice.
	 *
	 * @return void
	 */
	public function translation_notice() {
		?>
		<div class="update-nag notice notice-warning inline implecode-translate"><?php /* translators: 1: Plugin name. 2: Translation project URL. */ ?>
		<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: a translated string that deliberately contains HTML, so it cannot be escaped wholesale; the values interpolated into it are escaped individually on this line. ?>
		<?php /* translators: 1: plugin name, 2: translation project URL. */ printf( __( "<strong>Psst, it's less than 1 minute</strong> to add some translations to %1\$s collaborative <a target='_blank' href='%2\$s'>translation project</a>.", 'post-type-x' ), esc_html( IC_CATALOG_PLUGIN_NAME ), esc_url( 'https://translate.wordpress.org/projects/wp-plugins/ecommerce-product-catalog' ) ); ?>
		<span class="dashicons dashicons-no"></span></div>
		<?php
	}

	/**
	 * Hides the review notice.
	 *
	 * @param bool $forever Whether to hide the notice permanently.
	 *
	 * @return void
	 */
	public static function review_notice_hide( $forever = false ) {
		$user_id = get_current_user_id();
		if ( $forever && ! empty( $user_id ) ) {
			update_user_meta( $user_id, 'ic_review_hidden', 1 );
		} else {
			$count = get_option( 'implecode_hide_plugin_review_info_count', 1 );
			set_site_transient( 'implecode_hide_plugin_review_info', 1, WEEK_IN_SECONDS * $count );
			++$count;
			update_option( 'implecode_hide_plugin_review_info_count', $count, false );
		}
	}

	/**
	 * Handles the review notice dismissal AJAX request.
	 *
	 * @return void
	 */
	public function ajax_hide_review_notice() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- Custom capability is registered by the plugin.
		if ( ! empty( $nonce ) && wp_verify_nonce( $nonce, 'ic-ajax-nonce' ) && current_user_can( 'manage_product_settings' ) ) {
			$forever = isset( $_POST['forever'] );
			$this->review_notice_hide( $forever );
		}
		wp_die();
	}

	/**
	 * Hides the translation notice.
	 *
	 * @return void
	 */
	public function translation_notice_hide() {
		set_site_transient( 'implecode_hide_plugin_translation_info', 1 );
	}

	/**
	 * Handles the translation notice dismissal AJAX request.
	 *
	 * @return void
	 */
	public function ajax_hide_translation_notice() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- Custom capability is registered by the plugin.
		if ( ! empty( $nonce ) && wp_verify_nonce( $nonce, 'ic-ajax-nonce' ) && current_user_can( 'manage_product_settings' ) ) {
			$this->translation_notice_hide();
		}
		wp_die();
	}

	/**
	 * Returns the stored theme support checks.
	 *
	 * @return array
	 */
	public static function theme_support_check() {
		$current_check = get_option( 'product_adder_theme_support_check', array() );
		if ( ! is_array( $current_check ) ) {
			$old_current_check                   = $current_check;
			$current_check                       = array();
			$current_check[ $old_current_check ] = $old_current_check;
		}

		return $current_check;
	}

	/**
	 * Handles the add catalog shortcode AJAX request.
	 *
	 * @return void
	 */
	public function add_catalog_shortcode() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- Custom capability is registered by the plugin.
		if ( ! empty( $nonce ) && wp_verify_nonce( $nonce, 'ic-ajax-nonce' ) && current_user_can( 'manage_product_settings' ) ) {
			$page_id = get_product_listing_id();
			if ( empty( $page_id ) || 'noid' === $page_id ) {
				$page_id = create_products_page();
			}
			if ( ! empty( $page_id ) ) {
				$post              = get_post( $page_id );
				$post->post_status = 'publish';
				if ( ! is_ic_shortcode_integration( $page_id ) ) {
					$post->post_content .= ic_catalog_shortcode();
				}
				wp_update_post( $post );
			}
			permalink_options_update();
			echo esc_url( product_listing_url() );
		}
		wp_die();
	}

	/**
	 * Removes the catalog shortcode from the listing page.
	 *
	 * @return void
	 */
	public function remove_catalog_shortcode() {
		// Plain flag read; the nonce it carries is verified by wp_verify_nonce() below.
		if ( empty( $_GET['remove_shortcode_integration'] ) ) {
			return;
		}
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- Custom capability is registered by the plugin.
		if ( ! empty( $nonce ) && wp_verify_nonce( $nonce, 'ic_remove_shortcode_integration' ) && current_user_can( 'manage_product_settings' ) ) {
			$page_id = get_product_listing_id();
			if ( ! empty( $page_id ) && is_ic_shortcode_integration( $page_id ) ) {
				$post               = get_post( $page_id );
				$post->post_content = str_replace(
					array(
						'<!-- wp:ic-epc/show-catalog /-->',
						'[show_product_catalog]',
					),
					'',
					$post->post_content
				);
				wp_update_post( $post );
				permalink_options_update();
				wp_safe_redirect( remove_query_arg( array( 'remove_shortcode_integration', '_wpnonce' ) ) );
				exit;
			}
		}
	}
}

if ( ! class_exists( 'ic_catalog_notices', false ) ) {
	class_alias( 'IC_Catalog_Notices', 'ic_catalog_notices' );
}

$ic_notices = new IC_Catalog_Notices( true );
