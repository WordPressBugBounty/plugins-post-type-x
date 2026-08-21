<?php
/**
 * Core product helper functions.
 *
 * @version 1.0.0
 * @package ecommerce-product-catalog/functions
 * @author  impleCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Returns default product image.
 *
 * @return string
 */
function default_product_thumbnail() {
	$default_image = apply_filters( 'ic_default_product_image', '' );
	if ( ! empty( $default_image ) ) {
		return $default_image;
	}
	$default_url = default_product_thumbnail_url( false );
	if ( ! empty( $default_url ) ) {
		$url = $default_url;
	} else {
		$product_id = get_the_ID();
		$sample_id  = sample_product_id();
		if ( ! empty( $sample_id ) && $product_id === $sample_id && is_ic_product_page() ) {
			$url = AL_PLUGIN_BASE_PATH . 'img/implecode.jpg';
		} else {
			$url = AL_PLUGIN_BASE_PATH . 'img/no-default-thumbnail.png';
		}
	}

	return '<img src="' . esc_url( $url ) . '"  />';
}

/**
 * Returns default product image URL.
 *
 * @param bool $get_plugin_default Whether the plugin default image should be returned.
 * @param bool $get_user_default   Whether the user-configured image should be returned.
 *
 * @return string
 */
function default_product_thumbnail_url( $get_plugin_default = true, $get_user_default = true ) {
	if ( $get_user_default ) {
		$default_id = ic_default_product_image_id();
		if ( ! empty( $default_id ) ) {
			$image = wp_get_attachment_image_src( $default_id, 'full' );
			if ( ! empty( $image[0] ) ) {
				return $image[0];
			}
		}
	}
	$url = '';
	if ( get_option( 'default_product_thumbnail' ) ) {
		$url = get_option( 'default_product_thumbnail' );
	} elseif ( $get_plugin_default ) {
		$url = AL_PLUGIN_BASE_PATH . 'img/no-default-thumbnail.png';
	}

	return $url;
}

/**
 * Returns the default product image attachment ID.
 *
 * @return string
 */
function ic_default_product_image_id() {
	return get_option( 'ic_default_product_image_id', '' );
}

add_filter( 'ic_category_image_id', 'ic_set_default_category_image_id', 50 );

/**
 * Sets the default category image ID when a category image is missing.
 *
 * @param string $image_id Current image ID.
 *
 * @return string
 */
function ic_set_default_category_image_id( $image_id ) {
	if ( empty( $image_id ) ) {
		$image_id = ic_default_product_image_id();
	}

	return $image_id;
}

// Listing redirect helper stays disabled for legacy non-permalink setups.

/**
 * Redirects the product listing page to the archive page on non-permalink configuration.
 *
 * @return void
 */
function redirect_listing_on_non_permalink() {
	if ( ! is_ic_permalink_product_catalog() && 'advanced' === get_integration_type() ) {
		$product_listing_id = get_product_listing_id();
		if ( ! empty( $product_listing_id ) && is_ic_product_listing_enabled() && is_ic_page( $product_listing_id ) ) {
			$url = product_listing_url();
			wp_safe_redirect( $url, 301 );
			exit;
		}
	}
}

/**
 * Renders a media uploader field for a product image option.
 *
 * @param string      $name          Field name suffix.
 * @param string      $button_value  Upload button label.
 * @param string      $option_name   Option name to persist.
 * @param string|null $option_value  Current option value.
 * @param string|null $default_image Default image URL.
 *
 * @return void
 */
function upload_product_image( $name, $button_value, $option_name, $option_value = null, $default_image = null ) {
	wp_enqueue_media();
	if ( empty( $option_value ) ) {
		$option_value = get_option( $option_name );
	}
	if ( empty( $default_image ) ) {
		$default_image = AL_PLUGIN_BASE_PATH . 'img/no-default-thumbnail.png';
	}
	if ( $option_value ) {
		$src = $option_value;
	} else {
		$src = $default_image;
	}
	?>
		<div class="custom-uploader">
			<input type="hidden" id="default" value="<?php echo esc_url( $default_image ); ?>"/>
			<input type="hidden" name="<?php echo esc_attr( $option_name ); ?>" id="<?php echo esc_attr( $name ); ?>"
					value="<?php echo esc_attr( $option_value ); ?>"/>

			<div class="admin-media-image"><img class="media-image" src="<?php echo esc_url( $src ); ?>" width="100%" height="100%"/>
			</div>
			<a href="#" class="button insert-media add_media" name="<?php echo esc_attr( $name ); ?>_button"
				id="button_<?php echo esc_attr( $name ); ?>"><span class="wp-media-buttons-icon"></span> <?php echo esc_html( $button_value ); ?></a>
			<a class="button" id="reset-image-button"
				href="#"><?php esc_html_e( 'Reset image', 'post-type-x' ); ?></a>
		</div>
		<script>
			jQuery(document).ready(function () {
				jQuery('#button_<?php echo esc_js( $name ); ?>').on('click', function () {
					wp.media.editor.send.attachment = function (props, attachment) {
						jQuery('#<?php echo esc_js( $name ); ?>').val(attachment.url);
						jQuery('.media-image').attr("src", attachment.url);
					}

				wp.media.editor.open(this);

				return false;
			});
		});

			jQuery('#reset-image-button').on('click', function () {
				jQuery('#<?php echo esc_js( $name ); ?>').val('');
				src = jQuery('#default').val();
				jQuery('.media-image').attr("src", src);
			});
	</script>
	<?php
}

if ( ! function_exists( 'ic_select_product' ) ) {

	/**
	 * Renders a product selector input or dropdown.
	 *
	 * @param string       $first_option   First option label.
	 * @param array|string $selected_value Selected value or values.
	 * @param string       $select_name    Field name.
	 * @param string|null  $css_class      Additional CSS classes.
	 * @param int          $should_echo    Whether to echo the output.
	 * @param string|null  $attr           Additional HTML attributes.
	 * @param string       $exclude        Excluded post IDs.
	 * @param string       $orderby        Optional sort column.
	 * @param string       $order          Optional sort direction.
	 *
	 * @return string
	 */
	function ic_select_product(
		$first_option,
		$selected_value,
		$select_name,
		$css_class = null,
		$should_echo = 1,
		$attr = null,
		$exclude = '',
		$orderby = '',
		$order = ''
	) {
		$product_count = ic_products_count();
		if ( $product_count < 1000 ) {
			$catalogs = product_post_type_array();
			$set      = array(
				'posts_per_page'   => - 1,
				'offset'           => 0,
				'orderby'          => 'name',
				'order'            => 'ASC',
				'post_type'        => $catalogs,
				'post_status'      => ic_visible_product_status(),
				'fields'           => 'ids',
				// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- The public helper contract explicitly supports excluding requested category IDs.
				'exclude'          => $exclude,
			);
			if ( ! empty( $orderby ) ) {
				$set['orderby'] = $orderby;
			}
			if ( ! empty( $order ) ) {
				$set['order'] = $order;
			}

			$pages        = get_posts( $set );
			$field_number = filter_var( $select_name, FILTER_SANITIZE_NUMBER_INT );
			$select_box   = '<select custom="' . esc_attr( $field_number ) . '" id="' . esc_attr( $select_name ) . '" name="' . esc_attr( $select_name ) . '" class="all-products-dropdown ' . esc_attr( (string) $css_class ) . '" ' . $attr . '>';
			if ( ! empty( $first_option ) ) {
				$select_box .= '<option value="noid">' . esc_html( $first_option ) . '</option>';
			}
			foreach ( $pages as $product_id ) {
				if ( is_array( $selected_value ) ) {
					$selected = in_array( $product_id, $selected_value, true ) ? 'selected' : '';
				} else {
					$selected = selected( $product_id, $selected_value, false );
				}
				$name = get_product_name( $product_id ) . ' (ID:' . $product_id;
				if ( function_exists( 'is_ic_sku_enabled' ) && is_ic_sku_enabled() ) {
					$name .= ', SKU:' . get_product_sku( $product_id );
				}
				$name       .= ')';
				$select_box .= '<option class="id_' . esc_attr( $product_id ) . '" value="' . esc_attr( $product_id ) . '" ' . $selected . '>' . esc_html( $name ) . '</option>';
			}
			$select_box .= '</select>';
		} else {
			if ( is_array( $selected_value ) ) {
				$selected_value = implode( ',', $selected_value );
			}
			$select_box = '<input type="text" name="' . esc_attr( $select_name ) . '" placeholder="' . esc_attr__( 'Set Product ID', 'post-type-x' ) . '" value="' . esc_attr( $selected_value ) . '"/>';
		}

		return echo_ic_setting( $select_box, $should_echo );
	}

}

/**
 * Outputs an edit link for a page.
 *
 * @param int $page_id Page ID.
 *
 * @return void
 */
function show_page_link( $page_id ) {
	$page_url  = get_permalink( $page_id );
	$page_link = '<a target="_blank" href="' . esc_url( $page_url ) . '">' . esc_html( $page_url ) . '</a>';
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Link markup is escaped when built.
	echo $page_link;
}

/**
 * Warns when the selected page is not published.
 *
 * @param int $page_id Page ID.
 *
 * @return void
 */
function verify_page_status( $page_id ) {
	$page_status = get_post_status( $page_id );
	if ( '' !== $page_status && 'publish' !== $page_status ) {
		$message = sprintf(
			/* translators: %s: page status slug. */
			__( 'This page has wrong status: %s.', 'post-type-x' ),
			$page_status
		);
		echo '<div class="al-box warning">' . esc_html( $message ) . '<br>' . esc_html__( 'Don\'t forget to publish it before going live!', 'post-type-x' ) . '</div>';
	}
}

if ( ! function_exists( 'design_schemes' ) ) {

	/**
	 * Returns CSS class fragments for a design scheme.
	 *
	 * @param string $which       Color, size, box, or none.
	 * @param int    $should_echo Whether to echo the output.
	 *
	 * @return string
	 */
	function design_schemes( $which = null, $should_echo = 1 ) {
		$design_schemes                = ic_get_design_schemes();
		$design_schemes['price-color'] = isset( $design_schemes['price-color'] ) ? $design_schemes['price-color'] : '';
		$design_schemes['price-size']  = isset( $design_schemes['price-size'] ) ? $design_schemes['price-size'] : '';
		$design_schemes['box-color']   = isset( $design_schemes['box-color'] ) ? $design_schemes['box-color'] : '';
		if ( 'color' === $which ) {
			$output = $design_schemes['price-color'];
		} elseif ( 'size' === $which ) {
			$output = $design_schemes['price-size'];
		} elseif ( 'box' === $which ) {
			$output = $design_schemes['box-color'];
		} elseif ( 'none' === $which ) {
			$output = '';
		} else {
			$output = $design_schemes['price-color'] . ' ' . $design_schemes['price-size'];
		}
		if ( ! empty( $output ) ) {
			$output .= ' ic-design';
		}

		return echo_ic_setting( apply_filters( 'design_schemes_output', $output, $which ), $should_echo );
	}
}

