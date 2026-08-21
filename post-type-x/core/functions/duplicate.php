<?php
/**
 * Product duplication helpers.
 *
 * @package ecommerce-product-catalog/functions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Manages product duplication.
 *
 * @version        1.0.0
 * @package        ecommerce-product-catalog/functions
 * @author        impleCode
 */
add_action( 'admin_init', 'ic_duplicate_product' );

/**
 * Duplicates product.
 *
 * @global wpdb $wpdb WordPress database abstraction.
 *
 * @return void
 */
function ic_duplicate_product() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified immediately below.
	if ( isset( $_GET['ic-duplicate'] ) && isset( $_GET['ic_duplicate_product_nonce'] ) && current_user_can( 'publish_products' ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified here.
		$duplicate_nonce = sanitize_text_field( wp_unslash( $_GET['ic_duplicate_product_nonce'] ) );
		if ( ! wp_verify_nonce( $duplicate_nonce, 'ic_duplicate_product' ) ) {
			return;
		}
		global $wpdb;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified above.
		$original_id = isset( $_GET['ic-duplicate'] ) ? intval( wp_unslash( $_GET['ic-duplicate'] ) ) : 0;

		$duplicate = get_post( $original_id, 'ARRAY_A' );

		$duplicate['post_date']         = current_time( 'mysql' );
		$duplicate['post_date_gmt']     = current_time( 'mysql', true );
		$duplicate['post_modified']     = current_time( 'mysql' );
		$duplicate['post_modified_gmt'] = current_time( 'mysql', true );
		$duplicate['post_status']       = 'draft';
		unset( $duplicate['ID'] );
		unset( $duplicate['guid'] );
		unset( $duplicate['comment_count'] );
		unset( $duplicate['post_name'] );
		$duplicate_id = wp_insert_post( $duplicate );
		if ( ! is_wp_error( $duplicate_id ) ) {

			$taxonomies = get_object_taxonomies( $duplicate['post_type'] );
			foreach ( $taxonomies as $taxonomy ) {
				$terms = wp_get_post_terms( $original_id, $taxonomy, array( 'fields' => 'ids' ) );
				wp_set_object_terms( $duplicate_id, $terms, $taxonomy );
			}
			$custom_fields = get_post_custom( $original_id );
			foreach ( $custom_fields as $key => $value ) {
				if ( ic_duplicate_is_restricted( $key ) ) {
					continue;
				}
				if ( is_array( $value ) && count( $value ) > 0 ) {
					foreach ( $value as $i => $v ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Product duplication intentionally copies each allowlisted source meta row into the new product through wpdb::insert().
						$wpdb->insert(
							$wpdb->prefix . 'postmeta',
							array(
								'post_id'    => $duplicate_id,
								// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Duplicate creation must copy the exact source product meta key.
								'meta_key'   => $key,
								// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Duplicate creation must copy the exact source product meta value.
								'meta_value' => $v,
							)
						);
					}
				} elseif ( ! empty( $value ) && ! is_array( $value ) ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Product duplication intentionally copies the selected source meta row into the new product through wpdb::insert().
					$wpdb->insert(
						$wpdb->prefix . 'postmeta',
						array(
							'post_id'    => $duplicate_id,
							// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Duplicate creation must copy the exact source product meta key.
							'meta_key'   => $key,
							// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Duplicate creation must copy the exact source product meta value.
							'meta_value' => $value,
						)
					);
				}
			}
			$redirect_url = admin_url( 'edit.php?post_type=' . $duplicate['post_type'] . '&ic-duplicated=' . $duplicate_id );
				wp_safe_redirect( $redirect_url );
				exit;
		}
	}
}

/**
 * Checks if the meta key is on the restricted keys list.
 *
 * @param string $meta_key Meta key.
 *
 * @return bool
 */
function ic_duplicate_is_restricted( $meta_key ) {
	$restricted_keys = array(
		'_wp_old_slug',
		'_edit_lock',
		'_edit_last',
	);
	if ( in_array( $meta_key, $restricted_keys, true ) ) {
		return true;
	}

	return false;
}

add_filter( 'post_row_actions', 'ic_product_duplicator_action_row', 99, 2 );

/**
 * Adds duplication link.
 *
 * @param array   $actions Current row actions.
 * @param WP_Post $post    Current post object.
 *
 * @return array
 */
function ic_product_duplicator_action_row( $actions, $post ) {
	if ( ic_string_contains( $post->post_type, 'al_product' ) && current_user_can( 'publish_products' ) && ! isset( $actions['clone'] ) && ! isset( $actions['duplicate_post'] ) ) {
		$label = __( 'Duplicate', 'post-type-x' );
		$url   = wp_nonce_url( admin_url( 'edit.php?post_type=al_product&ic-duplicate=' . $post->ID ), 'ic_duplicate_product', 'ic_duplicate_product_nonce' );

			// Create a nonce and add an action.
			$action  = '<a class="ic-duplicate-product" href="' . $url . '">' . $label . '</a>';
			$actions = array_slice( $actions, 0, 3, true ) + array( 'duplicate_product' => $action ) + array_slice( $actions, 3, count( $actions ) - 1, true );
	}

	return $actions;
}

add_action( 'ic_catalog_admin_notices', 'ic_post_duplicator_notice' );

/**
 * Shows product duplication notice.
 *
 * @return void
 */
function ic_post_duplicator_notice() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice flag.
	$ic_duplicated_id = isset( $_GET['ic-duplicated'] ) ? intval( $_GET['ic-duplicated'] ) : '';
	if ( ! empty( $ic_duplicated_id ) ) {
		$names = get_catalog_names();
		$link  = '<a href="' . esc_url( get_edit_post_link( $ic_duplicated_id ) ) . '">' . esc_html__( 'here', 'post-type-x' ) . '</a>';
		/* translators: 1: singular catalog item label, 2: singular catalog item label in lowercase, 3: edit link HTML. */
		$label = sprintf( __( '%1$s successfully duplicated! You can edit your new %2$s %3$s.', 'post-type-x' ), esc_html( ic_ucfirst( $names['singular'] ) ), esc_html( ic_lcfirst( $names['singular'] ) ), $link );
		?>
		<div class="updated">
			<p>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Message is assembled from escaped values and trusted link markup.
				echo $label;
				?>
			</p>
		</div>
		<?php
	}
}
