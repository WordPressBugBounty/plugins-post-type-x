<?php
/**
 * AI settings screen renderer.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Renders and saves per-post-type AI settings.
 */
class IC_AI_Settings {
	/**
	 * AI checkout proxy REST namespace.
	 */
	const REST_NAMESPACE = 'implecode/v1';

	/**
	 * AI checkout proxy REST route.
	 */
	const CHECKOUT_AJAX_ROUTE = '/ai/checkout-ajax';

	/**
	 * Manager instance.
	 *
	 * @var IC_AI_Manager
	 */
	private $manager;

	/**
	 * Registered settings screens keyed by post type.
	 *
	 * @var IC_Settings_Screen[]
	 */
	private $screens = array();

	/**
	 * Registered settings pages keyed by post type.
	 *
	 * @var IC_Settings_Page[]
	 */
	private $pages = array();

	/**
	 * Whether the remote display-state refresh already ran for this request.
	 *
	 * @var bool
	 */
	private $remote_refresh_attempted = false;

	/**
	 * Page notices consumed earlier in this request, keyed by post type.
	 *
	 * @var array
	 */
	private $consumed_notices = array();

	/**
	 * Page-level notices already rendered during this request, keyed by post type.
	 *
	 * @var array
	 */
	private $rendered_notices = array();

