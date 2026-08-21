<?php
/**
 * Extension info settings class.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Manages extension promotion boxes in settings.
 */
class IC_Extension_Settings_Info {

	/**
	 * Registers hooks for extension promotion boxes.
	 */
	public function __construct() {
		add_action( 'ic_simple_csv_bottom', array( $this, 'product_csv' ) );
		add_action( 'general_submenu', array( $this, 'extensions_info' ), 99 );
		add_filter( 'admin_product_details', array( $this, 'extensions_info_add' ), 99 );
		add_action( 'ic_EPC_first_activation', array( $this, 'delay' ) );
	}

	/**
	 * Wraps extension info content in a styled box.
	 *
	 * @param string $content Box content.
	 *
	 * @return string
	 */
	public function info_box( $content ) {
		$box  = '<div class="extension-info-box">';
		$box .= $content;
		$box .= '</div>';

		return $box;
	}

	/**
	 * Shows the Product CSV promo box.
	 *
	 * @return void
	 */
	public function product_csv() {
		if ( current_action() !== 'ic_plugin_logo_container' ) {
			add_action( 'ic_plugin_logo_container', array( $this, 'product_csv' ) );
		} else {
			/* translators: 1: opening Product CSV link, 2: closing Product CSV link. */
			$info = sprintf( __( 'With %1$sProduct CSV%2$s you can import, export and update an unlimited number of products at once even on a very limited server. It also supports all product fields and will import external images to the WordPress media library. You can also enjoy scheduled sync and many more features that come with the premium support service!', 'post-type-x' ), '<a href="https://implecode.com/wordpress/plugins/product-csv/#cam=extension-info&key=product-csv">', '</a>' );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: built by info_box().
			echo $this->info_box( $info );
		}
	}

	/**
	 * Prepends extension info to admin product details output.
	 *
	 * @param string $content Existing output.
	 *
	 * @return string
	 */
	public function extensions_info_add( $content ) {
		ob_start();
		$this->extensions_info();
		$content = ob_get_clean() . $content;

		return $content;
	}

	/**
	 * Renders the extensions info link in settings.
	 *
	 * @return void
	 */
	public function extensions_info() {
		if ( ! function_exists( 'start_implecode_updater' ) && ! $this->hidden() ) {
			$extensions_url = admin_url( 'edit.php?post_type=al_product&page=extensions.php' );
			/* translators: 1: opening extensions page link, 2: closing extensions page link. */
			$message = sprintf( __( 'More free & premium features %1$shere%2$s.', 'post-type-x' ), '<a href="' . esc_url( $extensions_url ) . '">', '</a>' );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: HTML assembled earlier in this scope, so escaping it here would print the tags.
			echo '<span class="extensions-promo-box">' . $message . '</span>';
		}
	}

	/**
	 * Checks whether the extension box is hidden for the current user.
	 *
	 * @return bool
	 */
	public function hidden() {
		$hidden = get_user_meta( get_current_user_id(), 'ic_extensions_box_hidden', true );
		if ( $hidden ) {
			return true;
		}
		if ( false === get_site_transient( 'implecode_hide_extensions_box' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Delays the extension box display after activation.
	 *
	 * @return void
	 */
	public function delay() {
		set_site_transient( 'implecode_hide_extensions_box', 1, 3 * DAY_IN_SECONDS );
	}
}
