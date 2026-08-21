<?php
/**
 * Product taxonomy dropdown walker that uses slugs as values.
 *
 * @package Ecommerce_Product_Catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Walker that uses term slugs as dropdown option values.
 */
class IC_Walker_Tax_Slug_Dropdown extends Walker_CategoryDropdown {

	/**
	 * Starts the element output.
	 *
	 * @param string $output   Passed by reference. Used to append additional content.
	 * @param object $category Category data object.
	 * @param int    $depth    Depth of category in reference to parents.
	 * @param array  $args     Arguments for the dropdown.
	 * @param int    $id       Optional ID of the current category.
	 *
	 * @return string
	 */
	public function start_el( &$output, $category, $depth = 0, $args = array(), $id = 0 ) {
		$pad      = str_repeat( '&nbsp;', $depth * 3 );
		$cat_name = apply_filters( 'list_cats', $category->name, $category );

		if ( ! isset( $args['value'] ) ) {
			$args['value'] = ( 'category' !== $category->taxonomy ? 'slug' : 'id' );
		}

		$value = ( 'slug' === $args['value'] ? $category->slug : $category->term_id );

		$output .= "\t<option class=\"level-$depth\" value=\"" . $value . '"';
		if ( $value === (string) $args['selected'] ) {
			$output .= ' selected="selected"';
		}
		$output .= '>';
		$output .= $pad . $cat_name;
		$output .= '&nbsp;&nbsp;(' . $category->count . ')';

		$output .= "</option>\n";

		return $output;
	}
}

