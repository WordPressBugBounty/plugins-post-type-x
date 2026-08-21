<?php
/**
 * Shared helper-box renderer.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Renders helper boxes used on settings screens.
 */
class IC_Settings_Helper_Box {
	/**
	 * Helper box configuration.
	 *
	 * @var array
	 */
	private $args;

	/**
	 * Initializes the helper box.
	 *
	 * @param array $args Helper box configuration.
	 */
	public function __construct( $args = array() ) {
		$defaults   = array(
			'box_class'            => 'doc-helper',
			'box_type_class'       => '',
			'item_class'           => 'doc-item',
			'title_class'          => 'doc-name green-box',
			'description_class'    => 'doc-description',
			'button_wrapper_class' => 'doc-button',
			'button_input_class'   => 'doc_button classic-button',
			'background_class'     => 'background-url',
			'title'                => '',
			'descriptions'         => array(),
			'content_html'         => '',
			'content_callback'     => '',
			'content_callback_args'=> array(),
			'button_label'         => '',
			'button_url'           => '',
			'button_target'        => '',
			'background_url'       => '',
			'background_title'     => '',
			'tabs'                 => array(),
			'submenus'             => array(),
		);
		$this->args = wp_parse_args( is_array( $args ) ? $args : array(), $defaults );
	}

	/**
	 * Renders the helper box.
	 *
	 * @return void
	 */
	public function render( $context = array() ) {
		if ( ! $this->should_render( $context ) ) {
			return;
		}
		
		?>
		<div class="<?php echo esc_attr( $this->box_class() ); ?>">
			<div class="<?php echo esc_attr( $this->args['item_class'] ); ?>">
				<?php $this->render_title(); ?>
				<?php $this->render_descriptions(); ?>
				<?php $this->render_content(); ?>
				<?php $this->render_button(); ?>
				<?php $this->render_background_link(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Returns true when the helper should render in the current context.
	 *
	 * @param array $context Render context.
	 *
	 * @return bool
	 */
	private function should_render( $context ) {
		$context = $this->render_context( $context );
		if ( ! $this->matches_context_value( $this->args['tabs'], $context['tab'] ) ) {
			return false;
		}

		return $this->matches_context_value( $this->args['submenus'], $context['submenu'] );
	}

	/**
	 * Returns the normalized render context.
	 *
	 * @param array $context Render context.
	 *
	 * @return array
	 */
	private function render_context( $context ) {
		$context = is_array( $context ) ? $context : array();

		if ( ! isset( $context['tab'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing check.
			$context['tab'] = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		}

		if ( ! isset( $context['submenu'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing check.
			$context['submenu'] = isset( $_GET['submenu'] ) ? sanitize_key( wp_unslash( $_GET['submenu'] ) ) : '';
		}

		return $context;
	}

	/**
	 * Returns true when the requested value matches the configured context values.
	 *
	 * @param string|array $configured Configured context values.
	 * @param string       $current Current context value.
	 *
	 * @return bool
	 */
	private function matches_context_value( $configured, $current ) {
		if ( empty( $configured ) ) {
			return true;
		}

		$values = is_array( $configured ) ? $configured : array( $configured );
		$values = array_filter( array_map( 'sanitize_key', $values ) );

		if ( empty( $values ) ) {
			return true;
		}

		return in_array( sanitize_key( (string) $current ), $values, true );
	}

	/**
	 * Returns the normalized helper box class string.
	 *
	 * @return string
	 */
	private function box_class() {
		$classes = array( $this->args['box_class'] );

		if ( ! empty( $this->args['box_type_class'] ) ) {
			$classes[] = $this->args['box_type_class'];
		}

		return trim( implode( ' ', array_filter( $classes ) ) );
	}

	/**
	 * Renders the helper box title.
	 *
	 * @return void
	 */
	private function render_title() {
		if ( empty( $this->args['title'] ) ) {
			return;
		}
		?>
		<div class="<?php echo esc_attr( $this->args['title_class'] ); ?>"><?php echo wp_kses_post( $this->args['title'] ); ?></div>
		<?php
	}

	/**
	 * Renders helper box descriptions.
	 *
	 * @return void
	 */
	private function render_descriptions() {
		foreach ( $this->descriptions() as $description ) {
			?>
			<div class="<?php echo esc_attr( $this->args['description_class'] ); ?>"><?php echo wp_kses_post( $description ); ?></div>
			<?php
		}
	}

	/**
	 * Returns normalized descriptions.
	 *
	 * @return array
	 */
	private function descriptions() {
		if ( empty( $this->args['descriptions'] ) ) {
			return array();
		}

		return is_array( $this->args['descriptions'] ) ? $this->args['descriptions'] : array( $this->args['descriptions'] );
	}

	/**
	 * Renders additional box content.
	 *
	 * @return void
	 */
	private function render_content() {
		if ( ! empty( $this->args['content_html'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted admin markup passed by the caller.
			echo $this->args['content_html'];
		}

		if ( ! empty( $this->args['content_callback'] ) && is_callable( $this->args['content_callback'] ) ) {
			call_user_func_array( $this->args['content_callback'], $this->content_callback_args() );
		}
	}

	/**
	 * Returns normalized content callback arguments.
	 *
	 * @return array
	 */
	private function content_callback_args() {
		return is_array( $this->args['content_callback_args'] ) ? $this->args['content_callback_args'] : array( $this->args['content_callback_args'] );
	}

	/**
	 * Renders the helper box button.
	 *
	 * @return void
	 */
	private function render_button() {
		if ( empty( $this->args['button_label'] ) || empty( $this->args['button_url'] ) ) {
			return;
		}
		?>
		<div class="<?php echo esc_attr( $this->args['button_wrapper_class'] ); ?>">
			<a href="<?php echo esc_url( $this->args['button_url'] ); ?>"<?php $this->render_button_target(); ?>>
				<input class="<?php echo esc_attr( $this->args['button_input_class'] ); ?>" type="button" value="<?php echo esc_attr( $this->args['button_label'] ); ?>">
			</a>
		</div>
		<?php
	}

	/**
	 * Renders the helper box background link.
	 *
	 * @return void
	 */
	private function render_background_link() {
		if ( empty( $this->args['background_url'] ) ) {
			return;
		}
		?>
		<a title="<?php echo esc_attr( $this->args['background_title'] ); ?>" href="<?php echo esc_url( $this->args['background_url'] ); ?>" class="<?php echo esc_attr( $this->args['background_class'] ); ?>"></a>
		<?php
	}

	/**
	 * Renders the button target attribute when configured.
	 *
	 * @return void
	 */
	private function render_button_target() {
		if ( empty( $this->args['button_target'] ) ) {
			return;
		}
		?>
		 target="<?php echo esc_attr( $this->args['button_target'] ); ?>"
		<?php
	}
}
