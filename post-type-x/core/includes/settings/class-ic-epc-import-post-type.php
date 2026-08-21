<?php
/**
 * CSV import post type helper class.
 *
 * @package ecommerce-product-catalog/includes/settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Imports other public post types into the catalog.
 */
class IC_EPC_Import_Post_Type {

	/**
	 * Nonce action guarding the post type import.
	 */
	const IMPORT_NONCE_ACTION = 'ic_import_post_type';

	/**
	 * Nonce field name used by the post type import form.
	 *
	 * A dedicated name is required because this form is rendered on the same
	 * screen as the CSV upload form, and `wp_nonce_field()` derives the field
	 * `id` from the field name. Sharing the default `_wpnonce` name would emit
	 * a duplicate `id="_wpnonce"` on that screen.
	 */
	const IMPORT_NONCE_FIELD = 'ic_import_post_type_nonce';

	/**
	 * Hooks the import UI into the settings screen.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'ic_simple_csv_bottom', array( $this, 'import_output' ) );
		add_action( 'ic_csv_import_end', array( $this, 'import_output' ), 15 );
	}

	/**
	 * Returns the post type dropdown HTML.
	 *
	 * @return string|null
	 */
	public function post_types_dropdown() {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$options    = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only read used to preselect the dropdown option.
		$selected = isset( $_GET['import_post_type'] ) ? sanitize_key( wp_unslash( $_GET['import_post_type'] ) ) : '';
		foreach ( $post_types as $post_type ) {
			if ( ! ic_string_contains( $post_type->name, 'al_product' ) && 'attachment' !== $post_type->name ) {
				$options .= '<option value="' . esc_attr( $post_type->name ) . '" ' . selected( $selected, $post_type->name, false ) . '>' . esc_html( $post_type->label ) . '</option>';
			}
		}
		if ( ! empty( $options ) ) {
			$drop_down = '<select name="import_post_type">' . $options . '</select>';

			return $drop_down;
		}

		return null;
	}

	/**
	 * Handles the import output block.
	 *
	 * @return void
	 */
	public function import_output() {
		$this->import_initial_html();
		if ( ! empty( $_GET['import_post_type'] ) ) {
			// The import bulk-inserts posts, so the request must come from the nonced import form.
			check_admin_referer( self::IMPORT_NONCE_ACTION, self::IMPORT_NONCE_FIELD );
			$this->process_import_post_type();
		}
	}

	/**
	 * Renders the initial import HTML.
	 *
	 * @return void
	 */
	public function import_initial_html() {
		$post_types_dropdown = $this->post_types_dropdown();
		if ( ! empty( $post_types_dropdown ) ) {
			echo '<h3>' . esc_html__( 'Import from other content', 'post-type-x' ) . '</h3>';
			echo '<form>';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Current admin query args are only mirrored back into the form; the submission itself is nonced below.
			foreach ( $_GET as $key => $value ) {
				if ( ! is_scalar( $value ) || in_array( $key, array( '_wpnonce', '_wp_http_referer', self::IMPORT_NONCE_FIELD ), true ) ) {
					continue;
				}
				echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
			}
			wp_nonce_field( self::IMPORT_NONCE_ACTION, self::IMPORT_NONCE_FIELD );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- post_types_dropdown() builds a select from escaped post-type labels/values; post KSES would remove the control.
			echo $post_types_dropdown . ' <button type="submit" class="button-secondary">' . esc_html__( 'Import', 'post-type-x' ) . '</button>';
			echo '</form>';
		}
	}

