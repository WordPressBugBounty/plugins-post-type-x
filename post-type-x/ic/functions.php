<?php
/**
 * Shared framework utility functions.
 *
 * @package ImpleCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'ic_framework_require_once' ) ) {
	/**
	 * Require a file only once using a normalized path relative to the parent `ic` folder.
	 *
	 * @param string $file Absolute file path.
	 *
	 * @return bool
	 */
	function ic_framework_require_once( $file ) {
		static $loaded = array();

		$real_file = realpath( $file );
		if ( false === $real_file ) {
			_doing_it_wrong(
				__FUNCTION__,
				sprintf( 'IC Framework file is missing: %s', esc_html( $file ) ),
				'1.0'
			);

			return false;
		}

		$normalized_file = strtolower( str_replace( '\\', '/', $real_file ) );
		$ic_pos          = strrpos( $normalized_file, '/ic/' );

		if ( false === $ic_pos ) {
			$relative_file = strtolower( basename( $normalized_file ) );
		} else {
			$relative_file = substr( $normalized_file, $ic_pos + 4 );
		}

		if ( isset( $loaded[ $relative_file ] ) ) {
			return false;
		}

		$loaded[ $relative_file ] = true;

		require_once $real_file;

		return true;
	}
}

if ( ! function_exists( 'ic_strtolower' ) ) {
	/**
	 * Converts a string to lowercase.
	 *
	 * @param string $text Input string.
	 *
	 * @return string
	 */
	function ic_strtolower( $text ) {
		if ( function_exists( 'mb_strtolower' ) ) {
			return mb_strtolower( $text );
		}

		return strtolower( $text );
	}
}

if ( ! function_exists( 'ic_strtoupper' ) ) {
	/**
	 * Converts a string to uppercase.
	 *
	 * @param string $text Input string.
	 *
	 * @return string
	 */
	function ic_strtoupper( $text ) {
		if ( function_exists( 'mb_strtoupper' ) ) {
			return mb_strtoupper( $text );
		}

		return strtoupper( $text );
	}
}

if ( ! function_exists( 'ic_ucfirst' ) ) {
	/**
	 * Uppercases the first character of a string.
	 *
	 * @param string $text Input string.
	 *
	 * @return string
	 */
	function ic_ucfirst( $text ) {
		if ( ic_is_multibyte( $text ) ) {
			$first_char = mb_substr( $text, 0, 1 );
			$then       = mb_substr( $text, 1, null );

			return mb_strtoupper( $first_char ) . $then;
		}

		if ( function_exists( 'ucfirst' ) ) {
			return ucfirst( $text );
		}

		$text['0'] = strtoupper( $text['0'] );

		return $text;
	}
}

if ( ! function_exists( 'ic_substr' ) ) {
	/**
	 * Safe substring helper with multibyte support.
	 *
	 * @param string   $text   Input string.
	 * @param int      $start  Start position.
	 * @param int|null $length Length limit.
	 *
	 * @return string
	 */
	function ic_substr( $text, $start, $length ) {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $text, $start, intval( $length ) );
		}

		return substr( $text, $start, intval( $length ) );
	}
}

if ( ! function_exists( 'ic_string_contains' ) ) {
	/**
	 * Checks whether a string contains a given value.
	 *
	 * @param string     $text           Input string.
	 * @param mixed      $contains       Needle.
	 * @param bool|mixed $case_sensitive Whether the comparison is case-sensitive.
	 *
	 * @return bool
	 */
	function ic_string_contains( $text, $contains, $case_sensitive = true ) {
		if ( ! is_string( $text ) ) {
			return false;
		}
		if ( ! is_string( $contains ) ) {
			if ( is_array( $contains ) ) {
				return false;
			}
			$contains = strval( $contains );
		}
		if ( $case_sensitive && false !== strpos( $text, $contains ) ) {
			return true;
		} elseif ( ! $case_sensitive && false !== stripos( $text, $contains ) ) {
			return true;
		}

		return false;
	}
}

