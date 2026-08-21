<?php
/**
 * Shared defaults sync controller for admin overwrite tools.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles queued defaults sync lifecycle for admin overwrite boxes.
 */
class IC_EPC_Defaults_Sync_Controller {
	/**
	 * Controller configuration.
	 *
	 * @var array
	 */
	private $args = array();

	/**
	 * Whether WordPress hooks were already registered for this controller.
	 *
	 * @var bool
	 */
	private $hooks_registered = false;

	/**
	 * Derived internal field and action names.
	 *
	 * @var array
	 */
	private $field_names = array();

	/**
	 * Creates the controller instance.
	 *
	 * @param array $args Controller configuration.
	 */
	public function __construct( $args ) {
		$defaults   = array(
			'sync_key'              => '',
			'current_mode_callback' => '',
			'actions'               => array(),
			'request_values'        => array(),
			'meta_mappings'         => array(),
			'count_option_name'     => '',
			'settings_url'          => '',
			'capability'            => 'manage_product_settings',
			'singular_label'        => '',
			'plural_label'          => '',
			'dirty_selector'        => '',
		);
		$this->args = wp_parse_args( $args, $defaults );
		$this->set_field_names();
	}

	/**
	 * Builds internal field and action names from the unique sync key.
	 *
	 * @return void
	 */
	private function set_field_names() {
		$sync_key         = $this->get_sync_key();
		$count_update_key = str_replace( 'defaults_sync', 'update_count', $sync_key );
		$ajax_start_key   = preg_replace( '/^ic_epc_/', 'ic_epc_start_', $sync_key );
		if ( empty( $ajax_start_key ) ) {
			$ajax_start_key = $sync_key . '_start';
		}

		$this->field_names = array(
			'nonce_action'              => $sync_key,
			'nonce_field'               => $sync_key . '_nonce',
			'count_update_flag_field'   => $count_update_key,
			'count_update_nonce_field'  => $count_update_key . '_nonce',
			'count_update_nonce_action' => $count_update_key,
			'count_update_value_field'  => $this->get_count_option_name(),
			'request_flag_field'        => $sync_key,
			'form_actions_field'        => $sync_key . '_actions',
			'form_action_field'         => $sync_key . '_action',
			'include_empty_field'       => $sync_key . '_include_empty',
			'status_query_arg'          => $sync_key . '_status',
			'ajax_start_action'         => $ajax_start_key,
			'ajax_status_action'        => $sync_key . '_status',
		);
	}

	/**
	 * Returns a derived internal field/action name.
	 *
	 * @param string $name Field name key.
	 *
	 * @return string
	 */
	private function get_field_name( $name ) {
		return isset( $this->field_names[ $name ] ) ? $this->field_names[ $name ] : '';
	}

	/**
	 * Returns the unique sync key.
	 *
	 * @return string
	 */
	private function get_sync_key() {
		return sanitize_key( $this->args['sync_key'] );
	}

	/**
	 * Returns the short entity slug derived from the sync key.
	 *
	 * @return string
	 */
	private function get_sync_slug() {
		$slug = preg_replace( '/^ic_epc_/', '', $this->get_sync_key() );
		$slug = preg_replace( '/_defaults_sync$/', '', $slug );

		return sanitize_key( $slug );
	}

	/**
	 * Returns the internally derived queued request option name.
	 *
	 * @return string
	 */
	private function get_option_name() {
		return str_replace( '_defaults_sync', '_product_defaults_sync', $this->get_sync_key() );
	}

	/**
	 * Returns the internally derived count key used in queued requests.
	 *
	 * @return string
	 */
	private function get_count_key() {
		return $this->get_sync_slug() . '_count';
	}

	/**
	 * Returns the count option field name.
	 *
	 * @return string
	 */
	private function get_count_option_name() {
		return (string) $this->args['count_option_name'];
	}

	/**
	 * Returns the internally derived box ID.
	 *
	 * @return string
	 */
	private function get_box_id() {
		return str_replace( '_', '-', $this->get_sync_key() ) . '-box';
	}

	/**
	 * Returns the configured current source mode.
	 *
	 * @return string
	 */
	private function get_current_mode() {
		if ( is_callable( $this->args['current_mode_callback'] ) ) {
			return (string) call_user_func( $this->args['current_mode_callback'] );
		}

		return '';
	}

