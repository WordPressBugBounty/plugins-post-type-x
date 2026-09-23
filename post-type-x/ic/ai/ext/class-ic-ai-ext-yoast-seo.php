<?php
/**
 * Yoast target adapter for shared AI.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridges AI target fields to Yoast SEO metadata.
 */
class IC_AI_Ext_Yoast_SEO {
	const META_PREFIX = '_yoast_wpseo_';

	/** Registers the target field-map filter. */
	public function __construct() {
		add_filter( 'ic_ai_target_field_map', array( $this, 'filter_target_field_map' ), 20, 3 );
		add_filter( 'ic_ai_target_field_storage_read', array( $this, 'storage_read' ), 20, 6 );
		add_filter( 'ic_ai_target_field_storage_write', array( $this, 'storage_write' ), 20, 7 );
	}

	/**
	 * Returns available Yoast fields for a target.
	 *
	 * @param IC_AI_Target $target Target object.
	 *
	 * @return array
	 */
	public function fields( $target ) {
		if ( ! $this->available( $target ) ) {
			return array(); }
		$fields = array(
			'_yoast_wpseo_title'                 => array(
				'label'   => __( 'SEO title', 'post-type-x' ),
				'default' => true,
			),
			'_yoast_wpseo_metadesc'              => array(
				'label'   => __( 'Meta description', 'post-type-x' ),
				'default' => true,
			),
			'_yoast_wpseo_focuskw'               => array(
				'label'   => __( 'Focus keyphrase', 'post-type-x' ),
				'default' => true,
			),
			'_yoast_wpseo_opengraph-title'       => array(
				'label'   => __( 'Facebook title', 'post-type-x' ),
				'default' => false,
			),
			'_yoast_wpseo_opengraph-description' => array(
				'label'   => __( 'Facebook description', 'post-type-x' ),
				'default' => false,
			),
			'_yoast_wpseo_twitter-title'         => array(
				'label'   => __( 'X title', 'post-type-x' ),
				'default' => false,
			),
			'_yoast_wpseo_twitter-description'   => array(
				'label'   => __( 'X description', 'post-type-x' ),
				'default' => false,
			),
		);
		if ( ! $this->social_enabled( 'opengraph' ) ) {
			unset( $fields['_yoast_wpseo_opengraph-title'], $fields['_yoast_wpseo_opengraph-description'] ); }
		if ( ! $this->social_enabled( 'twitter' ) ) {
			unset( $fields['_yoast_wpseo_twitter-title'], $fields['_yoast_wpseo_twitter-description'] ); }
		return $fields;
	}

	/**
	 * Returns default-enabled Yoast fields.
	 *
	 * @param IC_AI_Target $target Target object.
	 *
	 * @return array
	 */
	public function default_fields( $target ) {
		return array_keys(
			array_filter(
				$this->fields( $target ),
				static function ( $field ) {
					return ! empty( $field['default'] );
				}
			)
		);
	}

