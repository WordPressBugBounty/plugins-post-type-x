<?php
/**
 * Product category filter widget class.
 *
 * @package ecommerce-product-catalog/includes/widgets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Category filter widget.
 */
class Product_Category_Filter extends WP_Widget {

	/**
	 * Sets up the widget.
	 *
	 * @return void
	 */
	public function __construct() {
		if ( is_plural_form_active() ) {
			$names = get_catalog_names();
			/* translators: %s: singular catalog item label. */
			$label = sprintf( __( '%s Category Filter', 'post-type-x' ), ic_ucfirst( $names['singular'] ) );
			/* translators: %s: plural catalog item label. */
			$sublabel = sprintf( __( 'Filter %s by categories.', 'post-type-x' ), ic_lcfirst( $names['plural'] ) );
		} else {
			$label    = __( 'Catalog Category Filter', 'post-type-x' );
			$sublabel = __( 'Filter items by categories.', 'post-type-x' );
		}
		$widget_ops = array(
			'classname'             => 'product_category_filter',
			'description'           => $sublabel,
			'show_instance_in_rest' => true,
		);
		parent::__construct( 'product_category_filter', $label, $widget_ops );
	}

	/**
	 * Renders the widget.
	 *
	 * @param array $args     Widget wrapper args.
	 * @param array $instance Widget instance.
	 *
	 * @return void
	 */
	public function widget( $args, $instance ) {
		if ( 'simple' !== get_integration_type() ) {
			if ( ic_if_show_filter_widget( $instance ) ) {
				$form       = false;
				$class      = apply_filters( 'ic_catalog_category_filter_class', 'product-category-filter-container', $instance );
				$title      = apply_filters( 'widget_title', empty( $instance['title'] ) ? '' : $instance['title'], $instance, $this->id_base );
				$child_form = '';
				global $shortcode_query;
				$taxonomy      = get_current_screen_tax();
				$post_ids      = null;
				$category_args = array();
				if ( ! empty( $shortcode_query ) && ! empty( $instance['shortcode_support'] ) && has_show_products_shortcode() ) {
						$excluded_terms = array();
						$shortcode_tax  = array();
					if ( is_product_filter_active( 'product_category' ) ) {
						$shortcode_terms = array();
						if ( ! empty( $shortcode_query->query['tax_query'] ) ) {
							foreach ( $shortcode_query->query['tax_query'] as $shortcode_tax_query ) {
								if ( isset( $shortcode_tax_query['terms'] ) && is_array( $shortcode_tax_query['terms'] ) ) {
									$shortcode_terms = array_merge( $shortcode_terms, $shortcode_tax_query['terms'] );
									if ( ! empty( $shortcode_tax_query['taxonomy'] ) && ! in_array( $shortcode_tax_query['taxonomy'], $shortcode_tax, true ) ) {
										$shortcode_tax[] = $shortcode_tax_query['taxonomy'];
									}
								}
							}
						}
						$current_filter_value = get_product_filter_value( 'product_category' );
						if ( ! empty( $current_filter_value ) ) {
							if ( is_array( $current_filter_value ) ) {
								$excluded_terms = array_merge( $excluded_terms, $current_filter_value );
							} else {
								$excluded_terms[] = $current_filter_value;
							}
						}

						if ( ! empty( $shortcode_terms ) ) {
							$excluded_terms = array_diff( $excluded_terms, $shortcode_terms );
						}
						if ( ! empty( $excluded_terms ) && is_array( $excluded_terms ) ) {
							foreach ( $excluded_terms as $excluded_term ) {
								$this_term = get_term( $excluded_term );
								if ( ! empty( $this_term->taxonomy ) && ! in_array( $this_term->taxonomy, $shortcode_tax, true ) ) {
									$shortcode_tax[] = $this_term->taxonomy;
								}
							}
						}
					}
					$post_ids             = ic_get_current_products( array(), $shortcode_tax, array(), $excluded_terms );
					$category_args['all'] = 1;
				} elseif ( null === $post_ids ) {
					$exclude_tax = array();
					if ( is_product_filter_active( 'product_category' ) ) {
						$exclude_tax[] = $taxonomy;
					}
					$post_ids = ic_get_current_products( array(), $exclude_tax );
				}
					$cache_key = 'ic_catalog_category_filter_' . md5(
						wp_json_encode(
							array(
								'instance'  => $instance,
								'taxonomy'  => $taxonomy,
								'post_ids'  => $post_ids,
								'active'    => get_product_filter_value( 'product_category' ),
								'shortcode' => ! empty( $instance['shortcode_support'] ) ? 1 : 0,
							)
						)
					);
				if ( ! is_product_filter_active( 'product_category' ) ) {
					$form = ic_get_global( $cache_key );
				}
				if ( false === $form || is_product_filter_active( 'product_category' ) ) {

					// Get all terms and save them to cache.
					ic_save_global( 'taxonomy_terms_' . $taxonomy, ic_get_terms( array( 'taxonomy' => $taxonomy ) ) );
					$categories                = ic_catalog_get_current_categories( $taxonomy, apply_filters( 'ic_catalog_category_filter_cat_args', $category_args, $instance ), array(), $post_ids );
					$form                      = '';
					$category_ids              = wp_list_pluck( $categories, 'term_id' );
					$category_elements         = array();
					$dowhile                   = true;
					$i                         = 0;
					$parsed_current_categories = array();
					$show_count_by_default     = apply_filters( 'ic_catalog_category_filter_show_count', true, $instance );
					while ( $dowhile ) {
						if ( ! isset( $categories[ $i ] ) ) {
							$dowhile = false;
						} else {
							$category = $categories[ $i ];
							++$i;
							if ( ! in_array( $category->term_id, $parsed_current_categories, true ) ) {
								$parsed_current_categories[] = $category->term_id;
							}
							if ( ! empty( $category->parent ) && is_numeric( $category->parent ) ) {
								if ( in_array( $category->parent, $category_ids, true ) ) {
									continue;
								}

								$parent_category = get_term( $category->parent );
								if ( ! empty( $parent_category ) && ! is_wp_error( $parent_category ) ) {
									$categories[]   = $parent_category;
									$category_ids[] = $category->parent;
								}
								continue;
							}
							// Category filter elements are built in the final categories loop below.
						}
					}
					foreach ( $categories as $category ) {
						if ( empty( $category->parent ) && ! empty( $category->name ) ) {
							$category_elements[ $category->name ] = apply_filters( 'ic_catalog_category_filter_parent', get_product_category_filter_element( $category, $post_ids, true, $show_count_by_default ), $category, $post_ids, $instance, $parsed_current_categories, $category_ids );
						}
					}
					if ( ! empty( $category_elements ) ) {
						if ( apply_filters( 'ic_catalog_category_filter_sort', true ) ) {
							ksort( $category_elements );
						}
						$form .= apply_filters( 'ic_catalog_category_filter_elements_ready', implode( '', $category_elements ) );
					}

					if ( is_product_filter_active( 'product_category' ) ) {
						$class       .= ' filter-active';
						$filter_value = get_product_filter_value( 'product_category' );
						if ( is_numeric( $filter_value ) ) {
							$children    = ic_catalog_get_categories( $filter_value );
							$parent_term = get_term_by( 'id', $filter_value, $taxonomy );
							if ( ! empty( $parent_term->parent ) ) {
								$form .= get_product_category_filter_element( $parent_term, $post_ids );
							}
							if ( is_array( $children ) ) {
								foreach ( $children as $child ) {
									$child_form .= get_product_category_filter_element( $child, $post_ids );
								}
							}
						}
					} else {
						ic_save_global( $cache_key, $form );
					}
				}
				if ( ! is_ic_ajax() && empty( $form ) && empty( $child_form ) ) {
					$args['before_widget'] = str_replace( 'class="', 'class="ic-empty-filter ', $args['before_widget'] );
				}
				if ( ! is_ic_ajax() || ! empty( $form ) || ! empty( $child_form ) ) {
					$child_form = apply_filters( 'ic_catalog_category_filter_child_form', $child_form, $instance );
					if ( isset( $args['before_widget'] ) ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget wrapper HTML is provided by WordPress/theme.
						echo $args['before_widget'];
					}
					if ( $title ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Wrapper HTML comes from the theme (or is kses-filtered on the Ajax path); the title itself is escaped.
						echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
					}
					echo '<div class="' . esc_attr( $class ) . ' ic_ajax" data-ic_responsive_label="' . esc_attr__( 'Category', 'post-type-x' ) . '" data-ic_ajax="product-category-filter-container" data-ic_ajax_data="' . esc_attr(
						wp_json_encode(
							array(
								'instance' => $instance,
								'args'     => $args,
							)
						)
					) . '">';
					if ( ! empty( $form ) ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Filter form HTML is assembled from escaped filter elements.
						echo apply_filters( 'ic_catalog_category_filter_form', $form, $instance );
					}
					if ( ! empty( $child_form ) ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Child filter HTML is assembled from escaped filter elements.
						echo '<div class="child-category-filters">' . $child_form . '</div>';
					}
					echo '</div>';
					if ( isset( $args['after_widget'] ) ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget wrapper HTML is provided by WordPress/theme.
						echo $args['after_widget'];
					}
				}
			}
		}
	}

