<?php
/**
 * Shared design settings table renderer.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Renders design selection tables with previews and additional settings areas.
 */
class IC_Settings_Design_Table {
	/**
	 * Table configuration.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Initializes the design table renderer.
	 *
	 * @param array $args Table configuration.
	 */
	public function __construct( $args = array() ) {
		$defaults = array(
			'settings' => array(),
		);
		$args     = wp_parse_args( is_array( $args ) ? $args : array(), $defaults );
		$settings = is_array( $args['settings'] ) ? $args['settings'] : array();

		$this->settings = wp_parse_args(
			$settings,
			array(
				'input_name'                => '',
				'selected'                  => '',
				'setting_label'             => '',
				'table_class'               => 'design-table',
				'designs'                   => array(),
				'additional_settings_label' => __( 'Additional Settings', 'post-type-x' ),
				'rows_after_callback'       => null,
				'after_table_callback'      => null,
			)
		);
	}

	/**
	 * Renders the table output.
	 *
	 * @return void
	 */
	public function render() {
		$designs = $this->normalize_designs( $this->settings['designs'] );

		if ( empty( $this->settings['input_name'] ) || empty( $designs ) ) {
			return;
		}

		if ( '' !== $this->settings['setting_label'] ) {
			ic_register_setting( $this->settings['setting_label'], $this->settings['input_name'] );
		}
		?>
		<table class="<?php echo esc_attr( $this->settings['table_class'] ); ?>">
			<thead></thead>
			<tbody>
				<?php foreach ( $designs as $index => $design ) : ?>
					<?php $this->render_design_rows( $design ); ?>
					<?php if ( $index + 1 < count( $designs ) ) : ?>
						<tr><td colspan="2" class="separator"></td></tr>
					<?php endif; ?>
				<?php endforeach; ?>
				<?php $this->render_callback( $this->settings['rows_after_callback'], $this->settings ); ?>
			</tbody>
		</table>
		<?php
		$this->render_callback( $this->settings['after_table_callback'], $this->settings );
	}

