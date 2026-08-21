<?php
/**
 * Template functions for the product catalog.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WP Product template functions
 *
 * Here all plugin template functions are defined.
 *
 * @version        1.1.3
 * @package        ecommerce-product-catalog/
 * @author        impleCode
 */
add_shortcode( 'content_product_adder', 'content_product_adder' );

/**
 * The function wrapper to show product catalog content for current URL.
 *
 * @param string|null $is_catalog Catalog context flag.
 */
function content_product_adder( $is_catalog = null ) {
	if ( 'is_catalog' !== $is_catalog && ! is_ic_catalog_page() ) {
		return;
	}
	echo '<div class="ic-catalog-container">';
	if ( is_archive() || is_search() || is_home_archive() || is_ic_product_listing() ) {
		do_action( 'before_product_archive' );
		content_product_adder_archive();
		do_action( 'after_product_archive' );
	} else {
		do_action( 'before_product_page' );
		content_product_adder_single();
		do_action( 'after_product_page' );
	}
	echo '</div>';
}

/**
 * Redirects to the current request URL.
 */
function ic_redirect_to_same() {
	$url         = 'http';
	$http_host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	if ( is_ssl() ) {
		$url .= 's';
	}
	$url .= '://' . $http_host . $request_uri;
	wp_safe_redirect( esc_url_raw( $url ) );
	exit;
}

/**
 * Renders the archive template wrapper.
 */
function content_product_adder_archive() {
	$path = get_custom_product_listing_path();
	if ( file_exists( $path ) ) {
		ob_start();
		include apply_filters( 'content_product_adder_archive_path', $path );
		$product_listing = ob_get_clean();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: do_shortcode() output. Escaping it would print the tags instead of rendering them.
		echo do_shortcode( $product_listing );
	} else {
		include apply_filters( 'content_product_adder_archive_path', AL_BASE_TEMPLATES_PATH . '/templates/full/product-listing.php' );
	}
}

/**
 * Renders the single product template wrapper.
 */
function content_product_adder_single() {
	add_action( 'product_page_inside', 'content_product_adder_single_content' );
	$path = get_custom_product_page_path();
	if ( file_exists( $path ) ) {
		ob_start();
		include apply_filters( 'content_product_adder_path', $path );
		$product_page = ob_get_clean();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: do_shortcode() output. Escaping it would print the tags instead of rendering them.
		echo do_shortcode( $product_page );
	} else {
		include apply_filters( 'content_product_adder_path', AL_BASE_TEMPLATES_PATH . '/templates/full/product-page.php' );
	}
}

/**
 * Renders the single product inner template.
 */
function content_product_adder_single_content() {
	$path = get_custom_product_page_inside_path();
	if ( file_exists( $path ) ) {
		ob_start();
		include $path;
		$product_page = ob_get_clean();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: do_shortcode() output. Escaping it would print the tags instead of rendering them.
		echo do_shortcode( $product_page );
	} else {
		include AL_BASE_TEMPLATES_PATH . '/templates/full/product-page-inside.php';
	}
}

/**
 * Gets the content shown before the archive listing.
 *
 * @return string
 */
function content_product_adder_archive_before() {
	$page_id = apply_filters( 'before_archive_post_id', get_product_listing_id() );
	$page    = empty( $page_id ) ? '' : get_post( $page_id );
	if ( !empty($page) && ! is_ic_shortcode_integration() ) {
		if ( 'simple' !== get_integration_type() ) {
			if ( ic_has_page_catalog_shortcode( $page ) ) {
				$page->post_content = str_replace(
					array(
						'<!-- wp:ic-epc/show-catalog /-->',
						'[show_product_catalog]',
					),
					'',
					$page->post_content
				);
			}
			$content = apply_filters( 'the_content', $page->post_content );
		} else {
			$content = $page->post_content;
		}
	} else {
		$content = '';
	}

	return '<div class="entry-summary">' . $content . '</div>';
}

/**
 * Outputs the archive title before the archive listing.
 */
function content_product_adder_archive_before_title() {
	$def_page_id   = get_product_listing_id();
	$archive_names = get_archive_names();
	$page_id       = apply_filters( 'before_archive_post_id', $def_page_id );
	$page          = empty( $page_id ) ? '' : get_post( $page_id );
	if ( '' === $page ) {
		echo '<h1 class="entry-title">' . esc_html( $archive_names['all_products'] ) . '</h1>';
	} else {
		echo '<h1 class="entry-title">' . esc_html( $page->post_title ) . '</h1>';
	}
}

/**
 * Shows products outside the default loop.
 *
 * @param array  $atts    Shortcode attributes.
 * @param string $content Shortcode content.
 *
 * @return string
 */
function show_products_outside_loop( $atts, $content = '' ) {
	global $shortcode_query, $product_sort, $archive_template, $shortcode_args;
	ic_reset_listing_globals();
	ic_enqueue_main_catalog_js_css();
	ic_save_global( 'in_shortcode', 1, true );
	$available_args   = apply_filters(
		'show_products_shortcode_args',
		array(
			'post_type'        => 'al_product',
			'category'         => '',
			'product'          => '',
			// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- The related-products template contract supports explicit caller exclusions.
			'exclude'          => '',
			'products_limit'   => 100,
			'archive_template' => get_product_listing_template(),
			'design_scheme'    => '',
			'sort'             => 0,
			'orderby'          => '',
			'order'            => '',
			'pagination'       => 0,
			'page'             => '',
			'per_row'          => '',
			'empty'            => '',
		)
	);
	$args             = shortcode_atts( $available_args, $atts );
	$shortcode_args   = $args;
	$category         = esc_html( $args['category'] );
	$product          = esc_html( $args['product'] );
	$exclude          = esc_html( $args['exclude'] );
	$products_limit   = intval( $args['products_limit'] );
	$archive_template = esc_attr( $args['archive_template'] );
	$design_scheme    = esc_attr( $args['design_scheme'] );
	$product_sort     = intval( $args['sort'] );
	$per_row          = intval( $args['per_row'] );
	$args['page']     = intval( $args['page'] );
	if ( ! empty( $per_row ) ) {
		ic_save_global( 'shortcode_per_row', $per_row, true );
	}
	$post_type = empty( $args['post_type'] ) ? 'al_product' : $args['post_type'];
	if ( ! empty( $product ) ) {
		$product_array = explode( ',', $product );
		$query_param   = array(
			'post_type'      => product_post_type_array(),
			'post__in'       => $product_array,
			'posts_per_page' => $products_limit,
		);
	} elseif ( ! empty( $category ) ) {
		$category_array = explode( ',', $category );
		$field          = 'name';
		if ( is_numeric( $category_array[0] ) ) {
			$field = 'term_id';
		}
		$query_param = array(
			'post_type'      => $post_type,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Related products are selected by shared catalog taxonomy terms.
			'tax_query'      => array(
				array(
					'taxonomy' => 'al_product-cat',
					'field'    => $field,
					'terms'    => $category_array,
				),
			),
			'posts_per_page' => $products_limit,
		);
	} else {
		$query_param = array(
			'post_type'      => $post_type,
			'posts_per_page' => $products_limit,
		);
		if ( ! empty( $exclude ) ) {
			// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- The caller-provided related-product exclusion is required to avoid rendering the current/explicit products.
			$query_param['post__not_in'] = explode( ',', $exclude );
		}
	}
	if ( 'none' === $args['orderby'] ) {
		$args['orderby'] = 'post__in';
	}
	if ( ! empty( $args['orderby'] ) ) {
		$query_param['orderby'] = esc_attr( $args['orderby'] );
	}
	if ( ! empty( $args['order'] ) ) {
		$query_param['order'] = esc_attr( $args['order'] );
	}
	if ( ! empty( $args['pagination'] ) ) {

		if ( get_query_var( 'paged' ) ) {
			$paged = absint( get_query_var( 'paged' ) );
		} elseif ( get_query_var( 'page' ) ) {
			$paged = absint( get_query_var( 'page' ) );
		} elseif ( ! empty( $args['page'] ) ) {
			$paged = $args['page'];
		} else {
			$paged = 1;
		}
		$query_param['paged'] = $paged;
	}
	$query_param = apply_filters( 'shortcode_query', $query_param, $args, $post_type, $products_limit );
	remove_all_filters( 'pre_get_posts' );
	$shortcode_query                            = new WP_Query( $query_param );
	$shortcode_query->query['archive_template'] = $archive_template;
	$i = 0;
	ob_start();
	do_action( 'before_product_list', $archive_template );
	do_action( 'before_shortcode_product_list', $shortcode_query, $args, $archive_template );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: do_shortcode() output. Escaping it would print the tags instead of rendering them.
	echo do_shortcode( $content );
	$before = ob_get_contents();
	ob_end_clean();
	$products_listed = false;
	$inside          = apply_filters( 'pre_show_products_outside_loop_inside', '', $shortcode_query );
	if ( empty( $inside ) ) {
		while ( $shortcode_query->have_posts() ) :
			$shortcode_query->the_post();
			global $post;
			ic_set_product_id( $post->ID );
			++$i;
			$inside .= get_catalog_template( $archive_template, $post, $i, $design_scheme );
			ic_reset_product_id();
		endwhile;
	}
	if ( ! empty( $inside ) ) {
		$products_listed = true;
	}
	$pagination = '';
	if ( ! empty( $args['pagination'] ) ) {
		ob_start();
		product_archive_pagination( $shortcode_query );
		$pagination = ob_get_clean();
	}
	if ( ! empty( $args['empty'] ) && ! $products_listed ) {
		$inside .= wp_kses_post( $args['empty'] );
	}
	$inside = apply_filters( 'product_list_ready', $inside, $archive_template, $args );
	wp_reset_postdata();
	if ( ! empty( $inside ) ) {
		$out_class = apply_filters( 'ic_show_products_container_class', 'product-list responsive ' . $archive_template . ' ' . product_list_class( $archive_template ), $args );
		$out       = $before . '<div class="' . $out_class . '" ' . product_list_attr( $shortcode_query ) . '>' . $inside . '<div style="clear:both"></div></div>' . $pagination;
	} else {
		$out = '';
	}
	unset( $GLOBALS['shortcode_args'] );
	unset( $GLOBALS['shortcode_query'] );
	unset( $GLOBALS['archive_template'] );
	unset( $GLOBALS['product_sort'] );
	ic_delete_global( 'in_shortcode' );
	reset_row_class();

	return $out;
}

