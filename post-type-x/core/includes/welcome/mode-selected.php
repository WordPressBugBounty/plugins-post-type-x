<?php
/**
 * Welcome screen content for a selected catalog mode.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only welcome screen state.
$selected_mode = isset( $_GET['selected_mode'] ) ? sanitize_text_field( wp_unslash( $_GET['selected_mode'] ) ) : '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only welcome screen state.
$activation_choice = isset( $_GET['ic_catalog_activation_choice'] ) ? sanitize_text_field( wp_unslash( $_GET['ic_catalog_activation_choice'] ) ) : '';
ob_start();
do_action( 'ic_epc_mode_selected', $selected_mode );
$additional_content = ob_get_clean();
?>
	<style>


		.ic_cat-activation-wizard .bottom-container {
			display: none;
		}

		.ic_cat-activation-question input {
			font-size: inherit;
		}

		.ic_cat-activation-question span.ic_tip {
			position: relative;
			top: 5px;
		}

		.ic-mode-selected-container {
			background: #fff;
		}

		.ic-mode-selected-container h1 {
			text-align: center;
		}
	</style>
	<div class="about__section is-feature ic-mode-selected-container">
		<?php
		if ( empty( $activation_choice ) ) {
			/* translators: %s: selected catalog mode label. */
			echo '<h1>' . sprintf( esc_html__( '%s mode is active now!', 'post-type-x' ), esc_html( ic_ucfirst( $selected_mode ) ) ) . '</h1>';
		}
		?>
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: HTML assembled earlier in this scope, so escaping it here would print the tags.
		echo $additional_content;
		?>
		<div class="column">
			<p style="text-align: center;"><a
						href="<?php echo esc_url( admin_url( 'edit.php?post_type=al_product&page=implecode_welcome' ) ); ?>"><?php esc_html_e( 'GO BACK TO MODE SELECTION', 'post-type-x' ); ?></a>
			</p>
		</div>
	</div>

	<hr/>

	<div class="about__section has-2-columns">
		<header class="is-section-header">
			<h2><?php esc_html_e( 'Display Catalog', 'post-type-x' ); ?></h2>
			<p><?php esc_html_e( 'You can select the main catalog page in the general settings screen. It will show categories and products according to the catalog settings.', 'post-type-x' ); ?></p>
			<p><?php esc_html_e( 'Apart from the main catalog page, you can display products and categories anywhere on the website.', 'post-type-x' ); ?></p>
		</header>
		<?php if ( function_exists( 'register_block_type' ) ) { ?>
			<div class="column">
				<h3><?php esc_html_e( 'Catalog Blocks', 'post-type-x' ); ?></h3>
				<p><?php esc_html_e( 'You can use three different blocks to display catalog parts.', 'post-type-x' ); ?></p>
				<p>
					<a href="https://implecode.com/docs/ecommerce-product-catalog/all-product-catalog-blocks/#cam=welcome&key=blocks"><?php esc_html_e( 'Blocks usage', 'post-type-x' ); ?></a>
				</p>
			</div>
		<?php } ?>
		<div class="column">
			<h3><?php esc_html_e( 'Catalog Shortcodes', 'post-type-x' ); ?></h3>
			<p><?php esc_html_e( 'You can use many different shortcodes to displays catalog parts.', 'post-type-x' ); ?></p>
			<p>
				<a href="https://implecode.com/docs/ecommerce-product-catalog/product-catalog-shortcodes/#cam=welcome&key=shortcodes"><?php esc_html_e( 'Available Shortcodes', 'post-type-x' ); ?></a>
			</p>
		</div>
	</div>

	<hr/>

	<div class="about__section has-subtle-background-color has-2-columns">
		<header class="is-section-header">
			<h2><?php esc_html_e( 'For developers', 'post-type-x' ); ?></h2>
			<p>
				<?php
				/* translators: %s: plugin name. */
				printf( esc_html__( '%s is designed to make it easy for developers to customize things.', 'post-type-x' ), esc_html( IC_CATALOG_PLUGIN_NAME ) );
				?>
			</p>
		</header>
		<div class="column">
			<h3><?php esc_html_e( 'Theme integration', 'post-type-x' ); ?></h3>
			<p><?php esc_html_e( 'Even if the catalog works fine with any theme, you can take full control of the output.', 'post-type-x' ); ?></p>
			<p><a taget="_blank"
					href="https://implecode.com/wordpress/product-catalog/theme-integration-guide/#theme_integration&cam=welcome&key=theme-integration-guide"><?php esc_html_e( 'Check the advanced theme integration method', 'post-type-x' ); ?></a>
			</p>
		</div>
		<div class="column">
			<h3><?php esc_html_e( 'Template Customization', 'post-type-x' ); ?></h3>
			<p><?php esc_html_e( "You can customize the output by placing the template file in your theme 'implecode' folder.", 'post-type-x' ); ?></p>
			<p><?php esc_html_e( 'All the templates are located in the plugin templates folder.', 'post-type-x' ); ?></p>
			<p><a target="_blank"
					href="https://implecode.com/docs/ecommerce-product-catalog/product-page-template/#cam=welcome&key=product-page-template"><?php esc_html_e( 'Check the details about template modification', 'post-type-x' ); ?></a>
			</p>
		</div>
	</div>

	<div class="about__section has-subtle-background-color has-2-columns">
		<div class="column">
			<h3><?php esc_html_e( 'Shortcodes', 'post-type-x' ); ?></h3>
			<p><?php esc_html_e( 'You can use many shortcodes to display the entire catalog or even each smallest part.', 'post-type-x' ); ?></p>
			<p><a target="_blank"
					href="https://implecode.com/docs/ecommerce-product-catalog/product-catalog-shortcodes/#cam=welcome&key=product-catalog-shortcodes"><?php esc_html_e( 'Check all the shortcodes', 'post-type-x' ); ?></a>
			</p>
		</div>
		<div class="column">
			<h3><?php esc_html_e( 'CSS & PHP code snippets', 'post-type-x' ); ?></h3>
			<p><?php esc_html_e( 'We keep the list of most useful code snippets to adjust things.', 'post-type-x' ); ?></p>
			<p><a target="_blank"
					href="https://implecode.com/docs/ecommerce-product-catalog/css-adjustments/#cam=welcome&key=css"><?php esc_html_e( 'CSS code snippets', 'post-type-x' ); ?></a>
				| <a target="_blank"
					href="https://implecode.com/docs/ecommerce-product-catalog/php-adjustments/#cam=welcome&key=php"><?php esc_html_e( 'PHP code snippets', 'post-type-x' ); ?></a>
			</p>
		</div>
	</div>

	<div class="about__section has-2-columns has-subtle-background-color is-wider-right">
		<div class="column">
			<h3><?php esc_html_e( 'Catalog Custom Coding', 'post-type-x' ); ?></h3>
			<p><?php esc_html_e( 'If you need a custom feature, do not hesitate to contact the developers.', 'post-type-x' ); ?></p>
			<p><?php esc_html_e( 'We know the plugin and WordPress to the ground, can adjust small things and create very complex features or integrations.', 'post-type-x' ); ?></p>
			<p><?php esc_html_e( 'We provide custom coding services in a professional and timely manner.', 'post-type-x' ); ?></p>
			<p><a href="https://implecode.com/support/?support_type=custom_job#cam=welcome&key=support"
					class="button-primary"
					target="_blank"><?php esc_html_e( 'Contact the developers', 'post-type-x' ); ?></a></p>
		</div>
		<div class="column about__image is-vertically-aligned-center">
			<figure aria-labelledby="about-block-pattern" class="about__image">
				<img src="<?php echo esc_url( AL_PLUGIN_BASE_PATH . 'img/example-customization-feedback.png' ); ?>">
			</figure>
		</div>
	</div>

	<hr class="is-small"/>

	<div class="about__section">
		<div class="column">
			<h3><?php esc_html_e( 'Check the documentation for more!', 'post-type-x' ); ?></h3>
			<p>
				<?php
				/*
				 * translators:
				 * 1: Plugin name and version.
				 * 2: Opening documentation link.
				 * 3: Closing documentation link.
				 */
				$documentation_template = esc_html__( 'There’s a lot more for developers to love in %1$s. To discover more and learn how to make the catalog shine on your sites, themes, plugins and more, check the %2$sdocumentation.%3$s', 'post-type-x' );
				$documentation_text     = sprintf(
					$documentation_template,
					esc_html( IC_CATALOG_PLUGIN_NAME . ' ' . IC_CATALOG_VERSION ),
					'<a href="' . esc_url( 'https://implecode.com/docs/#cam=welcome&key=docs' ) . '">',
					'</a>'
				);
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: HTML assembled earlier in this scope, so escaping it here would print the tags.
				echo $documentation_text;
				?>
			</p>
		</div>
	</div>
<?php