	/**
	 * Renders one design option.
	 *
	 * @param array $design Normalized design config.
	 *
	 * @return void
	 */
	private function render_design_rows( $design ) {
		?>
		<tr<?php echo '' !== $design['row_id'] ? ' id="' . esc_attr( $design['row_id'] ) . '"' : ''; ?>>
			<td class="<?php echo esc_attr( $design['name_cell_class'] ); ?>">
				<label>
					<input type="radio" name="<?php echo esc_attr( $this->settings['input_name'] ); ?>" value="<?php echo esc_attr( $design['value'] ); ?>" <?php checked( $design['value'], $this->settings['selected'] ); ?>><?php echo esc_html( $design['label'] ); ?>
				</label>
			</td>
			<td rowspan="2" class="<?php echo esc_attr( $design['preview_cell_class'] ); ?>"><?php $this->render_callback( $design['preview_callback'], $design ); ?></td>
		</tr>
		<tr>
			<td class="<?php echo esc_attr( $design['settings_cell_class'] ); ?>">
				<?php if ( '' !== $design['additional_settings_label'] ) : ?>
					<strong><?php echo esc_html( $design['additional_settings_label'] ); ?></strong><br>
				<?php endif; ?>
				<?php $this->render_design_settings( $design['settings'], $design ); ?>
				<?php $this->render_callback( $design['settings_callback'], $design ); ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Renders generated design settings before the compatibility callback.
	 *
	 * @param array $settings Normalized settings definitions.
	 * @param array $design Normalized design definition.
	 *
	 * @return void
	 */
	private function render_design_settings( $settings, $design ) {
		$settings = apply_filters( 'ic_settings_design_table_render_design_settings', $settings, $design, $this->settings, $this );
		$settings = $this->normalize_settings( $settings );

		if ( empty( $settings ) ) {
			return;
		}
		?>
		<table class="ic-settings-design-settings">
			<tbody>
		<?php
		foreach ( $settings as $setting ) {
			if ( ! empty( $setting['callback'] ) && is_callable( $setting['callback'] ) ) {
				call_user_func( $setting['callback'], $setting, $this->settings, $this );
				continue;
			}

			if ( 'html' === $setting['type'] ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Custom design setting HTML is trusted admin markup provided by framework/plugin settings definitions.
				echo $setting['html'];
				continue;
			}

			$this->render_design_setting( $setting );
		}
		?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Renders one generated design setting.
	 *
	 * @param array $setting Normalized setting definition.
	 *
	 * @return void
	 */
	private function render_design_setting( $setting ) {
		switch ( $setting['type'] ) {
			case 'checkbox':
				implecode_settings_checkbox(
					$setting['label'],
					$setting['name'],
					$setting['value'],
					1,
					$setting['tip'],
					$setting['checked_value'],
					$setting['class']
				);
				break;
			case 'dropdown':
				implecode_settings_dropdown(
					$setting['label'],
					$setting['name'],
					$setting['value'],
					$setting['options'],
					1,
					$setting['attributes'],
					$setting['tip']
				);
				break;
			case 'number':
				implecode_settings_number(
					$setting['label'],
					$setting['name'],
					$setting['value'],
					$setting['unit'],
					1,
					$setting['step'],
					$setting['tip'],
					$setting['min'],
					$setting['max'],
					$setting['class'],
					null,
					$setting['attributes']
				);
				break;
			case 'text':
			default:
				implecode_settings_text(
					$setting['label'],
					$setting['name'],
					$setting['value'],
					$setting['required'],
					1,
					$setting['class'],
					$setting['tip'],
					$setting['disabled'],
					$setting['attributes']
				);
				break;
		}

		if ( '' !== $setting['line'] && '' === $setting['label'] ) {
			echo $setting['line']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Line separator is trusted admin markup from settings definitions.
		}
	}

	/**
	 * Renders a configured callback.
	 *
	 * @param callable|null $callback Callback to render.
	 * @param array         $design Callback context.
	 *
	 * @return void
	 */
	private function render_callback( $callback, $design = array() ) {
		if ( ! is_callable( $callback ) ) {
			return;
		}

		call_user_func( $callback, $design, $this->settings, $this );
	}

	/**
	 * Normalizes design definitions.
	 *
	 * @param array $designs Raw design config.
	 *
	 * @return array
	 */
	private function normalize_designs( $designs ) {
		$normalized = array();

		if ( ! is_array( $designs ) ) {
			return $normalized;
		}

		foreach ( $designs as $design ) {
			if ( ! is_array( $design ) || empty( $design['value'] ) || empty( $design['label'] ) ) {
				continue;
			}

			$design['settings'] = $this->normalize_settings( isset( $design['settings'] ) ? $design['settings'] : array() );

			$normalized[] = wp_parse_args(
				$design,
				array(
					'row_id'                    => '',
					'label'                     => '',
					'value'                     => '',
					'preview_callback'          => null,
					'settings_callback'         => null,
					'settings'                  => array(),
					'additional_settings_label' => $this->settings['additional_settings_label'],
					'name_cell_class'           => 'with-additional-styling theme-name',
					'preview_cell_class'        => 'theme-example',
					'settings_cell_class'       => 'additional-styling',
				)
			);
		}

		return $normalized;
	}

	/**
	 * Normalizes generated design settings.
	 *
	 * @param array $settings Raw setting config.
	 *
	 * @return array
	 */
	private function normalize_settings( $settings ) {
		$normalized = array();

		if ( ! is_array( $settings ) ) {
			return $normalized;
		}

		foreach ( $settings as $setting ) {
			if ( ! is_array( $setting ) ) {
				continue;
			}

			if ( isset( $setting['callback'] ) && is_callable( $setting['callback'] ) ) {
				$normalized[] = $setting;
				continue;
			}

			if ( isset( $setting['type'] ) && 'html' === $setting['type'] ) {
				$normalized[] = wp_parse_args(
					$setting,
					array(
						'type' => 'html',
						'html' => '',
					)
				);
				continue;
			}

			if ( empty( $setting['name'] ) ) {
				continue;
			}

			$setting = wp_parse_args(
				$setting,
				array(
					'type'          => 'text',
					'label'         => '',
					'name'          => '',
					'value'         => '',
					'unit'          => '',
					'options'       => array(),
					'tip'           => '',
					'attributes'    => null,
					'required'      => null,
					'class'         => null,
					'disabled'      => '',
					'checked_value' => 1,
					'step'          => 1,
					'min'           => null,
					'max'           => null,
					'line'          => '<br>',
				)
			);

			$normalized[] = $setting;
		}

		return $normalized;
	}
}
