<?php
/**
 * Session handler bootstrap.
 *
 * @package impleCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Session handler class.
 *
 * Uses a custom table for session storage. Based on
 * https://github.com/kloon/woocommerce-large-sessions.
 *
 * @version 1.0.0
 * @author  impleCode
 */
class IC_Session {
	/**
	 * Cache prefix.
	 *
	 * @var string $_cache_prefix Cache prefix.
	 */
	protected $_cache_prefix = 'ic_cache'; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore -- Legacy session property is part of the compatibility API.
	/**
	 * Cache group.
	 *
	 * @var string $cache_group Cache group.
	 */
	protected $_cache_group = 'implecode'; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore -- Legacy session property is part of the compatibility API.
	/**
	 * Customer ID.
	 *
	 * @var int $_customer_id Customer ID.
	 */
	protected $_customer_id; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore -- Legacy session property is part of the compatibility API.

	/**
	 * Guest customer ID prefix.
	 *
	 * @var string $_random_customer_id_prefix Prefix.
	 */
	protected $_random_customer_id_prefix = 'ic_'; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore -- Legacy session property is part of the compatibility API.

	/**
	 * Session Data.
	 *
	 * @var array $_data Data array.
	 */
	protected $_data = array(); // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore -- Legacy session property is part of the compatibility API.

	/**
	 * To save when the session needs saving.
	 *
	 * @var bool $_to_save When something changes
	 */
	protected $_to_save = false; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore -- Legacy session property is part of the compatibility API.

	/**
	 * Cookie name used for the session.
	 *
	 * @var string cookie name
	 */
	protected $_cookie; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore -- Legacy session property is part of the compatibility API.

	/**
	 * Stores session expiry.
	 *
	 * @var string session due to expire timestamp
	 */
	protected $_session_expiring; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore -- Legacy session property is part of the compatibility API.

	/**
	 * Stores session due to expire timestamp.
	 *
	 * @var string session expiration timestamp
	 */
	protected $_session_expiration; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore -- Legacy session property is part of the compatibility API.

	/**
	 * True when the cookie exists.
	 *
	 * @var bool Based on whether a cookie exists.
	 */
	protected $_has_cookie = false; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore -- Legacy session property is part of the compatibility API.

	/**
	 * Table name for session data.
	 *
	 * @var string Custom session table name
	 */
	protected $_table; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore -- Legacy session property is part of the compatibility API.

	/**
	 * Array containing all data including to save
	 *
	 * @var array All stored data
	 */
	public $all_data = array();

	/**
	 * Constructor for the session class.
	 */
	public function __construct() {
		$this->_cookie = 'wordpress_ic_session_' . COOKIEHASH;
		$this->_table  = $GLOBALS['wpdb']->prefix . 'ic_sessions';
	}

	/**
	 * Init hooks and session data.
	 */
	public function init() {
		if ( ! $this->create_table() ) {
			return false;
		}
		$this->init_session_cookie();

		add_action( 'shutdown', array( $this, 'save_data' ), 20 );
		add_filter( 'wp_die_ajax_handler', array( $this, 'save_data_filter' ) );
		add_filter( 'wp_redirect', array( $this, 'save_data_filter' ) );
		add_action( 'wp_logout', array( $this, 'destroy_session' ) );
		add_action( 'ic_cleanup_sessions', array( $this, 'cleanup_sessions' ), 10, 0 );
		if ( ! wp_next_scheduled( 'ic_cleanup_sessions' ) ) {
			wp_schedule_event( time() + ( 6 * HOUR_IN_SECONDS ), 'twicedaily', 'ic_cleanup_sessions' );
		}

		return true;
	}

	/**
	 * Setup cookie and customer ID.
	 */
	public function init_session_cookie() {
		$cookie = $this->get_session_cookie();
		if ( $cookie ) {
			// Customer ID will be an MD5 hash id this is a guest session.
			$this->_customer_id        = $cookie[0];
			$this->_session_expiration = $cookie[1];
			$this->_session_expiring   = $cookie[2];
			$this->_has_cookie         = true;
			$this->_data               = $this->get_session_data();

			if ( ! $this->is_session_cookie_valid() ) {
				$this->destroy_session();
				$this->set_session_expiration();
			}

			// If the user logs in, merge the stale guest cookie into the user session.
			// Alternate auth flows such as 2FA can delay the cookie refresh by one request.
			if ( is_user_logged_in() && strval( get_current_user_id() ) !== $this->_customer_id ) {
				$this->adopt_customer_id( get_current_user_id() );
			}

			// Update session if it's close to expiring.
			if ( time() > $this->_session_expiring ) {
				$this->set_session_expiration();
				$this->update_session_timestamp( $this->_customer_id, $this->_session_expiration );
			}
		} else {
			$this->set_session_expiration();
			$this->_customer_id = $this->generate_customer_id();
			$this->_data        = $this->get_session_data();
		}
	}