/* Single Product Functions */
add_action( 'before_product_entry', 'single_product_header', 10, 1 );

/**
 * Displays the header on product pages.
 *
 * @param int|object|false $product_id Product ID or post object.
 *
 * @return void
 */
function single_product_header( $product_id = false ) {
	if ( ( 'simple' !== get_integration_type() && ! is_ic_shortcode_integration() ) || apply_filters( 'ic_catalog_force_product_header', false ) ) {
		if ( is_object( $product_id ) && isset( $product_id->ID ) ) {
			$product_id = $product_id->ID;
		}
		ic_show_template_file( 'product-page/product-header.php', AL_BASE_TEMPLATES_PATH, $product_id );
	}
}

add_action( 'single_product_header', 'add_product_name', 10, 1 );

/**
 * Shows the product name on the product page.
 *
 * @param int|object|false $product_id Product ID or post object.
 *
 * @return void
 */
function add_product_name( $product_id = false ) {
	if ( is_ic_product_name_enabled() ) {
		if ( is_object( $product_id ) && isset( $product_id->ID ) ) {
			$product_id = $product_id->ID;
		}
		ic_show_template_file( 'product-page/product-name.php', AL_BASE_TEMPLATES_PATH, $product_id );
	}
}

add_action( 'before_product_listing_entry', 'product_listing_header' );

/**
 * Shows the product listing header.
 *
 * @return void
 */
function product_listing_header() {
	if ( ( 'simple' !== get_integration_type() && ! is_ic_shortcode_integration() ) || apply_filters( 'ic_catalog_force_category_header', false ) ) {
		ic_show_template_file( 'product-listing/listing-header.php' );
	}
}

add_action( 'product_listing_header', 'add_product_listing_name' );

/**
 * Shows the product listing title tag.
 *
 * @return void
 */
function add_product_listing_name() {
	ic_show_template_file( 'product-listing/listing-title.php' );
}

add_shortcode( 'product_listing_title', 'get_product_catalog_page_title' );

/**
 * Returns the current product listing title.
 *
 * @return string
 */