	/**
	 * Returns configuration for one Yoast field.
	 *
	 * @param IC_AI_Target $target    Target object.
	 * @param string       $field_key Field key.
	 *
	 * @return array
	 */
	public function field_config( $target, $field_key ) {
		$fields = $this->fields( $target );
		if ( ! isset( $fields[ $field_key ] ) ) {
			return array(); }
		return array(
			'label'            => $fields[ $field_key ]['label'],
			'type'             => 'taxonomy' === $target->kind() ? 'term_meta' : 'post_meta',
			'storage'          => 'taxonomy' === $target->kind() ? 'extension' : 'post_meta',
			'storage_adapter'  => 'taxonomy' === $target->kind() ? 'yoast' : '',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- This established query shape is required for the bounded lookup; changing storage, indexes, caching, or result semantics is outside this advisory boundary.
			'meta_key'         => $field_key,
			'value_type'       => 'plain_text',
			'enhancement_mode' => 'rewrite',
			'default_enabled'  => ! empty( $fields[ $field_key ]['default'] ),
			'editor'           => $this->editor_config( $field_key, $target ),
		);
	}
	/**
	 * Reads taxonomy metadata through Yoast.
	 *
	 * @param mixed        $result    Existing storage result.
	 * @param IC_AI_Target $target    Target object.
	 * @param int          $object_id Object ID.
	 * @param string       $field_key Field key.
	 * @param array        $field     Field configuration.
	 * @param object       $client    AI client.
	 *
	 * @return mixed
	 */
	public function storage_read( $result, $target, $object_id, $field_key, $field, $client ) {
		unset( $client );
		if ( is_array( $result ) && ! empty( $result['handled'] ) ) {
			return $result; }
		if ( ! is_array( $field ) || 'extension' !== ( $field['storage'] ?? '' ) || 'yoast' !== ( $field['storage_adapter'] ?? '' ) || 'taxonomy' !== $target->kind() ) {
			return $result; }
		$stored = class_exists( 'WPSEO_Taxonomy_Meta' ) ? WPSEO_Taxonomy_Meta::get_term_meta( absint( $object_id ), $target->taxonomy() ) : array();
		$map    = $this->taxonomy_map();
		$key    = $map[ $field_key ] ?? $field_key;
		return array(
			'handled' => true,
			'value'   => is_array( $stored ) && isset( $stored[ $key ] ) ? $stored[ $key ] : '',
		);
	}
	/**
	 * Writes taxonomy metadata through Yoast.
	 *
	 * @param mixed        $result    Existing storage result.
	 * @param IC_AI_Target $target    Target object.
	 * @param int          $object_id Object ID.
	 * @param string       $field_key Field key.
	 * @param array        $field     Field configuration.
	 * @param mixed        $value     Value to store.
	 * @param object       $client    AI client.
	 *
	 * @return mixed
	 */
	public function storage_write( $result, $target, $object_id, $field_key, $field, $value, $client ) {
		unset( $client );
		if ( is_array( $result ) && ! empty( $result['handled'] ) ) {
			return $result; }
		if ( ! is_array( $field ) || 'extension' !== ( $field['storage'] ?? '' ) || 'yoast' !== ( $field['storage_adapter'] ?? '' ) || 'taxonomy' !== $target->kind() ) {
			return $result; }
		if ( ! class_exists( 'WPSEO_Taxonomy_Meta' ) ) {
			return array(
				'handled' => true,
				'success' => false,
			); }
		$stored = WPSEO_Taxonomy_Meta::get_term_meta( absint( $object_id ), $target->taxonomy() );
		$stored = is_array( $stored ) ? $stored : array();
		$key    = $this->taxonomy_map()[ $field_key ] ?? $field_key;
		if ( (string) ( $stored[ $key ] ?? '' ) === (string) $value ) {
			return array(
				'handled' => true,
				'success' => true,
			); }
		$stored[ $key ] = $value;
		WPSEO_Taxonomy_Meta::set_values( absint( $object_id ), $target->taxonomy(), $stored );
		$check = WPSEO_Taxonomy_Meta::get_term_meta( absint( $object_id ), $target->taxonomy() );
		$this->rebuild_term_indexable( absint( $object_id ) );
		return array(
			'handled' => true,
			'success' => is_array( $check ) && (string) ( $check[ $key ] ?? '' ) === (string) $value,
		);
	}

	/**
	 * Adds available Yoast fields to a target field map.
	 *
	 * @param array        $field_map   Existing field map.
	 * @param IC_AI_Target $target      Target object.
	 * @param object       $integration Owning integration.
	 *
	 * @return array
	 */
	public function filter_target_field_map( $field_map, $target, $integration ) {
		unset( $integration );
		if ( ! $target instanceof IC_AI_Target ) {
			return $field_map; }
		foreach ( $this->fields( $target ) as $key => $field ) {
			$field_map[ $key ] = array_merge( (array) ( $field_map[ $key ] ?? array() ), $this->field_config( $target, $key ) );
		}
		return $field_map;
	}

	/**
	 * Reads Yoast values for a target object.
	 *
	 * @param IC_AI_Target $target    Target object.
	 * @param int          $object_id Object ID.
	 *
	 * @return array
	 */
	public function read( $target, $object_id ) {
		if ( ! $this->available( $target ) ) {
			return array(); }
		if ( 'taxonomy' === $target->kind() && class_exists( 'WPSEO_Taxonomy_Meta' ) ) {
			$stored = WPSEO_Taxonomy_Meta::get_term_meta( (int) $object_id, $target->taxonomy() );
			$out    = array();
			foreach ( $this->taxonomy_map() as $public => $key ) {
				$out[ $public ] = isset( $stored[ $key ] ) ? $stored[ $key ] : ''; }
			return $out;
		}
		$out = array();
		foreach ( array_keys( $this->fields( $target ) ) as $key ) {
			$out[ $key ] = get_post_meta( (int) $object_id, $key, true ); }
		return $out;
	}

	/**
	 * Writes Yoast values for a target object.
	 *
	 * @param IC_AI_Target $target    Target object.
	 * @param int          $object_id Object ID.
	 * @param array        $values    Values keyed by public field key.
	 *
	 * @return bool
	 */
	public function write( $target, $object_id, $values ) {
		if ( ! $this->available( $target ) || ! is_array( $values ) ) {
			return false; }
		if ( 'taxonomy' === $target->kind() && class_exists( 'WPSEO_Taxonomy_Meta' ) ) {
			$stored = WPSEO_Taxonomy_Meta::get_term_meta( (int) $object_id, $target->taxonomy() );
			$stored = is_array( $stored ) ? $stored : array();
			foreach ( $this->taxonomy_map() as $public => $key ) {
				if ( array_key_exists( $public, $values ) ) {
					$stored[ $key ] = sanitize_text_field( (string) $values[ $public ] ); }
			}
			WPSEO_Taxonomy_Meta::set_values( (int) $object_id, $target->taxonomy(), $stored );
			$this->rebuild_term_indexable( (int) $object_id );
			return true;
		}
		foreach ( $this->fields( $target ) as $key => $unused ) {
			if ( array_key_exists( $key, $values ) ) {
				update_post_meta( (int) $object_id, $key, sanitize_text_field( (string) $values[ $key ] ) ); }
		}
		return true;
	}

