<?php
/**
 * Product sort filter widget class.
 *
 * @package ecommerce-product-catalog/includes/widgets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Sort filter widget.
 */
class Product_Sort_Filter extends WP_Widget {

	/**
	 * Sets up the widget.
	 *
	 * @return void
	 */
	public function __construct() {
		if ( is_plural_form_active() ) {
			$names = get_catalog_names();
			/* translators: %s: singular catalog item label. */
			$label = sprintf( __( '%s Sort', 'post-type-x' ), ic_ucfirst( $names['singular'] ) );
			/* translators: %s: plural catalog item label. */
			$sublabel = sprintf( __( 'Sort %s dropdown.', 'post-type-x' ), ic_lcfirst( $names['plural'] ) );
		} else {
			$label    = __( 'Catalog Sort', 'post-type-x' );
			$sublabel = __( 'Sort catalog items dropdown.', 'post-type-x' );
		}
		$widget_ops = array(
			'classname'             => 'product_sort_filter',
			'description'           => $sublabel,
			'show_instance_in_rest' => true,
		);
		parent::__construct( 'product_sort_filter', $label, $widget_ops );
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
				$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? '' : $instance['title'], $instance, $this->id_base );

				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget wrapper HTML is provided by WordPress/theme.
				echo $args['before_widget'];
				if ( $title ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Wrapper HTML comes from the theme; the title itself is escaped.
					echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
				}

				// Use current theme search form if it exists.
				show_product_order_dropdown( null, null, $instance );
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget wrapper HTML is provided by WordPress/theme.
				echo $args['after_widget'];
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
		} else {
			// Sort widget is disabled in simple mode due to the missing main listing page.
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

		return $instance;
	}
}

if ( ! class_exists( 'product_sort_filter', false ) ) {
	class_alias( 'Product_Sort_Filter', 'product_sort_filter' );
}
