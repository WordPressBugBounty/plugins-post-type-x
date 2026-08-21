<?php
/**
 * Shared settings section wrapper.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Renders a titled settings section with an optional documentation link.
 */
class IC_Settings_Section {
	/**
	 * Section configuration.
	 *
	 * @var array
	 */
	private $args;

	/**
	 * Initializes the settings section.
	 *
	 * @param array $args Section configuration.
	 */
	public function __construct( $args = array() ) {
		$defaults   = array(
			'title'             => '',
			'documentation_url' => '',
			'description'       => '',
			'settings'          => array(),
			'table_class'       => 'IC_Settings_Section_Table',
			'table_args'        => array(),
			'content_callback'  => null,
			'content_args'      => array(),
			'container_class'   => 'ic-settings-section',
			'after_actions'     => array(),
		);
		$this->args = wp_parse_args( is_array( $args ) ? $args : array(), $defaults );
	}
	/**
	 * Renders the section.
	 *
	 * @return void
	 */
	public function render() {
		if ( empty( $this->args['title'] ) ) {
			return;
		}

		$this->styles();
		?>
		<div class="<?php echo esc_attr( $this->args['container_class'] ); ?>">
			<h3>
				<?php echo esc_html( $this->args['title'] ); ?>
				<?php $this->documentation_link(); ?>
			</h3>
			<?php if ( '' !== $this->args['description'] ) : ?>
				<p class="description ic-settings-section-description"><?php echo esc_html( $this->args['description'] ); ?></p>
			<?php endif; ?>
			<?php
			$this->content();
			$this->after_actions();
			?>
		</div>
		
			<?php
	}

	/**
	 * Prints the shared settings section styles once per request.
	 *
	 * @return void
	 */
	private function styles() {
		static $printed = false;

		if ( $printed ) {
			return;
		}

		$printed = true;
		?>
		<style>
			.ic-settings-section .ic-settings-section-description {
				max-width: 960px;
				margin: -4px 0 16px;
				padding: 10px 12px;
				border-left: 4px solid #72aee6;
				background: #f6f7f7;
				color: #3c434a;
				line-height: 1.55;
			}
		</style>
		<?php
	}

	/**
	 * Renders the configured content.
	 *
	 * @return void
	 */
	private function content() {
		if ( is_callable( $this->args['content_callback'] ) ) {
			call_user_func( $this->args['content_callback'], $this->args['settings'], $this->args['content_args'], $this );

			return;
		}

		$table = $this->table();
		if ( $table && method_exists( $table, 'render' ) ) {
			$table->render();
		}
	}

	/**
	 * Creates the configured table renderer.
	 *
	 * @return object|null
	 */
	private function table() {
		$table_class = $this->args['table_class'];
		if ( empty( $table_class ) || ! class_exists( $table_class ) ) {
			return null;
		}

		$table_args = is_array( $this->args['table_args'] ) ? $this->args['table_args'] : array();
		if ( ! isset( $table_args['settings'] ) ) {
			$table_args['settings'] = $this->args['settings'];
		}

		return new $table_class( $table_args );
	}

	/**
	 * Fires configured actions after the section content.
	 *
	 * @return void
	 */
	private function after_actions() {

		if ( empty( $this->args['after_actions'] ) || ! is_array( $this->args['after_actions'] ) ) {
				return;
		}

		foreach ( $this->args['after_actions'] as $action ) {
			$action = wp_parse_args(
				is_array( $action ) ? $action : array(),
				array(
					'action' => '',
					'args'   => array(),
				)
			);

			if ( empty( $action['action'] ) ) {
				continue;
			}

			$action['args'] = is_array( $action['args'] ) ? $action['args'] : array();
			do_action( 'ic_settings_section_after_action', $action, $this->args['settings'], $this );
		}
	}
	/**
	 * Renders the documentation link next to the title.
	 *
	 * @return void
	 */
	private function documentation_link() {
		if ( empty( $this->args['documentation_url'] ) ) {
			return;
		}

		/* translators: %s: section title. */
		$label = sprintf( __( 'Open documentation for %s', 'post-type-x' ), $this->args['title'] );
		?>
		<a class="ic-settings-section-doc-link" href="<?php echo esc_url( $this->args['documentation_url'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $label ); ?>" title="<?php echo esc_attr( $label ); ?>">?</a>
		<?php
	}
}
