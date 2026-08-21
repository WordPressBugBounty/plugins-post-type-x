<?php
/**
 * Customizer helper controls.
 *
 * @created Apr 9, 2015
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( class_exists( 'WP_Customize_Control' ) ) {

	/**
	 * Control to display info.
	 */
	class More_Catalog_ImpleCode_Control extends WP_Customize_Control {

		/**
		 * Renders the control markup.
		 *
		 * @return void
		 */
		public function render_content() {
			?>
			<label style="overflow: hidden; zoom: 1;">
				<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
				<p>
					<?php
					/* translators: 1: opening catalog settings link, 2: closing catalog settings link. */
					$message = sprintf( __( 'Check the %1$scatalog settings%2$s for more advanced configuration options.', 'post-type-x' ), '<a href="' . esc_url( admin_url( 'edit.php?post_type=al_product&page=product-settings.php' ) ) . '">', '</a>' );
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: HTML assembled earlier in this scope, so escaping it here would print the tags.
					echo $message;
					?>
				</p>
				<p>
					<?php
					/* translators: 1: opening extensions page link, 2: closing extensions page link. */
					$message = sprintf( __( 'There\'s also a range of %1$sCatalog add-ons%2$s available to put additional power in your hands. Check out the %1$sextensions page%2$s in your dashboard for more information.', 'post-type-x' ), '<a href="' . esc_url( admin_url( 'edit.php?post_type=al_product&page=extensions.php' ) ) . '">', '</a>' );
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: HTML assembled earlier in this scope, so escaping it here would print the tags.
					echo $message;
					?>
				</p>
			</label>
			<?php
		}
	}

}
