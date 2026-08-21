<?php
/**
 * Tracking helpers for plugin usage reporting and error check-ins.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Usage tracking.
 */
class IC_EPC_Tracking {

	/**
	 * The data sent to the impleCode endpoint.
	 *
	 * @var array
	 */
	private $data;

	/**
	 * Sets up tracking hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'schedule_send' ) );
		add_action( 'admin_init', array( $this, 'init' ) );
		$this->init_errors();
	}

	/**
	 * Registers runtime and admin hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'pre_update_option_ic_epc_allow_tracking', array( $this, 'check_for_settings_optin' ) );
		add_action( 'ic_epc_opt_into_tracking', array( $this, 'check_for_optin' ) );
		add_action( 'ic_epc_opt_out_of_tracking', array( $this, 'check_for_optout' ) );
		add_action( 'ic_catalog_admin_priority_notices', array( $this, 'admin_notice' ), -2 );
		add_action( 'ic_system_tools', array( $this, 'settings_optin' ) );
		add_filter( 'ic_epc_links', array( $this, 'confirm_deactivation' ) );
		add_action( 'wp_ajax_ic_submit_deactivation_reason', array( $this, 'submit_deactivation_reason' ) );
	}

	/**
	 * Registers error-reporting hooks.
	 *
	 * @return void
	 */
	public function init_errors() {
		add_filter( 'recovery_mode_email', array( $this, 'paused_plugin_report_email' ), 10, 2 );
		register_shutdown_function( array( $this, 'fatal' ) );
	}

	/**
	 * Checks whether tracking is allowed.
	 *
	 * @return bool
	 */
	private function tracking_allowed() {
		return (bool) get_option( 'ic_epc_allow_tracking', false );
	}

	/**
	 * Prepares tracking data.
	 *
	 * @return void
	 */
	private function setup_data() {
		$data = array();

		$data['php_version']    = phpversion();
		$data['plugin_name']    = defined( 'IC_CATALOG_PLUGIN_NAME' ) ? IC_CATALOG_PLUGIN_NAME : 'not set';
		$data['plugin_version'] = defined( 'IC_EPC_VERSION' ) ? IC_EPC_VERSION : 'not set';
		$data['wp_version']     = get_bloginfo( 'version' );
		$data['server']         = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '';
		$data['install_ver']    = (string) get_option( 'first_activation_version', 'not set' );
		$data['multisite']      = is_multisite();
		$data['url']            = home_url();

		// Retrieve current theme info.
		$theme_data = wp_get_theme();
		if ( $theme_data->exists() ) {
			$theme              = $theme_data->display( 'Name' ) . ' ' . $theme_data->display( 'Version' );
			$data['theme']      = $theme;
			$data['theme_name'] = $theme_data->display( 'Name' );
		} else {
			$data['theme']      = 'Incorrect theme data';
			$data['theme_name'] = 'Incorrect theme data';
		}

		$data['integration'] = $this->get_integration_type();

		// Retrieve current plugin information.
		if ( ! function_exists( 'get_plugins' ) ) {
			include ABSPATH . '/wp-admin/includes/plugin.php';
		}

		$plugins        = array_keys( get_plugins() );
		$active_plugins = get_option( 'active_plugins', array() );

		foreach ( $plugins as $key => $plugin ) {
			if ( in_array( $plugin, $active_plugins, true ) ) {
				// Remove active plugins from list so we can show active and inactive separately.
				unset( $plugins[ $key ] );
			}
		}

		$data['active_plugins']   = implode( ',', $active_plugins );
		$data['inactive_plugins'] = implode( ',', $plugins );
		$data['products']         = function_exists( 'ic_products_count' ) ? ic_products_count() : 'not set';

		if ( function_exists( 'get_catalog_names' ) ) {
			$names                 = get_catalog_names();
			$data['product_label'] = $names['singular'];
		} else {
			$data['product_label'] = 'not set';
		}

		$data['locale'] = ( $data['wp_version'] >= 4.7 ) ? get_user_locale() : get_locale();

		$this->data = $data;
	}

