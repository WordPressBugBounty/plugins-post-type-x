<?php
/**
 * Product columns support for the catalog admin list table.
 *
 * @package Ecommerce_Product_Catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/class-ic-walker-tax-slug-dropdown.php';

add_filter( 'manage_edit-al_product_columns', 'add_product_columns' );

/**
 * Adds catalog-specific columns to the product list table.
 *
 * @param array $product_columns Existing product columns.
 *
 * @return array
 */
function add_product_columns( $product_columns ) {
	foreach ( $product_columns as $index => $column ) {
		if ( 'cb' === $index ) {
			$new_columns[ $index ] = $column;
			$new_columns['id']     = __( 'ID', 'post-type-x' );
			$new_columns           = apply_filters( 'product_columns_after_id', $new_columns );
			$new_columns['image']  = __( 'Image', 'post-type-x' );
		} elseif ( 'title' === $index ) {
			$new_columns[ $index ] = __( 'Product Name', 'post-type-x' );
			$new_columns           = apply_filters( 'product_columns_after_name', $new_columns );
		} elseif ( 'date' === $index ) {
			$new_columns['taxonomy-al_product-cat'] = __( 'Product Categories', 'post-type-x' );
			$new_columns['shortcode']               = __( 'Shortcode', 'post-type-x' );
			$new_columns                            = apply_filters( 'product_columns_before_date', $new_columns );
			$new_columns[ $index ]                  = $column;
		} else {
			$new_columns[ $index ] = $column;
		}
	}

	return apply_filters( 'product_columns', $new_columns );
}

add_action( 'manage_al_product_posts_custom_column', 'manage_product_columns', 10, 2 );

/**
 * Renders product column values in the admin list table.
 *
 * @param string $column_name Column name.
 * @param int    $product_id  Product post ID.
 *
 * @return void
 */
function manage_product_columns( $column_name, $product_id ) {
	switch ( $column_name ) {
		case 'id':
			echo (int) $product_id;
			break;
		case 'shortcode':
			echo esc_html( '[show_products product="' . (int) $product_id . '"]' );
			break;
		case 'image':
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: built by get_the_post_thumbnail(), WordPress core markup.
			echo get_the_post_thumbnail( $product_id, array( 40, 40 ) );
			break;
		case 'product_cat':
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: built by get_the_term_list(), WordPress core markup.
			echo get_the_term_list( $product_id, 'al_product-cat', '', ', ', '' );
			break;

		default:
			do_action( 'ic_manage_product_columns', $column_name, $product_id );
			break;
	}
}

add_filter( 'manage_edit-al_product_sortable_columns', 'product_sortable_columns' );

/**
 * Registers sortable columns for the product list table.
 *
 * @param array $columns Sortable columns.
 *
 * @return array
 */
function product_sortable_columns( $columns ) {
	$columns['price']                   = 'price';
	$columns['id']                      = 'ID';
	$columns['taxonomy-al_product-cat'] = 'taxonomy-al_product-cat';

	return apply_filters( 'product_sortable_columns', $columns );
}

add_filter( 'posts_orderby', 'orderby_product_cat', 10, 2 );

/**
 * Order by product categories when clicking on table header label
 *
 * @param string   $orderby  Order by SQL clause.
 * @param WP_Query $wp_query Current query instance.
 *
 * @return string
 * @global wpdb $wpdb WordPress database abstraction object.
 */
function orderby_product_cat( $orderby, $wp_query ) {
	global $wpdb;

	if ( is_admin() && isset( $wp_query->query['orderby'] ) && 'taxonomy-al_product-cat' === $wp_query->query['orderby'] ) {
		$orderby  = "(
			SELECT GROUP_CONCAT(name ORDER BY name ASC)
			FROM $wpdb->term_relationships
			INNER JOIN $wpdb->term_taxonomy USING (term_taxonomy_id)
			INNER JOIN $wpdb->terms USING (term_id)
			WHERE $wpdb->posts.ID = object_id
			AND taxonomy = 'al_product-cat'
			GROUP BY object_id
		) ";
		$orderby .= ( 'ASC' === strtoupper( $wp_query->get( 'order' ) ) ) ? 'ASC' : 'DESC';
	}

	return $orderby;
}

/**
 * Adds the product category filter dropdown to the admin list table.
 *
 * @return void
 */
