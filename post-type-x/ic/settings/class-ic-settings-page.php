<?php
/**
 * Shared settings page wrapper.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers and renders a settings page inside the product settings screen.
 */
class IC_Settings_Page {

	/**
	 * Registered settings pages grouped by screen tab.
	 *
	 * @var array
	 */
	private static $screen_tab_pages = array();

	/**
	 * Tracks which screen-tab registries were loaded.
	 *
	 * @var array
	 */
	private static $screen_tab_registry_loaded = array();

	/**
	 * Incrementing registration order for screen-tab pages.
	 *
	 * @var int
	 */
	private static $screen_tab_page_order = 0;

	/**
	 * Whether the screen tabs filter has been registered.
	 *
	 * @var bool
	 */
	private static $screen_tabs_filter_registered = false;

	/**
	 * Page configuration.
	 *
	 * @var array
	 */
	private $args;

	/**
	 * Screen-tab registration order.
	 *
	 * @var int
	 */
	private $screen_tab_order = 0;

	/**
	 * Initializes the settings page.
	 *
	 * @param array $args Page configuration.
	 */
	public function __construct( $args = array() ) {

		$defaults   = array(
			'title'                            => '',
			'option_group'                     => '',
			'option_name'                      => '',
			'registered_options'               => array(),
			'submenu'                          => '',
			'settings'                         => array(),
			'content_settings'                 => array(),
			'content_callback'                 => '',
			'content_callback_args'            => array(),
			'sections'                         => array(),
			'helpers'                          => array(),
			'screen'                           => '',
			'screen_tab'                       => '',
			'screen_tab_label'                 => '',
			'screen_tab_menu_item_id'          => '',
			'screen_tab_query_args'            => array(),
			'screen_tab_default'               => false,
			'screen_tab_content_wrapper_class' => '',
			'screen_tab_content_wrapper_style' => '',
			'screen_tab_helpers'               => array(),
			'screen_submenu_label'             => '',
			'screen_submenu_priority'          => 10,
			'container_class'                  => 'setting-content submenu',
			'submenu_item_id'                  => '',
			'submit_label'                     => __( 'Save changes', 'post-type-x' ),
			'submit_name'                      => '',
			'submit_class'                     => 'button-primary',
			'show_submit'                      => true,
			'show_title'                       => true,
			'show_form'                        => true,
			'form_action'                      => 'options.php',
			'form_method'                      => 'post',
			'form_attributes'                  => array(),
			'hidden_fields'                    => array(),
			'show_settings_fields'             => true,
			'nonce_action'                     => '',
			'nonce_name'                       => '_wpnonce',
			'nonce_referer'                    => true,
		);
		$this->args = wp_parse_args( is_array( $args ) ? $args : array(), $defaults );
		$this->register_screen_tabs_filter();
		$this->register_screen_tab_page();
		$this->maybe_register();
	}
	/**
	 * Registers the settings option.
	 *
	 * @return void
	 */
	public function register() {

		global $wp_registered_settings;

		if ( empty( $this->args['option_group'] ) ) {
			return;
		}

		foreach ( $this->registered_options() as $option_name => $option_args ) {
			if ( isset( $wp_registered_settings[ $option_name ] ) ) {
				continue;
			}

			register_setting( $this->args['option_group'], $option_name, $option_args );
		}
	}
	/**
	 * Sanitizes the submitted settings payload.
	 *
	 * @param mixed $settings Submitted settings.
	 *
	 * @return array
	 */
	public function sanitize_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings = apply_filters( 'ic_settings_page_sanitize', $settings, $this->args['option_group'], $this->args['option_name'], $this );

		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Returns normalized WordPress settings options to register.
	 *
	 * Supported registered_options entries:
	 * - 'option_name'
	 * - 'option_name' => 'sanitize_callback'
	 * - 'option_name' => array( 'sanitize_callback' => 'sanitize_callback' )
	 * - array( 'name' => 'option_name', 'sanitize_callback' => 'sanitize_callback' )
	 *
	 * @return array
	 */
	private function registered_options() {

		$options = array();

		if ( empty( $this->args['registered_options'] ) || ! is_array( $this->args['registered_options'] ) ) {
			if ( empty( $this->args['option_name'] ) ) {
				return $options;
			}

			return array(
				$this->args['option_name'] => array(
					'sanitize_callback' => array( $this, 'sanitize_settings' ),
				),
			);
		}

		foreach ( $this->args['registered_options'] as $key => $option ) {
			$option_name = '';
			$option_args = array();

			if ( is_int( $key ) && is_string( $option ) ) {
				$option_name = $option;
			} elseif ( is_string( $key ) ) {
				$option_name = $key;
				if ( is_array( $option ) ) {
					$option_args = $option;
				} elseif ( is_string( $option ) ) {
					$option_args = array(
						'sanitize_callback' => $option,
					);
				}
			} elseif ( is_array( $option ) && ! empty( $option['name'] ) ) {
				$option_name = $option['name'];
				unset( $option['name'] );
				$option_args = $option;
			}

			if ( '' === $option_name ) {
				continue;
			}

			$options[ $option_name ] = $option_args;
		}

		return $options;
	}