if ( ! function_exists( 'ic_error_log' ) ) {
	/**
	 * Logs debug information when enabled.
	 *
	 * @param mixed       $what  Value to log.
	 * @param string|bool $param Optional query flag required to log.
	 * @param bool        $cron  Whether the logger should run in cron.
	 *
	 * @return void
	 */
	function ic_error_log( $what, $param = false, $cron = false ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Debug helper only reads presence of query flags.
		$global_debug_enabled = defined( 'IC_FORCE_DEBUG_LOG' ) && IC_FORCE_DEBUG_LOG;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Debug helper only reads presence of query flags.
		$request_debug_enabled = isset( $_GET['ic_debug_log'] ) || isset( $_GET['ic-error-log'] );
		if ( ! $global_debug_enabled && ! $request_debug_enabled && ! $param ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Debug helper only reads presence of a query flag.
		if ( $param && ! isset( $_GET[ $param ] ) && ! is_ic_front_ajax() ) {
			return;
		}
		if ( $cron || ! wp_doing_cron() ) {
			$prefix = '';
			if ( is_ic_ajax() ) {
				$prefix = 'ajax ';
			}
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Intentional debug helper.
			error_log( $prefix . print_r( $what, 1 ) );
		}
	}
}

if ( ! function_exists( 'ic_filemtime' ) ) {
	/**
	 * Gets a cache-busting timestamp query string for a file.
	 *
	 * @param string $path File path.
	 *
	 * @return string|null
	 */
	function ic_filemtime( $path, $time_only = false ) {
		if ( file_exists( $path ) ) {
			if ( $time_only ) {
				return filemtime( $path );
			}
			return '?timestamp=' . filemtime( $path );
		}

		return null;
	}
}

if ( ! function_exists( 'ic_array_to_hidden_inputs' ) ) {
	/**
	 * Converts an array to hidden input fields.
	 *
	 * @param array  $items       Source data.
	 * @param string $name_prefix Current field name prefix.
	 * @param string $exclude     Name fragment to exclude.
	 *
	 * @return string
	 */
	function ic_array_to_hidden_inputs( $items, $name_prefix = '', $exclude = '' ) {
		if ( ! empty( $exclude ) && ic_string_contains( $name_prefix, $exclude ) ) {
			return '';
		}
		$html = '';
		foreach ( $items as $key => $value ) {
			if ( is_numeric( $key ) ) {
				$current_key = $name_prefix . "[$key]";
			} else {
				$current_key = '' === $name_prefix ? $key : $name_prefix . "[$key]";
			}

			if ( is_array( $value ) ) {
				$html .= ic_array_to_hidden_inputs( $value, $current_key, $exclude );
			} else {
				$input_name = $current_key;
				$html      .= '<input type="hidden" name="' . esc_attr( $input_name ) . '" value="' . esc_attr( $value ) . '">' . PHP_EOL;
			}
		}

		return $html;
	}
}

if ( ! function_exists( 'ic_array_key_last' ) ) {
	/**
	 * Polyfill for array_key_last().
	 *
	 * @param array $items Input array.
	 *
	 * @return int|string|null
	 */
	function ic_array_key_last( $items ) {
		if ( function_exists( 'array_key_last' ) ) {
			return array_key_last( $items );
		}
		if ( ! is_array( $items ) || empty( $items ) ) {
			return null;
		}

		return array_keys( $items )[ count( $items ) - 1 ];
	}
}

if ( ! function_exists( 'ic_get_role_display_name' ) ) {
	/**
	 * Gets a role display name.
	 *
	 * @param string $role Role slug.
	 *
	 * @return string
	 */
	function ic_get_role_display_name( $role ) {
		global $wp_roles;
		if ( isset( $wp_roles->roles[ $role ] ) ) {
			return $wp_roles->roles[ $role ]['name'];
		}

		return '';
	}
}

if ( ! function_exists( 'ic_get_posts' ) ) {
	/**
	 * Wrapper around get_posts() with framework meta key normalization.
	 *
	 * @param array $args Query arguments.
	 *
	 * @return array
	 */
	function ic_get_posts( $args ) {
		if ( ! empty( $args['meta_key'] ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Wrapper normalizes incoming query args.
			$args['meta_key'] = str_replace( '[]', '', $args['meta_key'] );
		}
		if ( ! empty( $args['meta_query'] ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Wrapper normalizes incoming query args.
			$args['meta_query'] = ic_process_meta_query( $args['meta_query'] );
		}

		return get_posts( $args );
	}
}

if ( ! function_exists( 'ic_get_post_meta_bulk' ) ) {
	/**
	 * Retrieves a unique set of meta values for many posts.
	 *
	 * @param array  $post_ids Post IDs.
	 * @param string $key      Meta key.
	 *
	 * @return array
	 */
	function ic_get_post_meta_bulk( $post_ids, $key ) {
		global $wpdb;
		if ( empty( $post_ids ) || empty( $key ) ) {
			return array();
		}
		$post_ids     = array_map( 'intval', (array) $post_ids );
		$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
		$query_values = array_merge( $post_ids, array( $key ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk lookup helper intentionally uses a direct query.
			$meta_values = $wpdb->get_col(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholder list is generated from sanitized post ID count.
					"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id IN ($placeholders) AND meta_key = %s",
					$query_values
				)
			);

		return array_unique( $meta_values );
	}
}

if ( ! function_exists( 'ic_process_meta_query' ) ) {
	/**
	 * Normalizes meta query keys recursively.
	 *
	 * @param array $meta_query Meta query clauses.
	 *
	 * @return array
	 */
	function ic_process_meta_query( $meta_query ) {
		foreach ( $meta_query as $index => $sub_meta_query ) {
			if ( isset( $sub_meta_query['key'] ) && is_string( $sub_meta_query['key'] ) ) {
				$meta_query[ $index ]['key'] = str_replace( '[]', '', $sub_meta_query['key'] );
			} elseif ( is_array( $sub_meta_query ) ) {
				$meta_query[ $index ] = ic_process_meta_query( $sub_meta_query );
			}
		}

		return $meta_query;
	}
}

if ( ! function_exists( 'ic_get_post_meta' ) ) {
	/**
	 * Gets post meta using the normalized framework meta key.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key.
	 * @param bool   $single   Whether to return a single value.
	 *
	 * @return mixed
	 */
	function ic_get_post_meta( $post_id, $meta_key, $single = false ) {
		return get_post_meta( $post_id, ic_get_meta_key( $meta_key ), $single );
	}
}

if ( ! function_exists( 'ic_get_meta_key' ) ) {
	/**
	 * Normalizes a meta key.
	 *
	 * @param string $meta_key Meta key.
	 *
	 * @return string
	 */
	function ic_get_meta_key( $meta_key ) {
		return str_replace( '[]', '', $meta_key );
	}
}

if ( ! function_exists( 'ic_update_post_meta' ) ) {
	/**
	 * Updates post meta using the normalized framework meta key.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 *
	 * @return int|bool
	 */
	function ic_update_post_meta( $post_id, $meta_key, $meta_value ) {
		return update_post_meta( $post_id, ic_get_meta_key( $meta_key ), $meta_value );
	}
}

if ( ! function_exists( 'ic_current_page_url' ) ) {
	/**
	 * Get current page URL from server global.
	 *
	 * @return string
	 */
	function ic_current_page_url() {
		if ( is_ic_ajax() ) {
			if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
				return esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
			}
			if ( function_exists( 'product_listing_url' ) ) {
				return product_listing_url();
			}
		} elseif ( isset( $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'] ) ) {
			$page_url = 'http';
			if ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ) {
				$page_url .= 's';
			}

			return esc_url_raw(
				$page_url . '://' . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) . wp_unslash( $_SERVER['REQUEST_URI'] )
			);
		}

		return '';
	}
}

if ( ! function_exists( 'implecode_array_variables_init' ) ) {
	/**
	 * Ensures array keys exist for a field list.
	 *
	 * @param array $fields Field names.
	 * @param array $data   Source data.
	 *
	 * @return array
	 */
	function implecode_array_variables_init( $fields, $data = array() ) {
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		foreach ( $fields as $field ) {
			$data[ $field ] = isset( $data[ $field ] ) ? $data[ $field ] : '';
		}

		return $data;
	}
}

if ( ! function_exists( 'ic_array_to_url' ) ) {
	/**
	 * Encodes an array into a URL-safe JSON string.
	 *
	 * @param array $items Array data.
	 *
	 * @return string
	 */
	function ic_array_to_url( $items ) {
		return rawurlencode( wp_json_encode( $items ) );
	}
}

if ( ! function_exists( 'ic_url_to_array' ) ) {
	/**
	 * Decodes a URL payload back to array data.
	 *
	 * @param string $url              Encoded payload.
	 * @param bool   $maybe_serialized Whether legacy serialized payloads are allowed.
	 *
	 * @return mixed
	 */
	function ic_url_to_array( $url, $maybe_serialized = true ) {
		$data = stripslashes( urldecode( $url ) );
		if ( $maybe_serialized && is_serialized( $data ) ) { // Don't attempt to unserialize data that wasn't serialized going in.
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Needed for legacy payload compatibility.
			return unserialize( trim( $data ), array( 'allowed_classes' => false ) );
		}

		$json_data = json_decode( trim( $data ), true );
		if ( JSON_ERROR_NONE === json_last_error() ) {
			return $json_data;
		}

		return $data;
	}
}

if ( ! function_exists( 'ic_sanitize_decoded_payload' ) ) {
	/**
	 * Decodes a URL payload before recursively sanitizing its scalar fields.
	 *
	 * @param mixed $payload URL-encoded JSON or legacy serialized payload.
	 * @return array Sanitized decoded structure, or an empty array.
	 */
	function ic_sanitize_decoded_payload( $payload ) {

		if ( ! is_scalar( $payload ) ) {
				return array();
		}

		$data    = urldecode( wp_unslash( (string) $payload ) );
		$decoded = json_decode( trim( $data ), true );
		if ( JSON_ERROR_NONE !== json_last_error() && is_serialized( $data ) ) {
			  // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Required for validated legacy handoff payload compatibility; object creation is disabled.
				$decoded = unserialize( trim( $data ), array( 'allowed_classes' => false ) );
		}
		if ( is_array( $decoded ) ) {
			return ic_sanitize_decoded_payload_values( $decoded );
		}

		return array();
	}
}

if ( ! function_exists( 'ic_sanitize_decoded_payload_values' ) ) {
	/**
	 * Recursively sanitizes values from an already decoded payload.
	 *
	 * @param array $values Decoded values.
	 * @return array
	 */
	function ic_sanitize_decoded_payload_values( $values ) {

		$clean = array();
		foreach ( $values as $key => $value ) {
			$clean[ $key ] = is_array( $value ) ? ic_sanitize_decoded_payload_values( $value ) : sanitize_text_field( (string) $value );
		}

		return $clean;
	}
}
if ( ! function_exists( 'ic_get_site_name' ) ) {
	/**
	 * Gets the current site name.
	 *
	 * @return string
	 */
	function ic_get_site_name() {
		if ( is_multisite() ) {
			$site_name = get_network()->site_name;
		} else {
			/*
			 * The blogname option is escaped with esc_html on the way into the database
			 * in sanitize_option. We want to reverse this for the plain text arena of emails.
			 */
			$site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		}

		return $site_name;
	}
}
