<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- Legacy bootstrap filename retained for compatibility.

/**
 * Widget blocks integration.
 *
 * @version 1.0.0
 * @package EcommerceProductCatalog
 * @author  impleCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


/**
 * Registers and renders EPC widget blocks.
 */
class IC_EPC_Widget_Blocks {

	/**
	 * Context blocks handler.
	 *
	 * @var ic_epc_context_blocks
	 */
	public $context_blocks;

	/**
	 * Hooks widget block callbacks.
	 */
	public function __construct() {
		add_action( 'ic_register_blocks', array( $this, 'register' ) );
		add_filter( 'ic_epc_blocks_localize', array( $this, 'localize' ) );
		add_filter( 'widget_block_dynamic_classname', array( $this, 'widget_block_classname' ), 10, 2 );
		add_filter( 'ic_widget_block_content', array( $this, 'default_block_content' ), 10, 3 );
	}

	/**
	 * Provides dynamic widget block content.
	 *
	 * @param string $block_content Existing block content.
	 * @param string $block_name Block slug without namespace.
	 * @param array  $attributes Block attributes.
	 * @return string
	 */
	public function default_block_content( $block_content, $block_name, $attributes ) {
		$attributes['title']             = isset( $attributes['title'] ) ? $attributes['title'] : '';
		$attributes['shortcode_support'] = isset( $attributes['shortcode_support'] ) ? $attributes['shortcode_support'] : '';
		ob_start();
		if ( 'product-search-widget' === $block_name ) {
			the_widget( 'product_widget_search', $attributes );
		} elseif ( 'product-sort-filter' === $block_name ) {
			the_widget( 'product_sort_filter', $attributes );
		} elseif ( 'product-category-filter' === $block_name ) {
			the_widget( 'Product_Category_Filter', $attributes );
		} elseif ( 'related-products' === $block_name ) {
			the_widget( 'Related_Products_Widget', $attributes );
		} elseif ( 'product-size-filter' === $block_name ) {
			the_widget( 'IC_Product_Size_Filter', $attributes );
		} elseif ( 'product-category-widget' === $block_name ) {
			$attributes['dropdown']     = isset( $attributes['dropdown'] ) ? $attributes['dropdown'] : '';
			$attributes['count']        = isset( $attributes['count'] ) ? $attributes['count'] : '';
			$attributes['hierarchical'] = isset( $attributes['hierarchical'] ) ? $attributes['hierarchical'] : '';
			the_widget( 'Product_Cat_Widget', $attributes );
		} else {
			do_action( 'ic_the_widget_block_content', $block_name, $attributes );
		}
		$new_block_content = ob_get_clean();
		if ( ! empty( $new_block_content ) ) {
			$block_content = $new_block_content;
		}

		return $block_content;
	}

	/**
	 * Adds a dynamic CSS class for EPC blocks.
	 *
	 * @param string $classname Existing class name.
	 * @param string $block_name Block name.
	 * @return string
	 */
	public function widget_block_classname( $classname, $block_name ) {
		if ( ic_string_contains( $block_name, 'ic-epc' ) ) {
			$classname .= ' ' . str_replace( 'ic-epc/', '', $block_name );
		}

		return $classname;
	}

	/**
	 * Renders a widget block instance.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $block_content Existing block content.
	 * @param WP_Block $block Block instance.
	 * @return string
	 */
	public function render( $attributes, $block_content, $block ) {
		$block_name    = explode( '/', $block->name );
		$block_name    = $block_name[1];
		$block_title   = $block->block_type->title;
		$block_content = apply_filters( 'ic_widget_block_content', $block_content, $block_name, $attributes );
		if ( ( empty( $block_content ) || ic_string_contains( $block_content, 'ic-empty-filter' ) ) && ic_is_rendering_block() ) {
			/* translators: %s: block title shown in the editor placeholder. */
			$block_content = '<div style="padding: 20px 10px">' . sprintf( __( '%s will only show up on the front-end if something is available in the context.', 'post-type-x' ), $block_title ) . '</div>';
		}

		return $this->container( $attributes, $block_content, $block_name );
	}

