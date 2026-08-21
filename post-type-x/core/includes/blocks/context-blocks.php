<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- Legacy loader filename retained for compatibility.
/**
 * Catalog context blocks.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Catalog context block renderer.
 */
class IC_EPC_Context_Blocks {

	/**
	 * Sets up context block hooks.
	 */
	public function __construct() {
		add_action( 'ic_register_blocks', array( $this, 'register' ) );
		add_filter( 'block_type_metadata', array( $this, 'configure_metadata' ) );
	}

	/**
	 * Renders a context block.
	 *
	 * @param array    $attributes    Block attributes.
	 * @param string   $block_content Block content.
	 * @param WP_Block $block         Block instance.
	 * @return string|null
	 */
	public function render( $attributes, $block_content, $block ) {
		$product_id = isset( $attributes['selectedProduct'] ) ? $attributes['selectedProduct'] : '';
		if ( empty( $product_id ) && ! empty( $attributes['ic_context_id'] ) && ic_is_rendering_block() ) {
			$product_id = intval( $attributes['ic_context_id'] );
		}
		if ( empty( $product_id ) ) {
			$product_id = ic_get_product_id();
		}
		$block_name  = explode( '/', $block->name );
		$block_name  = $block_name[1];
		$block_title = $block->block_type->title;
		if ( empty( $product_id ) || ! is_ic_product( $product_id ) ) {
			if ( ic_is_rendering_block() ) {
				$block_content = $this->block_sample( $attributes, $block_name );
			} else {
				return;
			}
		} else {
			$block_content = apply_filters( 'ic_block_content', $block_content, $product_id, $block_name, $attributes );
		}
		if ( empty( $block_content ) && ic_is_rendering_block() ) {
			/* translators: %s: block title. */
			$block_content = '<div style="padding: 20px 10px">' . sprintf( __( '%s will only show up on the product page if the product has the related data assigned.', 'post-type-x' ), $block_title ) . '</div>';
		}

		return $this->container( $attributes, $block_content, $block_name, $product_id );
	}