	/**
	 * Returns the settings screen URL.
	 *
	 * @return string
	 */
	private function get_settings_url() {
		if ( '' !== $this->args['settings_url'] ) {
			return (string) $this->args['settings_url'];
		}

		return '';
	}

	/**
	 * Returns the configured singular entity label.
	 *
	 * @return string
	 */
	private function get_singular_label() {
		return '' !== $this->args['singular_label'] ? (string) $this->args['singular_label'] : __( 'setting', 'post-type-x' );
	}

	/**
	 * Returns the configured plural entity label.
	 *
	 * @return string
	 */
	private function get_plural_label() {
		return '' !== $this->args['plural_label'] ? (string) $this->args['plural_label'] : $this->get_singular_label();
	}

	/**
	 * Returns a title-cased label for headings.
	 *
	 * @param string $label Label to title-case.
	 *
	 * @return string
	 */
	private function get_title_label( $label ) {
		return ucwords( strtolower( $label ) );
	}

	/**
	 * Returns generic UI text for the overwrite controller.
	 *
	 * @param string $key Text key.
	 *
	 * @return string
	 */
	private function get_text( $key ) {
		$singular = $this->get_singular_label();
		$plural   = $this->get_plural_label();

		$texts = array(
			'title'                 => sprintf(
				/* translators: %s: plural settings label, such as Attributes or Shipping. */
				__( 'Overwrite Existing Product %s Data', 'post-type-x' ),
				$this->get_title_label( $plural )
			),
			'description'           => sprintf(
				/* translators: %s: plural settings label, such as attributes or shipping. */
				__( 'This tool uses the currently saved default %s. If you changed the table above, save those changes first and then run the overwrite action.', 'post-type-x' ),
				$plural
			),
			'scheduled_message'     => sprintf(
				/* translators: %s: singular settings label, such as attribute or shipping. */
				__( 'The %s overwrite was queued and will now run through the existing product data updater.', 'post-type-x' ),
				$singular
			),
			'busy_message'          => sprintf(
				/* translators: %s: singular settings label, such as attribute or shipping. */
				__( 'Another product data reassignment is already in progress. Wait for it to finish before starting a %s overwrite.', 'post-type-x' ),
				$singular
			),
			'invalid_message'       => sprintf(
				/* translators: %s: singular settings label, such as attribute or shipping. */
				__( 'The requested overwrite action is not available for the current %s mode.', 'post-type-x' ),
				$singular
			),
			'queued_request_prefix' => __( 'An overwrite request is queued for', 'post-type-x' ),
			'done_text'             => sprintf(
				/* translators: %s: singular settings label, such as attribute or shipping. */
				__( 'The %s overwrite is done.', 'post-type-x' ),
				$singular
			),
			'processed_text'        => __( 'items processed while overwriting', 'post-type-x' ),
			'dirty_text'            => sprintf(
				/* translators: %s: singular settings label, such as attribute or shipping. */
				__( 'Save the %s settings above before starting an overwrite.', 'post-type-x' ),
				$singular
			),
			'no_selection_text'     => sprintf(
				/* translators: %s: singular settings label, such as attribute or shipping. */
				__( 'Select at least one %s setting to overwrite.', 'post-type-x' ),
				$singular
			),
			'include_empty_label'   => __( 'Overwrite with empty defaults too', 'post-type-x' ),
			'submit_label'          => __( 'Apply Selected to Existing Products', 'post-type-x' ),
			'start_error_text'      => __( 'Unable to start the overwrite action.', 'post-type-x' ),
			'status_error_text'     => __( 'Unable to read overwrite progress.', 'post-type-x' ),
		);

		return isset( $texts[ $key ] ) ? $texts[ $key ] : '';
	}

	/**
	 * Registers the WordPress hooks handled by this controller.
	 *
	 * @return void
	 */
	public function register_hooks() {
		if ( $this->hooks_registered ) {
			return;
		}

		if ( ! empty( $this->get_field_name( 'count_update_flag_field' ) ) ) {
			add_action( 'admin_init', array( $this, 'handle_count_update' ) );
		}
		add_action( 'admin_init', array( $this, 'handle_request' ) );
		add_action( 'wp_ajax_' . $this->get_field_name( 'ajax_start_action' ), array( $this, 'ajax_start' ) );
		add_action( 'wp_ajax_' . $this->get_field_name( 'ajax_status_action' ), array( $this, 'ajax_status' ) );
		add_filter( 'ic_update_product_data_post_meta_allowed_keys', array( $this, 'allow_product_meta_keys' ), 10, 2 );
		add_filter( 'ic_update_product_data_post_meta', array( $this, 'apply_to_product_meta' ), 10, 2 );
		add_action( 'ic_product_data_reassignment_done', array( $this, 'clear_request' ) );

		$this->hooks_registered = true;
	}

