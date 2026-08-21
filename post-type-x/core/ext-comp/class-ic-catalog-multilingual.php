<?php
/**
 * Multilingual compatibility class.
 *
 * @package ecommerce-product-catalog/ext-comp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Integrates multilingual plugins with eCommerce Product Catalog.
 */
class IC_Catalog_Multilingual {
	/**
	 * Registers multilingual integration hooks.
	 */
	public function __construct() {
		if ( function_exists( 'pll_is_translated_post_type' ) && ! pll_is_translated_post_type( 'al_product' ) ) {
			return;
		}
		add_action( 'pll_pre_init', array( $this, 'init' ) );
		add_action( 'init', array( $this, 'init' ), 5 );
	}

	/**
	 * Hooks multilingual behavior into the catalog runtime.
	 *
	 * @return void
	 */
	public function init() {
		if ( ! is_admin() || is_ic_ajax() ) {
			add_filter( 'product_listing_id', array( $this, 'replace_product_listing_id' ) );
			add_filter( 'product_listing_url', array( $this, 'replace_product_listing_url' ) );
			add_filter( 'ic_permalink_id', array( __CLASS__, 'replace_page_id' ) );
			add_filter( 'ic_product_id', array( __CLASS__, 'replace_page_id' ) );
			add_filter( 'ic_is_page_id', array( __CLASS__, 'replace_page_id' ) );
			add_action( 'ic_ajax_self_submit', array( $this, 'ajax_apply_lang' ) );
			add_action( 'admin_init', array( $this, 'ajax_apply_lang' ) );
			add_filter( 'ic_product_ajax_query_vars', array( $this, 'ajax_query_vars' ) );
			add_filter( 'ic_settings_select_page_args', array( $this, 'lang_arg' ) );
			add_filter( 'ic_get_all_catalog_products_args', array( $this, 'lang_arg' ) );
			add_filter( 'ic_reassign_attr_product_args', array( $this, 'set_any_lang_arg' ) );
			add_filter( 'ic_update_product_data_product_args', array( $this, 'set_any_lang_arg' ) );
			add_filter( 'product_slug', array( $this, 'pll_slug' ) );
			add_filter( 'ic_wp_query_args', array( $this, 'current_lang_arg' ) );
			add_filter( 'ic_adding_id_to_cart', array( $this, 'default_lang_post_id' ) );
			add_filter( 'ic_cart_insert_id', array( $this, 'default_lang_post_id' ) );
			add_filter( 'is_ic_product_in_cart', array( $this, 'in_cart' ), 10, 2 );
			add_filter( 'pll_home_url_white_list', array( $this, 'home_url' ) );
		}
	}

	/**
	 * Extends the Polylang home URL whitelist for catalog widgets.
	 *
	 * @param array $whitelist Whitelisted paths.
	 *
	 * @return array
	 */
	public function home_url( $whitelist ) {
		return array_merge( $whitelist, array( array( 'file' => 'search-widget' ) ) );
	}

	/**
	 * Filters the product listing slug in Polylang free.
	 *
	 * @param string $slug Current slug.
	 *
	 * @return string
	 */
	public function pll_slug( $slug ) {
		if ( defined( 'POLYLANG_PRO' ) || ! defined( 'POLYLANG_BASENAME' ) ) {
			return $slug;
		}
		remove_filter( 'product_listing_id', array( $this, 'replace_product_listing_id' ) );
		$page_id = get_product_listing_id();
		add_filter( 'product_listing_id', array( $this, 'replace_product_listing_id' ) );
		if ( 'noid' !== $page_id ) {
			$new_slug = urldecode( untrailingslashit( get_page_uri( $page_id ) ) );
		}
		if ( empty( $new_slug ) ) {
			$settings = get_multiple_settings();
			$new_slug = ic_sanitize_title( $settings['catalog_plural'] );
		}

		return $new_slug;
	}

