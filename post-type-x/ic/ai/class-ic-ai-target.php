<?php
/**
 * Canonical AI target identity.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Immutable target identity and behavior for AI enhancements.
 */
class IC_AI_Target {
	/**
	 * Target kind.
	 *
	 * @var string
	 */
	private $kind;
	/**
	 * Owning post type.
	 *
	 * @var string
	 */
	private $post_type;
	/**
	 * Target taxonomy.
	 *
	 * @var string
	 */
	private $taxonomy;
	/**
	 * Owning integration.
	 *
	 * @var IC_AI_Integration|null
	 */
	private $integration;
	/**
	 * Target labels.
	 *
	 * @var array
	 */
	private $labels;
	/**
	 * Edit capability.
	 *
	 * @var string
	 */
	private $edit_capability;
	/**
	 * Whether list enhancement is enabled.
	 *
	 * @var bool
	 */
	private $list_enabled;
	/**
	 * Target field map.
	 *
	 * @var array
	 */
	private $field_map;
	/**
	 * Default field keys.
	 *
	 * @var array
	 */
	private $default_fields;
	/**
	 * Context callback.
	 *
	 * @var callable|null
	 */
	private $context_callback;

	/**
	 * Creates a target from its registration arguments.
	 *
	 * @param array                  $args        Target arguments.
	 * @param IC_AI_Integration|null $integration Owning integration.
	 */
	public function __construct( $args, $integration = null ) {
		$args                   = is_array( $args ) ? $args : array();
		$this->kind             = sanitize_key( $args['kind'] ?? 'post' );
		$this->post_type        = sanitize_key( $args['post_type'] ?? '' );
		$this->taxonomy         = sanitize_key( $args['taxonomy'] ?? '' );
		$this->integration      = $integration;
		$this->labels           = array(
			'label'          => (string) ( $args['label'] ?? '' ),
			'singular_label' => (string) ( $args['singular_label'] ?? '' ),
		);
		$this->edit_capability  = sanitize_key( (string) ( $args['edit_capability'] ?? '' ) );
		$this->list_enabled     = ! empty( $args['list_enhance_enabled'] );
		$this->field_map        = is_array( $args['field_map'] ?? null ) ? $args['field_map'] : array();
		$this->default_fields   = is_array( $args['default_fields'] ?? null ) ? array_values( array_map( 'sanitize_key', $args['default_fields'] ) ) : array();
		$this->context_callback = $args['context_callback'] ?? null;
		$this->field_map        = apply_filters( 'ic_ai_target_field_map', $this->field_map, $this, $integration );
	}

	/**
	 * Determines whether the target identity is valid.
	 *
	 * @return bool
	 */
	public function is_valid() {
		if ( ! in_array( $this->kind, array( 'post', 'taxonomy' ), true ) || '' === $this->post_type ) {
			return false; }
		return 'post' === $this->kind ? '' === $this->taxonomy : '' !== $this->taxonomy;
	}
	/**
	 * Returns the target kind.
	 *
	 * @return string
	 */
	public function kind() {
		return $this->kind; }
	/**
	 * Returns the owning post type.
	 *
	 * @return string
	 */
	public function post_type() {
		return $this->post_type; }
	/**
	 * Returns the target taxonomy.
	 *
	 * @return string
	 */
	public function taxonomy() {
		return $this->taxonomy; }
	/**
	 * Returns the owning integration.
	 *
	 * @return IC_AI_Integration|null
	 */
	public function integration() {
		return $this->integration; }
	/**
	 * Returns the owning plugin slug.
	 *
	 * @return string
	 */
	public function plugin_slug() {
		return $this->integration && method_exists( $this->integration, 'plugin_slug' ) ? $this->integration->plugin_slug() : ''; }
	/**
	 * Determines whether the host settings screen is enabled.
	 *
	 * @return bool
	 */
	public function has_host_settings_screen() {
		return $this->integration && method_exists( $this->integration, 'has_host_settings_screen' ) ? $this->integration->has_host_settings_screen() : false; }
	/**
	 * Returns the host settings screen.
	 *
	 * @return string
	 */
	public function settings_screen() {
		return $this->integration && method_exists( $this->integration, 'settings_screen' ) ? $this->integration->settings_screen() : ''; }
	/**
	 * Returns the host settings screen tab.
	 *
	 * @return string
	 */
	public function settings_screen_tab() {
		return $this->integration && method_exists( $this->integration, 'settings_screen_tab' ) ? $this->integration->settings_screen_tab() : ''; }
	/**
	 * Returns host settings screen tab query arguments.
	 *
	 * @return array
	 */
	public function settings_screen_tab_query_args() {
		return $this->integration && method_exists( $this->integration, 'settings_screen_tab_query_args' ) ? $this->integration->settings_screen_tab_query_args() : array(); }
	/**
	 * Returns the integration menu parent.
	 *
	 * @return string
	 */
	public function menu_parent() {
		return $this->integration && method_exists( $this->integration, 'menu_parent' ) ? $this->integration->menu_parent() : ''; }
	/**
	 * Resolves this target when its kind and taxonomy match.
	 *
	 * @param string $kind     Target kind.
	 * @param string $taxonomy Taxonomy name.
	 *
	 * @return IC_AI_Target|false
	 */
	public function target( $kind = 'post', $taxonomy = '' ) {
		return ( $kind === $this->kind && ( '' === $taxonomy || $taxonomy === $this->taxonomy ) ) ? $this : false; }
	/**
	 * Returns the target label.
	 *
	 * @return string
	 */
	public function label() {
		return $this->labels['label']; }
	/**
	 * Returns the singular target label.
	 *
	 * @return string
	 */
	public function singular_label() {
		return $this->labels['singular_label']; }
	/**
	 * Returns the target edit capability.
	 *
	 * @return string
	 */
	public function edit_capability() {
		return $this->edit_capability; }
	/**
	 * Determines whether list enhancement is enabled.
	 *
	 * @return bool
	 */
	public function list_enhance_enabled() {
		return $this->list_enabled; }
	/**
	 * Returns default field keys.
	 *
	 * @return array
	 */
	public function default_fields() {
		return $this->default_fields; }
	/**
	 * Returns the context callback.
	 *
	 * @return callable|null
	 */
	public function context_callback() {
		return $this->context_callback; }
	/**
	 * Returns the target field map.
	 *
	 * @return array
	 */
	public function field_map() {
		return $this->field_map;
	}
	/**
	 * Returns the stable target key.
	 *
	 * @return string
	 */
	public function key() {
		return 'post' === $this->kind ? 'post--' . $this->post_type : 'taxonomy--' . $this->post_type . '--' . $this->taxonomy; }
	/**
	 * Returns the canonical target identity.
	 *
	 * @return array
	 */
	public function identity() {
		$data = array(
			'kind'      => $this->kind,
			'post_type' => $this->post_type,
		);
		if ( 'taxonomy' === $this->kind ) {
			$data['taxonomy'] = $this->taxonomy; }
		return $data;
	}
	/**
	 * Returns the target as an array.
	 *
	 * @return array
	 */
	public function to_array() {
		return $this->identity(); }
}