	/**
	 * Returns available overwrite actions.
	 *
	 * @param string $mode Optional source mode override.
	 *
	 * @return array
	 */
	public function get_actions( $mode = '' ) {
		if ( empty( $mode ) ) {
			$mode = $this->get_current_mode();
		}

		if ( ! empty( $this->args['actions'] ) && is_array( $this->args['actions'] ) ) {
			if ( isset( $this->args['actions'][ $mode ] ) && is_array( $this->args['actions'][ $mode ] ) ) {
				return $this->args['actions'][ $mode ];
			}

			return array();
		}

		return array();
	}

	/**
	 * Returns the queued request key for an action.
	 *
	 * @param string $action Sync action.
	 *
	 * @return string
	 */
	private function get_action_request_key( $action ) {
		if ( ! empty( $this->args['meta_mappings'][ $action ]['request_key'] ) ) {
			return (string) $this->args['meta_mappings'][ $action ]['request_key'];
		}

		return (string) $action;
	}

	/**
	 * Returns a request value from the configured direct values or callbacks.
	 *
	 * @param string $key Request value key.
	 *
	 * @return mixed
	 */
	private function get_request_value( $key ) {
		if ( ! is_array( $this->args['request_values'] ) || ! array_key_exists( $key, $this->args['request_values'] ) ) {
			return null;
		}

		$value = $this->args['request_values'][ $key ];
		if ( is_callable( $value ) ) {
			return call_user_func( $value );
		}

		return $value;
	}

	/**
	 * Returns normalized selected actions.
	 *
	 * @param array|string $actions Requested action or actions.
	 * @param string       $mode Optional source mode override.
	 *
	 * @return array
	 */
	public function get_selected_actions( $actions, $mode = '' ) {
		return ic_epc_normalize_sync_actions( $actions, array_keys( $this->get_actions( $mode ) ) );
	}

	/**
	 * Returns the display label for one action.
	 *
	 * @param string $action Sync action.
	 *
	 * @return string
	 */
	private function get_action_label( $action ) {
		foreach ( $this->args['actions'] as $actions ) {
			if ( isset( $actions[ $action ]['label'] ) ) {
				return (string) $actions[ $action ]['label'];
			}
		}

		return ucwords( str_replace( '_', ' ', $action ) );
	}

	/**
	 * Returns the progress label for one action.
	 *
	 * @param string $action Sync action.
	 *
	 * @return string
	 */
	public function get_action_progress_label( $action ) {
		$label = $this->get_action_label( $action );

		return sprintf(
			/* translators: %s: selected overwrite action label, such as attribute names or shipping prices. */
			__( 'product %s', 'post-type-x' ),
			strtolower( $label )
		);
	}

	/**
	 * Returns a combined progress label for selected actions.
	 *
	 * @param array|string $actions Selected actions.
	 * @param string       $mode Optional source mode override.
	 *
	 * @return string
	 */
	public function get_actions_progress_label( $actions, $mode = '' ) {
		$labels = array();
		foreach ( $this->get_selected_actions( $actions, $mode ) as $action ) {
			$label = $this->get_action_progress_label( $action );
			if ( '' !== $label ) {
				$labels[] = $label;
			}
		}

		return implode( ', ', $labels );
	}

	/**
	 * Builds the queued sync request payload.
	 *
	 * @param array|string $actions Requested action or actions.
	 * @param bool         $include_empty Whether empty defaults should also overwrite product meta.
	 * @param string       $mode Optional source mode override.
	 *
	 * @return array
	 */
	public function build_request( $actions, $include_empty = false, $mode = '' ) {
		$selected_actions = $this->get_selected_actions( $actions, $mode );
		$count_key        = $this->get_count_key();
		$count            = $this->get_request_value( 'count' );

		$request = array(
			'action'        => reset( $selected_actions ),
			'actions'       => $selected_actions,
			$count_key      => intval( $count ),
			'include_empty' => ! empty( $include_empty ) ? 1 : 0,
		);

		if ( empty( $selected_actions ) || $request[ $count_key ] < 1 ) {
			return array();
		}

		foreach ( $selected_actions as $action ) {
			$request_key = $this->get_action_request_key( $action );
			if ( empty( $request_key ) ) {
				continue;
			}
			$request[ $request_key ] = $this->get_request_value( $request_key );
		}

		return $request;
	}

