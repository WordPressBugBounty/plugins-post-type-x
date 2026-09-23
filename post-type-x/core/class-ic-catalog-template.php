<?php
/**
 * Catalog template manager.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Manages template loading and integration.
 */
class IC_Catalog_Template {

	/**
	 * Active template mode.
	 *
	 * @var string
	 */
	private $template = 'file';

	/**
	 * Sets up the template manager.
	 */
	public function __construct() {
		$this->files();

		add_action( 'ic_epc_loaded', array( $this, 'init' ) );
	}

	/**
	 * Registers template-related hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'ic_catalog_wp', array( 'ic_catalog_template', 'setup_postdata' ) );
		add_action( 'ic_catalog_wp', array( $this, 'load_templates' ) );

		add_filter( 'template_include', array( 'ic_catalog_template', 'home_product_listing_redirect' ), 5 );
		add_filter( 'redirect_canonical', array( 'ic_catalog_template', 'disable_redirect_canonical' ), 10, 2 );
	}

	/**
	 * Loads template dependencies.
	 *
	 * @return void
	 */
	public function files() {
		require_once AL_BASE_PATH . '/templates/templates-conditionals.php';
		require_once AL_BASE_PATH . '/templates/class-ic-catalog-theme-integration.php';
		require_once AL_BASE_PATH . '/templates/templates-files.php';
		require_once AL_BASE_PATH . '/templates/templates-functions.php';
		require_once AL_BASE_PATH . '/templates/templates-woo.php';
		require_once AL_BASE_PATH . '/templates/shortcode-catalog.php';
		require_once AL_BASE_PATH . '/templates/class-ic-catalog-block-templates.php';
	}

	/**
	 * Loads theme-specific template integrations.
	 *
	 * @return void
	 */
	public function load_templates() {
		if ( ! is_ic_shortcode_integration() ) {
			add_action( 'template_redirect', array( $this, 'initialize_product_adder_template' ), 99 );
		}

		$theme = get_option( 'template' );
		if ( 'twentyseventeen' === $theme ) {
			require_once AL_BASE_PATH . '/templates/class-ic-catalog-twenty-themes.php';
		}
	}

	/**
	 * Chooses the active product-adder template.
	 *
	 * @return void
	 */
	public function initialize_product_adder_template() {
		$theme           = get_option( 'template' );
		$woothemes       = array( 'canvas', 'woo', 'al' );
		$twentyeleven    = array( 'twentyeleven' );
		$twentyten       = array( 'twentyten' );
		$twentythirteen  = array( 'twentythirteen' );
		$twentyfourteen  = array( 'twentyfourteen' );
		$twentyfifteen   = array( 'twentyfifteen' );
		$twentysixteen   = array( 'twentysixteen' );
		$twentyseventeen = array( 'twentyseventeen' );
		$twentynineteen  = array( 'twentynineteen' );
		$third_party     = array( 'storefront' );
		$all_themes      = array_merge( $woothemes, $twentyeleven, $twentyten, $twentythirteen, $twentyfourteen, $twentyfifteen, $twentysixteen, $twentyseventeen, $twentynineteen, $third_party );
		if ( is_integraton_file_active() ) {
			$this->template = 'file';
		} elseif ( in_array( $theme, $all_themes, true ) ) {
			if ( in_array( $theme, $woothemes, true ) ) {
				$this->template = 'third-party/product-woo-adder.php';
			} elseif ( in_array( $theme, $third_party, true ) ) {
				$this->template = 'third-party/' . $theme . '.php';
			} elseif ( ic_string_contains( $theme, 'twenty' ) ) {
				$this->template = 'twenty/product-' . $theme . '-adder.php';
			}
		} elseif ( is_integraton_file_active( true ) && ic_is_woo_template_available() ) {
			$this->template = 'auto';
			add_action( 'wp', array( 'ic_catalog_template', 'woo_functions' ) );
		} elseif ( ic_is_woo_template_available() ) {
			$this->template = 'product-woo-adder.php';
			add_action( 'wp', array( 'ic_catalog_template', 'woo_functions' ) );
		} elseif ( 'simple' === get_integration_type() ) {
			$this->template = 'page';
			add_filter( 'the_content', array( 'ic_catalog_template', 'product_page_content' ) );
			add_action( 'wp', array( 'ic_catalog_template', 'remove_product_comments_rss' ) );
		} elseif ( 'theme' === get_integration_type() ) {
			$this->template = '';
			add_filter( 'the_content', array( 'ic_catalog_template', 'product_page_content' ) );
			add_action( 'wp', array( 'ic_catalog_template', 'remove_product_comments_rss' ) );
		} else {
			$this->template = 'product-adder.php';
		}
		if ( ! empty( $this->template ) ) {
			add_filter( 'template_include', array( $this, 'template_path' ), 99 );
		}
	}

