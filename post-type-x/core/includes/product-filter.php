<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName, Squiz.Commenting.FileComment.WrongStyle -- Legacy filename retained for backward compatibility.
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Legacy product catalog filter class.
 *
 * @version 1.0.0
 * @author  impleCode
 */
// phpcs:ignore PEAR.NamingConventions.ValidClassName.StartWithCapital, PEAR.NamingConventions.ValidClassName.Invalid, Squiz.Commenting.ClassComment.WrongStyle, Squiz.Commenting.ClassComment.Missing -- Legacy class name retained for backward compatibility; the class docblock is directly above.
class ic_catalog_filter {
	/**
	 * Filter name.
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Sanitization callback.
	 *
	 * @var string Sanitization function
	 */
	public $sanitization;

	/**
	 * Taxonomy name.
	 *
	 * @var string
	 */
	public $taxonomy_name;

	/**
	 * Meta field names.
	 *
	 * @var string|array
	 */
	public $meta_name;

	/**
	 * Meta comparison operators.
	 *
	 * @var string|array Possible values are ‘=’, ‘!=’, ‘>’, ‘>=’, ‘<‘, ‘<=’, ‘LIKE’, ‘NOT LIKE’, ‘IN’, ‘NOT IN’, ‘BETWEEN’, ‘NOT BETWEEN’, ‘EXISTS’, ‘NOT EXISTS’, ‘REGEXP’, ‘NOT REGEXP’, ‘RLIKE’
	 */
	public $meta_compare;

	/**
	 * Meta comparison values.
	 *
	 * @var string|array
	 */
	public $meta_compare_value;

	/**
	 * Whether the filter is enabled by default.
	 *
	 * @var bool
	 */
	public $enable_by_default;

	/**
	 * Query relation.
	 *
	 * @var string AND, OR
	 */
	public $relation;

	/**
	 * Whether the filter is permanent.
	 *
	 * @var bool
	 */
	public $permanent;

	/**
	 * Value that activates the filter.
	 *
	 * @var int|string|null
	 */
	public $apply_value;

	/**
	 * Value that disables the filter.
	 *
	 * @var int|string
	 */
	public $disable_value;

	/**
	 * Applied taxonomy query.
	 *
	 * @var array|void
	 */
	public $applied_tax_query;

	/**
	 * Applied meta query.
	 *
	 * @var array|void
	 */
	public $applied_meta_query;

	/**
	 * Query state before shortcode filters.
	 *
	 * @var WP_Query
	 */
	public $pre_shortcode_query;

	/**
	 * Query state before filters.
	 *
	 * @var WP_Query
	 */
	public $pre_query;

	/**
	 * Date query configuration.
	 *
	 * @var array|string|void
	 */
	public $date_query;

	/**
	 * Applied date query.
	 *
	 * @var array|string|void
	 */
	public $applied_date_query;