	/**
	 * Outputs the widget admin form.
	 *
	 * @param array $instance Widget instance.
	 *
	 * @return void
	 */
	public function form( $instance ) {
		if ( 'simple' !== get_integration_type() ) {
			$instance = wp_parse_args(
				(array) $instance,
				array(
					'title'             => '',
					'shortcode_support' => 0,
				)
			);
			$title    = $instance['title'];
			?>
				<p>
					<label
							for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'post-type-x' ); ?>
						<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
								name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text"
								value="<?php echo esc_attr( $title ); ?>"/></label></p>
				<p><input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'shortcode_support' ) ); ?>"
							name="<?php echo esc_attr( $this->get_field_name( 'shortcode_support' ) ); ?>" type="checkbox"
							value="1" <?php checked( 1, $instance['shortcode_support'] ); ?> /> <label
							for="<?php echo esc_attr( $this->get_field_id( 'shortcode_support' ) ); ?>"><?php esc_html_e( 'Enable also for shortcodes', 'post-type-x' ); ?></label>
				</p>
				<?php
				do_action( 'ic_catalog_category_filter_settings', $this, $instance );
		} else {
			// Category filter is disabled in simple mode due to the missing main listing page.
			IC_Catalog_Notices::simple_mode_notice();
		}
	}

	/**
	 * Sanitizes widget options on save.
	 *
	 * @param array $new_instance New widget values.
	 * @param array $old_instance Previous widget values.
	 *
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance                      = $old_instance;
		$new_instance                  = wp_parse_args(
			(array) $new_instance,
			array(
				'title'             => '',
				'shortcode_support' => 0,
			)
		);
		$instance['title']             = wp_strip_all_tags( $new_instance['title'] );
		$instance['shortcode_support'] = intval( $new_instance['shortcode_support'] );

		return apply_filters( 'ic_catalog_category_filter_settings_save', $instance, $new_instance );
	}
}
