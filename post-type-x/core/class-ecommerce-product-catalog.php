<?php
/**
 * Main plugin bootstrap class.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Main plugin bootstrap class.
 *
 * @since 2.4.7
 */
final class eCommerce_Product_Catalog {

	/**
	 * The one true plugin instance.
	 *
	 * @var eCommerce_Product_Catalog
	 * @since 2.4.7
	 */
	private static $instance;

	/**
	 * Gets the main plugin instance.
	 *
	 * Ensures that only one plugin instance exists in memory at any one time.
	 *
	 * @return eCommerce_Product_Catalog
	 * @since 2.4.7
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof self ) ) {
			self::$instance = new self();
			self::$instance->setup_constants();
			require_once AL_BASE_PATH . '/includes/tracking.php';

			add_action( 'admin_enqueue_scripts', array( self::$instance, 'implecode_run_admin_styles' ) );
			add_action( 'admin_init', array( self::$instance, 'implecode_register_styles' ) );
			add_action( 'wp', array( self::$instance, 'implecode_register_styles' ) );
			add_action( 'admin_init', array( self::$instance, 'implecode_register_admin_styles' ) );
			add_action( 'wp_enqueue_scripts', array( self::$instance, 'implecode_enqueue_styles' ), 9 );
			add_action( 'init', array( self::$instance, 'load_textdomain' ) );
			add_action( 'after_setup_theme', array( self::$instance, 'content' ), -2 );
		}

		return self::$instance;
	}

	/**
	 * Loads the plugin internals after theme setup.
	 *
	 * @return void
	 */
	public static function content() {
		self::$instance->includes();
		self::$instance->implecode_addons();
		do_action( 'ic_epc_loaded' );
	}

	/**
	 * Disable cloning.
	 *
	 * @return void
	 * @since 2.4.7
	 * @access protected
	 */
	public function __clone() {
		// Cloning instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'post-type-x' ), '4.3' );
	}

	/**
	 * Disable unserializing of the class.
	 *
	 * @return void
	 * @since 2.4.7
	 * @access protected
	 */
	public function __wakeup() {
		// Unserializing instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'post-type-x' ), '4.3' );
	}

	/**
	 * Setup plugin constants.
	 *
	 * @access private
	 * @return void
	 * @since 2.4.7
	 */
	private function setup_constants() {
		$plugin_main_file = __DIR__ . '/ecommerce-product-catalog.php';
		if ( ! defined( 'AL_BASE_PATH' ) ) {
			define( 'AL_BASE_PATH', untrailingslashit( plugin_dir_path( $plugin_main_file ) ) );
		}
		if ( ! defined( 'AL_PLUGIN_BASE_PATH' ) ) {
			define( 'AL_PLUGIN_BASE_PATH', plugins_url( '/', $plugin_main_file ) );
		}
		if ( ! defined( 'AL_PLUGIN_MAIN_FILE' ) ) {
			define( 'AL_PLUGIN_MAIN_FILE', $plugin_main_file );
		}
		if ( ! defined( 'AL_BASE_TEMPLATES_PATH' ) ) {
			define( 'AL_BASE_TEMPLATES_PATH', untrailingslashit( plugin_dir_path( $plugin_main_file ) ) );
		}
		if ( ! defined( 'IC_EPC_VERSION' ) ) {
			if ( function_exists( 'get_file_data' ) ) {
				$default_headers = array(
					'Version' => 'Version',
				);
				$plugin_data     = get_file_data( AL_PLUGIN_MAIN_FILE, $default_headers, 'plugin' );
			}
			if ( ! empty( $plugin_data['Version'] ) ) {
				define( 'IC_EPC_VERSION', $plugin_data['Version'] );
			} else {
				define( 'IC_EPC_VERSION', '2.7.21' );
			}
		}
		if ( ! defined( 'IC_CATALOG_VERSION' ) ) {
			define( 'IC_CATALOG_VERSION', IC_EPC_VERSION );
		}
		if ( ! defined( 'IC_EPC_FIRST_VERSION' ) ) {
			$first_version = (string) get_option( 'first_activation_version', IC_EPC_VERSION );
			define( 'IC_EPC_FIRST_VERSION', $first_version );
		}
	}

