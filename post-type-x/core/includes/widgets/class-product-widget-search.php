<?php
/**
 * Product search widget class.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Product search widget.
 */
class Product_Widget_Search extends WP_Widget {

	/**
	 * Sets up the search widget.
	 */
	public function __construct() {
		if ( is_plural_form_active() ) {
			$names = get_catalog_names();
			/* translators: %s: singular catalog label. */
			$label = sprintf( __( '%s Search', 'post-type-x' ), ic_ucfirst( $names['singular'] ) );
			/* translators: %s: plural catalog label. */
			$sublabel = sprintf( __( 'A search form for your %s.', 'post-type-x' ), ic_lcfirst( $names['plural'] ) );
		} else {
			$label    = __( 'Product Search', 'post-type-x' );
			$sublabel = __( 'A search form for your catalog items.', 'post-type-x' );
		}

		$widget_ops = array(
			'classname'             => 'product_search search widget_search',
			'description'           => $sublabel,
			'show_instance_in_rest' => true,
		);

		parent::__construct( 'product_search', $label, $widget_ops );
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

			if ( ! isset( $instance['title'] ) ) {
				$instance['title'] = '';
			}

			$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? '' : $instance['title'], $instance, $this->id_base );

			if ( ! empty( $args['before_widget'] ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget wrapper HTML is provided by WordPress/theme.
				echo $args['before_widget'];
			}

			if ( $title ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Wrapper HTML comes from the theme; the title itself is escaped.
				echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
			}

			ic_save_global( 'search_widget_instance', $instance );
			add_filter( 'ic_search_box_class', array( __CLASS__, 'box_class' ) );
			ic_show_search_widget_form();

			if ( ! empty( $args['after_widget'] ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget wrapper HTML is provided by WordPress/theme.
				echo $args['after_widget'];
			}
		}
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
			$instance = wp_parse_args( (array) $instance, array( 'title' => '' ) );
			$title    = $instance['title'];
			?>
			<p><label
					for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'post-type-x' ); ?>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
						name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text"
						value="<?php echo esc_attr( $title ); ?>"/></label></p>
						<?php
						do_action( 'product_search_widget_admin_form', $instance, $this );
		} else {
			IC_Catalog_Notices::simple_mode_notice();
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
		$instance          = $old_instance;
		$new_instance      = wp_parse_args( (array) $new_instance, array( 'title' => '' ) );
		$instance['title'] = wp_strip_all_tags( $new_instance['title'] );

		return apply_filters( 'product_search_widget_admin_save', $instance, $new_instance );
	}

	/**
	 * Adds the catalog box design class to the search widget.
	 *
	 * @param string $classes Existing CSS classes.
	 *
	 * @return string
	 */
	public static function box_class( $classes ) {
		if ( ! empty( $classes ) ) {
			$classes .= ' ';
		}

		$classes .= design_schemes( 'box', 0 );

		return $classes;
	}
}

if ( ! class_exists( 'product_widget_search', false ) ) {
	class_alias( 'Product_Widget_Search', 'product_widget_search' );
}