add_shortcode( 'show_products', 'show_products_outside_loop' );

/**
 * Enqueues scripts for single product pages.
 */
function single_scripts() {
	if ( is_ic_product_page() && is_lightbox_enabled() ) {
		wp_enqueue_style( 'colorbox' );
	}
}

add_action( 'wp_enqueue_scripts', 'single_scripts' );
add_action( 'ic_pre_get_products', 'set_products_limit', 99 );

/**
 * Sets product limit on product listing pages.
 *
 * @param WP_Query $query Main query object.
 */
function set_products_limit( $query ) {
	$archive_multiple_settings = get_multiple_settings();
	$current_per_page          = $query->get( 'posts_per_page' );
	if ( -1 !== $current_per_page && ! isset( $query->query['post__in'] ) && empty( $query->query['suppress_filters'] ) ) {
		$query->set( 'posts_per_page', $archive_multiple_settings['archive_products_limit'] );
	}
	if ( ic_ic_catalog_archive( $query ) ) {
		$query->set( 'post_status', ic_visible_product_status() );
	}
}

add_action( 'parse_tax_query', 'set_category_products_limit', 99 );

/**
 * Sets category product limits on taxonomy pages.
 *
 * @param WP_Query $query Main query object.
 */
function set_category_products_limit( $query ) {
	if ( ! is_admin() && $query->is_main_query() && is_ic_taxonomy_page( $query ) ) {
		$archive_multiple_settings = get_multiple_settings();
		$current_per_page          = $query->get( 'posts_per_page' );
		if ( -1 !== $current_per_page && ! isset( $query->query['post__in'] ) && empty( $query->query['suppress_filters'] ) ) {
			$query->set( 'posts_per_page', $archive_multiple_settings['archive_products_limit'] );
		}
	}
}


add_action( 'product_listing_end', 'product_archive_pagination' );

/**
 * Adds pagination to the product listings.
 *
 * @param WP_Query|null $wp_query Query object.
 *
 * @return void
 */
