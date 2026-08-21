<?php
/**
 * Catalog widget base class.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Base widget class used by the catalog widgets.
 */
abstract class IC_Catalog_Widget extends WP_Widget {

	/**
	 * Widget base name.
	 *
	 * @var string
	 */
	private $ic_name;

	/**
	 * Widget label.
	 *
	 * @var string
	 */
	private $ic_label;

	/**
	 * Widget description.
	 *
	 * @var string
	 */
	private $ic_description;

	/**
	 * Ajax filter name used for widget refresh callbacks.
	 *
	 * @var string|null
	 */
	private $widget_filter_name;

	/**
	 * HTML helper instance.
	 *
	 * @var IC_Html_Util
	 */
	public $html;

	/**
	 * Initializes the widget bootstrap data.
	 *
	 * @param string      $name        Widget base name.
	 * @param string      $label       Widget label.
	 * @param string      $description Widget description.
	 * @param string|null $filter_name Optional Ajax filter name.
	 */
	public function __construct( $name, $label, $description, $filter_name = null ) {
		$this->ic_name            = $name;
		$this->ic_label           = $label;
		$this->ic_description     = $description;
		$this->widget_filter_name = $filter_name;
		if ( current_filter() === 'implecode_register_widgets' ) {
			$this->init();
		} else {
			add_action( 'implecode_register_widgets', array( $this, 'register' ) );
		}
	}

	/**
	 * The widget front-end display.
	 *
	 * @param array $instance Widget instance settings.
	 * @param array $args     Widget display arguments.
	 *
	 * @return void
	 */
	public function front( $instance, $args ) {
	}

	/**
	 * The widget default settings.
	 *
	 * @return array
	 */
	abstract public function default_settings();

	/**
	 * Settings rows array for the widget.
	 *
	 * @param array $instance Widget instance settings.
	 *
	 * @return array
	 */
	abstract public function settings_rows( $instance );

	/**
	 * Registers the widget internals.
	 *
	 * @return void
	 */
	public function init() {
		if ( empty( $this->ic_name ) || empty( $this->ic_label ) || empty( $this->ic_description ) ) {
			return;
		}
		$this->html         = new IC_Html_Util();
		$this->html->fix_id = false;
		$widget_ops         = array(
			'classname'   => $this->ic_name,
			'description' => $this->ic_description,
		);
		parent::__construct( $this->ic_name, $this->ic_label, $widget_ops );
		if ( ! empty( $this->widget_filter_name ) ) {
			add_filter( 'ic_ajax_self_submit_return', array( $this, 'ajax' ) );
		}
		$this->additional();
	}

	/**
	 * Refreshes widget output during the catalog Ajax request.
	 *
	 * @param array $response Current Ajax response payload.
	 *
	 * @return array
	 */
	public function ajax( $response ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- IC_Catalog_Ajax::ajax_self_submit() verifies the ic_ajax nonce before this filter callback runs.
		if ( ! empty( $_POST['ajax_elements'][ $this->widget_filter_name ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is verified by the parent Ajax handler; the payload is sanitized by ic_sanitize_widget_ajax_settings() on the next line.
			$ajax_element = wp_unslash( $_POST['ajax_elements'][ $this->widget_filter_name ] );
			if ( ! is_array( $ajax_element ) ) {
				return $response;
			}
			$ajax_element = ic_sanitize_widget_ajax_settings( $ajax_element );
			ob_start();
			the_widget( $this->ic_name, $ajax_element['instance'], $ajax_element['args'] );
			$response[ $this->widget_filter_name ] = ob_get_clean();
		}

		return $response;
	}

	/**
	 * Does nothing unless extended.
	 *
	 * @return void
	 */
	public function additional() {
	}

	/**
	 * Registers the widget with WordPress.
	 *
	 * @return void
	 */
	public function register() {
		register_widget( $this->ic_name );
	}

	/**
	 * Renders widget output on the front end.
	 *
	 * @param array $args     Widget display arguments.
	 * @param array $instance Widget instance settings.
	 *
	 * @return void
	 */
	public function widget( $args, $instance ) {
		ob_start();
		$this->front( $instance, $args );
		$front = ob_get_clean();

		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? '' : $instance['title'], $instance, $this->id_base );

		$before_widget = $this->before_widget( $args );
		if ( empty( $front ) && ! ic_string_contains( $before_widget, 'ic-empty-filter' ) ) {
			$before_widget = str_replace( 'class="', 'class="ic-empty-filter ', $before_widget );
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget wrapper HTML is provided by WordPress and the active theme.
		echo $before_widget;
		if ( $title ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget title markup is filtered by WordPress and rendered as trusted widget HTML.
			echo $args['before_title'] . $title . $args['after_title'];
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget content is generated by the widget front-end renderer.
		echo $front;
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget wrapper HTML is provided by WordPress and the active theme.
		echo $args['after_widget'];
	}

	/**
	 * Returns the widget opening wrapper.
	 *
	 * @param array $args Widget display arguments.
	 *
	 * @return string
	 */
	public function before_widget( $args ) {
		return $args['before_widget'];
	}

	/**
	 * Outputs a single widget settings row.
	 *
	 * @param string $label   Field label.
	 * @param string $name    Field name.
	 * @param mixed  $value   Field value.
	 * @param string $type    Field type.
	 * @param array  $options Field options.
	 *
	 * @return void
	 */
	public function form_row( $label, $name, $value, $type = 'text', $options = array() ) {
		$id   = $this->get_field_id( $name );
		$name = $this->get_field_name( $name );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped by the widget HTML helper before output.
		echo $this->html->input( $type, $name, $value, $id, 0, $label, null, true, $options, array(), false );
	}

	/**
	 * Outputs the widget settings form.
	 *
	 * @param array $instance Widget instance settings.
	 *
	 * @return void
	 */
	public function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, $this->defaults() );
		$title    = $instance['title'];
		$this->form_row( __( 'Title:', 'post-type-x' ), 'title', $title );
		$settings = $this->settings_rows( $instance );
		foreach ( $settings as $name => $attr ) {
			$attr['type']    = empty( $attr['type'] ) ? 'text' : $attr['type'];
			$attr['label']   = isset( $attr['label'] ) ? $attr['label'] : '';
			$attr['value']   = isset( $attr['value'] ) ? $attr['value'] : '';
			$attr['options'] = isset( $attr['options'] ) ? $attr['options'] : array();
			$this->form_row( $attr['label'], $name, $attr['value'], $attr['type'], $attr['options'] );
		}
	}

	/**
	 * Normalizes widget settings before saving.
	 *
	 * @param array $new_instance New widget settings.
	 * @param array $old_instance Previous widget settings.
	 *
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		return wp_parse_args( (array) $new_instance, $this->defaults() );
	}

	/**
	 * Returns the widget default settings.
	 *
	 * @return array
	 */
	public function defaults() {
		return array_merge( array( 'title' => '' ), $this->default_settings() );
	}
}

if ( ! class_exists( 'ic_catalog_widget', false ) ) {
	class_alias( 'IC_Catalog_Widget', 'ic_catalog_widget' );
}
