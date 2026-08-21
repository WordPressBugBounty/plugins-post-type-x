<?php
/**
 * Block editor support for product editing.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles block editor product editing integration.
 */
class IC_EPC_Block_Edit {

	/**
	 * Register block editing hooks.
	 */
	public function __construct() {
		add_action( 'ic_after_layout_integration_setting_html', array( $this, 'edit_settings' ) );
		add_filter( 'catalog_multiple_settings', array( $this, 'default_edit' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'init', array( $this, 'init' ), 1 );
	}

	/**
	 * Enable Gutenberg on the frontend when block editing is active.
	 *
	 * @return void
	 */
	public function init() {
		if ( is_admin() || ! $this->enabled() ) {
			return;
		}
		add_filter( 'ic_epc_allow_gutenberg', array( $this, 'ret_true' ) );
	}

	/**
	 * Configure the admin edit screen for block mode.
	 *
	 * @return void
	 */
	public function admin_init() {
		if ( ! $this->enabled() ) {
			remove_filter( 'ic_epc_allow_gutenberg', array( $this, 'ret_true' ) );

			return;
		}
		add_filter( 'ic_epc_allow_gutenberg', array( $this, 'ret_true' ) );

		global $ic_register_product;
		if ( ! empty( $ic_register_product ) ) {
			remove_action( 'current_screen', array( $ic_register_product, 'edit_screen' ) );
			add_action( 'do_meta_boxes', array( $ic_register_product, 'change_image_box' ) );
			add_action( 'add_product_metaboxes', array( $this, 'modify_boxes' ) );
			add_action( 'add_meta_boxes', array( $this, 'modify_boxes' ) );
			add_action( 'enqueue_block_editor_assets', array( $this, 'modify_editor' ) );
			global $wp_version;
			if ( ! class_exists( 'jQuery_Migrate_Helper' ) && ( version_compare( $wp_version, 6.0 ) < 0 ) ) {
				add_filter( 'ic_product_short_desc_input', array( $this, 'excerpt_textarea' ) );
			}
		}
	}

	/**
	 * Render the excerpt meta box as textarea markup.
	 *
	 * @return string
	 */
	public function excerpt_textarea() {
		global $post;
		ob_start();
		post_excerpt_meta_box( $post );

		return ob_get_clean();
	}

	/**
	 * Remove the classic product description meta box.
	 *
	 * @return void
	 */
	public function modify_boxes() {
		remove_meta_box( 'al_product_desc', 'al_product', 'normal' );
	}

	/**
	 * Enqueue block editor compatibility assets.
	 *
	 * @return void
	 */
	public function modify_editor() {
		if ( is_ic_edit_product_screen() || is_ic_new_product_screen() ) {
			global $wp_version;
			if ( version_compare( $wp_version, 6.5 ) < 0 ) {
				wp_enqueue_script( 'ic_epc_modify_editor', AL_PLUGIN_BASE_PATH . 'includes/blocks/js/modify-editor.js' . ic_filemtime( AL_BASE_PATH . '/includes/blocks/js/modify-editor.js' ), array( 'wp-edit-post' ), IC_EPC_VERSION, true );
			} else {
				wp_enqueue_script( 'ic_epc_modify_editor', AL_PLUGIN_BASE_PATH . 'includes/blocks/js/modify-editor-65.js' . ic_filemtime( AL_BASE_PATH . '/includes/blocks/js/modify-editor-65.js' ), array( 'wp-edit-post' ), IC_EPC_VERSION, true );
			}
		}
	}

	/**
	 * Render edit-mode settings.
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	public function edit_settings( $settings ) {
		if ( ! $this->enable_switcher() ) {
			return;
		}
		?>
		<h3><?php esc_html_e( 'Edit Mode', 'post-type-x' ); ?></h3>
		<table>
		<?php
			implecode_settings_radio(
				__( 'Product Edit Mode', 'post-type-x' ),
				'archive_multiple_settings[edit_mode]',
				$settings['edit_mode'],
				array(
					'classic' => __( 'Classic Editor', 'post-type-x' ),
					'blocks'  => __( 'Blocks', 'post-type-x' ) . ' (Gutenberg)',
					// Full-page block mode remains intentionally disabled here.
				),
				1,
				__( 'Choose how would you like to edit the products.', 'post-type-x' )
			);
		?>
		</table>
		<?php
		if ( 'blocks' !== $settings['edit_mode'] && $this->is_forced() ) {
			?>
			<script>
				jQuery('[name="archive_multiple_settings[edit_mode]"][value="classic"]').prop('disabled', true);
				jQuery('[name="archive_multiple_settings[edit_mode]"][value="classic"]').prop('checked', false);
				jQuery('[name="archive_multiple_settings[edit_mode]"][value="blocks"]').prop('checked', true);
			</script>
			<?php
		}
	}

	/**
	 * Populate the default edit mode setting.
	 *
	 * @param array $settings Current settings.
	 * @return array
	 */
	public function default_edit( $settings ) {
		$settings['edit_mode'] = ! empty( $settings['edit_mode'] ) ? $settings['edit_mode'] : $this->default_mode();

		return $settings;
	}

	/**
	 * Return a true boolean value for filters.
	 *
	 * @return bool
	 */
	public function ret_true() {
		return true;
	}

	/**
	 * Check whether block edit mode is enabled.
	 *
	 * @return bool
	 */
	public function enabled() {
		if ( ! $this->enable_switcher() ) {
			$mode = $this->default_mode();
		} else {
			$archive_multiple_settings = get_multiple_settings();
			$mode                      = $archive_multiple_settings['edit_mode'];
		}
		if ( 'blocks' === $mode ) {
			return true;
		}
		if ( $this->is_forced() ) {
			return true;
		}

		return false;
	}

	/**
	 * Check whether the block editor is forced for the current product type.
	 *
	 * @return bool
	 */
	public function is_forced() {
		$post_type         = 'al_product';
		$current_post_type = get_post_type();
		if ( ! empty( $current_post_type ) ) {
			if ( 'al_product' === get_quasi_post_type( $current_post_type ) ) {
				$post_type = $current_post_type;
			} else {
				$post_type = '';
			}
		}
		if ( ! empty( $post_type ) ) {
			global $ic_register_product;
			$removed = remove_filter(
				'use_block_editor_for_post_type',
				array(
					$ic_register_product,
					'can_gutenberg',
				),
				999,
				2
			);
			$forced  = apply_filters( 'use_block_editor_for_post_type', false, $post_type );
			if ( $removed ) {
				add_filter( 'use_block_editor_for_post_type', array( $ic_register_product, 'can_gutenberg' ), 999, 2 );
			}
			if ( $forced ) {

				return true;
			}
		}

		return false;
	}

	/**
	 * Get the default editor mode.
	 *
	 * @return string
	 */
	public function default_mode() {
		$forced = $this->use_block_editor();
		if ( true === $forced ) {
			return 'blocks';
		} else {
			return 'classic';
		}
	}

	/**
	 * Determine whether the edit-mode switcher can be shown.
	 *
	 * @return bool
	 */
	public function enable_switcher() {
		if ( $this->is_managed_elsewhere() || $this->is_changed() ) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Check whether the block editor state changed elsewhere.
	 *
	 * @return bool
	 */
	public function is_changed() {
		$forced = $this->use_block_editor();
		if ( 'not_changed' === $forced ) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Get the block editor decision for the product edit context.
	 *
	 * @return bool|string
	 */
	public function use_block_editor() {
		global $ic_register_product;
		$removed_first = remove_filter(
			'use_block_editor_for_post_type',
			array(
				$ic_register_product,
				'can_gutenberg',
			),
			999,
			2
		);
		$forced        = apply_filters( 'use_block_editor_for_post_type', 'not_changed', 'al_product' );
		if ( 'post.php' === $GLOBALS['pagenow'] && isset( $_GET['action'], $_GET['post'] ) && 'edit' === $_GET['action'] && empty( $_GET['meta-box-loader'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen detection: these $_GET values are only compared to decide which editor filter applies and nothing is written; post.php?post=X&action=edit is a plain WordPress admin navigation URL that carries no nonce.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin screen context.
			$post_id        = intval( $_GET['post'] );
			$post           = get_post( $post_id );
			$removed_second = remove_filter(
				'use_block_editor_for_post',
				array(
					$ic_register_product,
					'can_gutenberg',
				),
				999,
				2
			);
			$forced         = apply_filters( 'use_block_editor_for_post', $forced, $post );
		}
		if ( ! empty( $removed_first ) ) {
			add_filter( 'use_block_editor_for_post_type', array( $ic_register_product, 'can_gutenberg' ), 999, 2 );
		}
		if ( ! empty( $removed_second ) ) {
			add_filter( 'use_block_editor_for_post', array( $ic_register_product, 'can_gutenberg' ), 999, 2 );
		}

		return $forced;
	}

	/**
	 * Check whether another plugin manages the editor mode.
	 *
	 * @return bool
	 */
	public function is_managed_elsewhere() {
		if ( class_exists( 'Classic_Editor' ) ) {
			return true;
		} else {
			return false;
		}
	}
}

$ic_epc_block_edit = new IC_EPC_Block_Edit();
