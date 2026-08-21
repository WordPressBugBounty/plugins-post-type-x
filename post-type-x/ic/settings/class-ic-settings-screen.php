<?php
/**
 * Shared settings screen wrapper.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers and renders a submenu settings screen with optional tab handling.
 */
class IC_Settings_Screen {
	/**
	 * Screen configuration.
	 *
	 * @var array
	 */
	private $args;

	/**
	 * Whether tab display hooks have been registered.
	 *
	 * @var bool
	 */
	private $tab_displays_registered = false;

	/**
	 * Tracks page callbacks loaded for screen tabs.
	 *
	 * @var array
	 */
	private $loaded_page_tabs = array();

	/**
	 * Whether configured screen page factories have been loaded.
	 *
	 * @var bool
	 */
	private $screen_pages_loaded = false;

	/**
	 * Registered option-page capability filters mapped to option groups.
	 *
	 * @var array
	 */
	private $option_page_capability_filters = array();

	/**
	 * Initializes the settings screen.
	 *
	 * @param array $args Screen configuration.
	 */
	public function __construct( $args = array() ) {
		$defaults   = array(
			'parent_slug'              => '',
			'page_title'               => '',
			'menu_title'               => '',
			'capability'               => 'manage_options',
			'menu_slug'                => '',
			'menu_priority'            => 10,
			'title'                    => '',
			'title_suffix'             => '',
			'wrap_id'                  => 'implecode_settings',
			'wrap_class'               => 'wrap',
			'content_container_class'  => 'table',
			'content_container_style'  => 'table-layout:fixed;margin-top: 20px; width: 100%;position:relative;text-align: left;',
			'top_callback'             => '',
			'tabs'                     => array(),
			'pages'                    => array(),
			'tabs_hooks'               => array(),
			'tab_displays'             => array(),
			'after_content_callback'   => '',
			'option_page_capabilities' => array(),
			'show_logo'                => false,
			'logo_link_url'            => 'https://implecode.com/#cam=catalog-settings-link&key=logo-link',
			'logo_image_url'           => '',
			'logo_image_width'         => '282px',
			'logo_image_alt'           => 'impleCode',
			'logo_content_hook'        => 'ic_plugin_logo_container',
			'show_tabs'                => true,
			'show_active_tab_script'   => true,
			'show_unsaved_changes_js'  => false,
			'show_sortable_rows_js'    => false,
			'show_nav_compact_js'      => false,
			'compact_reference_id'     => '',
			'compact_hide_ids'         => array(),
		);
		$this->args = wp_parse_args( is_array( $args ) ? $args : array(), $defaults );

		if ( ! empty( $this->args['parent_slug'] ) && ! empty( $this->args['menu_slug'] ) ) {
			add_action( 'admin_menu', array( $this, 'register_menu' ), intval( $this->args['menu_priority'] ) );
		}

		$this->register_tab_displays();
		$this->register_option_page_capability_filters();
		$this->register_page_option_page_capability_filters();
	}

	/**
	 * Returns the configured menu slug.
	 *
	 * @return string
	 */
	public function menu_slug() {
		return (string) $this->args['menu_slug'];
	}

	/**
	 * Registers the submenu page.
	 *
	 * @return void
	 */
	public function register_menu() {
		global $submenu;

		if ( empty( $this->args['parent_slug'] ) || empty( $this->args['menu_slug'] ) ) {
			return;
		}

		if ( isset( $submenu[ $this->args['parent_slug'] ] ) && is_array( $submenu[ $this->args['parent_slug'] ] ) ) {
			foreach ( $submenu[ $this->args['parent_slug'] ] as $submenu_item ) {
				if ( isset( $submenu_item[2] ) && $submenu_item[2] === $this->args['menu_slug'] ) {
					return;
				}
			}
		}

		add_submenu_page(
			$this->args['parent_slug'],
			$this->page_title(),
			$this->menu_title(),
			$this->capability(),
			$this->args['menu_slug'],
			array( $this, 'render' )
		);
	}

	/**
	 * Renders the whole settings screen.
	 *
	 * @return void
	 */
	public function render() {
		$active_tab = $this->active_tab();
		?>
		<div id="<?php echo esc_attr( $this->args['wrap_id'] ); ?>" class="<?php echo esc_attr( $this->args['wrap_class'] ); ?>">
			<h2><?php echo esc_html( $this->title() ); ?><?php echo esc_html( $this->args['title_suffix'] ); ?></h2>
			<?php do_action( 'ic_settings_screen_after_title', $this, $active_tab ); ?>
			<?php $this->render_top(); ?>
			<?php if ( ! empty( $this->args['show_tabs'] ) ) : ?>
				<?php $this->render_tabs(); ?>
			<?php endif; ?>
			<?php do_action( 'ic_settings_screen_before_content', $this, $active_tab ); ?>
			<div class="<?php echo esc_attr( $this->args['content_container_class'] ); ?>"<?php $this->render_content_container_style(); ?>>
				<?php $this->render_active_tab_content(); ?>
				<?php $this->render_after_content(); ?>
				<?php $this->render_logo(); ?>
			</div>
			<?php do_action( 'ic_settings_screen_after_content', $this, $active_tab ); ?>
			<?php $this->render_screen_scripts(); ?>
		</div>
		<?php
	}

