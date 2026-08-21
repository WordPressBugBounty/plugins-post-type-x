<?php
/**
 * Settings helper functions.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'implecode_settings_radio' ) ) {

	/**
	 * Shows radio buttons in tr and td tags
	 *
	 * @param string     $option_label Option label.
	 * @param string     $option_name Option name.
	 * @param string|int $option_value Selected option value.
	 * @param array      $elements Available radio options.
	 * @param int        $should_echo Whether to echo the output.
	 * @param string     $tip Tooltip text.
	 * @param string     $line Separator between radio rows.
	 * @param string     $input_class Input class.
	 * @param bool       $table_row Whether to render the setting inside a table row.
	 *
	 * @return string
	 */
	function implecode_settings_radio(
		$option_label,
		$option_name,
		$option_value,
		$elements = array(),
		$should_echo = 1,
		$tip = '',
		$line = '<br>',
		$input_class = 'number_box',
		$table_row = true
	) {
		if ( empty( $option_label ) ) {
			$table_row = false;
			$tip       = '';
		}
		if ( ! empty( $tip ) && ! is_array( $tip ) ) {
			$tip_html = 'title="' . $tip . '"';
		}

		$return = '';
		if ( $table_row ) {
			$return .= '<tr>';
			$return .= '<td style="white-space: nowrap;vertical-align: top;">';
		}
		if ( ! empty( $tip_html ) ) {
			$return .= '<span ' . $tip_html . ' class="dashicons dashicons-editor-help ic_tip"></span>';
		}
		if ( ! empty( $option_label ) ) {
			$return .= $option_label . ':';
		}
		if ( $table_row ) {
			$return .= '</td>';
			$return .= '<td class="ic_radio_td" style="width:100%;">';
		}
		foreach ( $elements as $key => $element ) {
			// Reserved for per-option tips.
			$return .= '<div><span style="display:table-cell"><input type="radio" class="' . $input_class . '" id="' . $option_name . '_' . $key . '" name="' . $option_name . '" value="' . $key . '"' . checked( $key, $option_value, 0 ) . '></span><label for="' . $option_name . '_' . $key . '" style="display: table-cell">' . $element . '</label></div>';
		}
		if ( $table_row ) {
			$return .= '</td>';
			$return .= '</tr>';
		}
		ic_register_setting( $option_label, $option_name, $tip );

		return echo_ic_setting( $return, $should_echo );
	}

}
if ( ! function_exists( 'implecode_settings_dropdown' ) ) {

	/**
	 * Displays a settings dropdown.
	 *
	 * @param string       $option_label Option label.
	 * @param string       $option_name Option name.
	 * @param string|array $option_value Selected option value.
	 * @param array        $elements Available options.
	 * @param int          $should_echo Whether to echo the output.
	 * @param string|null  $attr Additional select attributes.
	 * @param string|null  $tip Tooltip text.
	 *
	 * @return string
	 */
	function implecode_settings_dropdown(
		$option_label,
		$option_name,
		$option_value,
		$elements = array(),
		$should_echo = 1,
		$attr = null,
		$tip = null
	) {
		$return = '';
		if ( ! empty( $tip ) && ! is_array( $tip ) ) {
			$tip_html = 'title="' . $tip . '"';
		}
		if ( ! empty( $option_label ) ) {
			$return .= '<tr>';
			$return .= '<td>';
			if ( ! empty( $tip_html ) ) {
				$return .= '<span ' . $tip_html . ' class="dashicons dashicons-editor-help ic_tip"></span>';
			}
			$return .= $option_label . ':</td>';
			$return .= '<td>';
		}
		$class = '';
		if ( ! empty( $attr ) && ic_string_contains( $attr, 'multiple' ) ) {
			if ( ! ic_string_contains( $option_name, '[]' ) ) {
				$option_name .= '[]';
			}
			$class = 'ic_chosen';
		}
		if ( ! empty( $class ) ) {
			$class = 'class="' . $class . '" ';
		}
		$return           .= '<select name="' . $option_name . '" ' . $attr . ' ' . $class . '>';
		$this_option_value = $option_value;
		$selected_values   = null;
		$associative       = false;
		if ( is_array( $option_value ) ) {
			$selected_values = array_map( 'strval', $option_value );
		}
		if ( array_keys( $elements ) !== range( 0, count( $elements ) - 1 ) ) {
			$associative = true;
		}
		foreach ( $elements as $key => $element ) {
			if ( ! $associative ) {
				$key = $element;
			}
			if ( null !== $selected_values ) {
				if ( in_array( (string) $key, $selected_values, true ) ) {
					$this_option_value = $key;
				} else {
					$this_option_value = '';
				}
			}
			$return .= '<option value="' . $key . '" ' . selected( $key, $this_option_value, 0 ) . '>' . $element . '</option>';
		}
		$return .= '</select>';
		if ( ! empty( $option_label ) ) {
			$return .= '</td>';
			$return .= '</tr>';
		}
		ic_register_setting( $option_label, $option_name, $tip );

		return echo_ic_setting( $return, $should_echo );
	}

}
if ( ! function_exists( 'implecode_settings_checkbox' ) ) {

	/**
	 * Displays checkbox as HTML table row
	 *
	 * @param string $option_label Option label.
	 * @param string $option_name Option name.
	 * @param int    $option_enabled Whether the option is enabled.
	 * @param int    $should_echo Whether to echo the output.
	 * @param string $tip Tooltip text.
	 * @param int    $value Checkbox value.
	 * @param string $input_class Input class.
	 *
	 * @return string
	 */
	function implecode_settings_checkbox(
		$option_label,
		$option_name,
		$option_enabled,
		$should_echo = 1,
		$tip = '',
		$value = 1,
		$input_class = ''
	) {
		if ( ! empty( $tip ) && ! is_array( $tip ) ) {
			$tip_html = 'title="' . $tip . '" ';
		}
		$return = '';
		if ( ! empty( $option_label ) ) {
			$return .= '<tr>';
			$return .= '<td>';
		}

		if ( ! empty( $tip_html ) ) {
			$return .= '<span ' . $tip_html . ' class="dashicons dashicons-editor-help ic_tip"></span>';
		}
		if ( ! empty( $option_label ) ) {
			$return .= $option_label . ':</td>';
		}
		if ( ! empty( $input_class ) ) {
			$input_class = 'class="' . $input_class . '" ';
		}
		if ( ! empty( $option_label ) ) {
			$return .= '<td>';
		}
		$return .= '<input type="checkbox" ' . $input_class . 'name="' . $option_name . '" value="' . $value . '" ' . checked( $value, $option_enabled, 0 ) . '/>';
		if ( ! empty( $option_label ) ) {
			$return .= '</td>';
			$return .= '</tr>';

		}
		ic_register_setting( $option_label, $option_name, $tip );

		return echo_ic_setting( $return, $should_echo );
	}

}
if ( ! function_exists( 'implecode_settings_text' ) ) {

	/**
	 * Shows settings text fiels
	 *
	 * @param string     $option_label Option label.
	 * @param string     $option_name Option name.
	 * @param string|int $option_value Option value.
	 * @param string     $required Required attribute value.
	 * @param int        $should_echo Whether to echo the output.
	 * @param string     $input_class Input class.
	 * @param string     $tip Tooltip text.
	 * @param string     $disabled Disabled attribute value.
	 * @param string     $attributes Extra input attributes.
	 * @param string     $type Input type.
	 *
	 * @return string
	 */
	function implecode_settings_text(
		$option_label,
		$option_name,
		$option_value,
		$required = null,
		$should_echo = 1,
		$input_class = null,
		$tip = null,
		$disabled = '',
		$attributes = null,
		$type = 'text'
	) {
		if ( ! empty( $disabled ) ) {
			$disabled .= ' ';
		}
		if ( '' !== $required && null !== $required ) {
			$regired_field = 'required="required"';
			$star          = '<span class="star"> *</span>';
		} else {
			$regired_field = '';
			$star          = '';
		}
		$tip_html = ! empty( $tip ) ? 'title="' . $tip . '" ' : '';
		$return   = '';
		if ( '' !== $option_label ) {
			$return .= '<tr>';
			$return .= '<td>';
			if ( ! empty( $tip_html ) ) {
				$return .= '<span ' . $tip_html . ' class="dashicons dashicons-editor-help ic_tip"></span>';
			}
			$return .= $option_label . $star . ':</td>';
			$return .= '<td>';
		}
		$return .= '<input ' . $attributes . ' ' . $regired_field . ' ' . $disabled . 'class="' . $input_class . '" type="' . $type . '" name="' . $option_name . '" value="' . esc_html( $option_value ) . '" />';
		if ( '' !== $option_label ) {
			$return .= '</td>';
			$return .= '</tr>';
		}
		ic_register_setting( $option_label, $option_name, $tip );

		return echo_ic_setting( $return, $should_echo );
	}

}
if ( ! function_exists( 'implecode_settings_number' ) ) {

	/**
	 * Generates number field within table tr tags
	 *
	 * @param string $option_label Option label.
	 * @param string $option_name Option name.
	 * @param float  $option_value Option value.
	 * @param string $unit Unit label.
	 * @param int    $should_echo Whether to echo the output.
	 * @param float  $step Input step.
	 * @param string $tip Tooltip text.
	 * @param float  $min Minimum value.
	 * @param float  $max Maximum value.
	 * @param string $input_class Additional input class.
	 * @param string $tr_class Table row class.
	 * @param string $attr Additional input attributes.
	 *
	 * @return string
	 */
	function implecode_settings_number(
		$option_label,
		$option_name,
		$option_value,
		$unit,
		$should_echo = 1,
		$step = 1,
		$tip = null,
		$min = null,
		$max = null,
		$input_class = null,
		$tr_class = null,
		$attr = null
	) {
		if ( ! empty( $tr_class ) ) {
			$tr_class = ' class="' . $tr_class . '"';
		}
		$return = '';
		if ( ! empty( $option_label ) ) {
			$return .= '<tr' . $tr_class . '>';
			$return .= '<td>';
		}

		$input_class = 'number_box ' . $input_class;
		$tip_html    = ! empty( $tip ) ? 'title="' . $tip . '" ' : '';
		if ( ! empty( $tip_html ) ) {
			$return .= '<span ' . $tip_html . ' class="dashicons dashicons-editor-help ic_tip"></span>';
		}
		if ( ! empty( $option_label ) ) {
			$return .= $option_label . ':</td>';
		}
		$min = isset( $min ) ? 'min="' . floatval( $min ) . '" ' : '';
		$max = isset( $max ) ? 'max="' . floatval( $max ) . '" ' : '';
		if ( ! empty( $option_label ) ) {
			$return .= '<td>';
		}
		if ( ! empty( $attr ) ) {
			$attr = ' ' . $attr;
		}
		if ( '' !== $option_value ) {
			$option_value = floatval( $option_value );
		} else {
			$option_value = '';
		}
		$return .= '<input type="number" step="' . $step . '" ' . $min . ' ' . $max . ' class="' . $input_class . '" name="' . $option_name . '" value="' . $option_value . '"' . $attr . ' />' . $unit;
		if ( ! empty( $option_label ) ) {
			$return .= '</td>';
			$return .= '</tr>';
		}
		ic_register_setting( $option_label, $option_name, $tip );

		return echo_ic_setting( $return, $should_echo );
	}

}
if ( ! function_exists( 'implecode_settings_textarea' ) ) {

	/**
	 * Displays a textarea setting.
	 *
	 * @param string      $option_label Option label.
	 * @param string      $option_name Option name.
	 * @param string      $option_value Option value.
	 * @param int         $should_echo Whether to echo the output.
	 * @param string|null $attr Additional textarea attributes.
	 * @param string|null $tip Tooltip text.
	 *
	 * @return string
	 */
	function implecode_settings_textarea( $option_label, $option_name, $option_value, $should_echo = 1, $attr = null, $tip = null ) {
		$return = '';
		if ( ! empty( $option_label ) ) {
			$return .= '<tr>';
			$return .= '<td>';
		}

		if ( ! empty( $tip ) ) {
			$tip_html = ! empty( $tip ) ? 'title="' . $tip . '" ' : '';
			$return  .= '<span ' . $tip_html . ' class="dashicons dashicons-editor-help ic_tip"></span>';
		}
		if ( ! empty( $option_label ) ) {
			$return .= $option_label . ':</td>';
			$return .= '<td>';
		}
		$return .= '<textarea name="' . $option_name . '" ' . $attr . '>' . esc_textarea( $option_value ) . '</textarea>';
		if ( ! empty( $option_label ) ) {
			$return .= '</td>';
			$return .= '</tr>';
		}
		ic_register_setting( $option_label, $option_name, $tip );

		return echo_ic_setting( $return, $should_echo );
	}

}
if ( ! function_exists( 'implecode_upload_image' ) ) {

	/**
	 * Displays the upload image setting.
	 *
	 * @param string      $button_value Upload button label.
	 * @param string      $option_name Option name.
	 * @param string|int  $option_value Option value.
	 * @param string|null $default_image Default image value.
	 * @param string      $upload_image_id Upload mode.
	 * @param int         $should_echo Whether to echo the output.
	 * @param string      $id Field ID.
	 *
	 * @return string
	 */
	function implecode_upload_image(
		$button_value,
		$option_name,
		$option_value,
		$default_image = null,
		$upload_image_id = 'url',
		$should_echo = 1,
		$id = ''
	) {
		if ( function_exists( 'get_current_screen' ) ) {
			$current_screen = get_current_screen();
		}
		if ( empty( $current_screen->id ) || 'widgets' !== $current_screen->id ) {
			wp_enqueue_media();
			if ( function_exists( 'wp_enqueue_editor' ) ) {
				wp_enqueue_editor();
			}
		}
		if ( empty( $id ) ) {
			$id = sanitize_title( $option_name );
		}
		$option_value = ! empty( $option_value ) ? $option_value : $default_image;
		$image_src    = $option_value;
		if ( ! empty( $option_value ) && 'url' !== $upload_image_id ) {
			$upload_image_id = 'id';
			if ( empty( $image_src ) || strpos( $image_src, 'http' ) === false ) {
				$image_src = wp_get_attachment_image_src( $option_value, 'medium' );
				if ( ! empty( $image_src[0] ) ) {
					$image_src = $image_src[0];
				} else {
					$image_src = '';
				}
			}
		}
		$class = '';
		if ( null !== $option_value ) {
			$class = 'active-image';
		}
		$content  = '<div class="custom-uploader ' . $class . '">';
		$content .= '<input type="hidden" class="upload_type" id="upload_type" value="' . $upload_image_id . '" />';
		$content .= '<input type="hidden" class="default" id="default" value="' . $default_image . '" />';
		$content .= '<input type="hidden" name="' . $option_name . '" class="uploaded_image" id="' . $id . '" value="' . $option_value . '" />';
		// Keep the image wrapper rendered so the preview can be toggled dynamically.
		$class = '';
		if ( null === $option_value ) {
			$class = 'empty';
		}
		$content .= '<div class="implecode-admin-media-image ' . $class . '">';
		$style    = '';
		if ( null === $option_value ) {
			$style = 'style="display: none"';
		}
		$content .= '<span ' . $style . ' option_name="' . $option_name . '" class="catalog-reset-image-button">X</span>';
		$style    = '';
		if ( empty( $image_src ) ) {
			$style = ' style="display: none"';
		}
		$content .= '<img' . $style . ' class="media-image" name="' . $option_name . '_image" src="' . $image_src . '" />';
		$content .= '</div>';
		$style    = '';
		if ( null !== $option_value ) {
			$style = 'style="display: none"';
		}
		if ( ic_string_contains( $option_name, '[]' ) ) {
			$normal_name = str_replace( '[]', '', $option_name );
			$link_name   = $normal_name . '_button[]';
			global $ic_image_upload_count;
			if ( empty( $ic_image_upload_count ) ) {
				$ic_image_upload_count = 0;
			} else {
				++$ic_image_upload_count;
			}
			$link_id     = 'button_' . $normal_name . '_' . $ic_image_upload_count;
			$option_name = str_replace( '[]', '[' . $ic_image_upload_count . ']', $option_name );
		} else {
			$link_name = $option_name . '_button';
			$link_id   = 'button_' . $option_name;
		}
		$content .= '<a ' . $style . ' href="#" class="button add_catalog_media" option_name="' . $option_name . '" name="' . $link_name . '" id="' . $link_id . '"><span class="wp-media-buttons-icon"></span> ' . $button_value . '</a>';
		$content .= '<div class="image-label">' . $button_value . '</div>';
		$content .= '</div>';
		ic_register_setting( $button_value, $option_name );

		return echo_ic_setting( $content, $should_echo );
	}

}
if ( ! function_exists( 'echo_ic_setting' ) ) {

	/**
	 * Echoes or returns a rendered setting.
	 *
	 * @param string $content Rendered HTML.
	 * @param int    $should_echo Whether to echo the output.
	 *
	 * @return string|null
	 */
	function echo_ic_setting( $content, $should_echo = 1 ) {
		if ( 1 === (int) $should_echo ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: HTML assembled earlier in this scope, so escaping it here would print the tags.
			echo $content;
		} else {
			return $content;
		}
	}

}
if ( ! function_exists( 'implecode_warning' ) ) {

	/**
	 * Displays a warning notice.
	 *
	 * @param string $text Notice content.
	 * @param int    $should_echo Whether to echo the output.
	 *
	 * @return string|null
	 */
	function implecode_warning( $text, $should_echo = 1 ) {
		if ( ! is_ic_admin() ) {
			$text = do_shortcode( $text );
		}

		return echo_ic_setting( '<div class="al-box warning">' . $text . '</div>', $should_echo );
	}

}
if ( ! function_exists( 'implecode_info' ) ) {

	/**
	 * Displays an info notice.
	 *
	 * @param string $text Notice content.
	 * @param int    $should_echo Whether to echo the output.
	 * @param int    $p Whether to wrap the message in a paragraph.
	 * @param bool   $dismisable Whether the notice is dismissible.
	 *
	 * @return string|null
	 */
	function implecode_info( $text, $should_echo = 1, $p = 0, $dismisable = true ) {
		$return = '';
		if ( ! is_ic_admin() ) {
			$text = do_shortcode( $text );
		}
		if ( 1 === (int) $p ) {
			$return .= '<p>' . $text . '</p>';
		} else {
			$return .= $text;
		}
		if ( $dismisable && is_ic_admin() ) {
			$return .= '<span class="notice-dismiss"><span class="screen-reader-text">' . __( 'Dismiss this notice.', 'post-type-x' ) . '</span></span>';
		}
		$hash = ic_message_hash( $return );
		if ( ! ic_is_message_hidden( $return ) ) {
			$return = '<div class="al-box info" data-hash="' . $hash . '">' . $return . '</div>';

			return echo_ic_setting( $return, $should_echo );
		}
	}

}
if ( ! function_exists( 'implecode_success' ) ) {

	/**
	 * Displays a success notice.
	 *
	 * @param string $text Notice content.
	 * @param int    $should_echo Whether to echo the output.
	 * @param int    $p Whether to wrap the message in a paragraph.
	 *
	 * @return string|null
	 */
	function implecode_success( $text, $should_echo = 1, $p = 1 ) {
		$return = '<div class="al-box success">';
		if ( ! is_ic_admin() ) {
			$text = do_shortcode( $text );
		}
		if ( 1 === (int) $p ) {
			$return .= '<p>' . $text . '</p>';
		} else {
			$return .= $text;
		}
		$return .= '</div>';

		return echo_ic_setting( $return, $should_echo );
	}

}
if ( ! function_exists( 'implecode_plus' ) ) {

	/**
	 * Displays a plus notice.
	 *
	 * @param string $text Notice content.
	 * @param int    $should_echo Whether to echo the output.
	 *
	 * @return string|null
	 */
	function implecode_plus( $text, $should_echo = 1 ) {
		return echo_ic_setting( '<div class="al-box plus">' . $text . '</div>', $should_echo );
	}

}
if ( ! function_exists( 'ic_is_message_hidden' ) ) {

	/**
	 * Checks whether a message hash is hidden.
	 *
	 * @param string $message Message HTML.
	 *
	 * @return bool
	 */
	function ic_is_message_hidden( $message ) {
		$hidden = get_option( 'ic_hidden_boxes', array() );
		if ( ! is_array( $hidden ) ) {
			$hidden = array();
		}
		$hash = ic_message_hash( $message );
		if ( in_array( $hash, $hidden, true ) ) {
			return true;
		}
		if ( get_current_user_id() ) {
			$user_hidden = get_user_meta( get_current_user_id(), '_ic_hidden_boxes', true );
			if ( ! is_array( $user_hidden ) ) {
				$user_hidden = array();
			}
			if ( in_array( $hash, $user_hidden, true ) ) {
				return true;
			}
		}

		return false;
	}

}
if ( ! function_exists( 'ic_ajax_hide_message' ) ) {

	add_action( 'wp_ajax_ic_ajax_hide_message', 'ic_ajax_hide_message' );

	/**
	 * Hides a dismissible admin message.
	 *
	 * @param mixed $message Unused callback argument.
	 */
	function ic_ajax_hide_message( $message ) {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! empty( $nonce ) && wp_verify_nonce( $nonce, 'ic-ajax-nonce' ) ) {
			$hash = isset( $_POST['hash'] ) ? sanitize_text_field( wp_unslash( $_POST['hash'] ) ) : '';
			if ( ! empty( $hash ) ) {
				if ( get_current_user_id() ) {
					$hidden = get_user_meta( get_current_user_id(), '_ic_hidden_boxes', true );
				} else {
					$hidden = get_option( 'ic_hidden_boxes', array() );
				}
				if ( ! is_array( $hidden ) ) {
					$hidden = array();
				}
				if ( ! in_array( $hash, $hidden, true ) ) {
					$hidden[] = $hash;
					// Only the last branch writes the site-wide option, so only it requires the
					// settings capability. Dismissing your own admin message stays available to
					// any logged-in user through the self-scoped user-meta branch above.
					// phpcs:ignore WordPress.WP.Capabilities.Unknown -- Custom capability is registered by the plugin.
					$can_hide_site_wide = current_user_can( 'manage_product_settings' );
					if ( get_current_user_id() ) {
						update_user_meta( get_current_user_id(), '_ic_hidden_boxes', $hidden );
					} elseif ( $can_hide_site_wide ) {
						update_option( 'ic_hidden_boxes', $hidden, false );
					}
				}
			}
		}
		wp_die();
	}

}
if ( ! function_exists( 'ic_message_hash' ) ) {

	/**
	 * Generates a message hash.
	 *
	 * @param string $message Message HTML.
	 *
	 * @return string
	 */
	function ic_message_hash( $message ) {
		return hash( 'md5', stripslashes( $message ) );
	}

}

