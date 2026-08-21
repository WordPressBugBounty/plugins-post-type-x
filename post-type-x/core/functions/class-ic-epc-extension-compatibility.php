<?php
/**
 * EPC extension compatibility bootstrap.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles EPC extension compatibility checks before add-on hooks fire.
 */
class IC_EPC_Extension_Compatibility {
	/**
	 * Blocked extensions tracked for the current request.
	 *
	 * @var array
	 */
	private static $blocked_extensions = array();

	/**
	 * Whether compatibility checks already ran in the current request.
	 *
	 * @var bool
	 */
	private static $compatibility_checked = false;

	/**
	 * Cached plugin header data keyed by absolute plugin file path.
	 *
	 * @var array
	 */
	private static $plugin_header_cache = array();

	/**
	 * Incompatible extensions tracked for the plugins screen.
	 *
	 * @var array
	 */
	private static $plugins_screen_extensions = array();

	/**
	 * Registers runtime hooks for the compatibility manager.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'blocked_extensions_notice' ) );
		add_action( 'current_screen', array( __CLASS__, 'maybe_hook_plugins_screen_row_notices' ) );

		foreach ( self::addon_hook_names() as $hook ) {
			add_action( $hook, array( __CLASS__, 'maybe_block_incompatible_addons' ), -99 );
		}
	}

	/**
	 * Resets request-scoped runtime state.
	 *
	 * @return void
	 */
	public static function reset_runtime_state() {
		self::$blocked_extensions      = array();
		self::$compatibility_checked   = false;
		self::$plugin_header_cache     = array();
		self::$plugins_screen_extensions = array();
	}

	/**
	 * Blocks incompatible extension callbacks before EPC add-on hooks are fired.
	 *
	 * @return void
	 */
	public static function maybe_block_incompatible_addons() {
		if ( ! apply_filters( 'ic_epc_remove_incompatible_addons', true ) ) {
			return;
		}

		if ( self::$compatibility_checked ) {
			return;
		}

		self::$compatibility_checked = true;
		self::$blocked_extensions    = array();

		$active_plugins = self::active_extension_plugins();
		if ( empty( $active_plugins ) ) {
			return;
		}

		foreach ( self::addon_hook_names() as $hook ) {
			self::block_incompatible_hook_callbacks( $hook, $active_plugins );
		}
	}

	/**
	 * Returns blocked extensions for the current request.
	 *
	 * @return array
	 */
	public static function get_blocked_extensions() {
		return self::$blocked_extensions;
	}