	/**
	 * Initializes the filter.
	 *
	 * @param string          $name              Filter name.
	 * @param string          $sanitization      Sanitization callback.
	 * @param string          $taxonomy_name     Taxonomy name.
	 * @param string|array    $meta_name         Meta field names.
	 * @param string|array    $meta_compare      Meta comparison operators.
	 * @param string|array    $meta_compare_value Meta comparison values.
	 * @param string          $relation          Query relation.
	 * @param bool            $enable_by_default Whether enabled by default.
	 * @param bool            $permanent         Whether filter is permanent.
	 * @param int|string|null $apply_value       Value that activates the filter.
	 * @param int|string      $disable_value     Value that disables the filter.
	 */
	public function __construct( $name, $sanitization = 'intval', $taxonomy_name = '', $meta_name = '', $meta_compare = '=', $meta_compare_value = '1', $relation = 'AND', $enable_by_default = false, $permanent = false, $apply_value = null, $disable_value = 'all' ) {
		$this->name          = $name;
		$this->sanitization  = $sanitization;
		$this->taxonomy_name = apply_filters( 'ic_filter_taxonomy_name', $taxonomy_name );
		if ( 'date_query' === $meta_name ) {
			$this->date_query = $meta_compare_value;
		} else {
			$this->meta_name = apply_filters( 'ic_filter_meta_name', is_array( $meta_name ) ? $meta_name : array( $meta_name ) );
		}
		$this->meta_compare       = is_array( $meta_compare ) ? $meta_compare : array( $meta_compare );
		$this->meta_compare_value = apply_filters( 'ic_filter_meta_compare_value', is_array( $meta_compare_value ) ? $meta_compare_value : array( $meta_compare_value ) );
		$this->enable_by_default  = $enable_by_default;
		$this->relation           = $relation;
		$this->permanent          = $permanent;
		$this->apply_value        = $apply_value;
		$this->disable_value      = $disable_value;
		add_action( 'ic_set_product_filters', array( $this, 'set' ) );
		add_filter( 'active_product_filters', array( $this, 'enable' ) );

		if ( ! empty( $this->taxonomy_name ) ) {
			add_filter( 'init', array( $this, 'register_taxonomy' ), 50 );
			add_filter( 'ic_filter_taxonomies', array( $this, 'set_filter_taxonomy' ) );

			add_action( 'product_meta_save_update', array( $this, 'taxonomy_save' ), 10, 2 );
			add_filter( 'ic_product_ajax_query_vars', array( $this, 'ajax_query_vars_remove' ) );
		}
		add_action( 'apply_product_filters', array( $this, 'applied' ), 52 );
		add_filter( 'apply_shortcode_product_filters', array( $this, 'applied' ), 52 );
		do_action( 'ic_catalog_filter_construct', $this );
	}

	/**
	 * Registers the filter taxonomy label.
	 *
	 * @param array $taxonomies Registered filter taxonomies.
	 * @return array
	 */
	public function set_filter_taxonomy( $taxonomies ) {
		$taxonomies[ $this->taxonomy_name ] = $this->name;

		return $taxonomies;
	}

	/**
	 * Removes this filter from AJAX query vars.
	 *
	 * @param array $query_vars Query vars.
	 * @return array
	 */
	public function ajax_query_vars_remove( $query_vars ) {
		if ( empty( $query_vars['tax_query'] ) ) {
			return $query_vars;
		}
		$tax_query = $this->tax_query();
		if ( ! empty( $tax_query ) ) {
			$remove_key = array_search( $tax_query, $query_vars['tax_query'], true );
			if ( false !== $remove_key ) {
				unset( $query_vars['tax_query'][ $remove_key ] );
			}
		}

		return $query_vars;
	}

	/**
	 * Returns the applied filter query.
	 *
	 * @param mixed $query Query value.
	 * @return mixed
	 */
	public function applied( $query ) {
		if ( ! empty( $this->applied_tax_query ) ) {
			return apply_filters( 'ic_catalog_filter_applied_tax_query', $query, $this->applied_tax_query, $this->name );
		}

		return apply_filters( 'ic_catalog_filter_not_applied_tax_query', $query, $this );
	}