if ( ! function_exists( 'implecode_settings_text_color' ) ) {

	/**
	 * Displays a color picker text setting.
	 *
	 * @param string      $option_label Option label.
	 * @param string      $option_name Option name.
	 * @param string      $option_value Option value.
	 * @param string|null $required Required attribute value.
	 * @param int         $should_echo Whether to echo the output.
	 * @param string|null $input_class Input class.
	 * @param string|null $change Color picker change handler config.
	 *
	 * @return string
	 */
	function implecode_settings_text_color(
		$option_label,
		$option_name,
		$option_value,
		$required = null,
		$should_echo = 1,
		$input_class = null,
		$change = null
	) {
		if ( ! empty( $required ) ) {
			$regired_field = 'required="required"';
			$star          = '<span class="star"> *</span>';
		} else {
			$regired_field = '';
			$star          = '';
		}
		$return  = '<tr>';
		$return .= '<td>' . $option_label . $star . ':</td>';
		$return .= '<td><input ' . $regired_field . ' class="color-picker ' . $input_class . '" type="text" name="' . $option_name . '" value="' . $option_value . '" /></td>';
		$return .= '<script>jQuery(document).ready(function() {jQuery("input[name=\'' . $option_name . '\']").wpColorPicker(' . $change . ');});</script>';
		$return .= '</tr>';
		ic_register_setting( $option_label, $option_name );

		return echo_ic_setting( $return, $should_echo );
	}

}
if ( ! function_exists( 'ic_catalog_item_name' ) ) {

	/**
	 * Returns single catalog item name
	 *
	 * @param bool $plural Whether to return the plural form.
	 * @param bool $uppercase Whether to uppercase the first letter.
	 *
	 * @return string
	 */
	function ic_catalog_item_name( $plural = true, $uppercase = false ) {
		if ( is_plural_form_active() ) {
			$names = get_catalog_names();
			if ( $plural ) {
				$item_name = $names['plural'];
			} else {
				$item_name = $names['singular'];
			}
		} elseif ( $plural ) {
			$item_name = __( 'items', 'post-type-x' );
		} else {
			$item_name = __( 'item', 'post-type-x' );
		}
		if ( $uppercase ) {
			$item_name = ic_ucfirst( $item_name );
		} else {
			$item_name = ic_lcfirst( $item_name );
		}

		return $item_name;
	}

}