	/**
	 * Renders the tabs wrapper.
	 *
	 * @param bool $use_active_tab_state Whether to mark the active tab.
	 *
	 * @return void
	 */
	public function render_tabs( $use_active_tab_state = true ) {
		$tabs = $this->tabs();
		if ( empty( $tabs ) && empty( $this->args['tabs_hooks'] ) ) {
			return;
		}
		?>
		<h2 class="nav-tab-wrapper ic-nav-tab-wrapper">
			<?php if ( ! empty( $tabs ) ) : ?>
				<?php $this->render_generated_tabs( $use_active_tab_state ); ?>
			<?php endif; ?>
			<?php $this->render_tab_hooks(); ?>
		</h2>
		<?php
		if ( $use_active_tab_state && ! empty( $this->args['show_active_tab_script'] ) ) {
			$this->render_active_tab_script();
		}
		if ( ! empty( $this->args['show_nav_compact_js'] ) ) {
			$this->render_nav_compact_script();
		}
	}

	/**
	 * Renders configured tab displays for the current hook and priority.
	 *
	 * @param mixed $value Optional filtered value.
	 * @return mixed
	 */
	public function render_tab_display_hook( $value = null ) {
		$current_hook     = current_filter();
		$current_priority = $this->current_hook_priority( $current_hook );

		foreach ( $this->tab_displays() as $display ) {
			if ( $display['hook'] !== $current_hook || intval( $display['priority'] ) !== $current_priority ) {
				continue;
			}

			$this->render_tab_display( $display );
		}

		return $value;
	}

	/**
	 * Returns the currently active tab key.
	 *
	 * @return string
	 */
	public function active_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only settings tab routing.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		if ( '' === $tab ) {
			return $this->default_tab_key();
		}

