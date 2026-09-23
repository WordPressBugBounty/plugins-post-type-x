<?php
/**
 * AI client transport and storage helper.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles client-side AI settings, credentials, and signed requests.
 */
class IC_AI_Client {
	/**
	 * Site option name.
	 */
	const SITE_OPTION = 'ic_ai_site_settings';

	/**
	 * Per-post-type option name.
	 */
	const TARGET_OPTION = 'ic_ai_target_settings';

	/**
	 * Returns settings for one target.
	 *
	 * @param IC_AI_Target $target Target object.
	 *
	 * @return array
	 */
	public function target_settings( $target ) {
		if ( ! $target instanceof IC_AI_Target || ! $target->is_valid() ) {
			return array(); }
		$all            = get_option( self::TARGET_OPTION, array() );
		$all            = is_array( $all ) ? $all : array();
		$row_exists     = isset( $all[ $target->post_type() ][ $target->key() ] ) && is_array( $all[ $target->post_type() ][ $target->key() ] );
		$current        = $row_exists ? $all[ $target->post_type() ][ $target->key() ] : array();
		$default_fields = $this->split_target_default_fields( $target );
		$defaults       = array(
			'enabled'             => 0,
			'model'               => '',
			'fields'              => $default_fields['enhance'],
			'context_fields'      => $default_fields['context'],
			'custom_enhance_meta' => $this->default_target_custom_meta( $target ),
			'custom_context_meta' => array(),
			'marketing_controls'  => $this->marketing_controls_defaults(),
			'analysis_opt_in'     => 1,
			'consented_at'        => '',
			'consented_by'        => 0,
			'last_saved_at'       => '',
		);
		$settings       = wp_parse_args( $current, $defaults );
		if ( ! $row_exists && 'taxonomy' === $target->kind() ) {
			$post_target              = $target->integration()->target( 'post' );
			$post_state               = $post_target instanceof IC_AI_Target ? $this->target_settings( $post_target ) : array();
			$settings['enabled']      = ! empty( $post_state['enabled'] ) ? 1 : 0;
			$settings['consented_at'] = $post_state['consented_at'] ?? '';
			$settings['consented_by'] = $post_state['consented_by'] ?? 0;
		}
		$settings['fields']              = $this->normalized_target_field_selection( $settings['fields'], $target, 'enhance' );
		$settings['context_fields']      = array_values( array_diff( $this->normalized_target_field_selection( $settings['context_fields'], $target, 'context', $settings['fields'] ), $settings['fields'] ) );
		$allowed_meta                    = $this->registered_meta_keys( $target, $target->integration(), 'enhance' );
		$allowed_context                 = $this->registered_meta_keys( $target, $target->integration(), 'context' );
		$settings['custom_enhance_meta'] = array_values( array_intersect( array_map( 'sanitize_text_field', (array) $settings['custom_enhance_meta'] ), $allowed_meta ) );
		$settings['custom_context_meta'] = array_values( array_diff( array_intersect( array_map( 'sanitize_text_field', (array) $settings['custom_context_meta'] ), $allowed_context ), $settings['custom_enhance_meta'] ) );
		return $settings;
	}

	/**
	 * Determines whether a target has a persisted settings row.
	 *
	 * @param IC_AI_Target $target Target object.
	 * @return bool
	 */
	public function has_target_settings_row( $target ) {
		if ( ! $target instanceof IC_AI_Target || ! $target->is_valid() ) {
			return false;
		}
		$all = $this->all_target_settings();
		return isset( $all[ $target->post_type() ][ $target->key() ] ) && is_array( $all[ $target->post_type() ][ $target->key() ] );
	}

	/**
	 * Persists settings for one target.
	 *
	 * @param IC_AI_Target $target   Target object.
	 * @param array        $settings Target settings.
	 *
	 * @return array|false
	 */
	public function update_target_settings( $target, $settings ) {
		if ( ! $target instanceof IC_AI_Target || ! $target->is_valid() ) {
			return false; }
		$all                         = get_option( self::TARGET_OPTION, array() );
		$all                         = is_array( $all ) ? $all : array();
		$all[ $target->post_type() ] = isset( $all[ $target->post_type() ] ) && is_array( $all[ $target->post_type() ] ) ? $all[ $target->post_type() ] : array();
		$all[ $target->post_type() ][ $target->key() ] = is_array( $settings ) ? $settings : array();
		update_option( self::TARGET_OPTION, $all, false );
		return $this->target_settings( $target );
	}

	/**
	 * Returns all target settings.
	 *
	 * @return array
	 */
	private function all_target_settings() {
		$settings = get_option( self::TARGET_OPTION, array() );
		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Sanitizes a target field value according to its format.
	 *
	 * @param mixed $value Value.
	 * @param array $field Field definition.
	 *
	 * @return string
	 */
	public function sanitize_target_value( $value, $field = array() ) {
		if ( is_array( $field ) && 'html' === ( $field['content_format'] ?? '' ) ) {
			return wp_kses_post( (string) $value ); }
		return sanitize_text_field( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) );
	}

	/**
	 * Returns a target field map.
	 *
	 * @param IC_AI_Target $target Target object.
	 *
	 * @return array
	 */
	public function target_field_map( $target ) {
		return $target instanceof IC_AI_Target && $target->is_valid() ? (array) $target->field_map() : array();
	}

	/**
	 * Reads one target field value.
	 *
	 * @param IC_AI_Target $target    Target object.
	 * @param int          $object_id Object ID.
	 * @param string       $field_key Field key.
	 *
	 * @return mixed
	 */
	public function target_field_value( $target, $object_id, $field_key ) {
		if ( ! $target instanceof IC_AI_Target || ! $target->is_valid() ) {
			return ''; }
		$map      = $this->target_field_map( $target );
		$field    = isset( $map[ $field_key ] ) && is_array( $map[ $field_key ] ) ? $map[ $field_key ] : array();
		$filtered = apply_filters(
			'ic_ai_target_field_storage_read',
			array(
				'handled' => false,
				'value'   => null,
			),
			$target,
			absint( $object_id ),
			$field_key,
			$field,
			$this
		);
		if ( is_array( $filtered ) && ! empty( $filtered['handled'] ) ) {
			return $this->sanitize_target_value( $filtered['value'] ?? '', $field ); }
		$type = sanitize_key( (string) ( $field['storage'] ?? $field['type'] ?? '' ) );
		if ( 'post_field' === $type ) {
			$value = get_post_field( $field['post_field'] ?? 'post_content', absint( $object_id ) ); } elseif ( 'term_field' === $type ) {
			$term       = get_term( absint( $object_id ), $target->taxonomy() );
			$term_field = ! empty( $field['term_field'] ) ? sanitize_key( $field['term_field'] ) : 'description';
			$value      = $term && ! is_wp_error( $term ) ? ( $term->{$term_field} ?? '' ) : ''; } elseif ( 'post_meta' === $type || 'term_meta' === $type ) {
				$value = 'post_meta' === $type ? get_post_meta( absint( $object_id ), $field['meta_key'] ?? $field_key, true ) : get_term_meta( absint( $object_id ), $field['meta_key'] ?? $field_key, true ); } elseif ( 'taxonomy_assignment' === $type ) {
				$value = wp_get_object_terms( absint( $object_id ), $field['taxonomy'] ?? $target->taxonomy(), array( 'fields' => 'names' ) ); } elseif ( 'group' === $type ) {
						$value = array();
					foreach ( (array) ( $field['fields'] ?? $field['group_fields'] ?? array() ) as $group_key => $group_field ) {
								$value[ $group_key ] = $this->target_field_value_from_config( $target, $object_id, $group_key, $group_field ); }
				} else {
					$value = ''; }
					return $this->sanitize_target_value( $value, $field );
	}

	/**
	 * Persists one target field value.
	 *
	 * @param IC_AI_Target $target    Target object.
	 * @param int          $object_id Object ID.
	 * @param string       $field_key Field key.
	 * @param mixed        $value     Value.
	 *
	 * @return bool
	 */
	public function update_target_field_value( $target, $object_id, $field_key, $value ) {
		if ( ! $target instanceof IC_AI_Target || ! $target->is_valid() ) {
			return false; }
		$field    = $this->target_field_map( $target )[ $field_key ] ?? array();
		$value    = $this->sanitize_target_value( $value, $field );
		$filtered = apply_filters(
			'ic_ai_target_field_storage_write',
			array(
				'handled' => false,
				'success' => false,
			),
			$target,
			absint( $object_id ),
			$field_key,
			$field,
			$value,
			$this
		);
		if ( is_array( $filtered ) && ! empty( $filtered['handled'] ) ) {
			return ! empty( $filtered['success'] ); }
		$type = sanitize_key( (string) ( $field['storage'] ?? $field['type'] ?? '' ) );
		if ( 'post_field' === $type ) {
			return (bool) wp_update_post(
				array(
					'ID'                                   => absint( $object_id ),
					$field['post_field'] ?? 'post_content' => $value,
				),
				true
			); }
		if ( 'term_field' === $type ) {
			return ! is_wp_error( wp_update_term( absint( $object_id ), $target->taxonomy(), array( $field['term_field'] ?? 'description' => $value ) ) ); }
		if ( 'post_meta' === $type ) {
			$key = $field['meta_key'] ?? $field_key;
			if ( (string) get_post_meta( absint( $object_id ), $key, true ) === (string) $value ) {
				return true; }
			return (bool) update_post_meta( absint( $object_id ), $key, $value ); }
		if ( 'term_meta' === $type ) {
			$meta_key = $field['meta_key'] ?? $field_key;
			if ( (string) get_term_meta( absint( $object_id ), $meta_key, true ) === (string) $value ) {
				return true;
			}
			return false !== update_term_meta( absint( $object_id ), $meta_key, $value ); }
		if ( 'taxonomy_assignment' === $type ) {
			$terms = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : array( sanitize_text_field( (string) $value ) );
			return ! is_wp_error( wp_set_object_terms( absint( $object_id ), $terms, $field['taxonomy'] ?? $target->taxonomy(), false ) ); }
		if ( 'group' === $type && is_array( $value ) ) {
			$ok = true;
			foreach ( $value as $group_key => $group_value ) {
				$group_field = $field['fields'][ $group_key ] ?? $field['group_fields'][ $group_key ] ?? array();
				$ok          = $this->update_target_field_config( $target, $object_id, $group_key, $group_value, $group_field ) && $ok;
			} return $ok; }
		return false;
	}

	/**
	 * Reads a field value from a supplied field configuration.
	 *
	 * @param IC_AI_Target $target    Target object.
	 * @param int          $object_id Object ID.
	 * @param string       $field_key Field key.
	 * @param array        $field     Field definition.
	 *
	 * @return mixed
	 */
	private function target_field_value_from_config( $target, $object_id, $field_key, $field ) {
		$filtered = apply_filters(
			'ic_ai_target_field_storage_read',
			array(
				'handled' => false,
				'value'   => null,
			),
			$target,
			absint( $object_id ),
			$field_key,
			is_array( $field ) ? $field : array(),
			$this
		);
		if ( is_array( $filtered ) && ! empty( $filtered['handled'] ) ) {
			return $this->sanitize_target_value( $filtered['value'] ?? '', $field ); }
		$type = sanitize_key( (string) ( $field['storage'] ?? $field['type'] ?? '' ) );
		if ( 'post_meta' === $type ) {
			return $this->sanitize_target_value( get_post_meta( absint( $object_id ), $field['meta_key'] ?? $field_key, true ), $field ); }
		if ( 'term_meta' === $type ) {
			return $this->sanitize_target_value( get_term_meta( absint( $object_id ), $field['meta_key'] ?? $field_key, true ), $field ); }
		if ( 'group' === $type ) {
			$out = array();
			foreach ( (array) ( $field['fields'] ?? $field['group_fields'] ?? array() ) as $nested_key => $nested_field ) {
				$out[ $nested_key ] = $this->target_field_value_from_config( $target, $object_id, $nested_key, $nested_field ); }
			return $out;
		}
		return '';
	}

	/**
	 * Persists a field value from a supplied field configuration.
	 *
	 * @param IC_AI_Target $target    Target object.
	 * @param int          $object_id Object ID.
	 * @param string       $field_key Field key.
	 * @param mixed        $value     Value.
	 * @param array        $field     Field definition.
	 *
	 * @return bool
	 */
	private function update_target_field_config( $target, $object_id, $field_key, $value, $field ) {
		$value    = $this->sanitize_target_value( $value, $field );
		$filtered = apply_filters(
			'ic_ai_target_field_storage_write',
			array(
				'handled' => false,
				'success' => false,
			),
			$target,
			absint( $object_id ),
			$field_key,
			is_array( $field ) ? $field : array(),
			$value,
			$this
		);
		if ( is_array( $filtered ) && ! empty( $filtered['handled'] ) ) {
			return ! empty( $filtered['success'] ); }
		$type = sanitize_key( (string) ( $field['storage'] ?? $field['type'] ?? '' ) );
		if ( 'post_meta' === $type ) {
			$key = $field['meta_key'] ?? $field_key;
			return (string) get_post_meta( absint( $object_id ), $key, true ) === (string) $value || (bool) update_post_meta( absint( $object_id ), $key, $value ); }
		if ( 'term_meta' === $type ) {
			$key = $field['meta_key'] ?? $field_key;
			return (string) get_term_meta( absint( $object_id ), $key, true ) === (string) $value || false !== update_term_meta( absint( $object_id ), $key, $value ); }
		if ( 'group' === $type && is_array( $value ) ) {
			$ok = true;
			foreach ( (array) ( $field['fields'] ?? $field['group_fields'] ?? array() ) as $nested_key => $nested_field ) {
				if ( array_key_exists( $nested_key, $value ) ) {
					$ok = $this->update_target_field_config( $target, $object_id, $nested_key, $value[ $nested_key ], $nested_field ) && $ok;
				}
			} return $ok; }
		return false;
	}

	/**
	 * Registration endpoint path.
	 */
	const REGISTER_PATH = '/wp-json/implecode/v1/ai/clients/register';

	/**
	 * Revoke endpoint path.
	 */
	const REVOKE_PATH = '/wp-json/implecode/v1/ai/clients/revoke';

	/**
	 * Cancel-subscription endpoint path.
	 */
	const CANCEL_SUBSCRIPTION_PATH = '/wp-json/implecode/v1/ai/cancel-subscription';

	/**
	 * Plans endpoint path.
	 */
	const PLANS_PATH = '/wp-json/implecode/v1/ai/plans';

	/**
	 * Quota endpoint path.
	 */
	const QUOTA_PATH = '/wp-json/implecode/v1/ai/quota';

	/**
	 * Billing fragment endpoint path.
	 */
	const BILLING_FRAGMENT_PATH = '/wp-json/implecode/v1/ai/billing-fragment';

	/**
	 * Public billing-session endpoint path.
	 */
	const BILLING_SESSION_PATH = '/wp-json/implecode/v1/ai/billing-session';

	/** Free-license confirmation status path. */
	const FREE_CONFIRMATION_STATUS_PATH = '/wp-json/implecode/v1/ai/free-license-confirmation/status';

	/** Free-license confirmation resend path. */
	const FREE_CONFIRMATION_RESEND_PATH = '/wp-json/implecode/v1/ai/free-license-confirmation/resend';

	/**
	 * Remote checkout AJAX endpoint path.
	 */
	const CHECKOUT_AJAX_PATH = '/wp-admin/admin-ajax.php';

	/**
	 * Enhance endpoint path.
	 */
	const ENHANCE_PATH = '/wp-json/implecode/v1/ai/content-enhance';

	/**
	 * How long a cached public catalog payload stays fresh before a re-fetch is due.
	 */
	const PUBLIC_CATALOG_TTL = HOUR_IN_SECONDS;

	/**
	 * How long cached service-owned display definitions stay fresh.
	 */
	const DISPLAY_DEFINITIONS_TTL = WEEK_IN_SECONDS;