	/**
	 * Forces catalog queries into the default language.
	 *
	 * @param array $args Query arguments.
	 *
	 * @return array
	 */
	public function lang_arg( $args ) {
		if ( function_exists( 'pll_default_language' ) ) {
			$args['lang'] = pll_default_language();
		}

		return $args;
	}

	/**
	 * Forces catalog queries into the current language.
	 *
	 * @param array $args Query arguments.
	 *
	 * @return array
	 */
	public function current_lang_arg( $args ) {
		if ( function_exists( 'pll_current_language' ) ) {
			$args['lang'] = pll_current_language();
		}

		return $args;
	}

	/**
	 * Clears the language query argument.
	 *
	 * @param array $args Query arguments.
	 *
	 * @return array
	 */
	public function unset_lang_arg( $args ) {
		if ( ! empty( $args['lang'] ) ) {
			$args['lang'] = '';
		}

		return $args;
	}

	/**
	 * Forces the language query argument to allow any language.
	 *
	 * @param array $args Query arguments.
	 *
	 * @return array
	 */
	public function set_any_lang_arg( $args ) {
		if ( empty( $args['lang'] ) ) {
			$args['lang'] = '';
		}

		return $args;
	}

	/**
	 * Returns true for filter callbacks that need a constant truthy response.
	 *
	 * @return bool
	 */
	public function ret_true() {
		return true;
	}

	/**
	 * Replaces product listing IDs for different language.
	 *
	 * @param int $listing_id Listing page ID.
	 *
	 * @return int
	 */
	public function replace_product_listing_id( $listing_id ) {
		if ( ! empty( $listing_id ) ) {
			if ( function_exists( 'pll_get_post' ) ) {
				$alternative_listing_id = pll_get_post( $listing_id );
			}
			if ( function_exists( 'icl_object_id' ) ) {
				$alternative_listing_id = icl_object_id( $listing_id, 'page', true );
			}
			if ( ! empty( $alternative_listing_id ) ) {
				$listing_id = $alternative_listing_id;
			}
		}

		return $listing_id;
	}

	/**
	 * Replaces a page or product ID with its translated equivalent.
	 *
	 * @param int         $id   Current object ID.
	 * @param string|null $lang Optional language code.
	 *
	 * @return int
	 */
	public static function replace_page_id( $id, $lang = null ) {
		if ( ! empty( $id ) ) {
			if ( function_exists( 'pll_get_post' ) ) {
				$alternative_id = pll_get_post( $id );
			}
			if ( function_exists( 'icl_object_id' ) ) {
				$post_type = get_post_type( $id );
				if ( ! empty( $post_type ) ) {
					$alternative_id = icl_object_id( $id, $post_type, true, $lang );
				}
			}
			if ( ! empty( $alternative_id ) ) {
				$id = $alternative_id;
			}
		}

		return $id;
	}

	/**
	 * Resolves a post ID to the default language version.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return int
	 */
	public static function default_lang_post_id( $post_id ) {
		if ( empty( $post_id ) || ! function_exists( 'pll_get_post' ) ) {
			return $post_id;
		}

		$default_id = pll_get_post( $post_id, pll_default_language() );
		if ( empty( $default_id ) ) {
			return $post_id;
		}

		return $default_id;
	}

	/**
	 * Checks whether the default-language product is already in the cart.
	 *
	 * @param bool   $in_cart Current in-cart state.
	 * @param string $cart_id Cart item identifier.
	 *
	 * @return bool
	 */
	public static function in_cart( $in_cart, $cart_id ) {
		if ( $in_cart ) {
			return $in_cart;
		}
		$product_id         = cart_id_to_product_id( $cart_id );
		$default_product_id = self::default_lang_post_id( $product_id );
		if ( $default_product_id !== $product_id ) {
			$default_cart_id = str_replace( $product_id, $default_product_id, $cart_id );

			return is_ic_product_in_cart( $default_cart_id );
		}

		return $in_cart;
	}