	/**
	 * Include required files.
	 *
	 * @access private
	 * @return void
	 * @since 2.4.7
	 */
	private function includes() {
		require_once AL_BASE_PATH . '/ic/index.php';
		require_once AL_BASE_PATH . '/functions/activation.php';

		require_once AL_BASE_PATH . '/templates.php';

		require_once AL_BASE_PATH . '/functions/index.php';
		require_once AL_BASE_PATH . '/includes/index.php';

		require_once AL_BASE_PATH . '/includes/blocks/index.php';

		require_once AL_BASE_PATH . '/theme-product_adder_support.php';

		require_once AL_BASE_PATH . '/includes/product-settings.php';
		require_once AL_BASE_PATH . '/functions/base.php';
		require_once AL_BASE_PATH . '/functions/capabilities.php';
		require_once AL_BASE_PATH . '/functions/functions.php';
		require_once AL_BASE_PATH . '/config/const.php';

		require_once AL_BASE_PATH . '/functions/shortcodes.php';
		require_once AL_BASE_PATH . '/ext-comp/index.php';

		require_once AL_BASE_PATH . '/modules/index.php';
		do_action( 'ic_epc_included' );
	}

	/**
	 * Registers catalog styles and scripts.
	 *
	 * @return void
	 */
	public function implecode_register_styles() {
		wp_register_style( 'al_product_styles', AL_PLUGIN_BASE_PATH . 'css/al_product.min.css' . ic_filemtime( AL_BASE_PATH . '/css/al_product.min.css' ), array(), IC_EPC_VERSION );
		do_action( 'register_catalog_styles' );
		wp_register_script(
			'ic-integration',
			AL_PLUGIN_BASE_PATH . 'js/integration-script.min.js' . ic_filemtime( AL_BASE_PATH . '/js/integration-script.min.js' ),
			array(
				'al_product_scripts',
				'jquery-ui-tooltip',
			),
			IC_EPC_VERSION,
			true
		);
		wp_register_style( 'ic_chosen', AL_PLUGIN_BASE_PATH . 'js/chosen/chosen.css' . ic_filemtime( AL_BASE_PATH . '/js/chosen/chosen.css' ), array(), IC_EPC_VERSION );
		wp_register_style(
			'al_product_admin_styles',
			AL_PLUGIN_BASE_PATH . 'css/al_product-admin.min.css' . ic_filemtime( AL_BASE_PATH . '/css/al_product-admin.min.css' ),
			array(
				'wp-color-picker',
				'ic_chosen',
				'editor-buttons',
			),
			IC_EPC_VERSION
		);
		wp_register_style( 'ic_range_slider', AL_PLUGIN_BASE_PATH . 'js/range-slider/css/ion.rangeSlider.css' . ic_filemtime( AL_BASE_PATH . '/js/range-slider/css/ion.rangeSlider.css' ), array(), IC_EPC_VERSION );
		wp_register_script( 'ic_ion_range_slider', AL_PLUGIN_BASE_PATH . 'js/range-slider/ion.rangeSlider.min.js' . ic_filemtime( AL_BASE_PATH . '/js/range-slider/ion.rangeSlider.min.js' ), array( 'jquery' ), IC_EPC_VERSION, true );
		wp_register_script( 'ic_range_slider', AL_PLUGIN_BASE_PATH . 'js/range-slider.min.js' . ic_filemtime( AL_BASE_PATH . '/js/range-slider.min.js' ), array( 'ic_ion_range_slider' ), IC_EPC_VERSION, true );
	}