	/**
	 * Returns the default site settings.
	 *
	 * @return array
	 */
	public function default_site_settings() {
		return array(
			'license_key'                        => '',
			'client_id'                          => '',
			'client_secret'                      => '',
			'client_status'                      => '',
			'available_models'                   => array(),
			'plans'                              => array(),
			'field_modes'                        => array(),
			'marketing_control_definitions'      => array(),
			'disclosure'                         => array(),
			'display_definitions_synced_at'      => 0,
			'display_definitions_plugin_version' => '',
			'active_plan'                        => array(),
			'quota'                              => array(),
			'subscription'                       => array(),
			'license'                            => array(),
			'seo'                                => array(),
			'upgrade_url'                        => '',
			'buy_enhancements_url'               => '',
			'last_registration'                  => '',
			'last_error'                         => '',
			'last_error_code'                    => '',
			'registration_label'                 => '',
			'catalog_available'                  => 0,
			'catalog_message'                    => '',
			'request_timeout'                    => 40,
			'enhancement_packs'                  => array(),
			'plans_synced_at'                    => 0,
			'pending_status'                     => '',
			'pending_client_id'                  => '',
			'pending_callback_secret'            => '',
			'pending_post_type'                  => '',
			'pending_source'                     => '',
			'pending_started_at'                 => '',
			'pending_message'                    => '',
			'pending_user_id'                    => 0,
			'pending_plan_slug'                  => '',
			'pending_purchase_type'              => '',
			'pending_item_slug'                  => '',
			'pending_quantity'                   => 0,
			'pending_recovery_type'              => '',
			'field_error_field'                  => '',
			'field_error_message'                => '',
			'field_error_code'                   => '',
			'field_error_action_url'             => '',
			'field_error_action_label'           => '',
		);
	}

	/**
	 * Returns the running catalog plugin version used for display definitions.
	 *
	 * @return string
	 */
	public function display_definitions_plugin_version() {
		if ( ! defined( 'IC_CATALOG_VERSION' ) || ! is_scalar( IC_CATALOG_VERSION ) ) {
			return '';
		}

		return trim( (string) IC_CATALOG_VERSION );
	}

	/**
	 * Returns complete service-owned display definition cache updates.
	 *
	 * The existing cache remains authoritative when either response block is
	 * absent or empty, so transient or incomplete responses cannot erase the
	 * settings UI or mark an incomplete cache as fresh.
	 *
	 * @param array $response Decoded service response.
	 *
	 * @return array
	 */
	private function display_definition_updates( $response ) {
		$definitions = isset( $response['marketing_controls'] ) && is_array( $response['marketing_controls'] ) ? $response['marketing_controls'] : array();
		$disclosure  = isset( $response['disclosure'] ) && is_array( $response['disclosure'] ) ? $response['disclosure'] : array();

		if ( empty( $definitions ) || empty( $disclosure ) ) {
			return array();
		}

		$updates = array(
			'marketing_control_definitions' => $definitions,
			'disclosure'                    => $disclosure,
			'display_definitions_synced_at' => time(),
		);
		$version = $this->display_definitions_plugin_version();
		if ( '' !== $version ) {
			$updates['display_definitions_plugin_version'] = $version;
		}

		return $updates;
	}

	/**
	 * Returns the default persistent site field-error payload.
	 *
	 * @return array
	 */
	public function default_site_field_error() {
		return array(
			'field'        => '',
			'message'      => '',
			'code'         => '',
			'action_url'   => '',
			'action_label' => '',
		);
	}

	/**
	 * Returns the AI service URL.
	 *
	 * The production service is used unless a controlled environment defines a
	 * valid absolute HTTPS URL in the IC_AI_REMOTE_URL wp-config constant.
	 *
	 * @return string
	 */
	public function remote_url() {
		$production_url = 'https://implecode.com';
		if ( ! defined( 'IC_AI_REMOTE_URL' ) || ! is_scalar( IC_AI_REMOTE_URL ) ) {
			return $production_url;
		}

		$remote_url = trim( (string) IC_AI_REMOTE_URL );
		if ( '' === $remote_url ) {
			return $production_url;
		}

		$parts = wp_parse_url( $remote_url );
		if (
			! is_array( $parts ) ||
			'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) ||
			empty( $parts['host'] ) ||
			isset( $parts['user'] ) ||
			isset( $parts['pass'] ) ||
			isset( $parts['query'] ) ||
			isset( $parts['fragment'] )
		) {
			return $production_url;
		}

		return untrailingslashit( $remote_url );
	}