	/**
	 * Replaces the product listing URL with the translated archive URL.
	 *
	 * @param string $url Current listing URL.
	 *
	 * @return string
	 */
	public function replace_product_listing_url( $url ) {
		$post_type = ic_get_post_type();
		if ( ! empty( $post_type ) && ic_string_contains( $post_type, 'al_product' ) ) {
			$new_url = get_post_type_archive_link( $post_type );
		} else {
			$new_url = get_post_type_archive_link( 'al_product' );
		}
		if ( ! empty( $new_url ) ) {
			$url = $new_url;
		}

		return $url;
	}

	/**
	 * Filters Polylang post type links.
	 *
	 * @param string $link Current link.
	 *
	 * @return string
	 */
	public function pll_post_type_link( $link ) {
		return $link;
	}

	/**
	 * Adds taxonomy translation support to Polylang.
	 *
	 * @param array $taxonomies Registered taxonomies.
	 *
	 * @return array
	 */
	public function catalog_taxonomies( $taxonomies ) {
		$taxonomies[] = 'al_product-cat';

		return $taxonomies;
	}

	/**
	 * Adds post type translation support to Polylang.
	 *
	 * @param array $post_types Registered post types.
	 *
	 * @return array
	 */
	public function catalog_post_types( $post_types ) {
		$post_types[] = 'al_product';

		return $post_types;
	}

	/**
	 * Applies the current language to Ajax-driven catalog refresh requests.
	 *
	 * @param array|null $query_vars Current Ajax query vars.
	 *
	 * @return void
	 */
	public function ajax_apply_lang( $query_vars = null ) {
		if ( ! is_ic_ajax() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended -- ic_self_submit verifies the request before this hook receives Ajax query vars.
		$has_ajax_query_vars = isset( $_POST['query_vars'] ) && is_ic_ajax();
		if ( isset( $query_vars['ic_lang'] ) ) {
			$lang = $query_vars['ic_lang'];
		} elseif ( $has_ajax_query_vars ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- ic_self_submit verifies the request and the decoded array is sanitized immediately below.
			$ajax_query_vars = json_decode( wp_unslash( $_POST['query_vars'] ), true );
			if ( is_array( $ajax_query_vars ) ) {
				$ajax_query_vars = ic_sanitize( $ajax_query_vars );
			}
			if ( ! empty( $ajax_query_vars['ic_lang'] ) ) {
				$lang = $ajax_query_vars['ic_lang'];
			}
		}
		if ( ! empty( $lang ) ) {
			$_POST['lang']    = $lang;
			$_REQUEST['lang'] = $lang;
			do_action( 'wpml_switch_language', $lang );
		}
	}

	/**
	 * Stores the current language in catalog Ajax query vars.
	 *
	 * @param array $query_vars Query vars.
	 *
	 * @return array
	 */
	public function ajax_query_vars( $query_vars ) {
		$my_current_lang = apply_filters( 'wpml_current_language', null );
		if ( empty( $my_current_lang ) && defined( 'ICL_LANGUAGE_CODE' ) ) {
			$my_current_lang = ICL_LANGUAGE_CODE;
		}
		if ( $my_current_lang ) {
			$query_vars['ic_lang'] = $my_current_lang;
		}

		return $query_vars;
	}

	/**
	 * Filters translated listing slugs for multilingual archive pages.
	 *
	 * @param string $slug      Current slug.
	 * @param string $post_type Post type slug.
	 * @param string $lang      Language code.
	 *
	 * @return string
	 */
	public function ic_multilingual_listing_slug( $slug, $post_type, $lang ) {
		if ( ic_string_contains( $post_type, 'al_product' ) && ! empty( $lang ) ) {
			$listing_id  = get_product_listing_id();
			$new_page_id = icl_object_id( $listing_id, 'page', true, $lang );
			$post        = get_post( $new_page_id );
			$slug        = $post->post_name;
		}

		return $slug;
	}
}
