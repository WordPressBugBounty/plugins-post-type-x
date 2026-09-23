<?php
/**
 * AI post type integration value object.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Describes one AI-enabled post type integration.
 */
class IC_AI_Integration {
	/**
	 * Integration arguments.
	 *
	 * @var array
	 */
	private $args = array();
	/**
	 * Registered target objects.
	 *
	 * @var IC_AI_Target[]
	 */
	private $target_objects = array();

	/**
	 * Constructor.
	 *
	 * @param array $args Integration arguments.
	 */
	public function __construct( $args ) {
		$defaults   = array(
			'plugin_slug'             => '',
			'post_type'               => '',
			'label'                   => '',
			'menu_parent'             => '',
			'settings_screen'         => '',
			'settings_screen_tab'     => '',
			'settings_tab_label'      => '',
			'settings_tab_id'         => '',
			'settings_tab_query_args' => array(),
			'settings_capability'     => 'manage_options',
			'edit_capability'         => 'edit_posts',
			'targets'                 => array(),
			'show_settings_page'      => true,
		);
		$this->args = wp_parse_args( is_array( $args ) ? $args : array(), $defaults );
		if ( empty( $this->args['label'] ) ) {
			$object              = get_post_type_object( $this->post_type() );
			$this->args['label'] = ! empty( $object->labels->singular_name ) ? $object->labels->singular_name : $this->post_type();
		}
		if ( empty( $this->args['menu_parent'] ) ) {
			$this->args['menu_parent'] = 'edit.php?post_type=' . $this->post_type();
		}
		$post                         = is_array( $this->args['targets']['post'] ?? null ) ? $this->args['targets']['post'] : array();
		$post                         = wp_parse_args(
			$post,
			array(
				'edit_capability'      => (string) ( $this->args['edit_capability'] ?? 'edit_posts' ),
				'list_enhance_enabled' => ! empty( $this->args['list_enhance_enabled'] ),
			)
		);
		$this->target_objects['post'] = new IC_AI_Target(
			array_merge(
				array(
					'kind'      => 'post',
					'post_type' => $this->post_type(),
				),
				$post
			),
			$this
		);
		foreach ( (array) ( $this->args['targets']['taxonomies'] ?? array() ) as $taxonomy => $config ) {
			if ( is_string( $config ) ) {
				$config = array(); }
			$config['taxonomy'] = $config['taxonomy'] ?? $taxonomy;
			$config             = wp_parse_args(
				$config,
				array(
					'edit_capability'      => (string) ( $this->args['edit_capability'] ?? 'manage_categories' ),
					'list_enhance_enabled' => ! empty( $this->args['list_enhance_enabled'] ),
				)
			);
			$this->target_objects[ 'taxonomy:' . sanitize_key( (string) $config['taxonomy'] ) ] = new IC_AI_Target(
				array_merge(
					array(
						'kind'      => 'taxonomy',
						'post_type' => $this->post_type(),
					),
					$config
				),
				$this
			);
		}
	}

	/**
	 * Returns whether the integration is valid.
	 *
	 * @return bool
	 */
	public function is_valid() {
		return ! empty( $this->args['plugin_slug'] ) && ! empty( $this->args['post_type'] ) && isset( $this->target_objects['post'] ) && $this->target_objects['post']->is_valid();
	}

	/**
	 * Returns the raw target registration configuration.
	 *
	 * @return array
	 */
	public function targets() {
		return is_array( $this->args['targets'] ) ? $this->args['targets'] : array();
	}

	/**
	 * Returns constructed taxonomy target objects in registration order.
	 *
	 * @return IC_AI_Target[]
	 */
	public function taxonomy_targets() {
		$targets = array();
		foreach ( $this->target_objects as $target ) {
			if ( $target instanceof IC_AI_Target && 'taxonomy' === $target->kind() ) {
				$targets[ $target->taxonomy() ] = $target;
			}
		}
		return $targets;
	}

	/**
	 * Resolves a registered target by kind and taxonomy.
	 *
	 * @param string $kind     Target kind.
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return IC_AI_Target|false
	 */
	public function target( $kind = 'post', $taxonomy = '' ) {
		$kind = sanitize_key( (string) $kind );
		if ( 'taxonomy' === $kind ) {
			$key = 'taxonomy:' . sanitize_key( $taxonomy );
			return $this->target_objects[ $key ] ?? false;
		}
		return $this->target_objects['post'] ?? false;
	}

	/**
	 * Returns the plugin slug.
	 *
	 * @return string
	 */
	public function plugin_slug() {
		return sanitize_key( (string) $this->args['plugin_slug'] );
	}

	/**
	 * Returns the post type slug.
	 *
	 * @return string
	 */
	public function post_type() {
		return sanitize_key( (string) $this->args['post_type'] );
	}

	/**
	 * Returns the human label.
	 *
	 * @return string
	 */
	public function label() {
		return (string) $this->args['label'];
	}

	/**
	 * Returns the parent menu slug.
	 *
	 * @return string
	 */
	public function menu_parent() {
		return (string) $this->args['menu_parent'];
	}

	/**
	 * Returns whether shared AI list enhance is enabled for this integration.
	 *
	 * @return bool
	 */

	/**
	 * Returns the host settings screen slug.
	 *
	 * @return string
	 */
	public function settings_screen() {
		return (string) $this->args['settings_screen'];
	}

	/**
	 * Returns the host settings tab key.
	 *
	 * @return string
	 */
	public function settings_screen_tab() {
		return sanitize_key( (string) $this->args['settings_screen_tab'] );
	}

	/**
	 * Returns the host settings tab label.
	 *
	 * @return string
	 */
	public function settings_screen_tab_label() {
		if ( ! empty( $this->args['settings_tab_label'] ) ) {
			return (string) $this->args['settings_tab_label'];
		}

		return __( 'AI', 'post-type-x' );
	}

	/**
	 * Returns the host settings tab HTML ID.
	 *
	 * @return string
	 */
	public function settings_screen_tab_id() {
		if ( ! empty( $this->args['settings_tab_id'] ) ) {
			return sanitize_html_class( $this->args['settings_tab_id'] );
		}

		return sanitize_html_class( $this->settings_screen_tab() );
	}

	/**
	 * Returns additional host settings tab query args.
	 *
	 * @return array
	 */
	public function settings_screen_tab_query_args() {
		return is_array( $this->args['settings_tab_query_args'] ) ? $this->args['settings_tab_query_args'] : array();
	}

	/**
	 * Returns true when the integration is mounted inside another settings screen.
	 *
	 * @return bool
	 */
	public function has_host_settings_screen() {
		return '' !== $this->settings_screen() && '' !== $this->settings_screen_tab();
	}

	/**
	 * Returns the settings capability.
	 *
	 * @return string
	 */
	public function settings_capability() {
		return (string) $this->args['settings_capability'];
	}

	/**
	 * Returns the edit capability.
	 *
	 * @return string
	 */

	/**
	 * Returns whether this integration should expose its own settings page.
	 *
	 * @return bool
	 */
	public function show_settings_page() {
		return ! empty( $this->args['show_settings_page'] );
	}

	/**
	 * Returns the full field map.
	 *
	 * @return array
	 */
	/**
	 * Returns one field definition.
	 *
	 * @param string $field_key Field key.
	 *
	 * @return array
	 */
	/**
	 * Returns the default-enabled field keys.
	 *
	 * @return array
	 */
}
