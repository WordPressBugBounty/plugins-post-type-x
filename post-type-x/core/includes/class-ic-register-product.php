<?php
/**
 * Product post type registration.
 *
 * @package ecommerce-product-catalog/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Manages product post type
 *
 * Here all product fields are defined.
 *
 * @version        1.1.1
 * @package        ecommerce-product-catalog/includes
 * @author        impleCode
 */
class IC_Register_Product {

	/**
	 * Hooks product registration callbacks.
	 */
	public function __construct() {
		add_action( 'register_catalog_styles', array( $this, 'frontend_scripts' ) );
		add_action( 'init', array( $this, 'ic_create_product' ), 4 );

		add_action( 'admin_head', array( $this, 'product_icons' ) );

		add_action( 'post_updated', array( $this, 'implecode_save_products_meta' ), 1, 3 );
		add_action( 'transition_post_status', array( $this, 'status_change' ), 10, 3 );
		add_action( 'current_screen', array( $this, 'edit_screen' ) );

		add_filter( 'generate_rewrite_rules', array( $this, 'rewrite_rules' ) );

		add_filter( 'use_block_editor_for_post_type', array( $this, 'can_gutenberg' ), 999, 2 );
		add_filter( 'gutenberg_can_edit_post_type', array( $this, 'can_gutenberg' ), 999, 2 );
		add_filter( 'use_block_editor_for_post', array( $this, 'can_gutenberg' ), 999, 2 );
		add_filter( 'gutenberg_can_edit_post', array( $this, 'can_gutenberg' ), 999, 2 );

		add_action( 'wp_print_scripts', array( $this, 'structured_data' ) );
		if ( defined( 'IC_COMPRESS_PRIVATE_PRODUCTS_DATA' ) && ! empty( IC_COMPRESS_PRIVATE_PRODUCTS_DATA ) ) {
			add_filter( 'get_post_metadata', array( $this, 'hidden_product_data' ), 10, 4 );
		}

		add_action( 'ic_scheduled_hidden_data_processing', array( $this, 'process_hidden_data' ) );

		require_once AL_BASE_PATH . '/includes/product-categories.php';
	}

	/**
	 * Hooks product edit screen adjustments.
	 */
	public function edit_screen() {
		if ( is_ic_new_product_screen() || is_ic_edit_product_screen() ) {
			add_action( 'edit_form_after_title', array( $this, 'ic_remove_default_desc_editor' ) );
			add_action( 'edit_form_after_editor', array( $this, 'ic_restore_default_desc_editor' ) );

			add_action( 'admin_menu', array( $this, 'ic_remove_unnecessary_metaboxes' ) );
			add_action( 'admin_head', array( $this, 'ic_remove_unnecessary_metaboxes' ), 999 );

			add_action( 'do_meta_boxes', array( $this, 'change_image_box' ) );

			add_filter( 'post_updated_messages', array( $this, 'set_product_messages' ) );
		}
	}

	/**
	 * Determines if the block editor can be used for catalog products.
	 *
	 * @param bool                $can_edit  Whether the current post type can use Gutenberg.
	 * @param string|WP_Post_Type $post_type Post type string or object.
	 *
	 * @return bool
	 */
	public function can_gutenberg( $can_edit, $post_type ) {
		$enabled_post_types = product_post_type_array();
		if ( ! in_array( $post_type, $enabled_post_types, true ) ) {
			return $can_edit;
		}
		if ( apply_filters( 'ic_epc_allow_gutenberg', false ) ) {

			return true;
		}
		if ( isset( $post_type->post_type ) ) {
			$post_type = $post_type->post_type;
		}
		if ( ic_string_contains( $post_type, 'al_product' ) ) {
			return false;
		}

		return $can_edit;
	}


	/**
	 * Registers product meta fields.
	 */
	public function register_meta() {
		$post_types = product_post_type_array();
		foreach ( $post_types as $post_type ) {
			register_post_meta(
				$post_type,
				'_thumbnail_id',
				array(
					'auth_callback' => '__return_true',
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => 'number',
				)
			);
			do_action( 'ic_register_post_meta', $post_type );
		}
	}