	/**
	 * Starts a queued sync run.
	 *
	 * @param array|string $actions Requested action or actions.
	 * @param bool         $include_empty Whether empty defaults should also overwrite product meta.
	 *
	 * @return array
	 */
	public function start_sync( $actions, $include_empty = false ) {
		$selected_actions = $this->get_selected_actions( $actions );
		if ( empty( $selected_actions ) ) {
			return array(
				'status'  => 'invalid',
				'message' => $this->get_text( 'invalid_message' ),
			);
		}

		if ( ic_update_product_data_is_active() ) {
			return array(
				'status'  => 'busy',
				'message' => $this->get_text( 'busy_message' ),
			);
		}

		$request = $this->build_request( $selected_actions, $include_empty );
		if ( empty( $request ) ) {
			return array(
				'status'  => 'invalid',
				'message' => $this->get_text( 'invalid_message' ),
			);
		}

		update_option( $this->get_option_name(), $request, false );
		delete_transient( 'ic_update_product_data_done' );
		update_option( 'ic_update_product_data_done', -1 );
		ic_update_product_data();

		return array(
			'status'       => 'scheduled',
			'action'       => reset( $selected_actions ),
			'actions'      => $selected_actions,
			'action_label' => $this->get_actions_progress_label( $selected_actions ),
			'message'      => $this->get_text( 'scheduled_message' ),
		);
	}

	/**
	 * Returns the queued sync request.
	 *
	 * @return array
	 */
	public function get_request() {
		$request = get_option( $this->get_option_name(), array() );
		if ( ! is_array( $request ) ) {
			return array();
		}

		return $request;
	}

	/**
	 * Returns currently queued actions from a request.
	 *
	 * @param array $request Request payload.
	 *
	 * @return array
	 */
	private function get_request_actions( $request ) {
		$actions = ! empty( $request['actions'] ) ? $request['actions'] : array();
		if ( empty( $actions ) && ! empty( $request['action'] ) ) {
			$actions = array( $request['action'] );
		}

		return $this->get_selected_actions( $actions );
	}

	/**
	 * Returns the current sync job status payload.
	 *
	 * @return array
	 */
	public function get_job_status() {
		$request      = $this->get_request();
		$done         = intval( get_option( 'ic_update_product_data_done', 0 ) );
		$in_progress  = ic_update_product_data_is_active();
		$actions      = $this->get_request_actions( $request );
		$action       = ! empty( $request['action'] ) ? $request['action'] : '';
		$action_label = $this->get_actions_progress_label( $actions );

		if ( $done < 0 ) {
			$done = 0;
		}

		return array(
			'action'       => $action,
			'actions'      => $actions,
			'action_label' => $action_label,
			'done'         => $done,
			'in_progress'  => $in_progress,
			'has_request'  => ! empty( $actions ),
			'is_done'      => empty( $actions ) && ! $in_progress,
		);
	}

