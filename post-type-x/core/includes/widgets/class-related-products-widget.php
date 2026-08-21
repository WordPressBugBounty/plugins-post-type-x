<?php
/**
 * Related products widget class.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Related products widget.
 */
class Related_Products_Widget extends WP_Widget {

	/**
	 * Sets up the related products widget.
	 */
	public function __construct() {
		if ( is_plural_form_active() ) {
			$names = get_catalog_names();
			/* translators: %s: plural catalog label. */
			$label = sprintf( __( 'Related %s', 'post-type-x' ), ic_ucfirst( $names['plural'] ) );
			/* translators: %s: plural catalog label. */
			$sublabel = sprintf( __( 'Shows related %s.', 'post-type-x' ), ic_lcfirst( $names['plural'] ) );
		} else {
			$label    = __( 'Related Catalog Items', 'post-type-x' );
			$sublabel = __( 'Shows related catalog items.', 'post-type-x' );
		}

		$widget_ops = array(
			'classname'             => 'related_products_widget',
			'description'           => $sublabel,
			'show_instance_in_rest' => true,
		);

		parent::__construct( 'related_products_widget', $label, $widget_ops );
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
		$title      = apply_filters( 'widget_title', empty( $instance['title'] ) ? '' : $instance['title'], $instance, $this->id_base );
		$product_id = isset( $instance['selectedProduct'] ) ? intval( $instance['selectedProduct'] ) : '';

		if ( empty( $product_id ) && ! is_ic_product_page() ) {
			return;
		}

		$related = get_related_products( null, false, $product_id );

		if ( ! empty( $related ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget wrapper HTML is provided by WordPress/theme.
			echo $args['before_widget'];

			if ( $title ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Wrapper HTML comes from the theme; the title itself is escaped.
				echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
			}

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Related products HTML is generated upstream.
			echo $related;

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget wrapper HTML is provided by WordPress/theme.
			echo $args['after_widget'];
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

		return $instance;
	}
}

