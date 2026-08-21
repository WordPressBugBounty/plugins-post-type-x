<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- Legacy filename retained for compatibility.
/**
 * Woo template integration helpers.
 *
 * @version 1.1.3
 * @package ecommerce-product-catalog
 * @author  impleCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles Woo template integration.
 */
class IC_Woo_Templates {

	/**
	 * Nonce action guarding the template availability AJAX probe.
	 */
	const AJAX_NONCE_ACTION = 'ic_is_woo_template_available';

	/**
	 * Active theme slug.
	 *
	 * @var string
	 */
	public $theme;

	/**
	 * Whether the template file is blocked.
	 *
	 * @var bool
	 */
	public $block = false;

	/**
	 * Whether the template file is allowed.
	 *
	 * @var bool
	 */
	public $allow = false;

	/**
	 * Whether the template check has already been processed.
	 *
	 * @var bool
	 */
	public $processed = false;

	/**
	 * Boots the Woo template integration.
	 */
	public function __construct() {
		$this->setup();
		$this->hooks();
	}

	/**
	 * Registers the integration hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'apply_woo_templates' ) );
		add_action( 'wp_ajax_ic_is_woo_template_available', array( $this, 'ajax' ) );
		add_filter( 'admin_head', array( $this, 'ajax_script' ) );
	}

	/**
	 * Loads the saved template state.
	 */
	public function setup() {
		$this->theme = get_option( 'template' );
		// Stored options control the current allow/block decision.
		if ( get_option( 'ic_block_woo_template_file', 0 ) === $this->theme ) {
			$this->block     = true;
			$this->allow     = false;
			$this->processed = true;
		} elseif ( get_option( 'ic_allow_woo_template_file', 0 ) === $this->theme ) {
			$this->block     = false;
			$this->allow     = true;
			$this->processed = true;
		}
	}