function get_product_catalog_page_title() {
	if ( is_ic_taxonomy_page() ) {
		$archive_names = get_archive_names();
		$the_tax       = ic_get_queried_object();
		if ( ! empty( $archive_names['all_prefix'] ) ) {
			if ( has_shortcode( $archive_names['all_prefix'], 'product_category_name' ) ) {
				$title = do_shortcode( $archive_names['all_prefix'] );
			} else {
				$title = do_shortcode( $archive_names['all_prefix'] ) . ' ' . $the_tax->name;
			}
		} else {
			$title = $the_tax->name;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search query parameter.
	} elseif ( is_ic_product_search() && isset( $_GET['s'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,Minnow.PHP.PHPTypecasting.stringLiteralStringConcat -- Read-only search query parameter.
		$search_keyword = apply_filters( 'ic_search_keayword', sanitize_text_field( wp_unslash( (string) $_GET['s'] ) ) );
		if ( ! empty( $search_keyword ) ) {
			$title = esc_html__( 'Search Results for:', 'post-type-x' ) . ' <span class="ic-search-keyword">' . esc_html( $search_keyword ) . '</span>';
		} else {
			$title = '';
		}
	} elseif ( is_ic_product_listing() ) {
		$title = get_product_listing_title();
	} else {
		$title = get_the_title();
	}

	return $title;
}

add_action( 'product_details', 'show_short_desc', 5, 1 );

/**
 * Shows the short description.
 *
 * @param int|object|false $product_id Product ID or post object.
 *
 * @return void
 */
function show_short_desc( $product_id = false ) {
	if ( is_object( $product_id ) && isset( $product_id->ID ) ) {
		$product_id = $product_id->ID;
	}
	add_filter( 'product_short_description', 'wptexturize' );
	add_filter( 'product_short_description', 'convert_smilies' );
	add_filter( 'product_short_description', 'convert_chars' );
	add_filter( 'product_short_description', 'wpautop' );
	add_filter( 'product_short_description', 'shortcode_unautop' );
	add_filter( 'product_short_description', 'do_shortcode', 11 );
	ic_show_template_file( 'product-page/product-short-description.php', AL_BASE_TEMPLATES_PATH, $product_id );
}

add_action( 'after_product_details', 'show_product_description', 10, 1 );

/**
 * Shows the full product description.
 *
 * @param int|object|false $product_id Product ID or post object.
 *
 * @return void
 */
function show_product_description( $product_id = false ) {
	if ( is_object( $product_id ) && isset( $product_id->ID ) ) {
		$product_id = $product_id->ID;
	}
	add_filter( 'product_simple_description', 'wptexturize' );
	add_filter( 'product_simple_description', 'convert_smilies' );
	add_filter( 'product_simple_description', 'convert_chars' );
	add_filter( 'product_simple_description', 'wpautop' );
	add_filter( 'product_simple_description', 'shortcode_unautop' );
	$add_filter = false;
	if ( has_filter( 'the_content', array( 'ic_catalog_template', 'product_page_content' ) ) ) {
		remove_filter( 'the_content', array( 'ic_catalog_template', 'product_page_content' ) );
		$add_filter = true;
	}
	ic_show_template_file( 'product-page/product-description.php', AL_BASE_TEMPLATES_PATH, $product_id );
	if ( $add_filter ) {
		add_filter( 'the_content', array( 'ic_catalog_template', 'product_page_content' ) );
	}
}

add_action( 'single_product_end', 'show_related_categories', 10, 3 );

/**
 * Shows the related categories table on the product page.
 *
 * @param object $post          Current post object.
 * @param array  $single_names  Single template names.
 * @param string $taxonomy_name Taxonomy name.
 *
 * @return void
 */
function show_related_categories( $post, $single_names, $taxonomy_name ) {
	$settings = get_multiple_settings();
	if ( 'categories' === $settings['related'] ) {
		if ( ! empty( $post->ID ) ) {
			$product_id = $post->ID;
		} elseif ( is_numeric( $post ) ) {
			$product_id = $post;
		}
		if ( empty( $product_id ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is generated by the related categories template helper.
		echo get_related_categories( $product_id, $single_names, $taxonomy_name );
	} elseif ( 'products' === $settings['related'] ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is generated by the related products template helper.
		echo get_related_products( null, true );
	}
}

/**
 * Returns the related categories table.
 *
 * @param int        $product_id     Product ID.
 * @param array|null $v_single_names Single template names.
 * @param string     $taxonomy_name  Taxonomy name.
 *
 * @return string
 */
function get_related_categories( $product_id, $v_single_names = null, $taxonomy_name = 'al_product-cat' ) {
	$single_names = isset( $v_single_names ) ? $v_single_names : get_single_names();
	$terms        = wp_get_post_terms(
		$product_id,
		$taxonomy_name,
		array(
			'fields'    => 'ids',
			'orderby'   => 'none',
			'childless' => true,
		)
	);
	if ( empty( $terms ) || is_wp_error( $terms ) || 'simple' === get_integration_type() ) {
		return;
	}
	$args       = array(
		'title_li'     => '',
		'taxonomy'     => $taxonomy_name,
		'include'      => $terms,
		'echo'         => 0,
		'hierarchical' => 0,
		'style'        => 'none',
	);
	$categories = wp_list_categories( $args );
	$table      = '';
	if ( '<li class="cat-item-none">No categories</li>' !== $categories ) {
		$table .= '<div id="product_subcategories" class="product-subcategories">';
		$table .= '<table>';
		$table .= '<tr>';
		$table .= '<td>';
		$table .= esc_html( $single_names['other_categories'] );
		$table .= '</td>';
		$table .= '<td>';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Category list HTML is generated by wp_list_categories().
		$table .= trim( trim( str_replace( '<br />', ', ', $categories ) ), ',' );
		$table .= '</td>';
		$table .= '</tr>';
		$table .= '</table>';
		$table .= '</div>';

		return $table;
	}
}

add_filter( 'the_content', 'show_simple_product_listing' );

/**
 * Shows product listing in simple mode if no shortcode exists.
 *
 * @param string $content Current post content.
 *
 * @return string
 */
function show_simple_product_listing( $content ) {
	if ( is_main_query() && in_the_loop() && 'simple' === get_integration_type() && is_ic_product_listing() && ! is_ic_shortcode_integration() && is_ic_product_listing_enabled() ) {
		if ( ! has_shortcode( $content, 'show_products' ) ) {
			$archive_multiple_settings = get_multiple_settings();
			$content                  .= do_shortcode( '[show_products pagination=1 products_limit="' . $archive_multiple_settings['archive_products_limit'] . '"]' );
		}
	}

	return $content;
}

/**
 * Returns the quasi post type slug used by the catalog.
 *
 * @param string|array|null $post_type Post type value.
 *
 * @return string
 */
function get_quasi_post_type( $post_type = null ) {
	if ( ( empty( $post_type ) && is_home_archive() ) || ( is_array( $post_type ) && in_array( 'al_product', $post_type, true ) ) ) {
		$post_type = 'al_product';
	} elseif ( empty( $post_type ) ) {
		$post_type = get_post_type();
	}
	$quasi_post_type = substr( $post_type, 0, 10 );

	return $quasi_post_type;
}

/**
 * Returns the quasi taxonomy name used by the catalog.
 *
 * @param string $tax_name Taxonomy name.
 * @param bool   $exact    Whether an exact quasi name is required.
 *
 * @return string
 */
function get_quasi_post_tax_name( $tax_name, $exact = true ) {
	if ( $exact ) {
		$quasi_tax_name = substr( $tax_name, 0, 14 );
	} elseif ( strpos( $tax_name, 'al_product-cat' ) !== false ) {
		$quasi_tax_name = 'al_product-cat';
	}

	return $quasi_tax_name;
}

/**
 * Renders catalog breadcrumbs.
 *
 * @return string|null
 */
function product_breadcrumbs() {
	if ( 'simple' !== get_integration_type() && ! is_front_page() ) {
		$post_type = get_post_type();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only post type routing parameter.
		if ( empty( $post_type ) && isset( $_GET['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only post type routing parameter.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only post type routing parameter.
			$post_type = sanitize_text_field( wp_unslash( $_GET['post_type'] ) );
		}
		$home_page = get_home_url();
		if ( function_exists( 'additional_product_listing_url' ) && 'al_product' !== $post_type && ic_string_contains( $post_type, 'al_product' ) ) {
			if ( ! ic_string_contains( $post_type, 'al_product' ) ) {
				return;
			}
			$catalog_id       = catalog_id( $post_type );
			$product_archives = additional_product_listing_url();
			$product_archive  = $product_archives[ $catalog_id ];
			$archives_ids     = get_option( 'additional_product_archive_id' );
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Legacy default option format.
			if ( defined( 'DEFAULT_PRODUCT_BREADCRUMBS' ) ) {
				$breadcrumbs_options = get_option( 'product_breadcrumbs', unserialize( DEFAULT_PRODUCT_BREADCRUMBS ) );
			} else {
				$breadcrumbs_options = get_option( 'product_breadcrumbs', array() );
			}
			if ( ! is_array( $breadcrumbs_options ) ) {
				$breadcrumbs_options = array();
			}
			if ( empty( $breadcrumbs_options['enable_product_breadcrumbs'][ $catalog_id ] ) || ( ! empty( $breadcrumbs_options['enable_product_breadcrumbs'][ $catalog_id ] ) && 1 !== (int) $breadcrumbs_options['enable_product_breadcrumbs'][ $catalog_id ] ) ) {
				return;
			}
			$product_archive_title_options = $breadcrumbs_options['breadcrumbs_title'][ $catalog_id ];
			if ( '' !== $product_archive_title_options ) {
				$product_archive_title = $product_archive_title_options;
			} elseif ( 'noid' === $archives_ids[ $catalog_id ] && function_exists( 'get_catalogs_names' ) ) {
				$product_archive_title = get_catalogs_names( $catalog_id )['plural'];
			} else {
				$product_archive_title = get_the_title( $archives_ids[ $catalog_id ] );
			}
		} else {
			$archive_multiple_settings = get_multiple_settings();
			if ( empty( $archive_multiple_settings['enable_product_breadcrumbs'] ) || 1 !== (int) $archive_multiple_settings['enable_product_breadcrumbs'] ) {
				return;
			}
			$product_archive = product_listing_url();
			if ( '' !== $archive_multiple_settings['breadcrumbs_title'] ) {
				$product_archive_title = $archive_multiple_settings['breadcrumbs_title'];
			} else {
				$product_archive_title = get_product_listing_title();
			}
		}
		$additional = '';
		if ( is_ic_product_page() ) {
			$current_product = apply_filters( 'ic_catalog_breadcrumbs_current_product', get_the_title() );
		} elseif ( is_ic_taxonomy_page() ) {
			$obj                 = ic_get_queried_object();
			$current_product     = $obj->name;
			$taxonomy            = isset( $obj->taxonomy ) ? $obj->taxonomy : 'al_product-cat';
			$current_category_id = $obj->term_id;
			$category_parents    = ic_get_product_category_parents( $current_category_id, $taxonomy, true, '|', null, array(), ' itemprop="item" ', '<span itemprop="name">', '</span>' );
			if ( $category_parents && ! is_wp_error( $category_parents ) ) {
				$parents = array_filter( explode( '|', $category_parents ) );
				if ( is_array( $parents ) ) {
					array_pop( $parents );
				}
			}
		} elseif ( is_search() ) {
			$current_product = __( 'Product Search', 'post-type-x' );
		} else {
			$current_product = '';
		}
		$bread_divider = apply_filters( 'ic_breadcrumbs_divider', '»' );
		$archive_names = get_archive_names();
		$bread         = '<p id="breadcrumbs"><span>';
		if ( ! empty( $archive_names['bread_home'] ) ) {
			$home_link = '<span class="breadcrumbs-home"><a href="' . esc_url( $home_page ) . '"><span>' . esc_html( $archive_names['bread_home'] ) . '</span></a></span> ' . esc_html( $bread_divider ) . ' ';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Filtered breadcrumb HTML is escaped when built.
			$bread .= apply_filters( 'product_breadcrumbs_home', $home_link );
		}
		if ( ! empty( $product_archive ) ) {
			$bread .= '<span class="breadcrumbs-product-archive"><a href="' . esc_url( $product_archive ) . '"><span>' . esc_html( $product_archive_title ) . '</span></a></span>';
		}
		if ( ! empty( $parents ) && is_array( $parents ) ) {
			foreach ( $parents as $parent ) {
				if ( ! empty( $parent ) ) {
					$additional .= ' ' . esc_html( $bread_divider ) . ' <span>' . $parent . '</span>';
				}
			}
			if ( ! empty( $additional ) ) {
				$bread .= $additional;
			}
		}
		if ( ! empty( $current_product ) ) {
			$bread .= ' ' . esc_html( $bread_divider ) . ' <span><span class="breadcrumb_last">' . esc_html( $current_product ) . '</span></span>';
		}
		$bread .= '</span>';
		$bread .= '</p>';

		return $bread;
	}

	return '';
}

/**
 * Returns the current queried product category ID.
 *
 * @return int|string
 */
function ic_get_current_category_id() {
	$current_category_id = '';
	if ( is_ic_taxonomy_page() ) {
		$cached = ic_get_global( 'current_category_id' );
		if ( false !== $cached ) {
			return apply_filters( 'ic_current_category_id', $cached );
		}
		$obj = ic_get_queried_object();
		if ( ! empty( $obj->term_id ) ) {
			$current_category_id = $obj->term_id;
			ic_save_global( 'current_category_id', $current_category_id );
		}
	}

	return apply_filters( 'ic_current_category_id', $current_category_id );
}

/**
 * Returns the product category URL.
 *
 * @param int|string $category_id Category ID.
 *
 * @return string
 */
function ic_get_category_url( $category_id ) {
	$link = '';
	if ( is_numeric( $category_id ) ) {
		$link = get_term_link( $category_id );
	}

	return apply_filters( 'ic_category_url', $link, $category_id );
}

/**
 * Returns the cached category listing image HTML.
 *
 * @param int|string $term_id Term ID.
 *
 * @return string
 */
function ic_get_category_listing_image_html( $term_id ) {
	$image_html = ic_get_global( 'ic_category_listing_image_html_' . $term_id );
	if ( $image_html ) {
		return $image_html;
	}

	return '';
}

/**
 * Returns linked or plain parent category names for a term.
 *
 * @param int         $id        Category term ID.
 * @param string      $taxonomy  Taxonomy name.
 * @param bool        $link      Whether links should be included.
 * @param string      $separator Parent separator.
 * @param bool        $nicename  Whether to use the term slug.
 * @param array       $visited   Visited term IDs.
 * @param string      $attr      Anchor attributes.
 * @param string|null $open      Opening name wrapper.
 * @param string|null $close     Closing name wrapper.
 *
 * @return string|WP_Error
 */
function ic_get_product_category_parents(
	$id,
	$taxonomy,
	$link = false,
	$separator = '/',
	$nicename = false,
	$visited = array(),
	$attr = '',
	$open = null,
	$close = null
) {
	$chain  = '';
	$parent = get_term( $id, $taxonomy );

	if ( is_wp_error( $parent ) ) {
		return $parent;
	}

	if ( $nicename ) {
		$name = $parent->slug;
	} else {
		$name = $parent->name;
	}

	if ( ! empty( $parent->parent ) && $parent->parent !== $parent->term_id && ! in_array( $parent->parent, $visited, true ) ) {
		$visited[] = $parent->parent;
		$chain    .= ic_get_product_category_parents( $parent->parent, $taxonomy, $link, $separator, $nicename, $visited, $attr, $open, $close );
	}

	if ( ! $link ) {
		$chain .= $name . $separator;
	} else {
		$url    = ic_get_category_url( $parent->term_id );
		$chain .= '<a ' . $attr . ' href="' . $url . '">' . $open . $name . $close . '</a>' . $separator;
	}

	return $chain;
}

add_action( 'single_product_begin', 'add_product_breadcrumbs' );
add_action( 'product_listing_begin', 'add_product_breadcrumbs' );

/**
 * Shows product breadcrumbs.
 *
 * @return void
 */
function add_product_breadcrumbs() {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Breadcrumb HTML is escaped when built.
	echo product_breadcrumbs();
}

/**
 * Registers legacy catalog widgets.
 *
 * @return void
 */
function al_product_register_widgets() {
	register_widget( 'Product_Cat_Widget' );
	register_widget( 'product_widget_search' );
	do_action( 'implecode_register_widgets' );
}

add_action( 'widgets_init', 'al_product_register_widgets' );

/**
 * Adds legacy widget IDs to the block-widget hide list.
 *
 * @param array $widget_types Hidden widget type IDs.
 *
 * @return array
 */
function ic_hide_legacy_widget( $widget_types ) {
	$widget_types[] = 'product_categories';
	$widget_types[] = 'product_search';
	$widget_types[] = 'product_category_filter';
	$widget_types[] = 'product_sort_filter';
	$widget_types[] = 'product_price_filter';
	$widget_types[] = 'ic_product_size_filter';
	$widget_types[] = 'related_products_widget';

	return $widget_types;
}

add_filter( 'widget_types_to_hide_from_legacy_widget_block', 'ic_hide_legacy_widget' );

if ( ! function_exists( 'permalink_options_update' ) ) {

	/**
	 * Updates the permalink rewrite option that triggers the rewrite function
	 */
	function permalink_options_update() {
		update_option( 'al_permalink_options_update', 1, false );
	}

}
if ( ! function_exists( 'check_permalink_options_update' ) ) {

	add_action( 'admin_footer', 'check_permalink_options_update', 99 );

	/**
	 * Checks if the permalinks should be rewritten and does it if necessary.
	 *
	 * @return void
	 */
	function check_permalink_options_update() {
		$options_update = get_option( 'al_permalink_options_update', 'none' );
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- Custom catalog capability registered by the plugin.
		if ( 'none' !== $options_update && ( defined( 'DOING_CRON' ) || current_user_can( 'manage_product_settings' ) || current_user_can( 'edit_pages' ) ) ) {
			flush_rewrite_rules();
			update_option( 'al_permalink_options_update', 'none', false );
		}
	}

}


add_action( 'before_product_details', 'show_product_gallery', 10, 2 );

/**
 * Shows product gallery on the product page.
 *
 * @param int   $product_id     Product ID.
 * @param array $single_options Single product options.
 *
 * @return void
 */
function show_product_gallery( $product_id, $single_options ) {
	if ( 1 === (int) $single_options['enable_product_gallery'] ) {
		$product_image = get_product_image( $product_id );
		if ( ! empty( $product_image ) ) {
			ic_show_template_file( 'product-page/product-image.php', AL_BASE_TEMPLATES_PATH, $product_id );
		}
	}
}

/**
 * Returns the whole product gallery for the product page.
 *
 * @param int        $product_id        Product ID.
 * @param array|null $v_single_options Single product options.
 *
 * @return string|null
 */
function get_product_gallery( $product_id, $v_single_options = null ) {
	$single_options = isset( $v_single_options ) ? $v_single_options : get_product_page_settings();
	if ( 1 === (int) $single_options['enable_product_gallery'] ) {
		$product_image = get_product_image( $product_id );
		if ( ! empty( $product_image ) ) {
			ob_start();
			ic_show_template_file( 'product-page/product-image.php', AL_BASE_TEMPLATES_PATH, $product_id );
			$product_gallery = ob_get_clean();

			return $product_gallery;
		}
	}

	return null;
}

/**
 * Returns a CSS class when the product gallery should be hidden.
 *
 * @param int   $enable          Gallery enabled flag.
 * @param int   $enable_inserted Inserted image enabled flag.
 * @param mixed $post            Unused post context.
 *
 * @return string
 */
function product_gallery_enabled( $enable, $enable_inserted, $post ) {
	$details_class = 'no-image';
	if ( 1 === (int) $enable ) {
		if ( 1 === (int) $enable_inserted && ! has_post_thumbnail( $post ) ) {
			return $details_class;
		} else {
			return '';
		}
	}

	return $details_class;
}

if ( ! function_exists( 'product_post_type_array' ) ) {

	/**
	 * Returns the catalog product post types.
	 *
	 * @return array
	 */
	function product_post_type_array() {
		$array = apply_filters( 'product_post_type_array', array( 'al_product' ) );

		return $array;
	}

}

/**
 * Returns the catalog product taxonomies.
 *
 * @return array
 */
function product_taxonomy_array() {

	return apply_filters( 'product_taxonomy_array', array( 'al_product-cat' ) );
}

if ( ! function_exists( 'array_to_url' ) ) {

	/**
	 * Encodes an array for use in legacy URLs.
	 *
	 * @param array $input_array Data to encode.
	 *
	 * @return string
	 */
	function array_to_url( $input_array ) {
		return rawurlencode( wp_json_encode( $input_array ) );
	}

}

if ( ! function_exists( 'url_to_array' ) ) {

	/**
	 * Decodes legacy array data from a URL string.
	 *
	 * @param string $url              Encoded URL payload.
	 * @param bool   $maybe_serialized Whether serialized data should be supported.
	 *
	 * @return array|string
	 */
	function url_to_array( $url, $maybe_serialized = true ) {
		$data = stripslashes( urldecode( $url ) );
		if ( $maybe_serialized && is_serialized( $data ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize,WordPress.PHP.DiscouragedPHPFunctions.MaybeSerialize_unserialize,WordPress.PHP.NoSilencedErrors.Discouraged -- Legacy payloads may still be serialized and are conditionally unserialized after validation.
			return @unserialize( trim( $data ), array( 'allowed_classes' => false ) );
		}
		$json_data = json_decode( trim( $data ), true );
		if ( JSON_ERROR_NONE === json_last_error() ) {
			return $json_data;
		}

		return $data;
	}

}

add_action( 'wp', 'modify_product_listing_title_tag', 99 );

/**
 * Hooks catalog title filters for product archive requests.
 *
 * @return void
 */
function modify_product_listing_title_tag() {
	if ( is_ic_product_listing() ) {
		add_filter( 'wp_title', 'product_archive_title', 99, 3 );
		add_filter( 'wp_title', 'product_archive_custom_title', 99, 3 );
		add_filter( 'document_title_parts', 'product_archive_title', 99, 3 );
		add_filter( 'document_title_parts', 'product_archive_custom_title', 99, 3 );
	}
}

/**
 * Filters the custom archive title using SEO settings.
 *
 * @param string|array|null $title       Current title value.
 * @param string|null       $sep         Title separator.
 * @param string|null       $seplocation Separator location.
 *
 * @return string|array|null
 */
function product_archive_custom_title( $title = null, $sep = null, $seplocation = null ) {
	global $post;
	if ( is_post_type_archive( 'al_product' ) && is_object( $post ) && 'al_product' === $post->post_type ) {
		$settings = get_multiple_settings();
		if ( '' !== $settings['seo_title'] ) {
			$settings['seo_title']     = isset( $settings['seo_title'] ) ? $settings['seo_title'] : '';
			$settings['seo_title_sep'] = isset( $settings['seo_title_sep'] ) ? $settings['seo_title_sep'] : '';
			if ( 1 === (int) $settings['seo_title_sep'] ) {
				if ( '' !== $sep ) {
					$sep = ' ' . $sep . ' ';
				}
			} else {
				$sep = '';
			}
			if ( is_array( $title ) ) {
				$title['title'] = $settings['seo_title'];
			} elseif ( 'right' === $seplocation ) {
				$title = $settings['seo_title'] . $sep;
			} else {
				$title = $sep . $settings['seo_title'];
			}
		}
	}

	return $title;
}

/**
 * Filters the archive title from the listing page title when SEO title is empty.
 *
 * @param string|array|null $title       Current title value.
 * @param string|null       $sep         Title separator.
 * @param string|null       $seplocation Separator location.
 *
 * @return string|array|null
 */
function product_archive_title( $title = null, $sep = null, $seplocation = null ) {
	global $post;
	if ( is_ic_product_listing() && is_object( $post ) && 'al_product' === $post->post_type ) {
		$settings = get_multiple_settings();
		if ( '' === $settings['seo_title'] ) {
			$id = get_product_listing_id();
			if ( ! empty( $id ) ) {
				if ( is_array( $title ) ) {
					$title['title'] = get_the_title( $id );
				} else {
					$title = ic_get_single_post_title( $title, $id, $sep, $seplocation );
				}
			}
		}
	}

	return $title;
}

/**
 * Resolves a page title by temporarily querying the listing page.
 *
 * @param string|array|null $title       Current title value.
 * @param int               $post_id     Listing page ID.
 * @param string|null       $sep         Title separator.
 * @param string|null       $seplocation Separator location.
 *
 * @return string|array|null
 */
function ic_get_single_post_title( $title, $post_id, $sep, $seplocation ) {
	global $wp_query;
		$old_wp_query  = $wp_query;
		$listing_query = new WP_Query( array( 'page_id' => $post_id ) );
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Temporary query swap for legacy title generation.
		$wp_query = $listing_query;
	if ( ! empty( $listing_query->posts ) ) {
		remove_filter( 'wp_title', 'product_archive_title', 99, 3 );
		$title = wp_title( $sep, false, $seplocation );
	}
	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the original query after title generation.
	$wp_query = $old_wp_query;

	return $title;
}

/**
 * Adds the Support plugin row meta link.
 *
 * @param array  $links Existing row meta links.
 * @param string $file  Plugin basename.
 *
 * @return array
 */
function add_support_link( $links, $file ) {

	$plugin = plugin_basename( AL_PLUGIN_MAIN_FILE );

	// Create the support link only for this plugin row.
	if ( $file === $plugin ) {
		return array_merge(
			$links,
			array( sprintf( '<a href="%1$s">%2$s</a>', esc_url( admin_url( 'edit.php?post_type=al_product&page=product-settings.php&tab=product-settings&submenu=support' ) ), esc_html__( 'Support', 'post-type-x' ) ) )
		);
	}

	return $links;
}

add_filter( 'plugin_row_meta', 'add_support_link', 10, 2 );

if ( ! function_exists( 'implecode_al_box' ) ) {

	/**
	 * Builds an impleCode admin notice box.
	 *
	 * @param string $text Notice text or HTML.
	 * @param string $type Notice type.
	 * @param int    $should_echo Whether to echo the box.
	 *
	 * @return string
	 */
	function implecode_al_box( $text, $type = 'info', $should_echo = 1 ) {
		$box  = '<div class="al-box ' . esc_attr( $type ) . '">';
		$box .= $text;
		$box .= '</div>';

		return echo_ic_setting( $box, $should_echo );
	}

}

/**
 * Returns catalog products for dropdowns and settings screens.
 *
 * @param string|null $orderby Sort column.
 * @param string|null $order   Sort direction.
 * @param int|null    $per_page Results per page.
 * @param int|null    $offset   Query offset.
 * @param array|null  $custom   Additional query arguments.
 *
 * @return array
 */
function get_all_catalog_products( $orderby = null, $order = null, $per_page = null, $offset = null, $custom = null ) {
	ic_raise_memory_limit();
	if ( ic_is_reaching_memory_limit() ) {
		wp_cache_flush();
	}
	$product_post_types = product_post_type_array();
	$post_types         = array();
	foreach ( $product_post_types as $post_type ) {
		if ( ic_string_contains( $post_type, 'al_product' ) ) {
			$post_types[] = $post_type;
		}
	}
	$args = apply_filters(
		'ic_get_all_catalog_products_args',
		array(
			'post_type'      => $post_types,
			'post_status'    => ic_visible_product_status(),
			// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Full product list is needed for selector inputs.
			'posts_per_page' => 500,
		)
	);
	if ( ! empty( $orderby ) ) {
		$args['orderby'] = $orderby;
	}
	if ( ! empty( $order ) ) {
		$args['order'] = $order;
	}
	if ( ! empty( $per_page ) ) {
		$args['posts_per_page'] = $per_page;
	}
	if ( ! empty( $offset ) ) {
		$args['offset'] = $offset;
	}
	if ( ! empty( $custom ) ) {
		$args = array_merge( $args, $custom );
	}
	$products = get_posts( $args );
	if ( ic_is_reaching_memory_limit() ) {
		wp_cache_flush();
	}

	return $products;
}

/**
 * Builds a product dropdown field.
 *
 * @param string           $option_name    Field name.
 * @param string           $first_option   Placeholder text.
 * @param int|array|string $selected_value Selected value or values.
 * @param string|null      $orderby        Sort column.
 * @param string|null      $order          Sort direction.
 * @param bool             $all_option     Whether to add the "All" option.
 * @param string           $attr           Extra select attributes.
 * @param array            $before_options Extra options keyed by value.
 * @param array            $pages          Product posts.
 *
 * @return string
 */
function all_ctalog_products_dropdown(
	$option_name,
	$first_option,
	$selected_value,
	$orderby = null,
	$order = null,
	$all_option = false,
	$attr = '',
	$before_options = array(),
	$pages = array()
) {
	if ( empty( $pages ) ) {
		$pages = get_all_catalog_products( $orderby, $order );
	}
	if ( is_array( $selected_value ) ) {
		$attr .= ' multiple';
	}
	$select_box  = '<select class="all_products_dropdown ic_chosen" data-placeholder="' . esc_attr( $first_option ) . '" id="' . esc_attr( $option_name ) . '" name="' . esc_attr( $option_name ) . '"' . $attr . '>';
	$select_box .= '<option value=""></option>';
	foreach ( $before_options as $option_value => $option_label ) {
		$select_box .= '<option value="' . esc_attr( $option_value ) . '">' . esc_html( $option_label ) . '</option>';
	}
	foreach ( $pages as $page ) {
		$selected_attr = '';
		if ( is_array( $selected_value ) ) {
			if ( in_array( $page->ID, $selected_value, true ) ) {
				$selected_attr = 'selected';
			}
		} else {
			$selected_attr = selected( $page->ID, $selected_value, false );
		}
		$select_box .= '<option class="id_' . esc_attr( $page->ID ) . '" name="' . esc_attr( $option_name ) . '[' . esc_attr( $page->ID ) . ']" value="' . esc_attr( $page->ID ) . '" ' . $selected_attr . '>' . esc_html( $page->post_title ) . '</option>';
	}
	if ( $all_option ) {
		$select_box .= '<option class="id_all" name="' . esc_attr( $option_name ) . '[all]" value="all" ' . selected( 'all', $selected_value, false ) . '>' . esc_html__( 'All', 'post-type-x' ) . '</option>';
	}
	$select_box .= '</select>';

	return $select_box;
}

add_action( 'after_setup_theme', 'thumbnail_support_products', 99 );
add_action( 'init', 'thumbnail_support_products', 99 );

/**
 * Adds featured image support for products.
 *
 * @return void
 */
function thumbnail_support_products() {
	$support       = get_theme_support( 'post-thumbnails' );
	$support_array = product_post_type_array();
	if ( is_array( $support ) ) {
		if ( ! in_array( 'al_product', $support[0], true ) ) {
			$support_array = array_merge( $support[0], $support_array );
			add_theme_support( 'post-thumbnails', $support_array );
		}
	} elseif ( ! $support ) {
		add_theme_support( 'post-thumbnails', $support_array );
	} else {
		add_theme_support( 'post-thumbnails' );
	}
}

add_action( 'pre_get_posts', 'ic_pre_get_products', 99 );

/**
 * Routes main catalog queries through the catalog-specific pre_get_posts hooks.
 *
 * @param WP_Query $query Current query object.
 *
 * @return void
 */
function ic_pre_get_products( $query ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only ordering parameter check.
	if ( ( ( ! is_admin() && $query->is_main_query() ) || ( is_ic_ajax() && is_object( $query ) && empty( $query->query['ic_current_products'] ) ) ) && ! isset( $_GET['order'] ) && ( ( ! empty( $query->query['post_type'] ) && ic_string_contains( $query->query['post_type'], 'al_product' ) ) || is_ic_product_listing( $query ) || is_ic_taxonomy_page( $query ) || is_ic_product_search( $query ) || is_home_archive( $query ) ) ) {
		do_action( 'ic_pre_get_products', $query );
		if ( is_ic_product_listing( $query ) ) {
			do_action( 'ic_pre_get_products_listing', $query );
		} elseif ( is_ic_taxonomy_page( $query ) ) {
			do_action( 'ic_pre_get_products_tax', $query );
		} elseif ( is_ic_product_search( $query ) ) {
			do_action( 'ic_pre_get_products_search', $query );
		}
		if ( apply_filters( 'ic_force_pre_get_products_only', false, $query ) || ( ! empty( $query->query['post_type'] ) && ic_string_contains( $query->query['post_type'], 'al_product' ) && empty( $query->query['name'] ) ) || ( ! empty( $query->query ) && is_array( $query->query ) && ( ic_string_contains( implode( '::', array_keys( $query->query ) ), 'al_product-cat' ) || ! empty( $query->query['al_product-cat'] ) ) ) ) {
			do_action( 'ic_pre_get_products_only', $query );
		}
	}
}

add_action( 'pre_get_posts', 'ic_forced_categories_only_query_fast_path', 100 );

/**
 * Reduces the discarded product query on forced category-only listings.
 *
 * One product is retained so WordPress and theme archive state remains valid.
 * Secondary queries, including product shortcodes and blocks, are not changed.
 *
 * @param WP_Query $query Current query object.
 *
 * @return void
 */
function ic_forced_categories_only_query_fast_path( $query ) {
	if ( is_admin() || is_ic_ajax() || ! is_object( $query ) || ! $query->is_main_query() || ! is_ic_product_listing( $query ) ) {
		return;
	}
	$archive_multiple_settings = get_multiple_settings();
	if ( 'forced_cats_only' !== $archive_multiple_settings['product_listing_cats'] ) {
		return;
	}
	$query->set( 'ic_forced_categories_only_fast_path', 1 );
	$query->set( 'posts_per_page', 1 );
	$query->set( 'no_found_rows', true );
	$query->set( 'orderby', 'none' );
	$query->set( 'update_post_meta_cache', false );
	$query->set( 'update_post_term_cache', false );
	$query->set( 'lazy_load_term_meta', false );
}

add_filter( 'shortcode_query', 'ic_pre_get_products_shortcode' );

/**
 * Routes shortcode queries through the catalog shortcode hook.
 *
 * @param array $query Shortcode query arguments.
 *
 * @return array
 */
function ic_pre_get_products_shortcode( $query ) {
	do_action( 'ic_pre_get_products_shortcode', $query );

	return $query;
}

add_action( 'ic_pre_get_products_only', 'set_product_order', 30 );

/**
 * Sets the default product order.
 *
 * @param WP_Query $query Current query object.
 *
 * @return void
 */
function set_product_order( $query ) {
	if ( $query->get( 'ic_forced_categories_only_fast_path' ) || ! ic_is_main_query( $query ) ) {
		return;
	}
	$archive_multiple_settings = get_multiple_settings();
	$excluded_orders           = apply_filters( 'ic_excluded_product_orders', array() );
	$product_order             = '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only ordering parameter.
	if ( isset( $_GET['product_order'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only ordering parameter.
		$product_order = sanitize_text_field( wp_unslash( $_GET['product_order'] ) );
	}
	if ( ! is_ic_product_search( $query ) && ( '' === $product_order || in_array( $product_order, $excluded_orders, true ) ) ) {
		if ( 'product-name' === $archive_multiple_settings['product_order'] ) {
			$query->set( 'orderby', 'title' );
			$query->set( 'order', 'ASC' );
		} else {
			$query = apply_filters( 'modify_product_order', $query, $archive_multiple_settings );
		}
		$session = get_product_catalog_session();
		if ( isset( $session['filters']['product_order'] ) ) {
			unset( $session['filters']['product_order'] );
			set_product_catalog_session( $session );
		}
	} elseif ( '' !== $product_order ) {
		$orderby = translate_product_order();
		$query->set( 'orderby', $orderby );
		if ( 'date' === $orderby ) {
			$query->set( 'order', 'DESC' );
		} else {
			$query->set( 'order', 'ASC' );
		}
		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Legacy hook name kept for compatibility.
		$query                               = apply_filters( 'modify_product_order-dropdown', $query, $archive_multiple_settings );
		$session                             = get_product_catalog_session();
		$session['filters']['product_order'] = $product_order;
		set_product_catalog_session( $session );
	}
	do_action( 'ic_product_order_set', $query );
}

add_filter( 'shortcode_query', 'set_shortcode_product_order', 10, 2 );
add_filter( 'home_product_listing_query', 'set_shortcode_product_order' );

/**
 * Applies the selected product order to shortcode catalog queries.
 *
 * @param array      $shortcode_query Shortcode query arguments.
 * @param array|null $args            Shortcode arguments.
 *
 * @return array
 */
function set_shortcode_product_order( $shortcode_query, $args = null ) {
	$archive_multiple_settings = get_multiple_settings();
	$excluded_orders           = apply_filters( 'ic_excluded_product_orders', array() );
	$product_order             = '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only ordering parameter.
	if ( isset( $_GET['product_order'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only ordering parameter.
		$product_order = sanitize_text_field( wp_unslash( $_GET['product_order'] ) );
	}
	if ( '' === $product_order || in_array( $product_order, $excluded_orders, true ) ) {
		if ( 'product-name' === $archive_multiple_settings['product_order'] && empty( $args['orderby'] ) ) {
			$shortcode_query['orderby'] = 'title';
			$shortcode_query['order']   = 'ASC';
		}
		if ( isset( $shortcode_query['orderby'] ) && 'name' === $shortcode_query['orderby'] ) {
			$shortcode_query['orderby'] = 'title';
		}
		$shortcode_query = apply_filters( 'shortcode_modify_product_order', $shortcode_query, $archive_multiple_settings, $args );
	} elseif ( 'newest' !== $product_order ) {
		$orderby                    = translate_product_order();
		$shortcode_query['orderby'] = $orderby;
		$shortcode_query['order']   = 'ASC';
		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Legacy hook name kept for compatibility.
		$shortcode_query = apply_filters( 'shortcode_modify_product_order-dropdown', $shortcode_query, $archive_multiple_settings );
	}

	return apply_filters( 'ic_shortcode_product_order_set', $shortcode_query );
}

// Legacy template action remains disabled.

/**
 * Shows the product order dropdown.
 *
 * @param string|null $archive_template   Archive template slug.
 * @param array|null  $multiple_settings  Catalog settings.
 * @param mixed       $instance           Widget or block instance.
 *
 * @global string $product_sort
 *
 * @return void
 */
function show_product_order_dropdown( $archive_template = null, $multiple_settings = null, $instance = null ) {
	$multiple_settings = empty( $multiple_settings ) ? get_multiple_settings() : $multiple_settings;
	$sort_options      = apply_filters( 'ic_product_order_dropdown_options', get_product_sort_options(), $instance );
	$selected          = apply_filters( 'ic_product_order_dropdown_selected', $multiple_settings['product_order'], $instance );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only ordering parameter.
	if ( isset( $_GET['product_order'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only ordering parameter.
		$selected = sanitize_text_field( wp_unslash( $_GET['product_order'] ) );
	}
	$action = get_filter_widget_action( $instance );
	$class  = '';
	if ( is_product_filter_active( 'product_order' ) ) {
		$class .= 'filter-active';
	}
	$form_open = '<form class="product_order ic_ajax ' . esc_attr( $class ) . '" data-ic_responsive_label="' . esc_attr__( 'Sort by', 'post-type-x' ) . '" data-ic_ajax="product_order" action="' . esc_url( $action ) . '"><select class="product_order_selector ic_self_submit" name="product_order">';
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Form markup is escaped during assembly.
	echo $form_open;
	if ( is_ic_product_search() ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only ordering parameter.
		if ( isset( $_GET['product_order'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only ordering parameter.
			$selected = sanitize_text_field( wp_unslash( $_GET['product_order'] ) );
		} else {
			$selected = '';
		}
		$search_option = '<option value="" ' . selected( '', $selected, false ) . '>' . esc_html__( 'Sort by Relevance', 'post-type-x' ) . '</option>';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Option markup is escaped during assembly.
		echo $search_option;
	}

	foreach ( $sort_options as $name => $value ) {
		$option = '<option value="' . esc_attr( $name ) . '" ' . selected( $name, $selected, false ) . '>' . esc_html( $value ) . '</option>';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Filtered option HTML is escaped when built.
		echo apply_filters( 'product_order_dropdown_options', $option, $name, $value, $multiple_settings, $selected );
	}
	echo '</select>';
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.Security.NonceVerification.Recommended -- Hidden field helper escapes values; request data is read-only here.
	echo ic_get_to_hidden_field( $_GET, 'product_order' );
	do_action( 'ic_product_order_dropdown_form', $instance );
	echo '</form>';
}

/**
 * Shows a hidden form input for each array element.
 *
 * @param array|string    $get     Input values to render.
 * @param array|string    $exclude Keys to exclude.
 * @param string          $name    Parent field name.
 * @param string|int|null $value   Value to skip.
 *
 * @return string
 */
function ic_get_to_hidden_field( $get, $exclude = '', $name = '', $value = null ) {
	$fields = '';
	foreach ( $get as $key => $get_value ) {
		$arrarized = false;
		if ( ( is_array( $exclude ) && ! in_array( strval( $key ), $exclude, true ) ) || ( ! is_array( $exclude ) && $key !== $exclude ) ) {
			if ( is_array( $get_value ) && empty( $name ) ) {
				$fields .= ic_get_to_hidden_field( $get_value, $exclude, $key, $value );
			} else {
				if ( ! is_array( $get_value ) ) {
					$get_value = array( $get_value );
					$arrarized = true;
				}

				if ( ! empty( $name ) && $name !== $key ) {
					$key = $name . '[' . $key . ']';
					if ( empty( $arrarized ) ) {
						$key .= '[]';
					}
				}

				foreach ( $get_value as $val ) {
					if ( null === $value || $value !== $val ) {
						$fields .= '<input type="hidden" value="' . esc_attr( ic_sanitize( $val ) ) . '" name="' . esc_attr( ic_sanitize( $key ) ) . '" />';
					}
				}
			}
		}
	}

	return $fields;
}

add_action( 'before_product_list', 'show_product_sort_bar', 10, 2 );

/**
 * Shows the product sort and filters bar.
 *
 * @param string|null $archive_template  Archive template slug.
 * @param array|null  $multiple_settings Catalog settings.
 *
 * @return void
 */
function show_product_sort_bar( $archive_template = null, $multiple_settings = null ) {
	if ( is_product_sort_bar_active() || is_ic_ajax() ) {
		if ( is_active_sidebar( 'product_sort_bar' ) || is_ic_ajax() ) {
			ic_catalog_filters_bar();
		} else {
			show_default_product_sort_bar( $archive_template, $multiple_settings );
		}
	}
}

add_shortcode( 'catalog_filters_bar', 'ic_catalog_filters_bar_shortcode' );

/**
 * Renders the catalog filters bar shortcode.
 *
 * @return string
 */
function ic_catalog_filters_bar_shortcode() {
	ob_start();
	ic_catalog_filters_bar();

	return ob_get_clean();
}

/**
 * Outputs the catalog filters sidebar wrapper.
 *
 * @return void
 */
function ic_catalog_filters_bar() {
	global $is_filter_bar;
	$is_filter_bar = true;
	ob_start();
	dynamic_sidebar( 'product_sort_bar' );
	$sidebar_content = ob_get_clean();

	if ( ! empty( $sidebar_content ) ) {
		echo '<div id="product_filters_bar" class="product-sort-bar ' . esc_attr( design_schemes( 'box', 0 ) ) . ' ' . esc_attr( apply_filters( 'ic_filters_bar_class', '' ) ) . '">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sidebar HTML is generated by registered widgets.
		echo $sidebar_content;
		echo '<div class="clear-both"></div>';
		echo '</div>';
		$reset_url = get_filters_bar_reset_url();
		if ( ! empty( $reset_url ) ) {
			echo '<div class="reset-filters"><a href="' . esc_url( $reset_url ) . '">' . esc_html__( 'Reset Filters', 'post-type-x' ) . '</a></div>';
		}
	}
	$is_filter_bar = false;
	unset( $is_filter_bar );
}

/**
 * Shows the default product sort bar content.
 *
 * @param string|null $archive_template  Archive template slug.
 * @param array|null  $multiple_settings Catalog settings.
 *
 * @return void
 */
function show_default_product_sort_bar( $archive_template, $multiple_settings = null ) {
	if ( 1 === (int) get_option( 'old_sort_bar' ) ) {
		show_product_order_dropdown( $archive_template, $multiple_settings );
	} elseif ( current_user_can( 'edit_theme_options' ) && function_exists( 'is_customize_preview' ) ) {
		$show = get_option( 'hide_empty_bar_message', 0 );
		if ( 0 === (int) $show ) {
			global $is_filter_bar;
			$is_filter_bar = true;
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Design scheme class is escaped when built.
			echo '<div class="product-sort-bar ' . design_schemes( 'box', 0 ) . '">';
			echo '<div class="empty-filters-info">';
			echo '<h3>' . esc_html__( 'Product Filters Bar has no widgets', 'post-type-x' ) . '</h3>';
			$host          = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
			$request_uri   = isset( $_SERVER['REQUEST_URI'] ) ? wp_sanitize_redirect( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			$current_url   = ( is_ssl() ? 'https://' : 'http://' ) . $host . $request_uri;
			$customize_url = add_query_arg(
				array(
					'url'                              => rawurlencode( $current_url ),
					rawurlencode( 'autofocus[panel]' ) => 'widgets',
				),
				wp_customize_url()
			);
			$message       = sprintf(
				/* translators: 1: opening Customizer link, 2: closing Customizer link, 3: opening dismiss link, 4: closing dismiss link. */
				__( '%1$sAdd widgets to the filters bar now%2$s or %3$sdismiss this notice%4$s.', 'post-type-x' ),
				'<a href="' . esc_url( $customize_url ) . '">',
				'</a>',
				'<a class="dismiss-empty-bar" href="#">',
				'</a>'
			);
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Message HTML is escaped when built.
			echo $message;
			echo '</div>';
			echo '</div>';
			$is_filter_bar = false;
			unset( $is_filter_bar );
		}
	}
}

/**
 * Translates the selected product order to a WP_Query orderby value.
 *
 * @param string|null $order Selected product order slug.
 *
 * @return string
 */
function translate_product_order( $order = null ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only ordering parameter.
	if ( empty( $order ) && isset( $_GET['product_order'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only ordering parameter.
		$order = sanitize_text_field( wp_unslash( $_GET['product_order'] ) );
	}
	if ( 'product-name' === $order ) {
		$orderby = 'title';
	} elseif ( 'newest' === $order ) {
		$orderby = 'date';
	} else {
		$orderby = apply_filters( 'product_order_translate', $order );
	}

	return $orderby;
}

/**
 * Returns the published catalog products count.
 *
 * @param string|array $post_types Post type or post types to count.
 *
 * @return int
 */
function ic_products_count( $post_types = '' ) {
	$total = get_transient( 'ic_product_count_cache' );
	if ( is_numeric( $total ) ) {
		return $total;
	}
	if ( empty( $post_types ) ) {
		$post_types = product_post_type_array();
	}
	if ( ! is_array( $post_types ) ) {
		$post_types = array( $post_types );
	}
	$total = 0;
	foreach ( $post_types as $post_type ) {
		if ( ic_string_contains( $post_type, 'al_product' ) ) {
			$count = wp_count_posts( $post_type );
			if ( ! isset( $count->publish ) ) {
				$count = ic_fallback_products_count( $post_type );
			}
			if ( ! empty( $count->publish ) ) {
				$total += intval( $count->publish );
			}
		}
	}

	set_transient( 'ic_product_count_cache', $total );

	return intval( $total );
}

add_action( 'ic_product_status_change', 'ic_product_count_cache_clear' );

/**
 * Clears the cached catalog product count.
 *
 * @return void
 */
function ic_product_count_cache_clear() {
	delete_transient( 'ic_product_count_cache' );
}


/**
 * Returns product counts using a direct fallback query.
 *
 * @param string $post_type Post type slug.
 *
 * @return object
 */
function ic_fallback_products_count( $post_type ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fallback count query runs only when wp_count_posts() is insufficient.
	$results = (array) $wpdb->get_results(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Core posts table name is provided by $wpdb.
			"SELECT post_status, COUNT( * ) AS num_posts FROM {$wpdb->posts} WHERE post_type = %s GROUP BY post_status",
			$post_type
		),
		ARRAY_A
	);
		$counts = array_fill_keys( get_post_stati(), 0 );

	foreach ( $results as $row ) {
		$counts[ $row['post_status'] ] = $row['num_posts'];
	}

	$counts = (object) $counts;

	return $counts;
}

/**
 * Returns per row setting for current product listing theme
 *
 * @return int
 */
function get_current_per_row() {
	$archive_template = get_product_listing_template();
	$per_row          = 3;
	if ( 'default' === $archive_template ) {
		$settings = get_modern_grid_settings();
		$per_row  = $settings['per-row'];
	} elseif ( 'grid' === $archive_template ) {
		$settings = get_classic_grid_settings();
		$per_row  = $settings['entries'];
	}

	return apply_filters( 'current_per_row', $per_row, $archive_template );
}

/**
 * Returns the current screen taxonomy slug for the catalog.
 *
 * @return string
 */
function get_current_screen_tax() {
	$obj        = ic_get_queried_object();
	$taxonomies = array();
	if ( empty( $obj ) ) {
		$taxonomies = array( apply_filters( 'current_product_catalog_taxonomy', 'al_product-cat' ) );
	}
	if ( isset( $obj->ID ) ) {
		$taxonomies = get_object_taxonomies( $obj );
	} elseif ( isset( $obj->taxonomies ) ) {
		$taxonomies = $obj->taxonomies;
	} elseif ( isset( $obj->taxonomy ) ) {
		$taxonomies = array( $obj->taxonomy );
	}
	$current_tax = apply_filters( 'ic_current_def_product_tax', 'al_product-cat' );
	foreach ( $taxonomies as $tax ) {
		if ( ic_string_contains( $tax, 'al_product-cat' ) ) {
			$current_tax = $tax;
			break;
		}
	}

	return apply_filters( 'ic_current_product_tax', $current_tax );
}

/**
 * Returns the current screen post type for the catalog.
 *
 * @return string
 */
function get_current_screen_post_type() {
	$obj       = ic_get_queried_object();
	$post_type = apply_filters( 'current_product_post_type', 'al_product' );
	if ( isset( $obj->post_type ) && ic_string_contains( $obj->post_type, 'al_product' ) ) {
		$post_type = $obj->post_type;
	} elseif ( isset( $obj->name ) && ic_string_contains( $obj->name, 'al_product' ) ) {
		$post_type = $obj->name;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only post type routing parameter.
	} elseif ( isset( $_GET['post_type'] ) && ! is_array( $_GET['post_type'] ) && ic_string_contains( sanitize_text_field( wp_unslash( $_GET['post_type'] ) ), 'al_product' ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only post type routing parameter.
		$post_type = sanitize_text_field( wp_unslash( $_GET['post_type'] ) );
	}

	return apply_filters( 'ic_current_post_type', $post_type );
}

if ( ! function_exists( 'ic_strtolower' ) ) {
	/**
	 * Lowercases a string with multibyte support when available.
	 *
	 * @param string $input_string Input string.
	 *
	 * @return string
	 */
	function ic_strtolower( $input_string ) {
		if ( function_exists( 'mb_strtolower' ) ) {
			return mb_strtolower( $input_string );
		} else {
			return strtolower( $input_string );
		}
	}
}

if ( ! function_exists( 'ic_strtoupper' ) ) {

	/**
	 * Uppercases a string with multibyte support when available.
	 *
	 * @param string $input_string Input string.
	 *
	 * @return string
	 */
	function ic_strtoupper( $input_string ) {
		if ( function_exists( 'mb_strtoupper' ) ) {
			return mb_strtoupper( $input_string );
		} else {
			return strtoupper( $input_string );
		}
	}
}
if ( ! function_exists( 'ic_substr' ) ) {

	/**
	 * Returns part of a string with multibyte support when available.
	 *
	 * @param string $input_string Input string.
	 * @param int    $start Offset.
	 * @param int    $length Length.
	 *
	 * @return string
	 */
	function ic_substr( $input_string, $start, $length ) {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $input_string, $start, intval( $length ) );
		} else {
			return substr( $input_string, $start, intval( $length ) );
		}
	}
}
/**
 * Returns current product ID
 *
 * @return type
 */
function ic_get_product_id() {
	$product_id = ic_get_global( 'product_id' );
	if ( ! $product_id ) {
		do_action( 'ic_catalog_set_product_id' );
		$product_id = get_the_ID();
		if ( ! empty( $product_id ) && function_exists( 'is_ic_product' ) && is_ic_product( $product_id ) ) {
			ic_set_product_id( $product_id );
		}
	}

	return $product_id;
}

add_action( 'ic_catalog_wp_head', 'ic_handle_post_thumbnail' );

/**
 * Removes the post thumbnail from product header output.
 *
 * @return void
 */
function ic_handle_post_thumbnail() {
	add_filter( 'get_post_metadata', 'ic_override_product_post_thumbnail', 10, 3 );
	add_filter( 'has_post_thumbnail', 'ic_override_product_post_thumbnail', 10, 2 );
	// Legacy product-page hook remains disabled.
	add_action( 'single_product_begin', 'ic_handle_back_post_thumbnail' );
	add_action( 'product_listing_begin', 'ic_handle_back_post_thumbnail' );
	add_action( 'before_category_list', 'ic_handle_back_post_thumbnail' );
	add_action( 'before_product_list', 'ic_handle_back_post_thumbnail' );
	add_action( 'ic_before_get_image_html', 'ic_handle_back_post_thumbnail' );
}

/**
 * Adds the post thumbnail back after product output handling.
 *
 * @return void
 */
function ic_handle_back_post_thumbnail() {
	remove_filter( 'get_post_metadata', 'ic_override_product_post_thumbnail' );
	remove_filter( 'has_post_thumbnail', 'ic_override_product_post_thumbnail' );
}

/**
 * Clears the thumbnail ID value for catalog product output.
 *
 * @param mixed       $metadata Existing metadata value.
 * @param int|WP_Post $object_id Post object or ID.
 * @param string|null $meta_key Meta key.
 *
 * @return mixed
 */
function ic_override_product_post_thumbnail( $metadata, $object_id, $meta_key = null ) {
	if ( null === $object_id ) {
		$object_id = get_post();
	}
	if ( is_object( $object_id ) ) {
		$object_id = $object_id->ID;
	}
	if ( ( ( isset( $meta_key ) && '_thumbnail_id' === $meta_key ) || ( ! isset( $meta_key ) && 'has_post_thumbnail' === current_filter() ) ) && is_ic_product( $object_id ) ) {
		$metadata = 0;
	}

	return $metadata;
}

if ( ! function_exists( 'ic_filemtime' ) ) {
    /**
     * Gets a cache-busting timestamp query string for a file.
     *
     * @param string $path File path.
     *
     * @return string|null
     */
    function ic_filemtime( $path, $time_only = false ) {
        if ( file_exists( $path ) ) {
            if ( $time_only ) {
                return filemtime( $path );
            }
            return '?timestamp=' . filemtime( $path );
        }

        return null;
    }
}

/**
 * Returns the current or selected product object.
 *
 * @param int|WP_Post|null $product_id Product ID or object.
 *
 * @return ic_product
 */
function ic_get_product_object( $product_id = null ) {
	if ( empty( $product_id ) ) {
		$product_id = ic_get_product_id();
	} elseif ( is_object( $product_id ) && isset( $product_id->ID ) ) {
		$product_id = intval( $product_id->ID );
	}
	$ic_product = ic_get_global( 'ic_product_' . $product_id );
	if ( empty( $ic_product ) ) {
		$ic_product = new ic_product( $product_id );
		ic_save_global( 'ic_product_' . $product_id, $ic_product );
	}

	return $ic_product;
}

/**
 * Returns a permalink for the provided object ID.
 *
 * @param int|null $id Post ID.
 *
 * @return string|false
 */
function ic_get_permalink( $id = null ) {
	if ( ! empty( $id ) ) {
		$id = apply_filters( 'ic_permalink_id', $id );
	}

	return get_permalink( $id );
}

/**
 * Returns the current catalog post type.
 *
 * @param int|null $id Post ID.
 *
 * @return string|array|false
 */
function ic_get_post_type( $id = null ) {
	$raw_query_vars = filter_input( INPUT_POST, 'query_vars', FILTER_UNSAFE_RAW );
	if ( empty( $id ) && is_ic_ajax() && null !== $raw_query_vars ) {
		$raw_query_vars = wp_unslash( (string) $raw_query_vars );
		if ( '' !== $raw_query_vars ) {
			$query_vars = json_decode( $raw_query_vars, true );
			if ( ! empty( $query_vars['post_type'] ) ) {
				return sanitize_text_field( $query_vars['post_type'] );
			}
		}
	}
	$post_type = get_post_type( $id );
	if ( empty( $id ) && 'page' === $post_type ) {
		$catalog_query = ic_get_catalog_query();
		if ( ! empty( $catalog_query->posts[0]->ID ) ) {
			$post_type = ic_get_post_type( $catalog_query->posts[0]->ID );
		} else {
			global $shortcode_query;
			if ( ! empty( $shortcode_query->query['post_type'] ) ) {
				if ( ic_string_contains( $shortcode_query->query['post_type'], 'al_product' ) ) {
					$post_type = $shortcode_query->query['post_type'];
				}
			}
		}
	}

	return $post_type;
}

/**
 * Returns the archive price HTML for a product.
 *
 * @param int $product_id Product ID.
 *
 * @return string
 */
function ic_get_archive_price( $product_id ) {
	$post          = get_post( $product_id );
	$archive_price = apply_filters( 'archive_price_filter', '', $post );

	return $archive_price;
}

/**
 * Returns the default empty catalog listing text.
 *
 * @return string
 */
function ic_empty_list_text() {
	$names = get_catalog_names();
	$text  = sprintf(
		/* translators: %s: lowercase plural catalog item name. */
		__( 'No %s available.', 'post-type-x' ),
		ic_strtolower( $names['plural'] )
	);

	return apply_filters( 'ic_empty_list_text', $text );
}

/**
 * Defines not supported query vars
 *
 * @return array
 */
function ic_forbidden_query_vars() {
	return array(
		'title',
		'attachment',
		'attachment_id',
		'author',
		'author_name',
		'cat',
		'calendar',
		'category_name',
		'comments_popup',
		'cpage',
		'day',
		'error',
		'exact',
		'feed',
		'hour',
		'm',
		'minute',
		'monthnum',
		'more',
		'name',
		'order',
		'orderby',
		'p',
		'page_id',
		'page',
		'paged',
		'pagename',
		'pb',
		'post_type',
		'posts',
		'preview',
		'robots',
		's',
		'search',
		'second',
		'sentence',
		'static',
		'subpost',
		'subpost_id',
		'taxonomy',
		'tag',
		'tb',
		'tag_id',
		'term',
		'tb',
		'w',
		'withcomments',
		'withoutcomments',
		'year',
	);
}

if ( ! function_exists( 'ic_filter_objects' ) ) {

	/**
	 * Filters out objects from a mixed value list.
	 *
	 * @param mixed $value Value to test.
	 *
	 * @return bool
	 */
	function ic_filter_objects( $value ) {
		if ( is_object( $value ) ) {
			return false;
		}

		return true;
	}

}
if ( ! function_exists( 'ic_is_function_disabled' ) ) {

	/**
	 * Checks whether a PHP function is disabled.
	 *
	 * @param string $function_name Function name.
	 *
	 * @return bool
	 */
	function ic_is_function_disabled( $function_name ) {
		$disabled = explode( ',', ini_get( 'disable_functions' ) );

		return in_array( $function_name, $disabled, true );
	}

}

if ( ! function_exists( 'ic_set_time_limit' ) ) {

	/**
	 * Tries to raise the PHP time limit when available.
	 *
	 * @param int $limit Time limit in seconds.
	 *
	 * @return void
	 */
	function ic_set_time_limit( $limit = 0 ) {
		if ( filter_var( ini_get( 'safe_mode' ), FILTER_VALIDATE_BOOLEAN ) ) {
			return;
		}
		if ( function_exists( 'set_time_limit' ) && ! ic_is_function_disabled( 'set_time_limit' ) ) {
			// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- This guarded helper is explicitly invoked for bounded maintenance work that can exceed the host default.
			set_time_limit( $limit );
		}
	}

}

/**
 * Returns visible product statuses for the current request.
 *
 * @param bool $check_current_user Whether private products should be included for privileged users.
 *
 * @return array
 */
function ic_visible_product_status( $check_current_user = true ) {
	$visible_status = array( 'publish' );
	// phpcs:ignore WordPress.WP.Capabilities.Unknown -- Plugin registers a custom capability.
	if ( $check_current_user && current_user_can( 'read_private_products' ) ) {
		$visible_status[] = 'private';
	}

	return apply_filters( 'ic_visible_product_status', $visible_status, $check_current_user );
}

/**
 * Returns the current catalog mode.
 *
 * @return string
 */
function ic_get_catalog_mode() {
	$settings                 = get_multiple_settings();
	$settings['catalog_mode'] = ! empty( $settings['catalog_mode'] ) ? $settings['catalog_mode'] : 'simple';

	return $settings['catalog_mode'];
}

/**
 * Checks whether product data should be hidden for the given status.
 *
 * @param string $product_status Product status.
 *
 * @return bool
 */
function ic_data_should_be_hidden( $product_status ) {
	if ( defined( 'IC_COMPRESS_PRIVATE_PRODUCTS_DATA' ) && IC_COMPRESS_PRIVATE_PRODUCTS_DATA && ! in_array( $product_status, ic_visible_product_status( false ), true ) ) {
		return true;
	}

	return false;
}

/**
 * Sanitizes scalar or array input values for catalog storage.
 *
 * @param mixed $data   Data to sanitize.
 * @param bool  $strict Whether strict text sanitization should be used.
 *
 * @return mixed
 */
function ic_sanitize( $data, $strict = true ) {
	if ( is_array( $data ) ) {
		$return = array();
		foreach ( $data as $key => $value ) {
			$return[ $key ] = ic_sanitize( $value, $strict );
		}

		return $return;
	}
	if ( $strict ) {
		return sanitize_text_field( $data );
	}

	return addslashes( wp_kses( stripslashes( $data ), 'implecode' ) );
}

/**
 * Restricts client-supplied widget wrapper arguments to post-safe HTML.
 *
 * @param array $args Unslashed widget wrapper arguments.
 *
 * @return array
 */
function ic_sanitize_widget_ajax_args( $args ) {
	$sanitized = array();
	if ( ! is_array( $args ) ) {
		return $sanitized;
	}
	foreach ( $args as $key => $value ) {
		$key = sanitize_key( $key );
		if ( '' === $key ) {
			continue;
		}
		if ( is_array( $value ) ) {
			$sanitized[ $key ] = ic_sanitize_widget_ajax_args( $value );
		} elseif ( is_scalar( $value ) ) {
			$sanitized[ $key ] = wp_kses_post( (string) $value );
		}
	}

	return $sanitized;
}

/**
 * Normalizes client-supplied widget refresh settings coming from a catalog Ajax request.
 *
 * The `args` entry is echoed as the widget wrapper markup and the `instance` entry drives widget
 * rendering, so both are treated as untrusted here: wrapper markup is limited to post-safe HTML and
 * instance values are reduced to plain text. The trusted `dynamic_sidebar()` path never goes
 * through this function.
 *
 * @param mixed $settings Raw widget settings from the request. Must already be unslashed.
 *
 * @return array {
 *     @type array $instance Sanitized widget instance settings.
 *     @type array $args     Sanitized widget wrapper arguments.
 * }
 */
function ic_sanitize_widget_ajax_settings( $settings ) {
	$sanitized = array(
		'instance' => array(),
		'args'     => array(),
	);
	if ( is_string( $settings ) ) {
		$decoded = json_decode( $settings, true );
		if ( is_array( $decoded ) ) {
			$settings = $decoded;
		}
	}
	if ( ! is_array( $settings ) ) {
		return $sanitized;
	}
	if ( isset( $settings['instance'] ) && is_array( $settings['instance'] ) ) {
		$sanitized['instance'] = ic_sanitize( $settings['instance'] );
	}
	if ( isset( $settings['args'] ) ) {
		$sanitized['args'] = ic_sanitize_widget_ajax_args( $settings['args'] );
	}

	return $sanitized;
}

add_filter( 'wp_kses_allowed_html', 'ic_wp_kses_allowed_html', 10, 2 );

/**
 * Extends the custom impleCode KSES context.
 *
 * @param array  $allowedposttags Allowed HTML tags.
 * @param string $context         KSES context.
 *
 * @return array
 */
function ic_wp_kses_allowed_html( $allowedposttags, $context ) {
	if ( 'implecode' === $context ) {
		if ( ! empty( $allowedposttags['a'] ) ) {
			$allowedposttags['a']['target'] = true;
			$allowedposttags['a']['rel']    = true;
		}
		$allowedposttags = apply_filters( 'ic_wp_kses_allowed_html', $allowedposttags );
	}

	return $allowedposttags;
}

add_action( 'wp_print_footer_scripts', 'ic_loading_icon' );

/**
 * Prints the frontend loading icon CSS.
 *
 * @return void
 */
function ic_loading_icon() {
	?>
	<style>
		body.ic-disabled-body:before {
			background-image: url("<?php echo esc_url( includes_url( 'js/thickbox/loadingAnimation.gif', 'relative' ) ); ?>");
		}
		.ic-disabled-container:before {
			background-image: url("<?php echo esc_url( includes_url( 'js/thickbox/loadingAnimation.gif', 'relative' ) ); ?>");
		}
	</style>
	<?php
}

if ( ! function_exists( 'ic_setcookie' ) ) {
	/**
	 * Sets a cookie for the catalog.
	 *
	 * @param string $name     Cookie name.
	 * @param string $value    Cookie value.
	 * @param int    $expire   Expiration timestamp.
	 * @param bool   $secure   Whether the cookie is secure-only.
	 * @param bool   $httponly Whether the cookie is HTTP only.
	 *
	 * @return void
	 */
	function ic_setcookie( $name, $value, $expire = 0, $secure = false, $httponly = false ) {
		if ( ! defined( 'IC_USE_COOKIES' ) || ( defined( 'IC_USE_COOKIES' ) && ! IC_USE_COOKIES ) ) {
			return;
		}
		if ( headers_sent() ) {
			return;
		}
		$options = apply_filters(
			'ic_set_cookie_options',
			array(
				'expires'  => $expire,
				'secure'   => $secure,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'httponly' => $httponly,
				'samesite' => 'lax',
			),
			$name,
			$value
		);

		if ( version_compare( PHP_VERSION, '7.3.0', '>=' ) ) {
			setcookie( $name, $value, $options );
		} else {
			if ( ! ic_string_contains( $options['path'], 'samesite' ) ) {
				$options['path'] .= '; samesite=' . $options['samesite'];
			}
			setcookie( $name, $value, $options['expires'], $options['path'], $options['domain'], $options['secure'], $options['httponly'] );
		}
	}
}

if ( ! function_exists( 'ic_site_is_https' ) ) {
	/**
	 * Check if the home URL is https.
	 *
	 * @return bool
	 */
	function ic_site_is_https() {
		return false !== strstr( get_option( 'home' ), 'https:' );
	}
}

/**
 * Returns the currently queried object, with parse-tax-query fallback support.
 *
 * @return object|WP_Term|WP_Post|null
 */
function ic_get_queried_object() {
	$object = get_queried_object();
	if ( empty( $object ) && current_filter() === 'parse_tax_query' ) {
		global $wp_query;
		if ( ! empty( $wp_query->query_vars ) && is_array( $wp_query->query_vars ) ) {
			$taxonomies = product_taxonomy_array();
			foreach ( $wp_query->query_vars as $taxonomy => $slug ) {
				if ( in_array( $taxonomy, $taxonomies, true ) ) {
					$object = get_term_by( 'slug', $slug, $taxonomy );
					if ( ! empty( $object ) ) {
						break;
					}
				}
			}
		}
	}

	return $object;
}

/**
 * Conditionally proxies `_doing_it_wrong()` for catalog debugging.
 *
 * @param string $function_name Function name.
 * @param string $message       Debug message.
 * @param string $version       Version string.
 *
 * @return void
 */
function ic_doing_it_wrong( $function_name, $message, $version ) {
	if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
		return;
	}
	if ( ! defined( 'WP_DEBUG_DISPLAY' ) || WP_DEBUG_DISPLAY ) {
		return;
	}
	if ( ! defined( 'IC_DEBUG' ) || ! IC_DEBUG ) {
		return;
	}
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_wp_debug_backtrace_summary -- Explicitly gated behind plugin debug constants.
	$message .= ' Backtrace: ' . wp_debug_backtrace_summary();
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Values are passed to the core debug helper, not output here.
	_doing_it_wrong( $function_name, $message, $version );
}

if ( ! function_exists( 'implecode_array_variables_init' ) ) {

	/**
	 * Initializes missing array variables to empty strings.
	 *
	 * @param array $fields Field keys.
	 * @param array $data   Existing data.
	 *
	 * @return array
	 */
	function implecode_array_variables_init( $fields, $data = array() ) {
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		foreach ( $fields as $field ) {
			$data[ $field ] = isset( $data[ $field ] ) ? $data[ $field ] : '';
		}

		return $data;
	}

}

if ( ! function_exists( 'get_supported_country_name' ) ) {

	/**
	 * Returns a country name by its code.
	 *
	 * @param string $country_code Country code.
	 *
	 * @return string
	 */
	function get_supported_country_name( $country_code ) {
		$return    = 'none';
		$countries = implecode_supported_countries();
		foreach ( $countries as $key => $country ) {
			if ( $country_code === $key ) {
				$return = $country;
			}
		}
		if ( 'none' === $return && false !== array_search( $country_code, $countries, true ) ) {
			$return = $country_code;
		}

		return $return;
	}

}

/**
 * Returns the archive products limit.
 *
 * @return int
 */
function ic_get_products_limit() {
	$multiple_settings = get_multiple_settings();
	if ( ! empty( $multiple_settings['archive_products_limit'] ) ) {
		$limit = $multiple_settings['archive_products_limit'];
	} else {
		$limit = 12;
	}

	return $limit;
}

if ( ! function_exists( 'ic_force_purge_cache' ) ) {
	/**
	 * Clears supported third-party caches.
	 *
	 * @return void
	 */
	function ic_force_clear_cache() {
		// LiteSpeed Cache.
		if ( defined( 'LSCWP_V' ) ) {
			do_action( 'litespeed_purge_all', '3rd impleCode' );
		}
		// W3 Total Cache.
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}
		// WP Super Cache.
		if ( function_exists( 'wp_cache_clean_cache' ) ) {
			global $file_prefix;
			wp_cache_clean_cache( $file_prefix );
		}
		// FastCGI Cache.
		if ( function_exists( 'nginx_helper_purge_cache' ) ) {
			do_action( 'nginx_helper_purge_all' );
		}
		// WP Rocket.
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}
		// Redis Cache.
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
		// Autoptimize.
		if ( class_exists( 'autoptimizeCache' ) ) {
			autoptimizeCache::clearall();
		}
	}
}