		return isset( $this->tabs()[ $tab ] ) ? $tab : $this->default_tab_key();
	}

	/**
	 * Maps WordPress option-page capability checks to the screen capability.
	 *
	 * @param string $capability Original option page capability.
	 *
	 * @return string
	 */
	public function map_option_page_capability( $capability ) {
		$current_filter = current_filter();
		$option_group   = isset( $this->option_page_capability_filters[ $current_filter ] ) ? $this->option_page_capability_filters[ $current_filter ] : '';
		$mapped         = $this->capability();

		return apply_filters( 'ic_settings_screen_option_page_capability', $mapped, $option_group, $capability, $this );
	}

	/**
	 * Returns the generated tab URL.
	 *
	 * @param string $tab_key Tab key.
	 *
	 * @return string
	 */
	public function get_tab_url( $tab_key ) {
		$tabs    = $this->tabs();
		$tab_key = sanitize_key( (string) $tab_key );

		if ( empty( $tabs[ $tab_key ] ) ) {
			return '';
		}

		return $this->tab_url( $tab_key, $tabs[ $tab_key ] );
	}

	/**
	 * Renders a specific tab content block.
	 *
	 * @param string $tab_key Optional tab key.
	 *
	 * @return void
	 */
	public function render_tab_content( $tab_key = '' ) {
		$tabs = $this->tabs();

		if ( '' === $tab_key ) {
			$tab_key = $this->active_tab();
		} else {
			$tab_key = sanitize_key( (string) $tab_key );
		}

		if ( empty( $tabs[ $tab_key ] ) ) {
			return;
		}

		$this->render_tab_content_config( $tab_key, $tabs[ $tab_key ] );
	}

	/**
	 * Renders the fixed top screen hook and configured callback.
	 *
	 * @return void
	 */
	private function render_top() {
		do_action( 'ic_settings_top' );
		if ( is_callable( $this->args['top_callback'] ) ) {
			call_user_func( $this->args['top_callback'] );
		}
	}

	/**
	 * Registers configured tab display hooks once.
	 *
	 * @return void
	 */
	private function register_tab_displays() {
		if ( $this->tab_displays_registered ) {
			return;
		}

		foreach ( $this->tab_displays() as $display ) {
			add_action( $display['hook'], array( $this, 'render_tab_display_hook' ), intval( $display['priority'] ) );
		}

		$this->tab_displays_registered = true;
	}

	/**
	 * Registers option-page capability filters for configured option groups.
	 *
	 * @return void
	 */
	private function register_option_page_capability_filters() {
		foreach ( $this->option_page_capability_filter_map() as $filter_name => $option_group ) {
			$this->register_option_page_capability_filter( $filter_name, $option_group );
		}
	}

	/**
	 * Registers option-page capability filters for configured screen pages.
	 *
	 * @return void
	 */
	private function register_page_option_page_capability_filters() {
		foreach ( $this->tabs() as $tab_key => $tab ) {
			$this->load_tab_pages( $tab_key, $tab );
			foreach ( IC_Settings_Page::screen_tab_pages( $tab_key, $this ) as $page ) {
				if ( ! is_object( $page ) || ! method_exists( $page, 'option_group' ) ) {
					continue;
				}

				$option_group = $page->option_group();
				if ( '' === $option_group ) {
					continue;
				}

				$this->register_option_page_capability_filter( $this->option_page_capability_filter_name( $option_group ), $option_group );
			}
		}
	}

	/**
	 * Registers a single option-page capability filter.
	 *
	 * @param string $filter_name WordPress filter name.
	 * @param string $option_group Option group.
	 *
	 * @return void
	 */
	private function register_option_page_capability_filter( $filter_name, $option_group ) {
		$filter_name  = is_string( $filter_name ) ? sanitize_key( $filter_name ) : '';
		$option_group = sanitize_key( (string) $option_group );

		if ( '' === $filter_name || '' === $option_group || isset( $this->option_page_capability_filters[ $filter_name ] ) ) {
			return;
		}

		$this->option_page_capability_filters[ $filter_name ] = $option_group;
		add_filter( $filter_name, array( $this, 'map_option_page_capability' ) );
	}

	/**
	 * Returns configured option-page capability filters mapped to option groups.
	 *
	 * @return array
	 */
	private function option_page_capability_filter_map() {
		if ( empty( $this->args['option_page_capabilities'] ) ) {
			return array();
		}

		$filters             = array();
		$capability_mappings = is_array( $this->args['option_page_capabilities'] ) ? $this->args['option_page_capabilities'] : array( $this->args['option_page_capabilities'] );

		foreach ( $capability_mappings as $filter_name => $option_group ) {
			$option_group = sanitize_key( (string) $option_group );
			if ( '' === $option_group ) {
				continue;
			}

			if ( is_int( $filter_name ) ) {
				$filter_name = $this->option_page_capability_filter_name( $option_group );
			}

			$filter_name = is_string( $filter_name ) ? sanitize_key( $filter_name ) : '';
			if ( '' === $filter_name ) {
				continue;
			}

			$filters[ $filter_name ] = $option_group;
		}

		return $filters;
	}

	/**
	 * Returns the WordPress option-page capability filter name.
	 *
	 * @param string $option_group Option group.
	 *
	 * @return string
	 */
	private function option_page_capability_filter_name( $option_group ) {
		$option_group = sanitize_key( (string) $option_group );
		if ( '' === $option_group ) {
			return '';
		}

		return 'option_page_capability_' . $option_group;
	}

	/**
	 * Renders a configured tab display wrapper.
	 *
	 * @param array $display Display configuration.
	 *
	 * @return void
	 */
	private function render_tab_display( $display ) {
		if ( ! $this->should_render_tab_display( $display ) ) {
			return;
		}

		if ( '' !== $display['before_html'] ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted admin markup passed by the caller.
			echo $display['before_html'];
		}

		$this->render_tab_display_open_tag( $display, 'wrap' );
		$this->render_tab_display_open_tag( $display, 'inner_wrap' );
		$this->render_tabs( ! empty( $display['use_active_tab_state'] ) );
		$this->render_tab_display_close_tag( $display, 'inner_wrap' );
		$this->render_tab_display_close_tag( $display, 'wrap' );

		if ( '' !== $display['after_html'] ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted admin markup passed by the caller.
			echo $display['after_html'];
		}
	}

	/**
	 * Returns true when the configured tab display should render.
	 *
	 * @param array $display Display configuration.
	 *
	 * @return bool
	 */
	private function should_render_tab_display( $display ) {
		if ( empty( $display['condition_callback'] ) ) {
			return true;
		}

		if ( ! is_callable( $display['condition_callback'] ) ) {
			return false;
		}

		return (bool) call_user_func( $display['condition_callback'] );
	}

	/**
	 * Renders an opening display wrapper tag when configured.
	 *
	 * @param array  $display Display configuration.
	 * @param string $prefix Display wrapper prefix.
	 *
	 * @return void
	 */
	private function render_tab_display_open_tag( $display, $prefix ) {
		$tag = ! empty( $display[ $prefix . '_tag' ] ) ? tag_escape( $display[ $prefix . '_tag' ] ) : '';
		if ( '' === $tag ) {
			return;
		}
		?>
		<<?php echo esc_html( $tag ); ?><?php $this->render_tab_display_attributes( $display, $prefix ); ?>>
		<?php
	}

	/**
	 * Renders a closing display wrapper tag when configured.
	 *
	 * @param array  $display Display configuration.
	 * @param string $prefix Display wrapper prefix.
	 *
	 * @return void
	 */
	private function render_tab_display_close_tag( $display, $prefix ) {
		$tag = ! empty( $display[ $prefix . '_tag' ] ) ? tag_escape( $display[ $prefix . '_tag' ] ) : '';
		if ( '' === $tag ) {
			return;
		}
		?>
		</<?php echo esc_html( $tag ); ?>>
		<?php
	}

	/**
	 * Renders display wrapper attributes.
	 *
	 * @param array  $display Display configuration.
	 * @param string $prefix Display wrapper prefix.
	 *
	 * @return void
	 */
	private function render_tab_display_attributes( $display, $prefix ) {
		if ( ! empty( $display[ $prefix . '_id' ] ) ) {
			?>
			id="<?php echo esc_attr( $display[ $prefix . '_id' ] ); ?>"
			<?php
		}

		if ( ! empty( $display[ $prefix . '_class' ] ) ) {
			?>
			class="<?php echo esc_attr( $display[ $prefix . '_class' ] ); ?>"
			<?php
		}
	}

	/**
	 * Renders the active tab content callback.
	 *
	 * @return void
	 */
	private function render_active_tab_content() {
		$active_tab = $this->active_tab();
		$tabs       = $this->tabs();

		if ( empty( $tabs[ $active_tab ] ) ) {
			return;
		}

		$this->render_tab_content_config( $active_tab, $tabs[ $active_tab ] );
	}

	/**
	 * Renders the content for a normalized tab configuration.
	 *
	 * @param string $tab_key Tab key.
	 * @param array  $tab Tab configuration.
	 *
	 * @return void
	 */
	private function render_tab_content_config( $tab_key, $tab ) {
		if ( ! empty( $tab['callback'] ) && is_callable( $tab['callback'] ) ) {
			call_user_func( $tab['callback'] );
			return;
		}

		$this->load_tab_pages( $tab_key, $tab );
		$pages = IC_Settings_Page::screen_tab_pages( $tab_key, $this );
		if ( empty( $pages ) ) {
			return;
		}

		$this->render_generated_page_tab( $tab_key, $tab, $pages );
	}

	/**
	 * Calls configured page factories for a generated screen tab.
	 *
	 * @param string $tab_key Tab key.
	 * @param array  $tab Tab configuration.
	 *
	 * @return void
	 */
	private function load_tab_pages( $tab_key, $tab ) {
		$this->load_screen_pages();

		if ( ! empty( $this->loaded_page_tabs[ $tab_key ] ) ) {
			return;
		}

		$this->loaded_page_tabs[ $tab_key ] = true;

		if ( empty( $tab['pages'] ) ) {
			return;
		}

		$page_factories = is_array( $tab['pages'] ) ? $tab['pages'] : array( $tab['pages'] );
		foreach ( $page_factories as $page_factory ) {
			if ( is_object( $page_factory ) && $page_factory instanceof IC_Settings_Page ) {
				continue;
			}

			if ( is_callable( $page_factory ) ) {
				call_user_func( $page_factory );
			}
		}
	}

	/**
	 * Calls configured screen-level page factories.
	 *
	 * @return void
	 */
	private function load_screen_pages() {
		if ( $this->screen_pages_loaded ) {
			return;
		}

		$this->screen_pages_loaded = true;

		if ( empty( $this->args['pages'] ) ) {
			return;
		}

		$page_factories = is_array( $this->args['pages'] ) ? $this->args['pages'] : array( $this->args['pages'] );

		foreach ( $page_factories as $page_factory ) {
			if ( is_object( $page_factory ) && $page_factory instanceof IC_Settings_Page ) {
				continue;
			} elseif ( is_callable( $page_factory ) ) {
				call_user_func( $page_factory );
			}
		}
	}

	/**
	 * Renders a generated page-driven tab wrapper.
	 *
	 * @param string $tab_key Tab key.
	 * @param array  $tab Tab configuration.
	 * @param array  $pages Registered screen pages.
	 *
	 * @return void
	 */
	private function render_generated_page_tab( $tab_key, $tab, $pages ) {
		$active_page      = $this->active_page_for_tab( $pages );
		$additional_links = $this->tab_submenu_hook_html( $tab_key, $pages, $active_page );
		$wrapper_class    = $this->generated_tab_wrapper_class( $tab_key, $tab );
		?>
		<div class="<?php echo esc_attr( $wrapper_class ); ?>"<?php $this->render_generated_tab_wrapper_style( $tab ); ?>>
			<?php if ( ! empty( $pages ) || '' !== trim( $additional_links ) ) : ?>
				<div class="settings-submenu">
					<h3>
						<?php $this->render_generated_page_submenus( $tab_key, $tab, $pages, $active_page ); ?>
						<?php
						if ( '' !== trim( $additional_links ) ) {
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Hook output is trusted admin markup from the active plugin stack.
							echo $additional_links;
						}
						?>
					</h3>
				</div>
			<?php endif; ?>
			<?php if ( is_object( $active_page ) ) : ?>
				<?php $active_page->render( true ); ?>
			<?php endif; ?>
			<?php do_action( 'ic_settings_screen_tab_content', $tab_key, $active_page, $pages, $this ); ?>
			<?php $this->render_generated_tab_helpers( $tab_key, $tab, $active_page, $pages ); ?>
		</div>
		<?php
	}

	/**
	 * Renders the generated submenu links for a page-driven tab.
	 *
	 * @param string                $tab_key Tab key.
	 * @param array                 $tab Tab configuration.
	 * @param IC_Settings_Page[]    $pages Registered screen pages.
	 * @param IC_Settings_Page|null $active_page Active page object.
	 *
	 * @return void
	 */
	private function render_generated_page_submenus( $tab_key, $tab, $pages, $active_page ) {
		foreach ( $pages as $page ) {
			$link_class = 'element';
			if ( is_object( $active_page ) && $active_page === $page ) {
				$link_class .= ' current';
			}
			?>
			<a id="<?php echo esc_attr( $page->screen_submenu_item_id() ); ?>" class="<?php echo esc_attr( $link_class ); ?>"
				href="<?php echo esc_url( $this->page_submenu_url( $tab_key, $tab, $page ) ); ?>"><?php echo esc_html( $page->screen_submenu_label() ); ?></a>
			<?php
		}
	}

	/**
	 * Renders helper boxes for a generated page-driven tab.
	 *
	 * @param string                $tab_key Tab key.
	 * @param array                 $tab Tab configuration.
	 * @param IC_Settings_Page|null $active_page Active page object.
	 * @param array                 $pages Registered screen pages.
	 *
	 * @return void
	 */
	private function render_generated_tab_helpers( $tab_key, $tab, $active_page, $pages ) {
		$helpers = array();
		if ( ! empty( $tab['helpers'] ) && is_array( $tab['helpers'] ) ) {
			$helpers = $tab['helpers'];
		}

		if ( is_object( $active_page ) ) {
			$helpers = array_merge( $helpers, $active_page->helpers() );
		}

		ob_start();
		foreach ( $helpers as $helper ) {
			$this->render_helper( $helper, $tab_key, $active_page );
		}
		do_action( 'ic_settings_screen_tab_helpers', $tab_key, $active_page, $pages, $this );
		$helpers_html = trim( ob_get_clean() );

		if ( '' === $helpers_html ) {
			return;
		}
		?>
		<div class="helpers">
			<div class="wrapper">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper markup is already escaped or generated by trusted admin callbacks.
				echo $helpers_html;
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders a helper definition.
	 *
	 * @param mixed                 $helper Helper definition.
	 * @param string                $tab_key Current tab key.
	 * @param IC_Settings_Page|null $active_page Active page object.
	 *
	 * @return void
	 */
	private function render_helper( $helper, $tab_key, $active_page ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing check.
		$current_submenu = isset( $_GET['submenu'] ) ? sanitize_key( wp_unslash( $_GET['submenu'] ) ) : '';
		$context         = array(
			'tab'     => $tab_key,
			'submenu' => '' !== $current_submenu ? $current_submenu : ( is_object( $active_page ) ? $active_page->screen_submenu() : '' ),
		);

		if ( is_object( $helper ) && method_exists( $helper, 'render' ) ) {
			$helper->render( $context );
			return;
		}

		if ( is_string( $helper ) && is_callable( $helper ) ) {
			call_user_func( $helper );
			return;
		}

		if ( ! is_array( $helper ) ) {
			return;
		}

		if ( ! empty( $helper['callback'] ) && is_callable( $helper['callback'] ) ) {
			$args = array();
			if ( ! empty( $helper['args'] ) ) {
				$args = is_array( $helper['args'] ) ? $helper['args'] : array( $helper['args'] );
			}
			call_user_func_array( $helper['callback'], $args );
			return;
		}

		$helper_box = new IC_Settings_Helper_Box( $helper );
		$helper_box->render( $context );
	}

	/**
	 * Returns the active screen page for the current submenu state.
	 *
	 * @param IC_Settings_Page[] $pages Registered screen pages.
	 *
	 * @return IC_Settings_Page|null
	 */
	private function active_page_for_tab( $pages ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing check.
		$submenu = isset( $_GET['submenu'] ) ? sanitize_key( wp_unslash( $_GET['submenu'] ) ) : '';

		if ( '' !== $submenu ) {
			foreach ( $pages as $page ) {
				if ( $page->matches_submenu( $submenu ) ) {
					return $page;
				}
			}

			return null;
		}

		return empty( $pages ) ? null : $pages[0];
	}

	/**
	 * Returns generated submenu hook output.
	 *
	 * @param string                $tab_key Tab key.
	 * @param array                 $pages Registered screen pages.
	 * @param IC_Settings_Page|null $active_page Active page object.
	 *
	 * @return string
	 */
	private function tab_submenu_hook_html( $tab_key, $pages, $active_page ) {
		ob_start();
		do_action( 'ic_settings_screen_tab_submenus', $tab_key, $pages, $active_page, $this );
		return (string) ob_get_clean();
	}

	/**
	 * Returns the submenu URL for a screen page link.
	 *
	 * @param string           $tab_key Tab key.
	 * @param array            $tab Tab configuration.
	 * @param IC_Settings_Page $page Screen page object.
	 *
	 * @return string
	 */
	private function page_submenu_url( $tab_key, $tab, $page ) {
		$url         = $this->tab_url( $tab_key, $tab );
		$submenu_key = $page->screen_submenu();

		if ( '' === $submenu_key ) {
			return remove_query_arg( 'submenu', $url );
		}

		return add_query_arg( 'submenu', $submenu_key, $url );
	}

	/**
	 * Returns the wrapper class for generated page tabs.
	 *
	 * @param string $tab_key Tab key.
	 * @param array  $tab Tab configuration.
	 *
	 * @return string
	 */
	private function generated_tab_wrapper_class( $tab_key, $tab ) {
		$classes = array();

		if ( ! empty( $tab['content_wrapper_class'] ) ) {
			$classes[] = $tab['content_wrapper_class'];
		} else {
			$classes[] = sanitize_html_class( $tab_key );
		}

		$classes[] = 'settings-wrapper';

		return trim( implode( ' ', array_filter( $classes ) ) );
	}

	/**
	 * Renders the generated tab wrapper inline style.
	 *
	 * @param array $tab Tab configuration.
	 *
	 * @return void
	 */
	private function render_generated_tab_wrapper_style( $tab ) {
		if ( empty( $tab['content_wrapper_style'] ) ) {
			return;
		}
		?>
		style="<?php echo esc_attr( $tab['content_wrapper_style'] ); ?>"
		<?php
	}

	/**
	 * Renders any content configured after the tab body.
	 *
	 * @return void
	 */
	private function render_after_content() {
		if ( is_callable( $this->args['after_content_callback'] ) ) {
			call_user_func( $this->args['after_content_callback'] );
		}
	}

	/**
	 * Renders the shared impleCode logo row when enabled.
	 *
	 * @return void
	 */
	private function render_logo() {
		if ( empty( $this->args['show_logo'] ) ) {
			return;
		}

		$image_url = $this->logo_image_url();
		if ( empty( $image_url ) ) {
			return;
		}
		?>
		<div class="plugin-logo table-row">
			<div class="table-cell"></div>
			<div class="table-cell" style="padding-top: 20px">
				<?php do_action( $this->args['logo_content_hook'] ); ?>
			</div>
			<div class="table-cell">
				<a href="<?php echo esc_url( $this->args['logo_link_url'] ); ?>">
					<img class="en" src="<?php echo esc_url( $image_url ); ?>" width="<?php echo esc_attr( $this->args['logo_image_width'] ); ?>"
						alt="<?php echo esc_attr( $this->args['logo_image_alt'] ); ?>"/>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders generated tabs from the tab config.
	 *
	 * @param bool $use_active_tab_state Whether to mark the active tab.
	 *
	 * @return void
	 */
	private function render_generated_tabs( $use_active_tab_state = true ) {
		$active_tab = $this->active_tab();

		foreach ( $this->tabs() as $tab_key => $tab ) {
			if ( ! empty( $tab['render_callback'] ) && is_callable( $tab['render_callback'] ) ) {
				call_user_func( $tab['render_callback'], $tab_key, $tab, $this );
				continue;
			}

			$tab_class = empty( $tab['class'] ) ? 'nav-tab' : $tab['class'];
			if ( $this->is_tab_active( $tab_key, $tab, $active_tab, $use_active_tab_state ) ) {
				$tab_class .= ' nav-tab-active';
			}
			?>
				<a id="<?php echo esc_attr( $tab['menu_item_id'] ); ?>" class="<?php echo esc_attr( $tab_class ); ?>" href="<?php echo esc_url( $this->tab_url( $tab_key, $tab ) ); ?>"<?php $this->render_tab_title_attr( $tab ); ?><?php $this->render_tab_target_attr( $tab ); ?>><?php echo esc_html( $tab['label'] ); ?></a>
				<?php
		}
	}

	/**
	 * Renders tabs provided by configured hook callbacks.
	 *
	 * @return void
	 */
	private function render_tab_hooks() {
		$hooks = is_array( $this->args['tabs_hooks'] ) ? $this->args['tabs_hooks'] : array( $this->args['tabs_hooks'] );

		foreach ( $hooks as $hook ) {
			if ( empty( $hook ) || ! is_string( $hook ) ) {
				continue;
			}

			do_action( $hook, $this );
		}
	}

	/**
	 * Returns true when a tab should be marked active.
	 *
	 * @param string $tab_key Tab key.
	 * @param array  $tab Tab config.
	 * @param string $active_tab Active tab key.
	 * @param bool   $use_active_tab_state Whether the default active tab state should be used.
	 *
	 * @return bool
	 */
	private function is_tab_active( $tab_key, $tab, $active_tab, $use_active_tab_state ) {
		if ( ! empty( $tab['active_callback'] ) && is_callable( $tab['active_callback'] ) ) {
			return (bool) call_user_func( $tab['active_callback'], $tab_key, $tab, $this );
		}

		return $use_active_tab_state && $active_tab === $tab_key;
	}

	/**
	 * Renders the active tab class toggling script.
	 *
	 * @return void
	 */
	private function render_active_tab_script() {
		$active_tab = $this->active_tab();
		$tabs       = $this->tabs();
		if ( empty( $tabs[ $active_tab ]['menu_item_id'] ) ) {
			return;
		}
		?>
		<script>
			jQuery( function ( $ ) {
				$( '.nav-tab-wrapper a' ).removeClass( 'nav-tab-active' );
				$( <?php echo wp_json_encode( '.nav-tab-wrapper a#' . sanitize_html_class( $tabs[ $active_tab ]['menu_item_id'] ) ); ?> ).addClass( 'nav-tab-active' );
			} );
		</script>
		<?php
	}

	/**
	 * Renders the compact navigation script.
	 *
	 * @return void
	 */
	private function render_nav_compact_script() {
		$reference_id = sanitize_html_class( (string) $this->args['compact_reference_id'] );
		$hide_ids     = array();

		if ( empty( $reference_id ) || empty( $this->args['compact_hide_ids'] ) || ! is_array( $this->args['compact_hide_ids'] ) ) {
			return;
		}

		foreach ( $this->args['compact_hide_ids'] as $id ) {
			if ( ! empty( $id ) && is_string( $id ) ) {
				$hide_ids[] = sanitize_html_class( $id );
			}
		}

		if ( empty( $hide_ids ) ) {
			return;
		}
		?>
		<script>
			jQuery( function ( $ ) {
				var container = $( '.ic-nav-tab-wrapper' );
				var reference = container.find( <?php echo wp_json_encode( '#' . $reference_id ); ?> );

				if ( ! container.length || ! reference.length || container.width() > 1600 ) {
					return;
				}

				var referencePosition = reference.position();
				if ( ! referencePosition ) {
					return;
				}

				$.each( <?php echo wp_json_encode( $hide_ids ); ?>, function ( index, id ) {
					var link = container.find( '#' + id );
					if ( link.length && link.position() && link.position().top !== referencePosition.top ) {
						link.hide();
					}
				} );
			} );
		</script>
		<?php
	}

	/**
	 * Renders the optional default screen scripts.
	 *
	 * @return void
	 */
	private function render_screen_scripts() {
		if ( ! empty( $this->args['show_unsaved_changes_js'] ) ) {
			$this->render_unsaved_changes_script();
		}

		if ( ! empty( $this->args['show_sortable_rows_js'] ) ) {
			$this->render_sortable_rows_script();
		}
	}

	/**
	 * Renders the unsaved-changes warning script.
	 *
	 * @return void
	 */
	private function render_unsaved_changes_script() {
		?>
		<script>
			jQuery( function ( $ ) {
				$( 'div.setting-content.submenu form input:visible' ).change( function () {
					window.onbeforeunload = function () {
						return '';
					};
				} );
				$( document ).on( 'submit', 'form', function () {
					window.onbeforeunload = null;
				} );
				$( document ).on( 'click', '.ic-advanced-mode-wizard-button', function () {
					window.onbeforeunload = null;
				} );
			} );
		</script>
		<?php
	}

	/**
	 * Renders the sortable drag-table helper script.
	 *
	 * @return void
	 */
	private function render_sortable_rows_script() {
		?>
		<script>
			var fixHelper = function ( e, ui ) {
				ui.children().each( function () {
					jQuery( this ).width( jQuery( this ).width() );
				} );
				return ui;
			};

			jQuery( document ).ready( function () {
				if ( jQuery( 'body' ).outerWidth() < 800 ) {
					jQuery( '.product-settings-table.dragable tbody .dragger' ).hide();
					return true;
				}
				jQuery( '.product-settings-table.dragable tbody' ).sortable( {
					update: function () {
						jQuery( '.product-settings-table.dragable tbody tr' ).each( function () {
							var row = jQuery( this ).index() + 1;
							jQuery( this ).children( 'td:first-child' ).html( row );
							jQuery( this ).children( 'td:first-child' ).removeClass();
							jQuery( this ).children( 'td:first-child' ).addClass( 'lp-column lp' + row );
							jQuery( this ).find( 'input, textarea' ).each( function () {
								var name = jQuery( this ).attr( 'name' );
								name = name.replace( /[0-9]+(?!.*[0-9])/, row );
								jQuery( this ).attr( 'name', name );
							} );
						} );
					},
					helper: fixHelper,
					placeholder: 'sort-placeholder'
				} );
			} );
		</script>
		<?php
	}

	/**
	 * Returns normalized tab definitions.
	 *
	 * @return array
	 */
	private function tabs() {
		$tabs = $this->normalize_tabs( $this->args['tabs'] );
		$this->load_screen_pages();

		return apply_filters( 'ic_settings_screen_tabs', $tabs, $this );
	}

	/**
	 * Returns normalized tab definitions from a raw tab config.
	 *
	 * @param mixed $screen_tabs Raw tab config.
	 *
	 * @return array
	 */
	private function normalize_tabs( $screen_tabs ) {
		$tabs = array();

		if ( is_callable( $screen_tabs ) ) {
			$screen_tabs = call_user_func( $screen_tabs );
		}

		if ( empty( $screen_tabs ) || ! is_array( $screen_tabs ) ) {
			return $tabs;
		}

		foreach ( $screen_tabs as $tab_key => $tab ) {
			if ( is_int( $tab_key ) || ! is_array( $tab ) ) {
				continue;
			}

			$tab = wp_parse_args(
				$tab,
				array(
					'label'                 => '',
					'menu_item_id'          => $tab_key,
					'callback'              => '',
					'render_callback'       => '',
					'active_callback'       => '',
					'query_args'            => array(),
					'pages'                 => array(),
					'helpers'               => array(),
					'url'                   => '',
					'class'                 => 'nav-tab',
					'target'                => '',
					'title'                 => '',
					'default'               => false,
					'content_wrapper_class' => '',
					'content_wrapper_style' => '',
				)
			);

			if ( '' === $tab['menu_item_id'] ) {
				$tab['menu_item_id'] = $tab_key;
			}

			$tabs[ sanitize_key( $tab_key ) ] = $tab;
		}

		return $tabs;
	}

	/**
	 * Returns normalized tab display definitions.
	 *
	 * @return array
	 */
	private function tab_displays() {
		$displays = array();

		if ( empty( $this->args['tab_displays'] ) || ! is_array( $this->args['tab_displays'] ) ) {
			return $displays;
		}

		foreach ( $this->args['tab_displays'] as $display ) {
			if ( ! is_array( $display ) || empty( $display['hook'] ) || ! is_string( $display['hook'] ) ) {
				continue;
			}

			$displays[] = wp_parse_args(
				$display,
				array(
					'hook'                 => '',
					'priority'             => 10,
					'condition_callback'   => '',
					'use_active_tab_state' => false,
					'wrap_tag'             => 'div',
					'wrap_id'              => '',
					'wrap_class'           => '',
					'inner_wrap_tag'       => '',
					'inner_wrap_id'        => '',
					'inner_wrap_class'     => '',
					'before_html'          => '',
					'after_html'           => '',
				)
			);
		}

		return $displays;
	}

	/**
	 * Returns the current priority for a hooked callback.
	 *
	 * @param string $hook Hook name.
	 *
	 * @return int
	 */
	private function current_hook_priority( $hook ) {
		global $wp_filter;

		if ( empty( $hook ) || empty( $wp_filter[ $hook ] ) || ! method_exists( $wp_filter[ $hook ], 'current_priority' ) ) {
			return 10;
		}

		return intval( $wp_filter[ $hook ]->current_priority() );
	}

	/**
	 * Returns the configured logo image URL.
	 *
	 * @return string
	 */
	private function logo_image_url() {
		if ( ! empty( $this->args['logo_image_url'] ) ) {
			return (string) $this->args['logo_image_url'];
		}

		return plugins_url( '../img/implecode.png', __FILE__ );
	}

	/**
	 * Returns the default tab key.
	 *
	 * @return string
	 */
	private function default_tab_key() {
		$tabs = $this->tabs();

		foreach ( $tabs as $tab_key => $tab ) {
			if ( ! empty( $tab['default'] ) ) {
				return $tab_key;
			}
		}

		return empty( $tabs ) ? '' : (string) key( $tabs );
	}

	/**
	 * Returns the admin URL for a generated tab.
	 *
	 * @param string $tab_key Tab key.
	 * @param array  $tab Tab configuration.
	 *
	 * @return string
	 */
	private function tab_url( $tab_key, $tab = array() ) {
		if ( ! empty( $tab['url'] ) ) {
			return $tab['url'];
		}

		$base_url = '';
		if ( ! empty( $this->args['parent_slug'] ) && false !== strpos( $this->args['parent_slug'], '.php' ) ) {
			$query_args = array(
				'page' => $this->args['menu_slug'],
			);
			$path       = $this->args['parent_slug'];

			if ( false !== strpos( $path, '?' ) ) {
				list( $path, $query_string ) = explode( '?', $path, 2 );
				if ( ! empty( $query_string ) ) {
					parse_str( $query_string, $parent_query_args );
					if ( is_array( $parent_query_args ) ) {
						$query_args = array_merge( $parent_query_args, $query_args );
					}
				}
			}

			$base_url = add_query_arg( $query_args, admin_url( ltrim( $path, '/' ) ) );
		}

		if ( empty( $base_url ) ) {
			$base_url = menu_page_url( $this->args['menu_slug'], false );
			if ( ! empty( $base_url ) ) {
				$base_url = html_entity_decode( $base_url, ENT_QUOTES, get_bloginfo( 'charset' ) );
			}
		}
		if ( empty( $base_url ) ) {
			$base_url = admin_url( 'admin.php?page=' . rawurlencode( $this->args['menu_slug'] ) );
		}

		$query_args = array(
			'tab' => $tab_key,
		);

		if ( ! empty( $tab['query_args'] ) && is_array( $tab['query_args'] ) ) {
			$query_args = array_merge( $tab['query_args'], $query_args );
		}

		return add_query_arg( $query_args, remove_query_arg( array( 'updated', 'updated_id' ), $base_url ) );
	}

	/**
	 * Renders the tab title attribute when configured.
	 *
	 * @param array $tab Tab config.
	 *
	 * @return void
	 */
	private function render_tab_title_attr( $tab ) {
		if ( empty( $tab['title'] ) ) {
			return;
		}
		?>
		title="<?php echo esc_attr( $tab['title'] ); ?>"
		<?php
	}

	/**
	 * Renders the tab target attribute when configured.
	 *
	 * @param array $tab Tab config.
	 *
	 * @return void
	 */
	private function render_tab_target_attr( $tab ) {
		if ( empty( $tab['target'] ) ) {
			return;
		}
		?>
		target="<?php echo esc_attr( $tab['target'] ); ?>"
		<?php
	}

	/**
	 * Renders the content container inline style when configured.
	 *
	 * @return void
	 */
	private function render_content_container_style() {
		if ( empty( $this->args['content_container_style'] ) ) {
			return;
		}
		?>
		style="<?php echo esc_attr( $this->args['content_container_style'] ); ?>"
		<?php
	}

	/**
	 * Returns the page title.
	 *
	 * @return string
	 */
	private function page_title() {
		return '' !== $this->args['page_title'] ? $this->args['page_title'] : $this->title();
	}

	/**
	 * Returns the menu title.
	 *
	 * @return string
	 */
	private function menu_title() {
		return '' !== $this->args['menu_title'] ? $this->args['menu_title'] : $this->title();
	}

	/**
	 * Returns the screen heading.
	 *
	 * @return string
	 */
	private function title() {
		if ( '' !== $this->args['title'] ) {
			return $this->args['title'];
		}

		if ( '' !== $this->args['page_title'] ) {
			return $this->args['page_title'];
		}

		return $this->args['menu_title'];
	}

	/**
	 * Returns the screen capability.
	 *
	 * @return string
	 */
	private function capability() {
		return (string) $this->args['capability'];
	}
}