if ( ! function_exists( 'ic_select_page' ) ) {

	/**
	 * Displays a page selector.
	 *
	 * @param string       $option_name Option name.
	 * @param string       $first_option Placeholder option label.
	 * @param string|array $selected_value Selected page value.
	 * @param bool         $buttons Whether to render action buttons.
	 * @param string|bool  $custom_view_url Custom view URL.
	 * @param int          $should_echo Whether to echo the output.
	 * @param bool         $custom Whether to show the custom URL option.
	 * @param string       $custom_content Extra markup after the select.
	 * @param array|bool   $create_new_button Create-new button config.
	 * @param bool         $multiple Whether to allow multiple selections.
	 * @param string       $select_class Select class.
	 *
	 * @return string
	 */
	function ic_select_page(
		$option_name,
		$first_option,
		$selected_value,
		$buttons = false,
		$custom_view_url = false,
		$should_echo = 1,
		$custom = false,
		$custom_content = '',
		$create_new_button = false,
		$multiple = false,
		$select_class = ''
	) {
		$create_page_request = isset( $_GET['ic_create_new_page_for_settings'] ) && is_scalar( $_GET['ic_create_new_page_for_settings'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['ic_create_new_page_for_settings'] ) ) : '';
		$create_page_nonce   = isset( $_GET['ic_create_page_nonce'] ) && is_scalar( $_GET['ic_create_page_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['ic_create_page_nonce'] ) ) : '';
		$create_page_action  = 'ic_create_page_for_settings_' . $option_name;
		if ( ( empty( $selected_value ) || 'noid' === $selected_value ) && ! empty( $create_new_button ) && is_array( $create_new_button ) ) {
			if ( $create_page_request === $option_name && wp_verify_nonce( $create_page_nonce, $create_page_action ) ) {
				$selected_value = ic_create_page_for_settings( $create_new_button['title'], $create_new_button['content'], $create_new_button['option'], $create_new_button['option_sub'] );
			}
			if ( empty( $selected_value ) || 'noid' === $selected_value ) {
				$create_page_url = add_query_arg(
					array(
						'ic_create_new_page_for_settings' => $option_name,
						'ic_create_page_nonce'            => wp_create_nonce( $create_page_action ),
					)
				);
				$custom_content .= ' <a class="button button-small" style="vertical-align: middle;" href="' . esc_url( $create_page_url ) . '">' . __( 'Create New', 'post-type-x' ) . '</a>';
			}
		}
		$args  = array(
			'orderby'        => 'title',
			'order'          => 'asc',
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'private' ),
			'posts_per_page' => - 1,
		);
		$pages = get_posts( apply_filters( 'ic_settings_select_page_args', $args ) );
		$attr  = '';
		if ( $multiple ) {
			$attr .= ' multiple';
		}
		$select_box = '<div class="select-page-wrapper"><select id="' . $option_name . '" name="' . $option_name . '" class="' . $select_class . '"' . $attr . '><option value = "noid">' . $first_option . '</option>';
		foreach ( $pages as $page ) {
			$selected = '';
			if ( $multiple && is_array( $selected_value ) ) {
				if ( in_array( $page->ID, $selected_value, true ) ) {
					$selected = 'selected';
				}
			} else {
				$selected = selected( $page->ID, $selected_value, 0 );
			}
			$select_box .= '<option name="' . $option_name . '[' . $page->ID . ']" value="' . $page->ID . '" ' . $selected . '>' . $page->post_title . '</option>';
		}
		if ( $custom ) {
			$select_box .= '<option value="custom"' . selected( 'custom', $selected_value, 0 ) . '>' . __( 'Custom URL', 'post-type-x' ) . '</option>';
		}
		$select_box .= '</select>';
		if ( $buttons && ( 'noid' !== $selected_value || '' !== $custom_view_url ) ) {
			$edit_link  = get_edit_post_link( $selected_value );
			$front_link = $custom_view_url ? $custom_view_url : get_permalink( $selected_value );
			if ( ! empty( $edit_link ) ) {
				$select_box .= ' <a class="button button-small" style="vertical-align: middle;" href="' . $edit_link . '">' . __( 'Edit', 'post-type-x' ) . '</a>';
			}
			if ( ! empty( $front_link ) ) {
				$select_box .= ' <a class="button button-small" style="vertical-align: middle;" href="' . $front_link . '">' . __( 'View Page', 'post-type-x' ) . '</a>';
			}
		}
		$select_box .= $custom_content;
		$select_box .= '</div>';
		ic_register_setting( $first_option, $option_name );

		return echo_ic_setting( $select_box, $should_echo );
	}

}

if ( ! function_exists( 'ic_create_page_for_settings' ) ) {

	/**
	 * Creates a page for a settings field.
	 *
	 * @param string      $title Page title.
	 * @param string      $content Page content.
	 * @param string      $option Option name.
	 * @param string|null $option_sub Nested option key.
	 *
	 * @return int|void
	 */
	function ic_create_page_for_settings( $title, $content, $option, $option_sub = null ) {

		if ( ! current_user_can( 'publish_pages' ) ) {
			return;
		}
		if ( empty( $option ) ) {
			return;
		}
		if ( ! empty( $option_sub ) ) {
			$current_option  = get_option( $option );
			$current_page_id = isset( $current_option[ $option_sub ] ) ? $current_option[ $option_sub ] : '';
		} else {
			$current_page_id = get_option( $option );
		}
		if ( ! empty( $current_page_id ) && 'noid' !== $current_page_id ) {
			return;
		}

		$product_page = array(
			'post_title'     => $title,
			'post_type'      => 'page',
			'post_content'   => $content,
			'post_status'    => 'publish',
			'comment_status' => 'closed',
		);

		$page_id = wp_insert_post( $product_page );
		if ( ! is_wp_error( $page_id ) ) {
			if ( ! empty( $option_sub ) ) {
				$option_value = get_option( $option );
				if ( empty( $option_value ) ) {
					$option_value = array();
				}
				if ( ! is_array( $option_value ) ) {
					return;
				}
				$option_value[ $option_sub ] = $page_id;
			} else {
				$option_value = $page_id;
			}
			update_option( $option, $option_value );
		}

		return $page_id;
	}

}
if ( ! function_exists( 'select_page' ) ) {

	/**
	 * Backward-compatible wrapper for the page selector.
	 *
	 * @param string      $option_name Option name.
	 * @param string      $first_option Placeholder option label.
	 * @param string|int  $selected_value Selected page value.
	 * @param bool        $buttons Whether to render action buttons.
	 * @param string|bool $custom_view_url Custom view URL.
	 * @param int         $should_echo Whether to echo the output.
	 * @param bool        $custom Whether to show the custom URL option.
	 *
	 * @return string
	 */
	function select_page(
		$option_name,
		$first_option,
		$selected_value,
		$buttons = false,
		$custom_view_url = false,
		$should_echo = 1,
		$custom = false
	) {
		return ic_select_page(
			$option_name,
			$first_option,
			$selected_value,
			$buttons,
			$custom_view_url,
			$should_echo,
			$custom
		);
	}

}
if ( ! function_exists( 'ic_register_setting' ) ) {

	/**
	 * Registers a setting label for the current admin page.
	 *
	 * @param string $option_label Option label.
	 * @param string $option_name Option name.
	 * @param string $option_tip Option tooltip.
	 */
	function ic_register_setting( $option_label, $option_name, $option_tip = '' ) {
		$page    = filter_input( INPUT_GET, 'page', FILTER_DEFAULT );
		$tab     = filter_input( INPUT_GET, 'tab', FILTER_DEFAULT );
		$submenu = filter_input( INPUT_GET, 'submenu', FILTER_DEFAULT );
		$page    = is_string( $page ) ? sanitize_text_field( $page ) : '';
		$tab     = is_string( $tab ) ? sanitize_text_field( $tab ) : '';
		$submenu = is_string( $submenu ) ? sanitize_text_field( $submenu ) : '';
		if ( empty( $option_label ) || empty( $option_name ) ) {
			return;
		}
		if ( ! is_ic_admin() ) {
			return;
		}
		if ( empty( $page ) ) {
			return;
		}
		if ( 'product-settings.php' !== $page ) {
			return;
		}
		$url_args            = array(
			'option_label' => $option_label,
			'option_tip'   => $option_tip,
			'tab'          => $tab,
			'submenu'      => $submenu,
		);
		$registered_settings = ic_get_registered_settings();

		global $ic_submenu_settings_updated;
		if ( empty( $ic_submenu_settings_updated ) ) {
			foreach ( $registered_settings as $key => $setting ) {
				if ( $setting['tab'] === $url_args['tab'] && $setting['submenu'] === $url_args['submenu'] ) {
					unset( $registered_settings[ $key ] );
				}
			}
		}
		$ic_submenu_settings_updated         = 1;
		$registered_settings[ $option_name ] = $url_args;
		update_option( 'ic_registered_settings', $registered_settings, false );
	}

}

if ( ! function_exists( 'ic_get_registered_settings' ) ) {

	/**
	 * Returns the registered settings cache.
	 *
	 * @return array
	 */
	function ic_get_registered_settings() {
		$registered_settings = get_option( 'ic_registered_settings', array() );
		if ( ! is_array( $registered_settings ) ) {
			$registered_settings = array();
		}

		return $registered_settings;
	}

}
