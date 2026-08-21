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
		add_filter( 'redirect_canonical', array( 'ic_catalog_template', 'disable_redirect_canonical' ) );
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
	 * Disables the wrong canonical redirect for home catalog pagination.
	 *
	 * @param string|false $redirect_url Redirect URL.
	 * @return string|false
	 */
	public static function disable_redirect_canonical( $redirect_url ) {
		if ( is_paged() && is_front_page() && is_ic_permalink_product_catalog() && is_product_listing_home_set() ) {
			$redirect_url = false;
		}

		return $redirect_url;
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