	/**
	 * AJAX endpoint for template availability checks.
	 *
	 * The probe writes the `ic_allow_woo_template_file` / `ic_block_woo_template_file` options and
	 * may generate a theme template file, so it requires both a nonce and the settings capability.
	 */
	public function ajax() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- Custom capability is registered by the plugin.
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, self::AJAX_NONCE_ACTION ) || ! current_user_can( 'manage_product_settings' ) ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		$this->is_woo_template_available();
		wp_die();
	}

	/**
	 * Prints the admin-side AJAX probe.
	 */
	public function ajax_script() {
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- Custom capability is registered by the plugin.
		if ( $this->processed || ! current_user_can( 'manage_product_settings' ) || ! $this->let_template_error_check() ) {
			return;
		}
		?>
		<script>
			jQuery(document).ready(function () {
				var data = {
					'action': 'ic_is_woo_template_available',
					'nonce': '<?php echo esc_js( wp_create_nonce( self::AJAX_NONCE_ACTION ) ); ?>'
				};
				jQuery.post(ajaxurl, data);
			});
		</script>
			<?php
	}

	/**
	 * Hooks Woo wrapper callbacks when the template is available.
	 */
	public function apply_woo_templates() {
		if ( $this->let_template_error_check() && $this->is_woo_template_available() ) {
			add_action( 'before_product_archive', array( $this, 'woo_before_templates' ) );
			add_action( 'before_product_page', array( $this, 'woo_before_templates' ) );
			add_action( 'after_product_archive', array( $this, 'woo_after_templates' ) );
			add_action( 'after_product_page', array( $this, 'woo_after_templates' ) );
		}
	}

	/**
	 * Blocks the generated Woo template file.
	 */
	public function block() {
		update_option( 'ic_block_woo_template_file', $this->theme );
		delete_option( 'ic_allow_woo_template_file' );
		$this->block = true;
		$this->allow = false;
	}

	/**
	 * Allows the generated Woo template file.
	 */
	public function allow() {
		update_option( 'ic_allow_woo_template_file', $this->theme );
		delete_option( 'ic_block_woo_template_file' );
		$this->block = false;
		$this->allow = true;
	}

	/**
	 * Determines whether the template error check should run.
	 *
	 * @return bool
	 */
	public function let_template_error_check() {
		// Legacy PHP-version gating is intentionally disabled here.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presence-only read that can only make this check return false; the state-changing entry point is nonce- and capability-gated in ajax().
		if ( is_integration_mode_selected() || is_theme_implecode_supported() || is_ic_shortcode_integration( null, false ) || isset( $_GET['test_advanced'] ) ) {
			return false;
		}
		if ( is_ic_activation_hook() ) {
			return false;
		}

		return true;
	}

	/**
	 * Replays the WooCommerce opening wrapper hooks.
	 */
	public function woo_before_templates() {
		global $wp_filter;
		if ( isset( $wp_filter['woocommerce_before_main_content'] ) ) {
			if ( isset( $wp_filter['woocommerce_before_main_content']->callbacks ) && is_array( $wp_filter['woocommerce_before_main_content']->callbacks ) ) {
				$callbacks = $wp_filter['woocommerce_before_main_content']->callbacks;
				foreach ( $callbacks as $priority => $call ) {
					if ( 10 !== $priority ) {
						remove_all_actions( 'woocommerce_before_main_content', $priority );
					}
				}
			}
			remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
			do_action( 'woocommerce_before_main_content' );
		}
	}

	/**
	 * Replays the WooCommerce closing wrapper hooks.
	 */
	public function woo_after_templates() {
		global $wp_filter;
		if ( isset( $wp_filter['woocommerce_after_main_content'] ) ) {
			if ( isset( $wp_filter['woocommerce_before_main_content']->callbacks ) && is_array( $wp_filter['woocommerce_before_main_content']->callbacks ) ) {
				$callbacks = $wp_filter['woocommerce_after_main_content']->callbacks;
				foreach ( $callbacks as $priority => $call ) {
					if ( 10 !== $priority ) {
						remove_all_actions( 'woocommerce_after_main_content', $priority );
					}
				}
			}
			remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
			do_action( 'woocommerce_after_main_content' );
		}
	}

	/**
	 * Checks whether the Woo template file can be used.
	 *
	 * @return bool
	 */
	public function is_woo_template_available() {

		if ( $this->let_template_error_check() ) {

			if ( ! $this->processed && ! is_ic_ajax( 'ic_is_woo_template_available' ) ) {

				return false;
			}
			if ( $this->block ) {

				return false;
			}
			if ( $this->allow && ! is_ic_ajax( 'ic_is_woo_template_available' ) ) {
				return true;
			}
			ic_catalog_template::woo_functions();

			if ( $this->handle_error( 'woo_before_templates' ) ) {
				return true;
			} else {

				return $this->try_woo_template_file();
			}
		}

		return false;
	}

	/**
	 * Tries to create and validate a Woo template file.
	 *
	 * @return bool
	 */
	public function try_woo_template_file() {
		if ( ! $this->let_template_error_check() ) {
			return false;
		}
		if ( $this->block ) {
			return false;
		}
		$path = '';
		if ( is_readable( get_stylesheet_directory() . '/woocommerce.php' ) ) {
			$path = get_stylesheet_directory() . '/woocommerce.php';
		} elseif ( is_readable( get_template_directory() . '/woocommerce.php' ) ) {
			$path = get_template_directory() . '/woocommerce.php';
		}
		if ( ! empty( $path ) ) {
			$product_adder_path = get_product_adder_path( true, true );
			if ( ! file_exists( $product_adder_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local template file path.
				$main_file_contents = file_get_contents( $path );
				if ( ic_string_contains( $main_file_contents, 'woocommerce_content' ) && copy( $path, $product_adder_path ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading the generated local template file.
					$file_contents = file_get_contents( $product_adder_path );
					if ( ic_string_contains( $file_contents, 'woocommerce_content' ) ) {
						$new_file_contents = str_replace( 'woocommerce_content', 'content_product_adder', $file_contents );
						// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing the generated local template file.
						file_put_contents( $product_adder_path, $new_file_contents );
						$this->handle_error( $product_adder_path );

						return true;
					}
				}
			} else {
				$this->handle_error( $product_adder_path );

				return true;
			}
		} else {
			$this->block();
		}

		return false;
	}

	/**
	 * Handles template generation failures.
	 *
	 * @param string $product_adder_path Generated template path or method name.
	 * @return bool
	 */
	public function handle_error( $product_adder_path ) {
		if ( $this->allow ) {
			return true;
		}

		if ( $this->check_for_errors( $product_adder_path ) ) {
			if ( is_file( $product_adder_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Removing the generated local template file.
				unlink( $product_adder_path );
			}
			if ( ! method_exists( $this, $product_adder_path ) ) {
				$this->block();
			} else {
				return false;
			}
		} else {
			$this->allow();
		}

		if ( ! method_exists( $this, $product_adder_path ) ) {
			ic_redirect_to_same();
		}

		return true;
	}

	/**
	 * Executes a generated template and checks it for runtime errors.
	 *
	 * @param string $path File path or method name to execute.
	 * @return bool
	 * @throws Error When the generated content cannot be rendered.
	 */
	public function check_for_errors( $path ) {
		if ( is_ic_activation_hook() ) {
			return false;
		}
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Runtime validation temporarily promotes PHP errors to exceptions.
		set_error_handler( array( $this, 'error_handler' ) );
		ic_save_global( 'check_for_errors_path', $path );
		register_shutdown_function( array( $this, 'shutdown_get_errors' ) );
		try {
			ob_start();

			if ( is_file( $path ) ) {

				include $path;
			} elseif ( method_exists( $this, $path ) ) {
				$this->$path();
			} else {

				throw new Error( 'No Catalog Content' );
			}
			$content = ob_get_clean();
			if ( empty( $content ) ) {

				throw new Error( 'No Catalog Content' );
			}
		} catch ( Throwable $e ) {
			restore_error_handler();

			return true;
		} catch ( Exception $e ) {
			restore_error_handler();

			return true;
		} catch ( Error $e ) {
			restore_error_handler();

			return true;
		}
		restore_error_handler();

		return false;
	}

	/**
	 * Converts PHP runtime errors to exceptions.
	 *
	 * @param int    $errno   PHP error level.
	 * @param string $errstr  PHP error message.
	 * @param string $errfile Source file.
	 * @param int    $errline Source line.
	 * @return bool
	 * @throws ErrorException Converted runtime exception.
	 */
	public function error_handler( $errno, $errstr, $errfile, $errline ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting,WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting -- Suppressed errors should bypass the runtime validation handler.
		if ( 0 === error_reporting() ) {
			return false;
		}

		throw new ErrorException( esc_html( $errstr ), 0, absint( $errno ), esc_html( $errfile ), absint( $errline ) );
	}

	/**
	 * Handles fatal errors captured during template execution.
	 */
	public function shutdown_get_errors() {
		$error = error_get_last();
		if ( isset( $error['type'] ) && ( E_ERROR === $error['type'] || E_WARNING === $error['type'] ) ) {
			$path = ic_get_global( 'check_for_errors_path' );
			if ( ! empty( $path ) ) {
				if ( is_file( $path ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Removing the generated local template file after a fatal runtime error.
					unlink( $path );
				}
				$this->block();
				exit( 1 );
			}
		}
	}
}

global $ic_woo_templates;
$ic_woo_templates = new IC_Woo_Templates();

/**
 * Proxy helper for Woo template availability checks.
 *
 * @return bool
 */
function ic_is_woo_template_available() { // phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed -- Legacy global proxy function retained for compatibility.
	global $ic_woo_templates;
	if ( ! empty( $ic_woo_templates ) ) {
		return $ic_woo_templates->is_woo_template_available();
	}

	return false;
}
