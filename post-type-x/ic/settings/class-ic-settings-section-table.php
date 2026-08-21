<?php
/**
 * Shared settings section table renderer.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Renders grouped repeatable settings tables.
 */
class IC_Settings_Section_Table {
	/**
	 * Row index placeholder used in field-name templates.
	 *
	 * @var string
	 */
	const INDEX_PLACEHOLDER = '__index__';

	/**
	 * Field name placeholder used in field-name templates.
	 *
	 * @var string
	 */
	const FIELD_PLACEHOLDER = '__field__';

	/**
	 * Table configuration.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Initializes the table renderer.
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
				'groups'                  => array(),
				'selector'                => array(),
				'selector_actions'        => array(),
				'error_class'             => 'ic-settings-section-table-error',
				'group_error_message'     => '',
				'table_type'              => 'repeatable',
				'table_class'             => '',
				'component_class'         => '',
				'wrapper_class'           => '',
				'wrapper_style'           => '',
				'row_class'               => '',
				'invalid_row_class'       => '',
				'columns'                 => array(),
				'rows'                    => array(),
				'registered_settings'     => array(),
				'after_actions'           => array(),
				'new_row_values'          => array(),
				'single_row_reset_values' => array(),
				'range_validation'        => array(),
				'labels'                  => array(),
				'validation_messages'     => array(),
			)
		);
	}

	/**
	 * Renders the table output.
	 *
	 * @return void
	 */
	public function render() {

		if ( 'fields' === $this->settings['table_type'] ) {
			$this->render_fields_table();

			return;
		}

		$columns = $this->normalize_repeatable_columns( $this->settings['columns'] );
		if ( empty( $columns ) ) {
			$columns = $this->columns_from_groups( $this->settings['groups'] );
		}

		$groups = $this->normalize_groups( $this->settings['groups'], $columns );
		if ( empty( $groups ) || empty( $columns ) ) {
			return;
		}

		$selector          = $this->normalize_selector( $this->settings['selector'], $groups );
		$selector_actions  = $this->normalize_selector_actions( $this->settings['selector_actions'] );
		$component_id      = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'ic-settings-section-table-' ) : uniqid( 'ic-settings-section-table-', false );
		$messages          = $this->validation_messages();
		$row_defaults      = $this->row_value_defaults( array(), $columns );
		$new_row_values    = $this->normalize_row_values( $this->settings['new_row_values'], $row_defaults );
		$reset_row_values  = $this->normalize_row_values( $this->settings['single_row_reset_values'], $row_defaults );
		$labels            = $this->labels();
		$range_validation  = $this->range_validation();
		$selected_group_id = $selector['selected'];
		?>
		<div id="<?php echo esc_attr( $component_id ); ?>" class="<?php echo esc_attr( $this->repeatable_component_class() ); ?>">
			<?php if ( ! empty( $selector['options'] ) ) : ?>
				<p>
					<label for="<?php echo esc_attr( $selector['id'] ); ?>"><strong><?php echo esc_html( $selector['label'] ); ?>:</strong></label>
					<select id="<?php echo esc_attr( $selector['id'] ); ?>" class="ic-settings-section-table-selector <?php echo esc_attr( $selector['class'] ); ?>">
						<?php foreach ( $selector['options'] as $option ) : ?>
							<option value="<?php echo esc_attr( $option['value'] ); ?>" <?php selected( $option['value'], $selected_group_id ); ?>><?php echo esc_html( $option['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php foreach ( $selector_actions as $action ) : ?>
						<button type="button"
							class="button button-secondary ic-settings-section-table-selector-action <?php echo esc_attr( $action['class'] ); ?>"
							data-action="<?php echo esc_attr( $action['action'] ); ?>"><?php echo esc_html( $action['label'] ); ?></button>
					<?php endforeach; ?>
				</p>
			<?php endif; ?>
			<div class="notice notice-error inline ic-settings-section-table-errors <?php echo esc_attr( $this->settings['error_class'] ); ?>" style="display:none;">
				<p></p>
			</div>
			<?php foreach ( $groups as $group ) : ?>
				<div class="ic-settings-section-table-group <?php echo esc_attr( $group['wrapper_class'] ); ?>"
					data-group-id="<?php echo esc_attr( $group['id'] ); ?>"
					data-group-label="<?php echo esc_attr( $group['label'] ); ?>"
					data-field-name-template="<?php echo esc_attr( $group['field_name_template'] ); ?>"
					<?php
					if ( ! empty( $selector['options'] ) && $group['id'] !== $selected_group_id ) :
						?>
						style="display:none;"<?php endif; ?>>
					<table class="<?php echo esc_attr( $this->repeatable_table_class() ); ?>">
						<thead>
							<tr>
								<?php foreach ( $columns as $column ) : ?>
									<?php $this->render_repeatable_header_cell( $column ); ?>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $group['rows'] as $index => $row ) : ?>
								<?php $this->render_repeatable_row( $columns, $group, $index, $row ); ?>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endforeach; ?>
			<script type="text/html" class="ic-settings-section-table-row-template">
				<?php $this->render_repeatable_row( $columns, array( 'field_name_template' => '' ), self::INDEX_PLACEHOLDER, $row_defaults ); ?>
			</script>
		</div>
		<script>
			jQuery( function ( $ ) {
				var component = $( '#<?php echo esc_js( $component_id ); ?>' );
				var groups = component.find( '.ic-settings-section-table-group' );
				var selector = component.find( '.ic-settings-section-table-selector' );
				var errorBox = component.find( '.ic-settings-section-table-errors' );
				var messages = <?php echo wp_json_encode( $messages ); ?>;
				var newRowValues = <?php echo wp_json_encode( $new_row_values ); ?>;
				var resetRowValues = <?php echo wp_json_encode( $reset_row_values ); ?>;
				var groupErrorTemplate = <?php echo wp_json_encode( (string) $this->settings['group_error_message'] ); ?>;
				var selectorActions = <?php echo wp_json_encode( $selector_actions ); ?>;
				var rangeValidation = <?php echo wp_json_encode( $range_validation ); ?>;
				var rowTemplate = $.trim( component.find( '.ic-settings-section-table-row-template' ).html() );
				var invalidRowClass = <?php echo wp_json_encode( $this->invalid_row_class() ); ?>;

				function activeGroup() {
					if ( ! selector.length ) {
						return groups.first();
					}

					return groups.filter( '[data-group-id="' + selector.val() + '"]' );
				}

				function showSelectedGroup() {
					if ( ! selector.length ) {
						return;
					}

					groups.hide();
					activeGroup().show();
				}

				function nextRowIndex( group ) {
					var nextIndex = 0;

					group.find( '.ic-settings-section-table-row' ).each(
						function () {
							var currentIndex = parseInt( $( this ).attr( 'data-index' ), 10 );

							if ( ! isNaN( currentIndex ) && currentIndex >= nextIndex ) {
								nextIndex = currentIndex + 1;
							}
						}
					);

					return nextIndex;
				}

				function setRowIndex( row, group, index ) {
					var template = group.attr( 'data-field-name-template' );

					row.attr( 'data-index', index );
					row.find( '[data-field]' ).each(
						function () {
							var field = $( this ).data( 'field' );
							$( this ).attr(
								'name',
								template
									.replace( /__index__/g, index )
									.replace( /__field__/g, field )
							);
						}
					);
				}

				function fillRowValues( row, values ) {
					values = values || {};

					row.find( '[data-field]' ).each(
						function () {
							var field = String( $( this ).data( 'field' ) );
							var value = Object.prototype.hasOwnProperty.call( values, field ) ? values[ field ] : '';

							$( this ).val( value );
						}
					);
				}

				function refreshRangeConstraints( group ) {
					if ( ! rangeValidation.enabled ) {
						return;
					}

					var previousMax = null;

					group.find( '.ic-settings-section-table-row' ).each(
						function () {
							var row = $( this );
							var minField = row.find( '[data-field="' + rangeValidation.min_field + '"]' );
							var maxField = row.find( '[data-field="' + rangeValidation.max_field + '"]' );
							var minValue = parseFloat( $.trim( minField.val() ) );
							var maxValue = $.trim( maxField.val() );

							if ( previousMax === null ) {
								minField.attr( 'min', String( rangeValidation.start ) );
							} else {
								minField.attr( 'min', String( ( previousMax + rangeValidation.step ).toFixed( rangeValidation.precision ) ) );
							}

							if ( ! isNaN( minValue ) ) {
								maxField.attr( 'min', String( minValue ) );
							} else {
								maxField.attr( 'min', String( rangeValidation.start ) );
							}

							if ( maxValue === '' ) {
								previousMax = null;
								return false;
							}

							maxValue = parseFloat( maxValue );
							previousMax = isNaN( maxValue ) ? null : maxValue;
						}
					);
				}

				function rowValues( row ) {
					var values = {};

					row.find( '[data-field]' ).each(
						function () {
							values[ String( $( this ).data( 'field' ) ) ] = $( this ).val();
						}
					);

					return values;
				}

				function buildRow( group, index, values ) {
					var row = $( rowTemplate );

					setRowIndex( row, group, index );
					fillRowValues( row, values );

					return row;
				}

				function replaceGroupRows( group, rows ) {
					var tbody = group.find( 'tbody' ).first();

					tbody.empty();

					$.each(
						rows,
						function ( index, values ) {
							tbody.append( buildRow( group, index, values ) );
						}
					);

					refreshRangeConstraints( group );
				}

				function copyCurrentGroupToAll() {
					var sourceGroup = activeGroup();
					var sourceRows = [];

					sourceGroup.find( '.ic-settings-section-table-row' ).each(
						function () {
							sourceRows.push( rowValues( $( this ) ) );
						}
					);

					if ( ! sourceRows.length ) {
						sourceRows.push( $.extend( {}, resetRowValues ) );
					}

					groups.each(
						function () {
							var group = $( this );

							if ( group.is( sourceGroup ) ) {
								return;
							}

							replaceGroupRows( group, sourceRows );
						}
					);

					clearAllErrors();
					showErrors( validateGroup( sourceGroup, false ) );
				}

				function clearGroupErrors( group ) {
					group.find( '.ic-settings-section-table-row' ).removeClass( invalidRowClass );
				}

				function clearAllErrors() {
					groups.each(
						function () {
							clearGroupErrors( $( this ) );
						}
					);
					errorBox.hide();
					errorBox.find( 'p' ).empty();
				}

				function showErrors( errors ) {
					if ( ! errors.length ) {
						errorBox.hide();
						errorBox.find( 'p' ).empty();
						return;
					}

					errorBox.find( 'p' ).html( errors.join( '<br>' ) );
					errorBox.show();
				}

				function validateGroup( group, prefixLabel ) {
					var errors = [];
					var ranges = [];
					var groupLabel = group.attr( 'data-group-label' );

					clearGroupErrors( group );

					if ( ! rangeValidation.enabled ) {
						return errors;
					}

					group.find( '.ic-settings-section-table-row' ).each(
						function ( position ) {
							var row = $( this );
							var minField = row.find( '[data-field="' + rangeValidation.min_field + '"]' );
							var maxField = row.find( '[data-field="' + rangeValidation.max_field + '"]' );
							var minValue = $.trim( minField.val() );
							var maxValue = $.trim( maxField.val() );
							var rowNumber = position + 1;

							if ( minValue === '' ) {
								errors.push( messages.range_required.replace( '%s', rowNumber ) );
								row.addClass( invalidRowClass );
								return;
							}

							minValue = parseFloat( minValue );

							if ( isNaN( minValue ) ) {
								errors.push( messages.range_required.replace( '%s', rowNumber ) );
								row.addClass( invalidRowClass );
								return;
							}

							if ( maxValue !== '' ) {
								maxValue = parseFloat( maxValue );
							}

							if ( maxValue !== '' && ( isNaN( maxValue ) || minValue > maxValue ) ) {
								errors.push( messages.range_order.replace( '%s', rowNumber ) );
								row.addClass( invalidRowClass );
								return;
							}

							ranges.push(
								{
									rowNumber: rowNumber,
									min: minValue,
									max: maxValue === '' ? null : maxValue,
									unlimited: maxValue === '',
									element: row
								}
							);
						}
					);

					ranges.sort(
						function ( left, right ) {
							if ( left.min === right.min ) {
								if ( left.unlimited && right.unlimited ) {
									return 0;
								}
								if ( left.unlimited ) {
									return 1;
								}
								if ( right.unlimited ) {
									return -1;
								}

								return left.max - right.max;
							}

							return left.min - right.min;
						}
					);

					if ( ranges.length && ranges[0].min > rangeValidation.start ) {
						errors.push(
							messages.range_start
								.replace( '%s', ranges[0].rowNumber )
						);
						ranges[0].element.addClass( invalidRowClass );
					}

					for ( var i = 1; i < ranges.length; i++ ) {
						var expectedMin = ranges[ i - 1 ].unlimited ? null : parseFloat( ( ranges[ i - 1 ].max + rangeValidation.step ).toFixed( rangeValidation.precision ) );

						if ( ranges[ i - 1 ].unlimited || ranges[ i - 1 ].max >= ranges[ i ].min ) {
							errors.push(
								messages.range_overlap
									.replace( '%1$s', ranges[ i - 1 ].rowNumber )
									.replace( '%2$s', ranges[ i ].rowNumber )
							);
							ranges[ i - 1 ].element.addClass( invalidRowClass );
							ranges[ i ].element.addClass( invalidRowClass );
						} else if ( expectedMin !== null && ranges[ i ].min !== expectedMin ) {
							errors.push(
								messages.range_gap
									.replace( '%1$s', ranges[ i - 1 ].rowNumber )
									.replace( '%2$s', ranges[ i ].rowNumber )
									.replace( '%3$s', expectedMin.toFixed( rangeValidation.precision ) )
							);
							ranges[ i - 1 ].element.addClass( invalidRowClass );
							ranges[ i ].element.addClass( invalidRowClass );
						}
					}

					errors = errors.filter(
						function ( value, index, allErrors ) {
							return allErrors.indexOf( value ) === index;
						}
					);

					if ( prefixLabel && groupErrorTemplate !== '' ) {
						errors = errors.map(
							function ( message ) {
								return groupErrorTemplate
									.replace( '%1$s', groupLabel )
									.replace( '%2$s', message );
							}
						);
					}

					return errors;
				}

				if ( selector.length ) {
					selector.on(
						'change',
						function () {
							clearAllErrors();
							showSelectedGroup();
						}
					);
				}

				component.on(
					'click',
					'.ic-settings-section-table-selector-action',
					function () {
						var actionName = String( $( this ).attr( 'data-action' ) || '' );

						if ( 'copy-current-to-all' === actionName ) {
							copyCurrentGroupToAll();
						}
					}
				);

				component.on(
					'click',
					'.ic-settings-section-table-row-add',
					function () {
						var group = $( this ).closest( '.ic-settings-section-table-group' );
						var row = $( this ).closest( '.ic-settings-section-table-row' );
						var newRow = buildRow( group, nextRowIndex( group ), newRowValues );

						row.after( newRow );
						refreshRangeConstraints( group );
						showErrors( validateGroup( group, false ) );
					}
				);

				component.on(
					'click',
					'.ic-settings-section-table-row-remove',
					function () {
						var group = $( this ).closest( '.ic-settings-section-table-group' );
						var row = $( this ).closest( '.ic-settings-section-table-row' );

						if ( group.find( '.ic-settings-section-table-row' ).length === 1 ) {
							fillRowValues( row, resetRowValues );
							refreshRangeConstraints( group );
							showErrors( validateGroup( group, false ) );
							return;
						}

						row.remove();
						refreshRangeConstraints( group );
						showErrors( validateGroup( group, false ) );
					}
				);

				component.on(
					'input change',
					'input, textarea',
					function () {
						var group = $( this ).closest( '.ic-settings-section-table-group' );

						refreshRangeConstraints( group );
						showErrors( validateGroup( group, false ) );
					}
				);

				component.closest( 'form' ).on(
					'submit',
					function ( event ) {
						var errors = [];
						var firstInvalidGroup = '';

						clearAllErrors();

						groups.each(
							function () {
								var group = $( this );
								var groupErrors = validateGroup( group, groups.length > 1 );

								if ( groupErrors.length && firstInvalidGroup === '' ) {
									firstInvalidGroup = String( group.attr( 'data-group-id' ) );
								}

								errors = errors.concat( groupErrors );
							}
						);

						if ( ! errors.length ) {
							return;
						}

						event.preventDefault();
						if ( selector.length && firstInvalidGroup !== '' ) {
							selector.val( firstInvalidGroup );
							showSelectedGroup();
						}
						showErrors( errors );
						$( 'html, body' ).animate(
							{
								scrollTop: errorBox.offset().top - 80
							},
							150
						);
					}
				);

				groups.each(
					function () {
						refreshRangeConstraints( $( this ) );
					}
				);
				showSelectedGroup();
			} );
		</script>
		<?php
	}

