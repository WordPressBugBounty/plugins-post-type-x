<?php
/**
 * Product details class.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers EPC product details integrations.
 */
class IC_Product_Details {

	/**
	 * Initializes product detail hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_ai_integration' ), 30 );
		add_action( 'admin_menu', array( $this, 'register_ai_settings_submenu' ), 12 );
		add_filter( 'submenu_file', array( $this, 'highlight_ai_settings_submenu' ) );
	}

	/**
	 * Registers the EPC product editor with the shared AI framework.
	 *
	 * @return void
	 */
	public function register_ai_integration() {
		if ( ! function_exists( 'ic_ai_manager' ) ) {
			return;
		}

		ic_ai_manager()->register_integration(
			array(
				'plugin_slug'         => 'ecommerce-product-catalog',
				'post_type'           => 'al_product',
				'menu_parent'         => 'edit.php?post_type=al_product',
				'settings_screen'     => 'product-settings.php',
				'settings_screen_tab' => 'ai-settings',
				'settings_tab_label'  => __( 'impleCode AI', 'post-type-x' ),
				'settings_tab_id'     => 'ai-settings',
				'settings_capability' => 'manage_product_settings',
				'targets'             => array(
					'post'       => array(
						'post_type'            => 'al_product',
						'label'                => __( 'Products', 'post-type-x' ),
						'singular_label'       => __( 'Product', 'post-type-x' ),
						'edit_capability'      => 'edit_products',
						'list_enhance_enabled' => true,
						'field_map'            => self::ai_field_map( 'al_product' ),
					),
					'taxonomies' => array(
						'al_product-cat' => array(
							'taxonomy'             => 'al_product-cat',
							'label'                => __( 'Product Categories', 'post-type-x' ),
							'singular_label'       => __( 'Product Category', 'post-type-x' ),
							'edit_capability'      => 'edit_product_categories',
							'list_enhance_enabled' => true,
							'field_map'            => self::ai_category_field_map( 'al_product-cat', 'al_product' ),
							'default_fields'       => array( 'category_name', 'category_description' ),
							'context_callback'     => array( __CLASS__, 'ai_category_context' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Returns the AI field map for a product-category target.
	 *
	 * @param string $taxonomy  Taxonomy name.
	 * @param string $post_type Owning post type.
	 *
	 * @return array
	 */
	public static function ai_category_field_map( $taxonomy = 'al_product-cat', $post_type = 'al_product' ) {
		$fields = array(
			'category_name'        => array(
				'type'            => 'term_field',
				'label'           => __( 'Name', 'post-type-x' ),
				'term_field'      => 'name',
				'value_type'      => 'plain_text',
				'content_format'  => 'plain_text',
				'default_enabled' => true,
			),
			'category_description' => array(
				'type'            => 'term_field',
				'label'           => __( 'Description', 'post-type-x' ),
				'term_field'      => 'description',
				'value_type'      => 'html',
				'content_format'  => 'html',
				'default_enabled' => true,
			),
		);
		return apply_filters( 'ic_epc_ai_category_fields', $fields, $taxonomy, $post_type );
	}

	/**
	 * Builds hierarchy and directly assigned product context for a category.
	 *
	 * @param int               $term_id Term ID.
	 * @param IC_AI_Target|null $target Registered taxonomy target.
	 *
	 * @return array
	 */
	public static function ai_category_context( $term_id, $target = null ) {
		$taxonomy       = is_object( $target ) && method_exists( $target, 'taxonomy' ) ? $target->taxonomy() : 'al_product-cat';
		$post_type      = is_object( $target ) && method_exists( $target, 'post_type' ) ? $target->post_type() : 'al_product';
		$include_bottom = is_object( $target ) && method_exists( $target, 'field_map' ) && isset( $target->field_map()['category_bottom_description'] );
		$term           = get_term( absint( $term_id ), $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return array(); }
		$ancestors            = array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) );
		$context              = array(
			'category' => array(
				'id'          => (int) $term->term_id,
				'name'        => $term->name,
				'description' => wp_kses_post( $term->description ),
			),
		);
		$context['ancestors'] = array();
		foreach ( $ancestors as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, $term->taxonomy );
			if ( $ancestor && ! is_wp_error( $ancestor ) ) {
				$item = array(
					'id'          => (int) $ancestor->term_id,
					'name'        => $ancestor->name,
					'description' => wp_kses_post( $ancestor->description ),
				);
				if ( $include_bottom ) {
					$item['bottom_description'] = wp_kses_post( get_term_meta( $ancestor->term_id, '_ic_second_description', true ) );
				} $context['ancestors'][] = $item; }
		}
		$children = get_terms(
			array(
				'taxonomy'         => $term->taxonomy,
				'parent'           => $term->term_id,
				'hide_empty'       => false,
				'include_children' => false,
				'orderby'          => 'name',
				'order'            => 'ASC',
			)
		);
		usort(
			$children,
			static function ( $left, $right ) {
				$name_compare = strcasecmp( (string) $left->name, (string) $right->name );
				return 0 !== $name_compare ? $name_compare : ( (int) $left->term_id <=> (int) $right->term_id );
			}
		);
		$context['children'] = array();
		foreach ( (array) $children as $child ) {
			if ( ! is_wp_error( $child ) ) {
				$item = array(
					'id'          => (int) $child->term_id,
					'name'        => $child->name,
					'description' => wp_kses_post( $child->description ),
				);
				if ( $include_bottom ) {
					$item['bottom_description'] = wp_kses_post( get_term_meta( $child->term_id, '_ic_second_description', true ) );
				} $context['children'][] = $item; }
		}
		$product_ids         = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
					'ID'         => 'ASC',
				),
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Product/category AI context requires taxonomy membership filtering.
				'tax_query'      => array(
					array(
						'taxonomy'         => $term->taxonomy,
						'field'            => 'term_id',
						'terms'            => $term->term_id,
						'include_children' => false,
					),
				),
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		$context['products'] = array();
		foreach ( (array) $product_ids as $product_id ) {
			$context['products'][] = array(
				'id'                => (int) $product_id,
				'title'             => get_the_title( $product_id ),
				'short_description' => wp_kses_post( get_post_field( 'post_excerpt', $product_id ) ),
			); }
		return $context;
	}

	/**
	 * Returns the removed customer AI instruction default.
	 *
	 * Kept as a no-op compatibility shim so independently updated extensions do
	 * not fatal. Customer instructions are intentionally no longer supported.
	 *
	 * @deprecated 6.5.0 Customer AI instructions are no longer supported.
	 *
	 * @return string
	 */
	public static function default_ai_instructions() {
		return '';
	}

	/**
	 * Registers the AI link in the product post type submenu.
	 *
	 * @return void
	 */
	public function register_ai_settings_submenu() {
		global $submenu;

		// Distributions without the shared AI framework have no AI settings tab to link to.
		if ( ! function_exists( 'ic_ai_manager' ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- EPC registers this custom capability.
		if ( ! current_user_can( 'manage_product_settings' ) ) {
			return;
		}

		$parent_slug = 'edit.php?post_type=al_product';
		$menu_slug   = $this->ai_settings_menu_slug();

		if ( isset( $submenu[ $parent_slug ] ) && is_array( $submenu[ $parent_slug ] ) ) {
			foreach ( $submenu[ $parent_slug ] as $submenu_item ) {
				if ( isset( $submenu_item[2] ) && $menu_slug === $submenu_item[2] ) {
					return;
				}
			}
		}

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WordPress submenu registration requires mutating the shared $submenu array.
		$submenu[ $parent_slug ][] = array(
			__( 'impleCode AI', 'post-type-x' ),
			'manage_product_settings',
			$menu_slug,
			__( 'impleCode AI', 'post-type-x' ),
		);
	}

	/**
	 * Highlights the AI submenu link when the AI settings tab is open.
	 *
	 * @param string $submenu_file Current submenu file.
	 *
	 * @return string
	 */
	public function highlight_ai_settings_submenu( $submenu_file ) {
		if ( ! is_admin() || ! function_exists( 'ic_ai_manager' ) ) {
			return $submenu_file;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin menu state.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin menu state.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin menu state.
		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';

		if ( 'al_product' === $post_type && 'product-settings.php' === $page && 'ai-settings' === $tab ) {
			return $this->ai_settings_menu_slug();
		}

		return $submenu_file;
	}

	/**
	 * Returns the product submenu slug that points to the AI settings tab.
	 *
	 * @return string
	 */
	private function ai_settings_menu_slug() {
		return 'edit.php?post_type=al_product&page=product-settings.php&tab=ai-settings';
	}

	/**
	 * Returns whether the given post type belongs to the EPC product catalog family.
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return bool
	 */
	public static function is_ai_product_post_type( $post_type ) {
		$post_type = sanitize_key( (string) $post_type );

		return 'al_product' === $post_type || 0 === strpos( $post_type, 'al_product_' );
	}

	/**
	 * Returns the taxonomy used by the given EPC product post type.
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return string
	 */
	public static function ai_taxonomy( $post_type = 'al_product' ) {
		$post_type = sanitize_key( (string) $post_type );
		if ( 'al_product' === $post_type ) {
			return 'al_product-cat';
		}
		if ( 0 === strpos( $post_type, 'al_product_' ) ) {
			return 'al_product-cat_' . substr( $post_type, 11 );
		}

		return 'al_product-cat';
	}

	/**
	 * Returns the EPC AI field map.
	 *
	 * @param string $post_type Product post type.
	 *
	 * @return array
	 */
	public static function ai_field_map( $post_type = 'al_product' ) {
		$post_type = sanitize_key( (string) $post_type );
		$fields    = array(
			'title'             => array(
				'type'             => 'post_field',
				'label'            => __( 'Title', 'post-type-x' ),
				'post_field'       => 'post_title',
				'form_field'       => 'post_title',
				'input_selector'   => '#title',
				'enhancement_mode' => 'rewrite',
				'default_enabled'  => true,
			),
			'short_description' => array(
				'type'             => 'post_field',
				'value_type'       => 'html',
				'content_format'   => 'html',
				'label'            => __( 'Short Description', 'post-type-x' ),
				'post_field'       => 'post_excerpt',
				'form_field'       => 'excerpt',
				'input_selector'   => '#excerpt',
				'enhancement_mode' => 'rewrite',
				'default_enabled'  => true,
			),
			'long_description'  => array(
				'type'             => 'post_field',
				'value_type'       => 'html',
				'content_format'   => 'html',
				'label'            => __( 'Long Description', 'post-type-x' ),
				'post_field'       => 'post_content',
				'form_field'       => 'content',
				'input_selector'   => '#content',
				'enhancement_mode' => 'rewrite',
				'default_enabled'  => true,
			),
			'categories'        => array(
				'type'                  => 'taxonomy',
				'label'                 => __( 'Categories', 'post-type-x' ),
				'taxonomy'              => self::ai_taxonomy( $post_type ),
				'enhancement_mode'      => 'preserve',
				'lock_enhancement_mode' => true,
				'default_enabled'       => true,
			),
		);

		/**
		 * Filters the EPC AI field map shown in the "Enhance Fields" setting and used during AI requests.
		 *
		 * Modules and extensions can add or remove field definitions here. Each field should match the
		 * field-map structure expected by the local AI framework.
		 *
		 * @param array              $fields       AI field definitions keyed by field slug.
		 * @param string             $post_type    EPC post type slug.
		 * @param string             $taxonomy     Product taxonomy slug.
		 */
		return apply_filters( 'ic_epc_ai_product_fields', $fields, $post_type, self::ai_taxonomy( $post_type ) );
	}
}