	/**
	 * Returns normalized site settings.
	 *
	 * @return array
	 */
	public function site_settings() {
		$settings = get_option( self::SITE_OPTION, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$settings = wp_parse_args( $settings, $this->default_site_settings() );
		if ( ! empty( $settings['client_secret'] ) ) {
			$settings['client_secret'] = $this->decrypt_secret( $settings['client_secret'] );
		}
		if ( empty( $settings['client_id'] ) || empty( $settings['client_secret'] ) || 'revoked' === $settings['client_status'] ) {
			$settings = $this->normalize_unregistered_site_state( $settings );
		}

		return $settings;
	}

	/**
	 * Updates site settings.
	 *
	 * @param array $settings New settings.
	 *
	 * @return array
	 */
	public function update_site_settings( $settings ) {
		$current = $this->site_settings();
		$updated = wp_parse_args( is_array( $settings ) ? $settings : array(), $current );
		if ( isset( $updated['client_secret'] ) ) {
			$updated['client_secret'] = $this->encrypt_secret( (string) $updated['client_secret'] );
		}
		update_option( self::SITE_OPTION, $updated, false );

		return $this->site_settings();
	}

	/**
	 * Returns one normalized persistent site field-error payload.
	 *
	 * @param array $settings Optional site settings payload.
	 *
	 * @return array
	 */
	public function site_field_error( $settings = array() ) {
		$settings    = is_array( $settings ) && ! empty( $settings ) ? wp_parse_args( $settings, $this->default_site_settings() ) : $this->site_settings();
		$field_error = array(
			'field'        => ! empty( $settings['field_error_field'] ) ? sanitize_key( $settings['field_error_field'] ) : '',
			'message'      => ! empty( $settings['field_error_message'] ) ? sanitize_text_field( $settings['field_error_message'] ) : '',
			'code'         => ! empty( $settings['field_error_code'] ) ? sanitize_key( $settings['field_error_code'] ) : '',
			'action_url'   => ! empty( $settings['field_error_action_url'] ) ? esc_url_raw( $settings['field_error_action_url'] ) : '',
			'action_label' => ! empty( $settings['field_error_action_label'] ) ? sanitize_text_field( $settings['field_error_action_label'] ) : '',
		);
		if ( ! empty( $field_error['field'] ) && ! empty( $field_error['message'] ) ) {
			return $field_error;
		}

		return $this->legacy_site_field_error( $settings );
	}

	/**
	 * Updates the persistent site field-error payload.
	 *
	 * @param array $field_error Field-error payload.
	 *
	 * @return array
	 */
	public function update_site_field_error( $field_error ) {
		return $this->update_site_settings( $this->site_field_error_updates( $field_error ) );
	}

	/**
	 * Clears the persistent site field-error payload.
	 *
	 * @return array
	 */
	public function clear_site_field_error() {
		return $this->update_site_field_error( array() );
	}

	/**
	 * Clears saved license-key-specific error state when a new key has been accepted.
	 *
	 * @param array $settings Optional site settings payload.
	 *
	 * @return array
	 */
	public function clear_saved_license_key_error_state( $settings = array() ) {
		$settings    = is_array( $settings ) && ! empty( $settings ) ? wp_parse_args( $settings, $this->default_site_settings() ) : $this->site_settings();
		$field_error = $this->site_field_error( $settings );
		if ( empty( $field_error['field'] ) || 'license_key' !== sanitize_key( $field_error['field'] ) ) {
			return $settings;
		}

		$updates = $this->site_field_error_updates( array() );
		$legacy  = $this->legacy_site_field_error( $settings );
		if ( ! empty( $legacy['field'] ) && 'license_key' === sanitize_key( $legacy['field'] ) ) {
			$updates['last_error']      = '';
			$updates['last_error_code'] = '';
		}

		return $this->update_site_settings( $updates );
	}

	/**
	 * Returns the cached, service-authoritative marketing-control definitions block.
	 *
	 * @return array
	 */
	private function cached_marketing_control_definitions() {
		$settings = $this->site_settings();

		return isset( $settings['marketing_control_definitions'] ) && is_array( $settings['marketing_control_definitions'] ) ? $settings['marketing_control_definitions'] : array();
	}

	/**
	 * Returns the customer-facing marketing-control definitions.
	 *
	 * The service (`implecode-ai`) is the single source of truth. This reassembles
	 * the per-control label/default/options/tip structure from the cached service
	 * payload and returns an empty array when no definitions are cached yet.
	 *
	 * @return array
	 */
	public function marketing_control_definitions() {
		$cached = $this->cached_marketing_control_definitions();
		if ( empty( $cached['options'] ) || ! is_array( $cached['options'] ) ) {
			return array();
		}

		$defaults = isset( $cached['defaults'] ) && is_array( $cached['defaults'] ) ? $cached['defaults'] : array();
		$labels   = isset( $cached['labels'] ) && is_array( $cached['labels'] ) ? $cached['labels'] : array();
		$tips     = isset( $cached['tips'] ) && is_array( $cached['tips'] ) ? $cached['tips'] : array();

		$definitions = array();
		foreach ( $cached['options'] as $key => $allowed ) {
			if ( ! is_array( $allowed ) ) {
				continue;
			}
			$allowed       = array_values( array_map( 'strval', $allowed ) );
			$option_labels = isset( $labels[ $key ]['options'] ) && is_array( $labels[ $key ]['options'] ) ? $labels[ $key ]['options'] : array();

			$options = array();
			foreach ( $allowed as $value ) {
				$options[ $value ] = isset( $option_labels[ $value ] ) ? (string) $option_labels[ $value ] : $value;
			}

			$default = isset( $defaults[ $key ] ) && in_array( (string) $defaults[ $key ], $allowed, true ) ? (string) $defaults[ $key ] : ( isset( $allowed[0] ) ? $allowed[0] : '' );

			$definitions[ $key ] = array(
				'label'   => isset( $labels[ $key ]['label'] ) ? (string) $labels[ $key ]['label'] : (string) $key,
				'default' => $default,
				'options' => $options,
				'tip'     => isset( $tips[ $key ] ) ? (string) $tips[ $key ] : '',
			);
		}

		return $definitions;
	}

	/**
	 * Returns the default marketing-control object from the cached definitions.
	 *
	 * @return array
	 */
	public function marketing_controls_defaults() {
		$defaults = array();
		foreach ( $this->marketing_control_definitions() as $key => $definition ) {
			$defaults[ $key ] = $definition['default'];
		}

		return $defaults;
	}

	/**
	 * Sanitizes stored marketing controls against the cached service definitions.
	 *
	 * When the definitions cache is empty (no catalog fetched yet) the stored
	 * string values are passed through unchanged; the service re-validates every
	 * control on the enhance request.
	 *
	 * @param mixed $controls Raw controls.
	 *
	 * @return array
	 */
	public function normalize_marketing_controls( $controls ) {
		$controls    = is_array( $controls ) ? $controls : array();
		$definitions = $this->marketing_control_definitions();

		if ( empty( $definitions ) ) {
			$passthrough = array();
			foreach ( $controls as $key => $value ) {
				if ( is_string( $value ) ) {
					$passthrough[ $key ] = $value;
				}
			}

			return $passthrough;
		}

		$normalized = array();
		foreach ( $definitions as $key => $definition ) {
			$value              = isset( $controls[ $key ] ) && is_string( $controls[ $key ] ) ? $controls[ $key ] : '';
			$normalized[ $key ] = array_key_exists( $value, $definition['options'] ) ? $value : $definition['default'];
		}

		return $normalized;
	}

	/**
	 * Builds the six existing settings-table dropdown rows.
	 *
	 * @param string $name_prefix Form name before the control key.
	 * @param mixed  $controls    Current controls.
	 *
	 * @return array
	 */
	public function marketing_control_rows( $name_prefix, $controls ) {
		$rows        = array();
		$controls    = $this->normalize_marketing_controls( $controls );
		$name_prefix = (string) $name_prefix;
		foreach ( $this->marketing_control_definitions() as $key => $definition ) {
			$rows[] = array(
				'type'    => 'dropdown',
				'label'   => $definition['label'],
				'name'    => $name_prefix . '[' . $key . ']',
				'value'   => $controls[ $key ],
				'options' => $definition['options'],
				'tip'     => $definition['tip'],
			);
		}

		return $rows;
	}

	/**
	 * Returns the default "Custom Meta to Enhance" selection for a target.
	 *
	 * Integrations may declare meta keys that should be pre-selected for
	 * enhancement on post types that have never been saved. The returned defaults
	 * are later intersected against the allowed enhance keys in
	 * target_settings(), so integrations do not need their own allowlist logic.
	 *
	 * @param IC_AI_Target $target Target object.
	 *
	 * @return array List of meta-key strings.
	 */
	private function default_target_custom_meta( $target ) {
		/**
		 * Filters the default meta keys pre-selected as "Custom Meta to Enhance".
		 *
		 * @param array                  $defaults    Default meta keys.
		 * @param IC_AI_Target $target Target object.
		 * @param IC_AI_Integration|null $integration Owning integration.
		 */
		$defaults = apply_filters( 'ic_ai_default_enhance_meta_keys', array(), $target, $target->integration() );

		if ( ! is_array( $defaults ) ) {
			return array();
		}

		return array_map( 'strval', $defaults );
	}

	/**
	 * Splits a target's default-enabled fields by enhancement mode.
	 *
	 * @param IC_AI_Target $target Target object.
	 *
	 * @return array Enhance and context field-key lists.
	 */
	private function split_target_default_fields( $target ) {
		$split = array(
			'enhance' => array(),
			'context' => array(),
		);
		if ( ! $target instanceof IC_AI_Target ) {
			return $split;
		}

		foreach ( array_keys( $target->field_map() ) as $field_key ) {
			if ( ! empty( $target->default_fields() ) && in_array( $field_key, $target->default_fields(), true ) ) {
				$split['enhance'][] = $field_key;
				continue; }
			if ( isset( $target->field_map()[ $field_key ]['default_enabled'] ) && ! $target->field_map()[ $field_key ]['default_enabled'] ) {
				continue; }
			if ( 'preserve' === $this->service_field_mode( $field_key, $target->field_map()[ $field_key ] ) ) {
				$split['context'][] = $field_key;
			} else {
				$split['enhance'][] = $field_key;
			}
		}

		return $split;
	}

	/**
	 * Returns one normalized field-key selection filtered against the target field map.
	 *
	 * @param array        $selected            Raw selected field keys.
	 * @param IC_AI_Target $target              Target object.
	 * @param string       $which               Either 'enhance' or 'context'.
	 * @param array        $excluded_field_keys Optional field keys excluded from context selections.
	 *
	 * @return array
	 */
	private function normalized_target_field_selection( $selected, $target, $which, $excluded_field_keys = array() ) {
		if ( ! is_array( $selected ) || ! $target instanceof IC_AI_Target ) {
			return array();
		}

		$selected            = array_map( 'sanitize_key', $selected );
		$excluded_field_keys = is_array( $excluded_field_keys ) ? array_map( 'sanitize_key', $excluded_field_keys ) : array();
		$allowed             = array();

		foreach ( $target->field_map() as $field_key => $field ) {
			$sanitized_field_key = sanitize_key( $field_key );
			$is_preserve         = 'preserve' === $this->service_field_mode( $field_key, $field );

			if ( 'enhance' === $which && $is_preserve ) {
				continue;
			}
			if ( 'context' === $which && ! $is_preserve && in_array( $sanitized_field_key, $excluded_field_keys, true ) ) {
				continue;
			}

			$allowed[] = $sanitized_field_key;
		}

		return array_values( array_intersect( $allowed, $selected ) );
	}

	/**
	 * Returns whether the given target is enabled for AI.
	 *
	 * @param IC_AI_Target $target Target object.
	 *
	 * @return bool
	 */
	public function is_enabled( $target ) {
		if ( ! $target instanceof IC_AI_Target || ! $target->is_valid() ) {
			return false; }
		$settings = $this->target_settings( $target );
		$site     = $this->site_settings();

		return ! empty( $settings['enabled'] ) && $this->catalog_available( $site ) && $this->has_registered_client( $site );
	}

	/**
	 * Classifies whether a target is ready for an enhancement request.
	 *
	 * @param IC_AI_Target $target Target object.
	 *
	 * @return true|WP_Error
	 */
	public function enhancement_readiness( $target ) {
		if ( ! $target instanceof IC_AI_Target || ! $target->is_valid() || ! $target->integration() ) {
			return new WP_Error( 'ic_ai_invalid_target', __( 'The AI target is not registered.', 'post-type-x' ) );
		}

		$site_settings = $this->site_settings();
		if ( ! $this->has_registered_client( $site_settings ) && empty( $site_settings['license_key'] ) ) {
			$catalog = $this->refresh_public_catalog_cache();
			$message = __( 'An AI License Key is required before impleCode AI can be used.', 'post-type-x' );
			$data    = array(
				'status'            => 409,
				'license_flow'      => true,
				'target_key'        => $target->key(),
				'plans'             => is_wp_error( $catalog ) ? ( $site_settings['plans'] ?? array() ) : ( $catalog['plans'] ?? array() ),
				'enhancement_packs' => is_wp_error( $catalog ) ? ( $site_settings['enhancement_packs'] ?? array() ) : ( $catalog['enhancement_packs'] ?? array() ),
				'catalog_available' => is_wp_error( $catalog ) ? ! empty( $site_settings['catalog_available'] ) : ! empty( $catalog['catalog_available'] ),
				'catalog_message'   => ! is_wp_error( $catalog ) && ! empty( $catalog['catalog_message'] ) ? $catalog['catalog_message'] : $message,
			);

			return new WP_Error( 'ic_ai_license_required', $message, $data );
		}

		if ( ! $this->has_registered_client( $site_settings ) || ! $this->is_enabled( $target ) ) {
			return new WP_Error(
				'ic_ai_not_enabled',
				__( 'AI is not enabled for this content type yet. Open the AI settings to activate it.', 'post-type-x' ),
				array(
					'status'        => 409,
					'license_flow'  => true,
					'recovery_type' => 'enable_ai',
					'target_key'    => $target->key(),
				)
			);
		}

		return true;
	}

	/**
	 * Refreshes billing state and gates bulk enumeration before any traversal.
	 *
	 * @param IC_AI_Target|IC_AI_Integration $target Target or owning integration.
	 * @return true|WP_Error
	 */
	public function bulk_readiness( $target ) {
		$readiness = $this->enhancement_readiness( $target );
		if ( is_wp_error( $readiness ) ) {
			return $readiness;
		}
		$refresh = $this->refresh_plan_state();
		if ( is_wp_error( $refresh ) && 'ic_ai_missing_client' !== $refresh->get_error_code() ) {
			return $refresh; }
		$settings = $this->site_settings();
		$active   = ! empty( $settings['active_plan'] ) && is_array( $settings['active_plan'] ) ? $settings['active_plan'] : array();
		$quota    = ! empty( $settings['quota'] ) && is_array( $settings['quota'] ) ? $settings['quota'] : array();
		if ( ! $this->has_registered_client( $settings ) || empty( $active ) || ( isset( $active['status'] ) && in_array( sanitize_key( (string) $active['status'] ), array( 'inactive', 'cancelled', 'expired' ), true ) ) ) {
			return new WP_Error(
				'ic_ai_no_plan_selected',
				__( 'Select an AI plan before enhancing multiple items.', 'post-type-x' ),
				array(
					'status'     => 402,
					'code'       => 'ic_ai_no_plan_selected',
					'target_key' => $target instanceof IC_AI_Target ? $target->key() : '',
				)
			);
		}
		$remaining = null;
		foreach ( array( 'enhancements_remaining', 'remaining', 'units_remaining', 'extra_enhancements_remaining' ) as $key ) {
			if ( isset( $quota[ $key ] ) && is_numeric( $quota[ $key ] ) ) {
				$remaining = (int) $quota[ $key ];
				break;
			}
		}
		if ( 0 === $remaining || 'quota_blocked' === sanitize_key( (string) ( $quota['status'] ?? '' ) ) ) {
			return new WP_Error(
				'ic_ai_quota_blocked',
				__( 'No AI Credits are available.', 'post-type-x' ),
				array(
					'status'     => 402,
					'code'       => 'ic_ai_quota_blocked',
					'quota'      => $quota,
					'target_key' => $target instanceof IC_AI_Target ? $target->key() : '',
				)
			);
		}
		return true;
	}

	/**
	 * Returns whether the site has an active registered client.
	 *
	 * @param array $settings Optional site settings payload.
	 *
	 * @return bool
	 */
	public function has_registered_client( $settings = array() ) {
		$settings = ! empty( $settings ) && is_array( $settings ) ? $settings : $this->site_settings();

		return ! empty( $settings['client_id'] ) && ! empty( $settings['client_secret'] ) && 'revoked' !== $settings['client_status'];
	}

	/**
	 * Returns the enabled enhance-field map for an integration.
	 *
	 * Only non-preserve fields can be enhanced; preserve-mode fields are
	 * exposed through {@see context_field_map()} instead.
	 *
	 * @param IC_AI_Integration|IC_AI_Target $integration Integration or target.
	 *
	 * @return array
	 */
	public function enabled_field_map( $integration ) {
		$target = $integration instanceof IC_AI_Target ? $integration : ( $integration instanceof IC_AI_Integration ? $integration->target( 'post' ) : null );
		if ( ! $target ) {
			return array(); }
		$settings = $this->target_settings( $target );
		return $this->saved_target_field_map( $target, 'fields', false, $settings );
	}

	/**
	 * Returns the enabled context-field map for an integration.
	 *
	 * Context fields are sent to the AI as reference data only and are never
	 * returned or modified. Any field already selected for enhancement is
	 * excluded from the context payload.
	 *
	 * @param IC_AI_Integration|IC_AI_Target $integration Integration or target.
	 *
	 * @return array
	 */
	public function context_field_map( $integration ) {
		$target = $integration instanceof IC_AI_Target ? $integration : ( $integration instanceof IC_AI_Integration ? $integration->target( 'post' ) : null );
		if ( ! $target ) {
			return array(); }
		$settings = $this->target_settings( $target );
		return $this->saved_target_field_map( $target, 'context_fields', true, $settings, $settings['fields'] ?? array() );
	}

	/**
	 * Returns one saved field map filtered by preserve mode.
	 *
	 * @param IC_AI_Target $target       Target object.
	 * @param string       $settings_key Saved settings key holding the field keys.
	 * @param bool         $preserve     Whether to keep preserve-mode fields.
	 * @param array        $settings     Target settings.
	 * @param array        $excluded     Excluded field keys.
	 *
	 * @return array
	 */
	private function saved_target_field_map( $target, $settings_key, $preserve, $settings, $excluded = array() ) {
		$keys     = ! empty( $settings[ $settings_key ] ) && is_array( $settings[ $settings_key ] ) ? array_map( 'sanitize_key', $settings[ $settings_key ] ) : array();
		$excluded = is_array( $excluded ) ? array_map( 'sanitize_key', $excluded ) : array();
		$out      = array();
		foreach ( $target->field_map() as $key => $field ) {
			$is_preserve = 'preserve' === $this->service_field_mode( $key, $field );
			if ( ( 'context_fields' === $settings_key && ! $is_preserve && in_array( sanitize_key( $key ), $excluded, true ) ) || ( 'fields' === $settings_key && $is_preserve ) ) {
				continue; }
			if ( in_array( sanitize_key( $key ), $keys, true ) ) {
				$out[ $key ] = $field; }
		}
		return $out;
	}

	/**
	 * Returns the saved custom-meta field map for an integration.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 * @param string            $which       Either 'enhance' or 'context'.
	 *
	 * @return array
	 */
	public function custom_meta_field_map( $integration, $which ) {
		if ( $integration instanceof IC_AI_Target ) {
			$settings_key = 'context' === $which ? 'custom_context_meta' : 'custom_enhance_meta';
			$settings     = $this->target_settings( $integration );
			$meta_keys    = ! empty( $settings[ $settings_key ] ) && is_array( $settings[ $settings_key ] ) ? $settings[ $settings_key ] : array();
			$out          = array();
			foreach ( $meta_keys as $meta_key ) {
				$meta_key = sanitize_text_field( (string) $meta_key );
				if ( '' !== $meta_key ) {
					$out[ $meta_key ] = array(
						'storage'  => 'taxonomy' === $integration->kind() ? 'term_meta' : 'post_meta',
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- This established query shape is required for the bounded lookup; changing storage, indexes, caching, or result semantics is outside this advisory boundary.
						'meta_key' => $meta_key,
						'label'    => $this->prettified_meta_label( $meta_key, $integration->post_type(), $integration->integration(), $which ),
					); }
			}
			return $out;
		}
		$settings_key = 'context' === $which ? 'custom_context_meta' : 'custom_enhance_meta';
		$settings     = $this->target_settings( $integration instanceof IC_AI_Target ? $integration : $integration->target( 'post' ) );
		$meta_keys    = ! empty( $settings[ $settings_key ] ) && is_array( $settings[ $settings_key ] ) ? $settings[ $settings_key ] : array();
		$field_map    = array();

		foreach ( $meta_keys as $meta_key ) {
			$meta_key = sanitize_text_field( (string) $meta_key );
			if ( '' === $meta_key ) {
				continue;
			}

			$field = array(
				'type'     => 'custom_meta',
				'label'    => $this->prettified_meta_label( $meta_key, $integration->post_type(), $integration, $which ),
				'meta_key' => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- field-map config value, not a query argument.
			);

			/**
			 * Filters the configuration for one selected AI custom-meta field.
			 *
			 * Extensions can add labels, editor selectors, or metadata consumed by
			 * complex-control adapters. The filter applies to both Enhance and Context
			 * selections, while editor synchronization is only performed for Enhance.
			 *
			 * @param array             $field       Custom-meta field configuration.
			 * @param string            $meta_key    Meta key.
			 * @param string            $post_type   Post type slug.
			 * @param IC_AI_Integration $integration AI integration.
			 * @param string            $which       Selection context.
			 */
			$field                  = apply_filters( 'ic_ai_custom_meta_field_config', $field, $meta_key, $integration->post_type(), $integration, $which );
			$field_map[ $meta_key ] = is_array( $field ) ? $field : array(
				'type'     => 'custom_meta',
				'label'    => $meta_key,
				'meta_key' => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- field-map config value, not a query argument.
			);
		}

		return $field_map;
	}

	/**
	 * Returns the registered post-meta keys allowed in AI custom-meta selection.
	 *
	 * WordPress supports both subtype-specific post-meta registration and global
	 * post-meta registration, so both buckets are merged here.
	 *
	 * @param string            $post_type   Post type slug.
	 * @param IC_AI_Integration $integration Optional integration for field-map exclusions.
	 * @param string            $which       Optional selector context.
	 *
	 * @return array
	 */
	public function registered_meta_keys( $post_type, $integration = null, $which = '' ) {
		if ( $integration instanceof IC_AI_Target ) {
			$target      = $integration;
			$integration = $target->integration();
			$post_type   = $target->post_type();
		}
		if ( $post_type instanceof IC_AI_Target ) {
			$target          = $post_type;
			$registered_meta = 'taxonomy' === $target->kind() ? get_registered_meta_keys( 'term', $target->taxonomy() ) : get_registered_meta_keys( 'post', $target->post_type() );
			return $this->selectable_custom_meta_keys( array_keys( $registered_meta ), $integration, $target->post_type(), $which );
		}
		$post_type = sanitize_key( (string) $post_type );
		if ( '' === $post_type ) {
			return array();
		}
		if ( 'enhance' === sanitize_key( (string) $which ) ) {
			$enhance_keys = apply_filters( 'ic_ai_enhance_meta_keys', array(), $post_type, $integration, 'enhance' );

			return $this->selectable_custom_meta_keys( $enhance_keys, $integration, $post_type, 'enhance', false );
		}

		$registered_meta = array_merge(
			get_registered_meta_keys( 'post', '' ),
			get_registered_meta_keys( 'post', $post_type )
		);

		return $this->selectable_custom_meta_keys( array_keys( $registered_meta ), $integration, $post_type, $which );
	}

	/**
	 * Returns the service-resolved enhancement mode for one field.
	 *
	 * Uses the cached authoritative service field modes when available and
	 * falls back to the local field-definition derivation otherwise.
	 *
	 * Opt-in override: when a field definition sets a truthy
	 * `lock_enhancement_mode` flag, the local explicit mode from
	 * `field_enhancement_mode()` wins over the cached service `field_modes`
	 * value (when it resolves to `rewrite`, `preserve`, or `suggest_taxonomy`).
	 * Fields without the flag keep the existing service-cache-first behavior.
	 *
	 * @param string $field_key Field key.
	 * @param array  $field     Field definition.
	 *
	 * @return string
	 */
	public function service_field_mode( $field_key, $field = array() ) {
		$field_key = sanitize_key( (string) $field_key );

		if ( is_array( $field ) && ! empty( $field['lock_enhancement_mode'] ) ) {
			$local = $this->field_enhancement_mode( $field );
			if ( in_array( $local, array( 'rewrite', 'preserve', 'suggest_taxonomy' ), true ) ) {
				return $local;
			}
		}

		$site_settings = $this->site_settings();
		$field_modes   = ! empty( $site_settings['field_modes'] ) && is_array( $site_settings['field_modes'] ) ? $site_settings['field_modes'] : array();
		$mode          = ! empty( $field_modes[ $field_key ] ) ? sanitize_key( (string) $field_modes[ $field_key ] ) : '';

		if ( in_array( $mode, array( 'rewrite', 'preserve', 'suggest_taxonomy' ), true ) ) {
			return $mode;
		}

		return $this->field_enhancement_mode( $field );
	}

	/**
	 * Resolves the normalized local enhancement mode for one field definition.
	 *
	 * @param array $field Field definition.
	 *
	 * @return string
	 */
	public function field_enhancement_mode( $field ) {
		if ( is_array( $field ) && ! empty( $field['enhancement_mode'] ) ) {
			return sanitize_key( (string) $field['enhancement_mode'] );
		}

		$type = is_array( $field ) && ! empty( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : 'meta';
		if ( 'post_field' === $type || 'custom_meta' === $type ) {
			return 'rewrite';
		}
		if ( 'taxonomy' === $type ) {
			return 'suggest_taxonomy';
		}
		if ( 'term_field' === $type ) {
			return 'rewrite';
		}

		return 'preserve';
	}

	/**
	 * Returns the distinct post-meta keys stored for one post type.
	 *
	 * Meta keys already handled by the integration field map (including group
	 * subfields) and blocklisted internal keys are excluded. The raw key list
	 * is cached in a transient for 15 minutes.
	 *
	 * @param string            $post_type   Post type slug.
	 * @param IC_AI_Integration $integration Optional integration for field-map exclusions.
	 * @param string            $which       Optional selector context.
	 *
	 * @return array
	 */
	public function detected_meta_keys( $post_type, $integration = null, $which = '' ) {
		global $wpdb;

		$post_type = sanitize_key( (string) $post_type );
		if ( '' === $post_type ) {
			return array();
		}

		$cache_key = 'ic_ai_meta_keys_' . $post_type;
		$meta_keys = get_transient( $cache_key );
		if ( ! is_array( $meta_keys ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Distinct meta-key discovery has no core API; the result is transient-cached below.
			$meta_keys = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT pm.meta_key FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type = %s ORDER BY pm.meta_key",
					$post_type
				)
			);
			$meta_keys = is_array( $meta_keys ) ? array_map( 'strval', $meta_keys ) : array();
			set_transient( $cache_key, $meta_keys, 15 * MINUTE_IN_SECONDS );
		}

		return $this->selectable_custom_meta_keys( $meta_keys, $integration, $post_type, $which );
	}

	/**
	 * Returns one prettified label for a raw meta key.
	 *
	 * @param string            $meta_key    Meta key.
	 * @param string            $post_type   Optional post type slug.
	 * @param IC_AI_Integration $integration Optional integration.
	 * @param string            $which       Optional selector context.
	 *
	 * @return string
	 */
	public function prettified_meta_label( $meta_key, $post_type = '', $integration = null, $which = '' ) {
		if ( $post_type instanceof IC_AI_Target ) {
			$integration = $post_type->integration();
			$post_type   = $post_type->post_type(); }
		$label = trim( str_replace( array( '-', '_' ), ' ', ltrim( (string) $meta_key, '_' ) ) );
		if ( '' === $label ) {
			return (string) $meta_key;
		}

		$label = ucwords( $label );

		/**
		 * Filters the label shown for one selectable AI custom-meta key.
		 *
		 * @param string            $label       Current label.
		 * @param string            $meta_key    Meta key.
		 * @param string            $post_type   Post type slug.
		 * @param IC_AI_Integration $integration Optional integration.
		 * @param string            $which       Selector context.
		 */
		return (string) apply_filters( 'ic_ai_meta_label', $label, (string) $meta_key, sanitize_key( (string) $post_type ), $integration, sanitize_key( (string) $which ) );
	}

	/**
	 * Returns all meta keys referenced by one field map, including group subfields.
	 *
	 * @param array $field_map Field map.
	 *
	 * @return array
	 */
	private function field_map_meta_keys( $field_map ) {
		$meta_keys = array();

		foreach ( (array) $field_map as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			if ( ! empty( $field['meta_key'] ) ) {
				$meta_keys[] = (string) $field['meta_key'];
			}
			if ( ! empty( $field['group_fields'] ) && is_array( $field['group_fields'] ) ) {
				$meta_keys = array_merge( $meta_keys, $this->field_map_meta_keys( $field['group_fields'] ) );
			}
		}

		return $meta_keys;
	}

	/**
	 * Returns one normalized saved custom-meta selection filtered by allowed keys.
	 *
	 * Meta keys may be mixed case, so selections are sanitized as plain text
	 * instead of key-normalized before intersecting against the allowlist.
	 *
	 * @param array $selected     Saved or submitted selection.
	 * @param array $allowed_keys Registered selectable meta keys.
	 *
	 * @return array
	 */
	private function normalized_custom_meta_selection( $selected, $allowed_keys ) {
		$selected = is_array( $selected ) ? array_map( 'sanitize_text_field', $selected ) : array();
		if ( empty( $selected ) || empty( $allowed_keys ) ) {
			return array();
		}

		return array_values( array_intersect( $allowed_keys, $selected ) );
	}

	/**
	 * Returns the subset of custom meta keys allowed in AI selection.
	 *
	 * @param array             $meta_keys    Candidate meta keys.
	 * @param IC_AI_Integration $integration  Optional integration for field-map exclusions.
	 * @param string            $post_type    Post type slug.
	 * @param string            $which        Selector context.
	 * @param bool              $apply_registered_filter Whether to apply the registered-key filter.
	 *
	 * @return array
	 */
	private function selectable_custom_meta_keys( $meta_keys, $integration = null, $post_type = '', $which = '', $apply_registered_filter = true ) {
		$target   = $integration instanceof IC_AI_Integration ? $integration->target( 'post' ) : false;
		$excluded = $target instanceof IC_AI_Target ? $this->field_map_meta_keys( $target->field_map() ) : array();
		$allowed  = array();

		foreach ( (array) $meta_keys as $meta_key ) {
			$meta_key = (string) $meta_key;
			if ( '' === $meta_key || in_array( $meta_key, $excluded, true ) || $this->is_blocklisted_meta_key( $meta_key ) ) {
				continue;
			}

			$allowed[] = $meta_key;
		}

		/**
		 * Filters the registered custom-meta keys selectable in AI settings.
		 *
		 * @param array             $allowed     Selectable meta keys after core exclusions.
		 * @param string            $post_type   Post type slug.
		 * @param IC_AI_Integration $integration Optional integration.
		 * @param string            $which       Selector context.
		 */
		if ( $apply_registered_filter ) {
			$allowed = apply_filters( 'ic_ai_registered_meta_keys', $allowed, sanitize_key( (string) $post_type ), $integration, sanitize_key( (string) $which ) );
		}
		$allowed = is_array( $allowed ) ? array_map( 'strval', $allowed ) : array();
		$allowed = array_values( array_unique( $allowed ) );
		sort( $allowed, SORT_STRING );

		return $allowed;
	}

	/**
	 * Returns whether one detected meta key is blocklisted from AI selection.
	 *
	 * @param string $meta_key Meta key.
	 *
	 * @return bool
	 */
	private function is_blocklisted_meta_key( $meta_key ) {
		/**
		 * Filters the internal meta-key prefixes hidden from AI custom-meta selection.
		 *
		 * Entries are matched as prefixes, so exact keys work as well.
		 *
		 * @param array $blocklist Blocklisted meta-key prefixes.
		 */
		$blocklist = apply_filters( 'ic_ai_detected_meta_blocklist', array( '_edit_', '_wp_', '_oembed_', '_thumbnail_id' ) );

		foreach ( (array) $blocklist as $blocked ) {
			$blocked = (string) $blocked;
			if ( '' !== $blocked && 0 === strpos( (string) $meta_key, $blocked ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Registers the current site with the remote implecode-ai receiver.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 *
	 * @return array|WP_Error
	 */
	public function register_site( $integration ) {
		$site_settings = $this->site_settings();
		$endpoint      = $this->build_endpoint( $this->remote_url(), self::REGISTER_PATH );
		$body          = wp_json_encode(
			array(
				'site_url'           => home_url(),
				'site_label'         => get_bloginfo( 'name' ),
				'blog_id'            => get_current_blog_id(),
				'plugin_slug'        => $integration->plugin_slug(),
				'enabled_post_types' => $this->enabled_post_types(),
				'locale'             => $this->validated_site_locale(),
			)
		);
		$args          = array(
			'timeout' => 20,
			'headers' => array(
				'Content-Type'         => 'application/json',
				'Accept'               => 'application/json',
				'X-IC-AI-Key'          => (string) $site_settings['license_key'],
				'X-IC-AI-Registration' => $integration->post_type(),
			),
			'body'    => $body,
		);
		$response      = wp_remote_post( $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			$this->update_site_settings(
				array_merge(
					array(
						'last_error'      => $response->get_error_message(),
						'last_error_code' => $response->get_error_code(),
					),
					$this->site_field_error_updates( array() )
				)
			);

			return $response;
		}

		$decoded = $this->decode_response( $response );
		if ( is_wp_error( $decoded ) ) {
			$updates = array(
				'last_error'      => $decoded->get_error_message(),
				'last_error_code' => $decoded->get_error_code(),
			);
			if ( $this->is_invalid_registration_key_error( $decoded ) ) {
				$updates = array_merge( $updates, $this->site_field_error_updates( $this->registration_license_key_field_error( $decoded ) ) );
			} else {
				$updates = array_merge( $updates, $this->site_field_error_updates( array() ) );
			}
			$this->update_site_settings(
				$updates
			);

			return $decoded;
		}

		$payload = array_merge(
			array(
				'client_id'                     => isset( $decoded['client_id'] ) ? sanitize_text_field( $decoded['client_id'] ) : '',
				'client_secret'                 => isset( $decoded['client_secret'] ) ? (string) $decoded['client_secret'] : '',
				'client_status'                 => isset( $decoded['status'] ) ? sanitize_key( $decoded['status'] ) : 'active',
				'available_models'              => isset( $decoded['available_models'] ) && is_array( $decoded['available_models'] ) ? $decoded['available_models'] : array(),
				'plans'                         => isset( $decoded['plans'] ) && is_array( $decoded['plans'] ) ? $decoded['plans'] : array(),
				'enhancement_packs'             => isset( $decoded['enhancement_packs'] ) && is_array( $decoded['enhancement_packs'] ) ? $decoded['enhancement_packs'] : array(),
				'field_modes'                   => isset( $decoded['field_modes'] ) && is_array( $decoded['field_modes'] ) ? $decoded['field_modes'] : array(),
				'active_plan'                   => isset( $decoded['active_plan'] ) && is_array( $decoded['active_plan'] ) ? $decoded['active_plan'] : array(),
				'quota'                         => isset( $decoded['quota'] ) && is_array( $decoded['quota'] ) ? $decoded['quota'] : array(),
				'subscription'                  => isset( $decoded['subscription'] ) && is_array( $decoded['subscription'] ) ? $decoded['subscription'] : array(),
				'license'                       => isset( $decoded['license'] ) && is_array( $decoded['license'] ) ? $decoded['license'] : array(),
				'upgrade_url'                   => isset( $decoded['upgrade_url'] ) ? esc_url_raw( $decoded['upgrade_url'] ) : '',
				'buy_enhancements_url'          => isset( $decoded['buy_enhancements_url'] ) ? esc_url_raw( $decoded['buy_enhancements_url'] ) : '',
				'registration_label'            => isset( $decoded['registration_label'] ) ? sanitize_text_field( $decoded['registration_label'] ) : '',
				'catalog_available'             => ! empty( $decoded['catalog_available'] ) ? 1 : 0,
				'catalog_message'               => isset( $decoded['catalog_message'] ) ? sanitize_text_field( $decoded['catalog_message'] ) : '',
				'request_timeout'               => $this->sanitized_request_timeout( isset( $decoded['request_timeout'] ) ? $decoded['request_timeout'] : 40 ),
				'last_registration'             => gmdate( 'c' ),
				'last_error'                    => '',
				'last_error_code'               => '',
			),
			$this->site_field_error_updates( array() ),
			$this->display_definition_updates( $decoded )
		);
		$this->update_site_settings( $payload );

		if ( ! $this->catalog_available( $payload ) ) {
			$this->disable_all_post_types();

			return new WP_Error( 'ic_ai_catalog_unavailable', $this->catalog_message( $payload ) );
		}

		$this->update_site_settings(
			array(
				'pending_status'          => '',
				'pending_client_id'       => '',
				'pending_callback_secret' => '',
				'pending_post_type'       => '',
				'pending_source'          => '',
				'pending_started_at'      => '',
				'pending_message'         => '',
				'pending_user_id'         => 0,
				'pending_plan_slug'       => '',
				'pending_purchase_type'   => '',
				'pending_item_slug'       => '',
				'pending_quantity'        => 0,
				'pending_recovery_type'   => '',
			)
		);

		return $decoded;
	}

	/**
	 * Revokes the current client key.
	 *
	 * @param string $reason Revoke reason.
	 *
	 * @return array|WP_Error
	 */
	public function revoke_key( $reason = 'manual_remove' ) {
		$site_settings = $this->site_settings();
		if ( empty( $site_settings['client_id'] ) || empty( $site_settings['client_secret'] ) ) {
			return new WP_Error( 'ic_ai_missing_client', __( 'No registered AI client key was found.', 'post-type-x' ) );
		}
		$payload = array(
			'client_id' => $site_settings['client_id'],
			'site_url'  => home_url(),
			'blog_id'   => get_current_blog_id(),
			'reason'    => sanitize_key( $reason ),
		);
		$request = $this->signed_request( self::REVOKE_PATH, $payload, $site_settings['client_secret'] );
		$decoded = is_wp_error( $request ) ? $request : $this->decode_response( $request );

		$this->clear_credentials( is_wp_error( $decoded ) ? $decoded->get_error_message() : '' );

		return $decoded;
	}

	/**
	 * Cancels the active recurring subscription on the remote server.
	 *
	 * @return array|WP_Error
	 */
	public function cancel_subscription() {
		$site_settings = $this->site_settings();
		if ( empty( $site_settings['client_id'] ) || empty( $site_settings['client_secret'] ) ) {
			return new WP_Error( 'ic_ai_missing_client', __( 'No registered AI client key was found.', 'post-type-x' ) );
		}
		$payload = array(
			'client_id' => $site_settings['client_id'],
			'site_url'  => home_url(),
			'blog_id'   => get_current_blog_id(),
		);
		$request = $this->signed_request( self::CANCEL_SUBSCRIPTION_PATH, $payload, $site_settings['client_secret'] );
		if ( is_wp_error( $request ) ) {
			return $request;
		}
		$decoded = $this->decode_response( $request );
		if ( is_wp_error( $decoded ) ) {
			return $decoded;
		}

		$this->update_site_settings(
			array_merge(
				array(
					'plans'                         => isset( $decoded['plans'] ) && is_array( $decoded['plans'] ) ? $decoded['plans'] : array(),
					'enhancement_packs'             => isset( $decoded['enhancement_packs'] ) && is_array( $decoded['enhancement_packs'] ) ? $decoded['enhancement_packs'] : array(),
					'field_modes'                   => isset( $decoded['field_modes'] ) && is_array( $decoded['field_modes'] ) ? $decoded['field_modes'] : array(),
					'active_plan'                   => isset( $decoded['active_plan'] ) && is_array( $decoded['active_plan'] ) ? $decoded['active_plan'] : array(),
					'quota'                         => isset( $decoded['quota'] ) && is_array( $decoded['quota'] ) ? $decoded['quota'] : array(),
					'subscription'                  => isset( $decoded['subscription'] ) && is_array( $decoded['subscription'] ) ? $decoded['subscription'] : array(),
					'license'                       => isset( $decoded['license'] ) && is_array( $decoded['license'] ) ? $decoded['license'] : array(),
					'upgrade_url'                   => isset( $decoded['upgrade_url'] ) ? esc_url_raw( $decoded['upgrade_url'] ) : '',
					'buy_enhancements_url'          => isset( $decoded['buy_enhancements_url'] ) ? esc_url_raw( $decoded['buy_enhancements_url'] ) : '',
					'catalog_available'             => ! empty( $decoded['catalog_available'] ) ? 1 : 0,
					'catalog_message'               => isset( $decoded['catalog_message'] ) ? sanitize_text_field( $decoded['catalog_message'] ) : '',
					'request_timeout'               => $this->sanitized_request_timeout( isset( $decoded['request_timeout'] ) ? $decoded['request_timeout'] : $site_settings['request_timeout'] ),
					'last_error'                    => '',
					'last_error_code'               => '',
				),
				$this->site_field_error_updates( array() ),
				$this->display_definition_updates( $decoded )
			)
		);

		return $decoded;
	}

	/**
	 * Clears locally stored credentials.
	 *
	 * @param string $last_error Optional legacy error input kept for compatibility.
	 *
	 * @return array
	 */
	public function clear_credentials( $last_error = '' ) {
		$settings = $this->site_settings();
		unset( $settings['client_id'], $settings['client_secret'] );
		$settings['license_key']              = '';
		$settings['client_id']                = '';
		$settings['client_secret']            = '';
		$settings['client_status']            = 'revoked';
		$settings['active_plan']              = array();
		$settings['quota']                    = array();
		$settings['subscription']             = array();
		$settings['license']                  = array();
		$settings['upgrade_url']              = '';
		$settings['buy_enhancements_url']     = '';
		$settings['last_registration']        = '';
		$settings['registration_label']       = '';
		$settings['pending_status']           = '';
		$settings['pending_client_id']        = '';
		$settings['pending_callback_secret']  = '';
		$settings['pending_post_type']        = '';
		$settings['pending_source']           = '';
		$settings['pending_started_at']       = '';
		$settings['pending_message']          = '';
		$settings['pending_user_id']          = 0;
		$settings['pending_plan_slug']        = '';
		$settings['pending_purchase_type']    = '';
		$settings['pending_item_slug']        = '';
		$settings['pending_quantity']         = 0;
		$settings['pending_recovery_type']    = '';
		$settings['last_error']               = '';
		$settings['last_error_code']          = '';
		$settings['field_error_field']        = '';
		$settings['field_error_message']      = '';
		$settings['field_error_code']         = '';
		$settings['field_error_action_url']   = '';
		$settings['field_error_action_label'] = '';
		$this->clear_all_post_type_activation_state();

		return $this->update_site_settings( $settings );
	}

	/**
	 * Removes stale registered-only state from an unregistered site settings payload.
	 *
	 * @param array $settings Site settings.
	 *
	 * @return array
	 */
	private function normalize_unregistered_site_state( $settings ) {
		$settings['active_plan']          = array();
		$settings['quota']                = array();
		$settings['subscription']         = array();
		$settings['license']              = array();
		$settings['upgrade_url']          = '';
		$settings['buy_enhancements_url'] = '';
		$settings['last_registration']    = '';
		$settings['registration_label']   = '';
		if ( empty( $settings['pending_status'] ) && ! empty( $settings['last_error_code'] ) && 'revoked' === $settings['last_error_code'] ) {
			$settings['last_error']      = '';
			$settings['last_error_code'] = '';
		}

		return $settings;
	}

	/**
	 * Requests fresh plan state from the remote server.
	 *
	 * @return array|WP_Error
	 */
	public function refresh_plan_state() {
		$site_settings = $this->site_settings();
		if ( empty( $site_settings['client_id'] ) || empty( $site_settings['client_secret'] ) ) {
			return new WP_Error( 'ic_ai_missing_client', __( 'No AI client is currently registered.', 'post-type-x' ) );
		}
		$payload = array(
			'client_id' => $site_settings['client_id'],
			'site_url'  => home_url(),
			'blog_id'   => get_current_blog_id(),
		);
		$request = $this->signed_request( self::QUOTA_PATH, $payload, $site_settings['client_secret'] );
		if ( is_wp_error( $request ) ) {
			return $request;
		}
		$decoded = $this->decode_response( $request );
		if ( is_wp_error( $decoded ) ) {
			return $decoded;
		}
		$this->update_site_settings(
			array_merge(
				array(
					'plans'                         => isset( $decoded['plans'] ) && is_array( $decoded['plans'] ) ? $decoded['plans'] : array(),
					'enhancement_packs'             => isset( $decoded['enhancement_packs'] ) && is_array( $decoded['enhancement_packs'] ) ? $decoded['enhancement_packs'] : array(),
					'field_modes'                   => isset( $decoded['field_modes'] ) && is_array( $decoded['field_modes'] ) ? $decoded['field_modes'] : array(),
					'active_plan'                   => isset( $decoded['active_plan'] ) && is_array( $decoded['active_plan'] ) ? $decoded['active_plan'] : array(),
					'quota'                         => isset( $decoded['quota'] ) && is_array( $decoded['quota'] ) ? $decoded['quota'] : array(),
					'subscription'                  => isset( $decoded['subscription'] ) && is_array( $decoded['subscription'] ) ? $decoded['subscription'] : array(),
					'license'                       => isset( $decoded['license'] ) && is_array( $decoded['license'] ) ? $decoded['license'] : array(),
					'upgrade_url'                   => isset( $decoded['upgrade_url'] ) ? esc_url_raw( $decoded['upgrade_url'] ) : '',
					'buy_enhancements_url'          => isset( $decoded['buy_enhancements_url'] ) ? esc_url_raw( $decoded['buy_enhancements_url'] ) : '',
					'catalog_available'             => ! empty( $decoded['catalog_available'] ) ? 1 : 0,
					'catalog_message'               => isset( $decoded['catalog_message'] ) ? sanitize_text_field( $decoded['catalog_message'] ) : '',
					'request_timeout'               => $this->sanitized_request_timeout( isset( $decoded['request_timeout'] ) ? $decoded['request_timeout'] : $site_settings['request_timeout'] ),
					'last_error'                    => '',
					'last_error_code'               => '',
				),
				$this->site_field_error_updates( array() ),
				$this->display_definition_updates( $decoded )
			)
		);

		if ( ! $this->catalog_available( $decoded ) ) {
			$this->disable_all_post_types();

			return new WP_Error( 'ic_ai_catalog_unavailable', $this->catalog_message( $decoded ) );
		}

		return $decoded;
	}

	/**
	 * Fetches one embeddable billing fragment from the remote server.
	 *
	 * @param string $mode      Billing mode.
	 * @param array  $selection Optional selection payload.
	 *
	 * @return array|WP_Error
	 */
	public function fetch_billing_fragment( $mode, $selection = array() ) {
		$site_settings = $this->site_settings();
		if ( empty( $site_settings['client_id'] ) || empty( $site_settings['client_secret'] ) ) {
			return new WP_Error( 'ic_ai_missing_client', __( 'No AI client is currently registered.', 'post-type-x' ) );
		}

		$payload     = array(
			'client_id' => $site_settings['client_id'],
			'site_url'  => home_url(),
			'blog_id'   => get_current_blog_id(),
			'mode'      => 'enhancements' === sanitize_key( $mode ) ? 'enhancements' : 'upgrade',
			'selection' => is_array( $selection ) ? $selection : array(),
		);
		$license_key = $this->billing_lookup_license_key();
		if ( ! empty( $license_key ) ) {
			$payload['license_key'] = $license_key;
		}
		$request = $this->signed_request( self::BILLING_FRAGMENT_PATH, $payload, $site_settings['client_secret'] );
		if ( is_wp_error( $request ) ) {
			return $this->normalize_billing_error( $request );
		}

		$decoded = $this->decode_response( $request );
		if ( is_wp_error( $decoded ) ) {
			return $this->normalize_billing_error( $decoded );
		}

		return $decoded;
	}

	/**
	 * Sends an enhancement request.
	 *
	 * @param IC_AI_Integration $integration     Integration.
	 * @param array             $payload         Enhance-field payload.
	 * @param array             $context_payload Reference-only context field values.
	 * @param string            $job_id          Optional remote job ID to poll instead of submitting a payload.
	 * @param int               $batch_total     Total number of items in the surrounding batch; 1 for single enhances.
	 * @param bool              $queue_only      Whether the fresh submit should only enqueue remotely.
	 * @param string            $batch_group_id  Optional same-task batch group identifier for worker-side merging.
	 * @param array             $qa_context      Optional clarifying-question answers to refine the fields.
	 * @param bool              $questions_suppressed Whether to suppress further clarifying questions (refine call).
	 * @param string            $editor_type     Optional allowlisted editor type for a fresh editor request.
	 *
	 * @return array|WP_Error
	 */
	public function request_enhancement( $integration, $payload, $context_payload = array(), $job_id = '', $batch_total = 1, $queue_only = false, $batch_group_id = '', $qa_context = array(), $questions_suppressed = false, $editor_type = '' ) {
		$target = $integration instanceof IC_AI_Target ? $integration : null;
		$stats  = $target && $target->is_valid() ? array(
			'integration' => (string) $target->plugin_slug(),
			'target'      => (string) $target->key(),
			'kind'        => (string) $target->kind(),
			'mode'        => '' !== (string) $job_id ? 'poll' : ( $queue_only ? 'queue' : 'enhance' ),
			'field_count' => is_array( $payload ) ? count( $payload ) : 0,
			'batch_total' => max( 1, absint( $batch_total ) ),
		) : array();
		if ( $stats ) {
			$this->record_stats_event( 'ai_request', $stats ); }
		$started = microtime( true );
		$result  = $this->enhancement_request( $integration, $payload, $context_payload, $job_id, $batch_total, $queue_only, $batch_group_id, $qa_context, $questions_suppressed, $editor_type );
		if ( ! $stats ) {
			return $result; }
		if ( is_wp_error( $result ) ) {
			$data   = $result->get_error_data();
			$status = is_array( $data ) && isset( $data['status'] ) ? absint( $data['status'] ) : 0;
			$code   = (string) $result->get_error_code();
			// Only the error code and the HTTP status cross into statistics: the
			// message may carry service or payload detail.
			$this->record_stats_event(
				in_array( $status, array( 402, 429 ), true ) || in_array( $code, array( 'ic_ai_quota_blocked', 'ic_ai_rate_limited', 'ic_ai_enhancements_exhausted' ), true ) ? 'ai_rate' : 'ai_failure',
				array(
					'code'   => $code,
					'status' => $status,
				)
			);
			return $result; }
		$this->record_stats_event( 'ai_timing', array_merge( $stats, array( 'duration_ms' => (int) round( ( microtime( true ) - $started ) * 1000 ) ) ) );
		return $result;
	}

	/**
	 * Records one AI statistics event through the shared safe recorder.
	 *
	 * @param string $event   Allowlisted event key.
	 * @param array  $context Scalar-only, content-free context.
	 *
	 * @return void
	 */
	private function record_stats_event( $event, $context = array() ) {
		if ( function_exists( 'ic_ai_stats_record_event' ) ) {
			ic_ai_stats_record_event( $event, $context ); }
	}

	/**
	 * Performs the enhancement request itself.
	 *
	 * @param IC_AI_Integration $integration     Integration.
	 * @param array             $payload         Enhance-field payload.
	 * @param array             $context_payload Reference-only context field values.
	 * @param string            $job_id          Optional remote job ID to poll instead of submitting a payload.
	 * @param int               $batch_total     Total number of items in the surrounding batch; 1 for single enhances.
	 * @param bool              $queue_only      Whether the fresh submit should only enqueue remotely.
	 * @param string            $batch_group_id  Optional same-task batch group identifier for worker-side merging.
	 * @param array             $qa_context      Optional clarifying-question answers to refine the fields.
	 * @param bool              $questions_suppressed Whether to suppress further clarifying questions (refine call).
	 * @param string            $editor_type     Optional allowlisted editor type for a fresh editor request.
	 *
	 * @return array|WP_Error
	 */
	private function enhancement_request( $integration, $payload, $context_payload = array(), $job_id = '', $batch_total = 1, $queue_only = false, $batch_group_id = '', $qa_context = array(), $questions_suppressed = false, $editor_type = '' ) {
		$target = $integration instanceof IC_AI_Target ? $integration : null;
		if ( $target ) {
			$integration = $target->integration(); }
		if ( ! $target || ! $target->is_valid() || ! $integration ) {
			return new WP_Error( 'ic_ai_invalid_target', __( 'The AI target is not registered.', 'post-type-x' ) ); }
		$readiness = $this->enhancement_readiness( $target );
		if ( is_wp_error( $readiness ) ) {
			return $readiness;
		}
		$site_settings = $this->site_settings();
		$settings      = $this->target_settings( $target );
		$request       = array(
			'client_id'   => $site_settings['client_id'],
			'site_url'    => home_url(),
			'blog_id'     => get_current_blog_id(),
			'target'      => $target->to_array(),
			'plugin_slug' => $integration->plugin_slug(),
			'locale'      => $this->validated_site_locale(),
			'batch_total' => max( 1, absint( $batch_total ) ),
		);
		$job_id        = sanitize_text_field( (string) $job_id );
		if ( '' !== $job_id ) {
			$request['job_id'] = $job_id;
		} else {
			$editor_type = sanitize_key( (string) $editor_type );
			if ( in_array( $editor_type, array( 'classic', 'block' ), true ) ) {
				$request['editor_type'] = $editor_type;
			}
			$request['marketing_controls'] = $this->normalize_marketing_controls( $settings['marketing_controls'] ?? array() );
			$request['analysis_opt_in']    = ! empty( $settings['analysis_opt_in'] );
			$request['selected_fields']    = array_keys( $payload );
			$request['payload']            = $payload;
			$request['context_payload']    = is_array( $context_payload ) ? $context_payload : array();
			if ( $queue_only ) {
				$request['queue_only'] = true;
			}
			if ( '' !== $batch_group_id ) {
				$request['batch_group_id'] = sanitize_key( (string) $batch_group_id );
			}
			if ( ! empty( $qa_context ) && is_array( $qa_context ) ) {
				$request['qa_context'] = $this->sanitize_qa_context( $qa_context );
			}
			if ( $questions_suppressed ) {
				$request['questions_suppressed'] = true;
			}

			$size_error = $this->payload_size_precheck( $integration, $payload, $request, $site_settings );
			if ( is_wp_error( $size_error ) ) {
				return $size_error;
			}
		}
		$response = $this->signed_request( self::ENHANCE_PATH, $request, $site_settings['client_secret'] );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$decoded = $this->decode_response( $response );
		$pending = $this->pending_remote_job_error( $decoded );
		if ( is_wp_error( $pending ) ) {
			$decoded = $pending;
		}
		if ( is_wp_error( $decoded ) ) {
			$oversize_error = $this->oversize_response_error( $integration, $decoded );
			if ( is_wp_error( $oversize_error ) ) {
				return $oversize_error;
			}
			$field_error  = $this->enhancement_license_key_field_error( $decoded, $site_settings );
			$user_error   = $decoded;
			$updates      = array();
			$is_queueable = $this->is_queueable_remote_error( $decoded );
			$error_data   = $decoded->get_error_data();
			if ( ! empty( $error_data['body'] ) && is_array( $error_data['body'] ) ) {
				$body = $error_data['body'];
				if ( array_key_exists( 'catalog_available', $body ) ) {
					$updates = array(
						'plans'                         => isset( $body['plans'] ) && is_array( $body['plans'] ) ? $body['plans'] : $site_settings['plans'],
						'enhancement_packs'             => isset( $body['enhancement_packs'] ) && is_array( $body['enhancement_packs'] ) ? $body['enhancement_packs'] : $site_settings['enhancement_packs'],
						'field_modes'                   => isset( $body['field_modes'] ) && is_array( $body['field_modes'] ) ? $body['field_modes'] : $site_settings['field_modes'],
						'marketing_control_definitions' => isset( $body['marketing_controls'] ) && is_array( $body['marketing_controls'] ) ? $body['marketing_controls'] : $site_settings['marketing_control_definitions'],
						'disclosure'                    => isset( $body['disclosure'] ) && is_array( $body['disclosure'] ) ? $body['disclosure'] : $site_settings['disclosure'],
						'active_plan'                   => isset( $body['active_plan'] ) && is_array( $body['active_plan'] ) ? $body['active_plan'] : $site_settings['active_plan'],
						'quota'                         => isset( $body['quota'] ) && is_array( $body['quota'] ) ? $body['quota'] : $site_settings['quota'],
						'catalog_available'             => ! empty( $body['catalog_available'] ) ? 1 : 0,
						'catalog_message'               => isset( $body['catalog_message'] ) ? sanitize_text_field( $body['catalog_message'] ) : $site_settings['catalog_message'],
						'upgrade_url'                   => isset( $body['upgrade_url'] ) ? esc_url_raw( $body['upgrade_url'] ) : '',
						'buy_enhancements_url'          => isset( $body['buy_enhancements_url'] ) ? esc_url_raw( $body['buy_enhancements_url'] ) : '',
						'request_timeout'               => $this->sanitized_request_timeout( isset( $body['request_timeout'] ) ? $body['request_timeout'] : $site_settings['request_timeout'] ),
					);
					if ( $is_queueable ) {
						$updates['last_error']      = '';
						$updates['last_error_code'] = '';
					} elseif ( ! empty( $field_error['field'] ) && ! empty( $field_error['message'] ) ) {
						$updates['last_error']      = $field_error['message'];
						$updates['last_error_code'] = ! empty( $field_error['code'] ) ? $field_error['code'] : $decoded->get_error_code();
						$updates                    = array_merge( $updates, $this->site_field_error_updates( $field_error ) );
					} else {
						$updates['last_error']      = $decoded->get_error_message();
						$updates['last_error_code'] = $decoded->get_error_code();
					}
					$this->update_site_settings( $updates );
					if ( empty( $body['catalog_available'] ) ) {
						$this->disable_all_post_types();
					}
				}
			}
			if ( empty( $updates ) && ! empty( $field_error['field'] ) && ! empty( $field_error['message'] ) ) {
				$this->update_site_settings(
					array_merge(
						array(
							'last_error'      => $field_error['message'],
							'last_error_code' => ! empty( $field_error['code'] ) ? $field_error['code'] : $decoded->get_error_code(),
						),
						$this->site_field_error_updates( $field_error )
					)
				);
			}

			if ( empty( $field_error['field'] ) && $this->should_mask_internal_enhancement_error( $decoded ) ) {
				$user_error = new WP_Error(
					$decoded->get_error_code(),
					$this->service_unavailable_message(),
					$decoded->get_error_data()
				);
			}

			return $user_error;
		}
		$site_updates = array(
			'last_error'      => '',
			'last_error_code' => '',
		);
		if ( isset( $decoded['plans'] ) || isset( $decoded['quota'] ) || isset( $decoded['active_plan'] ) || isset( $decoded['catalog_available'] ) ) {
			$site_updates['plans']                         = isset( $decoded['plans'] ) && is_array( $decoded['plans'] ) ? $decoded['plans'] : $site_settings['plans'];
			$site_updates['enhancement_packs']             = isset( $decoded['enhancement_packs'] ) && is_array( $decoded['enhancement_packs'] ) ? $decoded['enhancement_packs'] : $site_settings['enhancement_packs'];
			$site_updates['field_modes']                   = isset( $decoded['field_modes'] ) && is_array( $decoded['field_modes'] ) ? $decoded['field_modes'] : $site_settings['field_modes'];
			$site_updates['marketing_control_definitions'] = isset( $decoded['marketing_controls'] ) && is_array( $decoded['marketing_controls'] ) ? $decoded['marketing_controls'] : $site_settings['marketing_control_definitions'];
			$site_updates['disclosure']                    = isset( $decoded['disclosure'] ) && is_array( $decoded['disclosure'] ) ? $decoded['disclosure'] : $site_settings['disclosure'];
			$site_updates['quota']                         = isset( $decoded['quota'] ) && is_array( $decoded['quota'] ) ? $decoded['quota'] : $site_settings['quota'];
			$site_updates['active_plan']                   = isset( $decoded['active_plan'] ) && is_array( $decoded['active_plan'] ) ? $decoded['active_plan'] : $site_settings['active_plan'];
			$site_updates['upgrade_url']                   = isset( $decoded['upgrade_url'] ) ? esc_url_raw( $decoded['upgrade_url'] ) : $site_settings['upgrade_url'];
			$site_updates['buy_enhancements_url']          = isset( $decoded['buy_enhancements_url'] ) ? esc_url_raw( $decoded['buy_enhancements_url'] ) : $site_settings['buy_enhancements_url'];
			$site_updates['catalog_available']             = ! empty( $decoded['catalog_available'] ) ? 1 : 0;
			$site_updates['catalog_message']               = isset( $decoded['catalog_message'] ) ? sanitize_text_field( $decoded['catalog_message'] ) : $site_settings['catalog_message'];
			$site_updates['request_timeout']               = $this->sanitized_request_timeout( isset( $decoded['request_timeout'] ) ? $decoded['request_timeout'] : $site_settings['request_timeout'] );
		}
		$this->update_site_settings( $site_updates );

		if ( ! $this->catalog_available( $site_updates ) ) {
			$this->disable_all_post_types();

			return new WP_Error( 'ic_ai_catalog_unavailable', $this->catalog_message( $site_updates ) );
		}

		return $decoded;
	}

	/**
	 * Returns the configured WordPress site locale when it is a safe identifier.
	 *
	 * @return string
	 */
	private function validated_site_locale() {
		$locale = (string) get_locale();

		return preg_match( '/^[A-Za-z]{2,3}(?:_[A-Za-z0-9]{2,16}){0,2}$/D', $locale ) ? $locale : 'en_US';
	}

	/**
	 * Sanitizes clarifying-question answers before they are sent for a refine call.
	 *
	 * @param array $qa_context Raw answer context entries.
	 *
	 * @return array
	 */
	private function sanitize_qa_context( $qa_context ) {
		if ( ! is_array( $qa_context ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $qa_context as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$answer = isset( $entry['answer'] ) ? sanitize_text_field( (string) $entry['answer'] ) : '';
			if ( '' === $answer ) {
				continue;
			}

			$sanitized[] = array(
				'id'       => isset( $entry['id'] ) ? sanitize_text_field( (string) $entry['id'] ) : '',
				'question' => isset( $entry['question'] ) ? sanitize_text_field( (string) $entry['question'] ) : '',
				'answer'   => $answer,
			);
		}

		return $sanitized;
	}

	/**
	 * Runs the authoritative-mirror client-side size pre-check for one enhancement request.
	 *
	 * Rejects the whole request when any single field, or the full payload, exceeds
	 * the active plan's byte limits. No enhancement is consumed because the service
	 * is never called.
	 *
	 * @param IC_AI_Integration $integration   Integration.
	 * @param array             $payload       Enhancement field payload.
	 * @param array             $request       Full outgoing request body.
	 * @param array             $site_settings Saved site settings.
	 *
	 * @return true|WP_Error
	 */
	private function payload_size_precheck( $integration, $payload, $request, $site_settings ) {
		$active_plan      = ! empty( $site_settings['active_plan'] ) && is_array( $site_settings['active_plan'] ) ? $site_settings['active_plan'] : array();
		$max_field_size   = isset( $active_plan['max_field_size'] ) ? absint( $active_plan['max_field_size'] ) : 0;
		$max_payload_size = isset( $active_plan['max_payload_size'] ) ? absint( $active_plan['max_payload_size'] ) : 0;

		if ( $max_field_size > 0 && is_array( $payload ) ) {
			foreach ( $payload as $field_key => $value ) {
				$size = $this->measure_value_bytes( $value );
				if ( $size > $max_field_size ) {
					return $this->oversize_error( 'ic_ai_field_too_large', $integration, sanitize_key( $field_key ), $max_field_size, $size );
				}
			}
		}

		if ( $max_payload_size > 0 ) {
			$payload_size = strlen( (string) wp_json_encode( $request ) );
			if ( $payload_size > $max_payload_size ) {
				return $this->oversize_error( 'ic_ai_payload_too_large', $integration, '', $max_payload_size, $payload_size );
			}
		}

		return true;
	}

	/**
	 * Returns the byte length of one payload value.
	 *
	 * @param mixed $value Field value.
	 *
	 * @return int
	 */
	private function measure_value_bytes( $value ) {
		if ( is_scalar( $value ) ) {
			return strlen( (string) $value );
		}

		return strlen( (string) wp_json_encode( $value ) );
	}

	/**
	 * Converts one remote 413 oversize response into a user-facing oversize error.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 * @param mixed             $error       Candidate decoded error.
	 *
	 * @return WP_Error|null
	 */
	private function oversize_response_error( $integration, $error ) {
		if ( ! is_wp_error( $error ) ) {
			return null;
		}
		$data   = $error->get_error_data();
		$status = ! empty( $data['status'] ) ? absint( $data['status'] ) : 0;
		if ( 413 !== $status ) {
			return null;
		}
		$body = ! empty( $data['body'] ) && is_array( $data['body'] ) ? $data['body'] : array();
		$code = ! empty( $body['code'] ) ? sanitize_key( $body['code'] ) : '';
		if ( ! in_array( $code, array( 'ic_ai_field_too_large', 'ic_ai_payload_too_large' ), true ) ) {
			return null;
		}
		$field = ! empty( $body['field'] ) ? sanitize_key( $body['field'] ) : '';
		$limit = isset( $body['limit'] ) ? absint( $body['limit'] ) : 0;
		$size  = isset( $body['size'] ) ? absint( $body['size'] ) : 0;

		return $this->oversize_error( $code, $integration, $field, $limit, $size );
	}

	/**
	 * Builds one user-facing oversize enhancement error.
	 *
	 * @param string            $code        Oversize error code.
	 * @param IC_AI_Integration $integration Integration.
	 * @param string            $field_key   Offending field key, if any.
	 * @param int               $limit       Allowed byte limit.
	 * @param int               $size        Measured byte size.
	 *
	 * @return WP_Error
	 */
	private function oversize_error( $code, $integration, $field_key, $limit, $size ) {
		$field_label = $this->field_label( $integration, $field_key );
		$message     = $this->oversize_message( $code, $field_label, $limit );

		return new WP_Error(
			$code,
			$message,
			array(
				'status'      => 413,
				'field'       => $field_key,
				'field_label' => $field_label,
				'limit'       => absint( $limit ),
				'size'        => absint( $size ),
			)
		);
	}

	/**
	 * Returns the human label for one integration field key.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 * @param string            $field_key   Field key.
	 *
	 * @return string
	 */
	private function field_label( $integration, $field_key ) {
		if ( empty( $field_key ) || ! $integration instanceof IC_AI_Integration ) {
			return '';
		}
		$target = $integration->target( 'post' );
		$field  = $target instanceof IC_AI_Target ? ( $target->field_map()[ $field_key ] ?? array() ) : array();
		if ( is_array( $field ) && ! empty( $field['label'] ) ) {
			return sanitize_text_field( $field['label'] );
		}

		return '';
	}

	/**
	 * Returns one user-facing oversize message.
	 *
	 * @param string $code        Oversize error code.
	 * @param string $field_label Offending field label, if any.
	 * @param int    $limit       Allowed byte limit.
	 *
	 * @return string
	 */
	private function oversize_message( $code, $field_label, $limit ) {
		$limit_label = $this->approximate_character_limit( $limit );
		if ( 'ic_ai_field_too_large' === $code ) {
			if ( ! empty( $field_label ) ) {
				return sprintf(
					/* translators: 1: field label, 2: approximate maximum allowed size. */
					__( 'The "%1$s" field is too large to enhance. Reduce it to around %2$s and try again.', 'post-type-x' ),
					$field_label,
					$limit_label
				);
			}

			return sprintf(
				/* translators: %s: approximate maximum allowed size. */
				__( 'One field is too large to enhance. Reduce it to around %s and try again.', 'post-type-x' ),
				$limit_label
			);
		}

		return sprintf(
			/* translators: %s: approximate maximum allowed size. */
			__( 'The selected content is too large to enhance. Reduce it to around %s total and try again.', 'post-type-x' ),
			$limit_label
		);
	}

	/**
	 * Returns one approximate character-limit label for user-facing messages.
	 *
	 * @param int $limit Stored byte limit.
	 *
	 * @return string
	 */
	private function approximate_character_limit( $limit ) {
		return sprintf(
			/* translators: %s: approximate character count. */
			__( '%s characters', 'post-type-x' ),
			number_format_i18n( absint( $limit ) )
		);
	}

	/**
	 * Returns whether the cached public catalog payload is due for a re-fetch.
	 *
	 * @param array $site_settings Site settings.
	 *
	 * @return bool
	 */
	public function public_catalog_is_stale( $site_settings ) {
		if ( empty( $site_settings['plans'] ) && empty( $site_settings['enhancement_packs'] ) ) {
			return true;
		}

		$synced_at = ! empty( $site_settings['plans_synced_at'] ) ? absint( $site_settings['plans_synced_at'] ) : 0;

		return empty( $synced_at ) || ( time() - $synced_at ) > self::PUBLIC_CATALOG_TTL;
	}

	/**
	 * Refreshes the locally cached public catalog payload.
	 *
	 * @return array|WP_Error
	 */
	public function refresh_public_catalog_cache() {
		$catalog_url = add_query_arg(
			array(
				'locale'   => $this->validated_site_locale(),
				'site_url' => home_url(),
				'blog_id'  => get_current_blog_id(),
			),
			$this->build_endpoint( $this->remote_url(), self::PLANS_PATH )
		);
		$response    = wp_remote_get(
			$catalog_url,
			array(
				'timeout' => 20,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			$err = $this->friendly_remote_error( 0, array(), $response );
			$this->update_site_settings(
				array(
					'last_error'      => $err['message'],
					'last_error_code' => $err['code'],
				)
			);

			return $response;
		}
		$decoded = $this->decode_response( $response );
		if ( is_wp_error( $decoded ) ) {
			$this->update_site_settings(
				array(
					'last_error'      => $decoded->get_error_message(),
					'last_error_code' => $decoded->get_error_code(),
				)
			);

			return $decoded;
		}

		$this->update_site_settings(
			array_merge(
				array(
					'plans'             => isset( $decoded['plans'] ) && is_array( $decoded['plans'] ) ? $decoded['plans'] : array(),
					'enhancement_packs' => isset( $decoded['enhancement_packs'] ) && is_array( $decoded['enhancement_packs'] ) ? $decoded['enhancement_packs'] : array(),
					'field_modes'       => isset( $decoded['field_modes'] ) && is_array( $decoded['field_modes'] ) ? $decoded['field_modes'] : array(),
					'catalog_available' => ! empty( $decoded['catalog_available'] ) ? 1 : 0,
					'catalog_message'   => isset( $decoded['catalog_message'] ) ? sanitize_text_field( $decoded['catalog_message'] ) : '',
					'plans_synced_at'   => time(),
					'last_error'        => '',
					'last_error_code'   => '',
				),
				$this->display_definition_updates( $decoded )
			)
		);

		return $decoded;
	}

	/**
	 * Starts one pending purchase flow and returns the public checkout payload.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 * @param array             $args        Billing session args.
	 *
	 * @return array|WP_Error
	 */
	public function create_billing_session( $integration, $args = array() ) {
		$args = wp_parse_args(
			is_array( $args ) ? $args : array(),
			array(
				'mode'            => 'purchase',
				'context_mode'    => 'checkout',
				'return_url'      => '',
				'callback_url'    => '',
				'callback_secret' => '',
				'post_type'       => $integration->post_type(),
				'source'          => 'settings',
				'plan_slug'       => '',
				'selection'       => array(),
			)
		);
		$mode = sanitize_key( $args['mode'] );
		if ( ! in_array( $mode, array( 'purchase', 'upgrade', 'enhancements' ), true ) ) {
			$mode = 'purchase';
		}
		$selection    = is_array( $args['selection'] ) ? $args['selection'] : array();
		$request_body = array(
			'context_mode'    => 'browse' === $args['context_mode'] ? 'browse' : 'checkout',
			'site_url'        => home_url(),
			'site_label'      => get_bloginfo( 'name' ),
			'blog_id'         => get_current_blog_id(),
			'plugin_slug'     => $integration->plugin_slug(),
			'mode'            => $mode,
			'return_url'      => esc_url_raw( $args['return_url'] ),
			'callback_url'    => esc_url_raw( $args['callback_url'] ),
			'callback_secret' => sanitize_text_field( $args['callback_secret'] ),
			'post_type'       => sanitize_key( $args['post_type'] ),
			'source'          => sanitize_key( $args['source'] ),
			'plan_slug'       => sanitize_key( $args['plan_slug'] ),
			'selection'       => $selection,
			'capabilities'    => array( 'free_license_confirmation_v1' ),
		);
		$license_key  = $this->billing_lookup_license_key();
		if ( ! empty( $license_key ) ) {
			$request_body['license_key'] = $license_key;
		}
		$body     = wp_json_encode( $request_body );
		$response = wp_remote_post(
			$this->build_endpoint( $this->remote_url(), self::BILLING_SESSION_PATH ),
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $this->normalize_billing_error( $response );
		}
		$decoded = $this->decode_response( $response );
		if ( is_wp_error( $decoded ) ) {
			return $this->normalize_billing_error( $decoded );
		}

		$purchase_type = ! empty( $selection['ic_ai_purchase_type'] ) ? sanitize_key( $selection['ic_ai_purchase_type'] ) : 'plan';
		if ( ! in_array( $purchase_type, array( 'plan', 'enhancements' ), true ) ) {
			$purchase_type = 'plan';
		}
		$item_slug = ! empty( $selection['ic_ai_item_slug'] ) ? sanitize_key( $selection['ic_ai_item_slug'] ) : sanitize_key( $args['plan_slug'] );
		$quantity  = 'enhancements' === $purchase_type ? max( 1, absint( $selection['ic_ai_quantity'] ) ) : 1;

		$settings = $this->site_settings();
		$update = array(
			'plans'                 => isset( $decoded['plans'] ) && is_array( $decoded['plans'] ) ? $decoded['plans'] : array(),
			'enhancement_packs'     => isset( $decoded['enhancement_packs'] ) && is_array( $decoded['enhancement_packs'] ) ? $decoded['enhancement_packs'] : array(),
			'catalog_available'     => ! empty( $decoded['catalog_available'] ) ? 1 : 0,
			'catalog_message'       => isset( $decoded['catalog_message'] ) ? sanitize_text_field( $decoded['catalog_message'] ) : '',
			'upgrade_url'           => isset( $decoded['upgrade_url'] ) ? esc_url_raw( $decoded['upgrade_url'] ) : '',
			'buy_enhancements_url'  => isset( $decoded['buy_enhancements_url'] ) ? esc_url_raw( $decoded['buy_enhancements_url'] ) : '',
		);
		if ( ! empty( $decoded['client_id'] ) && empty( $settings['client_id'] ) ) {
			$update['pending_client_id'] = sanitize_text_field( $decoded['client_id'] );
		}
		if ( 'browse' !== $args['context_mode'] && ! in_array( $settings['pending_status'], array( 'pending_confirmation', 'confirmed_pending_activation' ), true ) ) {
			$update = array_merge(
				$update,
				array(
					'pending_status'        => 'pending',
					'pending_post_type'     => sanitize_key( $args['post_type'] ),
					'pending_source'        => sanitize_key( $args['source'] ),
					'pending_started_at'    => gmdate( 'c' ),
					'pending_message'       => '',
					'pending_user_id'       => get_current_user_id(),
					'pending_plan_slug'     => $item_slug,
					'pending_purchase_type' => $purchase_type,
					'pending_item_slug'     => $item_slug,
					'pending_quantity'      => $quantity,
					'pending_recovery_type' => '',
				)
			);
		}
		if ( ! empty( $decoded['confirmation']['status'] ) && ! empty( $args['callback_secret'] ) && ! empty( $settings['pending_callback_secret'] ) && hash_equals( $settings['pending_callback_secret'], $args['callback_secret'] ) ) {
			$status = sanitize_key( $decoded['confirmation']['status'] );
			if ( in_array( $status, array( 'pending_confirmation', 'confirmed_pending_activation' ), true ) ) {
				$update['pending_status'] = $status;
				$update['pending_plan_slug'] = sanitize_key( $decoded['pending_plan_slug'] );
				$this->store_free_license_confirmation_state( $decoded['confirmation'] );
			} elseif ( in_array( $status, array( 'expired', 'superseded' ), true ) ) {
				$this->clear_pending_purchase();
				foreach ( array_keys( $update ) as $key ) {
					if ( 0 === strpos( $key, 'pending_' ) ) {
						unset( $update[ $key ] );
					}
				}
			}
		}
		$this->update_site_settings( $update );

		return $decoded;
	}

	/**
	 * Returns the stored AI license key used for embedded billing customer lookup.
	 *
	 * @return string
	 */
	private function billing_lookup_license_key() {
		$site_settings = $this->site_settings();
		if ( ! empty( $site_settings['license_key'] ) ) {
			return sanitize_text_field( $site_settings['license_key'] );
		}

		return sanitize_text_field( (string) get_option( 'custom_license_code', '' ) );
	}

	/**
	 * Forwards one legacy checkout AJAX payload to the remote AI checkout host.
	 *
	 * @param array $payload Legacy form-encoded AJAX payload.
	 *
	 * @return array|WP_Error
	 */
	public function forward_checkout_ajax( $payload ) {
		$payload = is_array( $payload ) ? $payload : array();
		$action  = ! empty( $payload['action'] ) ? sanitize_key( $payload['action'] ) : '';
		if ( empty( $action ) ) {
			return new WP_Error( 'ic_ai_checkout_proxy_missing_action', __( 'The AI checkout request is missing an action.', 'post-type-x' ), array( 'status' => 400 ) );
		}

		$response = wp_remote_post(
			$this->build_endpoint( $this->remote_url(), self::CHECKOUT_AJAX_PATH ),
			array(
				'timeout' => $this->effective_request_timeout(),
				'body'    => $payload,
				'headers' => array(
					'Accept' => '*/*',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'ic_ai_checkout_proxy_failed',
				__( 'The AI checkout request could not reach impleCode.', 'post-type-x' ),
				array(
					'status'       => 502,
					'remote_error' => $response->get_error_message(),
				)
			);
		}

		return $response;
	}

	/**
	 * Returns whether the error indicates a missing remote AI billing route.
	 *
	 * @param mixed $error Candidate error.
	 *
	 * @return bool
	 */
	public function is_billing_unavailable_error( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return false;
		}
		if ( 'ic_ai_billing_unavailable' === $error->get_error_code() ) {
			return true;
		}
		if ( ! in_array( $error->get_error_code(), array( 'ic_ai_remote_error', 'ic_ai_not_found' ), true ) ) {
			return false;
		}

		$data   = $error->get_error_data();
		$status = ! empty( $data['status'] ) ? absint( $data['status'] ) : 0;
		if ( 404 !== $status ) {
			return false;
		}

		$body = ! empty( $data['body'] ) && is_array( $data['body'] ) ? $data['body'] : array();
		if ( ! empty( $body['code'] ) && 'rest_no_route' === sanitize_key( $body['code'] ) ) {
			return true;
		}

		$message = ! empty( $body['message'] ) ? sanitize_text_field( $body['message'] ) : $error->get_error_message();

		return false !== stripos( $message, 'No route was found matching the URL and request method.' );
	}

	/**
	 * Returns whether the error indicates one invalid signed AI billing fragment request.
	 *
	 * @param mixed $error Candidate error.
	 *
	 * @return bool
	 */
	public function is_billing_signature_invalid_error( $error ) {
		if ( ! is_wp_error( $error ) || ! in_array( $error->get_error_code(), array( 'ic_ai_remote_error', 'ic_ai_auth' ), true ) ) {
			return false;
		}

		$data   = $error->get_error_data();
		$status = ! empty( $data['status'] ) ? absint( $data['status'] ) : 0;
		if ( 403 !== $status ) {
			return false;
		}

		$body    = ! empty( $data['body'] ) && is_array( $data['body'] ) ? $data['body'] : array();
		$message = ! empty( $body['message'] ) ? sanitize_text_field( $body['message'] ) : sanitize_text_field( $error->get_error_message() );

		return $this->is_signature_invalid_message( $message );
	}

	/**
	 * Returns whether the error indicates an invalid AI registration key.
	 *
	 * @param mixed $error Candidate error.
	 *
	 * @return bool
	 */
	public function is_invalid_registration_key_error( $error ) {
		if ( ! is_wp_error( $error ) || ! in_array( $error->get_error_code(), array( 'ic_ai_remote_error', 'ic_ai_auth' ), true ) ) {
			return false;
		}

		$data   = $error->get_error_data();
		$status = ! empty( $data['status'] ) ? absint( $data['status'] ) : 0;
		if ( 403 !== $status ) {
			return false;
		}

		$body      = ! empty( $data['body'] ) && is_array( $data['body'] ) ? $data['body'] : array();
		$body_code = ! empty( $body['code'] ) ? sanitize_key( $body['code'] ) : '';
		if ( in_array( $body_code, array( 'ic_ai_invalid_registration_credentials', 'missing_ai_license_product', 'ai_license_website_mismatch' ), true ) ) {
			return true;
		}

		$message = ! empty( $body['message'] ) ? sanitize_text_field( $body['message'] ) : sanitize_text_field( $error->get_error_message() );

		return 'Invalid AI registration credentials.' === $message;
	}

	/**
	 * Returns one structured field error for invalid AI registration credentials.
	 *
	 * @param WP_Error $error Registration error.
	 *
	 * @return array
	 */
	public function registration_license_key_field_error( $error ) {
		$field_error = array(
			'field'   => 'license_key',
			'message' => is_wp_error( $error ) ? $error->get_error_message() : '',
		);
		if ( ! is_wp_error( $error ) ) {
			return $field_error;
		}

		$data      = $error->get_error_data();
		$body      = ! empty( $data['body'] ) && is_array( $data['body'] ) ? $data['body'] : array();
		$body_code = ! empty( $body['code'] ) ? sanitize_key( $body['code'] ) : '';
		if ( ! empty( $body_code ) ) {
			$field_error['code'] = $body_code;
		}
		if ( 'ai_license_website_mismatch' === $body_code && ! empty( $body['upgrade_url'] ) ) {
			$field_error['action_url']   = esc_url_raw( $body['upgrade_url'] );
			$field_error['action_label'] = __( 'Upgrade now', 'post-type-x' );
		}

		return $field_error;
	}

	/**
	 * Returns one structured field error for enhancement failures caused by stale AI credentials.
	 *
	 * @param WP_Error $error         Enhancement error.
	 * @param array    $site_settings Optional saved site settings.
	 *
	 * @return array
	 */
	public function enhancement_license_key_field_error( $error, $site_settings = array() ) {
		if ( ! $this->is_billing_signature_invalid_error( $error ) ) {
			return $this->default_site_field_error();
		}
		$site_settings = ! empty( $site_settings ) && is_array( $site_settings ) ? $site_settings : $this->site_settings();
		$field_error   = $this->site_field_error( $site_settings );

		if ( empty( $field_error['field'] ) || 'license_key' !== sanitize_key( $field_error['field'] ) || empty( $field_error['message'] ) ) {
			return $this->default_site_field_error();
		}

		return $field_error;
	}

	/**
	 * Stores one new pending purchase payload locally.
	 *
	 * @param array $settings Pending settings update.
	 *
	 * @return array
	 */
	public function update_pending_purchase( $settings ) {
		return $this->update_site_settings( is_array( $settings ) ? $settings : array() );
	}

	/** Read remote free-license confirmation status. */
	public function free_license_confirmation_status() {
		return $this->free_confirmation_request( self::FREE_CONFIRMATION_STATUS_PATH );
	}

	/** Request another remote free-license confirmation email. */
	public function resend_free_license_confirmation() {
		return $this->free_confirmation_request( self::FREE_CONFIRMATION_RESEND_PATH );
	}

	/**
	 * Returns the versioned canonical string signed for one confirmation request.
	 *
	 * The service recomputes the identical string from the REST route and the raw
	 * request body, so route, method, timestamp, request ID and body are all bound
	 * into a single signature.
	 *
	 * @param string $route      REST route without the /wp-json prefix.
	 * @param string $timestamp  Request timestamp.
	 * @param string $request_id Random one-use request ID.
	 * @param string $body       Raw JSON request body.
	 *
	 * @return string
	 */
	public static function free_confirmation_signature_base( $route, $timestamp, $request_id, $body ) {
		return 'v1' . "\n" . 'POST' . "\n" . $route . "\n" . $timestamp . "\n" . $request_id . "\n" . hash( 'sha256', (string) $body );
	}

	/**
	 * Returns the REST route for a confirmation endpoint path.
	 *
	 * @param string $path Endpoint path including the /wp-json prefix.
	 *
	 * @return string
	 */
	private function free_confirmation_route( $path ) {
		$path = '/' . ltrim( (string) $path, '/' );

		return 0 === strpos( $path, '/wp-json/' ) ? substr( $path, strlen( '/wp-json' ) ) : $path;
	}

	/**
	 * Send one callback-secret-authenticated confirmation request.
	 *
	 * @param string $path Service route path.
	 * @return array|WP_Error
	 */
	private function free_confirmation_request( $path ) {
		$pending  = $this->pending_purchase();
		$settings = $this->site_settings();
		$client_id = ! empty( $settings['client_id'] ) ? $settings['client_id'] : ( ! empty( $settings['pending_client_id'] ) ? $settings['pending_client_id'] : '' );
		if ( empty( $pending['callback_secret'] ) || empty( $client_id ) ) {
			return new WP_Error( 'ic_ai_confirmation_context_missing', __( 'The pending confirmation context is unavailable.', 'post-type-x' ) );
		}
		$request_id = wp_generate_uuid4();
		$payload    = array(
			'action'     => $this->signed_action_for_path( $path ),
			'client_id'  => sanitize_text_field( $client_id ),
			'site_url'   => home_url(),
			'blog_id'    => get_current_blog_id(),
			'plan_slug'  => ! empty( $pending['plan_slug'] ) ? sanitize_key( $pending['plan_slug'] ) : '',
			'request_id' => $request_id,
		);
		$body       = wp_json_encode( $payload );
		$timestamp  = (string) time();
		$signature  = hash_hmac(
			'sha256',
			self::free_confirmation_signature_base( $this->free_confirmation_route( $path ), $timestamp, $request_id, $body ),
			$pending['callback_secret']
		);
		$response   = wp_remote_post(
			$this->build_endpoint( $this->remote_url(), $path ),
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type'       => 'application/json',
					'Accept'             => 'application/json',
					'X-IC-AI-Timestamp'  => $timestamp,
					'X-IC-AI-Request-ID' => $request_id,
					'X-IC-AI-Signature'  => $signature,
				),
				'body'    => $body,
			)
		);

		return is_wp_error( $response ) ? $response : $this->decode_response( $response );
	}

	/**
	 * Stores the sanitized remote pending-confirmation snapshot.
	 *
	 * Only display-safe fields are persisted. The service keeps batch identity, the
	 * recipient address and the token; none of them are accepted here.
	 *
	 * @param array $remote Decoded remote status/resend response.
	 *
	 * @return array Normalized confirmation state.
	 */
	public function store_free_license_confirmation_state( $remote ) {
		$remote = is_array( $remote ) ? $remote : array();
		$resend = isset( $remote['resend'] ) && is_array( $remote['resend'] ) ? $remote['resend'] : array();
		$state  = array(
			'pending_confirmation_sent_at'    => ! empty( $remote['sent_at'] ) ? sanitize_text_field( $remote['sent_at'] ) : '',
			'pending_confirmation_expires_at' => ! empty( $remote['expires_at'] ) ? sanitize_text_field( $remote['expires_at'] ) : '',
			'pending_confirmation_resend_at'  => ! empty( $resend['retry_after'] ) ? time() + absint( $resend['retry_after'] ) : 0,
			'pending_confirmation_polled_at'  => time(),
		);
		$this->update_site_settings( $state );

		return $this->free_license_confirmation_state();
	}

	/**
	 * Returns the locally cached pending-confirmation display state.
	 *
	 * @return array
	 */
	public function free_license_confirmation_state() {
		$settings    = $this->site_settings();
		$resend_at   = ! empty( $settings['pending_confirmation_resend_at'] ) ? absint( $settings['pending_confirmation_resend_at'] ) : 0;
		$retry_after = $resend_at > time() ? $resend_at - time() : 0;

		return array(
			'sent_at'     => ! empty( $settings['pending_confirmation_sent_at'] ) ? sanitize_text_field( $settings['pending_confirmation_sent_at'] ) : '',
			'expires_at'  => ! empty( $settings['pending_confirmation_expires_at'] ) ? sanitize_text_field( $settings['pending_confirmation_expires_at'] ) : '',
			'retry_after' => $retry_after,
			'can_resend'  => 0 === $retry_after,
			'polled_at'   => ! empty( $settings['pending_confirmation_polled_at'] ) ? absint( $settings['pending_confirmation_polled_at'] ) : 0,
		);
	}

	/** Clears the cached pending-confirmation display state. */
	public function clear_free_license_confirmation_state() {
		return $this->update_site_settings(
			array(
				'pending_confirmation_sent_at'    => '',
				'pending_confirmation_expires_at' => '',
				'pending_confirmation_resend_at'  => 0,
				'pending_confirmation_polled_at'  => 0,
			)
		);
	}

	/**
	 * Returns the current pending purchase payload.
	 *
	 * @return array
	 */
	public function pending_purchase() {
		$settings = $this->site_settings();

		return array(
			'status'          => ! empty( $settings['pending_status'] ) ? sanitize_key( $settings['pending_status'] ) : '',
			'callback_secret' => ! empty( $settings['pending_callback_secret'] ) ? sanitize_text_field( $settings['pending_callback_secret'] ) : '',
			'post_type'       => ! empty( $settings['pending_post_type'] ) ? sanitize_key( $settings['pending_post_type'] ) : '',
			'source'          => ! empty( $settings['pending_source'] ) ? sanitize_key( $settings['pending_source'] ) : '',
			'started_at'      => ! empty( $settings['pending_started_at'] ) ? sanitize_text_field( $settings['pending_started_at'] ) : '',
			'message'         => ! empty( $settings['pending_message'] ) ? sanitize_text_field( $settings['pending_message'] ) : '',
			'user_id'         => ! empty( $settings['pending_user_id'] ) ? absint( $settings['pending_user_id'] ) : 0,
			'plan_slug'       => ! empty( $settings['pending_plan_slug'] ) ? sanitize_key( $settings['pending_plan_slug'] ) : '',
			'purchase_type'   => ! empty( $settings['pending_purchase_type'] ) ? sanitize_key( $settings['pending_purchase_type'] ) : '',
			'item_slug'       => ! empty( $settings['pending_item_slug'] ) ? sanitize_key( $settings['pending_item_slug'] ) : '',
			'quantity'        => ! empty( $settings['pending_quantity'] ) ? absint( $settings['pending_quantity'] ) : 0,
			'recovery_type'   => ! empty( $settings['pending_recovery_type'] ) ? sanitize_key( $settings['pending_recovery_type'] ) : '',
			'target_key'      => ! empty( $settings['pending_target_key'] ) ? sanitize_text_field( $settings['pending_target_key'] ) : '',
		);
	}

	/**
	 * Returns the effective purchase type for one pending-purchase payload (as returned by
	 * {@see pending_purchase()}), applying a backwards-compatible fallback for pending states
	 * saved before purchase types were persisted explicitly: such legacy states are treated as
	 * plan purchases unless the saved slug matches a known enhancement-pack slug in the cached
	 * catalog, in which case they are treated as legacy enhancement purchases.
	 *
	 * @param array $pending Pending purchase payload from {@see pending_purchase()}.
	 *
	 * @return string Either 'plan' or 'enhancements'.
	 */
	public function pending_purchase_type( $pending ) {
		$pending = is_array( $pending ) ? $pending : array();
		if ( ! empty( $pending['purchase_type'] ) ) {
			$purchase_type = sanitize_key( $pending['purchase_type'] );

			return 'enhancements' === $purchase_type ? 'enhancements' : 'plan';
		}

		$slug = ! empty( $pending['item_slug'] ) ? sanitize_key( $pending['item_slug'] ) : ( ! empty( $pending['plan_slug'] ) ? sanitize_key( $pending['plan_slug'] ) : '' );
		if ( ! empty( $slug ) && $this->is_known_enhancement_pack_slug( $slug ) ) {
			return 'enhancements';
		}

		return 'plan';
	}

	/**
	 * Returns whether one slug matches a known enhancement-pack slug in the cached catalog.
	 *
	 * @param string $slug Candidate slug.
	 *
	 * @return bool
	 */
	private function is_known_enhancement_pack_slug( $slug ) {
		$slug = sanitize_key( $slug );
		if ( empty( $slug ) ) {
			return false;
		}
		$site_settings = $this->site_settings();
		$packs         = ! empty( $site_settings['enhancement_packs'] ) && is_array( $site_settings['enhancement_packs'] ) ? $site_settings['enhancement_packs'] : array();
		foreach ( $packs as $pack ) {
			if ( is_array( $pack ) && ! empty( $pack['slug'] ) && sanitize_key( $pack['slug'] ) === $slug ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Clears the current pending purchase state.
	 *
	 * @return array
	 */
	public function clear_pending_purchase() {
		return $this->update_site_settings(
			array(
				'pending_client_id'       => '',
				'pending_status'          => '',
				'pending_callback_secret' => '',
				'pending_post_type'       => '',
				'pending_target_key'      => '',
				'pending_source'          => '',
				'pending_started_at'      => '',
				'pending_message'         => '',
				'pending_user_id'         => 0,
				'pending_plan_slug'       => '',
				'pending_purchase_type'   => '',
				'pending_item_slug'       => '',
				'pending_quantity'        => 0,
				'pending_recovery_type'   => '',
				// Pending free-license confirmation display state is part of the same
				// pending purchase and must not survive it.
				'pending_confirmation_sent_at'    => '',
				'pending_confirmation_expires_at' => '',
				'pending_confirmation_resend_at'  => 0,
				'pending_confirmation_polled_at'  => 0,
			)
		);
	}

	/**
	 * Returns all currently enabled post types.
	 *
	 * @return array
	 */
	public function enabled_post_types() {
		$enabled = array();
		foreach ( $this->all_target_settings() as $post_type => $targets ) {
			foreach ( (array) $targets as $target_key => $settings ) {
				if ( ! empty( $settings['enabled'] ) ) {
					$enabled[] = sanitize_key( $target_key ); }
			}
		}

		return array_values( array_unique( $enabled ) );
	}

	/**
	 * Sends a signed JSON request.
	 *
	 * @param string $path     Endpoint path.
	 * @param array  $payload  JSON payload.
	 * @param string $secret   Shared secret.
	 *
	 * @return array|WP_Error
	 */
	private function signed_request( $path, $payload, $secret ) {
		$payload['action'] = $this->signed_action_for_path( $path );
		if ( ! isset( $payload['locale'] ) ) {
			// Signed so the service can render the localized display copy (marketing
			// controls, disclosure, plan detail rows) under this site's locale.
			$payload['locale'] = $this->validated_site_locale();
		}
		$body      = wp_json_encode( $payload );
		$timestamp = (string) time();
		$signature = hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );

		$response = wp_remote_post(
			$this->build_endpoint( $this->remote_url(), $path ),
			array(
				'timeout' => $this->signed_request_timeout( $path ),
				'headers' => array(
					'Content-Type'      => 'application/json',
					'Accept'            => 'application/json',
					'X-IC-AI-Timestamp' => $timestamp,
					'X-IC-AI-Signature' => $signature,
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			$friendly = $this->friendly_remote_error( 0, array(), $response );

			return new WP_Error( $friendly['code'], $friendly['message'], $response->get_error_data() );
		}

		return $response;
	}

	/**
	 * Returns the action identifier signed alongside a request's payload.
	 *
	 * @param string $path Endpoint path.
	 *
	 * @return string
	 */
	private function signed_action_for_path( $path ) {
		return sanitize_key( basename( (string) $path ) );
	}

	/**
	 * Categorizes a remote failure into a semantic code and a clear, actionable message.
	 *
	 * An explicit server-provided message always wins (legacy field-error string
	 * matching depends on the raw server message being preserved verbatim).
	 *
	 * @param int           $status   HTTP status code (0 for connection-level failures).
	 * @param array         $data     Decoded response body, if any.
	 * @param WP_Error|null $wp_error Connection-level error, if any.
	 *
	 * @return array {
	 *     @type string $code    Semantic error code.
	 *     @type string $message User-facing message.
	 * }
	 */
	private function friendly_remote_error( $status, $data = array(), $wp_error = null ) {
		$host = (string) wp_parse_url( $this->remote_url(), PHP_URL_HOST );

		if ( is_array( $data ) && ! empty( $data['message'] ) ) {
			$message = sanitize_text_field( $data['message'] );
			if ( 'ic_ai_quota_blocked' === sanitize_key( (string) ( $data['code'] ?? '' ) ) || ( in_array( (int) $status, array( 402, 429 ), true ) && false !== strpos( strtolower( $message ), 'quota' ) ) ) {
				return array(
					'code'    => 'ic_ai_quota_blocked',
					'message' => $message,
				);
			}
			if ( ! empty( $data['queueable'] ) && 429 === (int) $status ) {
				return array(
					'code'    => ! empty( $data['code'] ) ? sanitize_key( $data['code'] ) : 'ic_ai_busy',
					'message' => $message,
				);
			}
			if ( $this->is_signature_invalid_message( $message ) ) {
				return array(
					'code'    => 'ic_ai_auth',
					'message' => $this->signature_invalid_user_message(),
				);
			}

			return array(
				'code'    => 'ic_ai_remote_error',
				'message' => $message,
			);
		}

		if ( $wp_error instanceof WP_Error ) {
			if ( $this->is_timeout_wp_error( $wp_error ) ) {
				return array(
					'code'    => 'ic_ai_timeout',
					'message' => $this->timeout_user_message(),
				);
			}

			return array(
				'code'    => 'ic_ai_unreachable',
				/* translators: %s: remote AI service host name. */
				'message' => sprintf( __( 'Could not reach the impleCode AI service at %s. Check this site\'s outbound internet connection, then click Retry.', 'post-type-x' ), $host ),
			);
		}

		$status = (int) $status;
		if ( 401 === $status || 403 === $status ) {
			return array(
				'code'    => 'ic_ai_auth',
				'message' => __( 'The impleCode AI credentials were rejected — reconnect to refresh your AI license.', 'post-type-x' ),
			);
		}
		if ( 404 === $status ) {
			return array(
				'code'    => 'ic_ai_not_found',
				'message' => __( 'The impleCode AI service endpoint was not found. Confirm the AI service URL is online.', 'post-type-x' ),
			);
		}
		if ( 408 === $status || 429 === $status ) {
			return array(
				'code'    => 'ic_ai_busy',
				'message' => __( 'The impleCode AI service is busy or timed out — please Retry in a moment.', 'post-type-x' ),
			);
		}
		if ( $status >= 500 ) {
			return array(
				'code'    => 'ic_ai_service_down',
				'message' => __( 'The impleCode AI service is temporarily unavailable — please Retry in a few minutes.', 'post-type-x' ),
			);
		}

		return array(
			'code'    => 'ic_ai_remote_error',
			/* translators: %d: HTTP status code. */
			'message' => sprintf( __( 'The impleCode AI request failed (HTTP %d).', 'post-type-x' ), $status ),
		);
	}

	/**
	 * Returns whether one remote enhancement error is queueable in the editor.
	 *
	 * @param WP_Error $error Remote enhancement error.
	 *
	 * @return bool
	 */
	private function is_queueable_remote_error( $error ) {
		if ( ! $error instanceof WP_Error ) {
			return false;
		}

		$data = $error->get_error_data();

		return ! empty( $data['status'] ) && 429 === absint( $data['status'] ) && ! empty( $data['body']['queueable'] );
	}

	/**
	 * Converts one pending remote job payload into the local queueable error flow.
	 *
	 * @param mixed $decoded Decoded remote response.
	 *
	 * @return WP_Error|false
	 */
	private function pending_remote_job_error( $decoded ) {
		if ( ! is_array( $decoded ) || empty( $decoded['queueable'] ) || empty( $decoded['job_id'] ) || empty( $decoded['job_status'] ) ) {
			return false;
		}

		return new WP_Error(
			'ic_ai_job_pending',
			! empty( $decoded['message'] ) ? sanitize_text_field( $decoded['message'] ) : __( 'The impleCode AI service is still processing this request.', 'post-type-x' ),
			array(
				'status' => 429,
				'body'   => $decoded,
			)
		);
	}

	/**
	 * Returns whether one message represents a stale or mismatched AI request signature.
	 *
	 * @param string $message Candidate message.
	 *
	 * @return bool
	 */
	private function is_signature_invalid_message( $message ) {
		$message = sanitize_text_field( (string) $message );

		return 'The AI request signature is invalid.' === $message || $this->signature_invalid_user_message() === $message;
	}

	/**
	 * Returns the user-facing guidance for one invalid AI request signature.
	 *
	 * @return string
	 */
	private function signature_invalid_user_message() {
		return __( 'The saved AI connection is no longer valid. Save AI Settings again to reconnect this site with impleCode AI.', 'post-type-x' );
	}

	/**
	 * Returns whether the transport error is one timeout failure.
	 *
	 * @param WP_Error $wp_error Remote transport error.
	 *
	 * @return bool
	 */
	private function is_timeout_wp_error( $wp_error ) {
		if ( ! $wp_error instanceof WP_Error ) {
			return false;
		}

		$message = sanitize_text_field( $wp_error->get_error_message() );

		return false !== stripos( $message, 'timed out' ) || false !== stripos( $message, 'timeout' );
	}

	/**
	 * Returns the user-facing timeout recovery message.
	 *
	 * @return string
	 */
	private function timeout_user_message() {
		return __( 'The AI service took too long to respond. Please try again.', 'post-type-x' );
	}

	/**
	 * Returns the effective inherited timeout for long-running AI requests.
	 *
	 * @return int
	 */
	private function effective_request_timeout() {
		$site_settings = $this->site_settings();

		return $this->sanitized_request_timeout( isset( $site_settings['request_timeout'] ) ? $site_settings['request_timeout'] : 40 );
	}

	/**
	 * Returns the timeout for one signed AI request path.
	 *
	 * @param string $path Remote path.
	 *
	 * @return int
	 */
	private function signed_request_timeout( $path ) {
		if ( self::ENHANCE_PATH === $path ) {
			return $this->effective_request_timeout();
		}

		return 40;
	}

	/**
	 * Sanitizes one request-timeout value.
	 *
	 * @param mixed $timeout Raw timeout.
	 *
	 * @return int
	 */
	private function sanitized_request_timeout( $timeout ) {
		return max( 1, absint( $timeout ) );
	}

	/**
	 * Decodes a remote JSON response.
	 *
	 * @param array $response Remote response.
	 *
	 * @return array|WP_Error
	 */
	private function decode_response( $response ) {
		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );
		$data   = json_decode( $body, true );

		if ( $status < 200 || $status >= 300 ) {
			$err = $this->friendly_remote_error( $status, $data );

			return new WP_Error(
				$err['code'],
				$err['message'],
				array(
					'status' => $status,
					'body'   => $data,
				)
			);
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'ic_ai_invalid_response', __( 'The remote AI service returned an invalid response.', 'post-type-x' ) );
		}

		return $data;
	}

	/**
	 * Returns whether an enhancement error should be masked from editor users.
	 *
	 * @param WP_Error $error Enhancement error.
	 *
	 * @return bool
	 */
	private function should_mask_internal_enhancement_error( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return false;
		}

		if ( 'ic_ai_invalid_response' === $error->get_error_code() ) {
			return true;
		}

		$data   = $error->get_error_data();
		$status = ! empty( $data['status'] ) ? absint( $data['status'] ) : 0;

		return $status >= 500;
	}

	/**
	 * Returns the generic client-safe service error message.
	 *
	 * @return string
	 */
	private function service_unavailable_message() {
		return __( 'impleCode AI is temporarily unavailable. Please try again in a moment.', 'post-type-x' );
	}

	/**
	 * Normalizes remote billing errors into local user-facing errors when needed.
	 *
	 * @param mixed $error Candidate error.
	 *
	 * @return mixed
	 */
	private function normalize_billing_error( $error ) {
		if ( ! $this->is_billing_unavailable_error( $error ) ) {
			return $error;
		}

		return new WP_Error(
			'ic_ai_billing_unavailable',
			__( 'AI plan checkout is unavailable right now. If you already have an AI License Key, enter it below to activate AI.', 'post-type-x' ),
			array(
				'status'       => 503,
				'remote_error' => is_wp_error( $error ) ? $error->get_error_data() : array(),
			)
		);
	}

	/**
	 * Returns one normalized site-settings update payload for a field error.
	 *
	 * @param array $field_error Field-error payload.
	 *
	 * @return array
	 */
	private function site_field_error_updates( $field_error ) {
		$field_error = wp_parse_args( is_array( $field_error ) ? $field_error : array(), $this->default_site_field_error() );

		return array(
			'field_error_field'        => ! empty( $field_error['field'] ) ? sanitize_key( $field_error['field'] ) : '',
			'field_error_message'      => ! empty( $field_error['message'] ) ? sanitize_text_field( $field_error['message'] ) : '',
			'field_error_code'         => ! empty( $field_error['code'] ) ? sanitize_key( $field_error['code'] ) : '',
			'field_error_action_url'   => ! empty( $field_error['action_url'] ) ? esc_url_raw( $field_error['action_url'] ) : '',
			'field_error_action_label' => ! empty( $field_error['action_label'] ) ? sanitize_text_field( $field_error['action_label'] ) : '',
		);
	}

	/**
	 * Returns one derived field error from legacy saved last-error state.
	 *
	 * @param array $settings Site settings payload.
	 *
	 * @return array
	 */
	private function legacy_site_field_error( $settings ) {
		$message = ! empty( $settings['last_error'] ) ? sanitize_text_field( $settings['last_error'] ) : '';
		if ( empty( $message ) ) {
			return $this->default_site_field_error();
		}
		if ( 'Invalid AI registration credentials.' === $message ) {
			return array(
				'field'        => 'license_key',
				'message'      => $message,
				'code'         => 'ic_ai_invalid_registration_credentials',
				'action_url'   => '',
				'action_label' => '',
			);
		}
		if ( false !== strpos( $message, 'This AI License Key is assigned to' ) && false !== strpos( $message, 'cannot be used on' ) ) {
			return array(
				'field'        => 'license_key',
				'message'      => $message,
				'code'         => 'ai_license_website_mismatch',
				'action_url'   => ! empty( $settings['upgrade_url'] ) ? esc_url_raw( $settings['upgrade_url'] ) : '',
				'action_label' => ! empty( $settings['upgrade_url'] ) ? __( 'Upgrade now', 'post-type-x' ) : '',
			);
		}

		return $this->default_site_field_error();
	}

	/**
	 * Normalizes an endpoint URL.
	 *
	 * @param string $base_url Base URL.
	 * @param string $path     Path.
	 *
	 * @return string
	 */
	private function build_endpoint( $base_url, $path ) {
		return trailingslashit( untrailingslashit( esc_url_raw( $base_url ) ) ) . ltrim( $path, '/' );
	}

	/**
	 * Encrypts the local client secret.
	 *
	 * @param string $secret Secret.
	 *
	 * @return string
	 */
	private function encrypt_secret( $secret ) {
		if ( empty( $secret ) || ! function_exists( 'openssl_encrypt' ) ) {
			return base64_encode( (string) $secret );
		}
		$key    = hash( 'sha256', get_current_blog_id() . '|' . wp_salt( 'auth' ), true );
		$iv_len = openssl_cipher_iv_length( 'aes-256-cbc' );
		$iv     = random_bytes( $iv_len );
		$cipher = openssl_encrypt( $secret, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
		if ( false === $cipher ) {
			return base64_encode( (string) $secret );
		}

		return 'icai:' . base64_encode( $iv . $cipher );
	}

	/**
	 * Decrypts the local client secret.
	 *
	 * @param string $secret Secret.
	 *
	 * @return string
	 */
	private function decrypt_secret( $secret ) {
		if ( ! is_string( $secret ) || '' === $secret ) {
			return '';
		}
		if ( ! str_starts_with( $secret, 'icai:' ) ) {
			return (string) base64_decode( $secret, true );
		}
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}
		$raw    = base64_decode( substr( $secret, 5 ), true );
		$key    = hash( 'sha256', get_current_blog_id() . '|' . wp_salt( 'auth' ), true );
		$iv_len = openssl_cipher_iv_length( 'aes-256-cbc' );
		if ( false === $raw || strlen( $raw ) <= $iv_len ) {
			return '';
		}
		$iv     = substr( $raw, 0, $iv_len );
		$cipher = substr( $raw, $iv_len );
		$plain  = openssl_decrypt( $cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

		return false === $plain ? '' : (string) $plain;
	}

	/**
	 * Returns one cached fallback billing URL.
	 *
	 * @param string $mode Billing mode.
	 *
	 * @return string
	 */
	public function billing_fallback_url( $mode ) {
		$site_settings = $this->site_settings();

		return 'enhancements' === sanitize_key( $mode ) ? $site_settings['buy_enhancements_url'] : $site_settings['upgrade_url'];
	}

	/**
	 * Disables AI for all stored post types while keeping their field/model settings.
	 *
	 * @return void
	 */
	private function disable_all_post_types() {
		$all = $this->all_target_settings();
		foreach ( $all as $post_type => $targets ) {
			foreach ( (array) $targets as $target_key => $settings ) {
				if ( is_array( $settings ) ) {
					$settings['enabled']              = 0;
					$all[ $post_type ][ $target_key ] = $settings; }
			}
		}

		update_option( self::TARGET_OPTION, $all, false );
	}

	/**
	 * Clears the visible AI activation/disclosure state for all stored post types
	 * while preserving non-activation preferences such as optional analysis opt-in.
	 *
	 * @return void
	 */
	private function clear_all_post_type_activation_state() {
		$all = $this->all_target_settings();
		foreach ( $all as $post_type => $targets ) {
			foreach ( (array) $targets as $target_key => $settings ) {
				if ( is_array( $settings ) ) {
					$settings['enabled']              = 0;
					$settings['consented_at']         = '';
					$settings['consented_by']         = 0;
					$all[ $post_type ][ $target_key ] = $settings; }
			}
		}

		update_option( self::TARGET_OPTION, $all, false );
	}

	/**
	 * Returns whether the remote AI product catalog is available.
	 *
	 * @param array $settings Optional site settings payload.
	 *
	 * @return bool
	 */
	private function catalog_available( $settings = array() ) {
		$settings = is_array( $settings ) ? $settings : array();

		return ! empty( $settings['catalog_available'] );
	}

	/**
	 * Returns the remote AI product-catalog message.
	 *
	 * @param array $settings Optional site settings payload.
	 *
	 * @return string
	 */
	private function catalog_message( $settings = array() ) {
		$settings = is_array( $settings ) ? $settings : array();

		return ! empty( $settings['catalog_message'] ) ? sanitize_text_field( $settings['catalog_message'] ) : __( 'AI is currently unavailable because impleCode has no AI plans or AI Credit products configured.', 'post-type-x' );
	}
}