	/**
	 * Returns the repeatable table component class.
	 *
	 * @return string
	 */
	private function repeatable_component_class() {
		return trim( 'ic-settings-section-table-component ' . (string) $this->settings['component_class'] );
	}

	/**
	 * Returns the repeatable table class.
	 *
	 * @return string
	 */
	private function repeatable_table_class() {
		return '' !== $this->settings['table_class'] ? $this->settings['table_class'] : 'widefat striped ic-settings-section-table';
	}

	/**
	 * Returns the repeatable row class.
	 *
	 * @return string
	 */
	private function repeatable_row_class() {
		return trim( 'ic-settings-section-table-row ' . (string) $this->settings['row_class'] );
	}

	/**
	 * Returns the invalid repeatable row class.
	 *
	 * @return string
	 */
	private function invalid_row_class() {
		return trim( 'ic-settings-section-table-row-invalid ' . (string) $this->settings['invalid_row_class'] );
	}

	/**
	 * Normalizes repeatable table columns.
	 *
	 * @param array $columns Raw column config.
	 *
	 * @return array
	 */
	private function normalize_repeatable_columns( $columns ) {
		$normalized = array();

		if ( ! is_array( $columns ) ) {
			return $normalized;
		}

		foreach ( $columns as $column ) {
			if ( ! is_array( $column ) ) {
				continue;
			}

			$column = wp_parse_args(
				$column,
				array(
					'key'                 => '',
					'label'               => '',
					'text'                => '',
					'type'                => 'input',
					'class'               => '',
					'fields'              => array(),
					'field_wrapper_class' => '',
				)
			);

			if ( '' === $column['label'] && '' !== $column['text'] ) {
				$column['label'] = $column['text'];
			}

			if ( ! empty( $column['fields'] ) && is_array( $column['fields'] ) ) {
				$column['fields'] = $this->normalize_repeatable_fields( $column['fields'] );
			} elseif ( '' !== $column['key'] ) {
				$column['fields'] = array( $this->normalize_repeatable_field( $column ) );
			}

			if ( 'actions' !== $column['type'] && empty( $column['fields'] ) ) {
				continue;
			}

			$normalized[] = $column;
		}

		return $normalized;
	}