	/**
	 * Gets the integration type label.
	 *
	 * @return string
	 */
	public function get_integration_type() {
		if ( ! function_exists( 'get_integration_type' ) ) {
			return 'not set';
		}

		$integration_type = get_integration_type();
		if ( is_advanced_mode_forced() ) {
			$integration_type .= ' - forced';
			if ( ic_is_woo_template_available() ) {
				$integration_type .= ' - woo';
			} elseif ( is_theme_implecode_supported() ) {
				$integration_type .= ' - supported';
			} elseif ( is_integraton_file_active() ) {
				$integration_type .= ' - file';
			}
			if ( is_ic_shortcode_integration() ) {
				$integration_type .= ' - shortcode';
			}
		} elseif ( ! is_integration_mode_selected() ) {
			$integration_type .= ' - not selected';
		}

		return $integration_type;
	}

	/**
	 * Sends the usage check-in.
	 *
	 * @param bool $override            Whether to bypass the tracking opt-in check.
	 * @param bool $ignore_last_checkin Whether to bypass the last-send throttling check.
	 * @return bool|WP_Error
	 */
	public function send_checkin( $override = false, $ignore_last_checkin = false ) {
		$home_url = trailingslashit( home_url() );

		// Allows us to stop our own site from checking in, and a filter for our additional sites.
		if ( 'https://implecode.com/' === $home_url || apply_filters( 'ic_epc_disable_tracking_checkin', false ) ) {
			return false;
		}

		if ( ! $this->tracking_allowed() && ! $override ) {
			return false;
		}

		// Send a maximum of once per week.
		$last_send = $this->get_last_send();
		if ( is_numeric( $last_send ) && $last_send > strtotime( '-1 week' ) && ! $ignore_last_checkin ) {
			return false;
		}

		$this->setup_data();
		$action_name = 'checkin';
		if ( empty( $last_send ) ) {
			$action_name .= '-first';
		}

		$request = wp_remote_post(
			'https://check.implecode.com/?ic_epc_action=' . $action_name,
			array(
				'method'      => 'POST',
				'timeout'     => 20,
				'redirection' => 5,
				'httpversion' => '1.1',
				'blocking'    => true,
				'body'        => $this->data,
				'sslverify'   => false,
				'user-agent'  => 'IC_EPC/' . IC_EPC_VERSION . '; ' . get_bloginfo( 'url' ),
			)
		);

		if ( is_wp_error( $request ) ) {
			return $request;
		}

		update_option( 'ic_epc_tracking_last_send', time(), false );

		return true;
	}

	/**
	 * Handles opt-in changes from settings.
	 *
	 * @param mixed $input New option value.
	 * @return mixed
	 */
	public function check_for_settings_optin( $input ) {
		// Send an initial check-in on settings save.
		if ( ! empty( $input ) ) {
			$this->send_checkin( true );
		}

		return $input;
	}

	/**
	 * Renders the system-tools opt-in checkbox.
	 *
	 * @return void
	 */
	public function settings_optin() {
		$checked = $this->tracking_allowed() ? 1 : 0;
		implecode_settings_checkbox( __( 'Send anonymous statistics', 'post-type-x' ), 'epc_allow_tracking', $checked );
	}

	/**
	 * Handles explicit opt-in.
	 *
	 * @param array $data Action payload.
	 * @return void
	 */
	public function check_for_optin( $data ) {
		update_option( 'ic_epc_allow_tracking', 1, false );
		$this->send_checkin( true );
		update_option( 'ic_epc_tracking_notice', '1', false );
	}

	/**
	 * Handles explicit opt-out.
	 *
	 * @param array $data Action payload.
	 * @return void
	 */
	public function check_for_optout( $data ) {
		delete_option( 'ic_epc_allow_tracking' );
		update_option( 'ic_epc_tracking_notice', '1', false );
		wp_safe_redirect( remove_query_arg( 'ic_epc_action' ) );
		exit;
	}

	/**
	 * Gets the last usage check-in time.
	 *
	 * @return false|string
	 */
	private function get_last_send() {
		return get_option( 'ic_epc_tracking_last_send' );
	}

	/**
	 * Schedules the weekly check-in hook.
	 *
	 * @return void
	 */
	public function schedule_send() {
		// We send once a week while tracking is allowed to detect active sites.
		add_action( 'ic_epc_weekly_scheduled_events', array( $this, 'send_checkin' ) );
	}