	/**
	 * Registers the page settings immediately or schedules registration for admin init.
	 *
	 * @return void
	 */
	private function maybe_register() {

		if ( empty( $this->args['option_group'] ) ) {
			return;
		}

		if ( did_action( 'admin_init' ) ) {
			$this->register();
			return;
		}

		add_action( 'admin_init', array( $this, 'register' ) );
	}

	/**
	 * Registers the page inside the shared screen-tab registry.
	 *
	 * @return void
	 */
	private function register_screen_tab_page() {
		$screen_tab = $this->screen_tab();
		if ( '' === $screen_tab ) {
			return;
		}

		if ( ! isset( self::$screen_tab_pages[ $screen_tab ] ) ) {
			self::$screen_tab_pages[ $screen_tab ] = array();
		}

		if ( 0 === $this->screen_tab_order ) {
			++self::$screen_tab_page_order;
			$this->screen_tab_order = self::$screen_tab_page_order;
		}

		self::$screen_tab_pages[ $screen_tab ][ spl_object_hash( $this ) ] = $this;
	}

	/**
	 * Registers the filter that adds page-backed tabs to settings screens.
	 *
	 * @return void
	 */
	private function register_screen_tabs_filter() {
		if ( self::$screen_tabs_filter_registered && false !== has_filter( 'ic_settings_screen_tabs', array( __CLASS__, 'add_screen_tabs' ) ) ) {
			return;
		}

		self::$screen_tabs_filter_registered = true;
		add_filter( 'ic_settings_screen_tabs', array( __CLASS__, 'add_screen_tabs' ), 10, 2 );
	}