	/**
	 * Constructor.
	 *
	 * @param IC_AI_Manager $manager Manager.
	 */
	public function __construct( $manager ) {
		$this->manager = $manager;

		add_action( 'init', array( $this, 'register_screens' ), 40 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_ic_ai_save_settings', array( $this, 'handle_save' ) );
		add_action( 'admin_post_ic_ai_remove_key', array( $this, 'handle_remove_key' ) );
		add_action( 'wp_ajax_ic_ai_fetch_billing_fragment', array( $this, 'ajax_fetch_billing_fragment' ) );
		add_action( 'wp_ajax_ic_ai_start_billing_session', array( $this, 'ajax_start_billing_session' ) );
		add_action( 'wp_ajax_ic_ai_pending_status', array( $this, 'ajax_pending_status' ) );
		add_action( 'wp_ajax_ic_ai_resend_free_license_confirmation', array( $this, 'ajax_resend_free_license_confirmation' ) );
		add_action( 'wp_ajax_ic_ai_cancel_subscription', array( $this, 'ajax_cancel_subscription' ) );
		add_action( 'wp_ajax_ic_ai_retry_connection', array( $this, 'ajax_retry_connection' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_filter( 'rest_pre_serve_request', array( $this, 'serve_raw_checkout_ajax_response' ), 10, 4 );
		add_action( 'ic_settings_page_after_form', array( $this, 'render_page_after_form' ), 10, 3 );
	}

	/**
	 * Registers one settings screen per AI-enabled post type.
	 *
	 * @return void
	 */
	public function register_screens() {
		if ( ! class_exists( 'IC_Settings_Screen' ) || ! class_exists( 'IC_Settings_Page' ) ) {
			return;
		}
		$redirect_url = $this->checkout_failure_request_redirect_url();
		if ( empty( $redirect_url ) ) {
			$redirect_url = $this->waiting_ready_request_redirect_url();
		}
		if ( ! empty( $redirect_url ) ) {
			wp_safe_redirect( $redirect_url );
			exit;
		}

		// On the actual AI settings screen/tab (standalone screen OR a host-screen
		// tab), refresh the remote catalog/definitions cache BEFORE the page sections
		// are built so the cache-driven marketing-control dropdowns and disclosure
		// section render on the very first visit. This must run only for a genuine,
		// authorized settings-screen page load: `is_admin()` is also true for
		// `admin-ajax.php`, where attacker-controlled `page`/`tab` query params could
		// otherwise coax `current_integration()` into matching and trigger signed
		// remote refreshes plus option writes. AJAX/cron contexts are excluded and the
		// integration's settings capability is required before any remote work.
		if ( is_admin() && ! wp_doing_ajax() && ! wp_doing_cron() ) {
			$screen_integration = $this->current_integration();
			if ( $screen_integration && current_user_can( $screen_integration->settings_capability() ) ) {
				$this->refresh_remote_state_for_display();
			}
		}

		foreach ( $this->manager->integrations() as $integration ) {
			if ( ! $integration->show_settings_page() ) {
				continue;
			}
			$this->settings_page( $integration );
			if ( ! $integration->has_host_settings_screen() ) {
				$this->settings_screen( $integration );
			}
		}
	}

	/**
	 * Handles settings saves.
	 *
	 * @return void
	 */
	public function handle_save() {
		$target_key  = isset( $_POST['target_key'] ) ? sanitize_text_field( wp_unslash( $_POST['target_key'] ) ) : '';
		$target      = $target_key ? $this->manager->target( $target_key ) : false;
		$integration = $target instanceof IC_AI_Target ? $target->integration() : false;
		$post_type   = $integration instanceof IC_AI_Integration ? $integration->post_type() : '';
		if ( ! $integration || ! $target instanceof IC_AI_Target || $target->integration() !== $integration || ! current_user_can( $integration->settings_capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to edit AI settings for this post type.', 'post-type-x' ) );
		}
		check_admin_referer( 'ic_ai_settings_' . $target->key() );
		$this->clear_field_error( $post_type );

		$raw_settings               = isset( $_POST['ic_ai_settings'] ) && is_array( $_POST['ic_ai_settings'] ) ? map_deep( wp_unslash( $_POST['ic_ai_settings'] ), 'sanitize_text_field' ) : array();
		$analysis_opt_in            = isset( $_POST['ic_ai_analysis_opt_in'] ) ? sanitize_text_field( wp_unslash( $_POST['ic_ai_analysis_opt_in'] ) ) : '';
		$target_payload             = $this->sanitized_target_settings( $target, $raw_settings );
		$enabled                    = $target_payload['enabled'];
		$site_state                 = $this->manager->client()->site_settings();
		$current_post_type_settings = $this->manager->client()->target_settings( $target );
		$was_enabled                = ! empty( $current_post_type_settings['enabled'] );
		$settings                   = array_merge(
			$target_payload,
			array(
				'analysis_opt_in' => 'yes' === $analysis_opt_in ? 1 : 0,
				'last_saved_at'   => gmdate( 'c' ),
			)
		);
		if ( ! $enabled ) {
			$settings['consented_at'] = $current_post_type_settings['consented_at'] ?? '';
			$settings['consented_by'] = $current_post_type_settings['consented_by'] ?? 0;
		}
		if ( $enabled ) {
			$ack     = isset( $_POST['ic_ai_disclosure_ack'] ) ? sanitize_text_field( wp_unslash( $_POST['ic_ai_disclosure_ack'] ) ) : '';
			$consent = $this->validated_disclosure_consent( $ack, $current_post_type_settings );
			if ( is_wp_error( $consent ) ) {
				$this->redirect_with_notice( $post_type, 'error', __( 'You must confirm the data-sharing disclosure before enabling or updating AI.', 'post-type-x' ) );
			}
			$settings['consented_at'] = $consent['consented_at'];
			$settings['consented_by'] = $consent['consented_by'];
		}

		$site_settings = array(
			'license_key' => isset( $raw_settings['license_key'] ) ? sanitize_text_field( $raw_settings['license_key'] ) : '',
		);
		if ( $enabled && empty( $site_settings['license_key'] ) && ! $this->manager->client()->has_registered_client( $site_state ) ) {
			$this->redirect_with_notice( $post_type, 'error', __( 'Choose an AI plan or enter an AI License Key before enabling AI.', 'post-type-x' ) );
		}

		$this->manager->client()->update_site_settings( $site_settings );
		$this->manager->client()->update_target_settings( $target, $settings );
		/**
		 * Fires after the primary AI settings payload is stored for a post type and before remote registration.
		 *
		 * Extensions can persist additional post-type AI settings here while still affecting the outgoing
		 * registration payload.
		 *
		 * @param IC_AI_Target      $target       Saved target.
		 * @param IC_AI_Integration $integration  Saved integration.
		 * @param array             $settings     Normalized saved settings.
		 * @param array             $raw_settings Raw submitted settings.
		 * @param IC_AI_Settings    $screen       Settings screen instance.
		 */
		do_action( 'ic_ai_settings_saved', $target, $integration, $settings, $raw_settings, $this );

		if ( $enabled ) {
			$registered = $this->manager->client()->register_site( $integration );
			if ( is_wp_error( $registered ) ) {
				$this->redirect_with_notice( $post_type, 'error', $registered->get_error_message() );
			}
			$this->manager->client()->refresh_plan_state();
			if ( 'post' === $target->kind() && ! $was_enabled && $enabled ) {
				foreach ( $integration->taxonomy_targets() as $related_target ) {
					if ( ! $this->manager->client()->has_target_settings_row( $related_target ) ) {
						$related                 = $this->manager->client()->target_settings( $related_target );
						$related['enabled']      = 1;
						$related['consented_at'] = $settings['consented_at'];
						$related['consented_by'] = $settings['consented_by'];
						$this->manager->client()->update_target_settings( $related_target, $related );
					}
				}
			}
		}

		$this->redirect_with_notice( $post_type, 'success', __( 'AI settings saved.', 'post-type-x' ) );
	}

	/**
	 * Returns one sanitized custom-meta selection validated against registered meta keys.
	 *
	 * Meta keys can be mixed case, so values are sanitized as plain text
	 * instead of key-normalized, then intersected with the detected keys.
	 *
	 * @param array             $raw_settings Raw submitted settings.
	 * @param string            $settings_key Submitted settings key.
	 * @param IC_AI_Target      $target       Target.
	 * @param IC_AI_Integration $integration  Integration.
	 *
	 * @return array
	 */
	private function sanitized_custom_meta_selection( $raw_settings, $settings_key, $target, $integration ) {
		$selected = ! empty( $raw_settings[ $settings_key ] ) && is_array( $raw_settings[ $settings_key ] ) ? array_map( 'sanitize_text_field', $raw_settings[ $settings_key ] ) : array();
		if ( empty( $selected ) ) {
			return array();
		}

		$which = 'custom_context_meta' === $settings_key ? 'context' : 'enhance';

		return array_values( array_intersect( $this->manager->client()->registered_meta_keys( $target, $integration, $which ), $selected ) );
	}

	/**
	 * Handles site key removal.
	 *
	 * @return void
	 */
	public function handle_remove_key() {
		$target_key  = isset( $_POST['target_key'] ) ? sanitize_text_field( wp_unslash( $_POST['target_key'] ) ) : '';
		$target      = $target_key ? $this->manager->target( $target_key ) : false;
		$integration = $target instanceof IC_AI_Target ? $target->integration() : false;
		$post_type   = $integration instanceof IC_AI_Integration ? $integration->post_type() : '';
		if ( ! $integration || ! current_user_can( $integration->settings_capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to remove the AI key for this post type.', 'post-type-x' ) );
		}
		check_admin_referer( 'ic_ai_remove_key_' . $target->key() );
		$revoke = $this->manager->client()->revoke_key( 'manual_remove' );
		if ( is_wp_error( $revoke ) ) {
			$this->redirect_with_notice( $post_type, 'error', $revoke->get_error_message() );
		}
		$this->manager->client()->clear_pending_purchase();
		$this->reset_post_type_unregister_state( $post_type, $integration );

		$this->redirect_with_notice( $post_type, 'success', __( 'The site AI key was removed and revoked remotely.', 'post-type-x' ) );
	}

	/**
	 * Clears the visible activation/disclosure state for one integration after unregister.
	 *
	 * @param string            $post_type    Post type slug.
	 * @param IC_AI_Integration $integration  Integration instance.
	 *
	 * @return void
	 */
	private function reset_post_type_unregister_state( $post_type, $integration ) {
		$target                          = $integration->target( 'post' );
		$target_settings                 = $this->manager->client()->target_settings( $target );
		$target_settings['enabled']      = 0;
		$target_settings['consented_at'] = '';
		$target_settings['consented_by'] = 0;
		$this->manager->client()->update_target_settings( $target, $target_settings );
	}

	/**
	 * Enqueues billing-embed settings assets on AI screens.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		$integration = $this->current_integration();
		if ( ! $integration ) {
			return;
		}
		$target = $this->current_target_for_integration( $integration );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is read-only admin routing; every related mutation verifies its action nonce and required edit capability.
		if ( ! $target && empty( $_REQUEST['target_key'] ) ) {
			$target = $integration->target( 'post' );
		}
		if ( ! $target ) {
			return;
		}
		$site_settings         = $this->manager->client()->site_settings();
		$is_registered         = $this->manager->client()->has_registered_client( $site_settings );
		$has_saved_license_key = ! empty( $site_settings['license_key'] );
		$auto_plan             = $this->requested_auto_plan();
		$auto_billing_mode     = $this->requested_auto_billing_mode();
		$auto_billing_public   = $this->requested_auto_billing_public();
		if ( empty( $auto_billing_mode ) && ! $is_registered && ! $has_saved_license_key && ! $this->is_waiting_request() && empty( $auto_plan ) ) {
			$auto_billing_mode   = 'purchase';
			$auto_billing_public = true;
		} elseif ( 'purchase' === $auto_billing_mode ) {
			$auto_billing_public = true;
		}

		if ( function_exists( 'ic_chosen_init' ) ) {
			ic_chosen_init();
		}

		wp_enqueue_style( 'ic-ai-admin' );
		wp_enqueue_script( 'ic-ai-settings' );

		$confirm_unregister = $this->unregister_confirm_message( $site_settings );

		wp_localize_script(
			'ic-ai-settings',
			'icAISettings',
			array(
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'nonce'             => wp_create_nonce( 'ic-ai-settings' ),
				'postType'          => $integration->post_type(),
				'targetKey'         => $target->key(),
				'settingsUrl'       => $this->settings_url( $integration, $target ),
				'checkoutProxyUrl'  => $this->checkout_proxy_url( $integration ),
				'waiting'           => $this->should_poll_waiting_status(),
				'autoPlan'          => $auto_plan,
				'autoBillingMode'   => $auto_billing_mode,
				'autoBillingPublic' => $auto_billing_public,
				'autoSource'        => $this->requested_source(),
				'isRegistered'      => $is_registered,
				'pollEveryMs'       => 3000,
				'timeoutMs'         => 90000,
				'messages'          => array(
					'loading'                   => __( 'Loading billing options...', 'post-type-x' ),
					'back'                      => __( 'Back to AI Settings', 'post-type-x' ),
					'error'                     => __( 'The AI billing screen could not be loaded.', 'post-type-x' ),
					'startingCheckout'          => __( 'Preparing secure checkout...', 'post-type-x' ),
					'waiting'                   => __( 'Waiting for the AI license notification from impleCode...', 'post-type-x' ),
					'fallback'                  => $this->fallback_activation_message(),
					/* translators: %s: license expiration date. */
					'confirmCancelSubscription' => __( 'Your subscription will be cancelled and will not renew. Your plan stays active until your license expires on %s.', 'post-type-x' ),
					'cancelSubscriptionError'   => __( 'The subscription could not be cancelled.', 'post-type-x' ),
					'confirmUnregisterSite'     => $confirm_unregister,
					'retry'                     => __( 'Retry', 'post-type-x' ),
					'retrying'                  => __( 'Retrying...', 'post-type-x' ),
					'retrySuccess'              => __( 'Connection restored.', 'post-type-x' ),
					'reconnect'                 => __( 'Reconnect', 'post-type-x' ),
					'resendInFlight'            => __( 'Sending another confirmation email...', 'post-type-x' ),
					'resendFailed'              => __( 'The confirmation email could not be resent. Please try again shortly.', 'post-type-x' ),
					/* translators: %d: seconds remaining before another confirmation email may be requested. */
					'resendCountdown'           => __( 'You can request another email in %d seconds.', 'post-type-x' ),
				),
			)
		);
	}

	/**
	 * Registers public REST routes used by the remote license notification.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/ai/license-notify',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_license_notify' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			self::CHECKOUT_AJAX_ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_checkout_ajax' ),
				'permission_callback' => array( $this, 'rest_checkout_ajax_permission' ),
			)
		);
	}

	/**
	 * Returns one same-origin checkout proxy URL for the embedded AI purchase flow.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 *
	 * @return string
	 */
	private function checkout_proxy_url( $integration ) {
		return add_query_arg(
			array(
				'ic_ai_nonce' => wp_create_nonce( 'ic-ai-settings' ),
				'_wpnonce'    => wp_create_nonce( 'wp_rest' ),
				'post_type'   => $integration->post_type(),
			),
			rest_url( self::REST_NAMESPACE . self::CHECKOUT_AJAX_ROUTE )
		);
	}

	/**
	 * Fetches one remote billing fragment via local admin AJAX.
	 *
	 * @return void
	 */
	public function ajax_fetch_billing_fragment() {
		check_ajax_referer( 'ic-ai-settings', 'nonce' );

		$post_type      = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
		$mode           = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'upgrade';
		$public_billing = ! empty( $_POST['public_billing'] );
		$integration    = $this->manager->integration( $post_type );
		if ( ! $integration || ! current_user_can( $integration->settings_capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to load the AI billing screen.', 'post-type-x' ) ), 403 );
		}

		$raw_selection = isset( $_POST['selection'] ) && is_array( $_POST['selection'] ) ? map_deep( wp_unslash( $_POST['selection'] ), 'sanitize_text_field' ) : array();
		$selection     = $this->sanitized_billing_selection( $raw_selection );

		$fragment = $this->billing_fragment_response( $integration, $mode, $selection, $public_billing );
		if ( is_wp_error( $fragment ) ) {
			wp_send_json_error( $this->ajax_error_payload( $fragment, $this->manager->client()->billing_fallback_url( $mode ) ), 400 );
		}

		wp_send_json_success( $fragment );
	}

	/**
	 * Starts one public AI billing session for the selected plan.
	 *
	 * @return void
	 */
	public function ajax_start_billing_session() {
		check_ajax_referer( 'ic-ai-settings', 'nonce' );

		$post_type   = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
		$source      = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : 'settings';
		$plan_slug   = isset( $_POST['plan_slug'] ) ? sanitize_key( wp_unslash( $_POST['plan_slug'] ) ) : '';
		$integration = $this->manager->integration( $post_type );
		if ( ! $integration || ! current_user_can( $integration->settings_capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to start the AI checkout.', 'post-type-x' ) ), 403 );
		}

		$session = $this->public_billing_session(
			$integration,
			'purchase',
			array(),
			$source,
			$plan_slug
		);
		if ( is_wp_error( $session ) ) {
			wp_send_json_error( $this->ajax_error_payload( $session ), 400 );
		}

		$html         = ! empty( $session['html'] ) ? $session['html'] : '';
		$fallback_url = ! empty( $session['fallback_url'] ) ? esc_url_raw( $session['fallback_url'] ) : '';
		// The selected checkout replaces the chooser in the browser, so the local-site
		// warning travels with it. Presentation only: the checkout itself is unchanged.
		if ( ! empty( $html ) && ! $this->manager->client()->has_registered_client() ) {
			$html = $this->local_site_warning_html() . $html;
		}
		if ( empty( $html ) ) {
			wp_send_json_error(
				array(
					'message'      => __( 'The AI checkout could not be prepared.', 'post-type-x' ),
					'fallback_url' => $fallback_url,
				),
				400
			);
		}

		wp_send_json_success(
			array(
				'html'            => $html,
				'mode'            => ! empty( $session['mode'] ) ? sanitize_key( $session['mode'] ) : 'upgrade',
				'fallback_url'    => $fallback_url,
				'public_billing'  => true,
				'checkout_nonces' => $this->sanitized_checkout_nonces( $session ),
			)
		);
	}

	/**
	 * Starts one public embedded billing session and stores pending state.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 * @param string            $mode        Billing mode.
	 * @param array             $selection   Optional billing selection.
	 * @param string            $source      Optional source label.
	 * @param string            $plan_slug   Optional plan slug.
	 * @param IC_AI_Target|null $target      Optional target.
	 *
	 * @return array|WP_Error
	 */
	private function public_billing_session( $integration, $mode, $selection = array(), $source = 'settings', $plan_slug = '', $target = null ) {
		$target = $target instanceof IC_AI_Target ? $target : $this->current_target_for_integration( $integration );
		if ( ! $target ) {
			return new WP_Error( 'ic_ai_invalid_target', __( 'The AI target is not registered.', 'post-type-x' ) );
		}
		$mode      = sanitize_key( (string) $mode );
		$selection = $this->sanitized_billing_selection( $selection );
		$source    = sanitize_key( (string) $source );
		$plan_slug = sanitize_key( (string) $plan_slug );
		if ( empty( $plan_slug ) && ! empty( $selection['ic_ai_item_slug'] ) ) {
			$plan_slug = $selection['ic_ai_item_slug'];
		}
		$purchase_type = ! empty( $selection['ic_ai_purchase_type'] ) ? sanitize_key( $selection['ic_ai_purchase_type'] ) : 'plan';
		if ( ! in_array( $purchase_type, array( 'plan', 'enhancements' ), true ) ) {
			$purchase_type = 'plan';
		}
		$quantity = 'enhancements' === $purchase_type ? max( 1, absint( $selection['ic_ai_quantity'] ) ) : 1;

		$pending         = $this->manager->client()->pending_purchase();
		$browse          = empty( $plan_slug ) && empty( $selection );
		$preserve        = ! empty( $pending['callback_secret'] ) && in_array( $pending['status'], array( 'pending', 'pending_confirmation', 'confirmed_pending_activation' ), true );
		$callback_secret = ! empty( $pending['callback_secret'] ) && ( $browse || $preserve ) ? $pending['callback_secret'] : wp_generate_password( 48, true, true );
		if ( ! $browse && ! $preserve ) {
			$this->manager->client()->update_pending_purchase(
				array(
					'pending_status'          => 'pending',
					'pending_callback_secret' => $callback_secret,
					'pending_post_type'       => $integration->post_type(),
					'pending_target_key'      => $target->key(),
					'pending_source'          => $source,
					'pending_started_at'      => gmdate( 'c' ),
					'pending_message'         => '',
					'pending_user_id'         => get_current_user_id(),
					'pending_plan_slug'       => $plan_slug,
					'pending_purchase_type'   => $purchase_type,
					'pending_item_slug'       => $plan_slug,
					'pending_quantity'        => $quantity,
					'pending_recovery_type'   => '',
				)
			);
		}

		$session = $this->manager->client()->create_billing_session(
			$integration,
			array(
				'mode'            => $mode,
				'context_mode'    => $browse ? 'browse' : 'checkout',
				'return_url'      => add_query_arg( 'ic_ai_waiting', '1', $this->settings_url( $integration, $target ) ),
				'callback_url'    => rest_url( 'implecode/v1/ai/license-notify' ),
				'callback_secret' => $callback_secret,
				'post_type'       => $integration->post_type(),
				'source'          => $source,
				'plan_slug'       => $plan_slug,
				'selection'       => $selection,
			)
		);
		if ( is_wp_error( $session ) && ! $browse && ! $preserve ) {
			$this->manager->client()->update_pending_purchase(
				array(
					'pending_status'        => 'error',
					'pending_message'       => $session->get_error_message(),
					'pending_recovery_type' => '',
				)
			);
		}

		return $session;
	}

	/**
	 * Returns one embeddable billing response payload for the settings UI.
	 *
	 * @param IC_AI_Integration $integration    Integration.
	 * @param string            $mode           Billing mode.
	 * @param array             $selection      Optional selection.
	 * @param bool              $public_billing Whether the public path was requested explicitly.
	 *
	 * @return array|WP_Error
	 */
	private function billing_fragment_response( $integration, $mode, $selection = array(), $public_billing = false ) {
		$mode         = sanitize_key( (string) $mode );
		$selection    = $this->sanitized_billing_selection( $selection );
		$fallback_url = $this->manager->client()->billing_fallback_url( $mode );
		if ( $public_billing ) {
			return $this->public_billing_fragment_response( $integration, $mode, $selection, $fallback_url, $integration->target( 'post' ) );
		}

		$fragment = $this->manager->client()->fetch_billing_fragment( $mode, $selection );
		if ( is_wp_error( $fragment ) && $this->should_retry_public_billing_fragment( $mode, $fragment ) ) {
			return $this->public_billing_fragment_response( $integration, $mode, $selection, $fallback_url, $integration->target( 'post' ) );
		}
		if ( is_wp_error( $fragment ) ) {
			return $fragment;
		}

		return array(
			'html'            => ! empty( $fragment['html'] ) ? $fragment['html'] : '',
			'mode'            => ! empty( $fragment['mode'] ) ? sanitize_key( $fragment['mode'] ) : $mode,
			'fallback_url'    => ! empty( $fragment['fallback_url'] ) ? esc_url_raw( $fragment['fallback_url'] ) : $fallback_url,
			'public_billing'  => false,
			'checkout_nonces' => $this->sanitized_checkout_nonces( $fragment ),
		);
	}

	/**
	 * Returns the remote checkout nonce map carried by one billing fragment payload.
	 *
	 * Every embedded checkout AJAX call is proxied to the AI service and verified
	 * there, so the nonce it carries has to be one the SERVICE minted. Forwarding a
	 * locally created nonce can never verify remotely, and forwarding none at all is
	 * what produced the empty-bodied 403 from the shared formbuilder nonce guard.
	 *
	 * @param array $payload Remote fragment or session payload.
	 *
	 * @return array<string,string>
	 */
	private function sanitized_checkout_nonces( $payload ) {
		if ( ! is_array( $payload ) || empty( $payload['checkout_nonces'] ) || ! is_array( $payload['checkout_nonces'] ) ) {
			return array();
		}

		$nonces = array();
		foreach ( $payload['checkout_nonces'] as $key => $value ) {
			if ( ! is_string( $key ) || ! is_scalar( $value ) ) {
				continue;
			}
			$clean_key = sanitize_key( $key );
			if ( '' === $clean_key ) {
				continue;
			}
			$nonces[ $clean_key ] = sanitize_text_field( (string) $value );
		}

		return $nonces;
	}

	/**
	 * Returns one public embedded billing response payload.
	 *
	 * @param IC_AI_Integration $integration  Integration.
	 * @param string            $mode         Billing mode.
	 * @param array             $selection    Optional selection.
	 * @param string            $fallback_url Default fallback URL.
	 * @param IC_AI_Target|null $target       Optional target.
	 *
	 * @return array|WP_Error
	 */
	private function public_billing_fragment_response( $integration, $mode, $selection, $fallback_url, $target = null ) {
		$session = $this->public_billing_session( $integration, $mode, $selection, 'settings', '', $target );
		if ( is_wp_error( $session ) ) {
			return $session;
		}

		return array(
			'html'            => ! empty( $session['html'] ) ? $session['html'] : '',
			'mode'            => ! empty( $session['mode'] ) ? sanitize_key( $session['mode'] ) : $mode,
			'fallback_url'    => ! empty( $session['fallback_url'] ) ? esc_url_raw( $session['fallback_url'] ) : $fallback_url,
			'public_billing'  => true,
			'checkout_nonces' => $this->sanitized_checkout_nonces( $session ),
		);
	}

	/**
	 * Returns whether the signed fragment request should retry through the public billing path.
	 *
	 * @param string $mode  Billing mode.
	 * @param mixed  $error Candidate error.
	 *
	 * @return bool
	 */
	private function should_retry_public_billing_fragment( $mode, $error ) {
		$mode = sanitize_key( (string) $mode );
		if ( ! in_array( $mode, array( 'upgrade', 'enhancements' ), true ) ) {
			return false;
		}

		return $this->manager->client()->is_billing_signature_invalid_error( $error );
	}

	/**
	 * Returns one sanitized AI billing selection payload.
	 *
	 * @param mixed $selection Raw selection data.
	 *
	 * @return array
	 */
	private function sanitized_billing_selection( $selection ) {
		$selection = is_array( $selection ) ? $selection : array();

		return array(
			'ic_ai_purchase_type' => isset( $selection['ic_ai_purchase_type'] ) ? sanitize_key( $selection['ic_ai_purchase_type'] ) : '',
			'ic_ai_client_uuid'   => isset( $selection['ic_ai_client_uuid'] ) ? sanitize_text_field( $selection['ic_ai_client_uuid'] ) : '',
			'ic_ai_item_slug'     => isset( $selection['ic_ai_item_slug'] ) ? sanitize_key( $selection['ic_ai_item_slug'] ) : '',
			'ic_ai_quantity'      => isset( $selection['ic_ai_quantity'] ) ? absint( $selection['ic_ai_quantity'] ) : 1,
		);
	}

	/**
	 * Returns one normalized admin-AJAX error payload for the AI settings UI.
	 *
	 * @param WP_Error $error        Error object.
	 * @param string   $fallback_url Optional fallback URL.
	 *
	 * @return array
	 */
	private function ajax_error_payload( $error, $fallback_url = '' ) {
		$payload = array(
			'message' => $error instanceof WP_Error ? $error->get_error_message() : __( 'The AI request failed.', 'post-type-x' ),
		);

		if ( ! empty( $fallback_url ) ) {
			$payload['fallback_url'] = $fallback_url;
		}
		if ( ! $error instanceof WP_Error ) {
			return $payload;
		}

		$payload['code'] = $error->get_error_code();
		if ( $this->manager->client()->is_billing_unavailable_error( $error ) ) {
			$payload['code']              = 'ic_ai_billing_unavailable';
			$payload['reveal_manual_key'] = true;
		}

		return $payload;
	}

	/**
	 * Returns the current pending AI status for the waiting screen.
	 *
	 * @return void
	 */
	public function ajax_pending_status() {
		check_ajax_referer( 'ic-ai-settings', 'nonce' );

		$post_type   = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
		$integration = $this->manager->integration( $post_type );
		if ( ! $integration || ! current_user_can( $integration->settings_capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to read the AI activation status.', 'post-type-x' ) ), 403 );
		}

		wp_send_json_success( $this->pending_status_payload() );
	}

	/** Resend a pending Free-plan confirmation email. */
	public function ajax_resend_free_license_confirmation() {
		check_ajax_referer( 'ic-ai-settings', 'nonce' );
		$post_type   = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
		$integration = $this->manager->integration( $post_type );
		if ( ! $integration || ! current_user_can( $integration->settings_capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to resend this confirmation.', 'post-type-x' ) ), 403 );
		}
		$result = $this->manager->client()->resend_free_license_confirmation();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message'     => $result->get_error_message(),
					'retry_after' => $this->remote_retry_after( $result ),
				),
				400
			);
		}
		$state = $this->manager->client()->store_free_license_confirmation_state( $result );
		wp_send_json_success(
			array(
				'message'     => __( 'Another confirmation email was sent to the address used at checkout.', 'post-type-x' ),
				'retry_after' => ! empty( $state['retry_after'] ) ? absint( $state['retry_after'] ) : MINUTE_IN_SECONDS,
				'sent_at'     => $state['sent_at'],
				'expires_at'  => $state['expires_at'],
			)
		);
	}

	/**
	 * Extracts the service-provided retry delay from a remote client error.
	 *
	 * {@see IC_AI_Client::decode_response()} nests the decoded service body under the
	 * error data's `body` key, so a 429 `retry_after` must be read from there.
	 *
	 * @param WP_Error $error Remote client error.
	 *
	 * @return int Seconds to wait before retrying.
	 */
	private function remote_retry_after( $error ) {
		$data = is_wp_error( $error ) ? $error->get_error_data() : array();
		$body = ! empty( $data['body'] ) && is_array( $data['body'] ) ? $data['body'] : array();
		if ( ! empty( $body['retry_after'] ) ) {
			return absint( $body['retry_after'] );
		}
		if ( ! empty( $body['resend']['retry_after'] ) ) {
			return absint( $body['resend']['retry_after'] );
		}

		return ! empty( $data['retry_after'] ) ? absint( $data['retry_after'] ) : MINUTE_IN_SECONDS;
	}

	/**
	 * Cancels the active AI subscription on the remote server.
	 *
	 * @return void
	 */
	public function ajax_cancel_subscription() {
		check_ajax_referer( 'ic-ai-settings', 'nonce' );

		$post_type   = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
		$integration = $this->manager->integration( $post_type );
		if ( ! $integration || ! current_user_can( $integration->settings_capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to manage the AI subscription.', 'post-type-x' ) ), 403 );
		}

		$result = $this->manager->client()->cancel_subscription();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		$site_settings = $this->manager->client()->site_settings();

		wp_send_json_success(
			array(
				'subscription_html'       => $this->subscription_row_value( $site_settings ),
				'license_html'            => $this->license_expiration_value( $site_settings ),
				'confirm_unregister_site' => $this->unregister_confirm_message( $site_settings ),
			)
		);
	}

	/**
	 * Re-runs the remote AI connection check and clears a stale error on success.
	 *
	 * @return void
	 */
	public function ajax_retry_connection() {
		check_ajax_referer( 'ic-ai-settings', 'nonce' );

		$post_type   = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
		$integration = $this->manager->integration( $post_type );
		if ( ! $integration || ! current_user_can( $integration->settings_capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to retry the AI connection.', 'post-type-x' ) ), 403 );
		}

		$client = $this->manager->client();
		if ( $client->has_registered_client() ) {
			$result = $client->refresh_plan_state();
		} else {
			$result = $client->refresh_public_catalog_cache();
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		$client->site_settings();

		wp_send_json_success( array( 'message' => __( 'Connection restored.', 'post-type-x' ) ) );
	}

	/**
	 * Returns the current unregister confirmation message for the AI settings UI.
	 *
	 * @param array $site_settings Current AI site settings.
	 *
	 * @return string
	 */
	private function unregister_confirm_message( $site_settings ) {
		$confirm_unregister = __( 'This will disconnect this site from impleCode AI and immediately revoke its license key. AI features will stop working until you register again. Continue?', 'post-type-x' );
		$subscription       = ! empty( $site_settings['subscription'] ) && is_array( $site_settings['subscription'] ) ? $site_settings['subscription'] : array();
		if ( ! empty( $subscription['exists'] ) && empty( $subscription['cancelled'] ) ) {
			$renews_at = ! empty( $subscription['renews_at'] ) ? mysql2date( get_option( 'date_format' ), $subscription['renews_at'] ) : '';
			if ( ! empty( $renews_at ) ) {
				/* translators: %s: subscription renewal date. */
				$subscription_warning = sprintf( __( 'Note: your AI subscription is still active and will continue to renew on %s. Unregistering this site does NOT cancel that subscription — if you no longer want to be billed, cancel it separately from your impleCode account.', 'post-type-x' ), $renews_at );
			} else {
				$subscription_warning = __( 'Note: your AI subscription is still active and will keep renewing. Unregistering this site does NOT cancel that subscription — if you no longer want to be billed, cancel it separately from your impleCode account.', 'post-type-x' );
			}
			$confirm_unregister = $subscription_warning . "\n\n" . $confirm_unregister;
		}

		return $confirm_unregister;
	}

	/**
	 * Proxies one embedded AI checkout AJAX request to the remote checkout host.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function rest_checkout_ajax( $request ) {
		$integration = $this->rest_checkout_proxy_integration( $request );
		if ( is_wp_error( $integration ) ) {
			return $integration;
		}

		$payload = $request->get_body_params();
		if ( empty( $payload ) || ! is_array( $payload ) ) {
			$payload = $request->get_params();
		}
		unset( $payload['ic_ai_nonce'], $payload['_wpnonce'], $payload['post_type'], $payload['rest_route'] );

		$action = ! empty( $payload['action'] ) ? sanitize_key( $payload['action'] ) : '';
		if ( empty( $action ) ) {
			return new WP_Error( 'ic_ai_checkout_proxy_missing_action', __( 'The AI checkout request is missing an action.', 'post-type-x' ), array( 'status' => 400 ) );
		}
		if ( ! in_array( $action, $this->checkout_proxy_actions(), true ) ) {
			return new WP_Error( 'ic_ai_checkout_proxy_forbidden_action', __( 'This AI checkout action is not allowed.', 'post-type-x' ), array( 'status' => 403 ) );
		}

		$response = $this->manager->client()->forward_checkout_ajax( $payload );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$raw = new WP_REST_Response(
			array(
				'ic_ai_raw_response' => true,
				'body'               => wp_remote_retrieve_body( $response ),
				'content_type'       => $this->checkout_proxy_content_type( $response ),
			),
			wp_remote_retrieve_response_code( $response )
		);
		$raw->header( 'Content-Type', $this->checkout_proxy_content_type( $response ) );

		return $raw;
	}

	/**
	 * Verifies REST access to the embedded checkout proxy matches AI settings access.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return true|WP_Error
	 */
	public function rest_checkout_ajax_permission( $request ) {
		$integration = $this->rest_checkout_proxy_integration( $request );

		return is_wp_error( $integration ) ? $integration : true;
	}

	/**
	 * Serves raw embedded checkout proxy responses without REST JSON wrapping.
	 *
	 * @param bool             $served  Whether the request has already been served.
	 * @param WP_HTTP_Response $result  Result to send to the client.
	 * @param WP_REST_Request  $request Request object.
	 * @param WP_REST_Server   $server  Server instance.
	 *
	 * @return bool
	 */
	public function serve_raw_checkout_ajax_response( $served, $result, $request, $server ) {
		if ( $served || ! $request instanceof WP_REST_Request || $this->checkout_proxy_rest_route() !== $request->get_route() || ! $result instanceof WP_REST_Response ) {
			return $served;
		}

		$data = $result->get_data();
		if ( empty( $data['ic_ai_raw_response'] ) ) {
			return $served;
		}

		$content_type = ! empty( $data['content_type'] ) ? sanitize_text_field( $data['content_type'] ) : 'text/html; charset=' . get_option( 'blog_charset' );
		$server->send_header( 'Content-Type', $content_type );
		foreach ( $result->get_headers() as $header_key => $header_value ) {
			if ( 'content-type' === strtolower( $header_key ) ) {
				continue;
			}
			$server->send_header( $header_key, $header_value );
		}
		status_header( $result->get_status() );
		// Invariant: the remote body is trusted implecode-server checkout HTML, gated by nonce + capability + action allowlist in the checkout proxy — do not add un-escaped user input here.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped at this point.
		echo isset( $data['body'] ) ? $data['body'] : '';

		return true;
	}

	/**
	 * Renders the screen tab content.
	 *
	 * @return void
	 */
	public function render_screen_content() {
		$integration = $this->current_integration();
		if ( ! $integration ) {
			return;
		}
		$redirect_url = $this->waiting_ready_redirect_url( $integration );
		if ( ! empty( $redirect_url ) ) {
			wp_safe_redirect( $redirect_url );
			exit;
		}

		// Refresh the remote catalog/definitions cache before the form renders so
		// the cache-backed sections (disclosure checkbox + marketing controls) are
		// present on the very first visit, then rebuild the memoized page (which was
		// assembled from the stale cache during `register_screens()` on `init`).
		$this->refresh_remote_state_for_display();
		unset( $this->pages[ $integration->post_type() ] );
		$target = $this->current_target_for_integration( $integration );
		if ( ! $target ) {
			wp_die( esc_html__( 'The AI target is not registered.', 'post-type-x' ) );
		}
		/* translators: %s: AI target label. */
		echo '<h1>' . esc_html( sprintf( __( 'AI settings for %s', 'post-type-x' ), $target->label() ) ) . '</h1><nav class="nav-tab-wrapper ic-ai-target-nav">';
		$targets = array_merge( array( $integration->target( 'post' ) ), array_values( $integration->taxonomy_targets() ) );
		foreach ( $targets as $nav_target ) {
			if ( ! $nav_target instanceof IC_AI_Target ) {
				continue;
			}
			$class = $nav_target->key() === $target->key() ? ' nav-tab-active' : '';
			echo '<a class="nav-tab' . esc_attr( $class ) . '" href="' . esc_url( $this->settings_url( $integration, $nav_target ) ) . '">' . esc_html( $nav_target->label() ) . '</a>';
		}
		echo '</nav>';
		$this->render_notice( $integration->post_type() );
		$this->settings_page( $integration )->render( true );
	}

	/**
	 * Returns the clean settings URL when the waiting return screen is already ready.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 *
	 * @return string
	 */
	private function waiting_ready_redirect_url( $integration ) {
		if ( ! $this->is_waiting_request() ) {
			return '';
		}
		$status = $this->pending_status_payload();
		if ( empty( $status['status'] ) || 'ready' !== $status['status'] ) {
			return '';
		}

		return $this->settings_url( $integration );
	}

	/**
	 * Consume an allowlisted checkout failure on the authorized settings target.
	 *
	 * @return string
	 */
	private function checkout_failure_request_redirect_url() {
		if ( ! is_admin() || wp_doing_ajax() || wp_doing_cron() || ! isset( $_GET['ic_ai_checkout_error'] ) ) {
			return '';
		}
		$integration = $this->current_integration();
		if ( ! $integration || ! current_user_can( $integration->settings_capability() ) ) {
			return '';
		}
		$target = $this->current_target_for_integration( $integration );
		if ( ! $target instanceof IC_AI_Target ) {
			return '';
		}
		$code     = isset( $_GET['ic_ai_checkout_error'] ) && is_string( $_GET['ic_ai_checkout_error'] ) ? sanitize_key( wp_unslash( $_GET['ic_ai_checkout_error'] ) ) : '';
		$messages = array(
			'free_signup_protected' => __( 'The Free AI request was not accepted. Wait a moment, then choose the Free plan again.', 'post-type-x' ),
			'checkout_validation'   => __( 'The AI checkout could not be completed. Choose the plan again and review the checkout details.', 'post-type-x' ),
		);
		if ( isset( $messages[ $code ] ) ) {
			$this->store_notice( $integration->post_type(), 'warning', $messages[ $code ], true );
			$pending = $this->manager->client()->pending_purchase();
			if ( 'pending' === $pending['status'] && $integration->post_type() === $pending['post_type'] && ( empty( $pending['target_key'] ) || $target->key() === $pending['target_key'] ) ) {
				$this->manager->client()->update_pending_purchase(
					array(
						'pending_status'  => 'error',
						'pending_message' => $messages[ $code ],
					)
				);
			}
		}
		return $this->settings_url( $integration, $target );
	}

	/**
	 * Returns the clean settings URL for ready waiting-screen requests before page objects are built.
	 *
	 * @return string
	 */
	private function waiting_ready_request_redirect_url() {
		$integration = $this->current_integration();
		if ( ! $integration ) {
			return '';
		}

		return $this->waiting_ready_redirect_url( $integration );
	}

	/**
	 * Renders content after the settings form.
	 *
	 * @param string           $option_group Option group.
	 * @param array            $settings     Page settings.
	 * @param IC_Settings_Page $page         Settings page.
	 *
	 * @return void
	 */
	public function render_page_after_form( $option_group, $settings, $page ) {
		if ( ! is_object( $page ) || ! method_exists( $page, 'screen' ) ) {
			return;
		}

		$integration = $this->integration_from_page( $page );
		if ( ! $integration ) {
			return;
		}
		$this->render_notice( $integration->post_type() );

		$site_settings = $this->refresh_remote_state_for_display();
		if ( ! $this->manager->client()->has_registered_client( $site_settings ) && ! empty( $site_settings['pending_callback_secret'] ) && in_array( $site_settings['pending_status'], array( 'pending', 'pending_confirmation', 'confirmed_pending_activation' ), true ) ) {
			$this->public_billing_session( $integration, 'purchase' );
			$site_settings = $this->manager->client()->site_settings();
		}

		$definitions_missing = ! $this->manager->client()->has_registered_client( $site_settings ) && $this->cached_display_definitions_missing( $site_settings );

		if ( ! empty( $site_settings['last_error'] ) || $definitions_missing ) {
			$this->render_remote_error_notice( $site_settings, $integration );
		}
		if ( empty( $site_settings['catalog_available'] ) && ! empty( $site_settings['catalog_message'] ) ) {
			?>
			<div class="notice notice-warning"><p><?php echo esc_html( $site_settings['catalog_message'] ); ?></p></div>
			<?php
		}
		?>
		<div id="ic-ai-settings-secondary">
			<?php if ( $this->should_render_waiting_panel( $site_settings ) ) : ?>
				<?php $this->render_waiting_panel(); ?>
			<?php elseif ( $this->manager->client()->has_registered_client( $site_settings ) ) : ?>
				<?php $this->render_registered_panel( $integration, $site_settings ); ?>
			<?php else : ?>
				<?php if ( ! empty( $site_settings['pending_status'] ) && in_array( $site_settings['pending_status'], array( 'pending_confirmation', 'confirmed_pending_activation' ), true ) ) : ?>
					<?php $this->render_waiting_panel(); ?>
					<?php if ( 'pending_confirmation' === $site_settings['pending_status'] ) : ?>
						<p><?php esc_html_e( 'Resend uses the original checkout address. To correct that address, choose Free again and enter the correct email at checkout.', 'post-type-x' ); ?></p>
					<?php endif; ?>
				<?php endif; ?>
				<?php $this->render_unregistered_panel( $integration, $site_settings ); ?>
			<?php endif; ?>
		</div>
		<?php $this->render_disclosure_requirement_script(); ?>
		<?php
	}

	/**
	 * Renders the remote AI error notice with remediation guidance and a Retry control.
	 *
	 * @param array             $site_settings Current AI site settings.
	 * @param IC_AI_Integration $integration   Active integration.
	 *
	 * @return void
	 */
	private function render_remote_error_notice( $site_settings, $integration ) {
		$message = ! empty( $site_settings['last_error'] ) ? $site_settings['last_error'] : __( 'AI settings couldn\'t be loaded from impleCode.', 'post-type-x' );
		$code    = ! empty( $site_settings['last_error_code'] ) ? $site_settings['last_error_code'] : '';

		$guidance = '';
		switch ( $code ) {
			case 'ic_ai_unreachable':
				$guidance = __( 'This is usually a network or firewall issue on your server. Click Retry once connectivity is restored.', 'post-type-x' );
				break;
			case 'ic_ai_auth':
				$guidance = __( 'Your site\'s AI license credentials were rejected — reconnect to refresh them.', 'post-type-x' );
				break;
			case 'ic_ai_not_found':
				$guidance = __( 'The AI service endpoint could not be found. Confirm the service URL is online, then click Retry.', 'post-type-x' );
				break;
			case 'ic_ai_busy':
			case 'ic_ai_service_down':
				$guidance = __( 'The impleCode AI service is temporarily unavailable — please Retry in a few minutes.', 'post-type-x' );
				break;
			case 'ic_ai_timeout':
				$message  = __( 'The AI service took too long to respond. Please try again.', 'post-type-x' );
				$guidance = __( 'Please click Retry. If the problem persists, try again in a few minutes.', 'post-type-x' );
				break;
			default:
				$guidance = __( 'Please click Retry. If the problem persists, try again in a few minutes.', 'post-type-x' );
				break;
		}
		?>
		<div class="notice notice-error ic-ai-remote-error-notice">
			<p><?php echo esc_html( $message ); ?></p>
			<?php if ( ! empty( $guidance ) ) : ?>
				<p class="description"><?php echo esc_html( $guidance ); ?></p>
			<?php endif; ?>
			<p>
				<button type="button" class="button ic-ai-retry" data-post-type="<?php echo esc_attr( $integration->post_type() ); ?>"><?php echo esc_html__( 'Retry', 'post-type-x' ); ?></button>
				<?php if ( 'ic_ai_auth' === $code ) : ?>
					<a class="button button-secondary" href="<?php echo esc_url( $this->settings_url( $integration ) ); ?>"><?php echo esc_html__( 'Reconnect', 'post-type-x' ); ?></a>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Returns the settings screen instance for one integration.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 *
	 * @return IC_Settings_Screen
	 */
	private function settings_screen( $integration ) {
		$post_type = $integration->post_type();
		if ( isset( $this->screens[ $post_type ] ) ) {
			return $this->screens[ $post_type ];
		}

		$this->screens[ $post_type ] = new IC_Settings_Screen(
			array(
				'parent_slug'             => $integration->menu_parent(),
				/* translators: %s: integration label. */
				'page_title'              => sprintf( __( '%s AI', 'post-type-x' ), $integration->label() ),
				'menu_title'              => __( 'impleCode AI', 'post-type-x' ),
				'capability'              => $integration->settings_capability(),
				'menu_slug'               => $this->screen_slug( $post_type ),
				/* translators: %s: integration label. */
				'title'                   => sprintf( __( '%s AI', 'post-type-x' ), $integration->label() ),
				'tabs'                    => array(
					'ai-settings' => array(
						'label'    => __( 'AI Settings', 'post-type-x' ),
						'callback' => array( $this, 'render_screen_content' ),
						'default'  => true,
					),
				),
				'show_logo'               => false,
				'show_tabs'               => false,
				'show_unsaved_changes_js' => true,
			)
		);

		return $this->screens[ $post_type ];
	}

	/**
	 * Returns the settings page instance for one integration.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 *
	 * @return IC_Settings_Page
	 */
	private function settings_page( $integration ) {
		$post_type = $integration->post_type();
		if ( isset( $this->pages[ $post_type ] ) ) {
			return $this->pages[ $post_type ];
		}

		$page_settings = $this->page_settings( $integration );

		$this->pages[ $post_type ] = new IC_Settings_Page(
			array(
				'title'                   => __( 'AI Settings', 'post-type-x' ),
				'option_name'             => $this->settings_error_key( $post_type ),
				'settings'                => $page_settings,
				'sections'                => $this->page_sections( $integration ),
				'screen'                  => $this->page_screen_slug( $integration ),
				'screen_tab'              => $integration->has_host_settings_screen() ? $integration->settings_screen_tab() : '',
				'screen_tab_label'        => $integration->has_host_settings_screen() ? $integration->settings_screen_tab_label() : '',
				'screen_tab_menu_item_id' => $integration->has_host_settings_screen() ? $integration->settings_screen_tab_id() : '',
				'screen_tab_query_args'   => $integration->has_host_settings_screen() ? $integration->settings_screen_tab_query_args() : array(),
				'show_title'              => false,
				'show_settings_fields'    => false,
				'form_action'             => admin_url( 'admin-post.php' ),
				'form_attributes'         => array(
					'class' => $this->settings_form_class( $page_settings['site'], $page_settings['target'] ),
				),
				'hidden_fields'           => array(
					'action'     => 'ic_ai_save_settings',
					'post_type'  => $post_type,
					'target_key' => $this->current_target_for_integration( $integration )->key(),
				),
				'nonce_action'            => 'ic_ai_settings_' . $this->current_target_for_integration( $integration )->key(),
				'submit_label'            => __( 'Save AI Settings', 'post-type-x' ),
			)
		);

		return $this->pages[ $post_type ];
	}

	/**
	 * Returns the combined page settings payload.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 *
	 * @return array
	 */
	private function page_settings( $integration ) {
		$target          = $this->current_target_for_integration( $integration );
		$target_settings = $this->manager->client()->target_settings( $target );
		if ( $target instanceof IC_AI_Target ) {
			$defaults = apply_filters( 'ic_ai_target_default_fields', array(), $target, $integration );
			if ( empty( $target_settings['fields'] ) && is_array( $defaults ) ) {
				$target_settings['fields'] = array_values( array_map( 'sanitize_key', $defaults ) );
			}
		}
		return array(
			'site'       => $this->manager->client()->site_settings(),
			'target'     => $target_settings,
			'target_key' => $target instanceof IC_AI_Target ? $target->key() : '',
		);
	}

	/**
	 * Returns the generated settings sections.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 *
	 * @return array
	 */
	private function page_sections( $integration ) {
		$page_settings      = $this->page_settings( $integration );
		$target             = $this->current_target_for_integration( $integration );
		$site_settings      = $page_settings['site'];
		$post_type_settings = $page_settings['target'];
		$page_notice        = $this->current_notice( $integration->post_type(), true );
		if ( ! empty( $page_notice['page_level'] ) ) {
			$page_notice = array();
		}
		$persistent_notice = $this->persistent_disabled_notice( $integration, $site_settings, $post_type_settings );
		$recovery_notice   = $this->persistent_manual_recovery_notice( $site_settings );
		$section_notices   = $this->merged_section_notices( $page_notice, $persistent_notice, $recovery_notice );
		$field_error       = $this->current_field_error( $integration->post_type(), $site_settings );
		$license_key_row   = array(
			'type'  => 'text',
			'label' => __( 'License Key', 'post-type-x' ),
			'name'  => 'ic_ai_settings[license_key]',
			'value' => $site_settings['license_key'],
			'tip'   => __( 'Used to register or re-register this site with impleCode AI.', 'post-type-x' ),
		);
		if ( $this->is_license_key_field_error( $field_error ) ) {
			$license_key_row['class'] = 'ic-ai-license-key-input-error';
		}
		$section_class = 'ic-settings-section ic-ai-manual-settings-section';

		// The License Key is a single site-wide credential, so it is spliced into this
		// page only. Emitting it from the shared per-target row builder would render one
		// input per target, all writing the same option on a last-one-wins basis.
		$primary_rows = $this->target_rows(
			$target,
			'ic_ai_settings',
			array(
				'settings'          => $post_type_settings,
				'rows_after_enable' => array(
					$license_key_row,
					$this->license_key_error_row( $field_error ),
				),
			)
		);

		$sections = array(
			array(
				'title'           => __( 'impleCode AI Configuration', 'post-type-x' ),
				'container_class' => $section_class,
				'table_class'     => 'IC_Settings_Standard_Table',
				'settings'        => array(
					'notices' => $section_notices,
					'rows'    => $primary_rows,
				),
			),
		);
		/**
		 * Filters the rendered AI settings sections for one integration page.
		 *
		 * @param array             $sections      Generated settings sections.
		 * @param IC_AI_Integration $integration   Current integration.
		 * @param array             $page_settings Combined site and post-type settings.
		 * @param IC_AI_Settings    $screen        Settings screen instance.
		 */
		$sections           = apply_filters( 'ic_ai_settings_page_sections', $sections, $integration, $page_settings, $this );
		$disclosure_section = $this->disclosure_section();
		if ( ! empty( $disclosure_section ) ) {
			$sections[] = $disclosure_section;
		}

		return $sections;
	}

	/**
	 * Returns the canonical settings-table rows for one AI target.
	 *
	 * This is the single definition of the per-target AI row set. `page_sections()`
	 * consumes it with the `ic_ai_settings` name prefix; extensions that render an
	 * extra section for their own targets consume it with a per-target prefix such as
	 * `ic_ai_catalog_target_settings[<target key>]`, so every catalog shows the same
	 * controls instead of a hand-built approximation.
	 *
	 * The target is always the one passed in. Nothing here may resolve a target from
	 * the request: `current_target_for_integration()` reads `$_REQUEST['target_key']`,
	 * so a residual call would make every extra section silently render the field map
	 * of whichever target the current page happens to name.
	 *
	 * Site-level controls (License Key and its inline error) are not produced here.
	 * Callers splice them in through `rows_after_enable`.
	 *
	 * @param IC_AI_Target $target      Target to describe.
	 * @param string       $name_prefix Base form-name path for every emitted row.
	 * @param array        $args        {
	 *     Optional arguments.
	 *
	 *     @type array|null $settings                 Pre-resolved target settings. Resolved when null.
	 *     @type array      $rows_after_enable        Rows spliced directly after the Enable AI row.
	 *     @type bool       $marketing_controls       Whether to append the marketing dropdowns. Default true.
	 *     @type bool       $definitions_missing_note Whether to emit the definitions-missing note. Default true.
	 * }
	 *
	 * @return array Ordered list of settings-table row definitions.
	 */
	public function target_rows( $target, $name_prefix = 'ic_ai_settings', $args = array() ) {
		if ( ! $target instanceof IC_AI_Target ) {
			return array();
		}
		$args = wp_parse_args(
			(array) $args,
			array(
				'settings'                 => null,
				'rows_after_enable'        => array(),
				'marketing_controls'       => true,
				'definitions_missing_note' => true,
			)
		);

		$client        = $this->manager->client();
		$integration   = $target->integration();
		$name_prefix   = (string) $name_prefix;
		$settings      = is_array( $args['settings'] ) ? $args['settings'] : $client->target_settings( $target );
		$site_settings = $client->site_settings();
		$fields        = ! empty( $settings['fields'] ) && is_array( $settings['fields'] ) ? $settings['fields'] : array();

		// When a fresh/unregistered site cannot cache the service-owned disclosure and
		// marketing-control definitions, the consent checkbox is omitted; disable the
		// Enable AI control so the user cannot toggle it into a blocking consent dead-end.
		// This is site-level state, so every target gets the disabled variant.
		$definitions_missing = ! $client->has_registered_client( $site_settings ) && $this->cached_display_definitions_missing( $site_settings );

		$enable_ai_row = array(
			'type'  => 'checkbox',
			/* translators: %s: AI target label. */
			'label' => sprintf( __( 'Enable AI for %s', 'post-type-x' ), $target->label() ),
			'name'  => $name_prefix . '[enabled]',
			'value' => ! empty( $settings['enabled'] ) ? 1 : 0,
			'tip'   => __( 'Enable impleCode AI for this post type.', 'post-type-x' ),
		);

		$rows = array();
		if ( $definitions_missing ) {
			$rows[] = $this->disabled_enable_ai_row( $enable_ai_row );
			if ( ! empty( $args['definitions_missing_note'] ) ) {
				$rows[] = $this->definitions_missing_note_row();
			}
		} else {
			$rows[] = $enable_ai_row;
		}
		foreach ( (array) $args['rows_after_enable'] as $extra_row ) {
			$rows[] = $extra_row;
		}
		$rows[] = array(
			'type'    => 'checkboxes',
			'label'   => __( 'Enhance Fields', 'post-type-x' ),
			'name'    => $name_prefix . '[fields][]',
			'options' => $this->field_labels( $target, 'enhance' ),
			'value'   => $fields,
			'tip'     => __( 'Select fields the AI may rewrite or suggest values for. Fields enabled here are not available as Context Fields.', 'post-type-x' ),
		);
		if ( ! empty( $client->registered_meta_keys( $target, $integration, 'enhance' ) ) ) {
			$rows[] = array(
				'type'    => 'multiselect',
				'label'   => __( 'Custom Meta to Enhance', 'post-type-x' ),
				'name'    => $name_prefix . '[custom_enhance_meta][]',
				'options' => $this->registered_meta_options( $target, 'enhance', __( 'Choose custom meta to enhance...', 'post-type-x' ) ),
				'value'   => isset( $settings['custom_enhance_meta'] ) ? $settings['custom_enhance_meta'] : array(),
				'tip'     => __( 'Registered custom fields selected here are sent to the AI for enhancement and can be applied back to the product.', 'post-type-x' ),
			);
		}
		$rows[] = array(
			'type'    => 'checkboxes',
			'label'   => __( 'Context Fields', 'post-type-x' ),
			'name'    => $name_prefix . '[context_fields][]',
			'options' => $this->field_labels( $target, 'context', $fields ),
			'value'   => isset( $settings['context_fields'] ) ? $settings['context_fields'] : array(),
			'tip'     => __( 'Select reference-only fields to send with the AI request. Context fields are never modified.', 'post-type-x' ),
			'notice'  => array(
				'type'    => 'info',
				'message' => __( 'Context data is sent to the AI as reference only and is never modified.', 'post-type-x' ),
			),
		);
		if ( ! empty( $client->registered_meta_keys( $target, $integration, 'context' ) ) ) {
			$rows[] = array(
				'type'    => 'multiselect',
				'label'   => __( 'Custom Meta as Context', 'post-type-x' ),
				'name'    => $name_prefix . '[custom_context_meta][]',
				'options' => $this->registered_meta_options( $target, 'context', __( 'Choose custom meta as context...', 'post-type-x' ) ),
				'value'   => isset( $settings['custom_context_meta'] ) ? $settings['custom_context_meta'] : array(),
				'tip'     => __( 'Registered custom fields selected here are sent to the AI as reference data only and are never modified.', 'post-type-x' ),
			);
		}
		if ( ! empty( $args['marketing_controls'] ) ) {
			$rows = array_merge(
				$rows,
				$client->marketing_control_rows( $name_prefix . '[marketing_controls]', isset( $settings['marketing_controls'] ) ? $settings['marketing_controls'] : array() )
			);
		}

		return $rows;
	}

	/**
	 * Returns the normalized per-target settings payload for one submitted sub-array.
	 *
	 * Shared by the built-in save handler and by extensions saving their own targets, so
	 * every target is validated against its own field map and registered meta keys.
	 *
	 * The raw payload is text-sanitized first. `IC_AI_Client::normalize_marketing_controls()`
	 * passes string values straight through when the definitions cache is empty, so the
	 * sanitize pass here is what keeps unvalidated submitted strings out of the option.
	 *
	 * Consent, `analysis_opt_in` and `last_saved_at` are deliberately not returned: they
	 * are the caller's responsibility because the consent rules differ per caller.
	 *
	 * @param IC_AI_Target $target Target the payload belongs to.
	 * @param mixed        $raw    Unslashed submitted sub-array for this target.
	 *
	 * @return array
	 */
	public function sanitized_target_settings( $target, $raw ) {
		$raw = is_array( $raw ) ? map_deep( $raw, 'sanitize_text_field' ) : array();
		if ( ! $target instanceof IC_AI_Target ) {
			return array();
		}
		$integration       = $target->integration();
		$fields            = ! empty( $raw['fields'] ) && is_array( $raw['fields'] ) ? array_map( 'sanitize_key', $raw['fields'] ) : array();
		$context_fields    = ! empty( $raw['context_fields'] ) && is_array( $raw['context_fields'] ) ? array_map( 'sanitize_key', $raw['context_fields'] ) : array();
		$validated_fields  = array_values( array_intersect( array_keys( $this->field_labels( $target, 'enhance' ) ), $fields ) );
		$validated_context = array_values( array_intersect( array_keys( $this->field_labels( $target, 'context', $validated_fields ) ), $context_fields ) );
		$validated_context = array_values( array_diff( $validated_context, $validated_fields ) );
		$enhance_meta      = $this->sanitized_custom_meta_selection( $raw, 'custom_enhance_meta', $target, $integration );
		$context_meta      = array_values( array_diff( $this->sanitized_custom_meta_selection( $raw, 'custom_context_meta', $target, $integration ), $enhance_meta ) );

		return array(
			'enabled'             => ! empty( $raw['enabled'] ) ? 1 : 0,
			'fields'              => $validated_fields,
			'context_fields'      => $validated_context,
			'custom_enhance_meta' => $enhance_meta,
			'custom_context_meta' => $context_meta,
			'marketing_controls'  => $this->manager->client()->normalize_marketing_controls( isset( $raw['marketing_controls'] ) ? $raw['marketing_controls'] : array() ),
		);
	}

	/**
	 * Returns whether the field error targets the license-key field.
	 *
	 * @param array $field_error Field-error payload.
	 *
	 * @return bool
	 */
	private function is_license_key_field_error( $field_error ) {
		return ! empty( $field_error['field'] ) && 'license_key' === sanitize_key( $field_error['field'] ) && ! empty( $field_error['message'] );
	}

	/**
	 * Returns the persistent disabled-state reminder when a site key exists but AI is still off.
	 *
	 * @param IC_AI_Integration $integration        Integration.
	 * @param array             $site_settings      Site settings payload.
	 * @param array             $post_type_settings Post-type settings payload.
	 *
	 * @return array
	 */
	private function persistent_disabled_notice( $integration, $site_settings, $post_type_settings ) {
		if ( ! $this->activation_required( $site_settings, $post_type_settings ) ) {
			return array();
		}

		return $this->notice_payload( 'success', $this->notified_license_applied_message( $integration ) );
	}

	/**
	 * Returns whether saved credentials still require the activation controls.
	 *
	 * @param array $site_settings   Site settings.
	 * @param array $target_settings Target settings.
	 *
	 * @return bool
	 */
	private function activation_required( $site_settings, $target_settings ) {
		return empty( $target_settings['enabled'] ) &&
			! empty( $site_settings['license_key'] ) &&
			! $this->manager->client()->has_registered_client( $site_settings );
	}

	/**
	 * Returns one persistent warning notice for existing-license manual recovery.
	 *
	 * @param array $site_settings Site settings payload.
	 *
	 * @return array
	 */
	private function persistent_manual_recovery_notice( $site_settings ) {
		if ( ! $this->is_pending_manual_license_recovery( $site_settings ) ) {
			return array();
		}

		$pending = $this->manager->client()->pending_purchase();
		$message = ! empty( $pending['message'] ) ? $pending['message'] : $this->existing_license_key_manual_recovery_message();

		return $this->notice_payload( 'warning', $message );
	}

	/**
	 * Returns merged section notices without duplicate message/type pairs.
	 *
	 * @param array ...$notices Candidate notices.
	 *
	 * @return array
	 */
	private function merged_section_notices( ...$notices ) {
		$merged = array();

		foreach ( $notices as $notice ) {
			if ( empty( $notice ) || ! is_array( $notice ) || empty( $notice['message'] ) ) {
				continue;
			}

			$signature = sanitize_key( ! empty( $notice['type'] ) ? $notice['type'] : 'info' ) . '|' . sanitize_text_field( $notice['message'] );
			if ( isset( $merged[ $signature ] ) ) {
				continue;
			}

			$merged[ $signature ] = $this->notice_payload(
				! empty( $notice['type'] ) ? $notice['type'] : 'info',
				$notice['message']
			);
		}

		return array_values( $merged );
	}

	/**
	 * Returns one inline license-key comment row when needed.
	 *
	 * @param array $field_error Field-error payload.
	 *
	 * @return array
	 */
	private function license_key_error_row( $field_error ) {
		if ( ! $this->is_license_key_field_error( $field_error ) ) {
			return array();
		}

		$message = sprintf(
			'<p class="ic-ai-license-key-error-message">%s%s</p>',
			esc_html( $field_error['message'] ),
			$this->license_key_error_action_html( $field_error )
		);

		return array(
			'type' => 'html',
			'html' => '<tr class="ic-ai-license-key-comment-row"><th scope="row"></th><td>' . $message . '</td></tr>',
		);
	}

	/**
	 * Returns one inline license-key CTA when present.
	 *
	 * @param array $field_error Field-error payload.
	 *
	 * @return string
	 */
	private function license_key_error_action_html( $field_error ) {
		if ( empty( $field_error['action_url'] ) ) {
			return '';
		}

		$action_label = ! empty( $field_error['action_label'] ) ? $field_error['action_label'] : __( 'Upgrade now', 'post-type-x' );
		$action_mode  = $this->license_key_error_action_mode( $field_error['action_url'] );

		return sprintf(
			' <a class="ic-ai-billing-trigger ic-ai-license-key-error-link" href="%1$s" data-billing-mode="%2$s" data-billing-public="1">%3$s</a> %4$s',
			esc_url( $field_error['action_url'] ),
			esc_attr( $action_mode ),
			esc_html( $action_label ),
			esc_html__( 'to solve this problem.', 'post-type-x' )
		);
	}

	/**
	 * Returns the billing mode encoded in one action URL.
	 *
	 * @param string $action_url Action URL.
	 *
	 * @return string
	 */
	private function license_key_error_action_mode( $action_url ) {
		$query = wp_parse_url( $action_url, PHP_URL_QUERY );
		if ( empty( $query ) ) {
			return 'upgrade';
		}

		parse_str( $query, $query_args );
		$mode = ! empty( $query_args['ic-ai-billing'] ) ? sanitize_key( $query_args['ic-ai-billing'] ) : 'upgrade';

		return in_array( $mode, array( 'purchase', 'enhancements' ), true ) ? $mode : 'upgrade';
	}

	/**
	 * Returns the final disclosure section.
	 *
	 * @return array
	 */
	private function disclosure_section() {
		$site_settings = $this->manager->client()->site_settings();
		$disclosure    = $site_settings['disclosure'] ?? array();
		if ( empty( $disclosure ) || ! is_array( $disclosure ) || empty( $disclosure['ack'] ) || empty( $disclosure['analysis'] ) ) {
			return array();
		}

		$integration        = $this->current_integration();
		$target             = $integration ? $this->current_target_for_integration( $integration ) : false;
		$post_type_settings = $target instanceof IC_AI_Target ? $this->manager->client()->target_settings( $target ) : array();
		$ack                = is_array( $disclosure['ack'] ) ? $disclosure['ack'] : array();
		$analysis           = is_array( $disclosure['analysis'] ) ? $disclosure['analysis'] : array();
		$ack_label          = isset( $ack['label'] ) ? (string) $ack['label'] : '';
		if ( $this->activation_required( $site_settings, $post_type_settings ) ) {
			$ack_label = sprintf( '%1$s %2$s', $ack_label, __( '(Required)', 'post-type-x' ) );
		}

		return array(
			'title'           => isset( $disclosure['title'] ) ? (string) $disclosure['title'] : '',
			'container_class' => 'ic-settings-section ic-ai-manual-settings-section',
			'table_class'     => 'IC_Settings_Standard_Table',
			'settings'        => array(
				'rows' => array(
					array(
						'type'     => 'checkboxes',
						'label'    => $ack_label,
						'name'     => 'ic_ai_disclosure_ack',
						'value'    => $this->saved_disclosure_consent_value( $post_type_settings ),
						'multiple' => false,
						'notice'   => array(
							'type'    => 'warning',
							'message' => isset( $ack['notice'] ) ? (string) $ack['notice'] : '',
						),
						'options'  => array(
							array(
								'value'      => 'yes',
								'label'      => isset( $ack['option'] ) ? (string) $ack['option'] : '',
								'id'         => 'ic-ai-disclosure-ack',
								'attributes' => array(
									'aria-required' => 'false',
								),
							),
						),
					),
					array(
						'type'     => 'checkboxes',
						'label'    => isset( $analysis['label'] ) ? (string) $analysis['label'] : '',
						'name'     => 'ic_ai_analysis_opt_in',
						'value'    => $this->saved_analysis_opt_in_value( $post_type_settings ),
						'multiple' => false,
						'notice'   => array(
							'type'    => 'info',
							'message' => isset( $analysis['notice'] ) ? (string) $analysis['notice'] : '',
						),
						'options'  => array(
							array(
								'value' => 'yes',
								'label' => isset( $analysis['option'] ) ? (string) $analysis['option'] : '',
								'id'    => 'ic-ai-analysis-opt-in',
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Returns the persisted disclosure checkbox value for rendering.
	 *
	 * @param array $post_type_settings Stored post-type AI settings.
	 *
	 * @return string
	 */
	private function saved_disclosure_consent_value( $post_type_settings ) {
		return ! empty( $post_type_settings['consented_at'] ) ? 'yes' : '';
	}

	/**
	 * Returns the persisted optional analysis checkbox value for rendering.
	 *
	 * @param array $post_type_settings Stored post-type AI settings.
	 *
	 * @return string
	 */
	private function saved_analysis_opt_in_value( $post_type_settings ) {
		return ! empty( $post_type_settings['analysis_opt_in'] ) ? 'yes' : '';
	}

	/**
	 * Returns the effective disclosure consent for an enabled save.
	 *
	 * @param string $ack                Posted disclosure acknowledgement.
	 * @param array  $post_type_settings Stored post-type AI settings.
	 *
	 * @return array|WP_Error
	 */
	private function validated_disclosure_consent( $ack, $post_type_settings ) {
		if ( 'yes' === $ack ) {
			return array(
				'consented_at' => gmdate( 'c' ),
				'consented_by' => get_current_user_id(),
			);
		}

		if ( empty( $post_type_settings['consented_at'] ) ) {
			return new WP_Error( 'ic_ai_disclosure_required', __( 'You must confirm the data-sharing disclosure before enabling or updating AI.', 'post-type-x' ) );
		}

		return array(
			'consented_at' => sanitize_text_field( $post_type_settings['consented_at'] ),
			'consented_by' => ! empty( $post_type_settings['consented_by'] ) ? absint( $post_type_settings['consented_by'] ) : 0,
		);
	}

	/**
	 * Returns model dropdown options.
	 *
	 * @param array $site_settings Site settings.
	 *
	 * @return array
	 */
	private function model_dropdown_options( $site_settings ) {
		$options = array(
			'' => __( 'Use server default', 'post-type-x' ),
		);

		foreach ( $this->model_options( $site_settings ) as $model ) {
			$options[ $model ] = $model;
		}

		return $options;
	}

	/**
	 * Returns human field labels keyed by field slug.
	 *
	 * The target is always supplied by the caller: resolving it from the request
	 * here would make every consumer render the field map of whichever target the
	 * `target_key` query argument happens to name.
	 *
	 * @param IC_AI_Target $target              Target whose field map is described.
	 * @param string       $which               Optional mode filter: 'enhance', 'context', or '' for all.
	 * @param array        $excluded_field_keys Optional field keys excluded from context options.
	 *
	 * @return array
	 */
	private function field_labels( $target, $which = '', $excluded_field_keys = array() ) {
		$labels              = array();
		$excluded_field_keys = is_array( $excluded_field_keys ) ? array_map( 'sanitize_key', $excluded_field_keys ) : array();

		$field_map = $target instanceof IC_AI_Target ? $target->field_map() : array();
		foreach ( $field_map as $field_key => $field ) {
			if ( '' !== $which ) {
				$is_preserve = 'preserve' === $this->manager->client()->service_field_mode( $field_key, $field );
				if ( 'enhance' === $which && $is_preserve ) {
					continue;
				}
				if ( 'context' === $which && ! $is_preserve && in_array( sanitize_key( $field_key ), $excluded_field_keys, true ) ) {
					continue;
				}
			}
			$labels[ $field_key ] = isset( $field['label'] ) ? $field['label'] : $field_key;
		}

		return $labels;
	}

	/**
	 * Returns a disabled "Enable AI" checkbox row for the definitions-unavailable state.
	 *
	 * The service-owned disclosure/marketing-control definitions could not be cached, so
	 * the consent checkbox cannot render. Disabling the control prevents the user from
	 * toggling Enable AI into a blocking consent dead-end. The shared framework checkbox
	 * helper has no declarative disabled flag, so the row markup is emitted inline via the
	 * trusted `html` row type instead of adding external JavaScript.
	 *
	 * @param array $enable_ai_row Base Enable AI checkbox row definition.
	 *
	 * @return array
	 */
	private function disabled_enable_ai_row( $enable_ai_row ) {
		$checked = ! empty( $enable_ai_row['value'] ) ? ' checked="checked"' : '';
		$html    = '<tr>'
			. '<td><span title="' . esc_attr( $enable_ai_row['tip'] ) . '" class="dashicons dashicons-editor-help ic_tip"></span>' . esc_html( $enable_ai_row['label'] ) . ':</td>'
			. '<td><input type="checkbox" name="' . esc_attr( $enable_ai_row['name'] ) . '" value="1"' . $checked . ' disabled="disabled"/></td>'
			. '</tr>';

		return array(
			'type' => 'html',
			'html' => $html,
		);
	}

	/**
	 * Returns an inline note row shown when the service display definitions are missing.
	 *
	 * @return array
	 */
	private function definitions_missing_note_row() {
		$html = '<tr><td colspan="2"><p class="description">'
			. esc_html__( 'AI settings couldn\'t be loaded from impleCode — click Retry above to try again.', 'post-type-x' )
			. '</p></td></tr>';

		return array(
			'type' => 'html',
			'html' => $html,
		);
	}

	/**
	 * Returns registered custom-meta multiselect options keyed by meta key.
	 *
	 * The target is always supplied by the caller so the options belong to the
	 * rendered target rather than to the request-scoped `target_key`.
	 *
	 * @param IC_AI_Target $target      Target whose registered meta is listed.
	 * @param string       $which       Selector context.
	 * @param string       $placeholder Optional Chosen placeholder label.
	 *
	 * @return array
	 */
	private function registered_meta_options( $target, $which, $placeholder = '' ) {
		$integration = $target instanceof IC_AI_Target ? $target->integration() : false;
		$options     = array();
		if ( '' !== $placeholder ) {
			$options[0] = sanitize_text_field( $placeholder );
		}
		foreach ( $this->manager->client()->registered_meta_keys( $target, $integration, $which ) as $meta_key ) {
			$options[ $meta_key ] = esc_html( $this->manager->client()->prettified_meta_label( $meta_key, $target, $integration, $which ) );
		}

		return $options;
	}

	/**
	 * Renders the browser-level disclosure requirement sync script.
	 *
	 * @return void
	 */
	private function render_disclosure_requirement_script() {
		?>
		<style>
			#ic-ai-settings-secondary {
				min-width: 650px;
			}

			.ic-ai-manual-settings-hidden .ic-ai-manual-settings-section,
			.ic-ai-manual-settings-hidden .submit {
				display: none;
			}

			.ic-ai-activation-required input[name="ic_ai_settings[enabled]"]:not(:checked):not(:disabled),
			.ic-ai-activation-required #ic-ai-disclosure-ack:not(:checked):not(:disabled) {
				outline: 2px solid #dba617;
				outline-offset: 3px;
			}

		</style>
		<script>
			(function() {
				var enableField = document.querySelector('input[name="ic_ai_settings[enabled]"]');
				var disclosureField = document.getElementById('ic-ai-disclosure-ack');
				if (!enableField || !disclosureField) {
					return;
				}

				function syncDisclosureRequirement() {
					disclosureField.required = !!enableField.checked;
					disclosureField.setAttribute('aria-required', disclosureField.required ? 'true' : 'false');
				}

				enableField.addEventListener('change', syncDisclosureRequirement);
				syncDisclosureRequirement();
			})();
		</script>
		<?php
	}

	/**
	 * Resolves model options from cached site settings.
	 *
	 * @param array $site_settings Site settings.
	 *
	 * @return array
	 */
	private function model_options( $site_settings ) {
		if ( empty( $site_settings['available_models'] ) || ! is_array( $site_settings['available_models'] ) ) {
			return array();
		}
		$models = array();
		foreach ( $site_settings['available_models'] as $model ) {
			if ( is_array( $model ) && ! empty( $model['id'] ) ) {
				$models[] = sanitize_text_field( $model['id'] );
			} elseif ( is_string( $model ) ) {
				$models[] = sanitize_text_field( $model );
			}
		}

		return array_values( array_unique( array_filter( $models ) ) );
	}

	/**
	 * Returns a simple quota label.
	 *
	 * @param array $quota Quota data.
	 *
	 * @return string
	 */
	private function quota_label( $quota ) {
		if ( empty( $quota ) || ! is_array( $quota ) ) {
			return '';
		}
		return sprintf(
			/* translators: 1: formatted number of AI Credits remaining in the plan, 2: formatted number of extra AI Credits remaining. */
			__( 'Plan: %1$s · Extra: %2$s', 'post-type-x' ),
			number_format_i18n( intval( $quota['included_enhancements_remaining'] ?? 0 ) ),
			number_format_i18n( intval( $quota['extra_enhancements_remaining'] ?? 0 ) )
		);
	}

	/**
	 * Returns the "Active Subscription" table cell markup.
	 *
	 * @param array $site_settings Site settings.
	 *
	 * @return string
	 */
	private function subscription_row_value( $site_settings ) {
		$subscription = ! empty( $site_settings['subscription'] ) && is_array( $site_settings['subscription'] ) ? $site_settings['subscription'] : array();
		if ( empty( $subscription['exists'] ) ) {
			return esc_html__( 'No active subscription', 'post-type-x' );
		}

		if ( ! empty( $subscription['cancelled'] ) ) {
			return esc_html__( 'Cancelled — access continues until your license expires', 'post-type-x' );
		}

		$renews_at = ! empty( $subscription['renews_at'] ) ? mysql2date( get_option( 'date_format' ), $subscription['renews_at'] ) : '';
		$status    = esc_html__( 'Active', 'post-type-x' );
		if ( ! empty( $renews_at ) ) {
			/* translators: %s: renewal date. */
			$status .= ' — ' . sprintf( esc_html__( 'renews on %s', 'post-type-x' ), esc_html( $renews_at ) );
		}

		return $status . ' <a href="#" class="button-link ic-ai-cancel-subscription" data-expires="' . esc_attr( $this->license_expiration_date_label( $site_settings ) ) . '">' . esc_html__( 'Cancel', 'post-type-x' ) . '</a>';
	}

	/**
	 * Returns the "License Expiration Date" table cell markup.
	 *
	 * @param array $site_settings Site settings.
	 *
	 * @return string
	 */
	private function license_expiration_value( $site_settings ) {
		$license = ! empty( $site_settings['license'] ) && is_array( $site_settings['license'] ) ? $site_settings['license'] : array();
		$label   = esc_html( $this->license_expiration_label( $site_settings ) );
		$tip     = '';
		if ( ! empty( $license['is_expired'] ) || ( isset( $license['effective_status'] ) && 'expired' === $license['effective_status'] ) ) {
			$tip = __( 'This plan has expired. Included AI Credits were set to zero and will not refill. Extra AI Credits remain available and can still be used.', 'post-type-x' );
		} elseif ( ! empty( $license['free_plan_grandfathered'] ) && ! empty( $license['free_plan_policy_months'] ) ) {
			$tip = __( 'This Free license is grandfathered and remains non-expiring. The configured expiration term applies only to newly issued or manually regranted Free plans.', 'post-type-x' );
		} elseif ( isset( $license['free_plan_expiration_months'] ) && 0 === absint( $license['free_plan_expiration_months'] ) && ! empty( $license['free_plan_policy_months'] ) ) {
			$tip = __( 'This Free license remains non-expiring under the policy in force when it was issued. Newly issued or manually regranted Free plans use the currently configured expiration term.', 'post-type-x' );
		} elseif ( ! empty( $license['expires_at'] ) && ( ! empty( $license['free_plan_policy_months'] ) || ! empty( $license['free_plan_expiration_months'] ) ) ) {
			$tip = __( 'When this plan expires, Included AI Credits will be set to zero and will no longer refill. Extra AI Credits will remain available and usable.', 'post-type-x' );
		}
		if ( '' === $tip ) {
			return $label;
		}

		return $label . ' <span title="' . esc_attr( $tip ) . '" class="dashicons dashicons-editor-help ic_tip"></span>';
	}

	/**
	 * Returns a formatted license expiration date, or "Never".
	 *
	 * @param array $site_settings Site settings.
	 *
	 * @return string
	 */
	private function license_expiration_label( $site_settings ) {
		$license    = ! empty( $site_settings['license'] ) && is_array( $site_settings['license'] ) ? $site_settings['license'] : array();
		$is_expired = ! empty( $license['is_expired'] ) || ( isset( $license['effective_status'] ) && 'expired' === $license['effective_status'] );
		$date       = $this->license_expiration_date_label( $site_settings );
		if ( empty( $license['expires_at'] ) ) {
			return $date;
		}
		if ( $is_expired ) {
			/* translators: %s: localized license expiration date. */
			return sprintf( __( 'Expired on %s', 'post-type-x' ), $date );
		}

		return $date;
	}

	/**
	 * Returns only the localized expiration date for placeholder reuse.
	 *
	 * @param array $site_settings Site settings.
	 *
	 * @return string
	 */
	private function license_expiration_date_label( $site_settings ) {
		$license    = ! empty( $site_settings['license'] ) && is_array( $site_settings['license'] ) ? $site_settings['license'] : array();
		$expires_at = isset( $license['expires_at'] ) ? $license['expires_at'] : '';
		if ( empty( $expires_at ) ) {
			return __( 'Never', 'post-type-x' );
		}

		return date_i18n( get_option( 'date_format' ), absint( $expires_at ) );
	}

	/**
	 * Returns whether a registered current plan should be refreshed to backfill persisted detail rows.
	 *
	 * @param array $site_settings Site settings.
	 *
	 * @return bool
	 */
	private function current_plan_needs_remote_detail_refresh( $site_settings ) {
		$active_plan = ! empty( $site_settings['active_plan'] ) && is_array( $site_settings['active_plan'] ) ? $site_settings['active_plan'] : array();
		if ( empty( $active_plan['name'] ) ) {
			return false;
		}

		return ! isset( $active_plan['detail_rows'] ) || ! is_array( $active_plan['detail_rows'] ) || empty( $active_plan['detail_rows'] );
	}

	/**
	 * Returns whether the cached service-owned display definitions are missing.
	 *
	 * The marketing-control definitions and disclosure copy are cached from remote
	 * responses. When either is empty the cache-backed settings sections (marketing
	 * dropdowns + disclosure checkbox) cannot render, so a refresh is required.
	 *
	 * @param array $site_settings Site settings.
	 *
	 * @return bool
	 */
	private function cached_display_definitions_missing( $site_settings ) {
		$definitions = ! empty( $site_settings['marketing_control_definitions'] ) && is_array( $site_settings['marketing_control_definitions'] ) ? $site_settings['marketing_control_definitions'] : array();
		$disclosure  = ! empty( $site_settings['disclosure'] ) && is_array( $site_settings['disclosure'] ) ? $site_settings['disclosure'] : array();

		return empty( $definitions ) || empty( $disclosure );
	}

	/**
	 * Returns whether cached service-owned display definitions need refreshing.
	 *
	 * @param array $site_settings Site settings.
	 *
	 * @return bool
	 */
	private function cached_display_definitions_stale( $site_settings ) {
		if ( $this->cached_display_definitions_missing( $site_settings ) ) {
			return true;
		}

		$synced_at       = ! empty( $site_settings['display_definitions_synced_at'] ) ? absint( $site_settings['display_definitions_synced_at'] ) : 0;
		$now             = time();
		$current_version = $this->manager->client()->display_definitions_plugin_version();
		$stored_version  = isset( $site_settings['display_definitions_plugin_version'] ) && is_scalar( $site_settings['display_definitions_plugin_version'] ) ? (string) $site_settings['display_definitions_plugin_version'] : '';

		return empty( $synced_at )
			|| $synced_at > $now
			|| ( $now - $synced_at ) > IC_AI_Client::DISPLAY_DEFINITIONS_TTL
			|| ( '' !== $current_version && $current_version !== $stored_version );
	}

	/**
	 * Refreshes the remote catalog/plan cache before cache-backed UI renders.
	 *
	 * Runs only during actual settings-page rendering (never on every `init`).
	 * Guarded by staleness/registration checks so it fetches at most once per view
	 * and degrades gracefully on a remote error. The remote round-trip is memoized
	 * per request: several render hooks (`register_screens()`, `render_screen_content()`,
	 * `render_page_after_form()`) call this, but a failed refresh keeps its predicate
	 * true, so without memoization a single offline page load could fire multiple
	 * blocking 20-second calls and time out the error UI.
	 *
	 * @return array The current (possibly refreshed) site settings.
	 */
	private function refresh_remote_state_for_display() {
		$client = $this->manager->client();
		if ( $this->remote_refresh_attempted ) {
			return $client->site_settings();
		}
		$this->remote_refresh_attempted = true;

		$site_settings = $client->site_settings();
		if ( ! $client->has_registered_client( $site_settings ) && $client->public_catalog_is_stale( $site_settings ) ) {
			$catalog = $client->refresh_public_catalog_cache();
			if ( ! is_wp_error( $catalog ) ) {
				$site_settings = $client->site_settings();
			}
		} elseif ( $client->has_registered_client( $site_settings ) && ! empty( $site_settings['subscription']['cancelled'] ) && empty( $site_settings['upgrade_url'] ) ) {
			$refreshed = $client->refresh_plan_state();
			if ( ! is_wp_error( $refreshed ) ) {
				$site_settings = $client->site_settings();
			}
		} elseif ( $client->has_registered_client( $site_settings ) && ( $this->current_plan_needs_remote_detail_refresh( $site_settings ) || $this->cached_display_definitions_stale( $site_settings ) ) ) {
			$refreshed = $client->refresh_plan_state();
			if ( ! is_wp_error( $refreshed ) ) {
				$site_settings = $client->site_settings();
			}
		} elseif ( $client->has_registered_client( $site_settings ) && ! empty( $site_settings['last_error'] ) ) {
			$refreshed = $client->refresh_plan_state();
			if ( ! is_wp_error( $refreshed ) ) {
				$site_settings = $client->site_settings();
			}
		}

		return $site_settings;
	}

	/**
	 * Returns the "Current plan" table cell markup.
	 *
	 * @param IC_AI_Integration $integration   Integration.
	 * @param array             $site_settings Site settings.
	 *
	 * @return string
	 */
	private function current_plan_row_value( $integration, $site_settings ) {
		$active_plan = ! empty( $site_settings['active_plan'] ) && is_array( $site_settings['active_plan'] ) ? $site_settings['active_plan'] : array();
		$plan_name   = ! empty( $active_plan['name'] ) ? sanitize_text_field( $active_plan['name'] ) : '';
		if ( '' === $plan_name ) {
			return esc_html__( 'Not Selected', 'post-type-x' );
		}
		if ( ! $this->has_current_plan_details( $active_plan ) ) {
			return esc_html( $plan_name );
		}

		$price_label = $this->current_plan_price_label( $active_plan );
		$description = $this->current_plan_description( $active_plan );
		$detail_rows = $this->current_plan_detail_rows( $active_plan );
		$details_id  = 'ic-ai-current-plan-details-' . sanitize_html_class( $integration->post_type() );

		ob_start();
		?>
		<div class="ic-ai-current-plan-wrap">
			<button type="button" class="button-link ic-ai-current-plan-toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $details_id ); ?>">
				<span class="ic-ai-current-plan-name"><?php echo esc_html( $plan_name ); ?></span>
				<?php
				printf(
					'<span class="screen-reader-text">%s</span>',
					esc_html(
						sprintf(
							/* translators: %s: active AI plan name. */
							__( 'View plan details for %s', 'post-type-x' ),
							$plan_name
						)
					)
				);
				?>
				<span class="dashicons dashicons-arrow-down-alt2 ic-ai-current-plan-icon" aria-hidden="true"></span>
			</button>
			<div id="<?php echo esc_attr( $details_id ); ?>" class="ic-ai-current-plan-details" hidden>
				<div class="ic-ai-current-plan-card">
					<h3><?php echo esc_html( $plan_name ); ?></h3>
					<?php if ( '' !== $price_label ) : ?>
						<p class="ic-ai-current-plan-price"><?php echo esc_html( $price_label ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== $description ) : ?>
						<p class="ic-ai-current-plan-description"><?php echo esc_html( $description ); ?></p>
					<?php endif; ?>
					<?php foreach ( $detail_rows as $row ) : ?>
						<p class="ic-ai-current-plan-detail"><strong><?php echo $this->current_plan_detail_row_label_html( $row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- current_plan_detail_row_label_html() escapes label and optional tip. ?>:</strong> <?php echo esc_html( $row['value'] ); ?></p>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Returns whether the active plan has enough metadata for inline details.
	 *
	 * @param array $active_plan Active plan data.
	 *
	 * @return bool
	 */
	private function has_current_plan_details( $active_plan ) {
		return '' !== $this->current_plan_price_label( $active_plan ) || '' !== $this->current_plan_description( $active_plan ) || ! empty( $this->current_plan_detail_rows( $active_plan ) );
	}

	/**
	 * Returns the active-plan monthly price label.
	 *
	 * @param array $active_plan Active plan data.
	 *
	 * @return string
	 */
	private function current_plan_price_label( $active_plan ) {
		if ( ! is_array( $active_plan ) || ! array_key_exists( 'price', $active_plan ) || '' === (string) $active_plan['price'] || ! is_numeric( $active_plan['price'] ) ) {
			return '';
		}

		return '$' . number_format_i18n( floatval( $active_plan['price'] ), 2 ) . esc_html__( ' / month', 'post-type-x' );
	}

	/**
	 * Returns the sanitized active-plan description.
	 *
	 * @param array $active_plan Active plan data.
	 *
	 * @return string
	 */
	private function current_plan_description( $active_plan ) {
		if ( empty( $active_plan['description'] ) || ! is_scalar( $active_plan['description'] ) ) {
			return '';
		}

		return sanitize_text_field( wp_strip_all_tags( (string) $active_plan['description'] ) );
	}

	/**
	 * Returns the active-plan detail rows shown inline in settings.
	 *
	 * @param array $active_plan Active plan data.
	 *
	 * @return array
	 */
	private function current_plan_detail_rows( $active_plan ) {
		if ( empty( $active_plan ) || ! is_array( $active_plan ) ) {
			return array();
		}
		if ( ! empty( $active_plan['detail_rows'] ) && is_array( $active_plan['detail_rows'] ) ) {
			return $this->normalized_current_plan_detail_rows( $active_plan['detail_rows'] );
		}

		return array();
	}

	/**
	 * Returns normalized current-plan detail rows after external filters run.
	 *
	 * @param array $rows Candidate rows.
	 *
	 * @return array
	 */
	private function normalized_current_plan_detail_rows( $rows ) {
		if ( empty( $rows ) || ! is_array( $rows ) ) {
			return array();
		}

		$normalized_rows = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['label'], $row['value'] ) ) {
				continue;
			}
			if ( ! is_scalar( $row['label'] ) || ! is_scalar( $row['value'] ) ) {
				continue;
			}

			$label = sanitize_text_field( wp_strip_all_tags( (string) $row['label'] ) );
			$value = sanitize_text_field( wp_strip_all_tags( (string) $row['value'] ) );
			if ( '' === $label || '' === $value ) {
				continue;
			}

			$normalized_row = array(
				'label' => $label,
				'value' => $value,
			);
			if ( isset( $row['tip'] ) && is_scalar( $row['tip'] ) ) {
				$tip = sanitize_text_field( wp_strip_all_tags( (string) $row['tip'] ) );
				if ( '' !== $tip ) {
					$normalized_row['tip'] = $tip;
				}
			}

			$normalized_rows[] = $normalized_row;
		}

		return $normalized_rows;
	}

	/**
	 * Returns the rendered current-plan detail row label with optional help tip.
	 *
	 * @param array $row Normalized row.
	 *
	 * @return string
	 */
	private function current_plan_detail_row_label_html( $row ) {
		$label = esc_html( $row['label'] );
		if ( empty( $row['tip'] ) ) {
			return $label;
		}

		return '<span title="' . esc_attr( $row['tip'] ) . '" class="dashicons dashicons-editor-help ic_tip"></span> ' . $label;
	}

	/**
	 * Returns whether the current model list contains a real choice.
	 *
	 * @param array $site_settings Site settings.
	 *
	 * @return bool
	 */
	private function has_model_choice( $site_settings ) {
		return count( $this->model_options( $site_settings ) ) > 1;
	}

	/**
	 * Returns the AI settings form class list.
	 *
	 * @param array $site_settings Site settings.
	 * @param array $target_settings Target settings.
	 *
	 * @return string
	 */
	private function settings_form_class( $site_settings, $target_settings ) {
		$classes = array( 'ic-ai-settings-form' );
		if ( ! $this->manual_settings_visible( $site_settings ) ) {
			$classes[] = 'ic-ai-manual-settings-hidden';
		}
		if ( $this->activation_required( $site_settings, $target_settings ) ) {
			$classes[] = 'ic-ai-activation-required';
		}

		return implode( ' ', $classes );
	}

	/**
	 * Returns whether the manual activation settings should be visible.
	 *
	 * @param array $site_settings Site settings.
	 *
	 * @return bool
	 */
	private function manual_settings_visible( $site_settings ) {
		if ( $this->manager->client()->has_registered_client( $site_settings ) ) {
			return true;
		}

		if ( ! empty( $site_settings['license_key'] ) ) {
			return true;
		}

		$pending = $this->manager->client()->pending_purchase();
		if ( $this->is_pending_manual_license_recovery( $site_settings, $pending ) ) {
			return true;
		}
		// The email is confirmed and the key was mailed once service activation
		// succeeded, so the existing License Key field is the recovery path when the
		// automatic setup cannot reach this site.
		if ( ! empty( $pending['status'] ) && 'confirmed_pending_activation' === $pending['status'] ) {
			return true;
		}

		return $this->is_waiting_request() && ! empty( $pending['status'] ) && 'error' === $pending['status'];
	}

	/**
	 * Renders the registered-client panel.
	 *
	 * @param IC_AI_Integration $integration   Integration.
	 * @param array             $site_settings Site settings.
	 *
	 * @return void
	 */
	private function render_registered_panel( $integration, $site_settings ) {
		$target = $this->current_target_for_integration( $integration );
		if ( ! $target ) {
			$target = $integration->target( 'post' );
		}
		?>
		<h2><?php esc_html_e( 'Connection', 'post-type-x' ); ?></h2>
		<table class="widefat striped" style="max-width:900px;">
			<tbody>
				<tr><td><?php esc_html_e( 'Client ID', 'post-type-x' ); ?></td><td><?php echo esc_html( $site_settings['client_id'] ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Status', 'post-type-x' ); ?></td><td><?php echo esc_html( $site_settings['client_status'] ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Last registration', 'post-type-x' ); ?></td><td><?php echo esc_html( $site_settings['last_registration'] ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Current plan', 'post-type-x' ); ?></td><td class="ic-ai-current-plan-cell"><?php echo $this->current_plan_row_value( $integration, $site_settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in current_plan_row_value(). ?></td></tr>
				<tr><td><?php esc_html_e( 'AI Credits', 'post-type-x' ); ?></td><td><?php echo esc_html( $this->quota_label( $site_settings['quota'] ) ); ?><p class="description"><?php esc_html_e( 'One AI Credit is used for each item processed by impleCode AI. When multiple items are processed at once, each item uses one credit.', 'post-type-x' ); ?></p></td></tr>
				<tr><td><?php esc_html_e( 'Active Subscription', 'post-type-x' ); ?></td><td class="ic-ai-subscription-cell"><?php echo $this->subscription_row_value( $site_settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in subscription_row_value(). ?></td></tr>
				<tr><td><?php esc_html_e( 'License Expiration Date', 'post-type-x' ); ?></td><td class="ic-ai-license-expiration-cell"><?php echo $this->license_expiration_value( $site_settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in license_expiration_value(). ?></td></tr>
			</tbody>
		</table>
		<?php if ( ! empty( $site_settings['upgrade_url'] ) || ! empty( $site_settings['buy_enhancements_url'] ) ) : ?>
			<p>
				<?php if ( ! empty( $site_settings['upgrade_url'] ) ) : ?>
					<?php $upgrade_label = ! empty( $site_settings['subscription']['cancelled'] ) ? __( 'Renew Plan', 'post-type-x' ) : __( 'Upgrade Plan', 'post-type-x' ); ?>
					<a class="button button-secondary ic-ai-billing-trigger" href="<?php echo esc_url( $site_settings['upgrade_url'] ); ?>" data-billing-mode="upgrade" data-billing-public="1"><?php echo esc_html( $upgrade_label ); ?></a>
				<?php endif; ?>
				<?php if ( ! empty( $site_settings['buy_enhancements_url'] ) ) : ?>
					<a class="button button-secondary ic-ai-billing-trigger" href="<?php echo esc_url( $site_settings['buy_enhancements_url'] ); ?>" data-billing-mode="enhancements" data-billing-public="1"><?php esc_html_e( 'Buy AI Credits', 'post-type-x' ); ?></a>
				<?php endif; ?>
			</p>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:20px;">
			<input type="hidden" name="action" value="ic_ai_remove_key" />
			<input type="hidden" name="post_type" value="<?php echo esc_attr( $integration->post_type() ); ?>" />
			<input type="hidden" name="target_key" value="<?php echo esc_attr( $target->key() ); ?>" />
			<?php wp_nonce_field( 'ic_ai_remove_key_' . $target->key() ); ?>
			<button type="submit" name="submit" id="submit" class="button-link button-link-delete ic-ai-unregister-trigger"><?php esc_html_e( 'Unregister Site', 'post-type-x' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Renders the unregistered onboarding panel.
	 *
	 * @param IC_AI_Integration $integration   Integration.
	 * @param array             $site_settings Site settings.
	 *
	 * @return void
	 */
	private function render_unregistered_panel( $integration, $site_settings ) {
		$manual_recovery_visible = $this->manual_settings_visible( $site_settings );
		$manual_recovery_pending = $this->is_pending_manual_license_recovery( $site_settings );
		?>
		<?php if ( ! empty( $site_settings['license_key'] ) ) : ?>
			<h2><?php esc_html_e( 'Connect Your AI License Key', 'post-type-x' ); ?></h2>
			<p><?php esc_html_e( 'This site already has a saved AI License Key.', 'post-type-x' ); ?></p>
			<div class="notice notice-info inline">
				<p><?php esc_html_e( 'Save AI Settings to register or re-register this site with impleCode AI using the saved license key.', 'post-type-x' ); ?></p>
			</div>
		<?php elseif ( $manual_recovery_pending ) : ?>
			<h2><?php esc_html_e( 'Use Your Existing AI License Key', 'post-type-x' ); ?></h2>
			<p><?php esc_html_e( 'Your AI plan payment was processed, but this site could not retrieve the existing license key automatically.', 'post-type-x' ); ?></p>
			<div class="ic-ai-manual-key-note notice notice-warning inline" style="display:block;max-width:900px;">
				<p><?php esc_html_e( 'Paste the AI License Key from your existing impleCode AI plan above, enable AI if needed, and save the settings to activate this site.', 'post-type-x' ); ?></p>
			</div>
		<?php else : ?>
			<h2><?php esc_html_e( 'Choose Your AI Plan', 'post-type-x' ); ?></h2>
			<p><?php esc_html_e( 'Buy an AI plan first, or use an existing AI License Key to activate AI on this site.', 'post-type-x' ); ?></p>
				<?php /* translators: %s: remote AI service host. */ ?>
				<div class="notice notice-info inline"><p><?php echo esc_html( sprintf( __( 'After payment, you will return here and this screen will wait for the AI license notification from %s.', 'post-type-x' ), wp_parse_url( $this->manager->client()->remote_url(), PHP_URL_HOST ) ) ); ?></p></div>
			<?php echo wp_kses_post( $this->local_site_warning_html() ); ?>
			<div class="notice notice-info inline ic-ai-remote-plan-loader"><p><?php esc_html_e( 'Loading available AI plans from impleCode...', 'post-type-x' ); ?></p></div>
			<p>
				<button type="button" class="button button-secondary ic-ai-manual-key-toggle"><?php esc_html_e( 'I already have AI License Key', 'post-type-x' ); ?></button>
			</p>
			<div class="ic-ai-manual-key-note notice notice-warning inline" style="<?php echo esc_attr( $manual_recovery_visible ? 'display:block;max-width:900px;' : 'display:none;max-width:900px;' ); ?>">
				<p><?php esc_html_e( 'Paste your AI License Key above, enable AI if needed, and save the settings to activate the feature.', 'post-type-x' ); ?></p>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Renders the waiting-state panel.
	 *
	 * @return void
	 */
	private function render_waiting_panel() {
		$status        = $this->pending_status_payload();
		$purchase_type = $this->manager->client()->pending_purchase_type( $this->manager->client()->pending_purchase() );
		$fallback      = 'enhancements' === $purchase_type ? $this->enhancement_fallback_activation_message() : $this->fallback_activation_message();
		$waiting_copy  = 'enhancements' === $purchase_type ? $this->enhancement_waiting_message() : __( 'Waiting for the AI license notification from impleCode...', 'post-type-x' );
		?>
		<div class="notice notice-info inline ic-ai-waiting-panel" data-fallback-message="<?php echo esc_attr( $fallback ); ?>">
			<p><strong><?php esc_html_e( 'AI activation in progress', 'post-type-x' ); ?></strong></p>
			<p class="ic-ai-waiting-message"><?php echo esc_html( ! empty( $status['message'] ) ? $status['message'] : $waiting_copy ); ?></p>
			<?php if ( 'pending_confirmation' === $status['status'] ) : ?>
				<?php
				$confirmation = ! empty( $status['confirmation'] ) ? $status['confirmation'] : array(
					'retry_after' => 0,
					'can_resend'  => true,
				);
				?>
				<p class="ic-ai-confirmation-actions">
					<button type="button" class="button button-secondary ic-ai-resend-confirmation" data-retry-after="<?php echo esc_attr( (string) absint( $confirmation['retry_after'] ) ); ?>" <?php disabled( empty( $confirmation['can_resend'] ) ); ?>><?php esc_html_e( 'Resend confirmation email', 'post-type-x' ); ?></button>
					<span class="ic-ai-resend-status" role="status" aria-live="polite"></span>
				</p>
			<?php endif; ?>
			<?php if ( 'confirmed_pending_activation' === $status['status'] ) : ?>
				<p class="ic-ai-confirmed-key-guidance"><?php esc_html_e( 'Your email is confirmed. If setup does not finish automatically, enter the AI License Key from your impleCode email in the License Key field and save AI Settings.', 'post-type-x' ); ?></p>
			<?php endif; ?>
			<p class="ic-ai-waiting-fallback" style="display:none;"></p>
		</div>
		<?php
	}

	/**
	 * Returns the current pending-status payload.
	 *
	 * @return array
	 */
	private function pending_status_payload() {
		$pending       = $this->manager->client()->pending_purchase();
		$integration   = $this->current_integration();
		$target        = ! empty( $pending['pending_target_key'] ) ? $this->manager->target( $pending['pending_target_key'] ) : ( $integration ? $integration->target( 'post' ) : false );
		$site_settings = $this->manager->client()->site_settings();
		if ( in_array( $pending['status'], array( 'pending_confirmation', 'confirmed_pending_activation' ), true ) && ! empty( $pending['callback_secret'] ) ) {
			// The browser polls every few seconds; the service is contacted at most
			// once per throttle window, and local settings are re-read afterwards so
			// a final signed key callback that arrived meanwhile still wins.
			$throttle_key = 'ic_ai_free_confirmation_status_' . md5( $pending['callback_secret'] );
			if ( ! get_transient( $throttle_key ) ) {
				set_transient( $throttle_key, 1, 12 );
				$remote = $this->manager->client()->free_license_confirmation_status();
				if ( is_array( $remote ) && ! empty( $remote['status'] ) ) {
					$this->manager->client()->store_free_license_confirmation_state( $remote );
					$remote_status = sanitize_key( $remote['status'] );
					$current       = $this->manager->client()->pending_purchase();
					if ( in_array( $remote_status, array( 'expired', 'superseded' ), true ) && hash_equals( $pending['callback_secret'], $current['callback_secret'] ) && in_array( $current['status'], array( 'pending_confirmation', 'confirmed_pending_activation' ), true ) ) {
						$this->manager->client()->clear_pending_purchase();
						return array(
							'status'       => $remote_status,
							'message'      => sanitize_text_field( $remote['message'] ),
							'settings_url' => $integration ? $this->settings_url( $integration, $target ) : '',
						);
					}
					if ( in_array( $current['status'], array( 'pending_confirmation', 'confirmed_pending_activation' ), true ) ) {
						$this->manager->client()->update_pending_purchase(
							array(
								'pending_status'  => in_array( $remote_status, array( 'pending_confirmation', 'confirmed_pending_activation' ), true ) ? $remote_status : 'pending',
								'pending_message' => ! empty( $remote['message'] ) ? sanitize_text_field( $remote['message'] ) : $current['message'],
							)
						);
					}
				}
				$pending       = $this->manager->client()->pending_purchase();
				$site_settings = $this->manager->client()->site_settings();
			}
		}
		if ( ! empty( $pending['status'] ) && 'pending' === $pending['status'] ) {
			$this->maybe_reconcile_pending_registered_purchase( $pending, $site_settings );
			$pending       = $this->manager->client()->pending_purchase();
			$site_settings = $this->manager->client()->site_settings();
		}
		$status        = ! empty( $pending['status'] ) ? $pending['status'] : 'idle';
		$message       = ! empty( $pending['message'] ) ? $pending['message'] : '';
		$purchase_type = $this->manager->client()->pending_purchase_type( $pending );

		if ( $this->manager->client()->has_registered_client( $site_settings ) && empty( $pending['status'] ) ) {
			$status  = 'ready';
			$message = __( 'The AI license was received and the site has been activated.', 'post-type-x' );
		} elseif ( 'error' === $status && empty( $message ) ) {
			$message = 'enhancements' === $purchase_type ? $this->enhancement_fallback_activation_message() : $this->fallback_activation_message();
		} elseif ( 'pending' === $status && empty( $message ) ) {
			$message = 'enhancements' === $purchase_type ? $this->enhancement_waiting_message() : __( 'Waiting for the AI license notification from impleCode...', 'post-type-x' );
		} elseif ( 'pending_confirmation' === $status && empty( $message ) ) {
			$message = $this->free_license_confirmation_message();
		}

		$payload = array(
			'status'        => $status,
			'message'       => $message,
			'recovery_type' => ! empty( $pending['recovery_type'] ) ? $pending['recovery_type'] : '',
			'settings_url'  => $integration && $target instanceof IC_AI_Target && $target->integration() === $integration ? $this->settings_url( $integration, $target ) : '',
		);
		if ( 'pending_confirmation' === $status ) {
			// Display-safe only: the checkout address is never sent to the browser.
			$payload['confirmation'] = $this->manager->client()->free_license_confirmation_state();
		}

		return $payload;
	}

	/**
	 * Returns the default free-license confirmation waiting copy.
	 *
	 * The checkout address is deliberately never rendered.
	 *
	 * @return string
	 */
	private function free_license_confirmation_message() {
		return __( 'A confirmation email was sent to the address you used at checkout. Confirm it to activate the Free AI plan.', 'post-type-x' );
	}

	/**
	 * Returns the local/private website warning shown before checkout.
	 *
	 * Presentation only: it never blocks, alters or validates the checkout.
	 *
	 * @return string Notice markup, or an empty string for a public website.
	 */
	private function local_site_warning_html() {
		if ( ! $this->is_local_or_private_site_url( rest_url( 'implecode/v1/ai/license-notify' ) ) ) {
			return '';
		}

		return '<div class="notice notice-warning inline ic-ai-local-site-warning"><p><strong>'
			. esc_html__( 'Automatic setup may not work on this website.', 'post-type-x' )
			. '</strong> '
			. esc_html__( 'This website appears to be local or private, so impleCode may not be able to connect to it automatically. You can continue. If setup does not finish, use the AI License Key sent by impleCode.', 'post-type-x' )
			. '</p></div>';
	}

	/**
	 * Returns whether a URL names a local or private website.
	 *
	 * Classification is purely lexical: the host is never resolved, so no DNS lookup
	 * can happen here. It is a display hint and must never relax URL validation or
	 * callback authentication.
	 *
	 * @param string $url Candidate URL.
	 *
	 * @return bool
	 */
	private function is_local_or_private_site_url( $url ) {
		$host = wp_parse_url( (string) $url, PHP_URL_HOST );
		if ( ! is_string( $host ) ) {
			return false;
		}
		$host = rtrim( strtolower( trim( $host, '[]' ) ), '.' );
		if ( '' === $host ) {
			return false;
		}
		if ( 'localhost' === $host ) {
			return true;
		}
		foreach ( array( '.localhost', '.ddev.site', '.test', '.local', '.invalid' ) as $suffix ) {
			if ( strlen( $host ) > strlen( $suffix ) && substr( $host, -strlen( $suffix ) ) === $suffix ) {
				return true;
			}
		}
		if ( false === filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return false;
		}

		return $this->is_local_or_private_ip( $host );
	}

	/**
	 * Returns whether a literal IP address is loopback, private, link-local or reserved.
	 *
	 * @param string $ip Literal IPv4 or IPv6 address.
	 *
	 * @return bool
	 */
	private function is_local_or_private_ip( $ip ) {
		$packed = inet_pton( $ip );
		if ( false === $packed ) {
			return false;
		}
		// IPv4-mapped IPv6 (::ffff:a.b.c.d) is classified by its embedded IPv4 address.
		if ( 16 === strlen( $packed ) && 0 === strncmp( $packed, str_repeat( "\0", 10 ) . "\xff\xff", 12 ) ) {
			$packed = substr( $packed, 12 );
		}
		$ranges = 4 === strlen( $packed )
			? array( '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/3' )
			: array( '::/128', '::1/128', '100::/64', '2001:db8::/32', 'fc00::/7', 'fe80::/10', 'ff00::/8' );
		foreach ( $ranges as $range ) {
			list( $subnet, $bits ) = explode( '/', $range );
			$subnet                = inet_pton( $subnet );
			$bits                  = (int) $bits;
			$bytes                 = intdiv( $bits, 8 );
			$remainder             = $bits % 8;
			if ( strlen( $subnet ) !== strlen( $packed ) || ( $bytes && 0 !== strncmp( $packed, $subnet, $bytes ) ) ) {
				continue;
			}
			if ( 0 === $remainder ) {
				return true;
			}
			$mask = ( 0xff << ( 8 - $remainder ) ) & 0xff;
			if ( ( ord( $packed[ $bytes ] ) & $mask ) === ( ord( $subnet[ $bytes ] ) & $mask ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Reconciles one pending renewal/upgrade state for an already registered client
	 * when the local callback did not complete but the remote account may already be updated.
	 *
	 * @param array $pending       Pending purchase payload.
	 * @param array $site_settings Current site settings.
	 *
	 * @return void
	 */
	private function maybe_reconcile_pending_registered_purchase( $pending, $site_settings ) {
		if ( empty( $pending['post_type'] ) || ! $this->manager->client()->has_registered_client( $site_settings ) ) {
			return;
		}

		$integration = $this->manager->integration( $pending['post_type'] );
		if ( ! $integration ) {
			return;
		}

		$registered = $this->manager->client()->register_site( $integration );
		if ( is_wp_error( $registered ) ) {
			return;
		}

		$refreshed_settings = $this->manager->client()->site_settings();
		if ( $this->pending_registered_purchase_resolved( $pending, $site_settings, $refreshed_settings ) ) {
			$purchase_type = $this->manager->client()->pending_purchase_type( $pending );
			$message       = 'enhancements' === $purchase_type
				? $this->enhancement_completion_applied_message( $refreshed_settings )
				: $this->notified_license_applied_message( $integration );
			$this->manager->client()->update_pending_purchase(
				array(
					'pending_status'        => 'ready',
					'pending_message'       => $message,
					'pending_recovery_type' => '',
				)
			);

			return;
		}

		$this->manager->client()->update_pending_purchase(
			array(
				'pending_status'          => 'pending',
				'pending_callback_secret' => $pending['callback_secret'],
				'pending_post_type'       => $pending['post_type'],
				'pending_source'          => $pending['source'],
				'pending_started_at'      => $pending['started_at'],
				'pending_message'         => $pending['message'],
				'pending_user_id'         => $pending['user_id'],
				'pending_plan_slug'       => $pending['plan_slug'],
				'pending_purchase_type'   => $pending['purchase_type'],
				'pending_item_slug'       => $pending['item_slug'],
				'pending_quantity'        => $pending['quantity'],
				'pending_recovery_type'   => '',
			)
		);
	}

	/**
	 * Returns whether a registered pending purchase was completed remotely.
	 *
	 * @param array $pending            Pending purchase payload.
	 * @param array $previous_settings  Settings before the remote refresh.
	 * @param array $refreshed_settings Settings after the remote refresh.
	 *
	 * @return bool
	 */
	private function pending_registered_purchase_resolved( $pending, $previous_settings, $refreshed_settings ) {
		if ( 'enhancements' === $this->manager->client()->pending_purchase_type( $pending ) ) {
			return $this->pending_enhancement_purchase_resolved( $previous_settings, $refreshed_settings );
		}

		$previous_plan_slug  = ! empty( $previous_settings['active_plan']['slug'] ) ? sanitize_key( $previous_settings['active_plan']['slug'] ) : '';
		$refreshed_plan_slug = ! empty( $refreshed_settings['active_plan']['slug'] ) ? sanitize_key( $refreshed_settings['active_plan']['slug'] ) : '';
		$pending_plan_slug   = ! empty( $pending['plan_slug'] ) ? sanitize_key( $pending['plan_slug'] ) : '';

		if ( ! empty( $pending_plan_slug ) && $pending_plan_slug === $refreshed_plan_slug && $previous_plan_slug !== $refreshed_plan_slug ) {
			return true;
		}

		$previous_cancelled  = ! empty( $previous_settings['subscription']['cancelled'] );
		$refreshed_cancelled = ! empty( $refreshed_settings['subscription']['cancelled'] );
		if ( $previous_cancelled && ! $refreshed_cancelled ) {
			return true;
		}

		$previous_expiry  = ! empty( $previous_settings['license']['expires_at'] ) ? absint( $previous_settings['license']['expires_at'] ) : 0;
		$refreshed_expiry = ! empty( $refreshed_settings['license']['expires_at'] ) ? absint( $refreshed_settings['license']['expires_at'] ) : 0;
		if ( $refreshed_expiry > $previous_expiry ) {
			return true;
		}

		$previous_cycle_end  = ! empty( $previous_settings['quota']['cycle_ends_at'] ) ? sanitize_text_field( $previous_settings['quota']['cycle_ends_at'] ) : '';
		$refreshed_cycle_end = ! empty( $refreshed_settings['quota']['cycle_ends_at'] ) ? sanitize_text_field( $refreshed_settings['quota']['cycle_ends_at'] ) : '';

		return ! empty( $refreshed_cycle_end ) && $previous_cycle_end !== $refreshed_cycle_end;
	}

	/**
	 * Returns whether a registered pending enhancement-pack purchase was completed remotely,
	 * based on the refreshed extra-enhancements quota. Resolves either when the refreshed
	 * `extra_enhancements_remaining` increased over the previous value, or when the quota
	 * transitioned out of a blocked state alongside an at-least-equal, positive extra balance
	 * (covers packs applied while quota was blocked).
	 *
	 * @param array $previous_settings  Settings before the remote refresh.
	 * @param array $refreshed_settings Settings after the remote refresh.
	 *
	 * @return bool
	 */
	private function pending_enhancement_purchase_resolved( $previous_settings, $refreshed_settings ) {
		$previous_extra  = isset( $previous_settings['quota']['extra_enhancements_remaining'] ) ? intval( $previous_settings['quota']['extra_enhancements_remaining'] ) : 0;
		$refreshed_extra = isset( $refreshed_settings['quota']['extra_enhancements_remaining'] ) ? intval( $refreshed_settings['quota']['extra_enhancements_remaining'] ) : 0;
		if ( $refreshed_extra > $previous_extra ) {
			return true;
		}

		$previous_status  = ! empty( $previous_settings['quota']['status'] ) ? sanitize_key( $previous_settings['quota']['status'] ) : '';
		$refreshed_status = ! empty( $refreshed_settings['quota']['status'] ) ? sanitize_key( $refreshed_settings['quota']['status'] ) : '';
		$quota_unblocked  = 'quota_blocked' === $previous_status && 'quota_blocked' !== $refreshed_status;

		return $quota_unblocked && $refreshed_extra > 0 && $refreshed_extra >= $previous_extra;
	}

	/**
	 * Handles the remote license notification callback.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 */
	public function rest_license_notify( $request ) {
		$pending = $this->manager->client()->pending_purchase();
		if ( empty( $pending['callback_secret'] ) ) {
			return new WP_REST_Response( array( 'message' => __( 'No pending AI activation was found.', 'post-type-x' ) ), 409 );
		}

		$body      = $request->get_body();
		$timestamp = (string) $request->get_header( 'x-ic-ai-timestamp' );
		$signature = (string) $request->get_header( 'x-ic-ai-signature' );
		if ( empty( $timestamp ) || abs( time() - absint( $timestamp ) ) > 300 ) {
			return new WP_REST_Response( array( 'message' => __( 'The AI activation notification is stale.', 'post-type-x' ) ), 403 );
		}
		$expected = hash_hmac( 'sha256', $timestamp . '.' . $body, $pending['callback_secret'] );
		if ( empty( $signature ) || ! hash_equals( $expected, $signature ) ) {
			return new WP_REST_Response( array( 'message' => __( 'The AI activation signature is invalid.', 'post-type-x' ) ), 403 );
		}
		// One accepted signature may only be used once inside the accepted skew window,
		// so a captured callback cannot be replayed to re-drive activation state.
		$replay_key = 'ic_ai_notify_seen_' . md5( $signature );
		if ( get_transient( $replay_key ) ) {
			return new WP_REST_Response( array( 'message' => __( 'The AI activation notification was already processed.', 'post-type-x' ) ), 409 );
		}
		set_transient( $replay_key, 1, 2 * 300 );

		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}
		$post_type   = ! empty( $payload['post_type'] ) ? sanitize_key( $payload['post_type'] ) : $pending['post_type'];
		$integration = $this->manager->integration( $post_type );
		if ( ! $integration ) {
			return new WP_REST_Response( array( 'message' => __( 'Unknown AI integration.', 'post-type-x' ) ), 400 );
		}
		$manual_recovery = ! empty( $payload['manual_recovery'] );
		$recovery_type   = ! empty( $payload['recovery_type'] ) ? sanitize_key( $payload['recovery_type'] ) : '';
		if ( ! empty( $payload['pending_confirmation'] ) ) {
			$message = ! empty( $payload['message'] ) ? sanitize_text_field( $payload['message'] ) : __( 'A confirmation email was sent. Confirm it before the Free AI plan can be activated.', 'post-type-x' );
			$this->manager->client()->update_pending_purchase(
				array(
					'pending_status'        => 'pending_confirmation',
					'pending_client_id'     => ! empty( $payload['client_id'] ) ? sanitize_text_field( $payload['client_id'] ) : '',
					'pending_plan_slug'     => ! empty( $payload['item_slug'] ) ? sanitize_key( $payload['item_slug'] ) : $pending['plan_slug'],
					'pending_message'       => $message,
					'pending_recovery_type' => '',
				)
			);
			// The keyless pending callback carries no key and never registers the site;
			// only the later signed key callback may do that.
			$this->manager->client()->store_free_license_confirmation_state( $payload );

			return rest_ensure_response(
				array(
					'status'  => 'pending_confirmation',
					'message' => $message,
				)
			);
		}
		if ( $manual_recovery && 'existing_license_key_required' === $recovery_type ) {
			$message = ! empty( $payload['message'] ) ? sanitize_text_field( $payload['message'] ) : $this->existing_license_key_manual_recovery_message();
			$this->manager->client()->update_pending_purchase(
				array(
					'pending_status'        => 'error',
					'pending_message'       => $message,
					'pending_recovery_type' => $recovery_type,
				)
			);

			return rest_ensure_response(
				array(
					'status'        => 'error',
					'message'       => $message,
					'recovery_type' => $recovery_type,
				)
			);
		}
		$license_key   = ! empty( $payload['license_key'] ) ? sanitize_text_field( $payload['license_key'] ) : '';
		$purchase_type = ! empty( $payload['purchase_type'] ) ? sanitize_key( $payload['purchase_type'] ) : '';
		if ( empty( $license_key ) && 'enhancements' === $purchase_type ) {
			return $this->handle_enhancement_completion_notification( $integration, $payload );
		}
		if ( empty( $license_key ) ) {
			$this->manager->client()->update_pending_purchase(
				array(
					'pending_status'        => 'error',
					'pending_message'       => $this->fallback_activation_message(),
					'pending_recovery_type' => '',
				)
			);

			return new WP_REST_Response( array( 'message' => __( 'Missing AI license key.', 'post-type-x' ) ), 400 );
		}

		$activated = $this->activate_notified_license( $integration, $license_key );
		if ( is_wp_error( $activated ) ) {
			$this->manager->client()->update_pending_purchase(
				array(
					'pending_status'        => 'error',
					'pending_message'       => $activated->get_error_message(),
					'pending_recovery_type' => '',
				)
			);

			return new WP_REST_Response( array( 'message' => $activated->get_error_message() ), 500 );
		}

		return rest_ensure_response(
			array(
				'status'  => 'ready',
				'message' => $activated,
			)
		);
	}

	/**
	 * Handles one enhancement-pack completion notification, which carries no `license_key`.
	 * Refreshes the registered client's remote state (including quota), marks the pending
	 * purchase ready, and clears any manual-recovery state.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 * @param array             $payload     Decoded notification payload.
	 *
	 * @return WP_REST_Response
	 */
	private function handle_enhancement_completion_notification( $integration, $payload ) {
		$refreshed = $this->manager->client()->refresh_plan_state();
		if ( is_wp_error( $refreshed ) ) {
			$this->manager->client()->update_pending_purchase(
				array(
					'pending_status'        => 'error',
					'pending_message'       => $refreshed->get_error_message(),
					'pending_recovery_type' => '',
				)
			);

			return new WP_REST_Response( array( 'message' => $refreshed->get_error_message() ), 500 );
		}

		$site_settings = $this->manager->client()->site_settings();
		$message       = ! empty( $payload['message'] ) ? sanitize_text_field( $payload['message'] ) : $this->enhancement_completion_applied_message( $site_settings );
		$this->store_notice( $integration->post_type(), 'success', $message );

		$this->manager->client()->update_pending_purchase(
			array(
				'pending_status'        => 'ready',
				'pending_message'       => $message,
				'pending_recovery_type' => '',
			)
		);

		return rest_ensure_response(
			array(
				'status'  => 'ready',
				'message' => $message,
			)
		);
	}

	/**
	 * Returns the integration targeted by one checkout proxy request.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return IC_AI_Integration|WP_Error
	 */
	private function rest_checkout_proxy_integration( $request ) {
		// The proxy URL carries its own nonce and post type in the query string
		// (checkout_proxy_url()), and the request body is an arbitrary forwarded
		// checkout payload. WP_REST_Request::get_param() merges the two with the body
		// winning, so both are read from the query parameters first: a forwarded body
		// key must never be able to shadow the proxy's own authorization inputs.
		$query = $request->get_query_params();
		$query = is_array( $query ) ? $query : array();

		$nonce = '';
		foreach ( array( 'ic_ai_nonce', 'nonce', '_wpnonce' ) as $nonce_key ) {
			$maybe_nonce = isset( $query[ $nonce_key ] ) ? $query[ $nonce_key ] : $request->get_param( $nonce_key );
			if ( ! empty( $maybe_nonce ) && is_scalar( $maybe_nonce ) ) {
				$nonce = sanitize_text_field( (string) $maybe_nonce );
				break;
			}
		}
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'ic-ai-settings' ) ) {
			return new WP_Error( 'ic_ai_checkout_proxy_invalid_nonce', __( 'The AI checkout request is no longer valid. Refresh the page and try again.', 'post-type-x' ), array( 'status' => 403 ) );
		}

		$raw_post_type = isset( $query['post_type'] ) ? $query['post_type'] : $request->get_param( 'post_type' );
		$post_type     = is_scalar( $raw_post_type ) ? sanitize_key( (string) $raw_post_type ) : '';
		$integration   = $this->manager->integration( $post_type );
		if ( ! $integration ) {
			return new WP_Error( 'ic_ai_checkout_proxy_unknown_integration', __( 'Unknown AI integration.', 'post-type-x' ), array( 'status' => 400 ) );
		}
		if ( ! current_user_can( $integration->settings_capability() ) ) {
			return new WP_Error( 'ic_ai_checkout_proxy_forbidden', __( 'You are not allowed to use the AI checkout proxy.', 'post-type-x' ), array( 'status' => 403 ) );
		}

		return $integration;
	}

	/**
	 * Returns the allowlisted embedded checkout AJAX actions.
	 *
	 * @return array
	 */
	private function checkout_proxy_actions() {
			return array(
				'ic_vat_verify',
				'shopping_cart_products',
				'ic_price_format',
				'ic_formbuilder_save_field',
				'ic_state_dropdown',
				'ic_address_lines',
				'ic_premium_state_dropdown',
				'ic_premium_address_lines',
				'ic_pay_ajax',
				'get_shopping_cart_product_price',
				'modify_variations_price',
				'get_viariation_details',
			);
	}

	/**
	 * Returns one normalized content type for a proxied checkout response.
	 *
	 * @param array $response Remote HTTP response.
	 *
	 * @return string
	 */
	private function checkout_proxy_content_type( $response ) {
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );

		return $content_type ? sanitize_text_field( $content_type ) : 'text/html; charset=' . get_option( 'blog_charset' );
	}

	/**
	 * Returns the full REST route path for the embedded checkout proxy.
	 *
	 * @return string
	 */
	private function checkout_proxy_rest_route() {
		return '/' . self::REST_NAMESPACE . self::CHECKOUT_AJAX_ROUTE;
	}

	/**
	 * Activates one integration from a notified license key.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 * @param string            $license_key License key.
	 *
	 * @return string|WP_Error
	 */
	private function activate_notified_license( $integration, $license_key ) {
		$site_settings = $this->manager->client()->update_site_settings(
			array(
				'license_key' => $license_key,
			)
		);
		$site_settings = $this->manager->client()->clear_saved_license_key_error_state( $site_settings );
		if ( $this->manager->client()->has_registered_client( $site_settings ) ) {
			$registered = $this->manager->client()->register_site( $integration );
			if ( is_wp_error( $registered ) ) {
				return $registered;
			}
		}
		$message = $this->notified_license_applied_message( $integration );
		$this->store_notice( $integration->post_type(), 'success', $message );

		$this->manager->client()->update_pending_purchase(
			array(
				'pending_status'        => 'ready',
				'pending_message'       => $message,
				'pending_recovery_type' => '',
			)
		);

		return $message;
	}

	/**
	 * Returns the message shown after an extra AI enhancements purchase has been applied.
	 *
	 * @param array $site_settings Current site settings, used to report the refreshed extra balance.
	 *
	 * @return string
	 */
	private function enhancement_completion_applied_message( $site_settings ) {
		$extra = isset( $site_settings['quota']['extra_enhancements_remaining'] ) ? intval( $site_settings['quota']['extra_enhancements_remaining'] ) : 0;

		return sprintf(
			/* translators: %s: formatted number of extra AI Credits now available. */
			__( 'Your extra AI Credits were added. Extra AI Credits available: %s.', 'post-type-x' ),
			number_format_i18n( $extra )
		);
	}

	/**
	 * Returns the post-checkout message shown after the AI license key is auto-applied.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 *
	 * @return string
	 */
	private function notified_license_applied_message( $integration ) {
		$site_settings = $this->manager->client()->site_settings();
		if ( $this->manager->client()->has_registered_client( $site_settings ) ) {
			if ( ! empty( $site_settings['active_plan']['name'] ) ) {
				return sprintf(
					/* translators: %s: active AI plan name. */
					__( 'The AI plan has been upgraded. Current plan: %s.', 'post-type-x' ),
					sanitize_text_field( $site_settings['active_plan']['name'] )
				);
			}

			return __( 'The AI plan has been upgraded.', 'post-type-x' );
		}

		$target             = $this->current_target_for_integration( $integration );
		$post_type_settings = $target instanceof IC_AI_Target ? $this->manager->client()->target_settings( $target ) : array();
		if ( ! empty( $post_type_settings['enabled'] ) ) {
			return __( 'The AI License Key has been filled in. Save AI settings to activate AI on this site.', 'post-type-x' );
		}

		return __( 'The AI License Key has been filled in. Check Enable AI and save AI settings to activate AI on this site.', 'post-type-x' );
	}

	/**
	 * Returns the manual fallback activation message.
	 *
	 * @return string
	 */
	private function fallback_activation_message() {
		return __( 'If the payment was successful, the AI License Key will arrive by email. Paste that key into these AI settings and save to activate the feature.', 'post-type-x' );
	}

	/**
	 * Returns the waiting-state message shown while an extra AI enhancements purchase is
	 * being applied to the account (no license key is involved in this flow).
	 *
	 * @return string
	 */
	private function enhancement_waiting_message() {
		return __( 'Applying your extra AI Credits. This will only take a moment...', 'post-type-x' );
	}

	/**
	 * Returns the manual fallback message for an extra AI enhancements purchase.
	 *
	 * @return string
	 */
	private function enhancement_fallback_activation_message() {
		return __( 'If the payment was successful, your extra AI Credits will be applied automatically. If this message persists, click Retry or contact support.', 'post-type-x' );
	}

	/**
	 * Returns whether the current page is the waiting-state return screen.
	 *
	 * @return bool
	 */
	private function is_waiting_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only waiting-state flag.
		return ! empty( $_GET['ic_ai_waiting'] );
	}

	/**
	 * Returns whether the current waiting screen should continue polling.
	 *
	 * @return bool
	 */
	private function should_poll_waiting_status() {
		if ( ! $this->is_waiting_request() ) {
			return false;
		}

		$status = $this->pending_status_payload();

		// A free plan additionally waits for the customer's email proof, and then for
		// the deferred activation, before the final signed key callback arrives.
		return ! empty( $status['status'] ) && in_array( $status['status'], array( 'pending', 'pending_confirmation', 'confirmed_pending_activation' ), true );
	}

	/**
	 * Returns whether the waiting panel should render on the current request.
	 *
	 * @param array $site_settings Site settings payload.
	 *
	 * @return bool
	 */
	private function should_render_waiting_panel( $site_settings ) {
		return $this->is_waiting_request() && ! $this->is_pending_manual_license_recovery( $site_settings );
	}

	/**
	 * Returns whether the pending purchase requires an existing-key manual recovery flow.
	 *
	 * @param array $site_settings Site settings payload.
	 * @param array $pending       Optional pending purchase payload.
	 *
	 * @return bool
	 */
	private function is_pending_manual_license_recovery( $site_settings, $pending = array() ) {
		if ( $this->manager->client()->has_registered_client( $site_settings ) || ! empty( $site_settings['license_key'] ) ) {
			return false;
		}
		$pending = ! empty( $pending ) && is_array( $pending ) ? $pending : $this->manager->client()->pending_purchase();

		return ! empty( $pending['status'] ) &&
			'error' === $pending['status'] &&
			! empty( $pending['recovery_type'] ) &&
			'existing_license_key_required' === $pending['recovery_type'];
	}

	/**
	 * Returns the default manual-recovery guidance for existing-license checkouts.
	 *
	 * @return string
	 */
	private function existing_license_key_manual_recovery_message() {
		return __( 'We could not retrieve your existing AI License Key automatically. Enter your existing key below, then save AI settings to activate AI on this site.', 'post-type-x' );
	}

	/**
	 * Returns the requested auto-open plan slug.
	 *
	 * @return string
	 */
	private function requested_auto_plan() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only onboarding routing.
		return isset( $_GET['ic_ai_open_checkout'] ) ? sanitize_key( wp_unslash( $_GET['ic_ai_open_checkout'] ) ) : '';
	}

	/**
	 * Returns the requested auto-open billing mode.
	 *
	 * @return string
	 */
	private function requested_auto_billing_mode() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only onboarding routing.
		$mode = isset( $_GET['ic_ai_open_billing'] ) ? sanitize_key( wp_unslash( $_GET['ic_ai_open_billing'] ) ) : '';

		return in_array( $mode, array( 'purchase', 'upgrade', 'enhancements' ), true ) ? $mode : '';
	}

	/**
	 * Returns whether the requested auto-open billing flow should use the public path.
	 *
	 * @return bool
	 */
	private function requested_auto_billing_public() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only onboarding routing.
		return ! empty( $_GET['ic_ai_billing_public'] );
	}

	/**
	 * Returns the requested onboarding source.
	 *
	 * @return string
	 */
	private function requested_source() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only onboarding routing.
		return isset( $_GET['ic_ai_source'] ) ? sanitize_key( wp_unslash( $_GET['ic_ai_source'] ) ) : 'settings';
	}

	/**
	 * Resolves the exact target selected by the current settings request.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 *
	 * @return IC_AI_Target|false
	 */
	private function current_target_for_integration( $integration ) {
		if ( ! $integration instanceof IC_AI_Integration ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		$key = isset( $_REQUEST['target_key'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['target_key'] ) ) : '';
		if ( '' !== $key ) {
			$target = $this->manager->target( $key );
			if ( $target instanceof IC_AI_Target && $target->integration() === $integration ) {
				return $target;
			}
			return false;
		}
		return $integration->target( 'post' );
	}

	/**
	 * Returns the currently active integration.
	 *
	 * @return IC_AI_Integration|false
	 */
	private function current_integration() {
		$current_screen = $this->current_screen_slug();
		$current_tab    = $this->current_tab_slug();

		foreach ( $this->manager->integrations() as $integration ) {
			if ( $this->screen_slug( $integration->post_type() ) === $current_screen ) {
				return $integration;
			}

			if ( $integration->has_host_settings_screen() && $integration->settings_screen() === $current_screen && $integration->settings_screen_tab() === $current_tab ) {
				return $integration;
			}
		}

		return false;
	}

	/**
	 * Returns an integration from the screen slug.
	 *
	 * @param string $screen_slug Screen slug.
	 *
	 * @return IC_AI_Integration|false
	 */
	private function integration_from_screen( $screen_slug ) {
		$screen_slug = sanitize_key( (string) $screen_slug );
		foreach ( $this->manager->integrations() as $integration ) {
			if ( $this->screen_slug( $integration->post_type() ) === $screen_slug ) {
				return $integration;
			}
		}

		return false;
	}

	/**
	 * Returns an integration matching the rendered settings page object.
	 *
	 * @param IC_Settings_Page $page Settings page object.
	 *
	 * @return IC_AI_Integration|false
	 */
	private function integration_from_page( $page ) {
		foreach ( $this->pages as $post_type => $registered_page ) {
			if ( $registered_page === $page ) {
				return $this->manager->integration( $post_type );
			}
		}

		return false;
	}

	/**
	 * Returns the current screen slug.
	 *
	 * @return string
	 */
	private function current_screen_slug() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		return isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
	}

	/**
	 * Returns the current screen tab slug.
	 *
	 * @return string
	 */
	private function current_tab_slug() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		return isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
	}

	/**
	 * Returns the screen slug for a post type.
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return string
	 */
	private function screen_slug( $post_type ) {
		return 'ic-ai-' . sanitize_key( (string) $post_type );
	}

	/**
	 * Returns the host screen slug for one integration.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 *
	 * @return string
	 */
	private function page_screen_slug( $integration ) {
		if ( $integration->has_host_settings_screen() ) {
			return $integration->settings_screen();
		}

		return $this->screen_slug( $integration->post_type() );
	}

	/**
	 * Returns the settings errors key for a post type.
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return string
	 */
	private function settings_error_key( $post_type ) {
		return 'ic_ai_settings_' . sanitize_key( (string) $post_type );
	}

	/**
	 * Returns the notice transient key for a post type.
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return string
	 */
	private function notice_key( $post_type ) {
		return 'ic_ai_notice_' . sanitize_key( (string) $post_type );
	}

	/**
	 * Returns the field-error transient key for a post type.
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return string
	 */
	private function field_error_key( $post_type ) {
		return 'ic_ai_field_error_' . sanitize_key( (string) $post_type );
	}

	/**
	 * Renders the current page-level notice once across standalone and host routes.
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return void
	 */
	private function render_notice( $post_type ) {
		$notice = $this->current_notice( $post_type );
		if ( empty( $notice['page_level'] ) || ! empty( $this->rendered_notices[ $post_type ] ) ) {
			return;
		}
		$this->current_notice( $post_type, true );
		$this->rendered_notices[ $post_type ] = true;

		$type = ! empty( $notice['type'] ) ? sanitize_html_class( $notice['type'] ) : 'info';
		?>
		<div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
		<?php
	}

	/**
	 * Returns one stored page notice and optionally consumes it.
	 *
	 * @param string $post_type Post type slug.
	 * @param bool   $consume   Whether to delete the stored notice after reading it.
	 *
	 * @return array
	 */
	private function current_notice( $post_type, $consume = false ) {
		$notice = get_transient( $this->notice_key( $post_type ) );
		if ( empty( $notice ) || ! is_array( $notice ) || empty( $notice['message'] ) ) {
			// Standalone screens build the page (and consume the flash transient)
			// once during `register_screens()`, then `render_screen_content()`
			// rebuilds it. Re-use the notice consumed earlier in this request so
			// post-save success/error feedback survives that rebuild.
			if ( isset( $this->consumed_notices[ $post_type ] ) ) {
				return $this->consumed_notices[ $post_type ];
			}

			return array();
		}
		$payload               = $this->notice_payload(
			! empty( $notice['type'] ) ? $notice['type'] : 'info',
			$notice['message']
		);
		$payload['page_level'] = ! empty( $notice['page_level'] );
		if ( $consume ) {
			delete_transient( $this->notice_key( $post_type ) );
			$this->consumed_notices[ $post_type ] = $payload;
		}

		return $payload;
	}

	/**
	 * Returns one normalized notice payload for settings sections.
	 *
	 * @param string $type    Notice type.
	 * @param string $message Notice message.
	 *
	 * @return array
	 */
	private function notice_payload( $type, $message ) {
		return array(
			'type'        => sanitize_key( (string) $type ),
			'message'     => sanitize_text_field( $message ),
			'paragraph'   => 0,
			'dismissible' => false,
		);
	}

	/**
	 * Stores one page notice for later rendering.
	 *
	 * @param string $post_type  Post type slug.
	 * @param string $type       Message type.
	 * @param string $message    Message text.
	 * @param bool   $page_level Whether to render outside the manual settings section.
	 *
	 * @return void
	 */
	private function store_notice( $post_type, $type, $message, $page_level = false ) {
		$notice_type = 'success' === $type ? 'updated' : $type;
		set_transient(
			$this->notice_key( $post_type ),
			array(
				'type'       => 'updated' === $notice_type ? 'success' : $notice_type,
				'message'    => $message,
				'page_level' => $page_level,
			),
			30
		);
	}

	/**
	 * Stores one field-level error for later rendering.
	 *
	 * @param string $post_type    Post type slug.
	 * @param array  $field_error  Field-error payload.
	 *
	 * @return void
	 */
	private function store_field_error( $post_type, $field_error ) {
		if ( empty( $field_error['field'] ) || empty( $field_error['message'] ) ) {
			return;
		}

		set_transient(
			$this->field_error_key( $post_type ),
			array(
				'field'        => sanitize_key( $field_error['field'] ),
				'message'      => sanitize_text_field( $field_error['message'] ),
				'code'         => ! empty( $field_error['code'] ) ? sanitize_key( $field_error['code'] ) : '',
				'action_url'   => ! empty( $field_error['action_url'] ) ? esc_url_raw( $field_error['action_url'] ) : '',
				'action_label' => ! empty( $field_error['action_label'] ) ? sanitize_text_field( $field_error['action_label'] ) : '',
			),
			30
		);
	}

	/**
	 * Returns and clears one field-level error for the current settings screen.
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return array
	 */
	private function consume_field_error( $post_type ) {
		$field_error = get_transient( $this->field_error_key( $post_type ) );
		if ( empty( $field_error ) || ! is_array( $field_error ) ) {
			return array();
		}

		delete_transient( $this->field_error_key( $post_type ) );

		return array(
			'field'        => ! empty( $field_error['field'] ) ? sanitize_key( $field_error['field'] ) : '',
			'message'      => ! empty( $field_error['message'] ) ? sanitize_text_field( $field_error['message'] ) : '',
			'code'         => ! empty( $field_error['code'] ) ? sanitize_key( $field_error['code'] ) : '',
			'action_url'   => ! empty( $field_error['action_url'] ) ? esc_url_raw( $field_error['action_url'] ) : '',
			'action_label' => ! empty( $field_error['action_label'] ) ? sanitize_text_field( $field_error['action_label'] ) : '',
		);
	}

	/**
	 * Clears one stored field-level error.
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return void
	 */
	private function clear_field_error( $post_type ) {
		delete_transient( $this->field_error_key( $post_type ) );
	}

	/**
	 * Returns the current field error for one settings page.
	 *
	 * @param string $post_type     Post type slug.
	 * @param array  $site_settings Current site settings payload.
	 *
	 * @return array
	 */
	private function current_field_error( $post_type, $site_settings ) {
		$field_error = $this->consume_field_error( $post_type );
		if ( ! empty( $field_error ) ) {
			return $field_error;
		}

		return $this->manager->client()->site_field_error( $site_settings );
	}

	/**
	 * Redirects back with a settings notice.
	 *
	 * @param string $post_type Post type slug.
	 * @param string $type      Message type.
	 * @param string $message   Message text.
	 *
	 * @return void
	 */
	private function redirect_with_notice( $post_type, $type, $message ) {
		$notice_type = 'success' === $type ? 'updated' : $type;
		add_settings_error( $this->settings_error_key( $post_type ), $this->settings_error_key( $post_type ) . '_' . $type, $message, $notice_type );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		$this->store_notice( $post_type, $type, $message );
		$integration = $this->manager->integration( $post_type );
		if ( ! $integration ) {
			wp_safe_redirect( admin_url( 'edit.php?post_type=' . $post_type ) );
			exit;
		}

		wp_safe_redirect( $this->settings_url( $integration ) );
		exit;
	}

	/**
	 * Returns the admin settings URL for one integration.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 * @param IC_AI_Target|null $target      Optional target.
	 *
	 * @return string
	 */
	public function settings_url( $integration, $target = null ) {
		if ( ! $integration->has_host_settings_screen() ) {
			$url = admin_url( 'edit.php?post_type=' . $integration->post_type() . '&page=' . $this->screen_slug( $integration->post_type() ) );
			return $target instanceof IC_AI_Target ? add_query_arg( 'target_key', $target->key(), $url ) : $url;
		}

		$query_args = array_merge(
			array(
				'page' => $integration->settings_screen(),
				'tab'  => $integration->settings_screen_tab(),
			),
			$integration->settings_screen_tab_query_args()
		);

		$url = $this->menu_parent_url( $integration->menu_parent(), $query_args );
		return $target instanceof IC_AI_Target ? add_query_arg( 'target_key', $target->key(), $url ) : $url;
	}

	/**
	 * Builds an admin URL from a submenu parent slug and query args.
	 *
	 * @param string $parent_slug Parent admin slug.
	 * @param array  $query_args Query args to append.
	 *
	 * @return string
	 */
	private function menu_parent_url( $parent_slug, $query_args ) {
		$parent_slug = (string) $parent_slug;
		$query_args  = is_array( $query_args ) ? $query_args : array();

		if ( false !== strpos( $parent_slug, '.php' ) ) {
			$path = $parent_slug;
			if ( false !== strpos( $path, '?' ) ) {
				list( $path, $query_string ) = explode( '?', $path, 2 );
				if ( ! empty( $query_string ) ) {
					parse_str( $query_string, $parent_query_args );
					if ( is_array( $parent_query_args ) ) {
						$query_args = array_merge( $parent_query_args, $query_args );
					}
				}
			}

			return add_query_arg( $query_args, admin_url( ltrim( $path, '/' ) ) );
		}

		return add_query_arg( $query_args, admin_url( 'admin.php?page=' . rawurlencode( $parent_slug ) ) );
	}
}