	/**
	 * Wraps widget block content in the shared container.
	 *
	 * @param array    $attr Block attributes.
	 * @param string   $content Block content.
	 * @param string   $name Block name.
	 * @param int|null $product_id Optional product context.
	 * @return string
	 */
	public function container( $attr, $content, $name, $product_id = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Signature keeps compatibility with existing container callbacks.
		if ( ! empty( $this->context_blocks ) ) {
			return $this->context_blocks->container( $attr, $content, $name );
		} else {
			return $content;
		}
	}

	/**
	 * Registers widget block types.
	 */
	public function register() {
		$epc_blocks = array(
			__DIR__ . '/search/',
			__DIR__ . '/category-links/',
			__DIR__ . '/sort/',
			__DIR__ . '/category-filter/',
			__DIR__ . '/related/',
		);
		$blocks     = apply_filters(
			'ic_product_widget_blocks',
			$epc_blocks
		);
		if ( function_exists( 'ic_size_field_names' ) ) {
			$blocks[] = __DIR__ . '/size-filter/';
			$epc_blocks[] = __DIR__ . '/size-filter/';
		}
		foreach ( $blocks as $block_dir ) {
			$args = array(
				'render_callback' => array( $this, 'render' ),
			);
			if ( in_array( $block_dir, $epc_blocks, true ) ) {
				global $wp_version;
				$args['api_version'] = version_compare( $wp_version, '6.3', '>=' ) ? 3 : 2;
			}
			if ( ! empty( $block_dir ) && file_exists( $block_dir . 'block.json' ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading trusted local block metadata.
				$block_metadata = json_decode( file_get_contents( $block_dir . 'block.json' ), true );
				$args['title']  = isset( $block_metadata['title'] ) ? $block_metadata['title'] : '';
				if ( ! empty( $args['title'] ) ) {
					// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Block title is loaded from local block metadata.
					$args['title'] = __( $args['title'], 'post-type-x' );
				}
			}
			register_block_type( $block_dir, $args );
		}
	}

	/**
	 * Localizes widget block labels.
	 *
	 * @param array $localize Localized block data.
	 * @return array
	 */
	public function localize( $localize ) {
		if ( is_plural_form_active() ) {
			$names = get_catalog_names();
			/* translators: %s: singular catalog item label. */
			$search_label = sprintf( __( '%s Search', 'post-type-x' ), ic_ucfirst( $names['singular'] ) );
			/* translators: %s: singular catalog item label. */
			$sort_label = sprintf( __( '%s Sort', 'post-type-x' ), ic_ucfirst( $names['singular'] ) );
			/* translators: %s: singular catalog item label. */
			$category_filter_label = sprintf( __( '%s Category Filter', 'post-type-x' ), ic_ucfirst( $names['singular'] ) );
			/* translators: %s: singular catalog item label. */
			$size_filter_label = sprintf( __( '%s Size Filter', 'post-type-x' ), ic_ucfirst( $names['singular'] ) );
			/* translators: %s: plural catalog item label. */
			$related_products_label = sprintf( __( 'Related %s', 'post-type-x' ), ic_ucfirst( $names['plural'] ) );
		} else {
			$search_label           = __( 'Product Search', 'post-type-x' );
			$sort_label             = __( 'Catalog Sort', 'post-type-x' );
			$category_filter_label  = __( 'Catalog Category Filter', 'post-type-x' );
			$size_filter_label      = __( 'Catalog Size Filter', 'post-type-x' );
			$related_products_label = __( 'Related Catalog Items', 'post-type-x' );
		}
		$localize['strings']['search_widget']            = $search_label;
		$localize['strings']['sort_widget']              = $sort_label;
		$localize['strings']['category_filter_widget']   = $category_filter_label;
		$localize['strings']['size_filter_widget']       = $size_filter_label;
		$localize['strings']['related_products_widget']  = $related_products_label;
		$localize['strings']['settings']                 = __( 'Settings', 'post-type-x' );
		$localize['strings']['select_title']             = __( 'Title', 'post-type-x' );
		$localize['strings']['category_widget']          = __( 'Category Links', 'post-type-x' );
		$localize['strings']['select_dropdown']          = __( 'Display as dropdown', 'post-type-x' );
		$localize['strings']['select_count']             = __( 'Show product counts', 'post-type-x' );
		$localize['strings']['select_hierarchical']      = __( 'Show hierarchy', 'post-type-x' );
		$localize['strings']['select_shortcode_support'] = __( 'Enable also for shortcodes', 'post-type-x' );

		return $localize;
	}
}
