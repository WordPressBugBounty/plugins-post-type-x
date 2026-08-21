<?php
/**
 * Catalog frontend admin controls.
 *
 * @package ecommerce-product-catalog/functions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Manages frontend admin actions for catalog pages.
 */
class IC_Catalog_Frontend_Admin {

	/**
	 * Registers frontend admin hooks.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_bar_menu', array( $this, 'edit_product_listing' ), 999 );
		add_action( 'ic_product_admin_actions', array( $this, 'admin_actions_container' ), 10, 1 );
		add_action( 'ic_product_meta', array( $this, 'customize_product_page' ) );
		add_action( 'ic_product_meta', array( $this, 'customize_product_listing' ) );
		add_action( 'ic_listing_meta', array( $this, 'customize_product_listing' ) );
		add_action( 'ic_listing_meta', array( $this, 'customize_filters_bar' ) );
		add_action( 'ic_listing_meta', array( $this, 'customize_icons' ) );
		add_action( 'before_product_listing_entry', array( $this, 'listing_options' ) );
		add_action( 'ic_listing_admin_actions', array( $this, 'admin_listing_actions_container' ) );
	}

	/**
	 * Outputs listing admin actions when the current user can edit products.
	 *
	 * @return void
	 */
	public function listing_options() {
		if ( current_user_can( 'edit_products' ) ) {
			do_action( 'ic_listing_admin_actions' );
		}
	}

	/**
	 * Adds the edit listing link to the admin bar.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 *
	 * @return void
	 */
	public function edit_product_listing( $wp_admin_bar ) {
		$listing_id = get_product_listing_id();
		$query      = ic_get_global( 'pre_shortcode_query' );
		if ( ! empty( $listing_id ) && is_ic_product_listing( $query ) && 'noid' !== $listing_id && current_user_can( 'edit_pages' ) ) {
			if ( is_plural_form_active() ) {
				$names = get_catalog_names();
				/* translators: %s: singular catalog item label. */
				$label = sprintf( __( 'Edit %s Listing', 'post-type-x' ), ic_ucfirst( $names['singular'] ) );
			} else {
				$label = __( 'Edit Product Listing', 'post-type-x' );
			}
			$args = array(
				'id'    => 'edit',
				'title' => $label,
				'href'  => admin_url( 'post.php?post=' . $listing_id . '&action=edit' ),
				'meta'  => array( 'class' => 'edit-products-page' ),
			);
			$wp_admin_bar->add_node( $args );
		}
	}

	/**
	 * Outputs product admin actions.
	 *
	 * @param WP_Post $product Product object.
	 *
	 * @return void
	 */
	public function admin_actions_container( $product ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only preview flag.
		if ( get_edit_post_link( $product->ID ) && empty( $_GET['test_advanced'] ) ) {
			?>
			<div class="product-meta">
				<span><?php esc_html_e( 'Admin Options', 'post-type-x' ); ?>: </span>
				<?php
				edit_post_link( esc_html__( 'Edit Product', 'post-type-x' ), '<span class="edit-link">', '</span>', $product->ID );
				if ( current_user_can( 'edit_post', $product->ID ) ) {
					do_action( 'ic_product_meta', $product );
				}
				?>
			</div>
			<?php
		}
	}

	/**
	 * Outputs listing admin actions.
	 *
	 * @return void
	 */
	public function admin_listing_actions_container() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only preview flag.
		if ( empty( $_GET['test_advanced'] ) && current_user_can( 'edit_pages' ) ) {
			?>
			<div class="product-meta">
				<?php
				echo '<span>' . esc_html__( 'Admin Options', 'post-type-x' ) . ': </span>';
				do_action( 'ic_listing_meta' );
				?>
			</div>
			<?php
		}
	}

	/**
	 * Outputs the customize product page link.
	 *
	 * @return void
	 */
	public function customize_product_page() {
		if ( function_exists( 'is_customize_preview' ) ) {
			$current_page = get_permalink();
			$url          = admin_url( 'customize.php?autofocus[control]=ic_pc_integration_template&url=' . rawurlencode( $current_page ) );
			echo '<span><a href="' . esc_url( $url ) . '">' . esc_html__( 'Customize Page Design', 'post-type-x' ) . '</a></span>';
		}
	}

	/**
	 * Outputs the customize listing link.
	 *
	 * @return void
	 */
	public function customize_product_listing() {
		if ( function_exists( 'is_customize_preview' ) ) {
			$listing_page = product_listing_url();
			if ( ! empty( $listing_page ) ) {
				$url = admin_url( 'customize.php?autofocus[control]=ic_pc_archive_template&url=' . rawurlencode( $listing_page ) );
				echo '<span><a href="' . esc_url( $url ) . '">' . esc_html__( 'Customize Listing Design', 'post-type-x' ) . '</a></span>';
			}
		}
	}

	/**
	 * Outputs the customize icons link.
	 *
	 * @return void
	 */
	public function customize_icons() {
		if ( function_exists( 'is_customize_preview' ) ) {
			$listing_page = product_listing_url();
			if ( ! empty( $listing_page ) ) {
				$url = admin_url( 'customize.php?autofocus[control]=ic_pc_integration_icons_display&url=' . rawurlencode( $listing_page ) );
				echo '<span><a href="' . esc_url( $url ) . '">' . esc_html__( 'Customize Sitewide Icons', 'post-type-x' ) . '</a></span>';
			}
		}
	}

	/**
	 * Outputs the filters bar customization link.
	 *
	 * @return void
	 */
	public function customize_filters_bar() {
		$url = admin_url( 'widgets.php' );
		echo '<span><a href="' . esc_url( $url ) . '">' . esc_html__( 'Customize Filters Bar', 'post-type-x' ) . '</a></span>';
	}
}