function product_archive_pagination( $wp_query = null ) {
	if ( ! isset( $wp_query ) || ! is_object( $wp_query ) ) {
		global $wp_query;
	}
	if ( $wp_query->max_num_pages <= 1 ) {
		return;
	}
	$multiple_settings = get_multiple_settings();
	if ( ! is_ic_ajax() && ( is_ic_product_listing( $wp_query ) || ! is_ic_catalog_page( $wp_query ) ) && 'forced_cats_only' === $multiple_settings['product_listing_cats'] ) {
		return;
	}
	if ( $wp_query->get( 'paged' ) ) {
		$current_page = absint( $wp_query->get( 'paged' ) );
	} elseif ( $wp_query->get( 'page' ) ) {
		$current_page = absint( $wp_query->get( 'page' ) );
	} else {
		$current_page = 1;
	}
	$max   = intval( $wp_query->max_num_pages );
	$links = array();
	if ( $current_page >= 1 ) {
		$links[] = $current_page;
	}
	if ( $current_page >= 3 ) {
		$links[] = $current_page - 1;
		$links[] = $current_page - 2;
	}
	if ( ( $current_page + 2 ) <= $max ) {
		$links[] = $current_page + 2;
		$links[] = $current_page + 1;
	}
	$names = get_archive_names();
	echo '<div id="product_archive_nav" class="product-archive-nav ' . esc_attr( design_schemes( 'box', 0 ) ) . '"><ul>' . "\n";
	if ( get_previous_posts_link( $names['previous_products'] ) ) {
		$previous      = $current_page - 1;
		$previous_link = get_previous_posts_link( '<span>' . esc_html( $names['previous_products'] ) . '</span>' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: the return value of the 'ic_product_archive_nav_buttons' filter, which extensions and themes legitimately use to inject their own HTML.
		printf( '<li class="previous-page" data-page="%1$s">%2$s</li> ' . "\n", esc_attr( (string) $previous ), $previous_link );
	}
	$pre_page_buttons = apply_filters( 'ic_product_archive_nav_buttons', '', $links, $max, $current_page );
	if ( empty( $pre_page_buttons ) ) {
		if ( ! in_array( 1, $links, true ) ) {
			$class_name = 1 === $current_page ? 'active first-num' : 'first-num';
			printf( '<li class="%1$s" data-page="1"><a href="%2$s">1</a></li> ' . "\n", esc_attr( $class_name ), esc_url( get_pagenum_link( 1 ) ) );
			if ( ! in_array( 2, $links, true ) ) {
				echo '<li class="nav-dots">...</li>';
			}
		}
		sort( $links );
		foreach ( (array) $links as $link ) {
			$class_name = $current_page === $link ? 'active' : '';
			printf( '<li class="%1$s" data-page="%2$s"><a href="%3$s">%4$s</a></li> ' . "\n", esc_attr( $class_name ), esc_attr( (string) $link ), esc_url( get_pagenum_link( $link ) ), esc_html( (string) $link ) );
		}
		if ( ! in_array( $max, $links, true ) ) {
			if ( ! in_array( $max - 1, $links, true ) ) {
				echo '<li class="nav-dots">...</li>' . "\n";
			}
			$class_name = $current_page === $max ? 'active last-num' : 'last-num';
			printf( '<li class="%1$s" data-page="%2$s"><a href="%3$s">%4$s</a></li> ' . "\n", esc_attr( $class_name ), esc_attr( (string) $max ), esc_url( get_pagenum_link( $max ) ), esc_html( (string) $max ) );
		}
	} else {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: HTML assembled earlier in this scope, so escaping it here would print the tags.
		echo $pre_page_buttons;
	}
	if ( get_next_posts_link( $names['next_products'], $max ) ) {
		$next      = $current_page + 1;
		$next_link = get_next_posts_link( '<span>' . esc_html( $names['next_products'] ) . '</span>', $max );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: a translated string that deliberately contains HTML, so it cannot be escaped wholesale; the values interpolated into it are escaped individually on this line.
		printf( '<li class="next-page" data-page="%1$s">%2$s</li> ' . "\n", esc_attr( (string) $next ), $next_link );
	}
	echo '</ul></div>' . "\n";
	wp_reset_postdata();
}

/**
 * Gets the current catalog template markup.
 *
 * @param string      $archive_template Archive template slug.
 * @param WP_Post     $post             Current post.
 * @param int|null    $i                Loop index.
 * @param string|null $design_scheme    Design scheme.
 *
 * @return string
 */
function get_catalog_template( $archive_template, $post, $i = null, $design_scheme = null ) {
	$themes_array                      = apply_filters(
		'ecommerce_catalog_templates',
		array(
			'default' => get_default_archive_theme( $post, $archive_template ),
			'list'    => get_list_archive_theme( $post, $archive_template ),
			'grid'    => get_grid_archive_theme( $post, $archive_template ),
		),
		$post,
		$i,
		$design_scheme,
		$archive_template
	);
	$themes_array[ $archive_template ] = isset( $themes_array[ $archive_template ] ) ? $themes_array[ $archive_template ] : $themes_array['default'];
	$themes_array[ $archive_template ] = empty( $themes_array[ $archive_template ] ) ? get_default_archive_theme( $post, 'default' ) : $themes_array[ $archive_template ];
	$markup                            = $themes_array[ $archive_template ];
	$product_id                        = is_object( $post ) && isset( $post->ID ) ? absint( $post->ID ) : 0;
	if ( ! empty( $markup ) && $product_id > 0 && is_ic_product( $product_id ) ) {
		do_action(
			'ic_epc_listing_impression',
			array(
				'product_id' => $product_id,
				'page_type'  => 'listing',
			)
		);
	}

	return $markup;
}

/**
 * Gets the current product category template markup.
 *
 * @param string      $archive_template Archive template slug.
 * @param WP_Term     $product_cat      Product category term.
 * @param int|null    $i                Loop index.
 * @param string|null $design_scheme    Design scheme.
 *
 * @return string
 */
function get_product_category_template( $archive_template, $product_cat, $i = null, $design_scheme = null ) {
	$themes_array                      = apply_filters(
		'ecommerce_category_templates',
		array(
			'default' => get_default_category_theme( $product_cat, $archive_template ),
			'list'    => get_list_category_theme( $product_cat, $archive_template ),
			'grid'    => get_grid_category_theme( $product_cat, $archive_template ),
		),
		$product_cat,
		$i,
		$design_scheme,
		$archive_template
	);
	$themes_array[ $archive_template ] = isset( $themes_array[ $archive_template ] ) ? $themes_array[ $archive_template ] : $themes_array['default'];
	if ( empty( $themes_array[ $archive_template ] ) ) {
		if ( empty( $themes_array['default'] ) ) {
			$themes_array[ $archive_template ] = get_default_category_theme( $product_cat, 'default' );
		} else {
			$themes_array[ $archive_template ] = $themes_array['default'];
		}
	}

	return $themes_array[ $archive_template ];
}

/**
 * Gets available catalog templates.
 *
 * @return array
 */
function ic_get_available_templates() {
	$templates = array(
		'default' => __( 'Modern Grid', 'post-type-x' ),
		'list'    => __( 'Classic List', 'post-type-x' ),
		'grid'    => __( 'Classic Grid', 'post-type-x' ),
	);

	return apply_filters( 'ic_epc_available_templates', $templates );
}

/**
 * Checks whether more products are available in the current query.
 *
 * @return bool
 */
function more_products() {
	global $wp_query, $shortcode_query;
	$post_type = apply_filters( 'current_product_post_type', 'al_product' );
	$taxonomy  = apply_filters( 'current_product_catalog_taxonomy', 'al_product-cat' );
	if (
		empty( $wp_query->is_single )
		&& ( empty( $wp_query->is_page ) || ic_get_global( 'inside_show_catalog_shortcode' ) )
		&& (
			( isset( $wp_query->query['post_type'] ) && $post_type === $wp_query->query['post_type'] )
			|| ( isset( $wp_query->query_vars['post_type'] ) && is_array( $wp_query->query_vars['post_type'] ) && false !== array_search( $post_type, $wp_query->query_vars['post_type'], true ) )
			|| ( is_string( $taxonomy ) && isset( $wp_query->query[ $taxonomy ] ) )
		)
	) {
		$y_query = $wp_query;
	} else {
		$y_query = $shortcode_query;
	}
	if ( empty( $y_query ) ) {
		$y_query = $wp_query;
	}
	if ( ! empty( $y_query->posts ) && is_array( $y_query->posts ) && apply_filters( 'ic_query_check_if_product', true, $y_query ) ) {
		if ( ! empty( $y_query->posts[0]->ID ) && ! is_ic_product( $y_query->posts[0]->ID ) ) {

			return false;
		}
	}
	if ( isset( $y_query->current_post ) ) {
		return $y_query->current_post + 1 < $y_query->post_count;
	} else {

		return false;
	}
}

/**
 * Checks whether more product categories are available.
 *
 * @return bool
 */
function more_product_cats() {
	global $cat_shortcode_query;
	if ( isset( $cat_shortcode_query['current'] ) ) {

		return $cat_shortcode_query['current'] + 1 < $cat_shortcode_query['count'];
	} else {
		return false;
	}
}

/**
 * Gets the current row class.
 *
 * @param array  $grid_settings Grid settings.
 * @param string $what          Context.
 *
 * @return string
 */
function get_row_class( $grid_settings, $what = 'products' ) {
	$row_class = 'full';
	if ( is_ic_shortcode_query() ) {
		$shortcode_per_row = ic_get_global( 'shortcode_per_row' );
		if ( $shortcode_per_row ) {
			$grid_settings['entries']            = $shortcode_per_row;
			$grid_settings['per-row-categories'] = $shortcode_per_row;
		}
	}
	if ( 'products' === $what ) {
		$per_row = $grid_settings['entries'];
	} else {
		$per_row = $grid_settings['per-row-categories'];
	}
	if ( '' !== $per_row ) {
		global $ic_row;
		if ( $ic_row > $per_row || ! isset( $ic_row ) ) {
			$ic_row = 1;
		}

		$count = $ic_row - $per_row;
		if ( 1 === $ic_row ) {
			$row_class = 'first';
		} elseif ( 0 === $count ) {
			$row_class = 'last';
		} else {
			$row_class = 'middle';
		}
		if ( more_products() || more_product_cats() ) {
			++$ic_row;
		} else {
			$ic_row = 1;
		}
	}

	return $row_class;
}

add_action( 'product_listing_end', 'reset_row_class', 99 );

/**
 * Resets the current row class counter.
 */
function reset_row_class() {
	global $ic_row;
	$ic_row = 1;
	ic_delete_global( 'shortcode_per_row' );
}

add_filter( 'post_class', 'product_post_class', - 1 );

/**
 * Deletes the default WordPress has-post-thumbnail class.
 *
 * @param array $classes Post classes.
 *
 * @return array
 */
function product_post_class( $classes ) {
	if ( is_ic_catalog_page() ) {
		$key = array_search( 'has-post-thumbnail', $classes, true );
		if ( false !== $key ) {
			unset( $classes[ $key ] );
		}
		$classes[] = 'al_product';
		$classes[] = 'responsive';
		if ( is_ic_product_page() ) {
			$single_options = get_product_page_settings();
			$classes[]      = $single_options['template'];
			$classes[]      = 'ic-template-' . $single_options['template'];
			$product_id     = ic_get_product_id();
			if ( ! empty( $product_id ) ) {
				$classes[] = 'product-' . $product_id;
			}
			$classes = apply_filters( 'product-page-class', $classes, $product_id );
		}
	}

	return $classes;
}

add_action( 'before_product_list', 'product_listing_additional_styles' );
add_action( 'before_ajax_product_list', 'product_listing_additional_styles' );
add_action( 'before_category_list', 'product_listing_additional_styles' );
add_action( 'product_listing_entry_inside', 'product_listing_additional_styles' );
/**
 * Adds the product listing inline styles container.
 *
 * @param string $archive_template Archive template slug.
 */
function product_listing_additional_styles( $archive_template ) {
	if ( current_filter() === 'product_listing_entry_inside' ) {
		remove_action( 'before_product_list', 'product_listing_additional_styles' );
	} elseif ( current_filter() === 'before_product_list' ) {
		remove_action( 'product_listing_entry_inside', 'product_listing_additional_styles' );
	}

	$styles = wp_strip_all_tags( apply_filters( 'product_listing_additional_styles', '', $archive_template ) );
	if ( ! empty( $styles ) && ! is_ic_admin() ) {
		echo '<style>' . esc_html( $styles ) . '</style>';
	}
}

add_action( 'before_product_page', 'product_page_additional_styles' );

/**
 * Adds the product page inline styles container.
 */
function product_page_additional_styles() {
	$styles = wp_strip_all_tags( apply_filters( 'product_page_additional_styles', '' ) );
	if ( ! empty( $styles ) && ! is_ic_admin() ) {
		echo '<style>' . esc_html( $styles ) . '</style>';
	}
}

/**
 * Returns product listing template defined in settings
 *
 * @return string
 */
function get_product_listing_template() {
	global $shortcode_query;
	$default = 'default';
	if ( ! empty( $shortcode_query ) ) {
		global $archive_template;
		$archive_template = isset( $archive_template ) ? $archive_template : get_option( 'archive_template', $default );
	} else {
		$archive_template = get_option( 'archive_template', $default );
	}
	$archive_template = ! empty( $archive_template ) ? $archive_template : $default;

	return apply_filters( 'product_listing_template', $archive_template );
}


/**
 * Replaces auto products listing, product category pages and product search title with appropriate entries
 *
 * @param string   $page_title Page title.
 * @param int|null $id         Post ID.
 *
 * @return string
 */
function override_product_page_title( $page_title, $id = null ) {
	$listing_id = get_product_listing_id();
	if (
		(
			! is_admin()
			&& is_ic_catalog_page()
			&& ! is_ic_product_page()
			&& ! in_the_ic_loop()
			&& ! is_filter_bar()
			&& ( empty( $id ) || 'al_product' === get_quasi_post_type( get_post_type( $id ) ) )
		)
		|| $listing_id === $id
		) {
			$query = null;
		if ( 'noid' === $listing_id ) {
			return $page_title;
		}
		if ( is_ic_shortcode_integration() ) {
			$this_query = ic_get_global( 'pre_shortcode_query' );
			if ( $this_query ) {
				$query = $this_query;
			}
		} else {
			return $page_title;
		}
		if ( is_ic_taxonomy_page( $query ) && ( empty( $id ) || ! is_ic_product( $id ) ) && $listing_id !== $id ) {
			$page_title = get_product_tax_title( $page_title );
		} elseif ( is_ic_product_search( $query ) ) {
			$page_title = ic_get_search_page_title();
		} elseif ( is_ic_product_listing( $query ) && ( empty( $id ) || $id === $listing_id ) ) {
			$page_title = get_product_listing_title();
		}
	}

	return $page_title;
}

add_action( 'ic_catalog_wp', 'ic_catalog_set_post_title' );

/**
 * Sets the catalog post title for listing and taxonomy pages.
 */
function ic_catalog_set_post_title() {
	if ( is_ic_simple_mode() || is_ic_theme_mode() ) {
		return;
	}
	if ( is_ic_product_listing() ) {
		$page_title = get_product_listing_title();
	} elseif ( is_ic_taxonomy_page() ) {
		$page_title = get_product_tax_title();
	}
	if ( ! empty( $page_title ) ) {
		global $post;
		if ( ! empty( $post->ID ) ) {
			ic_save_global( 'pre_title_post_' . $post->ID, $post->post_title );
			$post->post_title = $page_title;
			add_action( 'before_product_list', 'ic_catalog_reset_post_title' );
		}
	}
}

/**
 * Restores the previous post title after catalog rendering.
 */
function ic_catalog_reset_post_title() {
	if ( is_ic_in_shortcode() ) {
		return;
	}
	global $post;
	the_post();
	if ( ! empty( $post->ID ) ) {
		$pre_title = ic_get_global( 'pre_title_post_' . $post->ID );
		if ( ! empty( $pre_title ) ) {
			$post->post_title = $pre_title;
			ic_delete_global( 'pre_title_post_' . $post->ID );
			remove_action( 'before_product_list', 'ic_catalog_reset_post_title' );
		}
	}
	rewind_posts();
}

/**
 * Gets the search page title.
 *
 * @return string
 */
function ic_get_search_page_title() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search query check.
	if ( ! empty( $_GET['s'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search query value.
		$search = sanitize_text_field( wp_unslash( $_GET['s'] ) );
	} else {
		$search = get_search_query( false );
		if ( empty( $search ) ) {
			$query = ic_get_catalog_query();
			if ( ! empty( $query->query['s'] ) ) {
				$search = $query->query['s'];
			}
		}
	}

	return __( 'Search Results for:', 'post-type-x' ) . ' <span class="ic-search-keyword">' . wp_unslash( esc_html( strval( $search ) ) ) . '</span>';
}

/**
 * Gets the product listing title.
 *
 * @return string
 */
function get_product_listing_title() {
	$archive_names = get_archive_names();
	$def_page_id   = get_product_listing_id();
	$page_id       = apply_filters( 'before_archive_post_id', $def_page_id );

	$page = empty( $page_id ) ? '' : get_post( $page_id );
	$page_title = '';
	if ( '' === $page ) {
		$archive_multiple_settings = get_multiple_settings();
		if ( 'off' === $archive_multiple_settings['product_listing_cats'] ) {
			$page_title = $archive_names['all_products'];
		} else {
			$page_title = $archive_names['all_main_categories'];
		}
	} else if (isset($page->post_title)) {
		$page_title = apply_filters( 'the_title', $page->post_title, $page_id );
	}

	return apply_filters( 'ic_product_listing_title', $page_title, $page );
}

/**
 * Gets the product taxonomy title.
 *
 * @param string|null $page_title Fallback page title.
 *
 * @return string
 */
function get_product_tax_title( $page_title = null ) {
	$archive_names = get_archive_names();
	$the_tax       = get_term_by( 'slug', get_query_var( 'term' ), get_query_var( 'taxonomy' ) );
	if ( isset( $the_tax->name ) ) {
		$name = $the_tax->name;
	} else {
		$name = $page_title;
	}
	if ( ! empty( $archive_names['all_prefix'] ) && has_shortcode( $archive_names['all_prefix'], 'product_category_name' ) ) {
		$page_title = do_shortcode( $archive_names['all_prefix'] );
	} elseif ( ! empty( $archive_names['all_prefix'] ) && ! empty( $name ) ) {
		$page_title = do_shortcode( $archive_names['all_prefix'] ) . ' ' . $name;
	} else {
		$page_title = $name;
	}

	return $page_title;
}

add_filter( 'nav_menu_css_class', 'product_listing_current_nav_class', 10, 2 );

/**
 * Adds product post type navigation menu current class
 *
 * @param array    $classes Menu item classes.
 * @param stdClass $item    Menu item object.
 *
 * @return string
 * @global type $post
 */
function product_listing_current_nav_class( $classes, $item ) {
	global $post;
	if ( isset( $post->ID ) && is_ic_product_listing() ) {
		if (isset($item->object_id) && get_product_listing_id() === $item->object_id ) {
			$classes[] = 'current-menu-item';
			$classes[] = 'current_page_item';
		} else {
			$key = array_search( 'current-menu-item', $classes, true );
			if ( false !== $key ) {
				unset( $classes[ $key ] );
			}
			$key = array_search( 'current_page_parent', $classes, true );
			if ( false !== $key ) {
				unset( $classes[ $key ] );
			}
		}
	} elseif ( isset( $post->ID ) && ( is_ic_product_page() || is_ic_taxonomy_page() ) ) {
		if ( isset( $item->object ) && false === strpos( $item->object, 'al_product-cat' ) && 'custom' !== $item->object ) {
			$key = array_search( 'current-menu-item', $classes, true );
			if ( false !== $key ) {
				unset( $classes[ $key ] );
			}
			$key = array_search( 'current_page_parent', $classes, true );
			if ( false !== $key ) {
				unset( $classes[ $key ] );
			}
		}
	}

	return $classes;
}

add_filter( 'page_css_class', 'product_listing_page_nav_class', 10, 2 );

/**
 * Adds products post type navigation class for automatic main menu
 *
 * @param array   $classes Page classes.
 * @param WP_Post $page    Page object.
 *
 * @return string
 * @global type $post
 */
function product_listing_page_nav_class( $classes, $page ) {
	global $post;
	if ( isset( $post->ID ) && is_ic_product_listing() ) {
		if ( get_product_listing_id() === $page->ID ) {
			$classes[] = 'current-menu-item';
			$classes[] = 'current_page_item';
		} else {
			$key = array_search( 'current-menu-item', $classes, true );
			if ( false !== $key ) {
				unset( $classes[ $key ] );
			}
			$key = array_search( 'current_page_parent', $classes, true );
			if ( false !== $key ) {
				unset( $classes[ $key ] );
			}
		}
	} elseif ( isset( $post->ID ) && ( is_ic_product_page() || is_ic_taxonomy_page() ) ) {
		$key = array_search( 'current-menu-item', $classes, true );
		if ( false !== $key ) {
			unset( $classes[ $key ] );
		}
		$key = array_search( 'current_page_parent', $classes, true );
		if ( false !== $key ) {
			unset( $classes[ $key ] );
		}
	}

	return $classes;
}

/**
 * Defines custom classes to product or category listing div
 *
 * @param string $archive_template Archive template slug.
 * @param string $where            Context.
 *
 * @return string
 */
function product_list_class( $archive_template, $where = 'product-list' ) {
	return apply_filters( 'product-list-class', '', $where, $archive_template );
}

/**
 * Defines custom attributes for the product list container.
 *
 * @param WP_Query|null $query Query object.
 *
 * @return string
 */
function product_list_attr( $query = null ) {
	return apply_filters( 'product-list-attr', '', $query );
}

/**
 * Defines custom classes to product or category element div
 *
 * @param int $product_id Product ID.
 *
 * @return string
 */
function product_class( $product_id ) {
	$class = get_post_status( $product_id );

	return apply_filters( 'product-class', $class, $product_id );
}

/**
 * Defines custom classes to product or category element div
 *
 * @param int $category_id Category ID.
 *
 * @return string
 */
function product_category_class( $category_id ) {
	return apply_filters( 'product-category-class', '', $category_id );
}

add_action( 'before_product_listing_category_list', 'product_list_categories_header' );

/**
 * Adds product main categories label on product listing
 */
function product_list_categories_header() {
	$archive_names = get_archive_names();
	if ( ! empty( $archive_names['all_main_categories'] ) && ! isset( $shortcode_query ) ) {
		$title = do_shortcode( $archive_names['all_main_categories'] );
		if ( get_product_listing_title() !== $title ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: do_shortcode() output. Escaping it would print the tags instead of rendering them.
			echo '<h2 class="catalog-header">' . do_shortcode( $archive_names['all_main_categories'] ) . '</h2>';
		}
	}
}

add_action( 'before_category_subcategories', 'category_list_subcategories_header' );

/**
 * Adds product subcategories label on category product listing
 */
function category_list_subcategories_header() {
	if ( is_ic_taxonomy_page() ) {
		$archive_names = get_archive_names();
		if ( ! empty( $archive_names['all_subcategories'] ) && ! is_ic_shortcode_query() ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: do_shortcode() output. Escaping it would print the tags instead of rendering them.
			echo '<h2 class="catalog-header">' . do_shortcode( $archive_names['all_subcategories'] ) . '</h2>';
		}
	}
}

add_action( 'before_product_list', 'product_list_header', 9 );

/**
 * Adds product header on product listing
 */
function product_list_header() {
	$archive_names = get_archive_names();
	if ( ( ! empty( $archive_names['all_products'] ) || ! empty( $archive_names['category_products'] ) ) && ! is_ic_shortcode_query() ) {
		if ( is_ic_product_listing() && ! empty( $archive_names['all_products'] ) ) {
			$title = do_shortcode( $archive_names['all_products'] );
			if ( get_product_listing_title() !== $title ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: HTML assembled earlier in this scope, so escaping it here would print the tags.
				echo '<h2 class="catalog-header">' . $title . '</h2>';
			}
		} elseif ( is_ic_taxonomy_page() && ! empty( $archive_names['category_products'] ) && is_ic_product_listing_showing_cats() ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: do_shortcode() output. Escaping it would print the tags instead of rendering them.
			echo '<h2 class="catalog-header">' . do_shortcode( $archive_names['category_products'] ) . '</h2>';
		}
	}
}

/**
 * Defines example image URL
 *
 * @return string
 */
function design_settings_examples_image() {
	return AL_PLUGIN_BASE_PATH . 'templates/themes/img/example-product.jpg';
}

add_filter( 'parse_tax_query', 'exclude_products_from_child_cat' );

/**
 * Excludes products from child categories when needed.
 *
 * @param WP_Query $query Query object.
 */
function exclude_products_from_child_cat( $query ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query string check.
	if ( empty( $_GET['product_category'] ) && ( ! is_admin() || is_ic_ajax() ) && ( is_ic_ajax() || $query->is_main_query() ) && is_ic_taxonomy_page( $query ) && is_ic_only_main_cats( $query ) && ! is_product_filter_active( 'product_category' ) ) {
		foreach ( $query->tax_query->queries as $i => $xquery ) {
			if ( ! empty( $query->tax_query->queries[ $i ] ) && is_array( $query->tax_query->queries[ $i ] ) && ! empty( $query->tax_query->queries[ $i ]['taxonomy'] ) ) {
				$query->tax_query->queries[ $i ]['include_children'] = 0;
			}
		}
	}
}

add_filter( 'product_listing_classes', 'add_classes_on_categories' );

/**
 * Adds neccessary classes for some themes
 *
 * @param string $classes Classes string.
 *
 * @return string
 */
function add_classes_on_categories( $classes ) {
	if ( is_tax() ) {
		$classes .= ' hentry status-publish';
	}

	return $classes;
}

add_action( 'advanced_mode_layout_start', 'advanced_mode_styling' );

/**
 * Adds advanced mode custom styling settings
 */
function advanced_mode_styling() {
	$settings = IC_Catalog_Theme_Integration::settings();
	// Stored option values are normalized before they are concatenated into the style block.
	$container_width   = absint( $settings['container_width'] );
	$container_padding = absint( $settings['container_padding'] );
	$container_bg      = sanitize_hex_color( $settings['container_bg'] );
	$container_text    = sanitize_hex_color( $settings['container_text'] );
	$styling           = '<style>';
	if ( 100 !== $container_width ) {
		$styling .= '#container.content-area.product-catalog {width: ' . $container_width . '%; margin: 0 auto; overflow: hidden; box-sizing: border-box; float: none;}';
	}
	if ( ! empty( $container_bg ) ) {
		$styling .= '#container.content-area.product-catalog {background: ' . $container_bg . ';}';
	}
	if ( 0 !== $container_padding ) {
		$styling .= '.content-area.product-catalog #content {padding: ' . $container_padding . 'px; box-sizing: border-box; float: none; }';
		if ( is_ic_default_theme_sided_sidebar_active() ) {
			$styling .= '.content-area.product-catalog #catalog_sidebar {padding: ' . $container_padding . 'px; box-sizing: border-box;}';
		}
	}
	if ( ! empty( $container_text ) ) {
		$styling .= '#container.content-area.product-catalog * {color: ' . $container_text . ';}';
	}
	if ( 'left' === $settings['default_sidebar'] ) {
		$styling .= '.content-area.product-catalog #catalog_sidebar {float: left;}';
	}
	if ( is_ic_default_theme_sided_sidebar_active() ) {
		$styling .= '.content-area.product-catalog #content {width: 70%;';
		if ( 'left' === $settings['default_sidebar'] ) {
			$styling .= 'float:right;';
		} elseif ( 'right' === $settings['default_sidebar'] ) {
			$styling .= 'float:left;';
		}
		$styling .= '}';
	}
	$styling .= apply_filters( 'advanced_mode_styling_rules', '' );
	$styling .= '</style>';
	if ( '<style></style>' !== $styling ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS block built from hardcoded selectors plus absint()/sanitize_hex_color() values and the advanced_mode_styling_rules filter.
		echo $styling;
	}
}

add_action( 'advanced_mode_layout_start', 'show_advanced_mode_default_sidebar' );

/**
 * Shows theme default catalog styled sidebar if necessary
 */
function show_advanced_mode_default_sidebar() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only wizard query flag.
	$test_advanced = isset( $_GET['test_advanced'] ) ? absint( wp_unslash( $_GET['test_advanced'] ) ) : 0;
	if ( is_ic_default_theme_sided_sidebar_active() || ( is_ic_integration_wizard_page() && 1 === $test_advanced ) ) {
		add_action( 'advanced_mode_layout_after_content', 'advanced_mode_default_sided_sidebar' );
	} elseif ( is_ic_default_theme_sidebar_active() ) {
		add_action( 'advanced_mode_layout_end', 'advanced_mode_default_sidebar' );
	}
}

/**
 * Shows theme default sidebar if necessary
 */
function advanced_mode_default_sidebar() {
	get_sidebar();
}

/**
 * Shows theme default sidebar if necessary
 */
function advanced_mode_default_sided_sidebar() {
	$sidebar_id = apply_filters( 'catalog_default_sidebar_id', 'catalog_sidebar' );
	$class      = apply_filters( 'catalog_default_sidebar_class', 'catalog_sidebar' );
	echo '<div id="' . esc_attr( $sidebar_id ) . '" class="' . esc_attr( $class ) . '" role="complementary">';
	$first_sidebar = ic_get_theme_primary_sidebar();
	dynamic_sidebar( $first_sidebar );
	echo '</div>';
}

/**
 * Gets the primary theme sidebar.
 *
 * @return string
 */
function ic_get_theme_primary_sidebar() {
	$registered_sidebars = $GLOBALS['wp_registered_sidebars'];
	unset( $registered_sidebars['product_sort_bar'] );
	foreach ( $registered_sidebars as $sidebar_name => $sidebar ) {
		if ( ic_string_contains( $sidebar['name'], 'primary' ) || ic_string_contains( $sidebar['name'], 'Primary' ) ) {
			$first_sidebar = $sidebar_name;
			break;
		}
	}
	if ( ! isset( $first_sidebar ) ) {
		reset( $registered_sidebars );
		$first_sidebar = key( $registered_sidebars );
	}

	return apply_filters( 'advanced_mode_sidebar_name', $first_sidebar, $registered_sidebars );
}

/**
 * Returns realted products
 *
 * @param int|null $products_limit Products limit.
 * @param bool     $markup         Whether to return markup.
 * @param int|null $product_id     Product ID.
 *
 * @return string
 * @global object $post
 */
function get_related_products( $products_limit = null, $markup = false, $product_id = null ) {
	if ( ! isset( $products_limit ) ) {
		$products_limit = apply_filters( 'related_products_count', get_current_per_row() );
	}
	if ( ! empty( $product_id ) ) {
		$current_product_id = intval( $product_id );
	}
	if ( empty( $current_product_id ) ) {
		$current_product_id = ic_get_product_id();
	}
	$taxonomy  = apply_filters( 'ic_cat_related_products_tax', get_current_screen_tax() );
	$post_type = get_current_screen_post_type();
	$terms     = ic_get_product_categories( $current_product_id );
	if ( is_array( $terms ) && ! empty( $taxonomy ) && ! empty( $post_type ) ) {
			$terms    = apply_filters( 'ic_catalog_related_product_terms', array_reverse( $terms ), $current_product_id );
			$i        = 0;
			$products = array();
		foreach ( $terms as $term ) {
			$query_param = array(
				'post_type'      => $post_type,
				'orderby'        => 'rand',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Random related products require the current product's taxonomy term constraint.
				'tax_query'      => array(
					array(
						'taxonomy' => $taxonomy,
						'field'    => 'slug',
						'terms'    => $term->slug,
					),
				),
				'posts_per_page' => $products_limit * 2,
			);
			$query       = new WP_Query( $query_param );
			while ( $query->have_posts() ) :
				$query->the_post();
				global $post;
				if ( $current_product_id !== $post->ID ) {
					++$i;
					$products[] = $post->ID;
				}
				if ( $i >= $products_limit ) {
					break;
				}
				endwhile;
			wp_reset_postdata();
			reset_row_class();
			if ( $i >= $products_limit ) {
				break;
			}
		}
		$div = '';
		if ( ! empty( $products ) ) {
			$products = apply_filters( 'ic_cat_related_products', implode( ',', $products ), $products_limit );
			ic_save_global( 'current_related_products', $products );
			remove_filter( 'shortcode_query', 'set_shortcode_product_order', 10, 2 );
			if ( $markup ) {
				ob_start();
				ic_show_template_file( 'product-page/related-products.php' );
				$div = ob_get_clean();
			} else {
				$div = do_shortcode( '[show_products post_type="' . $post_type . '" product="' . $products . '"]' );
			}
			add_filter( 'shortcode_query', 'set_shortcode_product_order', 10, 2 );
		}

		return $div;
	}

		return '';
}

/**
 * Gets product categories for the current product.
 *
 * @param int         $product_id Product ID.
 * @param string|null $taxonomy   Taxonomy name.
 * @param array|null  $args       Unused legacy args.
 *
 * @return array|WP_Error|false
 */
function ic_get_product_categories( $product_id, $taxonomy = null, $args = null ) {
	unset( $args );
	if ( empty( $taxonomy ) ) {
		$taxonomy = apply_filters( 'ic_cat_related_products_tax', get_current_screen_tax() );
	}
	$terms = get_the_terms( $product_id, $taxonomy );

	return $terms;
}

add_action( 'product_category_page_start', 'ic_add_product_category_image' );

/**
 * Shows product category image
 *
 * @param int $term_id Term ID.
 */
function ic_add_product_category_image( $term_id ) {
	if ( is_ic_category_image_enabled() ) {
		ic_save_global( 'current_product_category_id', $term_id );
		ic_show_template_file( 'product-listing/category-image.php' );
	}
}

add_action( 'product_category_page_start', 'ic_add_product_category_description' );

/**
 * Shows product category description
 */
function ic_add_product_category_description() {
	add_filter( 'ic_product_cat_desc', 'wptexturize' );
	add_filter( 'ic_product_cat_desc', 'convert_smilies' );
	add_filter( 'ic_product_cat_desc', 'convert_chars' );
	add_filter( 'ic_product_cat_desc', 'wpautop' );
	add_filter( 'ic_product_cat_desc', 'shortcode_unautop' );
	add_filter( 'ic_product_cat_desc', 'do_shortcode', 11 );
	ic_show_template_file( 'product-listing/category-description.php' );
}

add_action( 'product_listing_entry_inside', 'ic_product_listing_categories', 10, 2 );

/**
 * Generates product listing categories
 *
 * @param string $archive_template  Archive template slug.
 * @param array  $multiple_settings Multiple settings.
 */
function ic_product_listing_categories( $archive_template, $multiple_settings ) {
	$taxonomy_name = apply_filters( 'current_product_catalog_taxonomy', 'al_product-cat' );
	if ( ! is_tax() && ! is_search() ) {
		$before_archive = content_product_adder_archive_before();
		if ( '<div class="entry-summary"></div>' !== $before_archive ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: HTML assembled earlier in this scope, so escaping it here would print the tags.
			echo $before_archive;
		}
		if ( 'on' === $multiple_settings['product_listing_cats'] || 'cats_only' === $multiple_settings['product_listing_cats'] || 'forced_cats_only' === $multiple_settings['product_listing_cats'] ) {

			if ( 'template' !== $multiple_settings['cat_template'] ) {
				$product_subcategories = wp_list_categories( 'show_option_none=No_cat&echo=0&title_li=&taxonomy=' . $taxonomy_name . '&parent=0' );
				if ( false === strpos( $product_subcategories, 'No_cat' ) ) {
					do_action( 'before_product_listing_category_list' );
					ic_save_global( 'current_product_categories', $product_subcategories );
					ic_save_global( 'current_product_archive_template', get_product_listing_template() );
					ic_show_template_file( 'product-listing/categories-listing.php' );
				}
			} else {
				$show_categories = do_shortcode( '[show_categories parent="0" shortcode_query="no"]' );
				if ( ! empty( $show_categories ) ) {
					do_action( 'before_product_listing_category_list' );
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: HTML assembled earlier in this scope, so escaping it here would print the tags.
					echo $show_categories;
					if ( 'list' !== $archive_template && more_products() && 'forced_cats_only' !== $multiple_settings['product_listing_cats'] ) {
						echo '<hr>';
					}
				}
			}
		}
	} elseif ( is_tax() ) {
		$term = ic_get_queried_object();
		if ( empty( $term->term_id ) ) {
			return;
		}
		if ( current_filter() === 'product_listing_entry_inside' ) {
			if ( has_shortcode( $term->description, 'product_listing_products' ) ) {
				remove_action( 'product_listing_entry_inside', 'ic_product_listing_products', 20, 2 );
				add_action( 'after_product_list', 'product_archive_pagination', 99, 0 );
				remove_action( 'product_listing_end', 'product_archive_pagination' );
			}
			do_action( 'product_category_page_start', $term->term_id );

			if ( has_shortcode( $term->description, 'product_listing_categories' ) ) {
				return;
			}
		}
		if ( 'on' === $multiple_settings['category_top_cats'] || 'only_subcategories' === $multiple_settings['category_top_cats'] ) {
			if ( 'template' !== $multiple_settings['cat_template'] ) {
				$product_subcategories = wp_list_categories( 'show_option_none=No_cat&echo=0&title_li=&taxonomy=' . $taxonomy_name . '&child_of=' . $term->term_id );
				if ( false === strpos( $product_subcategories, 'No_cat' ) ) {
					do_action( 'before_category_subcategories' );
					ic_save_global( 'current_product_categories', $product_subcategories );
					ic_save_global( 'current_product_archive_template', get_product_listing_template() );
					ic_show_template_file( 'product-listing/categories-listing.php' );
				}
			} else {
				$show_categories = do_shortcode( '[show_categories parent=' . get_queried_object_id() . ' shortcode_query=no]' );
				if ( ! empty( $show_categories ) ) {
					do_action( 'before_category_subcategories' );
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: HTML assembled earlier in this scope, so escaping it here would print the tags.
					echo $show_categories;
					if ( 'list' !== $archive_template && more_products() ) {
						echo '<hr>';
					}
				}
			}
		}
	}
}

add_action( 'product_listing_entry_inside', 'ic_product_listing_products', 20, 2 );

/**
 * Generates product listing products
 *
 * @param string $archive_template  Archive template slug.
 * @param array  $multiple_settings Multiple settings.
 */
function ic_product_listing_products( $archive_template, $multiple_settings ) {
	global $ic_is_home;
	if ( ! is_ic_ajax() && ( is_ic_product_listing() || ! is_ic_catalog_page() ) && 'forced_cats_only' === $multiple_settings['product_listing_cats'] ) {
		return;
	}

	if ( is_home_archive() || ( ! more_products() && ( is_custom_product_listing_page() || ( ! is_ic_catalog_page() && ic_get_global( 'inside_show_catalog_shortcode' ) && ! is_ic_only_main_cats() ) ) ) ) {
		$catalog_query = ic_set_home_listing_query();
		if ( ! empty( $catalog_query ) ) {
			global $wp_query;
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Home listing query temporarily replaces the global query and is reset on product_listing_end.
			$wp_query = $catalog_query;

		}
		$ic_is_home = 1;
	}
	global $wp_query;
	if ( more_products() ) {
		do_action( 'before_product_list', $archive_template, $multiple_settings );
		$product_list = '';
		while ( have_posts() ) :
			the_post();
			$post = get_post();
			if ( empty( $post->ID ) ) {

				continue;
			}
			ic_set_product_id( $post->ID );
			$product_list .= get_catalog_template( $archive_template, $post );
			ic_reset_product_id();
		endwhile;
		$product_list = apply_filters( 'product_list_ready', $product_list, $archive_template, 'auto_listing' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: HTML assembled earlier in this scope, so escaping it here would print the tags.
		echo '<div class="product-list ' . $archive_template . ' ' . product_list_class( $archive_template ) . '" ' . product_list_attr() . '>' . $product_list . '</div>';
		do_action( 'after_product_list', $archive_template, $multiple_settings );
		add_action( 'product_listing_end', 'ic_product_clear_span', 99 );
	} elseif ( ( ! is_product_filters_active() && is_search() ) && ! more_products() ) {
		do_action( 'ic_before_empty_search', $archive_template, $multiple_settings );
		ob_start();
		$notfound_text = __( 'Sorry, but nothing matched your search terms. Please try again with some different keywords.', 'post-type-x' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: the return value of the 'ic_catalog_not_found_message' filter, which extensions and themes legitimately use to inject their own HTML.
		echo '<div class="product-list ' . product_list_class( $archive_template ) . '"><p>' . apply_filters( 'ic_catalog_not_found_message', $notfound_text ) . '</p></div>';
		product_search_form();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: the return value of the 'ic_catalog_not_found_content' filter, which extensions and themes legitimately use to inject their own HTML.
		echo apply_filters( 'ic_catalog_not_found_content', ob_get_clean() );
	} elseif ( is_product_filters_active() && ! more_products() ) {
		show_product_sort_bar();
		/* translators: 1: opening reset filters link, 2: closing reset filters link */
		$notfound_filters_text = sprintf( __( 'Sorry, but nothing matched your search terms. Please try again with some different options or %1$sreset filters%2$s.', 'post-type-x' ), '<a href="' . esc_url( get_filters_bar_reset_url() ) . '">', '</a>' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: the return value of the 'ic_catalog_not_found_filters_message' filter, which extensions and themes legitimately use to inject their own HTML.
		echo '<div class="product-list ' . product_list_class( $archive_template ) . '"><p>' . apply_filters( 'ic_catalog_not_found_filters_message', $notfound_filters_text ) . '</p></div>';
	} elseif ( ! more_products() && ! ic_is_rendering_catalog_block() && ! is_ic_admin() && ( ! is_ic_only_main_cats() || ( is_ic_taxonomy_page() && ! has_category_children() ) ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: built by ic_empty_list_text().
		echo ic_empty_list_text();
	}
}

/**
 * Gets the home listing query args.
 *
 * @return array|false
 */
function ic_home_listing_query_args() {
	$args = ic_get_global( 'home_listing_query_args' );
	if ( false !== $args ) {
		return $args;
	}
	$multiple_settings = get_multiple_settings();
	if ( get_query_var( 'paged' ) ) {
		$paged = get_query_var( 'paged' );
	} elseif ( get_query_var( 'page' ) ) {
		$paged = get_query_var( 'page' );
	} else {
		$paged = 1;
	}
	$args = apply_filters(
		'home_product_listing_query',
		array(
			'post_status'    => ic_visible_product_status(),
			'post_type'      => 'al_product',
			'posts_per_page' => isset( $multiple_settings['archive_products_limit'] ) ? $multiple_settings['archive_products_limit'] : 12,
			'paged'          => $paged,
		)
	);
	ic_save_global( 'home_listing_query_args', $args );

	return $args;
}

/**
 * Gets or creates the home listing query.
 *
 * @return WP_Query|false
 */
function ic_set_home_listing_query() {
	$args = ic_home_listing_query_args();
	if ( ! empty( $args ) ) {
		$catalog_query = apply_filters( 'ic_home_query', '', $args );
		if ( empty( $catalog_query ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Used only to build a cache key from query args.
			$cache_meta    = 'ic_home_listing_query_' . serialize( array_values( $args ) );
			$catalog_query = ic_get_global( $cache_meta );
			if ( false !== $catalog_query ) {
				return $catalog_query;
			}
			do_action( 'ic_before_home_listing_query' );
			$catalog_query = new WP_Query( $args );
			do_action( 'ic_after_home_listing_query' );

			if ( ! empty( $args['is_archive'] ) ) {
				$catalog_query->is_post_type_archive = true;
			}
			ic_save_global( $cache_meta, $catalog_query );
		}

		return $catalog_query;
	}

	return false;
}

/**
 * Outputs the clear span after product listings.
 */
function ic_product_clear_span() {
	?>
	<span class="clear"></span>
	<?php
}

add_action( 'product_listing_end', 'ic_reset_home_listing_query', 99 );

/**
 * Resets the query for home product listing
 *
 * @global type $ic_is_home
 */
function ic_reset_home_listing_query() {
	global $ic_is_home;
	if ( isset( $ic_is_home ) && 1 === $ic_is_home ) {
		// phpcs:ignore WordPress.WP.DiscouragedFunctions.wp_reset_query_wp_reset_query -- The function intentionally restores the temporary global query swap.
		wp_reset_query();
	}
}

add_filter( 'body_class', 'ic_catalog_page_body_class', 99 );

/**
 * Adds catalog body classes.
 *
 * @param array $classes Body classes.
 *
 * @return array
 */
function ic_catalog_page_body_class( $classes ) {
	if ( is_ic_catalog_page() ) {
		$classes[] = 'ecommerce-product-catalog';
		if ( is_ic_product_listing() ) {
			$classes[] = 'main-catalog-page';
		}
		if ( ! is_ic_theme_mode() ) {
			$classes[]     = 'type-page';
			$classes[]     = 'page';
			$listing_id    = intval( get_product_listing_id() );
			$template_slug = apply_filters( 'ic_catalog_page_template', get_page_template_slug( $listing_id ) );
			if ( ! empty( $template_slug ) ) {
				$classes[]      = 'page-template';
				$template_parts = explode( '/', $template_slug );
				foreach ( $template_parts as $part ) {
					$classes[] = 'page-template-' . sanitize_html_class(
						str_replace(
							array(
								'.',
								'/',
							),
							'-',
							basename( $part, '.php' )
						)
					);
				}
				$classes[] = 'page-template-' . sanitize_html_class( str_replace( '.', '-', $template_slug ) );
			} else {
				$classes[] = 'page-template-default';
			}
		}
	}

	return $classes;
}

add_filter( 'ic_catalog_single_body_class', 'ic_catalog_product_page_body_class' );

/**
 * Adds body classes for product pages.
 *
 * @param array $body_class Body classes.
 *
 * @return array
 */
function ic_catalog_product_page_body_class( $body_class ) {
	$product_id   = ic_get_product_id();
	$body_class[] = 'single-product-page';
	$terms        = wp_get_post_terms(
		$product_id,
		'al_product-cat',
		array(
			'fields'  => 'ids',
			'orderby' => 'none',
		)
	);
	if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term_id ) {
			$body_class[] = 'product-category-' . $term_id;
			$parent_terms = ic_get_parent_terms( $term_id );
			if ( ! empty( $parent_terms ) ) {
				foreach ( $parent_terms as $parent_term_id ) {
					$body_class[] = 'parent-product-category-' . $parent_term_id;
				}
			}
		}
	}

	return $body_class;
}

add_filter( 'ic_catalog_tax_body_class', 'ic_catalog_product_category_body_class' );

/**
 * Adds body classes for product category pages.
 *
 * @param array $body_class Body classes.
 *
 * @return array
 */
function ic_catalog_product_category_body_class( $body_class ) {
	$term_id = ic_get_current_category_id();
	if ( empty( $term_id ) ) {
		return $body_class;
	}
	$term = get_term( $term_id );
	if ( ! is_wp_error( $term ) ) {
		$body_class[] = 'product-category-page';
		$body_class[] = 'product-category-page-' . $term_id;
	}
	if ( ! empty( $term->parent ) ) {
		$terms = array( $term->parent );
		foreach ( $terms as $term_id ) {
			$body_class[] = 'product-category-page-parent-' . $term_id;
			$parent_terms = ic_get_parent_terms( $term_id );
			if ( ! empty( $parent_terms ) ) {
				foreach ( $parent_terms as $parent_term_id ) {
					$body_class[] = 'product-category-page-parent-' . $parent_term_id;
				}
			}
		}
	}

	return $body_class;
}

/**
 * Gets parent term IDs for the given term.
 *
 * @param int $term_id Term ID.
 *
 * @return array
 */
function ic_get_parent_terms( $term_id ) {
	$term = get_term( $term_id );
	if ( ! empty( $term->parent ) ) {
		$term_ids    = array( $term->parent );
		$parent_term = ic_get_parent_terms( $term->parent );
		if ( ! empty( $parent_term ) ) {
			$term_ids = array_merge( $term_ids, $parent_term );
		}

		return array_unique( $term_ids );
	}

	return array();
}

if ( ! function_exists( 'ic_get_template_file' ) ) {

	/**
	 * Manages template files paths
	 *
	 * @param string    $file_path  Relative file path.
	 * @param string    $base_path  Base templates path.
	 * @param int|false $product_id Product ID.
	 *
	 * @return string|false
	 */
	function ic_get_template_file( $file_path, $base_path = AL_BASE_TEMPLATES_PATH, $product_id = false ) {
		if ( empty( $base_path ) ) {
			$base_path = AL_BASE_TEMPLATES_PATH;
		}
		$folder    = get_custom_templates_folder();
		$file_path = apply_filters( 'ic_template_file_path', $file_path, $product_id );
		$base_path = apply_filters( 'ic_template_file_base_path', $base_path, $file_path );
		$file_name = basename( $file_path );
		if ( file_exists( $folder . $file_name ) ) {
			return $folder . $file_name;
		} elseif ( file_exists( $base_path . '/templates/template-parts/' . $file_path ) ) {
			return $base_path . '/templates/template-parts/' . $file_path;
		} else {
			return false;
		}
	}

}

if ( ! function_exists( 'ic_show_template_file' ) ) {

	/**
	 * Includes template file
	 *
	 * @param string    $file_path  Relative file path.
	 * @param string    $base_path  Base templates path.
	 * @param int|false $product_id Product ID.
	 *
	 * @return void
	 */
	function ic_show_template_file( $file_path, $base_path = AL_BASE_TEMPLATES_PATH, $product_id = false ) {
		$path = ic_get_template_file( $file_path, $base_path, $product_id );
		if ( $path ) {
			ic_enqueue_main_catalog_js_css();
			if ( $product_id ) {
				$prev_id = ic_get_global( 'product_id' );
				if ( $prev_id !== $product_id && is_ic_product( $product_id ) ) {
					ic_save_global( 'product_id', $product_id, false, false, true );
				}
			}
			include $path;
			if ( $product_id && isset( $prev_id ) ) {
				if ( $prev_id && $prev_id !== $product_id && is_ic_product( $prev_id ) ) {
					ic_save_global( 'product_id', $prev_id, false, false, true );
				} elseif ( false === $prev_id ) {
					ic_delete_global( 'product_id' );
				}
			}
		}
	}

}

add_filter( 'get_the_archive_title', 'ic_catalog_archive_title' );

/**
 * Filters the archive title for catalog pages.
 *
 * @param string $title Archive title.
 *
 * @return string
 */
function ic_catalog_archive_title( $title ) {
	if ( is_ic_taxonomy_page() ) {
		$title = single_term_title( '', false );
	} elseif ( is_ic_product_listing() ) {
		$title = post_type_archive_title( '', false );
	}

	return $title;
}