	/**
	 * Registers product related front-end scripts
	 */
	public function frontend_scripts() {
		$dependence = array( 'jquery' );
		wp_register_script( 'ic_magnifier', AL_PLUGIN_BASE_PATH . 'js/magnifier/magnifier.min.js', array( 'jquery' ), ic_filemtime( AL_BASE_PATH . '/js/magnifier/magnifier.min.js' ), true );
		wp_register_script( 'colorbox', AL_PLUGIN_BASE_PATH . 'js/colorbox/jquery.colorbox.min.js', array( 'jquery' ), ic_filemtime( AL_BASE_PATH . '/js/colorbox/jquery.colorbox.min.js' ), true );
		wp_register_style( 'colorbox', AL_PLUGIN_BASE_PATH . 'js/colorbox/colorbox.css', array(), ic_filemtime( AL_BASE_PATH . '/js/colorbox/colorbox.css' ) );
		if ( is_ic_product_page() ) {
			if ( is_ic_magnifier_enabled() || ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) ) {
				$dependence[] = 'ic_magnifier';
			}
			if ( ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) || ( is_lightbox_enabled() && is_ic_product_gallery_enabled() ) ) {
				$dependence[] = 'colorbox';
			}
		}
		wp_register_script( 'al_product_scripts', AL_PLUGIN_BASE_PATH . 'js/product.min.js', apply_filters( 'al_product_scripts_dependence', $dependence ), ic_filemtime( AL_BASE_PATH . '/js/product.min.js', true ), true );
	}

	/**
	 * Registers products post type
	 *
	 * @global type $wp_version
	 */
	public function ic_create_product() {
		global $wp_version;
		$page_id = get_product_listing_id();
		$slug    = get_product_slug();
		if ( is_ic_product_listing_enabled() && ( ( 'simple' !== get_integration_type() && ! is_ic_shortcode_integration() ) || ! is_numeric( $page_id ) ) ) {
			$product_listing_t = $slug;
		} else {
			$product_listing_t = false;
		}
		$names     = get_catalog_names();
		$query_var = $this->get_product_query_var();
		if ( is_plural_form_active() ) {
				$labels = array(
					'name'                  => $names['plural'],
					'singular_name'         => $names['singular'],
					// translators: %s: singular product label.
					'add_new'               => sprintf( __( 'Add New %s', 'post-type-x' ), ic_ucfirst( $names['singular'] ) ),
					// translators: %s: singular product label.
					'add_new_item'          => sprintf( __( 'Add New %s', 'post-type-x' ), ic_ucfirst( $names['singular'] ) ),
					// translators: %s: singular product label.
					'edit_item'             => sprintf( __( 'Edit %s', 'post-type-x' ), ic_ucfirst( $names['singular'] ) ),
					// translators: %s: singular product label.
					'new_item'              => sprintf( __( 'Add New %s', 'post-type-x' ), ic_ucfirst( $names['singular'] ) ),
					// translators: %s: singular product label.
					'view_item'             => sprintf( __( 'View %s', 'post-type-x' ), ic_ucfirst( $names['singular'] ) ),
					// translators: %s: plural product label.
					'search_items'          => sprintf( __( 'Search %s', 'post-type-x' ), ic_ucfirst( $names['plural'] ) ),
					// translators: %s: plural product label.
					'not_found'             => sprintf( __( 'No %s found', 'post-type-x' ), $names['plural'] ),
					// translators: %s: plural product label.
					'not_found_in_trash'    => sprintf( __( 'No %s found in trash', 'post-type-x' ), $names['plural'] ),
					// translators: %s: singular product label.
					'set_featured_image'    => sprintf( __( 'Set main %s image', 'post-type-x' ), ic_lcfirst( $names['singular'] ) ),
					// translators: %s: singular product label.
					'remove_featured_image' => sprintf( __( 'Remove main %s image', 'post-type-x' ), ic_lcfirst( $names['singular'] ) ),
					// translators: %s: singular product label.
					'featured_image'        => sprintf( __( '%s Image', 'post-type-x' ), ic_ucfirst( $names['singular'] ) ),
				);
		} else {
			$labels = array(
				'name'                  => $names['plural'],
				'singular_name'         => $names['singular'],
				'add_new'               => __( 'Add New', 'post-type-x' ),
				'add_new_item'          => __( 'Add New Item', 'post-type-x' ),
				'edit_item'             => __( 'Edit Item', 'post-type-x' ),
				'new_item'              => __( 'Add New Item', 'post-type-x' ),
				'view_item'             => __( 'View Item', 'post-type-x' ),
				'search_items'          => __( 'Search Items', 'post-type-x' ),
				'not_found'             => __( 'Nothing found', 'post-type-x' ),
				'not_found_in_trash'    => __( 'Nothing found in trash', 'post-type-x' ),
				'set_featured_image'    => __( 'Set main image', 'post-type-x' ),
				'remove_featured_image' => __( 'Remove main image', 'post-type-x' ),
				'featured_image'        => __( 'Image', 'post-type-x' ),
			);
		}
		if ( version_compare( $wp_version, 3.8 ) < 0 ) {
			$reg_settings = array(
				'labels'               => $labels,
				'public'               => true,
				'show_in_rest'         => true,
				'show_in_nav_menus'    => true,
				'hierarchical'         => false,
				'has_archive'          => $product_listing_t,
				'rewrite'              => array(
					'slug'       => apply_filters( 'product_slug_value_register', $slug ),
					'with_front' => false,
				),
				'query_var'            => $query_var,
				'supports'             => apply_filters(
					'ic_products_type_support',
					array(
						'title',
						'thumbnail',
						'editor',
						'excerpt',
					)
				),
				'register_meta_box_cb' => array( $this, 'add_product_metaboxes' ),
				'taxonomies'           => array( 'al_product_cat' ),
				'menu_icon'            => plugins_url() . '/ecommerce-product-catalog/img/product.png',
				'capability_type'      => 'product',
				'map_meta_cap'         => true,
				'menu_position'        => 30,
				'exclude_from_search'  => false,
			);
		} else {
			$reg_settings = array(
				'labels'               => $labels,
				'public'               => true,
				'show_in_rest'         => true,
				'show_in_nav_menus'    => true,
				'hierarchical'         => false,
				'has_archive'          => $product_listing_t,
				'rewrite'              => array(
					'slug'       => apply_filters( 'product_slug_value_register', $slug ),
					'with_front' => false,
					'pages'      => true,
				),
				'query_var'            => $query_var,
				'supports'             => apply_filters(
					'ic_products_type_support',
					array(
						'title',
						'thumbnail',
						'editor',
						'excerpt',
					)
				),
				'register_meta_box_cb' => array( $this, 'add_product_metaboxes' ),
				'taxonomies'           => array( 'al_product-cat' ),
				'capability_type'      => 'product',
				'map_meta_cap'         => true,
				'menu_position'        => 30,
				'exclude_from_search'  => false,
			);
			if ( apply_filters( 'ic_epc_allow_gutenberg', false ) && ! in_array( 'custom-fields', $reg_settings['supports'], true ) ) {
				$reg_settings['supports'][] = 'custom-fields';
			}
		}
		register_post_type( 'al_product', $reg_settings );
	}

	/**
	 * Gets the product query var.
	 *
	 * @return string
	 */
	public function get_product_query_var() {
		$query_var = 'al_product';
		if ( ! is_ic_permalink_product_catalog() ) {
			$names         = get_catalog_names();
			$new_query_var = sanitize_title( ic_strtolower( $names['singular'] ) );
			$new_query_var = ( strpos( $new_query_var, '%' ) !== false ) ? 'product' : $new_query_var;
			$forbidden     = ic_forbidden_query_vars();
			if ( array_search( $new_query_var, $forbidden, true ) === false ) {
				$query_var = $new_query_var;
			}
		}

		return apply_filters( 'product_query_var', $query_var );
	}

	/**
	 * Outputs the product icon CSS on the admin edit screen.
	 */
	public function product_icons() {
		$post_type = '';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading the current admin post type from the URL is safe here.
		if ( isset( $_GET['post_type'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading the current admin post type from the URL is safe here.
			$post_type = sanitize_key( wp_unslash( $_GET['post_type'] ) );
		}
		?>
		<style>
			<?php if ( 'al_product' === $post_type ) : ?>
			#icon-edit {
				background: transparent url('
				<?php
				echo esc_url( plugins_url( '/ecommerce-product-catalog/img/product-32.png' ) );
				?>
			') no-repeat;
			}

			<?php endif; ?>
		</style>
		<?php
	}

	/**
	 * Registers the product edit metaboxes.
	 */
	public function add_product_metaboxes() {
		$names             = get_catalog_names();
		$names['singular'] = ic_ucfirst( $names['singular'] );
			$labels        = array();
		if ( is_plural_form_active() ) {
			// translators: %s: singular product label.
			$labels['s_desc'] = sprintf( __( '%s Short Description', 'post-type-x' ), $names['singular'] );
			// translators: %s: singular product label.
			$labels['desc'] = sprintf( __( '%s description', 'post-type-x' ), $names['singular'] );
			// translators: %s: singular product label.
			$labels['details'] = sprintf( __( '%s Details', 'post-type-x' ), $names['singular'] );
		} else {
			$labels['s_desc']  = __( 'Short Description', 'post-type-x' );
			$labels['desc']    = __( 'Long Description', 'post-type-x' );
			$labels['details'] = __( 'Details', 'post-type-x' );
		}
		$labels = apply_filters( 'ic_add_meta_box_labels', $labels );
		add_meta_box(
			'al_product_short_desc',
			$labels['s_desc'],
			array(
				$this,
				'al_product_short_desc',
			),
			'al_product',
			apply_filters( 'short_desc_box_column', 'normal' ),
			apply_filters( 'short_desc_box_priority', 'default' )
		);
		add_meta_box(
			'al_product_desc',
			$labels['desc'],
			array(
				$this,
				'al_product_desc',
			),
			'al_product',
			apply_filters( 'desc_box_column', 'normal' ),
			apply_filters( 'desc_box_priority', 'default' )
		);
		if ( ic_product_details_box_visible() ) {
			add_meta_box(
				'al_product_details',
				$labels['details'],
				array(
					$this,
					'al_product_details',
				),
				'al_product',
				apply_filters( 'product_details_box_column', 'side' ),
				apply_filters( 'product_details_box_priority', 'default' )
			);
		}
		do_action( 'add_product_metaboxes', $names, $labels );
	}

	/**
	 * Renders the product details metabox.
	 */
	public function al_product_details() {
		global $post;
		echo '<input type="hidden" name="pricemeta_noncename" id="pricemeta_noncename" value="' .
			esc_attr( wp_create_nonce( plugin_basename( __FILE__ ) ) ) . '" />';
		$product_details = '';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: the return value of the 'admin_product_details' filter, which extensions and themes legitimately use to inject their own HTML.
		echo apply_filters( 'admin_product_details', $product_details, $post->ID );
	}

	/**
	 * Renders the short description metabox.
	 */
	public function al_product_short_desc() {
		global $post;
		echo '<input type="hidden" name="shortdescmeta_noncename" id="shortdescmeta_noncename" value="' . esc_attr( wp_create_nonce( plugin_basename( __FILE__ ) ) ) . '" />';
		$shortdesc        = get_product_short_description( $post->ID );
		$override_excerpt = apply_filters( 'ic_product_short_desc_input', '' );
		if ( empty( $override_excerpt ) ) {
			$short_desc_settings = array(
				'media_buttons' => false,
				'textarea_rows' => 5,
				'tinymce'       => array(
					'menubar'            => false,
					'toolbar1'           => 'bold,italic,underline,blockquote,strikethrough,bullist,numlist,alignleft,aligncenter,alignright,undo,redo,link,unlink,fullscreen',
					'toolbar2'           => '',
					'toolbar3'           => '',
					'toolbar4'           => '',
					'add_unload_trigger' => false,
				),
			);
			if ( apply_filters( 'ic_epc_allow_gutenberg', false ) ) {
				$short_desc_settings['tinymce']['wp_skip_init'] = true;
			}
			wp_editor( $shortdesc, 'excerpt', $short_desc_settings );
		} else {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: HTML assembled earlier in this scope, so escaping it here would print the tags.
			echo $override_excerpt;
		}
	}

	/**
	 * Renders the main description metabox.
	 */
	public function al_product_desc() {
		global $post;
		echo '<input type="hidden" name="descmeta_noncename" id="descmeta_noncename" value="' .
			esc_attr( wp_create_nonce( plugin_basename( __FILE__ ) ) ) . '" />';
		$desc_settings = array(
			'textarea_rows' => 30,
			'tinymce'       => array( 'add_unload_trigger' => false ),
		);
		wp_editor( $post->post_content, 'content', $desc_settings );
	}

	/**
	 * Handles product status changes.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Previous post status.
	 * @param WP_Post $post       Updated post object.
	 */
	public function status_change( $new_status, $old_status, $post ) {
		if ( $new_status === $old_status || empty( $post->ID ) || empty( $post->post_type ) ) {
			return;
		}
		$post_type_now = substr( $post->post_type, 0, 10 );
		if ( 'al_product' === $post_type_now ) {
			do_action( 'ic_product_status_change', $new_status, $old_status, $post );
			if ( ! ic_data_should_be_hidden( $post->post_status ) ) {
				$this->hidden_to_public( $post->ID );
				do_action( 'ic_product_status_change_visible', $new_status, $old_status, $post );
			} else {
				$this->public_to_hidden( $post->ID );
				do_action( 'ic_product_status_change_hidden', $new_status, $old_status, $post );
			}
		}
	}

	/**
	 * Handles product data save
	 *
	 * @param int          $post_id   Current post ID.
	 * @param WP_Post      $post      Current post object.
	 * @param WP_Post|null $post_prev Previous post object.
	 *
	 * @return int|void
	 */
	public function implecode_save_products_meta( $post_id, $post, $post_prev = null ) {
		$hook_existed  = remove_filter( 'get_post_metadata', array( $this, 'hidden_product_data' ) );
		$post_type_now = substr( $post->post_type, 0, 10 );
		if ( 'al_product' === $post_type_now ) {
			$pricemeta_noncename = isset( $_POST['pricemeta_noncename'] ) ? sanitize_text_field( wp_unslash( $_POST['pricemeta_noncename'] ) ) : '';
			if ( empty( $pricemeta_noncename ) || ( ! empty( $pricemeta_noncename ) && ! wp_verify_nonce( $pricemeta_noncename, plugin_basename( __FILE__ ) ) ) ) {
				return $post->ID;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended -- Action is read after the product save nonce has already been verified.
			$action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
			if ( empty( $action ) ) {
				return $post->ID;
			} elseif ( 'editpost' !== $action ) {
				return $post->ID;
			}
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return $post->ID;
			}
			if ( is_ic_ajax() ) {
				return $post->ID;
			}
			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				return $post->ID;
			}
				$product_meta = array();

			$product_meta = apply_filters( 'product_meta_save', $product_meta, $post );

			$product_meta = apply_filters( 'ic_product_meta_save_update_data', $product_meta, $post );

			do_action( 'product_meta_save_update', $product_meta, $post );

			$this->save_meta( $post, $product_meta );

			do_action( 'product_edit_save', $post, $product_meta, $post_prev, $this );
		}
		if ( $hook_existed ) {
			add_filter( 'get_post_metadata', array( $this, 'hidden_product_data' ), 10, 4 );
		}
	}

	/**
	 * Saves product metadata.
	 *
	 * @param int|WP_Post       $post         Post object or ID.
	 * @param array|string      $product_meta Product meta array or meta key.
	 * @param string|array|null $value        Meta value when saving a single key.
	 *
	 * @return bool|void
	 */
	public function save_meta( $post, $product_meta, $value = null ) {
		if ( empty( $post->ID ) ) {
			if ( is_numeric( $post ) ) {
				$post = get_post( $post );
			} else {
				return false;
			}
		}
		if ( ! is_array( $product_meta ) ) {
			if ( null === $value ) {
				return false;
			}
			$product_meta = array( $product_meta => $value );
		}
		$hook_existed = remove_filter( 'get_post_metadata', array( $this, 'hidden_product_data' ) );
		$product_meta = apply_filters( 'product_meta_save_anywhere', $product_meta, $post );
		if ( ! ic_data_should_be_hidden( $post->post_status ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended -- Branch-selection read only: it decides whether hidden product meta is merged back and writes nothing. Reachable from status_change() (transition_post_status) where no nonce exists, so it must not carry write authority.
			$action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
			if ( empty( $action ) || 'editpost' !== $action ) {
				$this->hidden_to_public( $post->ID );
			}
			foreach ( $product_meta as $key => $value ) {
				$this->save_single_meta( $post->ID, $key, $value, false );
			}
			delete_post_meta( $post->ID, '_ic_hidden_product_data' );
		} else {
			$this->public_to_hidden( $post->ID, $product_meta );
		}
		if ( $hook_existed ) {
			add_filter( 'get_post_metadata', array( $this, 'hidden_product_data' ), 10, 4 );
		}
	}

	/**
	 * Saves a single product meta value.
	 *
	 * @param int          $product_id   Product ID.
	 * @param string       $key          Meta key.
	 * @param string|array $value        Meta value.
	 * @param bool         $check_status Whether to check hidden status first.
	 *
	 * @return mixed
	 */
	public function save_single_meta( $product_id, $key, $value, $check_status = false ) {
		$hook_existed = remove_filter( 'get_post_metadata', array( $this, 'hidden_product_data' ) );
		if ( $check_status ) {
			$post = get_post( $product_id );
			if ( ic_data_should_be_hidden( $post->post_status ) ) {
				$hidden_result = $this->save_single_hidden_meta( $product_id, $key, $value );
				// Restore the reader before returning. Without this, the early return leaves
				// get_post_metadata unhooked for the REST of the request, so every later get_post_meta()
				// call on a compressed product misses _ic_hidden_product_data and the product renders with
				// no image, no price and zero inventory until the next page load.
				if ( $hook_existed ) {
					add_filter( 'get_post_metadata', array( $this, 'hidden_product_data' ), 10, 4 );
				}

				return $hidden_result;
			}
		}
		$current_post_keys = get_post_custom_keys( $product_id );
		if ( is_ic_multiple_key( $key ) ) {
			$single = false;
			if ( ! is_array( $value ) ) {
				$value = explode( ',', $value );
			}
		} else {
			$single = true;
		}
		if ( is_array( $current_post_keys ) && in_array( $key, $current_post_keys, true ) ) {
			$current_value = get_post_meta( $product_id, $key, $single );
		}
		$allow_empty = apply_filters( 'ic_meta_allow_empty', ! empty( $value ) || is_numeric( $value ), $key );
		if ( $allow_empty && ! isset( $current_value ) ) {
			if ( ! $single ) {
				delete_post_meta( $product_id, $key );
				foreach ( $value as $this_value ) {
					add_post_meta( $product_id, $key, trim( $this_value ), false );
				}
			} else {
				add_post_meta( $product_id, $key, $value, true );
				$this->save_filterable_meta( $product_id, $key, $value );
			}
		} elseif ( isset( $current_value ) && $allow_empty && ( ( is_numeric( $value ) && (string) $value !== (string) $current_value ) || ( ! is_numeric( $value ) && $value !== $current_value ) ) ) {
			if ( ! $single ) {
				delete_post_meta( $product_id, $key );
				foreach ( $value as $this_value ) {
					add_post_meta( $product_id, $key, trim( $this_value ), false );
				}
			} else {
				update_post_meta( $product_id, $key, $value );
				$this->save_filterable_meta( $product_id, $key, $value );
			}
		} elseif ( ! $allow_empty && empty( $value ) && ! is_numeric( $value ) && isset( $current_value ) ) {
			delete_post_meta( $product_id, $key );
			delete_post_meta( $product_id, $key . '_filterable' );
		}
		if ( $hook_existed ) {
			add_filter( 'get_post_metadata', array( $this, 'hidden_product_data' ), 10, 4 );
		}
	}

	/**
	 * Saves a single hidden meta value.
	 *
	 * @param int          $product_id Product ID.
	 * @param string       $key        Meta key.
	 * @param string|array $value      Meta value.
	 */
	public function save_single_hidden_meta( $product_id, $key, $value ) {
		$hidden_data = get_post_meta( $product_id, '_ic_hidden_product_data', true );
		if ( empty( $hidden_data ) ) {
			$hidden_data = array();
		}
		$hidden_data[ $key ] = $value;
		update_post_meta( $product_id, '_ic_hidden_product_data', $hidden_data );
		if ( '_sku' === $key ) {
			$this->save_single_meta( $product_id, $key, $value );
		}
	}

	/**
	 * Saves filterable metadata values.
	 *
	 * @param int          $product_id Product ID.
	 * @param string       $key        Meta key.
	 * @param string|array $value      Meta value.
	 */
	public function save_filterable_meta( $product_id, $key, $value ) {
		if ( ! is_array( $value ) || apply_filters( 'ic_save_meta_avoid_filterable', false, $key ) ) {
			return;
		}
		delete_post_meta( $product_id, $key . '_filterable' );
		foreach ( $value as $val ) {
			if ( is_array( $val ) || ( empty( $val ) && ! is_numeric( $val ) ) ) {
				break;
			}
			add_post_meta( $product_id, $key . '_filterable', $val, false );
		}
	}

	/**
	 * Restores hidden data back to public meta fields.
	 *
	 * @param int $product_id Product ID.
	 */
	public function hidden_to_public( $product_id ) {
		$hidden_data = get_post_meta( $product_id, '_ic_hidden_product_data', true );
		if ( ! empty( $hidden_data ) ) {
			foreach ( $hidden_data as $key => $value ) {
				$this->save_single_meta( $product_id, $key, $value );
			}
			delete_post_meta( $product_id, '_ic_hidden_product_data' );
		}
	}

	/**
	 * Moves product data into hidden storage.
	 *
	 * @param int   $product_id   Product ID.
	 * @param array $product_meta Product meta values.
	 */
	public function public_to_hidden( $product_id, $product_meta = array() ) {
		if ( empty( $product_meta ) ) {
			$meta_keys = $this->data_keys( $product_id );
			foreach ( $meta_keys as $key ) {
				$meta_value = get_post_meta( $product_id, $key, true );
				if ( ! empty( $meta_value ) || is_numeric( $meta_value ) ) {
					$product_meta[ $key ] = $meta_value;
				}
			}
		}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended -- Branch-selection read only: it decides whether hidden product meta is merged back and writes nothing. Reachable from status_change() (transition_post_status) where no nonce exists, so it must not carry write authority.
			$action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
		if ( empty( $action ) || 'editpost' !== $action ) {
			$current_hidden = get_post_meta( $product_id, '_ic_hidden_product_data', true );
			if ( ! empty( $current_hidden ) ) {
				foreach ( $current_hidden as $hidden_key => $hidden_value ) {
					if ( ! isset( $product_meta[ $hidden_key ] ) ) {
						$product_meta[ $hidden_key ] = $hidden_value;
					}
				}
			}
		}
		update_post_meta( $product_id, '_ic_hidden_product_data', $product_meta );
		foreach ( $product_meta as $key => $val ) {
			// _sku stays public so SKU lookups keep working on a hidden product. _ic_hidden_product_data is
			// the store this loop is filling -- deleting it here wipes every field of the product. It cannot
			// reach $product_meta through data_keys() any more (see restricted_meta()), but callers such as
			// save_meta() pass their own array through the product_meta_save_anywhere filter, so the guard
			// stays.
			if ( '_sku' === $key || '_ic_hidden_product_data' === $key ) {
				continue;
			}
			delete_post_meta( $product_id, $key );
			delete_post_meta( $product_id, $key . '_filterable' );
		}
	}

	/**
	 * Reads hidden product data from the compressed meta store.
	 *
	 * @param mixed       $metadata  Existing metadata value.
	 * @param int         $object_id Object ID.
	 * @param string|null $meta_key  Meta key.
	 * @param bool        $single    Whether a single value was requested.
	 *
	 * @return mixed
	 */
	public function hidden_product_data( $metadata, $object_id, $meta_key = null, $single = false ) {
		unset( $single );

		if ( ! defined( 'IC_COMPRESS_PRIVATE_PRODUCTS_DATA' ) || ( defined( 'IC_COMPRESS_PRIVATE_PRODUCTS_DATA' ) && empty( IC_COMPRESS_PRIVATE_PRODUCTS_DATA ) ) ) {
			return $metadata;
		}
		if ( ! empty( $object_id ) && empty( $metadata ) && ! is_numeric( $metadata ) && ! empty( $meta_key ) && '_ic_hidden_product_data' !== $meta_key ) {
			$hidden_data = get_post_meta( $object_id, '_ic_hidden_product_data', true );
			if ( isset( $hidden_data[ $meta_key ] ) ) {
				return array( $hidden_data[ $meta_key ] );
			}
		}

		return $metadata;
	}

	/**
	 * Processes the hidden data migration queue.
	 *
	 * @return int|void
	 */
	public function process_hidden_data() {
		$done = get_option( 'ic_product_hidden_data_upgrade_done', 0 );
		if ( empty( $done ) ) {
			update_option( 'ic_product_hidden_data_upgrade_done', -1, false );
			wp_schedule_single_event( time(), 'ic_scheduled_hidden_data_processing' );

			return $done;
		}

		if ( $done < 0 ) {
			$done = 0;
		}

		$post_statuses    = get_post_statuses();
		$visible_statuses = ic_visible_product_status( false );
		$fetch_status     = array();
		foreach ( $post_statuses as $post_status => $post_status_label ) {
			if ( ! in_array( $post_status, $visible_statuses, true ) ) {
				$fetch_status[] = $post_status;
			}
		}
		if ( empty( $fetch_status ) ) {
			return;
		}
		$products  = get_all_catalog_products( 'date', 'ASC', 200, $done, apply_filters( 'ic_scheduled_hidden_data_processing_args', array( 'post_status' => $fetch_status ) ) );
		$max_round = 200;
		$rounds    = 1;
		foreach ( $products as $post ) {
			if ( $rounds > $max_round ) {
				break;
			}
			ic_set_time_limit( 30 );
			if ( defined( 'IC_COMPRESS_PRIVATE_PRODUCTS_DATA' ) && IC_COMPRESS_PRIVATE_PRODUCTS_DATA ) {
				$this->public_to_hidden( $post->ID );
			} else {
				$this->hidden_to_public( $post->ID );
			}
			++$done;
			++$rounds;
		}
		if ( ! empty( $products ) ) {
			update_option( 'ic_product_hidden_data_upgrade_done', $done, false );
			wp_schedule_single_event( time(), 'ic_scheduled_hidden_data_processing' );
		} else {
			delete_option( 'ic_product_hidden_data_upgrade_done' );
			wp_clear_scheduled_hook( 'ic_scheduled_hidden_data_processing' );
		}

		return $done;
	}

	/**
	 * Gets product meta keys excluding restricted entries.
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return array
	 */
	public function data_keys( $product_id ) {
		$current_post_keys = get_post_custom_keys( $product_id );
		if ( empty( $current_post_keys ) ) {
			return array();
		}
		$restricted = $this->restricted_meta();
		foreach ( $current_post_keys as $akey => $key ) {
			if ( in_array( $key, $restricted, true ) ) {
				unset( $current_post_keys[ $akey ] );
			}
		}

		return $current_post_keys;
	}

	/**
	 * Returns restricted product meta keys.
	 *
	 * @return array
	 */
	public function restricted_meta() {
		return array(
			'_wp_old_slug',
			'_edit_lock',
			'_edit_last',
			// The compressed store itself. data_keys() feeds public_to_hidden(), which deletes every key it
			// returns; leaving this one in made a second compression pass delete the blob it had just
			// written, and nest the blob inside itself. See public_to_hidden().
			'_ic_hidden_product_data',
			// WordPress trash bookkeeping. wp_untrash_post() READS _wp_trash_meta_status to pick the restore
			// status and then deletes both keys; once they are swallowed into the blob those deletes hit
			// nothing, the keys survive the restore and reappear as public rows on a published product.
			'_wp_trash_meta_status',
			'_wp_trash_meta_time',
		);
	}

	/**
	 * Disables the default editor screen on product add/edit page
	 */
	public function ic_remove_default_desc_editor() {
		remove_post_type_support( 'al_product', 'editor' );
	}

	/**
	 * Restores editor support
	 */
	public function ic_restore_default_desc_editor() {
		add_post_type_support( 'al_product', 'editor' );
	}

	/**
	 * Removes unnecessary metaboxes for product edit/add screen
	 */
	public function ic_remove_unnecessary_metaboxes() {
		remove_meta_box( 'postexcerpt', 'al_product', 'normal' );
	}

	/**
	 * Renames the product image metabox.
	 */
	public function change_image_box() {
		$names = get_catalog_names();
		remove_meta_box( 'postimagediv', 'al_product', 'side' );
		if ( is_plural_form_active() ) {
			// translators: %s: singular product label.
			$label = sprintf( __( '%s Image', 'post-type-x' ), ic_ucfirst( $names['singular'] ) );
		} else {
			$label = __( 'Image', 'post-type-x' );
		}
		add_meta_box( 'postimagediv', $label, 'post_thumbnail_meta_box', 'al_product', apply_filters( 'product_image_box_column', 'side' ), apply_filters( 'product_image_box_priority', 'high' ) );
	}

	/**
	 * Filters product updated messages.
	 *
	 * @param array $messages Admin update messages.
	 *
	 * @return array
	 */
	public function set_product_messages( $messages ) {
		global $post, $post_ID;
		$quasi_post_type = get_quasi_post_type();
		$post_type       = get_post_type( $post_ID );
		$revision        = 0;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading the current revision ID for an admin notice is safe here.
		if ( isset( $_GET['revision'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading the current revision ID for an admin notice is safe here.
			$revision = absint( wp_unslash( $_GET['revision'] ) );
		}
		if ( 'al_product' === $quasi_post_type ) {
			$obj      = get_post_type_object( $post_type );
			$singular = $obj->labels->singular_name;

			$messages[ $post_type ] = array(
				0  => '',
				// translators: 1: product label, 2: product permalink.
				1  => sprintf( __( '%1$s updated. <a href="%2$s">View %1$s</a>', 'post-type-x' ), ic_strtoupper( $singular ), esc_url( get_permalink( $post_ID ) ) ),
				2  => __( 'Custom field updated.', 'post-type-x' ),
				3  => __( 'Custom field deleted.', 'post-type-x' ),
				// translators: %s: product label.
				4  => sprintf( __( '%s updated.', 'post-type-x' ), $singular ),
				// translators: 1: product label, 2: revision title.
				5  => $revision ? sprintf( __( '%1$s restored to revision from %2$s', 'post-type-x' ), $singular, wp_post_revision_title( $revision, false ) ) : false,
				// translators: 1: product label, 2: product permalink.
				6  => sprintf( __( '%1$s published. <a href="%2$s">View %1$s</a>', 'post-type-x' ), $singular, esc_url( get_permalink( $post_ID ) ) ),
				7  => __( 'Page saved.', 'post-type-x' ),
				// translators: 1: product label, 2: preview URL.
				8  => sprintf( __( '%1$s submitted. <a target="_blank" href="%2$s">Preview %1$s</a>', 'post-type-x' ), $singular, esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) ), strtolower( $singular ) ),
				// translators: 1: scheduled date, 2: preview URL, 3: product label.
				9  => sprintf( __( '%3$s scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview %3$s</a>', 'post-type-x' ), date_i18n( __( 'M j, Y @ G:i', 'post-type-x' ), strtotime( $post->post_date ) ), esc_url( get_permalink( $post_ID ) ), $singular ),
				// translators: 1: product label, 2: preview URL.
				10 => sprintf( __( '%1$s draft updated. <a target="_blank" href="%2$s">Preview %1$s</a>', 'post-type-x' ), $singular, esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) ) ),
			);
		}

		return $messages;
	}

	/**
	 * Rewrite to support pagination on shortcode archive
	 *
	 * @param WP_Rewrite $wp_rewrite WordPress rewrite object.
	 */
	public function rewrite_rules( $wp_rewrite ) {
		if ( is_ic_shortcode_integration() ) {
			$slug       = get_product_slug();
			$listing_id = intval( get_product_listing_id() );
			if ( ! empty( $slug ) && ! empty( $listing_id ) ) {
				$rule           = $slug . '/page/?([0-9]{1,})/?$';
				$rewrite        = 'index.php?page_id=' . $listing_id . '&paged=$matches[1]';
				$rules[ $rule ] = $rewrite;
			}
		}

		if ( ! empty( $rules ) ) {
			$wp_rewrite->rules = $rules + $wp_rewrite->rules;
		}

		return apply_filters( 'ic_cat_urls_rewrite', $wp_rewrite );
	}

	/**
	 * Prints front-end structured data when enabled.
	 */
	public function structured_data() {
		$archive_multiple_settings = get_multiple_settings();
		if ( ! empty( $archive_multiple_settings['enable_structured_data'] ) && is_ic_product_page() ) {
			ic_show_template_file( 'product-page/structured-data.php', AL_BASE_TEMPLATES_PATH );
		}
	}
}

if ( ! class_exists( 'ic_register_product', false ) ) {
	class_alias( 'IC_Register_Product', 'ic_register_product' );
}

global $ic_register_product;
$ic_register_product = new IC_Register_Product();