	/**
	 * Determines whether Yoast metadata is available for a target.
	 *
	 * @param IC_AI_Target $target Target object.
	 *
	 * @return bool
	 */
	private function available( $target ) {
		if ( ! $target instanceof IC_AI_Target || ! class_exists( 'WPSEO_Meta' ) ) {
			return false; }
		if ( ! class_exists( 'WPSEO_Utils' ) || ! is_callable( array( 'WPSEO_Utils', 'is_metabox_active' ) ) ) {
			return true; }
		return (bool) WPSEO_Utils::is_metabox_active( 'taxonomy' === $target->kind() ? $target->taxonomy() : $target->post_type(), 'taxonomy' === $target->kind() ? 'taxonomy' : 'post_type' );
	}

	/**
	 * Determines whether a Yoast social feature is enabled.
	 *
	 * @param string $feature Feature key.
	 *
	 * @return bool
	 */
	private function social_enabled( $feature ) {
		return ! class_exists( 'WPSEO_Options' ) || ! is_callable( array( 'WPSEO_Options', 'get' ) ) || (bool) WPSEO_Options::get( $feature, false, array( 'wpseo_social' ) );
	}

	/**
	 * Returns the public-to-taxonomy Yoast field map.
	 *
	 * @return array
	 */
	private function taxonomy_map() {
		return array(
			'_yoast_wpseo_title'                 => 'wpseo_title',
			'_yoast_wpseo_metadesc'              => 'wpseo_desc',
			'_yoast_wpseo_focuskw'               => 'wpseo_focuskw',
			'_yoast_wpseo_opengraph-title'       => 'wpseo_opengraph-title',
			'_yoast_wpseo_opengraph-description' => 'wpseo_opengraph-description',
			'_yoast_wpseo_twitter-title'         => 'wpseo_twitter-title',
			'_yoast_wpseo_twitter-description'   => 'wpseo_twitter-description',
		);
	}

	/**
	 * Returns editor configuration for a Yoast field.
	 *
	 * @param string       $key    Public field key.
	 * @param IC_AI_Target $target Target object.
	 *
	 * @return array
	 */
	private function editor_config( $key, $target ) {
		$config = array(
			'_yoast_wpseo_title'                 => array( '#yoast_wpseo_title', 'updateData', array( 'title' => '__value__' ) ),
			'_yoast_wpseo_metadesc'              => array( '#yoast_wpseo_metadesc', 'updateData', array( 'description' => '__value__' ) ),
			'_yoast_wpseo_focuskw'               => array( '#yoast_wpseo_focuskw', 'setFocusKeyword', array( '__value__' ) ),
			'_yoast_wpseo_opengraph-title'       => array( '#yoast_wpseo_opengraph-title', 'setFacebookPreviewTitle', array( '__value__' ) ),
			'_yoast_wpseo_opengraph-description' => array( '#yoast_wpseo_opengraph-description', 'setFacebookPreviewDescription', array( '__value__' ) ),
			'_yoast_wpseo_twitter-title'         => array( '#yoast_wpseo_twitter-title', 'setTwitterPreviewTitle', array( '__value__' ) ),
			'_yoast_wpseo_twitter-description'   => array( '#yoast_wpseo_twitter-description', 'setTwitterPreviewDescription', array( '__value__' ) ),
		);
		if ( 'taxonomy' === $target->kind() ) {
			$stored = $this->taxonomy_map()[ $key ];
			return array(
				'adapter'        => 'yoast',
				'store'          => 'yoast-seo/editor',
				'input_selector' => '#hidden_' . $stored,
				'action'         => $config[ $key ][1],
				'payload'        => $config[ $key ][2],
			); }
		return array(
			'adapter'        => 'yoast',
			'store'          => 'yoast-seo/editor',
			'input_selector' => $config[ $key ][0],
			'action'         => $config[ $key ][1],
			'payload'        => $config[ $key ][2],
		);
	}

	/**
	 * Requests a Yoast taxonomy indexable rebuild when supported.
	 *
	 * @param int $term_id Term ID.
	 */
	private function rebuild_term_indexable( $term_id ) {
		if ( ! function_exists( 'YoastSEO' ) ) {
			return; }
		$yoast = YoastSEO();
		if ( ! is_object( $yoast ) || ! isset( $yoast->classes ) || ! is_object( $yoast->classes ) ) {
			return; }
		$class = class_exists( 'Yoast\\WP\\SEO\\Watchers\\Indexable_Term_Watcher' ) ? 'Yoast\\WP\\SEO\\Watchers\\Indexable_Term_Watcher' : 'Yoast\\WP\\SEO\\Integrations\\Watchers\\Indexable_Term_Watcher';
		if ( class_exists( $class ) ) {
			$watcher = $yoast->classes->get( $class );
			if ( is_object( $watcher ) && is_callable( array( $watcher, 'build_indexable' ) ) ) {
				$watcher->build_indexable( $term_id ); }
		}
	}
}