	/**
	 * Clears the queued sync request.
	 *
	 * @return void
	 */
	public function clear_request() {
		delete_option( $this->get_option_name() );
		wp_cache_delete( $this->get_option_name(), 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}

	/**
	 * Handles count updates without submitting the full settings group.
	 *
	 * @return void
	 */
	public function handle_count_update() {
		if ( ! is_admin() || ! current_user_can( $this->args['capability'] ) ) {
			return;
		}

		if ( empty( $this->get_field_name( 'count_update_flag_field' ) ) || empty( $_POST[ $this->get_field_name( 'count_update_flag_field' ) ] ) || empty( $_POST[ $this->get_field_name( 'count_update_nonce_field' ) ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ $this->get_field_name( 'count_update_nonce_field' ) ] ) );
		if ( ! wp_verify_nonce( $nonce, $this->get_field_name( 'count_update_nonce_action' ) ) ) {
			return;
		}

		$count = 0;
		if ( isset( $_POST[ $this->get_field_name( 'count_update_value_field' ) ] ) ) {
			$count = sanitize_text_field( wp_unslash( $_POST[ $this->get_field_name( 'count_update_value_field' ) ] ) );
		}

		$count = max( 0, intval( $count ) );
		if ( ! empty( $this->get_count_option_name() ) ) {
			update_option( $this->get_count_option_name(), $count );
			ic_delete_global( $this->get_count_option_name() );
		}

		wp_safe_redirect( $this->get_settings_url() );
		exit;
	}

	/**
	 * Handles the non-JS form request.
	 *
	 * @return void
	 */
	public function handle_request() {
		if ( ! is_admin() || ! current_user_can( $this->args['capability'] ) ) {
			return;
		}

		if ( empty( $_POST[ $this->get_field_name( 'request_flag_field' ) ] ) || empty( $_POST[ $this->get_field_name( 'nonce_field' ) ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ $this->get_field_name( 'nonce_field' ) ] ) );
		if ( ! wp_verify_nonce( $nonce, $this->get_field_name( 'nonce_action' ) ) ) {
			return;
		}

		$actions = array();
		if ( isset( $_POST[ $this->get_field_name( 'form_actions_field' ) ] ) ) {
			if ( is_array( $_POST[ $this->get_field_name( 'form_actions_field' ) ] ) ) {
				$actions = array_map( 'sanitize_key', wp_unslash( $_POST[ $this->get_field_name( 'form_actions_field' ) ] ) );
			} else {
				$actions = sanitize_key( wp_unslash( $_POST[ $this->get_field_name( 'form_actions_field' ) ] ) );
			}
		}
		if ( empty( $actions ) && ! empty( $this->get_field_name( 'form_action_field' ) ) && ! empty( $_POST[ $this->get_field_name( 'form_action_field' ) ] ) ) {
			$actions = sanitize_key( wp_unslash( $_POST[ $this->get_field_name( 'form_action_field' ) ] ) );
		}
		$include_empty = ! empty( $_POST[ $this->get_field_name( 'include_empty_field' ) ] );
		$result        = $this->start_sync( $actions, $include_empty );
		wp_safe_redirect( add_query_arg( $this->get_field_name( 'status_query_arg' ), $result['status'], $this->get_settings_url() ) );
		exit;
	}

	/**
	 * Handles the AJAX start request.
	 *
	 * @return void
	 */
	public function ajax_start() {
		if ( ! current_user_can( $this->args['capability'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You are not allowed to run this action.', 'post-type-x' ),
				),
				403
			);
		}

		check_ajax_referer( $this->get_field_name( 'nonce_action' ), 'nonce' );

		$actions = array();
		if ( isset( $_POST['sync_actions'] ) ) {
			if ( is_array( $_POST['sync_actions'] ) ) {
				$actions = array_map( 'sanitize_key', wp_unslash( $_POST['sync_actions'] ) );
			} else {
				$actions = sanitize_key( wp_unslash( $_POST['sync_actions'] ) );
			}
		}
		if ( empty( $actions ) && ! empty( $_POST['sync_action'] ) ) {
			$actions = sanitize_key( wp_unslash( $_POST['sync_action'] ) );
		}
		$include_empty = ! empty( $_POST['include_empty'] );
		$result        = $this->start_sync( $actions, $include_empty );
		if ( 'scheduled' !== $result['status'] ) {
			wp_send_json_error( $result );
		}

		wp_send_json_success(
			array_merge(
				$result,
				$this->get_job_status()
			)
		);
	}

	/**
	 * Handles the AJAX status request.
	 *
	 * @return void
	 */
	public function ajax_status() {
		if ( ! current_user_can( $this->args['capability'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You are not allowed to run this action.', 'post-type-x' ),
				),
				403
			);
		}

		check_ajax_referer( $this->get_field_name( 'nonce_action' ), 'nonce' );

		wp_send_json_success( $this->get_job_status() );
	}

	/**
	 * Allows this controller's mapped meta keys in the batch updater.
	 *
	 * @param array   $allowed_keys Current allowed meta keys.
	 * @param WP_Post $post Product object.
	 * @return array
	 */
	public function allow_product_meta_keys( $allowed_keys, $post ) {
		$request          = $this->get_request();
		$selected_actions = $this->get_request_actions( $request );
		$count_key        = $this->get_count_key();
		$count            = ! empty( $request[ $count_key ] ) ? intval( $request[ $count_key ] ) : 0;

		if ( empty( $selected_actions ) || empty( $post->ID ) || $count < 1 || empty( $this->args['meta_mappings'] ) ) {
			return $allowed_keys;
		}

		for ( $i = 1; $i <= $count; $i++ ) {
			foreach ( $selected_actions as $action ) {
				if ( empty( $this->args['meta_mappings'][ $action ] ) ) {
					continue;
				}

				$meta_key = $this->get_meta_key( $this->args['meta_mappings'][ $action ], $i );
				if ( ! empty( $meta_key ) ) {
					$allowed_keys[] = $meta_key;
				}
			}
		}

		return array_values( array_unique( $allowed_keys ) );
	}

	/**
	 * Applies queued defaults to one product during the batch updater.
	 *
	 * @param array   $product_meta Product meta being normalized.
	 * @param WP_Post $post Product object.
	 *
	 * @return array
	 */
	public function apply_to_product_meta( $product_meta, $post ) {
		$request          = $this->get_request();
		$selected_actions = $this->get_request_actions( $request );
		$count_key        = $this->get_count_key();
		$count            = ! empty( $request[ $count_key ] ) ? intval( $request[ $count_key ] ) : 0;

		if ( empty( $selected_actions ) || empty( $post->ID ) || $count < 1 || empty( $this->args['meta_mappings'] ) ) {
			return $product_meta;
		}

		$include_empty = ! empty( $request['include_empty'] );
		for ( $i = 1; $i <= $count; $i++ ) {
			foreach ( $selected_actions as $action ) {
				if ( empty( $this->args['meta_mappings'][ $action ] ) || empty( $this->args['meta_mappings'][ $action ]['request_key'] ) ) {
					continue;
				}

				$mapping     = $this->args['meta_mappings'][ $action ];
				$request_key = $mapping['request_key'];
				$meta_key    = $this->get_meta_key( $mapping, $i );
				if ( empty( $meta_key ) ) {
					continue;
				}

				$value = isset( $request[ $request_key ][ $i ] ) ? $request[ $request_key ][ $i ] : '';
				if ( $include_empty || ic_epc_sync_default_has_value( $value ) ) {
					$product_meta[ $meta_key ] = $value;
				}
			}
		}

		return $product_meta;
	}

	/**
	 * Returns the product meta key for a mapped sync action and index.
	 *
	 * @param array $mapping Action meta mapping.
	 * @param int   $index One-based option index.
	 *
	 * @return string
	 */
	private function get_meta_key( $mapping, $index ) {
		if ( ! empty( $mapping['meta_key_callback'] ) && is_callable( $mapping['meta_key_callback'] ) ) {
			return (string) call_user_func( $mapping['meta_key_callback'], $index );
		}

		if ( isset( $mapping['meta_key_prefix'] ) ) {
			return $mapping['meta_key_prefix'] . $index;
		}

		return '';
	}

	/**
	 * Renders the shared overwrite controls.
	 *
	 * @param string $mode Current source mode.
	 * @param string $form_id Optional external form ID.
	 *
	 * @return void
	 */
	public function render_controls( $mode, $form_id = '' ) {
		$actions = $this->get_actions( $mode );
		$request = $this->get_request();
		if ( empty( $actions ) && empty( $request ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Status query arg only controls the admin notice text.
		$status          = isset( $_GET[ $this->get_field_name( 'status_query_arg' ) ] ) ? sanitize_key( wp_unslash( $_GET[ $this->get_field_name( 'status_query_arg' ) ] ) ) : '';
		$button_state    = ic_update_product_data_is_active() ? ' disabled="disabled"' : '';
		$done            = get_option( 'ic_update_product_data_done', 0 );
		$current_label   = '';
		$nonce           = wp_create_nonce( $this->get_field_name( 'nonce_action' ) );
		$request_actions = $this->get_request_actions( $request );
		if ( ! empty( $request_actions ) ) {
			$current_label = $this->get_actions_progress_label( $request_actions );
		}

		$form_attr = '';
		if ( ! empty( $form_id ) ) {
			$form_attr = ' form="' . esc_attr( $form_id ) . '"';
		}

		echo '<div class="al-box warning ic-epc-defaults-sync-box" id="' . esc_attr( $this->get_box_id() ) . '" data-ajax-url="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-nonce="' . esc_attr( $nonce ) . '" data-start-action="' . esc_attr( $this->get_field_name( 'ajax_start_action' ) ) . '" data-status-action="' . esc_attr( $this->get_field_name( 'ajax_status_action' ) ) . '" data-dirty-selector="' . esc_attr( $this->args['dirty_selector'] ) . '" data-start-error-text="' . esc_attr( $this->get_text( 'start_error_text' ) ) . '" data-status-error-text="' . esc_attr( $this->get_text( 'status_error_text' ) ) . '" data-done-text="' . esc_attr( $this->get_text( 'done_text' ) ) . '" data-processed-text="' . esc_attr( $this->get_text( 'processed_text' ) ) . '" data-dirty-text="' . esc_attr( $this->get_text( 'dirty_text' ) ) . '" data-no-selection-text="' . esc_attr( $this->get_text( 'no_selection_text' ) ) . '">';
		echo '<h3>' . esc_html( $this->get_text( 'title' ) ) . '</h3>';
		echo '<p>' . esc_html( $this->get_text( 'description' ) ) . '</p>';

		if ( 'scheduled' === $status ) {
			echo '<p class="ic-epc-defaults-sync-message"><strong>' . esc_html( $this->get_text( 'scheduled_message' ) ) . '</strong></p>';
		} elseif ( 'busy' === $status ) {
			echo '<p class="ic-epc-defaults-sync-message"><strong>' . esc_html( $this->get_text( 'busy_message' ) ) . '</strong></p>';
		} elseif ( 'invalid' === $status ) {
			echo '<p class="ic-epc-defaults-sync-message"><strong>' . esc_html( $this->get_text( 'invalid_message' ) ) . '</strong></p>';
		} elseif ( '' !== $current_label && ! empty( $done ) ) {
			if ( $done < 0 ) {
				$done = 0;
			}
			echo '<p class="ic-epc-defaults-sync-message">' . absint( $done ) . ' ' . esc_html( $this->get_text( 'processed_text' ) ) . ' ' . esc_html( $current_label ) . '.</p>';
		} elseif ( '' !== $current_label ) {
			echo '<p class="ic-epc-defaults-sync-message">' . esc_html( $this->get_text( 'queued_request_prefix' ) ) . ' ' . esc_html( $current_label ) . '.</p>';
		} else {
			echo '<p class="ic-epc-defaults-sync-message" style="display:none;"></p>';
		}

		if ( ! empty( $actions ) ) {
			echo '<p>';
			foreach ( $actions as $action => $action_data ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Form attribute HTML is escaped when the string is built.
				echo '<label style="display:block;"><input type="checkbox" class="ic-epc-defaults-sync-action-option" name="' . esc_attr( $this->get_field_name( 'form_actions_field' ) ) . '[]" value="' . esc_attr( $action ) . '"' . $form_attr . '/> ' . esc_html( $action_data['label'] ) . '</label>';
			}
			echo '</p>';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Form attribute HTML is escaped when the string is built.
			echo '<p><label><input type="checkbox" class="ic-epc-defaults-sync-include-empty" name="' . esc_attr( $this->get_field_name( 'include_empty_field' ) ) . '" value="1"' . $form_attr . '/> ' . esc_html( $this->get_text( 'include_empty_label' ) ) . '</label></p>';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Form and disabled attribute HTML is escaped when the strings are built.
			echo '<p><button type="submit" class="button button-primary ic-epc-defaults-sync-submit"' . $form_attr . $button_state . '>' . esc_html( $this->get_text( 'submit_label' ) ) . '</button> <span class="spinner ic-epc-defaults-sync-spinner" style="float:none;margin-top:0;"></span></p>';
		}

		echo '</div>';
	}

	/**
	 * Renders the shared standalone form.
	 *
	 * @param string $form_id Form ID.
	 *
	 * @return void
	 */
	public function render_form( $form_id ) {
		if ( empty( $form_id ) ) {
			return;
		}

		echo '<form id="' . esc_attr( $form_id ) . '" method="post" action="' . esc_url( $this->get_settings_url() ) . '">';
		wp_nonce_field( $this->get_field_name( 'nonce_action' ), $this->get_field_name( 'nonce_field' ) );
		echo '<input type="hidden" name="' . esc_attr( $this->get_field_name( 'request_flag_field' ) ) . '" value="1"/>';
		echo '</form>';
	}
}