	/**
	 * Adds catalog admin styles and scripts.
	 *
	 * @return void
	 */
	public function implecode_run_admin_styles() {
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'al_product_styles' );
		wp_enqueue_style( 'al_product_admin_styles' );
		do_action( 'enqueue_catalog_admin_styles' );
		if ( function_exists( 'get_current_screen' ) ) {
			$current_screen = get_current_screen();
		}
		if ( is_ic_admin_page() || ( ! empty( $current_screen->id ) && 'widgets' === $current_screen->id ) ) {
			wp_enqueue_script( 'al_product_admin-scripts' );
			wp_localize_script(
				'al_product_admin-scripts',
				'ic_catalog',
				apply_filters(
					'ic_catalog_admin_scrits_localize',
					array(
						'import_screen_url' => current_user_can( 'manage_product_settings' ) ? admin_url( 'edit.php?post_type=al_product&page=product-settings.php&tab=product-settings&submenu=csv' ) : '',
						'import_export'     => __( 'Import / Export', 'post-type-x' ),
						'nonce'             => wp_create_nonce( 'ic-ajax-nonce' ),
						'formbuilder_state_nonce' => wp_create_nonce( 'ic_state_dropdown' ),
					)
				)
			);
			do_action( 'enqueue_catalog_admin_scripts' );
		}
	}

	/**
	 * Registers catalog admin styles and scripts.
	 *
	 * @return void
	 */
	public function implecode_register_admin_styles() {
		wp_register_style( 'ic_chosen', AL_PLUGIN_BASE_PATH . 'js/chosen/chosen.css' . ic_filemtime( AL_BASE_PATH . '/js/chosen/chosen.css' ), array(), IC_EPC_VERSION );
		wp_register_style(
			'al_product_admin_styles',
			AL_PLUGIN_BASE_PATH . 'css/al_product-admin.min.css' . ic_filemtime( AL_BASE_PATH . '/css/al_product-admin.min.css' ),
			array(
				'wp-color-picker',
				'ic_chosen',
			),
			IC_EPC_VERSION
		);
		wp_register_script( 'jquery-validate', AL_PLUGIN_BASE_PATH . 'js/jquery-validate/jquery.validate.min.js' . ic_filemtime( AL_BASE_PATH . '/js/jquery-validate/jquery.validate.min.js' ), array( 'jquery' ), '1.19.5', true );
		wp_register_script( 'jquery-validate-add', AL_PLUGIN_BASE_PATH . 'js/jquery-validate/additional-methods.min.js' . ic_filemtime( AL_BASE_PATH . '/js/jquery-validate/additional-methods.min.js' ), array( 'jquery-validate' ), '1.19.5', true );
		wp_register_script( 'ic_chosen', AL_PLUGIN_BASE_PATH . 'js/chosen/chosen.jquery.js' . ic_filemtime( AL_BASE_PATH . '/js/chosen/chosen.jquery.js' ), array( 'jquery' ), IC_EPC_VERSION, true );
		wp_register_script(
			'al_product_admin-scripts',
			AL_PLUGIN_BASE_PATH . 'js/admin-scripts.min.js' . ic_filemtime( AL_BASE_PATH . '/js/admin-scripts.min.js' ),
			array(
				'jquery-ui-sortable',
				'jquery-ui-tooltip',
				'jquery-validate-add',
				'wp-color-picker',
				'jquery-ui-autocomplete',
				'ic_chosen',
			),
			IC_EPC_VERSION,
			true
		);
		do_action( 'register_catalog_admin_styles' );
	}

	/**
	 * Adds catalog front-end styles and scripts.
	 *
	 * @return void
	 */
	public function implecode_enqueue_styles() {
		if ( ! did_action( 'ic_catalog_localize_scripts' ) ) {
			do_action( 'ic_catalog_localize_scripts' );
		}
		if ( function_exists( 'ic_maybe_engueue_all' ) && ic_maybe_engueue_all() ) {
			if ( function_exists( 'ic_enqueue_main_catalog_js_css' ) ) {
				ic_enqueue_main_catalog_js_css();
			}

			if ( is_ic_integration_wizard_page() ) {
				wp_enqueue_style( 'al_product_admin_styles' );
				wp_enqueue_script( 'ic-integration' );
			}
			do_action( 'enqueue_catalog_scripts' );
		}
	}

	/**
	 * Loads plugin textdomain.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		if ( ! defined( 'IC_EPC_TEXTDOMAIN_PATH' ) ) {
			define( 'IC_EPC_TEXTDOMAIN_PATH', dirname( plugin_basename( AL_PLUGIN_MAIN_FILE ) ) . '/lang' );
		}
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Bundled translations and non-WordPress.org installs require the legacy language-directory fallback on WordPress 5.9.
		load_plugin_textdomain( 'post-type-x', false, IC_EPC_TEXTDOMAIN_PATH );
	}

	/**
	 * Adds support for impleCode addons.
	 *
	 * @return void
	 */
	public function implecode_addons() {
		if ( ! is_network_admin() ) {
			do_action( 'ecommerce-prodct-catalog-addons' );
			do_action( 'ecommerce_product_catalog_addons_v3' );
			do_action( 'implecode_addons' );
		}
	}
}
