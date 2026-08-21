<?php
/**
 * Support settings screen helpers.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'implecode_support_menu' ) ) :

	/**
	 * Prints the support submenu link.
	 *
	 * @return void
	 */
	function implecode_custom_support_menu() {
		?>
		<a id="support-settings" class="element"
			href="<?php echo esc_url( admin_url( 'edit.php?post_type=al_product&page=product-settings.php&tab=product-settings&submenu=support' ) ); ?>"><?php esc_html_e( 'Support', 'post-type-x' ); ?></a>
		<?php
	}

	add_action( 'general_submenu', 'implecode_custom_support_menu', 20 );

	/**
	 * Renders the support settings tab content.
	 *
	 * @return void
	 */
	function implecode_custom_support_settings_content() {
		$submenu              = filter_input( INPUT_GET, 'submenu', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$support_bold_markup  = array( 'b' => array() );
		$support_link_markup  = array( 'a' => array( 'href' => array() ) );
		$support_mixed_markup = array_merge( $support_bold_markup, $support_link_markup );
		?>
		<?php if ( 'support' === $submenu ) { ?>
			<div class="setting-content submenu support-tab">
				<script>
					jQuery('.settings-submenu a').removeClass('current');
					jQuery('.settings-submenu a#support-settings').addClass('current');
				</script>
				<style>
					.setting-content p {
						max-width: 800px;
					}
				</style>
				<h2><?php esc_html_e( 'impleCode Support', 'post-type-x' ); ?></h2>
				<p>
					<?php
					/* translators: %s: plugin name. */
					echo wp_kses( sprintf( __( '<b>%s is free to use</b>. That\'s great! It\'s a pleasure to serve it to you. Let\'s keep it free forever!', 'post-type-x' ), esc_html( IC_CATALOG_PLUGIN_NAME ) ), $support_bold_markup );
					?>
				</p>
				<p>
					<?php
					echo wp_kses( __( 'This awesome plugin is being developed under impleCode brand, which is a legally operating European company. It means that you can be assured that the high-quality <b>development will be continuous</b>.', 'post-type-x' ), $support_bold_markup );
					?>
				</p>
				<p>
					<?php
					/* translators: 1: opening support forum link, 2: closing support forum link. */
					echo wp_kses( sprintf( __( 'For <b>free support</b>, please visit %1$ssupport forums%2$s where the plugin developers will give you valuable advice.', 'post-type-x' ), '<a href="' . esc_url( 'https://wordpress.org/support/plugin/' . IC_CATALOG_PLUGIN_SLUG ) . '">', '</a>' ), $support_mixed_markup );
					?>
				</p>
				<p>
					<strong><?php esc_html_e( 'Please consider upgrading to support the project.', 'post-type-x' ); ?></strong>
				</p>
				<div style="clear: both; height: 10px;"></div>
				<div class="extension premium-support">
						<a href="https://implecode.com/wordpress/plugins/premium-support/#cam=catalog-support-tab&key=support-link">
							<h3><span><?php esc_html_e( 'Premium Toolset', 'post-type-x' ); ?></span></h3></a>
					<p><?php esc_html_e( 'An upgrade that will add many valuable features to the catalog functionality!', 'post-type-x' ); ?></p>
					<p>
						<?php
						/* translators: 1: opening premium support link, 2: closing premium support link, 3: price. */
						echo wp_kses( sprintf( __( 'One year of high quality and speedy %1$sPremium Support%2$s from the developers for just %3$s.', 'post-type-x' ), '<a href="https://implecode.com/wordpress/plugins/premium-support/#cam=catalog-support-tab&key=support-link">', '</a>', '$49' ), $support_link_markup );
						?>
					</p>
					<form style="text-align: center; position: relative; top: 10px;"
							action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
						<input type="hidden" name="cmd" value="_s-xclick">
						<input type="hidden" name="hosted_button_id" value="LCRGR95EST66S">
						<input style="cursor:pointer;" type="image"
								src="https://www.paypalobjects.com/en_US/i/btn/btn_buynowCC_LG.gif" border="0"
								name="submit" alt="PayPal - The safer, easier way to pay online!">
					</form>
				</div>
				<div class="extension premium-support">
						<a href="https://implecode.com/wordpress/plugins/#extensions&cam=catalog-support-tab&key=extensions-link">
							<h3><span><?php echo esc_html( IC_CATALOG_PLUGIN_NAME ); ?> <?php esc_html_e( 'Extensions', 'post-type-x' ); ?></span></h3></a>
					<p>
						<?php
						/* translators: 1: SEO URL, 2: usability URL, 3: productivity URL, 4: conversion URL, 5: plugin name. */
						echo wp_kses( sprintf( __( '<b>Extensions apart of premium support</b> provide additional useful features. They improve %5$s in a field of <a href="%1$s">SEO</a>, <a href="%2$s">Usability</a>, <a href="%3$s">Productivity</a> and <a href="%4$s">Conversion</a>.', 'post-type-x' ), esc_url( 'https://implecode.com/wordpress/plugins/#seo_usability_boosters&cam=catalog-support-tab&key=extensions-link-seo' ), esc_url( 'https://implecode.com/wordpress/plugins/#seo_usability_boosters&cam=catalog-support-tab&key=extensions-link-usability' ), esc_url( 'https://implecode.com/wordpress/plugins/#productivity_boosters&cam=catalog-support-tab&key=extensions-link-productivity' ), esc_url( 'https://implecode.com/wordpress/plugins/#conversion_boosters&cam=catalog-support-tab&key=extensions-link-conversion' ), esc_html( IC_CATALOG_PLUGIN_NAME ) ), $support_mixed_markup );
						?>
					</p>
					<p>
						<a href="https://implecode.com/wordpress/plugins/#extensions&cam=catalog-support-tab&key=extensions-link"><input
									style="cursor:pointer;" class="button-primary" type="button"
									value="Check out the extensions &raquo;"></a></p>
				</div>
				<div style="clear: both; height: 10px;"></div>
					<h2><?php esc_html_e( 'Premium Toolset Features', 'post-type-x' ); ?></h2>
					<p>
						<?php
						/* translators: %s: plugin name. */
						echo wp_kses( sprintf( __( 'Apart of fast, confidential email support <b>you will receive some advanced features</b> for %s.', 'post-type-x' ), esc_html( IC_CATALOG_PLUGIN_NAME ) ), $support_bold_markup );
						?>
					</p>
					<h4><?php esc_html_e( 'Premium Features:', 'post-type-x' ); ?></h4>
				<ol>
						<li><strong><?php esc_html_e( 'Alternative Products', 'post-type-x' ); ?></strong>
							- <?php esc_html_e( 'select alternative products for each product and display them in a separate tab or section', 'post-type-x' ); ?>
						;
					</li>
						<li><strong><?php esc_html_e( 'Alternative Products Table', 'post-type-x' ); ?></strong>
							- <?php esc_html_e( 'display alternative products as a table with many different parameters to compare', 'post-type-x' ); ?>
						;
					</li>
						<li><strong><?php esc_html_e( 'Price Filter Ranges', 'post-type-x' ); ?></strong>
							- <?php esc_html_e( 'set clickable price filter ranges in the price filter widget', 'post-type-x' ); ?>
						;
					</li>
						<li><strong><?php esc_html_e( 'Category template', 'post-type-x' ); ?></strong>
							- <?php esc_html_e( 'select different template for categories', 'post-type-x' ); ?>;
					</li>
						<li><strong><?php esc_html_e( 'Disabled default image', 'post-type-x' ); ?></strong>
							- <?php esc_html_e( 'disable default image for category pages', 'post-type-x' ); ?>;
					</li>
						<li><strong><?php esc_html_e( 'Disabled category name', 'post-type-x' ); ?></strong>
							- <?php esc_html_e( 'disable category name - useful if your category image already has the name included', 'post-type-x' ); ?>
						;
					</li>
						<li><strong><?php esc_html_e( 'Automatic Product Descriptions', 'post-type-x' ); ?></strong>
							- <?php esc_html_e( 'generate product descriptions from category descriptions', 'post-type-x' ); ?>
						;
					</li>
						<li><strong><?php esc_html_e( 'Enhanced category filter', 'post-type-x' ); ?></strong>
							- <?php esc_html_e( 'only bottom category clickable or always show child categories', 'post-type-x' ); ?>
						;
					</li>
						<li><strong><?php esc_html_e( 'Checkbox category filter', 'post-type-x' ); ?></strong>
							- <?php esc_html_e( 'checkbox instead of links for category filter', 'post-type-x' ); ?>;
					</li>
						<li><strong><?php esc_html_e( 'Alternative listing template', 'post-type-x' ); ?></strong>
							- <?php esc_html_e( 'let the user switch the listing template on front-end - for example between grid or list.', 'post-type-x' ); ?>
						;
					</li>
						<li><strong><?php esc_html_e( 'Product Count widget', 'post-type-x' ); ?></strong>
							- <?php esc_html_e( 'product count widget that shows the number of displayed products', 'post-type-x' ); ?>
						;
					</li>
						<li><strong><?php esc_html_e( 'Product Pagination widget', 'post-type-x' ); ?></strong>
							- <?php esc_html_e( 'display pagination in any widget area', 'post-type-x' ); ?>;
					</li>
						<li><strong><?php esc_html_e( 'Product Promo widget', 'post-type-x' ); ?></strong>
							- <?php esc_html_e( 'display selected or random product in widget area', 'post-type-x' ); ?>
						;
					</li>
						<li><strong><?php esc_html_e( 'Product tags', 'post-type-x' ); ?></strong>
							- <?php esc_html_e( 'which is considered as SEO booster if used properly', 'post-type-x' ); ?>
						;
					</li>
						<li><strong><?php esc_html_e( 'Product tags filter', 'post-type-x' ); ?></strong>
							- <?php esc_html_e( 'filter products by tags', 'post-type-x' ); ?>;
					</li>
						<li><strong><?php esc_html_e( 'Separate sidebar', 'post-type-x' ); ?></strong>
							- <?php esc_html_e( 'on main listing, individual product, catalog search and category pages', 'post-type-x' ); ?>
						;
					</li>
						<li><strong><?php esc_html_e( 'Responsive sidebar', 'post-type-x' ); ?></strong>
							- <?php esc_html_e( 'select styling and appearance for sidebar on small screens', 'post-type-x' ); ?>
						;
					</li>
						<li><strong><?php esc_html_e( 'More styling', 'post-type-x' ); ?></strong>
							- <?php esc_html_e( 'set product sidebar and product description width', 'post-type-x' ); ?>
						;
					</li>
						<li><strong><?php esc_html_e( 'Category widget enhancement', 'post-type-x' ); ?></strong>
							- <?php esc_html_e( 'Show child categories only on parent category pages', 'post-type-x' ); ?>
						.
					</li>
				</ol>
					<p><?php esc_html_e( 'Go ahead and use the Buy Now button above to receive the premium support service and the described features immediately. You will receive the premium extension on your PayPal email address immediately after the payment is confirmed.', 'post-type-x' ); ?></p>
					<p>
						<?php
						/* translators: %s: impleCode premium support URL. */
						echo wp_kses( sprintf( __( 'If you need to get it on a different email address, please use the <a href="%s">impleCode website to order the premium support</a>. It will let you set a different email address than the one for PayPal.', 'post-type-x' ), esc_url( 'https://implecode.com/wordpress/plugins/premium-support/#cam=catalog-support-tab&key=support-link-1' ) ), $support_link_markup );
						?>
					</p>
					<p>
						<?php
						/* translators: 1: opening extensions link, 2: closing extensions link. */
						echo wp_kses( sprintf( __( 'You can also choose one of the %1$scatalog extensions%2$s to get the premium support.', 'post-type-x' ), '<a href="https://implecode.com/wordpress/plugins/#cam=catalog-support-tab&key=extensions-link">', '</a>' ), $support_link_markup );
						?>
					</p>
					<h2><?php esc_html_e( 'Theme Integration', 'post-type-x' ); ?></h2>
					<p>
						<?php
						/* translators: 1: plugin name, 2: theme integration guide URL, 3: advanced theme integration URL, 4: premium support URL. */
						echo wp_kses( sprintf( __( 'As you may already know some themes may need Theme Integration to support %1$s fully. We wrote this <a href="%2$s">theme integrations guide</a>, however, to make it even easier you will get <a href="%3$s">Advanced Theme Integration</a> service for free if you choose <a href="%4$s">Premium Support</a> service.', 'post-type-x' ), esc_html( IC_CATALOG_PLUGIN_NAME ), esc_url( 'https://implecode.com/wordpress/product-catalog/theme-integration-guide/#cam=catalog-support-tab&key=integration-link' ), esc_url( 'https://implecode.com/wordpress/plugins/advanced-theme-integration/#cam=catalog-support-tab&key=integration-service-link' ), esc_url( 'https://implecode.com/wordpress/plugins/premium-support/#cam=catalog-support-tab&key=support-link-2' ) ), $support_link_markup );
						?>
					</p>
					<h2>
						<?php
						/* translators: %s: plugin name. */
						printf( esc_html__( '%s documentation', 'post-type-x' ), esc_html( IC_CATALOG_PLUGIN_NAME ) );
						?>
					</h2>
					<p>
						<?php
						/* translators: 1: documentation URL, 2: support forum URL, 3: premium support URL, 4: plugin name. */
						echo wp_kses( sprintf( __( '<b>%4$s</b> documentation is being developed <a href="%1$s">here</a>. For questions about %4$s please use <a href="%2$s">support forum</a> or <a href="%3$s">Premium Support service</a>.', 'post-type-x' ), esc_url( 'https://implecode.com/wordpress/product-catalog/#cam=catalog-support-tab&key=docs-link' ), esc_url( 'http://wordpress.org/support/plugin/ecommerce-product-catalog' ), esc_url( 'https://implecode.com/wordpress/plugins/premium-support/#cam=catalog-support-tab&key=support-link-3' ), esc_html( IC_CATALOG_PLUGIN_NAME ) ), $support_mixed_markup );
						?>
					</p>
			</div>
			<div class="helpers">
			<div class="wrapper">
			<?php
				ic_epc_main_helper();
				ic_epc_did_know_helper( 'support', __( 'You can get instant premium support from plugin developers', 'post-type-x' ), 'https://implecode.com/wordpress/plugins/premium-support/' );
				ic_epc_bug_report_helper();
				ic_epc_review_helper();
			?>
			</div></div>
			<?php
		}
	}

	add_action( 'product-settings', 'implecode_custom_support_settings_content' );

	add_action( 'admin_init', 'ic_disable_ic_updater', 4 );

	/**
	 * Disables premium updates and support on demand
	 */
	function ic_disable_ic_updater() {
		if ( 1 === (int) get_option( 'ic_disable_license_message' ) ) {
			add_action( 'admin_init', 'ic_disable_license_message', 6 );
		}
		if ( 1 === (int) get_option( 'ic_disable_ic_updater' ) ) {
			if ( ! function_exists( 'start_implecode_updater' ) ) {
				/**
				 * Prevents the updater bootstrap from loading.
				 *
				 * @return void
				 */
				function start_implecode_updater() {
				}
			}
			if ( ! function_exists( 'implecode_support_menu' ) ) {
				/**
				 * Prevents the support menu from loading.
				 *
				 * @return void
				 */
				function implecode_support_menu() {
				}
			}
		}
	}

	/**
	 * Disables premium license check message
	 */
	function ic_disable_license_message() {
		remove_action( 'admin_init', 'check_if_license_exists', 99 );
	}


endif;