	/**
	 * Imports posts for the selected post type.
	 *
	 * @return void
	 */
	public function process_import_post_type() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- import_output() runs check_admin_referer() before calling this method.
		$post_type = isset( $_GET['import_post_type'] ) ? sanitize_key( wp_unslash( $_GET['import_post_type'] ) ) : '';
		if ( ! empty( $post_type ) ) {
			$posts   = get_posts(
				array(
					'posts_per_page' => 1000,
					'post_type'      => $post_type,
					'post_parent'    => 0,
				)
			);
			$counter = 0;
			foreach ( $posts as $post ) {
				$original_id     = $post->ID;
				$post->ID        = 0;
				$post->post_type = 'al_product';
				$new_id          = wp_insert_post( $post );
				if ( ! is_wp_error( $new_id ) ) {
					$this->copy_post_meta( $new_id, $original_id );
					$this->copy_taxonomies( $new_id, $original_id );
					++$counter;
				}
			}
			/* translators: %s: Number of imported posts. */
			implecode_success( sprintf( __( '%s successfully imported!', 'post-type-x' ), $counter ) );
		}
	}

	/**
	 * Copies post meta to the imported product.
	 *
	 * @param int $target_post_id Target product ID.
	 * @param int $origin_post_id Origin post ID.
	 *
	 * @return void
	 */
	public function copy_post_meta( $target_post_id, $origin_post_id ) {
		if ( ! is_int( $target_post_id ) || ! is_int( $origin_post_id ) ) {
			return;
		}
		$post_meta        = get_post_meta( $origin_post_id );
		$restricted_names = $this->meta_import_restricted_names();
		foreach ( $post_meta as $name => $value ) {
			if ( in_array( $name, $restricted_names, true ) ) {
				continue;
			}
			if ( '_length' === $name ) {
				$name = '_size_length';
			}
			if ( '_width' === $name ) {
				$name = '_size_width';
			}
			if ( '_height' === $name ) {
				$name = '_size_height';
			}
			if ( is_array( $value ) ) {
				foreach ( $value as $val ) {
					update_post_meta( $target_post_id, $name, $val );
				}
			} else {
				update_post_meta( $target_post_id, $name, $value );
			}
		}
	}

	/**
	 * Copies hierarchical taxonomies to the imported product.
	 *
	 * @param int $target_post_id Target product ID.
	 * @param int $origin_post_id Origin post ID.
	 *
	 * @return void
	 */
	public function copy_taxonomies( $target_post_id, $origin_post_id ) {
		$taxonomies   = get_object_taxonomies( get_post_type( $origin_post_id ), 'objects' );
		$valid_tax    = array();
		$priority_tax = array();
		foreach ( $taxonomies as $tax_name => $tax ) {
			if ( empty( $tax->publicly_queryable ) || empty( $tax->public ) || empty( $tax->hierarchical ) ) {
				continue;
			}
			$valid_tax[] = $tax_name;
			if ( ic_string_contains( $tax->label, 'cat' ) || ic_string_contains( $tax->label, 'kat' ) ) {
				$priority_tax[] = $tax_name;
			}
		}
		if ( ! empty( $priority_tax ) ) {
			$valid_tax = $priority_tax;
		}
		if ( ! empty( $valid_tax[0] ) ) {
			$origin_tax = $valid_tax[0];
			$terms      = wp_get_object_terms( $origin_post_id, $origin_tax );
			$term_ids   = array();
			foreach ( $terms as $term ) {
				$term_id       = 0;
				$args          = array(
					'slug'        => $term->slug,
					'parent'      => $term->parent,
					'description' => $term->description,
				);
				$existing_term = term_exists( $term->name, 'al_product-cat', $args['parent'] );
				if ( empty( $existing_term ) ) {
					$inserted = wp_insert_term( $term->name, 'al_product-cat', $args );
					if ( ! is_wp_error( $inserted ) ) {
						$existing_term = $inserted;
					}
				}
				if ( ! empty( $existing_term['term_id'] ) ) {
					$term_id = intval( $existing_term['term_id'] );
				} elseif ( is_int( $existing_term ) ) {
					$term_id = intval( $existing_term );
				}
				if ( ! empty( $term_id ) && function_exists( 'get_term_meta' ) ) {
					$meta = get_term_meta( $term->term_id );
					if ( ! empty( $meta['thumbnail_id'] ) ) {
						if ( ! empty( $meta['thumbnail_id'][0] ) ) {
							$image_id = $meta['thumbnail_id'][0];
						} else {
							$image_id = $meta['thumbnail_id'];
						}
						update_term_meta( $term_id, 'thumbnail_id', intval( $image_id ) );
					}
					$term_ids[] = $term_id;
				}
			}
			if ( ! empty( $term_ids ) ) {
				wp_set_object_terms( $target_post_id, $term_ids, 'al_product-cat' );
			}
		}
	}

	/**
	 * Returns post meta keys that should not be imported.
	 *
	 * @return array
	 */
	public function meta_import_restricted_names() {
		return array( '_wp_page_template', '_edit_last', '_edit_lock' );
	}
}

if ( ! class_exists( 'IC_EPC_import_post_type', false ) ) {
	class_alias( 'IC_EPC_Import_Post_Type', 'IC_EPC_import_post_type' );
}