	/**
	 * Registers context blocks.
	 *
	 * @return void
	 */
	public function register() {
		$epc_blocks = array(
			__DIR__ . '/image-gallery/',
			__DIR__ . '/name/',
			__DIR__ . '/short-description/',
		);
		$blocks     = apply_filters(
			'ic_product_parts_blocks',
			$epc_blocks
		);
		foreach ( $blocks as $block_dir ) {
			$args = array(
				'render_callback' => array( $this, 'render' ),
			);
			if ( in_array( $block_dir, $epc_blocks, true ) ) {
				global $wp_version;
				$args['api_version'] = version_compare( $wp_version, '6.3', '>=' ) ? 3 : 2;
			}
			if ( file_exists( $block_dir . 'block.json' ) ) {
				if ( ! empty( $block_dir ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local block.json file from the plugin.
					$block_metadata = json_decode( file_get_contents( $block_dir . 'block.json' ), true );
					$args['title']  = isset( $block_metadata['title'] ) ? $block_metadata['title'] : '';
				} else {
					$args['title'] = '';
				}
				if ( ! empty( $args['title'] ) ) {
					// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Block title is loaded from local block.json metadata.
					$args['title'] = __( $args['title'], 'post-type-x' );
				}
			}
			register_block_type( $block_dir, $args );
		}
	}

	/**
	 * Configures block metadata for catalog blocks.
	 *
	 * @param array $metadata Block metadata.
	 * @return array
	 */
	public function configure_metadata( $metadata ) {
		if ( ! empty( $metadata['name'] ) && ( ic_string_contains( $metadata['name'], 'ic-epc' ) || ic_string_contains( $metadata['name'], 'ic-price-field' ) ) ) {
			global $wp_version;
			if ( version_compare( $wp_version, '6.3', '>=' ) ) {
				$metadata['apiVersion'] = 3;
			}
			if ( ! empty( $metadata['attributes']['fontSize'] ) ) {
				$metadata['attributes']['style']    = array(
					'type'    => 'object',
					'default' => array(),
				);
				$metadata['supports']['typography'] = array(
					'fontSize'                      => true,
					'lineHeight'                    => true,
					'__experimentalFontStyle'       => true,
					'__experimentalFontWeight'      => true,
					'__experimentalLetterSpacing'   => true,
					'__experimentalTextTransform'   => true,
					'__experimentalDefaultControls' => array(
						'fontSize' => true,
					),
				);
			}
			if ( ! empty( $metadata['attributes']['colors'] ) ) {
				$metadata['attributes']['textColor']       = array(
					'type'    => 'string',
					'default' => '',
				);
				$metadata['attributes']['backgroundColor'] = array(
					'type'    => 'string',
					'default' => '',
				);
				$metadata['supports']['color']             = true;
				unset( $metadata['attributes']['colors'] );
			}
			if ( ! empty( $metadata['attributes']['alignment'] ) ) {
				$metadata['attributes']['align'] = array(
					'type'    => 'string',
					'default' => '',
				);
				$metadata['supports']['align']   = true;
			}

			if ( ! empty( $metadata['attributes']['spacing'] ) ) {
				unset( $metadata['attributes']['spacing'] );
				$metadata['supports']['spacing'] = array(
					'margin'  => true,
					'padding' => true,
				);
				$metadata['attributes']['style'] = array(
					'type'    => 'object',
					'default' => array(),
				);
			}
			if ( ! empty( $metadata['attributes']['border'] ) ) {
				$metadata['supports']['__experimentalBorder'] = array(
					'radius' => true,
					'color'  => true,
					'width'  => true,
					'style'  => true,
				);
				$metadata['attributes']['style']              = array(
					'type'    => 'object',
					'default' => array(),
				);
			}
			$metadata['attributes']['ic_context_id']        = array(
				'type'    => 'integer',
				'default' => 0,
			);
			$metadata['attributes']['ic_context_post_type'] = array(
				'type'    => 'string',
				'default' => '',
			);
			$block_name                                     = explode( '/', $metadata['name'] );
			$block_name                                     = $block_name[1];
			$metadata                                       = apply_filters( 'ic_block_metadata', $metadata, $block_name );
		}

		return $metadata;
	}

	/**
	 * Returns sample block content for editor previews.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $block_name Block name.
	 * @return string
	 */
	public function block_sample( $attributes, $block_name ) {
		$block_content = '';
		if ( empty( $attributes['sample'] ) ) {
			$product_id = $this->sample_product_id();
			if ( $product_id ) {
				$block_content = apply_filters( 'ic_block_content', '', $product_id, $block_name, $attributes );
			}
		} else {
			$block_content = $attributes['sample'];
		}

		return $block_content;
	}

	/**
	 * Finds a sample product ID.
	 *
	 * @return int
	 */
	public function sample_product_id() {
		$products = get_all_catalog_products( null, null, 1 );
		if ( ! empty( $products[0] ) && isset( $products[0]->ID ) ) {
			return $products[0]->ID;
		}

		return 0;
	}

	/**
	 * Wraps rendered block content in a container.
	 *
	 * @param array      $attr       Block attributes.
	 * @param string     $content    Block content.
	 * @param string     $name       Block name.
	 * @param int|string $product_id Product ID.
	 * @return string
	 */
	public function container( $attr, $content, $name, $product_id = null ) {
		if ( ic_is_rendering_block() ) {
			return $content;
		}
		$wrapper_attributes = get_block_wrapper_attributes();
		$class              = 'ic-block-' . $name;
		if ( isset( $attr['alignment'] ) ) {
			if ( 'center' === $attr['alignment'] ) {
				$class .= ' ic-align-center';
			} elseif ( 'right' === $attr['alignment'] ) {
				$class .= ' ic-align-right';
			}
		}
		$container_class = apply_filters( 'ic_block_container_class', $class, $product_id, $name );
		if ( ic_string_contains( $wrapper_attributes, 'class="' ) ) {
			$wrapper_attributes = str_replace( 'class="', 'class="' . $container_class . ' ', $wrapper_attributes );
		} else {
			$wrapper_attributes .= ' class="' . $container_class . '"';
		}

		return '<div ' . $wrapper_attributes . '>' . $content . '</div>';
	}
}