	/**
	 * Applies this filter to the main query.
	 *
	 * @param WP_Query $query Query object.
	 * @return void
	 */
	public function apply( $query ) {
		if ( $query->get( 'ic_filter_applied_' . $this->name ) ) {
			return;
		}
		$filter_tax_query = $this->tax_query();
		if ( array() === $filter_tax_query ) {
			$this->reset();

			return;
		}
		$this->pre_query = clone $query;
		if ( ! empty( $filter_tax_query ) ) {
			if ( empty( $query->query['ic_exclude_tax'] ) || ( ! empty( $query->query['ic_exclude_tax'] ) && ! in_array( $this->taxonomy_name, $query->query['ic_exclude_tax'], true ) ) ) {
				$tax_query = $query->get( 'tax_query' );
				if ( empty( $tax_query ) ) {
					$tax_query = array();
				}
				if ( ! in_array( $filter_tax_query, $tax_query, true ) ) {
					$tax_query[] = $filter_tax_query;
					$query->set( 'tax_query', $tax_query );
				}
				$this->applied_tax_query = $filter_tax_query;
			}
		} elseif ( ! empty( $this->date_query ) ) {
			if ( empty( $query->query['ic_exclude'] ) || ( ! empty( $query->query['ic_exclude'] ) && ! in_array( 'date_query', $query->query['ic_exclude'], true ) ) ) {
				$date_query = $query->get( 'date_query' );
				if ( empty( $date_query ) ) {
					$date_query = array();
				}
				if ( ! in_array( $this->date_query, $date_query, true ) ) {
					$date_query[] = $this->date_query;
					$query->set( 'date_query', $date_query );
				}
				$this->applied_date_query = $this->date_query;
			}
		} else {
			$filter_meta_query = $this->meta_query();
			if ( ! empty( $filter_meta_query ) ) {
				$meta_query = $query->get( 'meta_query' );
				if ( empty( $meta_query ) ) {
					$meta_query = array();
				}
				if ( ! in_array( $filter_meta_query, $meta_query, true ) ) {
					$meta_query[] = $filter_meta_query;
					$query->set( 'meta_query', $meta_query );
				}
				$this->applied_meta_query = $filter_meta_query;
			} else {
				do_action( 'ic_apply_' . $this->name . '_filter', $query );
			}
		}
		$query->set( 'ic_filter_applied_' . $this->name, '1' );
	}

	/**
	 * Resets the filter if the query becomes empty.
	 *
	 * @param WP_Query $query Query object.
	 * @return void
	 */
	public function check_if_empty( $query ) {
		if ( $query->get( 'ic_filter_' . $this->name . 'checked_if_empty' ) ) {
			return;
		}

		// The pre-query is used to restore results when the applied filter empties the set.
		if ( ! empty( $this->applied_tax_query ) && ! empty( $this->pre_query ) ) {
			$removed = remove_action( 'apply_product_filters', array( $this, 'check_if_empty' ), 51 );
			global $wp_query;
			if ( ! empty( $wp_query->request ) ) {
				$posts = $wp_query->posts;
			} else {
				$new_temp_query = ic_wp_query( $query->query_vars );
				$posts          = $new_temp_query->posts;
			}
			$pre_tax_query = $this->pre_query->get( 'tax_query' );
			if ( ! is_product_filters_active( array( $this->name ) ) ) {
				$temp_pre_query  = ic_wp_query( $this->pre_query->query_vars );
				$pre_query_posts = $temp_pre_query->posts;
			}
			if ( empty( $posts ) || ( isset( $pre_query_posts ) && count( $pre_query_posts ) < $query->query_vars['posts_per_page'] ) ) {
				$query->set( 'tax_query', $pre_tax_query );
				$this->reset();
			}
				$query->set( 'ic_filter_' . $this->name . 'checked_if_empty', '1' );
			if ( $removed ) {
				add_action( 'apply_product_filters', array( $this, 'check_if_empty' ), 51 );
			}
		}
	}

