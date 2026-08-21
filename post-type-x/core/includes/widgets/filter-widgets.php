<?php
/**
 * Filter widget classes and helpers.
 *
 * @version 1.4.0
 * @package ecommerce-product-catalog/includes
 * @author  impleCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/class-product-category-filter.php';
require_once __DIR__ . '/class-product-sort-filter.php';
require_once __DIR__ . '/class-ic-product-size-filter.php';
require_once __DIR__ . '/class-ic-active-filters-widget.php';

add_action( 'widgets_init', 'register_product_filter_bar', 30 );

/**
 * Registers the catalog filter bar sidebar.
 *
 * @return void
 */
function register_product_filter_bar() {
	if ( is_plural_form_active() ) {
		$names = get_catalog_names();
		/* translators: %s: singular catalog item label. */
		$label = sprintf( __( '%s Filters Bar', 'post-type-x' ), ic_ucfirst( $names['singular'] ) );
		/* translators: %1$s: singular catalog item label. */
		$sublabel = sprintf( __( 'Appears above the product list. Recommended widgets: %1$s Search, %1$s Price Filter, %1$s Sort and %1$s Category Filter.', 'post-type-x' ), ic_ucfirst( $names['singular'] ) );
	} else {
		$label    = __( 'Catalog Filters Bar', 'post-type-x' );
		$sublabel = __( 'Appears above the product list. Recommended widgets: Catalog Search, Catalog Price Filter, Catalog Sort and Catalog Category Filter.', 'post-type-x' );
	}
	$args = array(
		'name'          => $label,
		'id'            => 'product_sort_bar',
		'description'   => $sublabel,
		'class'         => '',
		'before_widget' => '<div id="%1$s" class="filter-widget %2$s">',
		'after_widget'  => '</div>',
		'before_title'  => '<h2 class="filter-widget-title">',
		'after_title'   => '</h2>',
	);
	register_sidebar( $args );
}

/**
 * Seeds the default filter bar widgets.
 *
 * @return void
 */
function ic_set_filter_bar_default_widgets() {
	$sidebar_id     = 'product_sort_bar';
	$active_widgets = get_option( 'sidebars_widgets' );
	if ( empty( $active_widgets ) ) {
		$active_widgets = array();
	}
	if ( ! empty( $active_widgets[ $sidebar_id ] ) ) {

		return;
	}
	$widgets                       = ic_get_filter_bar_default_widgets();
	$active_widgets[ $sidebar_id ] = array();
	foreach ( $widgets as $widget ) {
		if ( ! empty( $widget ) ) {
			$active_widgets[ $sidebar_id ][] = $widget;
		}
	}

	update_option( 'sidebars_widgets', $active_widgets );
}

/**
 * Returns the default widgets for the filter bar.
 *
 * @return array
 */
function ic_get_filter_bar_default_widgets() {
	$widgets = array();
	if ( class_exists( 'product_widget_search' ) ) {
		$widgets[] = ic_set_default_widget( 'product_search', array( 'title' => '' ) );
	}
	if ( class_exists( 'product_sort_filter' ) ) {
		$widgets[] = ic_set_default_widget(
			'product_sort_filter',
			array(
				'title'             => '',
				'shortcode_support' => 0,
			)
		);
	}
	if ( class_exists( 'product_price_filter' ) ) {
		$widgets[] = ic_set_default_widget(
			'product_price_filter',
			array(
				'title'             => '',
				'shortcode_support' => 0,
			)
		);
	}
	if ( class_exists( 'Product_Category_Filter' ) ) {
		$widgets[] = ic_set_default_widget(
			'product_category_filter',
			array(
				'title'             => '',
				'shortcode_support' => 0,
			)
		);
	}

	return $widgets;
}

/**
 * Registers a default widget instance and returns its sidebar identifier.
 *
 * @param string $widget_name    Widget base name.
 * @param array  $widget_content Widget settings.
 *
 * @return string
 */
function ic_set_default_widget( $widget_name, $widget_content ) {
	if ( function_exists( 'register_block_type' ) ) {
		if ( 'product_search' === $widget_name ) {
			$widget_name = 'product-search-widget';
		}
		$widget_name = str_replace( '_', '-', $widget_name );
		$option_name = 'widget_block';
	} else {
		$option_name = 'widget_' . $widget_name;
	}
	$option = get_option( $option_name );
	if ( ! empty( $option ) && is_array( $option ) ) {
		$key = ic_array_key_last( $option );
		if ( is_numeric( $key ) ) {
			++$key;
		} else {
			$key = 99;
		}
	} else {
		$option = array();
		$key    = 1;
	}
	if ( function_exists( 'register_block_type' ) ) {
		$registered_name = 'block-' . $key;
		$option[ $key ]  = array( 'content' => '<!-- wp:ic-epc/' . $widget_name . ' /-->' );
	} else {
		$registered_name = $widget_name . '-' . $key;
		$option[ $key ]  = $widget_content;
	}
	update_option( $option_name, $option );

	return $registered_name;
}

/**
 * Determines whether a filter widget should be shown in the current context.
 *
 * @param array|null $instance    Widget instance.
 * @param string     $filter_name Filter identifier.
 *
 * @return bool
 */
function ic_if_show_filter_widget( $instance = null, $filter_name = '' ) {
	if ( ic_is_rendering_block() || ic_is_rendering_catalog_block() || ( ( ic_get_global( 'inside_show_catalog_shortcode' ) || ! empty( $instance['shortcode_support'] ) ) && ( has_show_products_shortcode() || ic_is_rendering_products_block() ) ) || ( ! is_ic_shortcode_query() && ( is_ic_ajax() || ( ( is_ic_taxonomy_page() || is_ic_product_listing() || ( is_ic_product_search() && more_products() ) ) ) ) ) ) {

		return apply_filters( 'ic_if_show_filter_widget', true, $filter_name );
	}

	return false;
}

add_action( 'implecode_register_widgets', 'register_filter_widgets' );

/**
 * Registers the filter widget classes.
 *
 * @return void
 */
function register_filter_widgets() {
	register_widget( 'Product_Category_Filter' );
	register_widget( 'product_sort_filter' );
	register_widget( 'IC_Product_Size_Filter' );
	register_widget( 'IC_Active_Filters_Widget' );
}

/**
 * Defines the form action for a filter widget.
 *
 * @param array $instance Widget instance.
 *
 * @return string
 */
function get_filter_widget_action( $instance ) {
	if ( is_ic_inside_filters_bar() || ( ! empty( $instance['shortcode_support'] ) && has_show_products_shortcode() ) || is_ic_taxonomy_page() || is_ic_product_search() ) {
		$action = '';
	} elseif ( ! is_ic_catalog_page() && ic_get_global( 'inside_show_catalog_shortcode' ) ) {
			$action = '';
	} else {
		$action = apply_filters( 'ic_product_listing_widget_action', product_listing_url() );
	}

	return $action;
}
