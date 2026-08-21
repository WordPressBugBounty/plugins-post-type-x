<?php
/**
 * Product category dropdown walker.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Product category dropdown walker.
 */
class IC_Cat_Walker_CategoryDropdown extends Walker_CategoryDropdown {

	/**
	 * Starts rendering a category dropdown option.
	 *
	 * @param string $output   Walker output.
	 * @param object $category Current category.
	 * @param int    $depth    Walker depth.
	 * @param array  $args     Walker arguments.
	 * @param int    $id       Current item ID.
	 *
	 * @return string
	 */
	public function start_el( &$output, $category, $depth = 0, $args = array(), $id = 0 ) {
		$term_link = get_term_link( $category );

		if ( is_wp_error( $term_link ) ) {
			return $output;
		}

		$pad      = str_repeat( '&nbsp;', $depth * 3 );
		$taxonomy = $category->taxonomy;
		$cat_name = apply_filters( 'list_cats', $category->name, $category );
		$output  .= "\t<option class=\"level-$depth\" value=\"" . $term_link . '"';

		if ( get_query_var( $taxonomy ) === $category->slug ) {
			$output .= ' selected="selected"';
		}

		$output .= '>';
		$output .= $pad . $cat_name;

		if ( ! empty( $args['show_count'] ) ) {
			$output .= '&nbsp;&nbsp;(' . $category->count . ')';
		}

		if ( isset( $args['show_last_update'] ) ) {
			$format  = 'Y-m-d';
			$output .= '&nbsp;&nbsp;' . gmdate( $format, $category->last_update_timestamp );
		}

		$output .= "</option>\n";

		return $output;
	}
}