	/**
	 * Applies this filter to shortcode query arguments.
	 *
	 * @param array $shortcode_query Shortcode query args.
	 * @return array
	 */
	public function apply_shortcode( $shortcode_query ) {
		$filter_tax_query = $this->tax_query();
		if ( array() === $filter_tax_query ) {
			$this->reset();

			return $shortcode_query;
		}
		$this->pre_shortcode_query = $shortcode_query;
		if ( ! empty( $filter_tax_query ) ) {
			if ( empty( $shortcode_query['tax_query'] ) ) {
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- This sets WP_Query arguments for catalog filtering.
				$shortcode_query['tax_query'] = array();
			}
			if ( ! in_array( $filter_tax_query, $shortcode_query['tax_query'], true ) ) {
				$shortcode_query['tax_query'][] = $filter_tax_query;
			}
			$this->applied_tax_query = $filter_tax_query;
		} elseif ( ! empty( $this->date_query ) ) {
			if ( empty( $shortcode_query['date_query'] ) ) {
				$shortcode_query['date_query'] = array();
			}
			if ( ! in_array( $this->date_query, $shortcode_query['date_query'], true ) ) {
				$shortcode_query['date_query'][] = $this->date_query;
			}
		} else {
			$filter_meta_query = $this->meta_query();
			if ( ! empty( $filter_meta_query ) ) {
				if ( empty( $shortcode_query['meta_query'] ) ) {
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- This sets WP_Query arguments for catalog filtering.
					$shortcode_query['meta_query'] = array();
				}
				if ( ! in_array( $filter_meta_query, $shortcode_query['meta_query'], true ) ) {
					$shortcode_query['meta_query'][] = $filter_meta_query;
				}
			}
		}

		return $shortcode_query;
	}

	/**
	 * Resets shortcode filters if the result becomes empty.
	 *
	 * @param array $shortcode_query Shortcode query args.
	 * @return array
	 */
	public function check_if_empty_shortcode( $shortcode_query ) {
		if ( ! empty( $this->applied_tax_query ) && ! empty( $this->pre_shortcode_query ) && ! is_product_filters_active( array( $this->name ) ) ) {
			$archive_multiple_settings = get_multiple_settings();
			$per_page                  = isset( $shortcode_query['posts_per_page'] ) ? $shortcode_query['posts_per_page'] : $archive_multiple_settings['archive_products_limit'];
			$query                     = ic_wp_query( $shortcode_query );
			$posts                     = $query->posts;
			$pre_query                 = ic_wp_query( array_merge( $this->pre_shortcode_query, array( 'posts_per_page' => $per_page ) ) );
			$pre_query_posts           = $pre_query->posts;

			if ( empty( $posts ) || count( $pre_query_posts ) < $per_page ) {
				$shortcode_query = $this->pre_shortcode_query;
				$this->reset();
			}
		}

		return $shortcode_query;
	}

	/**
	 * Builds the taxonomy query.
	 *
	 * @return array|void
	 */
	public function tax_query() {
		if ( empty( $this->taxonomy_name ) ) {
			return;
		}
		$terms    = ic_get_terms( array( 'taxonomy' => $this->taxonomy_name ) );
		$term_ids = wp_list_pluck( $terms, 'term_taxonomy_id' );
		if ( empty( $term_ids ) ) {
			return array();
		}
		$tax_query = array(
			'taxonomy' => $this->taxonomy_name,
			'field'    => 'term_taxonomy_id',
			'terms'    => $term_ids,
		);

		return apply_filters( 'ic_catalog_filter_tax_query', $tax_query, $this->name, $this->taxonomy_name, $terms );
	}

	/**
	 * Builds the meta query.
	 *
	 * @return array|void
	 */
	public function meta_query() {
		if ( empty( $this->meta_name ) ) {
			return;
		}
		$meta_query = array();
		foreach ( $this->meta_name as $key => $meta_name ) {
			if ( empty( $meta_name ) ) {
				continue;
			}
			$meta_query[] = array(
				'key'     => $meta_name,
				'compare' => $this->meta_compare[ $key ],
				'value'   => $this->meta_compare_value[ $key ],
			);
		}
		if ( ! empty( $meta_query ) ) {
			$meta_query['relation'] = $this->relation;
		}

		return apply_filters( 'ic_catalog_filter_meta_query', $meta_query, $this->name, $this->meta_name );
	}

	/**
	 * Builds the date query.
	 *
	 * @return array|void
	 */
	public function date_query() {
		if ( empty( $this->meta_name ) || 'after' !== $this->meta_name ) {
			return;
		}

		return array(
			$this->meta_compare_value,
		);
	}