	/**
	 * Loads WooCommerce template helpers when needed.
	 *
	 * @return void
	 */
	public static function woo_functions() {
		if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
			require_once AL_BASE_PATH . '/templates/templates-woo-functions.php';
		}
	}

	/**
	 * Returns the resolved template path.
	 *
	 * @param string $template Current template path.
	 * @return string
	 */
	public function template_path( $template ) {
		if ( is_ic_catalog_page() && ! is_ic_shortcode_integration() ) {
			$type = $this->template;
			if ( empty( $type ) ) {
				return $template;
			}
			switch ( $type ) {
				case 'file':
					return get_product_adder_path();
				case 'auto':
					return get_product_adder_path( true );
				case 'page':
					return $this->theme_page_template( $template );
				default:
					return __DIR__ . '/templates/' . $type;
			}
		}

		return $template;
	}

	/**
	 * Resolves the fallback page template.
	 *
	 * @param string $template Current template path.
	 * @return string
	 */
	public function theme_page_template( $template ) {
		if ( is_archive() || is_search() || is_tax() ) {
			$product_archive = get_product_listing_id();
			if ( ! empty( $product_archive ) && 'simple' !== get_integration_type() ) {
				wp_safe_redirect( get_permalink( $product_archive ) );
				exit;
			}
		}
		if ( file_exists( get_page_php_path() ) ) {
			return get_page_php_path();
		} elseif ( file_exists( get_index_php_path() ) ) {
			return get_index_php_path();
		}

		return $template;
	}

	/**
	 * Sets up post globals for catalog pages.
	 *
	 * @return void
	 */
	public static function setup_postdata() {
		if ( is_ic_catalog_page() ) {
			ic_set_product_id( get_the_ID() );
			global $post;
			if ( isset( $post->post_content ) && empty( $post->post_content ) && ( 'simple' === get_integration_type() || is_ic_shortcode_integration() ) ) {
				$post->post_content = ' ';
			}
			setup_postdata( $post );
		}
	}

	/**
	 * Replaces the content with the product-page output.
	 *
	 * @param string $content Current content.
	 * @return string
	 */
	public static function product_page_content( $content ) {
		$integration_type = get_integration_type();
		if ( is_main_query() && in_the_loop() && is_ic_product_page() && ! is_ic_shortcode_integration() && ( 'simple' === $integration_type || 'theme' === $integration_type ) ) {
			remove_filter( 'the_content', array( 'ic_catalog_template', 'product_page_content' ) );
			ob_start();
			content_product_adder();
			$content = ob_get_clean();
		}

		return $content;
	}

	/**
	 * Redirects the product listing page to the homepage catalog.
	 *
	 * @param string $template Current template path.
	 * @return string
	 */
	public static function home_product_listing_redirect( $template ) {
		if ( ! is_paged() && ! is_front_page() && is_ic_permalink_product_catalog() && is_product_listing_home_set() && is_post_type_archive( 'al_product' ) && ! is_search() ) {
			wp_safe_redirect( get_site_url(), 301 );
			exit;
		}

		return $template;
	}

	/**
	 * Disables invalid canonical redirects on catalog pagination.
	 *
	 * @param string|false $redirect_url Redirect URL.
	 * @param string       $requested_url Requested URL.
	 * @return string|false
	 */
	public static function disable_redirect_canonical( $redirect_url, $requested_url = '' ) {
		if ( is_paged() && is_front_page() && is_ic_permalink_product_catalog() && is_product_listing_home_set() ) {
			return false;
		}

		if ( ! is_paged() || ! is_ic_catalog_page() || ! is_string( $redirect_url ) || '' === $redirect_url || '' === $requested_url ) {
			return $redirect_url;
		}

		$redirect_parts  = wp_parse_url( $redirect_url );
		$requested_parts = wp_parse_url( $requested_url );

		if ( ! self::is_complete_canonical_url( $redirect_parts ) || ! self::is_complete_canonical_url( $requested_parts ) ) {
			return $redirect_url;
		}

		$requested_query = array();
		$redirect_query  = array();
		wp_parse_str( isset( $requested_parts['query'] ) ? $requested_parts['query'] : '', $requested_query );
		wp_parse_str( isset( $redirect_parts['query'] ) ? $redirect_parts['query'] : '', $redirect_query );

		if ( ! self::contains_registered_filter( $requested_query ) ) {
			return $redirect_url;
		}

		$same_location = strtolower( $requested_parts['scheme'] ) === strtolower( $redirect_parts['scheme'] )
			&& strtolower( $requested_parts['host'] ) === strtolower( $redirect_parts['host'] )
			&& self::canonical_url_port( $requested_parts ) === self::canonical_url_port( $redirect_parts )
			&& $requested_parts['path'] === $redirect_parts['path'];

		if ( $same_location && $requested_query == $redirect_query ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- Parsed query arrays are compared semantically so key order does not affect equivalence.
			return false;
		}

		return $redirect_url;
	}

	/**
	 * Checks whether parsed URL parts identify a complete HTTP location.
	 *
	 * @param array|false $parts Parsed URL parts.
	 * @return bool
	 */
	private static function is_complete_canonical_url( $parts ) {
		return is_array( $parts )
			&& isset( $parts['scheme'], $parts['host'], $parts['path'] )
			&& in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true )
			&& '' !== $parts['host']
			&& '' !== $parts['path'];
	}

	/**
	 * Returns the explicit or scheme-default URL port.
	 *
	 * @param array $parts Parsed URL parts.
	 * @return int
	 */
	private static function canonical_url_port( $parts ) {
		if ( isset( $parts['port'] ) ) {
			return (int) $parts['port'];
		}

		return 'https' === strtolower( $parts['scheme'] ) ? 443 : 80;
	}

	/**
	 * Checks whether a parsed query uses a registered catalog filter.
	 *
	 * @param array $query Parsed query data.
	 * @return bool
	 */
	private static function contains_registered_filter( $query ) {
		$registered_filters = get_active_product_filters();

		foreach ( array_keys( $query ) as $query_key ) {
			if ( in_array( $query_key, $registered_filters, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Removes RSS comment links on simple product pages.
	 *
	 * @param string $url Unused URL argument from the hook.
	 * @return void
	 */
	public static function remove_product_comments_rss( $url ) {
		if ( 'simple' === get_integration_type() && is_ic_product_page() ) {
			remove_action( 'wp_head', 'feed_links_extra', 3 );
		}
	}
}

if ( ! class_exists( 'ic_catalog_template', false ) ) {
	class_alias( 'IC_Catalog_Template', 'ic_catalog_template' );
}