	/**
	 * Displays the tracking opt-in admin notice.
	 *
	 * @return void
	 */
	public function admin_notice() {
		$hide_notice = get_option( 'ic_epc_tracking_notice' );

		if ( $hide_notice || get_option( 'ic_epc_allow_tracking', false ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if (
			false !== stristr( network_site_url( '/' ), 'dev' ) ||
			false !== stristr( network_site_url( '/' ), 'localhost' ) ||
			false !== stristr( network_site_url( '/' ), ':8888' )
		) {
			update_option( 'ic_epc_tracking_notice', '1', false );
			return;
		}

		$optin_url  = add_query_arg( 'ic_epc_action', 'opt_into_tracking' );
		$optout_url = add_query_arg( 'ic_epc_action', 'opt_out_of_tracking' );

		echo '<div class="updated"><p>';
		/* translators: %s: plugin name. */
		printf( esc_html__( 'Allow %s to track plugin usage? Opt-in to tracking to help in plugin development. No sensitive data is tracked.', 'post-type-x' ), esc_html( IC_CATALOG_PLUGIN_NAME ) );
		echo '&nbsp;<a href="' . esc_url( $optin_url ) . '" class="button-primary">' . esc_html__( 'Allow', 'post-type-x' ) . '</a>';
		echo '&nbsp;<a href="' . esc_url( $optout_url ) . '" class="button-secondary">' . esc_html__( 'Do not allow', 'post-type-x' ) . '</a>';
		echo '</p></div>';
	}

	/**
	 * Adds the deactivation confirmation UI to plugin links.
	 *
	 * @param array $links Plugin action links.
	 * @return array
	 */
	public function confirm_deactivation( $links ) {
		if ( isset( $links['settings'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Script string is assembled from trusted values in this method.
			$links['settings'] .= '<script>jQuery(document).ready(function() {
				var deactivate_link = jQuery("tr[data-slug=' . IC_CATALOG_PLUGIN_SLUG . '] span.deactivate a");
deactivate_link.click(function(e) {
	if (jQuery(this).data("prevented") === true) {
	jQuery(this).data("prevented", false);
        return;
}
	e.preventDefault();
	jQuery("div.ic_deactivate_confirm").show();
	jQuery(this).data("prevented", true);
});
jQuery(".ic_deactivate_bg, div.ic_deactivate_confirm a.button-secondary ").click(function(e) {
e.preventDefault();
jQuery("div.ic_deactivate_confirm").hide();
deactivate_link.data("prevented", false);
});
jQuery("div.ic_deactivate_confirm a.deactivate").click(function(e) {
e.preventDefault();
jQuery("div.ic_deactivate_confirm").hide();
window.location = deactivate_link.attr("href");
});
jQuery("div.ic_deactivate_confirm .button-primary").click(function(e) {
e.preventDefault();
var selected_reason = jQuery("input[name=deactivation-reason]:checked");
if (selected_reason.length) {
jQuery(".ic_deactivate_box .warning").hide();
jQuery(this).attr("disabled", true);
jQuery(this).text("' . esc_js( __( 'Processing...', 'post-type-x' ) ) . '");
var reason = selected_reason.val();
var reason_desc = selected_reason.parent("p").next("p").find("textarea").val();
 var data = {
            "action": "ic_submit_deactivation_reason",
			"reason": reason,
			"reason_desc": reason_desc,
			"nonce": "' . esc_js( wp_create_nonce( 'ic-ajax-nonce' ) ) . '"
        };
        jQuery.post( ajaxurl, data, function ( response ) {
            window.location = deactivate_link.attr("href");
        } );

} else {
jQuery(".ic_deactivate_box .warning").show();
}
});
jQuery("input[name=deactivation-reason]").click(function() {
jQuery(".ic_deactivate_box textarea").hide();
jQuery(this).parent("p").next("p").find("textarea").show();
});
});</script>';
			$links['settings'] .= $this->confirm_deactivation_box();
		}

		return $links;
	}

	/**
	 * Builds the deactivation confirmation markup.
	 *
	 * @return string
	 */
	public function confirm_deactivation_box() {
		$box  = '<div class="ic_deactivate_confirm">';
		$box .= '<div class="ic_deactivate_bg"></div>';
		$box .= '<div class="ic_deactivate_box">';
		$box .= '<div class="ic_deactivate_question">';
		$box .= '<h3>' . esc_html__( 'If you have a moment, please let us know why you are deactivating', 'post-type-x' ) . ':</h3>';
		foreach ( $this->confirm_deactivation_options() as $key => $field ) {
			$box .= '<p><input type="radio" id="' . esc_attr( $key ) . '" name="deactivation-reason" value="' . esc_attr( $key ) . '"> <label for="' . esc_attr( $key ) . '">' . esc_html( $field['label'] ) . '</label></p>';
			$box .= '<p><textarea name="deactivation-reason-desc" placeholder="' . esc_attr( $field['placeholder'] ) . '"></textarea></p>';
		}
		$box .= '</div>';
		$box .= '<div class="ic_deactivate_buttons">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is generated by implecode_warning().
		$box .= implecode_warning( __( 'Please choose a reason...', 'post-type-x' ), 0 );
		$box .= '&nbsp;<a href="" class="submit_deactivate button-primary">' . esc_html__( 'Submit & Deactivate', 'post-type-x' ) . '</a>';
		$box .= '&nbsp;&nbsp;&nbsp;<a href="" class="button-secondary">' . esc_html__( 'Cancel', 'post-type-x' ) . '</a>';
		$box .= '&nbsp;&nbsp;&nbsp;<a href="" class="deactivate">' . esc_html__( 'Skip', 'post-type-x' ) . '</a>';
		$box .= '</div>';
		$box .= '</div>';
		$box .= '</div>';

		return $box;
	}

	/**
	 * Gets deactivation feedback options.
	 *
	 * @return array
	 */
	public function confirm_deactivation_options() {
		return array(
			'dont-understand'        => array(
				'label'       => __( "I couldn't understand how to make it work", 'post-type-x' ),
				'placeholder' => __( 'What could we do better?', 'post-type-x' ),
			),
			'better-plugin'          => array(
				'label'       => __( 'I found a better plugin', 'post-type-x' ),
				'placeholder' => __( "What's the plugin's name?", 'post-type-x' ),
			),
			'missing-feature'        => array(
				'label'       => __( "The plugin is excellent, but I need a specific feature that you don't support", 'post-type-x' ),
				'placeholder' => __( 'What feature?', 'post-type-x' ),
			),
			'not-working'            => array(
				'label'       => __( 'The plugin is not working', 'post-type-x' ),
				'placeholder' => __( "Kindly share what didn't work so we can fix it for future users...", 'post-type-x' ),
			),
			'looking-something-else' => array(
				'label'       => __( "It's not what I was looking for", 'post-type-x' ),
				'placeholder' => __( "What you've been looking for?", 'post-type-x' ),
			),
			'didnt-work'             => array(
				'label'       => __( "The plugin didn't work as expected", 'post-type-x' ),
				'placeholder' => __( 'What did you expect?', 'post-type-x' ),
			),
			'other'                  => array(
				'label'       => __( 'Other', 'post-type-x' ),
				'placeholder' => __( 'What is the reason?', 'post-type-x' ),
			),
		);
	}

	/**
	 * Sends the deactivation reason.
	 *
	 * @return void
	 */
	public function submit_deactivation_reason() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		$reason = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';

		// The 'ic-ajax-nonce' is minted for every front-end visitor, so it cannot act as an
		// authorization control. Deactivating a plugin is an 'activate_plugins' action, so the
		// deactivation survey must not be submittable by lower roles.
		if ( ! empty( $nonce ) && wp_verify_nonce( $nonce, 'ic-ajax-nonce' ) && ! empty( $reason ) && current_user_can( 'activate_plugins' ) ) {
			$this->setup_data();
			$data                        = $this->data;
			$data['deactivation_reason'] = $reason;

			if ( ! empty( $_POST['reason_desc'] ) ) {
				$data['deactivation_reason_desc'] = sanitize_textarea_field( wp_unslash( $_POST['reason_desc'] ) );
			}

			wp_remote_post(
				'https://check.implecode.com/?ic_epc_action=deactivation',
				array(
					'method'      => 'POST',
					'timeout'     => 20,
					'redirection' => 5,
					'httpversion' => '1.1',
					'blocking'    => true,
					'body'        => $data,
					'sslverify'   => false,
					'user-agent'  => 'IC_EPC/' . IC_EPC_VERSION . '; ' . get_bloginfo( 'url' ),
				)
			);
		}

		wp_die();
	}

	/**
	 * Reports recovery-mode emails related to this plugin.
	 *
	 * @param array  $email Email payload.
	 * @param string $url   Recovery URL.
	 * @return array
	 */
	public function paused_plugin_report_email( $email, $url ) {
		if ( ! empty( $email['message'] ) && ( ic_string_contains( $email['message'], 'eCommerce Product Catalog' ) || ic_string_contains( $email['message'], 'ecommerce-product-catalog' ) ) ) {
			$this->send_paused_checkin( array( $email['message'] ) );
		}

		return $email;
	}

	/**
	 * Reports supported fatals.
	 *
	 * @return void
	 */
	public function fatal() {
		$error = error_get_last();
		if ( ! empty( $error ) && ! empty( $error['file'] ) ) {
			if ( $this->supported_slug( $error['file'] ) ) {
				$message = '';
				if ( ! empty( $error['type'] ) ) {
					$message .= '[' . $error['type'] . '] ';
				}
				if ( ! empty( $error['message'] ) ) {
					$message .= str_replace( untrailingslashit( plugin_dir_path( AL_BASE_PATH ) ), '', $error['message'] ) . ' ';
				}
				$message .= 'in file ' . str_replace( untrailingslashit( plugin_dir_path( AL_BASE_PATH ) ), '', $error['file'] ) . ' ';

				if ( ! empty( $error['line'] ) ) {
					$message .= 'on line ' . $error['line'] . ' ';
				}
				if ( defined( 'IC_CATALOG_VERSION' ) ) {
					$message .= '[' . IC_CATALOG_VERSION . ']';
				}

				/** Fully anonymized error report. */
				$this->send_paused_checkin( array( $message ), true );
			}
		}
	}

	/**
	 * Checks whether the file belongs to a supported plugin slug.
	 *
	 * @param string $file File path.
	 * @return bool
	 */
	public function supported_slug( $file ) {
		if ( empty( $file ) || ! is_string( $file ) ) {
			return false;
		}
		if ( defined( 'AL_BASE_PATH' ) && false !== strpos( $file, AL_BASE_PATH ) ) {
			return true;
		}

		$slugs = $this->slugs();
		foreach ( $slugs as $slug ) {
			if ( false !== strpos( $file, $slug ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Gets impleCode plugin slugs from installed plugins.
	 *
	 * @return array
	 */
	public function slugs() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();
		if ( empty( $all_plugins ) ) {
			return array();
		}

		$slugs = array();
		foreach ( $all_plugins as $name => $plugin ) {
			if ( 'impleCode' !== $plugin['Author'] ) {
				continue;
			}
			$name = explode( '/', $name );
			if ( ! in_array( $name[0], $slugs, true ) ) {
				$slugs[] = $name[0];
			}
		}

		return $slugs;
	}

	/**
	 * Sends the fatal-error check-in.
	 *
	 * @param array $errors              Error messages.
	 * @param bool  $override            Whether to bypass the tracking opt-in check.
	 * @param bool  $ignore_last_checkin Whether to bypass the last-send throttling check.
	 * @return bool|WP_Error
	 */
	public function send_paused_checkin( $errors, $override = false, $ignore_last_checkin = false ) {
		$home_url = trailingslashit( home_url() );

		// Allows us to stop our own site from checking in, and a filter for our additional sites.
		if ( 'https://implecode.com/' === $home_url || apply_filters( 'ic_epc_disable_tracking_checkin', false ) ) {
			return false;
		}

		if ( ! $this->tracking_allowed() && ! $override ) {
			return false;
		}

		// Send a maximum of once per day.
		$last_send = $this->get_last_paused_send();
		if ( is_numeric( $last_send ) && $last_send > strtotime( '-1 day' ) && ! $ignore_last_checkin ) {
			return false;
		}

		$this->setup_data();
		$anonymize            = array( WP_PLUGIN_DIR, untrailingslashit( plugin_dir_path( AL_BASE_PATH ) ) );
		$data_errors          = wp_json_encode( $errors );
		$this->data['errors'] = str_replace( $anonymize, '', $data_errors );
		if ( ! $this->tracking_allowed() ) {
			/** Anonymize if forced check-in. */
			$this->data['url'] = 'not_provided';
		}

		$action_name = 'error';
		if ( empty( $last_send ) ) {
			$action_name .= '-first';
		}

		$request = wp_remote_post(
			'https://errors.implecode.com/?ic_epc_action=' . $action_name,
			array(
				'method'      => 'POST',
				'timeout'     => 20,
				'redirection' => 5,
				'httpversion' => '1.1',
				'blocking'    => true,
				'body'        => $this->data,
				'sslverify'   => false,
				'user-agent'  => 'IC_EPC/' . IC_EPC_VERSION . '; ',
			)
		);

		if ( is_wp_error( $request ) ) {
			return $request;
		}

		update_option( 'ic_epc_tracking_last_paused_send', time(), false );

		return true;
	}

	/**
	 * Gets the last paused-error check-in time.
	 *
	 * @return false|string
	 */
	private function get_last_paused_send() {
		return get_option( 'ic_epc_tracking_last_paused_send' );
	}
}