	/**
	 * Adds this filter to the active list.
	 *
	 * @param array $filters Active filters.
	 * @return array
	 */
	public function enable( $filters ) {
		$filters[] = $this->name;

		return $filters;
	}

	/**
	 * Loads and applies request values for this filter.
	 *
	 * @return void
	 */
	public function set() {
		if ( empty( $this->name ) ) {
			return;
		}
		$session        = get_product_catalog_session();
		$check_if_empty = false;
		$permanent      = $this->permanent;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, bookmarkable catalog filter parameter that only affects the current visitor's own catalog session.
		if ( isset( $_GET[ $this->name ] ) || ( $this->enable_by_default && ! isset( $session['filters'][ $this->name ] ) ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, bookmarkable catalog filter parameter that only affects the current visitor's own catalog session.
			if ( isset( $_GET[ $this->name ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Public, bookmarkable catalog filter parameter affecting only the current visitor's session; the raw value is sanitized by the configured callback on the next line.
				$raw_filter_value = wp_unslash( $_GET[ $this->name ] );
				$filter_value     = is_array( $raw_filter_value ) ? array_map( $this->sanitization, $raw_filter_value ) : call_user_func( $this->sanitization, $raw_filter_value );
			} elseif ( $this->enable_by_default ) {
				$filter_value   = 1;
				$check_if_empty = true;
				$permanent      = false;
			} else {
				$filter_value = '';
			}
			if ( ! empty( $filter_value ) || is_numeric( $filter_value ) ) {
				if ( ! isset( $session['filters'] ) ) {
					$session['filters'] = array();
				}
				if ( ! isset( $session['permanent-filters'] ) ) {
					$session['permanent-filters'] = array();
				}
				if ( $permanent && ! in_array( $this->name, $session['permanent-filters'], true ) ) {
					$session['permanent-filters'][] = $this->name;
				}

				$session['filters'][ $this->name ] = $filter_value;
			} elseif ( isset( $session['filters'][ $this->name ] ) ) {
				unset( $session['filters'][ $this->name ] );
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, bookmarkable catalog filter parameter that only affects the current visitor's own catalog session.
		} elseif ( ! isset( $_GET[ $this->name ] ) ) {
			if ( ! $this->enable_by_default && isset( $session['filters'][ $this->name ] ) ) {
				unset( $session['filters'][ $this->name ] );
			}
			$check_if_empty = true;
		}
		set_product_catalog_session( $session );
		if ( is_product_filter_active( $this->name, $this->apply_value ) || apply_filters( 'ic_force_filter_active', false, $this->name, $this->apply_value ) ) {
			add_action( 'apply_product_filters', array( $this, 'apply' ), 50 );
			add_filter( 'apply_shortcode_product_filters', array( $this, 'apply_shortcode' ), 50 );
			if ( $check_if_empty ) {
				add_action( 'apply_product_filters', array( $this, 'check_if_empty' ), 51 );
				add_filter( 'apply_shortcode_product_filters', array( $this, 'check_if_empty_shortcode' ), 51 );
			}
		} else {
			do_action( 'ic_dont_apply_' . $this->name . '_filter', $this );
		}
	}

	/**
	 * Resets the applied taxonomy query.
	 *
	 * @return void
	 */
	public function reset() {
		$_GET[ $this->name ]     = $this->disable_value;
		$this->applied_tax_query = '';
	}

	/**
	 * Saves taxonomy terms for the current filter.
	 *
	 * @param array   $product_meta Product meta.
	 * @param WP_Post $post         Product post.
	 * @return array
	 */
	public function taxonomy_save( $product_meta, $post ) {
		foreach ( $this->meta_name as $key => $meta_name ) {
			$filtered_meta_value = apply_filters( 'ic_catalog_filter_compare_meta_value', false, $meta_name, $product_meta, $this->taxonomy_name );
			if ( 'false' === $filtered_meta_value || ( false === $filtered_meta_value && ! isset( $product_meta[ $meta_name ] ) ) ) {
				$this->save_term( $meta_name . $key, $post->ID, true );
				continue;
			}
			if ( false !== $filtered_meta_value ) {
				$compare_value = $filtered_meta_value;
			} else {
				$compare_value = $product_meta[ $meta_name ];
			}
			$different_than = $this->meta_compare_value[ $key ];
			if ( substr( $different_than, 0, 1 ) === '_' ) {
				if ( empty( $compare_value ) ) {
					$this->save_term( $meta_name . $key, $post->ID, true );
					continue;
				}
				if ( isset( $product_meta[ $different_than ] ) ) {
					$different_than_value = $product_meta[ $different_than ];
				} else {
					$different_than_value = '';
				}
				$different_than = apply_filters( 'ic_catalog_filter_compare_different_than_meta', $different_than_value, $different_than, $product_meta, $this->taxonomy_name );
				if ( 'false' === $different_than || empty( $different_than ) ) {
					$this->save_term( $meta_name . $key, $post->ID, true );
					continue;
				}
			}
			if ( $this->compare( $compare_value, $different_than, $this->meta_compare[ $key ] ) ) {
				$this->save_term( $meta_name . $key, $post->ID );
			} else {
				$this->save_term( $meta_name . $key, $post->ID, true );
			}
		}

		return $product_meta;
	}

	/**
	 * Saves or removes a term on the product.
	 *
	 * @param string $name       Term name.
	 * @param int    $product_id Product ID.
	 * @param bool   $remove     Whether to remove the term.
	 * @return void
	 */
	public function save_term( $name, $product_id, $remove = false ) {
		$term = get_term_by( 'name', $name, $this->taxonomy_name );
		if ( is_wp_error( $term ) ) {
			return;
		}
		$prev_term_ids = wp_get_object_terms( $product_id, $this->taxonomy_name, array( 'fields' => 'ids' ) );
		$term_ids      = $prev_term_ids;
		if ( empty( $term ) ) {
			if ( $remove ) {
				return;
			}
			$term       = wp_insert_term( $name, $this->taxonomy_name );
			$term_ids[] = $term['term_id'];
		} else {
			$terms = array( $term->term_id );
			if ( $remove ) {
				$term_ids = array_diff( $term_ids, $terms );
			} else {
				$term_ids = array_merge( $term_ids, $terms );
			}
		}
		$term_ids = array_unique( $term_ids );
		if ( $term_ids !== $prev_term_ids ) {
			wp_set_object_terms( $product_id, $term_ids, $this->taxonomy_name );
		}
		do_action( 'ic_catalog_filter_term_saved', $this->name, $product_id, $remove, $term_ids );
	}

	/**
	 * Registers the internal taxonomy used by this filter.
	 *
	 * @return void
	 */
	public function register_taxonomy() {
		if ( empty( $this->taxonomy_name ) ) {
			return;
		}
		if ( taxonomy_exists( $this->taxonomy_name ) ) {
			return;
		}
		$args       = array(
			'label'        => $this->name,
			'hierarchical' => false,
			'public'       => false,
			'query_var'    => false,
			'rewrite'      => false,
		);
		$post_types = apply_filters( 'ic_catalog_filter_taxonomy_post_types', product_post_type_array() );
		register_taxonomy( $this->taxonomy_name, $post_types, $args );
	}

	/**
	 * Compares two values using a legacy operator.
	 *
	 * @param mixed  $var1 First value.
	 * @param mixed  $var2 Second value.
	 * @param string $op   Comparison operator.
	 * @return bool
	 */
	public function compare( $var1, $var2, $op ) {
		switch ( $op ) {
			case '=':
				return (string) $var1 === (string) $var2;
			case '!=':
				return (string) $var1 !== (string) $var2;
			case '>=':
				return $var1 >= $var2;
			case '<=':
				return $var1 <= $var2;
			case '>':
				return $var1 > $var2;
			case '<':
				return $var1 < $var2;
			default:
				return false;
		}
	}
}