function restrict_listings_by_product_cat() {
	global $typenow;
	global $wp_query;

	if ( 'al_product' === $typenow ) {
		$taxonomy         = 'al_product-cat';
		$current_taxonomy = get_taxonomy( $taxonomy );
		$selected         = isset( $wp_query->query['al_product-cat'] ) ? $wp_query->query['al_product-cat'] : '';
		wp_dropdown_categories(
			array(
				'walker'          => new IC_Walker_Tax_Slug_Dropdown(),
				'value'           => 'slug',
				'show_option_all' => __( 'All', 'post-type-x' ) . ' ' . $current_taxonomy->label,
				'taxonomy'        => $taxonomy,
				'name'            => 'al_product-cat',
				'orderby'         => 'name',
				'selected'        => $selected,
				'hierarchical'    => true,
				'depth'           => 0,
				'hide_empty'      => true,
				'hide_if_empty'   => true,
				'value_field'     => 'slug',
			)
		);
	}
}

add_action( 'restrict_manage_posts', 'restrict_listings_by_product_cat' );

add_action( 'quick_edit_custom_box', 'display_custom_quickedit_product' );

/**
 * Adds quick edit support for product fields
 *
 * @param string $column_name Column name.
 *
 * @return void
 */
function display_custom_quickedit_product( $column_name ) {
	?>
	<fieldset class="inline-edit-col-right inline-edit-product">
		<div class="inline-edit-col column-<?php echo esc_attr( $column_name ); ?>">
			<label class="inline-edit-group">
				<?php
				do_action( 'product_quickedit', $column_name );
				?>
			</label>
		</div>
	</fieldset>
	<?php
}

add_action( 'save_post', 'save_product_quick_edit' );

/**
 * Handles quick edit save for products
 *
 * @param int $product_id Product post ID.
 *
 * @return void
 */
function save_product_quick_edit( $product_id ) {
	$slug        = 'al_product';
	$inline_edit = isset( $_POST['_inline_edit'] ) ? sanitize_text_field( wp_unslash( $_POST['_inline_edit'] ) ) : '';

	if ( empty( $inline_edit ) || ! wp_verify_nonce( $inline_edit, 'inlineeditnonce' ) ) {
		return;
	}

	if ( empty( $_POST['post_type'] ) ) {
		return;
	}

	$post_type = sanitize_text_field( wp_unslash( $_POST['post_type'] ) );

	if ( false === strpos( $slug, $post_type ) ) {
		return;
	}

	// phpcs:ignore WordPress.WP.Capabilities.Unknown -- Custom product capability is registered in functions/capabilities.php.
	if ( ! current_user_can( 'edit_product', $product_id ) ) {
		return;
	}

	do_action( 'save_product_quick_edit', $product_id );
}

add_filter( 'views_edit-al_product', 'ic_epc_products_edit_screen_actions', 10, 1 );

/**
 * Fires product list screen actions.
 *
 * @param array $views Existing product list views.
 *
 * @return array
 */
function ic_epc_products_edit_screen_actions( $views ) {
	do_action( 'ic_products_edit_screen' );

	return $views;
}

/**
 * Returns true when the Products tab should be active.
 *
 * @param string $tab_key Current tab key.
 * @param array  $tab Current tab config.
 * @param mixed  $screen Screen instance.
 *
 * @return bool
 */
function ic_admin_products_tab_active( $tab_key = '', $tab = array(), $screen = null ) {
	return is_ic_product_list_admin_screen();
}

/**
 * Returns true when the Categories tab should be active.
 *
 * @param string $tab_key Current tab key.
 * @param array  $tab Current tab config.
 * @param mixed  $screen Screen instance.
 *
 * @return bool
 */
function ic_admin_product_categories_tab_active( $tab_key = '', $tab = array(), $screen = null ) {
	return is_ic_product_categories_admin_screen() || is_ic_product_categories_edit_admin_screen();
}

/**
 * Displays the add new product action on product admin screens.
 *
 * @return void
 */
function ic_admin_add_new_custom_tabs() {
	// phpcs:ignore WordPress.WP.Capabilities.Unknown -- Custom product capability is registered in functions/capabilities.php.
	if ( current_user_can( 'publish_products' ) ) {
		?>
		<a id="add-new-product-page" class="page-title-action"
			href="<?php echo esc_url( admin_url( 'post-new.php?post_type=al_product' ) ); ?>">
			<?php
			/* translators: %s: catalog singular label. */
			printf( esc_html__( 'Add new %s', 'post-type-x' ), esc_html( get_catalog_names( 'singular' ) ) );
			?>
		</a>
		<?php
	}
}

