<?php
/**
 * Welcome screen start content.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( file_exists( AL_BASE_PATH . '/modules/cart/index.php' ) ) {
	?>
	<div class="ic-select-mode-container">
		<div class="about__section is-feature has-subtle-background-color">
			<h2>
				<?php
				/* translators: %s: Plugin name. */
				printf( esc_html__( 'Choose your preferred %s configuration.', 'post-type-x' ), esc_html( IC_CATALOG_PLUGIN_NAME ) );
				?>
			</h2>
			<p>
				<?php esc_html_e( 'Use the buttons below to choose the catalog mode. Simple Catalog is enabled by default.', 'post-type-x' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'You can make additional adjustments in the settings later.', 'post-type-x' ); ?>
			</p>
		</div>

		<hr/>

		<div class="about__section has-2-columns">
			<div class="column">
				<h2><?php esc_html_e( 'Web Store', 'post-type-x' ); ?></h2>
				<p><?php esc_html_e( 'Enable this option if you are planning to sell products directly from the website.', 'post-type-x' ); ?></p>
				<p><?php esc_html_e( 'The shopping cart feature will be enabled in this mode.', 'post-type-x' ); ?></p>
				<p class="ic-select-mode-button-container">
					<a class="button-primary"
						href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'mode', 'store', admin_url( 'edit.php?post_type=al_product&page=implecode_welcome' ) ), 'ic_catalog_mode_selection' ) ); ?>">
						<?php esc_html_e( 'Enable Web Store Mode', 'post-type-x' ); ?>
					</a>
				</p>
			</div>
			<div class="column">
				<h2><?php esc_html_e( 'Inquiry Catalog', 'post-type-x' ); ?></h2>
				<p><?php esc_html_e( 'Enable this option if you want the customers to ask for price.', 'post-type-x' ); ?></p>
				<p><?php esc_html_e( 'The quote cart feature will be enabled in this mode.', 'post-type-x' ); ?></p>
				<p class="ic-select-mode-button-container">
					<a class="button-primary"
						href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'mode', 'inquiry', admin_url( 'edit.php?post_type=al_product&page=implecode_welcome' ) ), 'ic_catalog_mode_selection' ) ); ?>">
						<?php esc_html_e( 'Enable Inquiry Catalog Mode', 'post-type-x' ); ?>
					</a>
				</p>
			</div>
		</div>

		<hr/>

		<div class="about__section has-2-columns has-subtle-background-color">
			<div class="column">
				<h2><?php esc_html_e( 'Affiliate Catalog', 'post-type-x' ); ?></h2>
				<p><?php esc_html_e( 'Enable this option if you want the customers to click the affiliate button.', 'post-type-x' ); ?></p>
				<p><?php esc_html_e( 'The affiliate button feature will be enabled in this mode.', 'post-type-x' ); ?></p>
				<p class="ic-select-mode-button-container">
					<a class="button-primary"
						href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'mode', 'affiliate', admin_url( 'edit.php?post_type=al_product&page=implecode_welcome' ) ), 'ic_catalog_mode_selection' ) ); ?>">
						<?php esc_html_e( 'Enable Affiliate Catalog Mode', 'post-type-x' ); ?>
					</a>
				</p>
			</div>
			<div class="column">
				<h2><?php esc_html_e( 'Simple Catalog', 'post-type-x' ); ?></h2>
				<p><?php esc_html_e( 'Enable this option if you want to display products without any call to action.', 'post-type-x' ); ?></p>
				<p class="ic-select-mode-button-container">
					<a class="button-primary"
						href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'mode', 'simple', admin_url( 'edit.php?post_type=al_product&page=implecode_welcome' ) ), 'ic_catalog_mode_selection' ) ); ?>">
						<?php esc_html_e( 'Enable Simple Catalog Mode', 'post-type-x' ); ?>
					</a>
				</p>
			</div>
		</div>

		<hr/>
	</div>
	<?php
}
?>
	<div class="about__section has-subtle-background-color has-2-columns">
		<header class="is-section-header">
			<h2><?php esc_html_e( 'For every day users', 'post-type-x' ); ?></h2>
			<p>
				<?php
				/* translators: %s: Plugin name. */
				printf( esc_html__( '%s is carefully crafted for seamless usability by everyday users.', 'post-type-x' ), esc_html( IC_CATALOG_PLUGIN_NAME ) );
				?>
			</p>
		</header>
		<div class="column">
			<h3><?php esc_html_e( 'Catalog Blocks', 'post-type-x' ); ?></h3>
			<p><?php esc_html_e( 'You can use three different blocks to display catalog parts.', 'post-type-x' ); ?></p>
			<p>
				<?php
				/* translators: 1: "Show Catalog" block name, 2: "Show Products" block name. */
				printf( esc_html__( 'Start with %1$s block to display your main listing page or %2$s to display only selected products.', 'post-type-x' ), esc_html__( 'Show Catalog', 'post-type-x' ), esc_html__( 'Show Products', 'post-type-x' ) );
				?>
			</p>
			<p>
				<a href="https://implecode.com/docs/ecommerce-product-catalog/all-product-catalog-blocks/#cam=welcome&key=blocks"><?php esc_html_e( 'Blocks usage', 'post-type-x' ); ?></a>
			</p>
		</div>
		<div class="column">
			<h3><?php esc_html_e( 'Customize Headings and Text', 'post-type-x' ); ?></h3>
			<p><?php esc_html_e( 'You have complete control over tailoring any text visible on the front-end through an intuitive settings screen.', 'post-type-x' ); ?></p>
			<p><?php esc_html_e( 'You can make the catalog multilingual thanks to full Polylang compatibility.', 'post-type-x' ); ?></p>
			<p><a target="_blank"
					href="https://implecode.com/docs/ecommerce-product-catalog/change-catalog-headings-text/#cam=welcome&key=text-customization"><?php esc_html_e( 'Headings & text customization', 'post-type-x' ); ?></a>
				| <a target="_blank"
					href="https://implecode.com/docs/ecommerce-product-catalog/polylang-and-ecommerce-product-catalog/#cam=welcome&key=polylang"><?php esc_html_e( 'EPC & Polylang', 'post-type-x' ); ?></a>
			</p>
		</div>
	</div>
	<div class="about__section has-subtle-background-color has-2-columns">
		<header class="is-section-header">
			<h2><?php esc_html_e( 'For developers', 'post-type-x' ); ?></h2>
			<p>
				<?php
				/* translators: %s: Plugin name. */
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
				<img src="<?php echo esc_url( AL_PLUGIN_BASE_PATH . 'img/example-customization-feedback.png' ); ?>" alt="">
			</figure>
		</div>
	</div>

	<hr class="is-small"/>

	<div class="about__section">
		<div class="column">
			<h3><?php esc_html_e( 'Check the documentation for more!', 'post-type-x' ); ?></h3>
			<p>
				<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: a translated string that deliberately contains HTML, so it cannot be escaped wholesale; the values interpolated into it are escaped individually on this line.
					printf( /* translators: 1: Current EPC version number. 2: Opening documentation link. 3: Closing link. */ __( 'There’s a lot more for developers to love in %1$s. To discover more and learn how to make the catalog shine on your sites, themes, plugins and more, check the %2$sdocumentation.%3$s', 'post-type-x' ), esc_html( IC_CATALOG_PLUGIN_NAME . ' ' . IC_CATALOG_VERSION ), '<a href="' . esc_url( 'https://implecode.com/docs/#cam=welcome&key=docs' ) . '">', '</a>' );
				?>
			</p>
		</div>
	</div>
