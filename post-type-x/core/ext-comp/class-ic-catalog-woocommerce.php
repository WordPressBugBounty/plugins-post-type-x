<?php
/**
 * WooCommerce compatibility settings class.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WooCommerce compatibility settings handlers.
 */
class IC_Catalog_Woocommerce {

	/**
	 * Registers WooCommerce compatibility hooks.
	 */
	public function __construct() {
		if ( class_exists( 'WooCommerce' ) ) {
			add_filter( 'ic_settings_screen_tabs', array( $this, 'woocommerce_tab_config' ), 51, 2 );
			add_action( 'ic_woocommerce_settings', array( $this, 'content' ) );
		}
	}

	/**
	 * Adds the WooCommerce settings tab configuration.
	 *
	 * @param array              $tabs Existing settings screen tabs.
	 * @param IC_Settings_Screen $screen Settings screen instance.
	 *
	 * @return array
	 */
	public function woocommerce_tab_config( $tabs, $screen = null ) {
		if ( function_exists( 'ic_epc_is_settings_screen_instance' ) ) {
			if ( ! ic_epc_is_settings_screen_instance( $screen ) ) {
				return $tabs;
			}
		} elseif ( ! is_object( $screen ) || ! method_exists( $screen, 'menu_slug' ) || 'product-settings.php' !== $screen->menu_slug() ) {
			return $tabs;
		}

		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- Custom capability is registered by the catalog plugin.
		if ( current_user_can( 'manage_product_settings' ) ) {
			$tab_settings = array(
				'label'           => __( 'WooCommerce', 'post-type-x' ),
				'menu_item_id'    => 'woocommerce-settings',
				'url'             => $this->woocommerce_tab_url(),
				'active_callback' => array( $this, 'woocommerce_tab_active' ),
			);

			if ( ! function_exists( 'start_ic_woocat' ) ) {
				$tab_settings['callback']   = array( $this, 'tab' );
				$tab_settings['query_args'] = array(
					'submenu' => 'woocommerce',
				);
			}

			$tabs['woocommerce'] = $tab_settings;
		}

		return $tabs;
	}

	/**
	 * Returns the WooCommerce settings tab URL.
	 *
	 * @return string
	 */
	private function woocommerce_tab_url() {
		if ( function_exists( 'start_ic_woocat' ) ) {
			return admin_url( 'admin.php?page=ic-catalog-mode' );
		}

		return admin_url( 'edit.php?post_type=al_product&page=product-settings.php&tab=woocommerce&submenu=woocommerce' );
	}

	/**
	 * Returns true when the WooCommerce tab should be active.
	 *
	 * @return bool
	 */
	public function woocommerce_tab_active() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing check.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing check.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		if ( function_exists( 'start_ic_woocat' ) ) {
			return 'ic-catalog-mode' === $page;
		}

		return 'product-settings.php' === $page && 'woocommerce' === $tab;
	}

	/**
	 * Renders the WooCommerce settings tab content wrapper.
	 *
	 * @return void
	 */
	public function tab() {
		?>
		<div class="woocommerce-settings settings-wrapper">
			<div class="settings-submenu">
				<h3>
					<a id="woocommerce-settings" class="element current"
						href="<?php echo esc_url( $this->woocommerce_tab_url() ); ?>"><?php esc_html_e( 'WooCommerce', 'post-type-x' ); ?></a>
				</h3>
			</div>
			<div class="setting-content submenu">
				<?php do_action( 'ic_woocommerce_settings' ); ?>
			</div>
			<div class="helpers">
				<div class="wrapper">
				<?php
					ic_epc_main_helper();
				?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the WooCommerce settings inner content.
	 *
	 * @return void
	 */
	public function content() {
		$submenu = (string) filter_input( INPUT_GET, 'submenu', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$submenu = sanitize_key( $submenu );
		if ( 'woocommerce' === $submenu ) {
			?>
			<script>
				jQuery('.settings-submenu a').removeClass('current');
				jQuery('.settings-submenu a#woocommerce-settings').addClass('current');
			</script>
			<?php
			$message  = __( 'Missing Module!', 'post-type-x' );
			$message .= '<br>';
			$message .= '<br>';
			/* translators: 1: extension name, 2: opening catalog extensions link, 3: closing catalog extensions link. */
			$message .= sprintf( __( 'Please install free %1$s extension from %2$scatalog extensions menu%3$s.', 'post-type-x' ), 'WooCommerce Catalog', '<a href="' . esc_url( admin_url( 'edit.php?post_type=al_product&page=extensions.php&tab=product-extensions' ) ) . '">', '</a>' );
			$message .= '<br>';
			$message .= '<br>';
			$message .= __( 'You will be able to customize the WooCommerce catalog design, disable cart, enable inquiry and many more.', 'post-type-x' );
			$message .= '<br>';
			$message .= '<br>';
			/* translators: 1: opening import settings link, 2: closing import settings link. */
			$message .= sprintf( __( 'You can also %1$simport WooCommerce products into separate catalog%2$s.', 'post-type-x' ), '<a href="' . esc_url( admin_url( 'edit.php?post_type=al_product&page=product-settings.php&tab=product-settings&submenu=csv' ) ) . '">', '</a>' );
			implecode_info( $message );
			?>
			<?php
		}
	}
}