	/**
	 * Hooks plugin row notices for incompatible active extensions.
	 *
	 * @param object $screen Current admin screen object.
	 *
	 * @return void
	 */
	public static function maybe_hook_plugins_screen_row_notices( $screen ) {
		if ( empty( $screen->id ) || ! in_array( $screen->id, array( 'plugins', 'plugins-network' ), true ) ) {
			return;
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		self::$plugins_screen_extensions = self::incompatible_active_plugins();
		if ( empty( self::$plugins_screen_extensions ) ) {
			return;
		}

		foreach ( array_keys( self::$plugins_screen_extensions ) as $plugin_basename ) {
			add_action( 'after_plugin_row_' . $plugin_basename, array( __CLASS__, 'render_plugin_row_notice' ), 10, 3 );
		}
	}

	/**
	 * Displays an admin notice when EPC blocks incompatible extension callbacks.
	 *
	 * @return void
	 */
	public static function blocked_extensions_notice() {
		$blocked_extensions = self::get_blocked_extensions();
		if ( empty( $blocked_extensions ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		?>
		<div class="notice notice-warning">
			<p>
				<?php
				printf(
				/* translators: Placeholders are substituted with runtime values. */
					esc_html__( 'eCommerce Product Catalog keeps incompatible extensions inactive until they are updated to avoid compatibility errors for EPC %s.', 'post-type-x' ),
					esc_html( IC_EPC_VERSION )
				);
				?>
			</p>
			<ul style="list-style:disc;padding-left:20px;">
				<?php foreach ( $blocked_extensions as $extension ) : ?>
					<?php
					$rule_label = self::compatibility_rule_label( $extension );
					$item       = $extension['name'] . ' - ' . $rule_label . ' - ' . sprintf(
						__( 'inactive until updated', 'post-type-x' )
					);

					if ( ! empty( $extension['note'] ) ) {
						$item .= ' - ' . $extension['note'];
					}
					?>
					<li><?php echo esc_html( $item ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	/**
	 * Renders the plugins screen notice row for one incompatible extension.
	 *
	 * @param string $plugin_file Plugin basename.
	 *
	 * @return void
	 */
	public static function render_plugin_row_notice( $plugin_file ) {
		if ( empty( self::$plugins_screen_extensions[ $plugin_file ] ) ) {
			return;
		}

		$extension  = self::$plugins_screen_extensions[ $plugin_file ];
		$rule_label = self::compatibility_rule_label( $extension );
		$message    = sprintf(
			/* translators: 1: extension name, 2: EPC version, 3: compatibility rule label. */
			__( '%1$s is inactive in eCommerce Product Catalog %2$s until it is updated to a compatible version (%3$s). Update the extension to avoid compatibility errors.', 'post-type-x' ),
			$extension['name'],
			IC_EPC_VERSION,
			$rule_label
		);

		if ( ! empty( $extension['note'] ) ) {
			$message .= ' ' . $extension['note'];
		}

		$screen  = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$columns = is_object( $screen ) ? get_column_headers( $screen ) : array();
		$colspan = ! empty( $columns ) ? count( $columns ) : 4;
		?>
		<tr class="plugin-update-tr active ic-epc-inactive-extension-tr">
			<td colspan="<?php echo esc_attr( $colspan ); ?>" class="plugin-update colspanchange">
				<div class="notice inline notice-error">
					<p>
						<strong><?php esc_html_e( 'EPC Compatibility Warning:', 'post-type-x' ); ?></strong>
						<?php echo ' ' . esc_html( $message ); ?>
					</p>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Returns the EPC add-on bootstrap hooks.
	 *
	 * @return string[]
	 */
	private static function addon_hook_names() {
		return array(
			'ecommerce-prodct-catalog-addons',
			'ecommerce_product_catalog_addons_v3',
			'implecode_addons',
		);
	}

	/**
	 * Returns active plugin metadata keyed by plugin basename.
	 *
	 * @return array[]
	 */
	private static function active_extension_plugins() {
		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			$active_plugins = array_merge(
				$active_plugins,
				array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) )
			);
		}

		$active_plugins = array_unique( $active_plugins );
		$plugins        = array();

		foreach ( $active_plugins as $plugin_basename ) {
			if ( 'ecommerce-product-catalog/ecommerce-product-catalog.php' === $plugin_basename ) {
				continue;
			}

			$plugin_file = wp_normalize_path( WP_PLUGIN_DIR . '/' . $plugin_basename );
			if ( ! file_exists( $plugin_file ) ) {
				continue;
			}

			$plugins[ $plugin_basename ] = array(
				'basename' => $plugin_basename,
				'file'     => $plugin_file,
				'dir'      => trailingslashit( wp_normalize_path( dirname( $plugin_file ) ) ),
				'data'     => self::get_plugin_header_data( $plugin_file ),
			);
		}

		return $plugins;
	}

	/**
	 * Returns plugin headers used by the compatibility gate.
	 *
	 * @param string $plugin_file Absolute plugin main file path.
	 *
	 * @return array
	 */
	private static function get_plugin_header_data( $plugin_file ) {
		$plugin_file = wp_normalize_path( $plugin_file );
		if ( isset( self::$plugin_header_cache[ $plugin_file ] ) ) {
			return self::$plugin_header_cache[ $plugin_file ];
		}

		self::$plugin_header_cache[ $plugin_file ] = get_file_data(
			$plugin_file,
			array(
				'Name'             => 'Plugin Name',
				'Version'          => 'Version',
				'EPCCompatibility' => 'EPC Compatibility',
			),
			'plugin'
		);

		return self::$plugin_header_cache[ $plugin_file ];
	}

	/**
	 * Resolves compatibility for one active extension.
	 *
	 * @param array $plugin Active plugin metadata.
	 *
	 * @return array
	 */
	private static function resolve_extension_compatibility( array $plugin ) {
		$source = 'none';
		$rule   = '';
		$note   = '';
		$kind   = 'epc_rule';

		if ( ! empty( $plugin['data']['EPCCompatibility'] ) ) {
			$source = 'header';
			$rule   = trim( $plugin['data']['EPCCompatibility'] );
		} else {
			$fallback_map = self::legacy_extension_compatibility_map();
			if ( isset( $fallback_map[ $plugin['basename'] ] ) ) {
				$fallback = $fallback_map[ $plugin['basename'] ];
				$source   = 'fallback';
				if ( is_array( $fallback ) ) {
					if ( ! empty( $fallback['minimum_version'] ) ) {
						$minimum_version = (string) $fallback['minimum_version'];
						$rule            = $minimum_version . '+';
						$note            = isset( $fallback['note'] ) ? (string) $fallback['note'] : '';
						$kind            = 'minimum_version';

						if ( empty( $plugin['data']['Version'] ) || version_compare( $plugin['data']['Version'], $minimum_version, '<' ) ) {
							return array(
								'status' => 'incompatible',
								'source' => $source,
								'rule'   => $rule,
								'note'   => $note,
								'kind'   => $kind,
							);
						}

						return array(
							'status' => 'compatible',
							'source' => $source,
							'rule'   => $rule,
							'note'   => $note,
							'kind'   => $kind,
						);
					}
					$rule = isset( $fallback['rule'] ) ? trim( (string) $fallback['rule'] ) : '';
					$note = isset( $fallback['note'] ) ? (string) $fallback['note'] : '';
				} else {
					$rule = trim( (string) $fallback );
				}
			}
		}

		/**
		 * Filters the resolved EPC compatibility rule for one plugin.
		 *
		 * @param string $rule Rule string.
		 * @param string $plugin['basename'] Active plugin basename.
		 * @param string $source Rule source: header, fallback, or none.
		 */
		$rule = trim( (string) apply_filters( 'ic_epc_extension_compatibility_rule', $rule, $plugin['basename'], $source ) );

		if ( '' === $rule ) {
			return array(
				'status' => 'unknown',
				'source' => $source,
				'rule'   => '',
				'note'   => $note,
				'kind'   => $kind,
			);
		}

		$matches = self::matches_compatibility_rule( $rule, IC_EPC_VERSION );
		if ( null === $matches ) {
			return array(
				'status' => 'unknown',
				'source' => $source,
				'rule'   => $rule,
				'note'   => $note,
				'kind'   => $kind,
			);
		}

		return array(
			'status' => $matches ? 'compatible' : 'incompatible',
			'source' => $source,
			'rule'   => $rule,
			'note'   => $note,
			'kind'   => $kind,
		);
	}

	/**
	 * Returns legacy fallback rules for extensions without the new header tag.
	 *
	 * @return array
	 */
	private static function legacy_extension_compatibility_map() {
		$fallbacks = array(
			'catalog-users-manager/catalog-users-manager.php'     => array(
				'minimum_version' => '1.1.9',
				'note'            => 'Catalog Users Manager 1.1.9 or newer is required for the current EPC version.',
			),
			'implecode-shopping-cart/implecode-shopping-cart.php' => array(
				'minimum_version' => '2.12.28',
				'note'            => 'Shopping Cart PRO 2.12.28 or newer is required for the current EPC version.',
			),
			'implecode-quote-cart/implecode-quote-cart.php'       => array(
				'minimum_version' => '2.6.6',
				'note'            => 'Quote Cart PRO 2.6.6 or newer is required for the current EPC version.',
			),
		);

		/**
		 * Filters legacy EPC compatibility fallbacks keyed by plugin basename.
		 *
		 * @param array $fallbacks Legacy fallback rules.
		 */
		return apply_filters( 'ic_epc_legacy_extension_compatibility_map', $fallbacks );
	}

	/**
	 * Returns active extensions whose rules mark them incompatible with the current EPC version.
	 *
	 * @return array
	 */
	private static function incompatible_active_plugins() {
		$incompatible_plugins = array();

		foreach ( self::active_extension_plugins() as $plugin_basename => $plugin ) {
			$compatibility = self::resolve_extension_compatibility( $plugin );
			if ( 'incompatible' !== $compatibility['status'] ) {
				continue;
			}

			$incompatible_plugins[ $plugin_basename ] = array(
				'name'   => ! empty( $plugin['data']['Name'] ) ? $plugin['data']['Name'] : $plugin_basename,
				'rule'   => $compatibility['rule'],
				'source' => $compatibility['source'],
				'note'   => $compatibility['note'],
				'kind'   => isset( $compatibility['kind'] ) ? $compatibility['kind'] : 'epc_rule',
			);
		}

		return $incompatible_plugins;
	}

	/**
	 * Returns the display label for a compatibility requirement.
	 *
	 * @param array $extension Compatibility data.
	 *
	 * @return string
	 */
	private static function compatibility_rule_label( array $extension ) {
		if ( isset( $extension['kind'] ) && 'minimum_version' === $extension['kind'] ) {
			return sprintf(
				/* translators: %s: minimum required extension version. */
				__( 'Required extension version: %s', 'post-type-x' ),
				$extension['rule']
			);
		}

		if ( 'header' === $extension['source'] ) {
			return sprintf(
				/* translators: %s: compatibility rule. */
				__( 'EPC Compatibility: %s', 'post-type-x' ),
				$extension['rule']
			);
		}

		return sprintf(
			/* translators: %s: compatibility rule. */
			__( 'Legacy EPC compatibility rule: %s', 'post-type-x' ),
			$extension['rule']
		);
	}

	/**
	 * Matches a compatibility rule against the current EPC version.
	 *
	 * Supported formats:
	 * - `3.4.13`
	 * - `3.4.*`
	 * - `>= 3.4.0, < 3.5.0`
	 * - `< 3.4.13`
	 *
	 * @param string $rule            Compatibility rule.
	 * @param string $current_version Current EPC version.
	 *
	 * @return bool|null
	 */
	private static function matches_compatibility_rule( $rule, $current_version ) {
		$clauses = preg_split( '/\s*,\s*|\s*&&\s*/', trim( $rule ) );
		if ( empty( $clauses ) ) {
			return null;
		}

		foreach ( $clauses as $clause ) {
			$clause = trim( $clause );
			if ( '' === $clause ) {
				continue;
			}

			if ( preg_match( '/^([0-9][0-9A-Za-z.\-_]*)\.\*$/', $clause, $matches ) ) {
				$prefix = $matches[1] . '.';
				if ( 0 !== strpos( $current_version, $prefix ) ) {
					return false;
				}
				continue;
			}

			if ( preg_match( '/^(<=|>=|<|>|==|=|!=)\s*([0-9][0-9A-Za-z.\-_]*)$/', $clause, $matches ) ) {
				$operator = '=' === $matches[1] ? '==' : $matches[1];
				if ( ! version_compare( $current_version, $matches[2], $operator ) ) {
					return false;
				}
				continue;
			}

			if ( preg_match( '/^[0-9][0-9A-Za-z.\-_]*$/', $clause ) ) {
				if ( ! version_compare( $current_version, $clause, '==' ) ) {
					return false;
				}
				continue;
			}

			return null;
		}

		return true;
	}

	/**
	 * Removes incompatible callbacks from one EPC add-on hook.
	 *
	 * @param string  $hook           Hook name.
	 * @param array[] $active_plugins Active plugins keyed by basename.
	 *
	 * @return void
	 */
	private static function block_incompatible_hook_callbacks( $hook, array $active_plugins ) {
		global $wp_filter;

		if ( empty( $wp_filter[ $hook ] ) || empty( $wp_filter[ $hook ]->callbacks ) ) {
			return;
		}

		$compatibility_cache = array();
		$removals            = array();

		foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback_data ) {
				if ( empty( $callback_data['function'] ) ) {
					continue;
				}

				$plugin = self::find_callback_owner_plugin( $callback_data['function'], $active_plugins );
				if ( empty( $plugin ) ) {
					continue;
				}

				if ( ! isset( $compatibility_cache[ $plugin['basename'] ] ) ) {
					$compatibility_cache[ $plugin['basename'] ] = self::resolve_extension_compatibility( $plugin );
				}

				$compatibility = $compatibility_cache[ $plugin['basename'] ];
				if ( 'incompatible' !== $compatibility['status'] ) {
					continue;
				}

				$removals[] = array(
					'priority'      => $priority,
					'callback'      => $callback_data['function'],
					'plugin'        => $plugin,
					'compatibility' => $compatibility,
				);
			}
		}

		foreach ( $removals as $removal ) {
			remove_action( $hook, $removal['callback'], $removal['priority'] );
			self::track_blocked_extension( $removal['plugin'], $hook, $removal['callback'], $removal['compatibility'] );
		}
	}

	/**
	 * Finds the owning active plugin for a hooked callback.
	 *
	 * @param callable $callback       Hook callback.
	 * @param array[]  $active_plugins Active plugins keyed by basename.
	 *
	 * @return array|null
	 */
	private static function find_callback_owner_plugin( $callback, array $active_plugins ) {
		$callback_file = self::get_callback_file( $callback );
		if ( '' === $callback_file ) {
			return null;
		}

		$owner      = null;
		$owner_size = 0;

		foreach ( $active_plugins as $plugin ) {
			if ( 0 === strpos( $callback_file, $plugin['dir'] ) ) {
				$plugin_dir_size = strlen( $plugin['dir'] );
				if ( $plugin_dir_size > $owner_size ) {
					$owner      = $plugin;
					$owner_size = $plugin_dir_size;
				}
			}
		}

		return $owner;
	}

	/**
	 * Resolves the source file that defines a hooked callback.
	 *
	 * @param callable $callback Hook callback.
	 *
	 * @return string
	 */
	private static function get_callback_file( $callback ) {
		try {
			if ( is_string( $callback ) ) {
				if ( false !== strpos( $callback, '::' ) ) {
					$callback = explode( '::', $callback, 2 );
				} elseif ( function_exists( $callback ) ) {
					$reflection = new ReflectionFunction( $callback );
					return wp_normalize_path( (string) $reflection->getFileName() );
				}
			}

			if ( is_array( $callback ) && 2 === count( $callback ) ) {
				$reflection = new ReflectionMethod( $callback[0], $callback[1] );
				return wp_normalize_path( (string) $reflection->getFileName() );
			}

			if ( $callback instanceof Closure ) {
				$reflection = new ReflectionFunction( $callback );
				return wp_normalize_path( (string) $reflection->getFileName() );
			}
		} catch ( ReflectionException $exception ) {
			return '';
		}

		return '';
	}

	/**
	 * Tracks one blocked extension for the current request.
	 *
	 * @param array    $plugin        Active plugin metadata.
	 * @param string   $hook          Hook name.
	 * @param callable $callback      Removed callback.
	 * @param array    $compatibility Resolved compatibility data.
	 *
	 * @return void
	 */
	private static function track_blocked_extension( array $plugin, $hook, $callback, array $compatibility ) {
		$basename = $plugin['basename'];
		$name     = ! empty( $plugin['data']['Name'] ) ? $plugin['data']['Name'] : $basename;

		if ( empty( self::$blocked_extensions[ $basename ] ) ) {
			self::$blocked_extensions[ $basename ] = array(
				'name'   => $name,
				'rule'   => $compatibility['rule'],
				'source' => $compatibility['source'],
				'note'   => $compatibility['note'],
				'kind'   => isset( $compatibility['kind'] ) ? $compatibility['kind'] : 'epc_rule',
				'hooks'  => array(),
			);
		}

		if ( empty( self::$blocked_extensions[ $basename ]['hooks'][ $hook ] ) ) {
			self::$blocked_extensions[ $basename ]['hooks'][ $hook ] = array();
		}

		$callback_label = self::get_callback_label( $callback );
		if ( ! in_array( $callback_label, self::$blocked_extensions[ $basename ]['hooks'][ $hook ], true ) ) {
			self::$blocked_extensions[ $basename ]['hooks'][ $hook ][] = $callback_label;
		}
	}

	/**
	 * Returns a readable callback label.
	 *
	 * @param callable $callback Hook callback.
	 *
	 * @return string
	 */
	private static function get_callback_label( $callback ) {
		if ( is_string( $callback ) ) {
			return $callback;
		}

		if ( is_array( $callback ) && isset( $callback[1] ) ) {
			$owner = is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0];
			return $owner . '::' . $callback[1];
		}

		if ( $callback instanceof Closure ) {
			return 'Closure';
		}

		return 'callback';
	}
}

IC_EPC_Extension_Compatibility::init();