	/**
	 * Renders the settings page when the configured submenu is active.
	 *
	 * @param bool $force Whether to render without checking the current request.
	 *
	 * @return void
	 */
	public function render( $force = false ) {
		if ( empty( $this->args['title'] ) || ( ! $force && ! $this->matches_request() ) ) {
			return;
		}

		?>
		<?php $this->render_submenu_script(); ?>
		<div class="<?php echo esc_attr( $this->args['container_class'] ); ?>">
			<?php if ( ! empty( $this->args['show_title'] ) ) : ?>
				<h2><?php echo esc_html( $this->args['title'] ); ?></h2>
			<?php endif; ?>
			<?php if ( ! empty( $this->args['option_name'] ) ) : ?>
				<?php settings_errors( $this->args['option_name'], false, false ); ?>
			<?php endif; ?>
			<?php if ( ! empty( $this->args['show_form'] ) ) : ?>
				<?php do_action( 'ic_settings_page_before_form', $this->args['option_group'], $this->settings(), $this ); ?>
				<form method="<?php echo esc_attr( $this->form_method() ); ?>" action="<?php echo esc_url( $this->form_action() ); ?>"<?php echo $this->form_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Form attributes are escaped by form_attributes(). ?>>
					<?php if ( ! empty( $this->args['option_group'] ) && ! empty( $this->args['show_settings_fields'] ) ) : ?>
						<?php settings_fields( $this->args['option_group'] ); ?>
					<?php endif; ?>
					<?php $this->render_form_nonce(); ?>
					<?php $this->render_hidden_fields(); ?>
					<?php $this->render_body(); ?>
					<?php if ( ! empty( $this->args['show_submit'] ) ) : ?>
						<p class="submit">
							<input type="submit"<?php $this->render_submit_name(); ?> class="<?php echo esc_attr( $this->args['submit_class'] ); ?>" value="<?php echo esc_attr( $this->args['submit_label'] ); ?>"/>
						</p>
					<?php endif; ?>
				</form>
				<?php do_action( 'ic_settings_page_after_form', $this->args['option_group'], $this->settings(), $this ); ?>
			<?php else : ?>
				<?php $this->render_body(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders the generated page content and sections without the wrapper form.
	 *
	 * @return void
	 */
	public function render_body() {
		$this->render_content();
		$this->render_sections();
	}

	/**
	 * Returns the current page settings payload.
	 *
	 * @return array
	 */
	public function settings() {
		return is_array( $this->args['settings'] ) ? $this->args['settings'] : array();
	}

	/**
	 * Returns the configured helper definitions.
	 *
	 * @return array
	 */
	public function helpers() {
		return is_array( $this->args['helpers'] ) ? $this->args['helpers'] : array();
	}

	/**
	 * Returns the configured form action URL.
	 *
	 * @return string
	 */
	public function form_action() {
		return (string) $this->args['form_action'];
	}

	/**
	 * Returns the configured form method.
	 *
	 * @return string
	 */
	public function form_method() {
		$method = strtolower( (string) $this->args['form_method'] );

		return 'get' === $method ? 'get' : 'post';
	}

	/**
	 * Returns the assigned settings screen menu slug.
	 *
	 * @return string
	 */
	public function screen() {
		return (string) $this->args['screen'];
	}

	/**
	 * Returns true when the page belongs to the provided screen.
	 *
	 * @param mixed $screen Settings screen instance.
	 *
	 * @return bool
	 */
	public function matches_screen( $screen ) {
		$screen_slug = $this->screen();
		if ( '' === $screen_slug ) {
			return true;
		}

		if ( ! is_object( $screen ) || ! method_exists( $screen, 'menu_slug' ) ) {
			return false;
		}

		return $screen_slug === $screen->menu_slug();
	}

	/**
	 * Returns the assigned screen tab key.
	 *
	 * @return string
	 */
	public function screen_tab() {
		return sanitize_key( (string) $this->args['screen_tab'] );
	}

	/**
	 * Returns the parent screen tab label.
	 *
	 * @return string
	 */
	public function screen_tab_label() {
		if ( ! empty( $this->args['screen_tab_label'] ) ) {
			return (string) $this->args['screen_tab_label'];
		}

		return (string) $this->args['title'];
	}

	/**
	 * Returns the parent screen tab menu item ID.
	 *
	 * @return string
	 */
	public function screen_tab_menu_item_id() {
		if ( ! empty( $this->args['screen_tab_menu_item_id'] ) ) {
			return sanitize_html_class( $this->args['screen_tab_menu_item_id'] );
		}

		return sanitize_html_class( $this->screen_tab() );
	}

	/**
	 * Returns the parent screen tab query args.
	 *
	 * @return array
	 */
	public function screen_tab_query_args() {
		return is_array( $this->args['screen_tab_query_args'] ) ? $this->args['screen_tab_query_args'] : array();
	}

	/**
	 * Returns true when the parent screen tab is the default tab.
	 *
	 * @return bool
	 */
	public function screen_tab_default() {
		return ! empty( $this->args['screen_tab_default'] );
	}

	/**
	 * Returns the parent screen tab content wrapper class.
	 *
	 * @return string
	 */
	public function screen_tab_content_wrapper_class() {
		return (string) $this->args['screen_tab_content_wrapper_class'];
	}

	/**
	 * Returns the parent screen tab content wrapper style.
	 *
	 * @return string
	 */
	public function screen_tab_content_wrapper_style() {
		return (string) $this->args['screen_tab_content_wrapper_style'];
	}

	/**
	 * Returns helpers assigned to the parent screen tab.
	 *
	 * @return array
	 */
	public function screen_tab_helpers() {
		return is_array( $this->args['screen_tab_helpers'] ) ? $this->args['screen_tab_helpers'] : array();
	}

	/**
	 * Returns the WordPress settings option group.
	 *
	 * @return string
	 */
	public function option_group() {
		return sanitize_key( (string) $this->args['option_group'] );
	}

	/**
	 * Returns the screen submenu label.
	 *
	 * @return string
	 */
	public function screen_submenu_label() {
		if ( ! empty( $this->args['screen_submenu_label'] ) ) {
			return (string) $this->args['screen_submenu_label'];
		}

		return (string) $this->args['title'];
	}

	/**
	 * Returns the screen submenu item ID.
	 *
	 * @return string
	 */
	public function screen_submenu_item_id() {
		if ( ! empty( $this->args['submenu_item_id'] ) ) {
			return sanitize_html_class( $this->args['submenu_item_id'] );
		}

		return sanitize_html_class( $this->screen_submenu() );
	}

	/**
	 * Returns the screen submenu priority.
	 *
	 * @return int
	 */
	public function screen_submenu_priority() {
		return intval( $this->args['screen_submenu_priority'] );
	}

	/**
	 * Returns the screen submenu registration order.
	 *
	 * @return int
	 */
	public function screen_submenu_order() {
		return intval( $this->screen_tab_order );
	}

	/**
	 * Returns the primary screen submenu slug.
	 *
	 * @return string
	 */
	public function screen_submenu() {
		$submenus = $this->submenus();
		foreach ( $submenus as $submenu ) {
			if ( '' !== $submenu ) {
				return $submenu;
			}
		}

		return empty( $submenus ) ? '' : (string) reset( $submenus );
	}

	/**
	 * Returns true when the provided submenu belongs to the page.
	 *
	 * @param string $submenu Screen submenu slug.
	 *
	 * @return bool
	 */
	public function matches_submenu( $submenu ) {
		return in_array( sanitize_key( (string) $submenu ), $this->submenus(), true );
	}

	/**
	 * Returns the registered screen-tab pages for the requested tab.
	 *
	 * @param string $screen_tab Screen tab key.
	 * @param mixed  $screen Optional settings screen instance.
	 *
	 * @return array
	 */
	public static function screen_tab_pages( $screen_tab, $screen = null ) {
		$screen_tab = sanitize_key( (string) $screen_tab );
		if ( '' === $screen_tab ) {
			return array();
		}

		if ( empty( self::$screen_tab_registry_loaded[ $screen_tab ] ) ) {
			do_action( 'ic_settings_page_register_screen_tab_pages', $screen_tab );
			self::$screen_tab_registry_loaded[ $screen_tab ] = true;
		}

		$pages = isset( self::$screen_tab_pages[ $screen_tab ] ) ? array_values( self::$screen_tab_pages[ $screen_tab ] ) : array();
		if ( empty( $pages ) ) {
			return array();
		}

		if ( null !== $screen ) {
			$pages = array_values(
				array_filter(
					$pages,
					static function ( $page ) use ( $screen ) {
						return is_object( $page ) && $page instanceof IC_Settings_Page && $page->matches_screen( $screen );
					}
				)
			);
		}

		if ( empty( $pages ) ) {
			return array();
		}

		usort(
			$pages,
			static function ( $left, $right ) {
				$left_priority  = $left->screen_submenu_priority();
				$right_priority = $right->screen_submenu_priority();
				if ( $left_priority !== $right_priority ) {
					return $left_priority - $right_priority;
				}

				return $left->screen_submenu_order() - $right->screen_submenu_order();
			}
		);

		return $pages;
	}

	/**
	 * Returns screen tab keys with registered pages.
	 *
	 * @return array
	 */
	public static function screen_tabs() {
		return array_keys( self::$screen_tab_pages );
	}

	/**
	 * Adds registered page-backed tabs to a settings screen.
	 *
	 * @param array $tabs Existing settings screen tabs.
	 * @param mixed $screen Settings screen instance.
	 *
	 * @return array
	 */
	public static function add_screen_tabs( $tabs, $screen ) {
		if ( ! is_array( $tabs ) ) {
			$tabs = array();
		}

		foreach ( self::screen_tabs() as $tab_key ) {
			$pages = self::screen_tab_pages( $tab_key, $screen );
			if ( empty( $pages ) ) {
				continue;
			}

			$primary_page = reset( $pages );
			if ( ! is_object( $primary_page ) || ! $primary_page instanceof IC_Settings_Page ) {
				continue;
			}

			$page_tab = self::screen_tab_config( $tab_key, $primary_page );
			if ( isset( $tabs[ $tab_key ] ) ) {
				$tabs[ $tab_key ] = wp_parse_args( $tabs[ $tab_key ], $page_tab );
				continue;
			}

			$tabs[ $tab_key ] = $page_tab;
		}

		return $tabs;
	}

	/**
	 * Returns the generated tab config for a settings page.
	 *
	 * @param string           $tab_key Screen tab key.
	 * @param IC_Settings_Page $page Primary settings page for the tab.
	 *
	 * @return array
	 */
	private static function screen_tab_config( $tab_key, $page ) {
		$query_args = $page->screen_tab_query_args();
		if ( empty( $query_args ) && '' !== $page->screen_submenu() ) {
			$query_args = array(
				'submenu' => $page->screen_submenu(),
			);
		}

		return array(
			'label'                 => $page->screen_tab_label(),
			'menu_item_id'          => $page->screen_tab_menu_item_id(),
			'callback'              => '',
			'render_callback'       => '',
			'active_callback'       => '',
			'query_args'            => $query_args,
			'pages'                 => array(),
			'helpers'               => $page->screen_tab_helpers(),
			'url'                   => '',
			'class'                 => 'nav-tab',
			'target'                => '',
			'title'                 => '',
			'default'               => $page->screen_tab_default(),
			'content_wrapper_class' => $page->screen_tab_content_wrapper_class(),
			'content_wrapper_style' => $page->screen_tab_content_wrapper_style(),
		);
	}

	/**
	 * Checks whether the current request matches the configured submenu.
	 *
	 * @return bool
	 */
	private function matches_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only submenu routing for the current admin settings screen.
		$submenu = isset( $_GET['submenu'] ) ? sanitize_key( wp_unslash( $_GET['submenu'] ) ) : '';

		return in_array( $submenu, $this->submenus(), true );
	}

	/**
	 * Renders the standard settings table content.
	 *
	 * @return void
	 */
	private function render_content() {
		$has_content_settings = ! empty( $this->args['content_settings'] ) && is_array( $this->args['content_settings'] );

		if ( $has_content_settings ) {
			$content = new IC_Settings_Standard_Table(
				array(
					'settings' => $this->args['content_settings'],
				)
			);
			$content->render();
		}

		if ( is_callable( $this->args['content_callback'] ) ) {
			call_user_func_array( $this->args['content_callback'], $this->content_callback_args() );
		}

		do_action( 'ic_settings_page_content', $this->args['option_group'], $this->args['settings'], $this );
	}

	/**
	 * Returns normalized content callback arguments.
	 *
	 * @return array
	 */
	private function content_callback_args() {
		if ( empty( $this->args['content_callback_args'] ) ) {
			return array();
		}

		return is_array( $this->args['content_callback_args'] ) ? $this->args['content_callback_args'] : array( $this->args['content_callback_args'] );
	}

	/**
	 * Fires the trailing page sections hook.
	 *
	 * @return void
	 */
	private function render_sections() {
		$sections = is_array( $this->args['sections'] ) ? $this->args['sections'] : array();

		foreach ( $sections as $section ) {
			if ( is_object( $section ) && method_exists( $section, 'render' ) ) {
				$section->render();
				continue;
			}

			if ( ! is_array( $section ) ) {
				continue;
			}

			$section_object = new IC_Settings_Section( $section );
			$section_object->render();
		}

		do_action( 'ic_settings_page_sections_end', $this->args['option_group'], $this->settings(), $this );
	}

	/**
	 * Renders the submenu activation script when configured.
	 *
	 * @return void
	 */
	private function render_submenu_script() {
		$current_selector = $this->submenu_item_selector();
		if ( empty( $current_selector ) ) {
			return;
		}
		?>
		<script>
			jQuery( function ( $ ) {
				$( '.settings-submenu a' ).removeClass( 'current' );
				$( <?php echo wp_json_encode( $current_selector ); ?> ).addClass( 'current' );
			} );
		</script>
		<?php
	}

	/**
	 * Returns the selector for the active submenu item.
	 *
	 * @return string
	 */
	private function submenu_item_selector() {
		if ( empty( $this->args['submenu_item_id'] ) ) {
			return '';
		}

		return '.settings-submenu a#' . sanitize_html_class( $this->args['submenu_item_id'] );
	}

	/**
	 * Returns normalized submenu matches for the current page.
	 *
	 * @return array
	 */
	private function submenus() {
		$submenus = is_array( $this->args['submenu'] ) ? $this->args['submenu'] : array( $this->args['submenu'] );

		return array_map( 'sanitize_key', $submenus );
	}

	/**
	 * Returns normalized form attributes.
	 *
	 * @return string
	 */
	private function form_attributes() {
		if ( empty( $this->args['form_attributes'] ) || ! is_array( $this->args['form_attributes'] ) ) {
			return '';
		}

		$attributes = '';
		foreach ( $this->args['form_attributes'] as $attribute => $value ) {
			$attribute = sanitize_key( (string) $attribute );
			if ( '' === $attribute || null === $value || false === $value ) {
				continue;
			}
			if ( true === $value ) {
				$attributes .= ' ' . $attribute;
				continue;
			}

			$attributes .= sprintf( ' %1$s="%2$s"', $attribute, esc_attr( (string) $value ) );
		}

		return $attributes;
	}

	/**
	 * Renders a nonce field for custom form handlers when configured.
	 *
	 * @return void
	 */
	private function render_form_nonce() {
		if ( empty( $this->args['nonce_action'] ) ) {
			return;
		}

		wp_nonce_field(
			(string) $this->args['nonce_action'],
			(string) $this->args['nonce_name'],
			! empty( $this->args['nonce_referer'] )
		);
	}

	/**
	 * Renders configured hidden fields.
	 *
	 * @return void
	 */
	private function render_hidden_fields() {
		if ( empty( $this->args['hidden_fields'] ) || ! is_array( $this->args['hidden_fields'] ) ) {
			return;
		}

		foreach ( $this->args['hidden_fields'] as $name => $value ) {
			$name = is_scalar( $name ) ? (string) $name : '';
			if ( '' === $name ) {
				continue;
			}
			?>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( is_scalar( $value ) ? (string) $value : '' ); ?>" />
			<?php
		}
	}

	/**
	 * Renders the optional submit name attribute.
	 *
	 * @return void
	 */
	private function render_submit_name() {
		if ( empty( $this->args['submit_name'] ) ) {
			return;
		}
		?>
name="<?php echo esc_attr( (string) $this->args['submit_name'] ); ?>"
		<?php
	}
}