	/**
	 * Normalizes repeatable field definitions.
	 *
	 * @param array $fields Raw field config.
	 *
	 * @return array
	 */
	private function normalize_repeatable_fields( $fields ) {
		$normalized = array();

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$field = $this->normalize_repeatable_field( $field );
			if ( '' === $field['key'] && 'text' !== $field['type'] ) {
				continue;
			}

			$normalized[] = $field;
		}

		return $normalized;
	}

	/**
	 * Normalizes a repeatable field definition.
	 *
	 * @param array $field Raw field config.
	 *
	 * @return array
	 */
	private function normalize_repeatable_field( $field ) {
		$field = wp_parse_args(
			is_array( $field ) ? $field : array(),
			array(
				'key'         => '',
				'type'        => 'input',
				'input_type'  => 'text',
				'input_class' => '',
				'class'       => '',
				'attributes'  => array(),
				'default'     => '',
				'rows'        => 2,
				'text'        => '',
			)
		);

		$field['key']        = (string) $field['key'];
		$field['type']       = (string) $field['type'];
		$field['input_type'] = (string) $field['input_type'];
		$field['attributes'] = is_array( $field['attributes'] ) ? $field['attributes'] : array();
		$field['default']    = is_scalar( $field['default'] ) ? (string) $field['default'] : '';
		$field['rows']       = max( 1, absint( $field['rows'] ) );

		return $field;
	}

	/**
	 * Builds generic text columns from existing rows when explicit columns are unavailable.
	 *
	 * @param array $groups Raw group config.
	 *
	 * @return array
	 */
	private function columns_from_groups( $groups ) {
		$keys = array();

		foreach ( array( 'new_row_values', 'single_row_reset_values' ) as $setting_key ) {
			if ( empty( $this->settings[ $setting_key ] ) || ! is_array( $this->settings[ $setting_key ] ) ) {
				continue;
			}

			$keys = array_merge( $keys, array_keys( $this->settings[ $setting_key ] ) );
		}

		if ( is_array( $groups ) ) {
			foreach ( $groups as $group ) {
				if ( ! is_array( $group ) || empty( $group['rows'] ) || ! is_array( $group['rows'] ) ) {
					continue;
				}

				foreach ( $group['rows'] as $row ) {
					if ( is_array( $row ) ) {
						$keys = array_merge( $keys, array_keys( $row ) );
						break 2;
					}
				}
			}
		}

		$keys    = array_values( array_unique( $keys ) );
		$columns = array();

		foreach ( $keys as $key ) {
			$columns[] = array(
				'key'   => (string) $key,
				'label' => ucwords( str_replace( array( '_', '-' ), ' ', (string) $key ) ),
			);
		}

		if ( ! empty( $columns ) ) {
			$columns[] = array(
				'type'  => 'actions',
				'label' => $this->labels()['actions'],
			);
		}

		return $this->normalize_repeatable_columns( $columns );
	}

	/**
	 * Renders a repeatable table header cell.
	 *
	 * @param array $column Column config.
	 *
	 * @return void
	 */
	private function render_repeatable_header_cell( $column ) {
		$class = isset( $column['class'] ) ? (string) $column['class'] : '';
		$label = isset( $column['label'] ) ? (string) $column['label'] : '';
		?>
		<th<?php echo '' !== $class ? ' class="' . esc_attr( $class ) . '"' : ''; ?>><?php echo esc_html( $label ); ?></th>
		<?php
	}

	/**
	 * Renders a repeatable table row.
	 *
	 * @param array      $columns Table columns.
	 * @param array      $group Table group config.
	 * @param int|string $index Row index.
	 * @param array      $row Row values.
	 *
	 * @return void
	 */
	private function render_repeatable_row( $columns, $group, $index, $row ) {
		$group = wp_parse_args(
			is_array( $group ) ? $group : array(),
			array(
				'field_name_template' => '',
			)
		);
		$row   = $this->normalize_row_values( $row, $this->row_value_defaults( array(), $columns ) );
		?>
		<tr class="<?php echo esc_attr( $this->repeatable_row_class() ); ?>" data-index="<?php echo esc_attr( (string) $index ); ?>">
			<?php foreach ( $columns as $column ) : ?>
				<?php $this->render_repeatable_cell( $column, $group, $index, $row ); ?>
			<?php endforeach; ?>
		</tr>
		<?php
	}

	/**
	 * Renders a repeatable table cell.
	 *
	 * @param array      $column Column config.
	 * @param array      $group Table group config.
	 * @param int|string $index Row index.
	 * @param array      $row Row values.
	 *
	 * @return void
	 */
	private function render_repeatable_cell( $column, $group, $index, $row ) {
		$class = isset( $column['class'] ) ? (string) $column['class'] : '';
		?>
		<td<?php echo '' !== $class ? ' class="' . esc_attr( $class ) . '"' : ''; ?>>
			<?php
			if ( isset( $column['type'] ) && 'actions' === $column['type'] ) {
				$this->render_repeatable_actions( $column );
			} elseif ( ! empty( $column['field_wrapper_class'] ) ) {
				?>
				<div class="<?php echo esc_attr( $column['field_wrapper_class'] ); ?>">
					<?php $this->render_repeatable_fields( $column['fields'], $group, $index, $row ); ?>
				</div>
				<?php
			} else {
				$this->render_repeatable_fields( $column['fields'], $group, $index, $row );
			}
			?>
		</td>
		<?php
	}

	/**
	 * Renders repeatable fields.
	 *
	 * @param array      $fields Field configs.
	 * @param array      $group Table group config.
	 * @param int|string $index Row index.
	 * @param array      $row Row values.
	 *
	 * @return void
	 */
	private function render_repeatable_fields( $fields, $group, $index, $row ) {
		foreach ( $fields as $field ) {
			$this->render_repeatable_field( $field, $group, $index, $row );
		}
	}

	/**
	 * Renders a repeatable field.
	 *
	 * @param array      $field Field config.
	 * @param array      $group Table group config.
	 * @param int|string $index Row index.
	 * @param array      $row Row values.
	 *
	 * @return void
	 */
	private function render_repeatable_field( $field, $group, $index, $row ) {
		if ( 'text' === $field['type'] ) {
			$class = '' !== $field['class'] ? ' class="' . esc_attr( $field['class'] ) . '"' : '';
			?>
			<span<?php echo $class; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $class is escaped above. ?>><?php echo esc_html( $field['text'] ); ?></span>
			<?php
			return;
		}

		if ( '' === $field['key'] ) {
			return;
		}

		$name       = $this->field_name( $group['field_name_template'], $index, $field['key'] );
		$value      = isset( $row[ $field['key'] ] ) ? $row[ $field['key'] ] : $field['default'];
		$class      = '' !== $field['input_class'] ? ' class="' . esc_attr( $field['input_class'] ) . '"' : '';
		$attributes = $field['attributes'];
		$attributes = array_merge(
			$attributes,
			array(
				'data-field' => $field['key'],
			)
		);

		if ( 'textarea' === $field['type'] ) {
			?>
			<textarea rows="<?php echo esc_attr( (string) $field['rows'] ); ?>"<?php echo $class; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $class is escaped above. ?>
				<?php if ( '' !== $name ) : ?>
					name="<?php echo esc_attr( $name ); ?>"
				<?php endif; ?>
				<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attributes_html() escapes attribute names and values internally. ?>
				<?php echo $this->attributes_html( $attributes ); ?>><?php echo esc_textarea( $value ); ?></textarea>
			<?php
			return;
		}
		?>
		<input type="<?php echo esc_attr( $field['input_type'] ); ?>"<?php echo $class; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $class is escaped above. ?>
			<?php if ( '' !== $name ) : ?>
				name="<?php echo esc_attr( $name ); ?>"
			<?php endif; ?>
			value="<?php echo esc_attr( $value ); ?>"
			<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attributes_html() escapes attribute names and values internally. ?>
			<?php echo $this->attributes_html( $attributes ); ?>/>
		<?php
	}

	/**
	 * Renders repeatable row action buttons.
	 *
	 * @param array $column Action column config.
	 *
	 * @return void
	 */
	private function render_repeatable_actions( $column ) {
		$labels       = $this->labels();
		$add_class    = isset( $column['add_button_class'] ) ? (string) $column['add_button_class'] : '';
		$remove_class = isset( $column['remove_button_class'] ) ? (string) $column['remove_button_class'] : '';
		?>
		<button type="button" class="button button-secondary ic-settings-section-table-row-add <?php echo esc_attr( $add_class ); ?>" aria-label="<?php echo esc_attr( $labels['add_row_below'] ); ?>">+</button>
		<button type="button" class="button button-secondary ic-settings-section-table-row-remove <?php echo esc_attr( $remove_class ); ?>" aria-label="<?php echo esc_attr( $labels['remove_row'] ); ?>">-</button>
		<?php
	}

	/**
	 * Returns repeatable numeric range validation settings.
	 *
	 * @return array
	 */
	private function range_validation() {
		$validation = wp_parse_args(
			is_array( $this->settings['range_validation'] ) ? $this->settings['range_validation'] : array(),
			array(
				'enabled'   => false,
				'min_field' => '',
				'max_field' => '',
				'start'     => 0,
				'step'      => 0.01,
				'precision' => 2,
			)
		);

		$min_field = (string) $validation['min_field'];
		$max_field = (string) $validation['max_field'];

		return array(
			'enabled'   => ! empty( $validation['enabled'] ) && '' !== $min_field && '' !== $max_field,
			'min_field' => $min_field,
			'max_field' => $max_field,
			'start'     => is_numeric( $validation['start'] ) ? (float) $validation['start'] : 0,
			'step'      => is_numeric( $validation['step'] ) ? (float) $validation['step'] : 0.01,
			'precision' => max( 0, absint( $validation['precision'] ) ),
		);
	}

	/**
	 * Renders a generic fixed-fields settings table.
	 *
	 * @return void
	 */
	private function render_fields_table() {

		$columns = is_array( $this->settings['columns'] ) ? $this->settings['columns'] : array();
		$rows    = is_array( $this->settings['rows'] ) ? $this->settings['rows'] : array();

		if ( empty( $columns ) || empty( $rows ) ) {
			return;
		}

		$this->register_settings();

		if ( '' !== $this->settings['wrapper_class'] ) {
			?>
			<div class="<?php echo esc_attr( $this->settings['wrapper_class'] ); ?>"<?php echo '' !== $this->settings['wrapper_style'] ? ' style="' . esc_attr( $this->settings['wrapper_style'] ) . '"' : ''; ?>>
			<?php
		}
		?>
		<table class="<?php echo esc_attr( $this->fields_table_class() ); ?>">
			<thead>
				<tr>
					<?php
					foreach ( $columns as $column ) :
						?>
						<?php $this->render_header_cell( $column ); ?>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $rows as $row ) :
					?>
					<tr>
						<?php
						foreach ( $this->row_cells( $row ) as $cell ) :
							?>
							<?php $this->render_body_cell( $cell ); ?>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		if ( '' !== $this->settings['wrapper_class'] ) {
			?>
			</div>
			<?php
		}

		$this->after_actions();
	}

	/**
	 * Returns the generic fields table class.
	 *
	 * @return string
	 */
	private function fields_table_class() {

		return '' !== $this->settings['table_class'] ? $this->settings['table_class'] : 'widefat striped ic-settings-section-table';
	}

	/**
	 * Registers settings represented by a generic fields table.
	 *
	 * @return void
	 */
	private function register_settings() {

		if ( empty( $this->settings['registered_settings'] ) || ! is_array( $this->settings['registered_settings'] ) || ! function_exists( 'ic_register_setting' ) ) {
			return;
		}

		foreach ( $this->settings['registered_settings'] as $setting ) {
			if ( ! is_array( $setting ) || empty( $setting['name'] ) ) {
				continue;
			}

			$label = isset( $setting['label'] ) ? $setting['label'] : '';
			ic_register_setting( $label, $setting['name'] );
		}
	}

	/**
	 * Renders a generic table header cell.
	 *
	 * @param array $column Header cell configuration.
	 *
	 * @return void
	 */
	private function render_header_cell( $column ) {

		if ( ! is_array( $column ) ) {
			return;
		}

		if ( isset( $column['type'] ) && 'action' === $column['type'] ) {
			$this->render_action( $column );

			return;
		}

		$class = isset( $column['class'] ) ? (string) $column['class'] : '';
		$text  = isset( $column['text'] ) ? (string) $column['text'] : '';
		?>
		<th<?php echo '' !== $class ? ' class="' . esc_attr( $class ) . '"' : ''; ?>><?php echo esc_html( $text ); ?></th>
		<?php
	}

	/**
	 * Returns a row cell list.
	 *
	 * @param array $row Row configuration.
	 *
	 * @return array
	 */
	private function row_cells( $row ) {

		if ( ! is_array( $row ) ) {
			return array();
		}

		return isset( $row['cells'] ) && is_array( $row['cells'] ) ? $row['cells'] : array();
	}

	/**
	 * Renders a generic table body cell.
	 *
	 * @param array $cell Body cell configuration.
	 *
	 * @return void
	 */
	private function render_body_cell( $cell ) {

		if ( ! is_array( $cell ) ) {
			return;
		}

		if ( isset( $cell['type'] ) && 'action' === $cell['type'] ) {
			$this->render_action( $cell );

			return;
		}

		$class = isset( $cell['class'] ) ? (string) $cell['class'] : '';
		?>
		<td<?php echo '' !== $class ? ' class="' . esc_attr( $class ) . '"' : ''; ?>>
			<?php
			if ( isset( $cell['type'] ) && 'input' === $cell['type'] ) {
				$this->render_input( $cell );
			} else {
				echo esc_html( isset( $cell['text'] ) ? (string) $cell['text'] : '' );
			}
			?>
		</td>
		<?php
	}

	/**
	 * Renders a generic input field.
	 *
	 * @param array $cell Cell configuration.
	 *
	 * @return void
	 */
	private function render_input( $cell ) {

		$type  = isset( $cell['input_type'] ) ? (string) $cell['input_type'] : 'text';
		$name  = isset( $cell['name'] ) ? (string) $cell['name'] : '';
		$value = isset( $cell['value'] ) ? $cell['value'] : '';
		$class = isset( $cell['input_class'] ) ? (string) $cell['input_class'] : '';
		$id    = isset( $cell['id'] ) ? (string) $cell['id'] : '';
		$attrs = isset( $cell['attributes'] ) && is_array( $cell['attributes'] ) ? $cell['attributes'] : array();
		?>
		<input type="<?php echo esc_attr( $type ); ?>"
			<?php
			if ( '' !== $id ) :
				?>
				id="<?php echo esc_attr( $id ); ?>"<?php endif; ?>
			<?php
			if ( '' !== $class ) :
				?>
					class="<?php echo esc_attr( $class ); ?>"<?php endif; ?>
				name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attributes_html() escapes attribute names and values internally. ?>
				<?php echo $this->attributes_html( $attrs ); ?>/>
		
		<?php
	}

	/**
	 * Renders a configured action.
	 *
	 * @param array $action Action configuration.
	 *
	 * @return void
	 */
	private function render_action( $action ) {

		$action = wp_parse_args(
			is_array( $action ) ? $action : array(),
			array(
				'action' => '',
				'args'   => array(),
			)
		);

		if ( empty( $action['action'] ) ) {
			return;
		}

		$action['args'] = is_array( $action['args'] ) ? $action['args'] : array();
		do_action( 'ic_settings_section_table_action', $action, $this->settings, $this );
	}

	/**
	 * Fires configured actions after a generic fields table.
	 *
	 * @return void
	 */
	private function after_actions() {

		if ( empty( $this->settings['after_actions'] ) || ! is_array( $this->settings['after_actions'] ) ) {
				return;
		}

		foreach ( $this->settings['after_actions'] as $action ) {
			$this->render_action( is_array( $action ) ? $action : array() );
		}
	}

	/**
	 * Returns escaped attribute HTML.
	 *
	 * @param array $attributes Attribute map.
	 *
	 * @return string
	 */
	private function attributes_html( $attributes ) {

		$html = '';

		foreach ( $attributes as $name => $value ) {
			if ( '' === $name ) {
				continue;
			}

			$html .= ' ' . esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
		}

		return $html;
	}
	/**
	 * Returns the configured validation messages.
	 *
	 * @return array
	 */
	private function validation_messages() {
		return wp_parse_args(
			$this->settings['validation_messages'],
			array(
				'range_start'    => 'Row %s must start at 0.',
				'range_required' => 'Row %s must include a minimum value.',
				'range_order'    => 'Row %s has a minimum value greater than the maximum value.',
				'range_gap'      => 'Rows %1$s and %2$s must continue without gaps. The next minimum price should be %3$s.',
				'range_overlap'  => 'Rows %1$s and %2$s have overlapping ranges.',
			)
		);
	}

	/**
	 * Returns the configured labels.
	 *
	 * @return array
	 */
	private function labels() {
		return wp_parse_args(
			$this->settings['labels'],
			array(
				'actions'       => 'Actions',
				'selector'      => 'Select',
				'add_row_below' => 'Add row below',
				'remove_row'    => 'Remove row',
			)
		);
	}

	/**
	 * Normalizes the configured groups.
	 *
	 * @param array $groups Raw group config.
	 * @param array $columns Table column config.
	 *
	 * @return array
	 */
	private function normalize_groups( $groups, $columns = array() ) {
		$normalized = array();

		if ( ! is_array( $groups ) ) {
			return $normalized;
		}

		foreach ( $groups as $group ) {
			if ( ! is_array( $group ) || empty( $group['field_name_template'] ) ) {
				continue;
			}

			$group = wp_parse_args(
				$group,
				array(
					'id'                  => '',
					'label'               => '',
					'rows'                => array(),
					'field_name_template' => '',
					'wrapper_class'       => '',
				)
			);

			$rows         = is_array( $group['rows'] ) ? $group['rows'] : array();
			$row_defaults = $this->row_value_defaults( $rows, $columns );
			if ( empty( $rows ) ) {
				$rows = array( $this->normalize_row_values( $this->settings['single_row_reset_values'], $row_defaults ) );
			} else {
				$rows = array_map(
					function ( $row ) use ( $row_defaults ) {
						return $this->normalize_row_values( $row, $row_defaults );
					},
					$rows
				);
			}

			$normalized[] = array(
				'id'                  => (string) $group['id'],
				'label'               => (string) $group['label'],
				'rows'                => array_values( $rows ),
				'field_name_template' => (string) $group['field_name_template'],
				'wrapper_class'       => (string) $group['wrapper_class'],
			);
		}

		return $normalized;
	}

	/**
	 * Normalizes the selector config.
	 *
	 * @param array $selector Raw selector config.
	 * @param array $groups Table groups.
	 *
	 * @return array
	 */
	private function normalize_selector( $selector, $groups ) {
		$labels   = $this->labels();
		$defaults = array(
			'id'       => function_exists( 'wp_unique_id' ) ? wp_unique_id( 'ic-settings-section-selector-' ) : uniqid( 'ic-settings-section-selector-', false ),
			'label'    => $labels['selector'],
			'options'  => array(),
			'selected' => '',
			'class'    => '',
		);
		$selector = wp_parse_args( is_array( $selector ) ? $selector : array(), $defaults );
		$options  = array();

		foreach ( $selector['options'] as $option ) {
			if ( ! is_array( $option ) || ! isset( $option['value'], $option['label'] ) ) {
				continue;
			}

			$options[] = array(
				'value' => (string) $option['value'],
				'label' => (string) $option['label'],
			);
		}

		$selector['options']  = $options;
		$selector['selected'] = (string) $selector['selected'];

		if ( empty( $selector['options'] ) ) {
			$selector['selected'] = ! empty( $groups[0]['id'] ) ? $groups[0]['id'] : '';

			return $selector;
		}

		$selected_values = wp_list_pluck( $selector['options'], 'value' );
		if ( ! in_array( $selector['selected'], $selected_values, true ) ) {
			$selector['selected'] = $selector['options'][0]['value'];
		}

		return $selector;
	}

	/**
	 * Normalizes selector action buttons shown next to the selector.
	 *
	 * @param array $actions Raw selector-action config.
	 *
	 * @return array
	 */
	private function normalize_selector_actions( $actions ) {
		$normalized = array();
		if ( ! is_array( $actions ) ) {
			return $normalized;
		}

		foreach ( $actions as $action ) {
			if ( ! is_array( $action ) || empty( $action['action'] ) || empty( $action['label'] ) ) {
				continue;
			}

			$normalized[] = array(
				'action' => (string) $action['action'],
				'label'  => (string) $action['label'],
				'class'  => isset( $action['class'] ) ? (string) $action['class'] : '',
			);
		}

		return $normalized;
	}

	/**
	 * Normalizes a row payload.
	 *
	 * @param array $values Raw row values.
	 * @param array $defaults Row defaults.
	 *
	 * @return array
	 */
	private function normalize_row_values( $values, $defaults = array() ) {
		$values     = wp_parse_args( is_array( $values ) ? $values : array(), $defaults );
		$normalized = array();

		foreach ( $values as $key => $value ) {
			$normalized[ $key ] = is_scalar( $value ) ? (string) $value : '';
		}

		return $normalized;
	}

	/**
	 * Returns default values for a repeatable row.
	 *
	 * @param array $rows Optional current row list.
	 * @param array $columns Table column config.
	 *
	 * @return array
	 */
	private function row_value_defaults( $rows = array(), $columns = array() ) {
		$defaults = $this->row_defaults_from_columns( $columns );

		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				foreach ( $row as $key => $value ) {
					if ( ! array_key_exists( $key, $defaults ) ) {
						$defaults[ $key ] = '';
					}
				}
			}
		}

		return $defaults;
	}

	/**
	 * Returns default row values from repeatable column field definitions.
	 *
	 * @param array $columns Table column config.
	 *
	 * @return array
	 */
	private function row_defaults_from_columns( $columns ) {
		$defaults = array();

		foreach ( $columns as $column ) {
			if ( empty( $column['fields'] ) || ! is_array( $column['fields'] ) ) {
				continue;
			}

			foreach ( $column['fields'] as $field ) {
				if ( empty( $field['key'] ) ) {
					continue;
				}

				$defaults[ $field['key'] ] = isset( $field['default'] ) && is_scalar( $field['default'] ) ? (string) $field['default'] : '';
			}
		}

		return $defaults;
	}

	/**
	 * Builds a row field name from the configured template.
	 *
	 * @param string $template Field-name template.
	 * @param int    $index Row index.
	 * @param string $field Field key.
	 *
	 * @return string
	 */
	private function field_name( $template, $index, $field ) {
		return str_replace(
			array( self::INDEX_PLACEHOLDER, self::FIELD_PLACEHOLDER ),
			array( (string) $index, $field ),
			$template
		);
	}
}
