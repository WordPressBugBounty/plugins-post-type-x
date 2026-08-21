<?php
/**
 * WP session utility helpers.
 *
 * @package ecommerce-product-catalog
 */

/**
 * Utility methods for WP session storage.
 *
 * This class should never be instantiated.
 *
 * @package ecommerce-product-catalog
 */
class WP_Session_Utils {
	/**
	 * Count the total sessions in the database.
	 *
	 * @global wpdb $wpdb
	 *
	 * @return int
	 */
	public static function count_sessions() {
		global $wpdb;

		$query = "SELECT COUNT(*) FROM $wpdb->options WHERE option_name LIKE %s";

		/**
		 * Filter the query in case tables are non-standard.
		 *
		 * @param string $query Database count query.
		 */
		$query = apply_filters( 'wp_session_count_query', $query );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- The filtered query contract retains a value placeholder and is prepared immediately here; this bounded count is intentionally direct.
		$sessions = $wpdb->get_var( $wpdb->prepare( $query, '_wp_session_expires_%' ) );

		return absint( $sessions );
	}

	/**
	 * Create a new, random session in the database.
	 *
	 * @param null|string $date Optional expiration date string.
	 */
	public static function create_dummy_session( $date = null ) {
		// Generate our date.
		if ( null !== $date ) {
			$time = strtotime( $date );

			if ( false === $time ) {
				$date = null;
			} else {
				$expires = (int) gmdate( 'U', $time );
			}
		}

		// If null was passed, or if the string parsing failed, fall back on a default.
		if ( null === $date ) {
			/**
			 * Filter the expiration of the session in the database.
			 *
			 * @param int $expiration Session expiration in seconds.
			 */
			$expires = time() + (int) apply_filters( 'wp_session_expiration', 30 * 60 );
		}

		$session_id = self::generate_id();

		// Store the session.
		add_option( "_wp_session_{$session_id}", array(), '', 'no' );
		add_option( "_wp_session_expires_{$session_id}", $expires, '', 'no' );
	}

	/**
	 * Delete old sessions from the database.
	 *
	 * @param int $limit Maximum number of sessions to delete.
	 *
	 * @global wpdb $wpdb
	 *
	 * @return int Sessions deleted.
	 */
	public static function delete_old_sessions( $limit = 1000 ) {
		global $wpdb;

		$limit = absint( $limit );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded session cleanup reads WordPress options and deletes the selected rows immediately.
		$keys  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE %s ORDER BY option_value ASC LIMIT 0, %d",
				'_wp_session_expires_%',
				$limit
			)
		);

		$now     = time();
		$expired = array();
		$count   = 0;

		foreach ( $keys as $expiration ) {
			$key     = $expiration->option_name;
			$expires = $expiration->option_value;

			if ( $now > $expires ) {
				$session_id = preg_replace( '/[^A-Za-z0-9_]/', '', substr( $key, 20 ) );

				$expired[] = $key;
				$expired[] = "_wp_session_{$session_id}";

				++$count;
			}
		}

		// Delete expired sessions.
		if ( ! empty( $expired ) ) {
			$placeholders = array_fill( 0, count( $expired ), '%s' );
			$format       = implode( ', ', $placeholders );
			$query        = "DELETE FROM $wpdb->options WHERE option_name IN ($format)";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The dynamic placeholder list is prepared immediately before execution.
			$prepared = $wpdb->prepare( $query, $expired );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The statement above is prepared and performs bounded session cleanup.
			$wpdb->query( $prepared );
		}

		return $count;
	}

	/**
	 * Remove all sessions from the database, regardless of expiration.
	 *
	 * @global wpdb $wpdb
	 *
	 * @return int Sessions deleted
	 */
	public static function delete_all_sessions() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Explicit session cleanup must remove all matching option pairs immediately.
		$count = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $wpdb->options WHERE option_name LIKE %s",
				'_wp_session_%'
			)
		);

		return (int) ( $count / 2 );
	}

	/**
	 * Generate a new, random session ID.
	 *
	 * @return string
	 */
	public static function generate_id() {
		require_once ABSPATH . 'wp-includes/class-phpass.php';
		$hash = new PasswordHash( 8, false );

		return md5( $hash->get_random_bytes( 32 ) );
	}
}
