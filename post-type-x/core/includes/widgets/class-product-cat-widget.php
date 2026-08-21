<?php
/**
 * Product category widget class.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Product category widget.
 */
class Product_Cat_Widget extends WP_Widget {

	/**
	 * Sets up the category widget.
	 */
	public function __construct() {
		if ( is_plural_form_active() ) {
			$names = get_catalog_names();
			/* translators: %s: singular catalog label. */
			$label = sprintf( __( '%s Categories', 'post-type-x' ), ic_ucfirst( $names['singular'] ) );
			/* translators: %s: singular catalog label. */
			$sublabel = sprintf( __( 'A list or dropdown of %s categories', 'post-type-x' ), ic_lcfirst( $names['singular'] ) );
		} else {
			$label    = __( 'Catalog Categories', 'post-type-x' );
			$sublabel = __( 'A list or dropdown of catalog categories', 'post-type-x' );
		}

		$widget_ops = array(
			'classname'             => 'widget_product_categories widget_categories',
			'description'           => $sublabel,
			'show_instance_in_rest' => true,
		);

		parent::__construct( 'product_categories', $label, $widget_ops );
	}

	/**
	 * Outputs the widget on the front end.
	 *
	 * @param array $args     Widget wrapper arguments.
	 * @param array $instance Saved widget instance.
	 *
	 * @return void
	 */
	public function widget( $args, $instance ) {
		if ( 'simple' !== get_integration_type() ) {
			ic_enqueue_main_catalog_js_css();
			$instance['title'] = isset( $instance['title'] ) ? $instance['title'] : '';
			$title             = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );
			$c                 = ! empty( $instance['count'] ) ? '1' : '0';
			$h                 = ! empty( $instance['hierarchical'] ) ? '1' : '0';
			$d                 = ! empty( $instance['dropdown'] ) ? '1' : '0';

			do_action( 'ic_before_widget', 'product_cat_widget' );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget wrapper HTML is provided by WordPress/theme.
			echo $args['before_widget'];

			if ( $title ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Wrapper HTML comes from the theme; the title itself is escaped.
				echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
			}

			$taxonomy = get_current_screen_tax();

			if ( is_array( $taxonomy ) ) {
				$taxonomy = 'al_product-cat';
			}

			$cat_args = array(
				'orderby'      => 'name',
				'show_count'   => $c,
				'hierarchical' => $h,
				'taxonomy'     => $taxonomy,
			);

			if ( $d ) {
				if ( is_plural_form_active() ) {
					$names = get_catalog_names();

					if ( is_ic_taxonomy_page() ) {
						/* translators: %s: plural catalog label. */
						$label = sprintf( __( 'Show All %s', 'post-type-x' ), ic_ucfirst( $names['plural'] ) );
					} else {
						/* translators: %s: singular catalog label. */
						$label = sprintf( __( 'Select %s Category', 'post-type-x' ), ic_ucfirst( $names['singular'] ) );
					}
				} elseif ( is_ic_taxonomy_page() ) {
					$label = __( 'Show All', 'post-type-x' );
				} else {
					$label = __( 'Select Category', 'post-type-x' );
				}

				$listing_url = product_listing_url();
				$cat_args    = array(
					'orderby'          => 'name',
					'show_count'       => $c,
					'hierarchical'     => $h,
					'taxonomy'         => $taxonomy,
					'walker'           => new IC_Cat_Walker_CategoryDropdown(),
					'show_option_none' => $label,
					'pad_counts'       => (bool) $c,
					'class'            => 'ic-category-select',
				);

				if ( ! empty( $listing_url ) ) {
					$cat_args['option_none_value'] = $listing_url;
				} else {
					$cat_args['option_none_value'] = 'none';
					$cat_args['show_option_none']  = ' ';
				}

				$cat_args = apply_filters( 'widget_product_categories_dropdown_args', $cat_args );
				$cat_args = $this->sync_pad_counts( $cat_args, (bool) $c );
				wp_dropdown_categories( $cat_args );
			} else {
				$class = 'ic-cat-categories-list';
				?>
				<ul class="<?php echo esc_attr( apply_filters( 'ic_catalog_categories_list_ul', $class, $instance ) ); ?>">
					<?php
					$cat_args['title_li'] = '';

					if ( is_ic_product_page() ) {
						$product_cats = ic_get_product_categories( ic_get_product_id() );

						if ( ! empty( $product_cats ) ) {
							$cat_args['current_category'] = wp_list_pluck( $product_cats, 'term_id' );
						}
					}

					$cat_args['pad_counts'] = (bool) $c;
					$cat_args               = apply_filters( 'widget_product_categories_args', $cat_args, $instance );
					$cat_args               = $this->sync_pad_counts( $cat_args, (bool) $c );

					add_filter( 'category_css_class', array( $this, 'add_category_parent_css' ), 10, 4 );
					wp_list_categories( $cat_args );
					remove_filter( 'category_css_class', array( $this, 'add_category_parent_css' ), 10, 4 );

					do_action( 'ic_categories_list_end', $instance );
					?>
				</ul>
				<?php

				do_action( 'after_product_category_widget', $cat_args, $instance );
			}

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget wrapper HTML is provided by WordPress/theme.
			echo $args['after_widget'];

			do_action( 'ic_after_widget', 'product_cat_widget' );
		}
	}

	/**
	 * Sanitizes widget settings on save.
	 *
	 * @param array $new_instance New widget values.
	 * @param array $old_instance Previous widget values.
	 *
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance                 = $old_instance;
		$instance['title']        = wp_strip_all_tags( $new_instance['title'] );
		$instance['count']        = ! empty( $new_instance['count'] ) ? 1 : 0;
		$instance['hierarchical'] = ! empty( $new_instance['hierarchical'] ) ? 1 : 0;
		$instance['dropdown']     = ! empty( $new_instance['dropdown'] ) ? 1 : 0;
		$instance                 = apply_filters( 'product_category_widget_save_instance', $instance, $new_instance, $old_instance );

		return $instance;
	}

	/**
	 * Outputs the widget admin form.
	 *
	 * @param array $instance Saved widget instance.
	 *
	 * @return void
	 */
	public function form( $instance ) {
		if ( 'simple' !== get_integration_type() ) {
			$instance     = wp_parse_args( (array) $instance, array( 'title' => '' ) );
			$title        = esc_attr( $instance['title'] );
			$count        = isset( $instance['count'] ) ? (bool) $instance['count'] : false;
			$hierarchical = isset( $instance['hierarchical'] ) ? (bool) $instance['hierarchical'] : false;
			$dropdown     = isset( $instance['dropdown'] ) ? (bool) $instance['dropdown'] : false;
			?>
			<p><label
						for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'post-type-x' ); ?></label>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
						name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text"
						value="<?php echo esc_attr( $title ); ?>"/>
			</p>

			<p><input type="checkbox" class="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'dropdown' ) ); ?>"
						name="<?php echo esc_attr( $this->get_field_name( 'dropdown' ) ); ?>"<?php checked( $dropdown ); ?> />
			<label
					for="<?php echo esc_attr( $this->get_field_id( 'dropdown' ) ); ?>"><?php esc_html_e( 'Display as dropdown', 'post-type-x' ); ?></label>
			<br/>

			<input type="checkbox" class="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>"
					name="<?php echo esc_attr( $this->get_field_name( 'count' ) ); ?>"<?php checked( $count ); ?> />
			<label
					for="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>"><?php esc_html_e( 'Show product counts', 'post-type-x' ); ?></label>
			<br/>

			<input type="checkbox" class="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'hierarchical' ) ); ?>"
					name="<?php echo esc_attr( $this->get_field_name( 'hierarchical' ) ); ?>"<?php checked( $hierarchical ); ?> />
			<label
					for="<?php echo esc_attr( $this->get_field_id( 'hierarchical' ) ); ?>"><?php esc_html_e( 'Show hierarchy', 'post-type-x' ); ?></label>
			<?php
			$object = $this;
			do_action( 'product_categories_widget_settings', $instance, $object );
			?>
			</p>
			<?php
		} else {
			IC_Catalog_Notices::simple_mode_notice();
		}
	}

	/**
	 * Adds a helper class when a category has children.
	 *
	 * @param array        $css_classes Existing CSS classes.
	 * @param WP_Term      $category    Current category term.
	 * @param int          $depth       Walker depth.
	 * @param object|array $args        Walker arguments.
	 *
	 * @return array
	 */
	public function add_category_parent_css( $css_classes, $category, $depth, $args ) {
		if ( $args['has_children'] ) {
			$css_classes[] = 'has_children';
		}

		return apply_filters( 'ic_catalog_cat_widget_el_classes', $css_classes, $category, $depth, $args );
	}

	/**
	 * Synchronizes padded counts with the filtered count-display setting.
	 *
	 * An explicit padding value that differs from the widget default is treated
	 * as an intentional filter override.
	 *
	 * @param array $args               Filtered category arguments.
	 * @param bool  $default_pad_counts Widget padding default.
	 * @return array
	 */
	private function sync_pad_counts( $args, $default_pad_counts ) {
		if ( ! array_key_exists( 'pad_counts', $args ) || (bool) $args['pad_counts'] === $default_pad_counts ) {
			$args['pad_counts'] = ! empty( $args['show_count'] );
		}

		return $args;
	}
}