	/**
	 * Checks if session cookie is expired, or belongs to a logged-out user.
	 *
	 * @return bool Whether session cookie is valid.
	 */
	private function is_session_cookie_valid() {
		// If session is expired, session cookie is invalid.
		if ( time() > $this->_session_expiration ) {
			return false;
		}

		// If user has logged out, session cookie is invalid.
		if ( ! is_user_logged_in() && ! $this->is_customer_guest( $this->_customer_id ) ) {

			return false;
		}

		// Session from a different user is not valid, although a guest session remains valid.
		if ( is_user_logged_in() && ! $this->is_customer_guest( $this->_customer_id ) && strval( get_current_user_id() ) !== strval( $this->_customer_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Sets the session cookie on-demand.
	 */
	public function set_customer_session_cookie() {
		$to_hash           = $this->_customer_id . '|' . $this->_session_expiration;
		$cookie_hash       = hash_hmac( 'md5', $to_hash, wp_hash( $to_hash ) );
		$cookie_value      = $this->_customer_id . '||' . $this->_session_expiration . '||' . $this->_session_expiring . '||' . $cookie_hash;
		$this->_has_cookie = true;
		if ( ! isset( $_COOKIE[ $this->_cookie ] ) || $_COOKIE[ $this->_cookie ] !== $cookie_value ) {
			ic_setcookie( $this->_cookie, $cookie_value, $this->_session_expiration, $this->use_secure_cookie(), true );
		}
	}

	/**
	 * Should the session cookie be secure?
	 *
	 * @return bool
	 */
	protected function use_secure_cookie() {
		return ic_site_is_https() && is_ssl();
	}

	/**
	 * Return true if the current user has an active session, i.e. a cookie to retrieve values.
	 *
	 * @return bool
	 */
	public function has_session() {
		return isset( $_COOKIE[ $this->_cookie ] ) || $this->_has_cookie || is_user_logged_in(); // @codingStandardsIgnoreLine.
	}

	/**
	 * Set session expiration.
	 */
	public function set_session_expiration() {
		$this->_session_expiring   = time() + intval( apply_filters( 'ic_session_expiration', 3 * DAY_IN_SECONDS ) );
		$this->_session_expiration = $this->_session_expiring + HOUR_IN_SECONDS;
	}

	/**
	 * Generate a unique customer ID for guests, or return user ID if logged in.
	 *
	 * Uses Portable PHP password hashing framework to generate a unique cryptographically strong ID.
	 *
	 * @return string
	 */
	public function generate_customer_id() {
		$customer_id = '';

		if ( is_user_logged_in() ) {
			$customer_id = strval( get_current_user_id() );
		}

		if ( empty( $customer_id ) ) {
			require_once ABSPATH . 'wp-includes/class-phpass.php';
			$hash        = new PasswordHash( 8, false );
			$customer_id = $this->_random_customer_id_prefix . substr( md5( $hash->get_random_bytes( 32 ) ), strlen( $this->_random_customer_id_prefix ) );
		}

		return $customer_id;
	}

	/**
	 * Checks if this is an auto-generated customer ID.
	 *
	 * @param string|int $customer_id Customer ID to check.
	 *
	 * @return bool Whether customer ID is randomly generated.
	 */
	private function is_customer_guest( $customer_id ) {
		$customer_id = strval( $customer_id );
		if ( empty( $customer_id ) ) {
			return true;
		}
		if ( substr( $customer_id, 0, strlen( $this->_random_customer_id_prefix ) ) === $this->_random_customer_id_prefix ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Get the session cookie, if set. Otherwise, return false.
	 *
	 * Session cookies without a customer ID are invalid.
	 *
	 * @return bool|array
	 */
	public function get_session_cookie() {
		$cookie_value = isset( $_COOKIE[ $this->_cookie ] ) ? wp_unslash( $_COOKIE[ $this->_cookie ] ) : false; // @codingStandardsIgnoreLine.
		if ( empty( $cookie_value ) || ! is_string( $cookie_value ) ) {
			return false;
		}

		if ( substr_count( $cookie_value, '||' ) < 3 ) {
			return false;
		}

		list( $customer_id, $session_expiration, $session_expiring, $cookie_hash ) = explode( '||', $cookie_value );

		if ( empty( $customer_id ) ) {
			return false;
		}

		// Validate hash.
		$to_hash = $customer_id . '|' . $session_expiration;
		$hash    = hash_hmac( 'md5', $to_hash, wp_hash( $to_hash ) );

		if ( empty( $cookie_hash ) || ! hash_equals( $hash, $cookie_hash ) ) {
			return false;
		}

		return array( $customer_id, $session_expiration, $session_expiring, $cookie_hash );
	}

	/**
	 * Get session data.
	 *
	 * @return array
	 */
	public function get_session_data() {
		return $this->has_session() ? (array) $this->get_session( $this->_customer_id, array() ) : array();
	}

	/**
	 * Gets a cache prefix. This is used in session names so the entire cache can be invalidated with 1 function call.
	 *
	 * @return string
	 */
	private function get_cache_prefix() {
		$time = wp_cache_get( $this->_cache_prefix . '_cache_prefix', $this->_cache_group );

		if ( false === $time ) {
			$time = microtime();
			wp_cache_set( $this->_cache_prefix . '_cache_prefix', $time, $this->_cache_group );
		}

		return $this->_cache_prefix . '_' . $time;
	}

	/**
	 * Save data and delete guest session.
	 *
	 * @param int $old_session_key session ID before user logs in.
	 */
	public function save_data( $old_session_key = 0 ) {
		// Dirty if something changed - prevents saving nothing new.
		if ( $this->_to_save && $this->has_session() ) {
			global $wpdb;
			$session_value = maybe_serialize( $this->_data );
			$existing      = $this->get_existing_session_row( $this->_customer_id );

			if ( ! empty( $existing ) && $session_value === $existing['session_value'] ) {
				if ( intval( $existing['session_expiry'] ) !== intval( $this->_session_expiration ) ) {
					$this->update_session_timestamp( $this->_customer_id, $this->_session_expiration );
					wp_cache_set( $this->get_cache_prefix() . $this->_customer_id, $this->_data, $this->_cache_group, $this->_session_expiration - time() );
				}
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- The identifier is the fixed prefixed session table; values are prepared and rows are cached manually.
				$wpdb->query(
					$wpdb->prepare(
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The identifier is the fixed prefixed session table and all values use placeholders.
						"INSERT INTO {$this->_table} (`session_key`, `session_value`, `session_expiry`) VALUES (%s, %s, %d)
						ON DUPLICATE KEY UPDATE `session_value` = VALUES(`session_value`), `session_expiry` = VALUES(`session_expiry`)",
						$this->_customer_id,
						$session_value,
						$this->_session_expiration
					)
				);
				wp_cache_set( $this->get_cache_prefix() . $this->_customer_id, $this->_data, $this->_cache_group, $this->_session_expiration - time() );
			}
			$this->_to_save = false;
			if ( ! empty( $old_session_key ) && get_current_user_id() !== $old_session_key && ! is_object( get_user_by( 'id', $old_session_key ) ) ) {
				$this->delete_session( $old_session_key );
			}
		}
	}

	/**
	 * Save session data before returning a filtered value.
	 *
	 * @param mixed $value Filtered value.
	 *
	 * @return mixed
	 */
	public function save_data_filter( $value ) {
		$this->save_data();

		return $value;
	}

	/**
	 * Rebinds the active session to a logged-in customer.
	 *
	 * This is used by front-end auth flows that establish the WordPress user
	 * mid-request and need the catalog session to persist under that user key
	 * immediately, rather than waiting for the next request bootstrap.
	 *
	 * @param int|string $customer_id Target customer ID.
	 *
	 * @return bool
	 */
	public function adopt_customer_id( $customer_id ) {
		$customer_id = strval( absint( $customer_id ) );
		if ( empty( $customer_id ) ) {
			return false;
		}

		if ( strval( $this->_customer_id ) === $customer_id ) {
			$this->all_data = array();
			$this->set_customer_session_cookie();

			return true;
		}

		$old_customer_id = strval( $this->_customer_id );
		$current_data    = $this->get();
		$target_data     = (array) $this->get_session( $customer_id, array() );
		$merged_data     = array_merge( $target_data, $current_data );

		$this->_customer_id = $customer_id;
		$this->_data        = array();
		$this->all_data     = array();
		$this->_to_save     = false;

		foreach ( $merged_data as $key => $value ) {
			$this->set( $key, $value );
		}

		$this->save_data( $old_customer_id );
		$this->set_customer_session_cookie();
		do_action( 'ic_session_customer_id_adopted', $old_customer_id, $customer_id, $this );

		return true;
	}

	/**
	 * Destroy all session data.
	 */
	public function destroy_session() {
		$this->delete_session( $this->_customer_id );
		$this->forget_session();
	}

	/**
	 * Forget all session data without destroying it.
	 */
	public function forget_session() {
		ic_setcookie( $this->_cookie, '', time() - YEAR_IN_SECONDS, $this->use_secure_cookie(), true );

		$this->_data        = array();
		$this->_to_save     = false;
		$this->_customer_id = $this->generate_customer_id();
	}

	/**
	 * Cleanup session data from the database and clear caches.
	 */
	public function cleanup_sessions() {
		global $wpdb;

		$batch_size = absint( apply_filters( 'ic_session_cleanup_batch_size', 500 ) );
		if ( 0 === $batch_size ) {
			$batch_size = 500;
		}

		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM $this->_table WHERE session_expiry < %d ORDER BY session_id ASC LIMIT %d", time(), $batch_size ) ); // @codingStandardsIgnoreLine.

		if ( ! empty( $deleted ) ) {
			wp_cache_set( $this->_cache_prefix . '_cache_prefix', microtime(), $this->_cache_group );
		}

		if ( intval( $deleted ) >= $batch_size && ! wp_next_scheduled( 'ic_cleanup_sessions', array( 'batch' ) ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'ic_cleanup_sessions', array( 'batch' ) );
		}
	}

	/**
	 * Gets an existing stored session row.
	 *
	 * @param string|int $customer_id Customer ID.
	 *
	 * @return array|null
	 */
	private function get_existing_session_row( $customer_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- The identifier is the fixed prefixed session table; the value is prepared and the row is cached manually.
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT `session_value`, `session_expiry` FROM {$this->_table} WHERE `session_key` = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The identifier is the fixed prefixed session table and the key uses a placeholder.
				$customer_id
			),
			ARRAY_A
		);
	}

	/**
	 * Returns the session.
	 *
	 * @param string $customer_id Customer ID.
	 * @param mixed  $default Default session value.
	 *
	 * @return string|array
	 */
	public function get_session( $customer_id, $default = false ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound -- Legacy method parameter is part of the compatibility API.
		global $wpdb;

		if ( defined( 'WP_SETUP_CONFIG' ) ) {
			return false;
		}

		$value        = wp_cache_get( $this->get_cache_prefix() . $customer_id, $this->_cache_group );
		$value_source = 'cache';
		if ( false === $value ) {
			$value_source = 'db';
			$value = $wpdb->get_var( $wpdb->prepare( "SELECT session_value FROM $this->_table WHERE session_key = %s", $customer_id ) ); // @codingStandardsIgnoreLine.

			if ( is_null( $value ) ) {
				$value = $default;
			}

			$cache_duration = $this->_session_expiration - time();
			if ( 0 < $cache_duration ) {
				wp_cache_add( $this->get_cache_prefix() . $customer_id, $value, $this->_cache_group, $cache_duration );
			}
		}

		$decoded = $this->decode_session_value( $value, $default );
		if ( ! $decoded['valid'] ) {
			$this->delete_session( $customer_id );

			return $default;
		}

		return $decoded['value'];
	}

	/**
	 * Delete the session from the cache and database.
	 *
	 * @param int $customer_id Customer ID.
	 */
	public function delete_session( $customer_id ) {
		global $wpdb;

		wp_cache_delete( $this->get_cache_prefix() . $customer_id, $this->_cache_group );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Legacy session persistence uses the established wpdb API.
		$wpdb->delete(
			$this->_table,
			array(
				'session_key' => $customer_id,
			)
		);
	}

	/**
	 * Update the session expiry timestamp.
	 *
	 * @param string $customer_id Customer ID.
	 * @param int    $timestamp Timestamp to expire the cookie.
	 */
	public function update_session_timestamp( $customer_id, $timestamp ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Legacy session persistence uses the established wpdb API.
		$wpdb->update(
			$this->_table,
			array(
				'session_expiry' => $timestamp,
			),
			array(
				'session_key' => $customer_id,
			),
			array(
				'%d',
			)
		);
	}

	/**
	 * Magic get method.
	 *
	 * @param mixed $key Key to get.
	 *
	 * @return mixed
	 */
	public function __get( $key ) {
		return $this->get( $key );
	}

	/**
	 * Magic set method.
	 *
	 * @param mixed $key Key to set.
	 * @param mixed $value Value to set.
	 */
	public function __set( $key, $value ) {
		$this->set( $key, $value );
	}

	/**
	 * Magic isset method.
	 *
	 * @param mixed $key Key to check.
	 *
	 * @return bool
	 */
	public function __isset( $key ) {
		return isset( $this->_data[ sanitize_title( $key ) ] );
	}

	/**
	 * Magic unset method.
	 *
	 * @param mixed $key Key to unset.
	 */
	public function __unset( $key ) {
		if ( isset( $this->_data[ $key ] ) ) {
			unset( $this->_data[ $key ] );
			$this->_to_save = true;
		}
	}

	/**
	 * Get a session variable.
	 *
	 * @param string $key Key to get.
	 * @param mixed  $default used if the session variable isn't set.
	 *
	 * @return array|string value of session variable
	 */
	public function get( $key = '', $default = null ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound -- Legacy method parameter is part of the compatibility API.
		if ( empty( $key ) ) {

			if ( ! empty( $this->all_data ) ) {
				return $this->all_data;
			}
			$all_data = $this->get_session_data();

			if ( ! empty( $this->_data ) && $this->_to_save ) {
				$all_data = array_merge( $all_data, $this->_data );
			}

			$return_data = array();
			foreach ( $all_data as $key => $value ) {
				$return_data[ $key ] = $this->get( $key );
			}
			$this->all_data = $return_data;

			return $this->all_data;
		}
		$key = sanitize_key( $key );

		if ( ! isset( $this->_data[ $key ] ) ) {
			return $default;
		}

		$decoded = $this->decode_session_value( $this->_data[ $key ], $default );
		if ( ! $decoded['valid'] ) {
			unset( $this->_data[ $key ] );
			unset( $this->all_data[ $key ] );
			$this->_to_save = true;

			return $default;
		}

		return $decoded['value'];
	}

	/**
	 * Decodes one stored session value when it is safe to unserialize.
	 *
	 * @param mixed $value   Raw stored value.
	 * @param mixed $default Default value returned for invalid payloads.
	 *
	 * @return array{valid:bool,value:mixed,reason:string}
	 */
	private function decode_session_value( $value, $default = null ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound -- Legacy method parameter is part of the compatibility API.
		if ( ! is_string( $value ) ) {
			return array(
				'valid'  => true,
				'value'  => $value,
				'reason' => 'not_string',
			);
		}

		$value = trim( $value );
		if ( ! is_serialized( $value ) ) {
			return array(
				'valid'  => true,
				'value'  => $value,
				'reason' => 'not_serialized',
			);
		}

		$safety = $this->session_payload_safety( $value );
		if ( ! $safety['valid'] ) {
			return array(
				'valid'  => false,
				'value'  => $default,
				'reason' => $safety['reason'],
			);
		}

		return array(
			'valid'  => true,
			'value'  => maybe_unserialize( $value ),
			'reason' => 'decoded',
		);
	}

	/**
	 * Returns whether a serialized session payload is safe to unserialize.
	 *
	 * @param string $value Serialized value.
	 *
	 * @return bool
	 */
	private function is_session_payload_safe_to_unserialize( $value ) {
		$safety = $this->session_payload_safety( $value );

		return $safety['valid'];
	}

	/**
	 * Returns the validation result for one serialized session payload.
	 *
	 * @param string $value Serialized value.
	 *
	 * @return array{valid:bool,reason:string}
	 */
	private function session_payload_safety( $value ) {
		$max_length = absint( apply_filters( 'ic_session_serialized_payload_max_length', MB_IN_BYTES ) );
		if ( empty( $max_length ) ) {
			$max_length = MB_IN_BYTES;
		}
		if ( strlen( $value ) > $max_length ) {
			return array(
				'valid'  => false,
				'reason' => 'payload_too_large',
			);
		}

		if ( preg_match( '/(^|;|{|})(O|C):\d+:"/m', $value ) ) {
			return array(
				'valid'  => false,
				'reason' => 'serialized_object_not_allowed',
			);
		}

		$max_collection_size = absint( apply_filters( 'ic_session_serialized_collection_max_items', 5000 ) );
		if ( empty( $max_collection_size ) ) {
			$max_collection_size = 5000;
		}
		if ( preg_match_all( '/(^|;|{|})a:(\d+):\{/m', $value, $array_matches ) ) {
			foreach ( $array_matches[2] as $collection_size ) {
				if ( absint( $collection_size ) > $max_collection_size ) {
					return array(
						'valid'  => false,
						'reason' => 'collection_too_large',
					);
				}
			}
		}

		if ( preg_match_all( '/(^|;|{|})s:(\d+):"/m', $value, $string_matches ) ) {
			foreach ( $string_matches[2] as $string_length ) {
				$string_length = absint( $string_length );
				if ( $string_length > $max_length || $string_length > strlen( $value ) ) {
					return array(
						'valid'  => false,
						'reason' => 'declared_string_length_invalid',
					);
				}
			}
		}

		return array(
			'valid'  => true,
			'reason' => 'safe',
		);
	}

	/**
	 * Set a session variable.
	 *
	 * @param string $key Key to set.
	 * @param mixed  $value Value to set.
	 */
	public function set( $key, $value ) {
		if ( $value !== $this->get( $key ) ) {
			$sanitized_key                 = sanitize_key( $key );
			$this->_data[ $sanitized_key ] = maybe_serialize( $value );
			if ( ! empty( $this->all_data ) ) {
				$this->all_data[ $sanitized_key ] = $value;
			}
			$this->_to_save = true;
		}
	}

	/**
	 * Replace multiple session variables
	 *
	 * @param array $new_data   Data to be replaced.
	 * @param bool  $clear_old Whether to remove values missing from the replacement set.
	 */
	public function replace( $new_data, $clear_old = true ) {
		if ( $clear_old ) {
			foreach ( $this->all_data as $key => $value ) {
				if ( ! isset( $new_data[ $key ] ) ) {
					unset( $this->all_data[ $key ] );
					$this->_to_save = true;
				}
			}
			foreach ( $this->_data as $key => $value ) {
				if ( ! isset( $new_data[ $key ] ) ) {
					unset( $this->_data[ $key ] );
					$this->_to_save = true;
				}
			}
		}
		foreach ( $new_data as $key => $data ) {
			$this->set( $key, $data );
		}
	}

	/**
	 * Get customer ID.
	 *
	 * @return int
	 */
	public function get_customer_id() {
		return $this->_customer_id;
	}

	/**
	 * Check whether the session table exists.
	 *
	 * @param bool $use_cache Whether to use the request-local existence cache.
	 *
	 * @return bool
	 */
	private function table_exists( $use_cache = true ) {
		if ( $use_cache ) {
			$return = ic_get_global( 'session_table_exists' );
			if ( 1 === $return ) {
				return true;
			} elseif ( 2 === $return ) {
				return false;
			}
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This checks the custom session table directly during bootstrap.
		if ( $this->_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $this->_table ) ) ) ) {
			ic_save_global( 'session_table_exists', 1 );

			return true;
		}

		ic_save_global( 'session_table_exists', 2 );

		return false;
	}

	/**
	 * Create the custom session table when missing.
	 *
	 * @return bool
	 */
	private function create_table() {
		if ( $this->table_exists() ) {
			return true;
		}
		global $wpdb;

		$collate = '';

		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}
		$create = "CREATE TABLE IF NOT EXISTS $this->_table (
  session_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_key char(32) NOT NULL,
  session_value longtext NOT NULL,
  session_expiry BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY  (session_id),
  UNIQUE KEY session_key (session_key)
) $collate;";
		// Do not use dbDelta() here: its CREATE parser treats IF as the table name.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- The complete schema statement uses the fixed prefixed session table and trusted collation clause.
		if ( false === $wpdb->query( $create ) ) {
			return false;
		}

		return $this->table_exists( false );
	}
}
