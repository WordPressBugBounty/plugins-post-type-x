<?php
/**
 * AI manager singleton.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers AI integrations and shared services.
 */
class IC_AI_Manager {
	/**
	 * Singleton instance.
	 *
	 * @var IC_AI_Manager|null
	 */
	private static $instance = null;

	/**
	 * Registered integrations.
	 *
	 * @var IC_AI_Integration[]
	 */
	private $integrations = array();
	/**
	 * Registered targets.
	 *
	 * @var IC_AI_Target[]
	 */
	private $targets = array();

	/**
	 * Client service.
	 *
	 * @var IC_AI_Client
	 */
	private $client;

	/**
	 * Settings service.
	 *
	 * @var IC_AI_Settings
	 */
	private $settings;

	/**
	 * Admin service.
	 *
	 * @var IC_AI_Admin
	 */
	private $admin;

	/**
	 * Returns the singleton instance.
	 *
	 * @return IC_AI_Manager
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->client   = new IC_AI_Client();
		$this->settings = new IC_AI_Settings( $this );
		$this->admin    = new IC_AI_Admin( $this );
	}

	/**
	 * Registers one integration.
	 *
	 * @param array|IC_AI_Integration $integration Integration config.
	 *
	 * @return IC_AI_Integration|false
	 */
	public function register_integration( $integration ) {
		if ( ! $integration instanceof IC_AI_Integration ) {
			$integration = new IC_AI_Integration( $integration );
		}
		if ( ! $integration->is_valid() ) {
			return false;
		}
		$post_target = $integration->target( 'post' );
		if ( ! $post_target instanceof IC_AI_Target || ! $post_target->is_valid() ) {
			return false;
		}
		if ( isset( $this->integrations[ $integration->post_type() ] ) ) {
			$old = $this->integrations[ $integration->post_type() ];
			foreach ( $this->targets as $key => $registered_target ) {
				if ( $registered_target instanceof IC_AI_Target && $registered_target->integration() === $old ) {
					unset( $this->targets[ $key ] );
				}
			}
		}
		$taxonomy_targets    = $integration->taxonomy_targets();
		$declared_taxonomies = $integration->targets();
		$declared_taxonomies = isset( $declared_taxonomies['taxonomies'] ) && is_array( $declared_taxonomies['taxonomies'] ) ? $declared_taxonomies['taxonomies'] : array();
		if ( count( $declared_taxonomies ) !== count( $taxonomy_targets ) ) {
			return false;
		}
		foreach ( $taxonomy_targets as $taxonomy_target ) {
			if ( ! $taxonomy_target instanceof IC_AI_Target || ! $taxonomy_target->is_valid() ) {
				return false; }
		}

		$this->integrations[ $integration->post_type() ] = $integration;
		$this->targets[ $post_target->key() ]            = $post_target;
		foreach ( $taxonomy_targets as $target ) {
			$this->targets[ $target->key() ] = $target;
		}

		return $integration;
	}

	/**
	 * Registers targets through the integration registration contract.
	 *
	 * @param array $registration Integration registration.
	 *
	 * @return IC_AI_Integration|false
	 */
	public function register_targets( $registration ) {
		if ( ! is_array( $registration ) ) {
			return false; }
		return $this->register_integration( $registration );
	}

	/**
	 * Returns a target by its stable key.
	 *
	 * @param string $key Target key.
	 *
	 * @return IC_AI_Target|false
	 */
	public function target( $key ) {
		return isset( $this->targets[ (string) $key ] ) ? $this->targets[ (string) $key ] : false;
	}

	/**
	 * Returns all registered targets.
	 *
	 * @return IC_AI_Target[]
	 */
	public function targets() {
		return $this->targets; }

	/**
	 * Returns all integrations.
	 *
	 * @return array
	 */
	public function integrations() {
		return $this->integrations;
	}

	/**
	 * Returns one integration.
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return IC_AI_Integration|false
	 */
	public function integration( $post_type ) {
		$post_type = sanitize_key( (string) $post_type );

		return isset( $this->integrations[ $post_type ] ) ? $this->integrations[ $post_type ] : false;
	}

	/**
	 * Returns the client service.
	 *
	 * @return IC_AI_Client
	 */
	public function client() {
		return $this->client;
	}

	/**
	 * Returns the settings service.
	 *
	 * @return IC_AI_Settings
	 */
	public function settings() {
		return $this->settings;
	}

	/**
	 * Returns the admin service.
	 *
	 * @return IC_AI_Admin
	 */
	public function admin() {
		return $this->admin;
	}

	/**
	 * Revokes the site key during uninstall/remove flows.
	 *
	 * @param string $reason Revoke reason.
	 *
	 * @return array|WP_Error
	 */
	public function revoke_site_key( $reason = 'plugin_uninstall' ) {
		return $this->client->revoke_key( $reason );
	}
}
