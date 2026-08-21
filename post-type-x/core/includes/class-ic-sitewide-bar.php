<?php
/**
 * Sitewide bar integration.
 *
 * @package responsive-bar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles sitewide catalog bar rendering and settings.
 */
class IC_Sitewide_Bar {
	/**
	 * Excluded menu location score.
	 *
	 * @var int
	 */
	const MENU_LOCATION_EXCLUDED = -1;

	/**
	 * Neutral menu location score.
	 *
	 * @var int
	 */
	const MENU_LOCATION_NEUTRAL = 0;

	/**
	 * Footer fallback menu location score.
	 *
	 * @var int
	 */
	const MENU_LOCATION_FOOTER_FALLBACK = 1;

	/**
	 * Preferred menu location score.
	 *
	 * @var int
	 */
	const MENU_LOCATION_PREFERRED = 2;

	/**
	 * Icons display mode.
	 *
	 * @var string
	 */
	private $display = '';

	/**
	 * Search icon display flag.
	 *
	 * @var string
	 */
	private $search = '';

	/**
	 * Catalog icon display flag.
	 *
	 * @var string
	 */
	private $catalog = '';

	/**
	 * Search icon mode.
	 *
	 * @var string
	 */
	private $search_type = '';

	/**
	 * Menu target mode.
	 *
	 * @var string
	 */
	private $menu_target = 'auto';

	/**
	 * Tracks whether the icons already rendered on the current request.
	 *
	 * @var bool
	 */
	private $rendered = false;

	/**
	 * Hooks the sitewide bar integrations.
	 */
	public function __construct() {
		add_filter( 'wp_nav_menu_args', array( $this, 'wrap_fallback_callback' ), 99 );
		add_filter( 'wp_nav_menu_items', array( $this, 'show' ), 99, 2 );

		add_action( 'ic_catalog_design_schemes_top', array( $this, 'settings' ) );
		add_filter( 'ic_catalog_design_schemes', array( $this, 'settings_default' ) );
		add_action( 'ic_catalog_bar_content', array( $this, 'listing' ) );
		add_action( 'ic_catalog_bar_content', array( $this, 'search' ) );

		add_action( 'ic_catalog_customizer_sections', array( $this, 'customizer_sections' ) );
		add_filter( 'ic_customizer_settings', array( $this, 'customizer' ), 10, 2 );

		add_action( 'ic_register_blocks', array( $this, 'register_block' ) );

		add_action( 'enqueue_block_assets', array( $this, 'enqueue' ) );

		add_action( 'wp', array( $this, 'init' ) );
	}

	/**
	 * Enqueues frontend styles.
	 *
	 * @return void
	 */
	public function enqueue() {
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'al_product_styles' );
	}

	/**
	 * Loads the current design scheme settings.
	 *
	 * @return void
	 */
	public function init() {
		$design_schemes    = ic_get_design_schemes();
		$this->display     = $design_schemes['icons_display'];
		$this->search      = $design_schemes['icons_display_search'];
		$this->catalog     = $design_schemes['icons_display_catalog'];
		$this->search_type = $design_schemes['icons_search'];
		$this->menu_target = isset( $design_schemes['icons_menu_target'] ) ? (string) $design_schemes['icons_menu_target'] : 'auto';
		$this->rendered    = false;
	}

	/**
	 * Outputs the sitewide bar in supported menu locations.
	 *
	 * @param string|null $nav_menu Existing menu HTML.
	 * @param object|null $args     Menu arguments.
	 * @return string|null
	 */
	public function show( $nav_menu = null, $args = null ) {
		if ( ! $this->should_append_to_menu( $nav_menu, $args ) ) {
			return $nav_menu;
		}

		return $nav_menu . $this->icons();
	}

	/**
	 * Wraps menu fallback callbacks so automatically generated menus can receive icons too.
	 *
	 * @param array $args Menu arguments.
	 * @return array
	 */
	public function wrap_fallback_callback( $args ) {
		if ( ! $this->is_displayed() || ! $this->supports_classic_menu_auto_detection() ) {
			return $args;
		}

		if ( empty( $args['fallback_cb'] ) || ! is_callable( $args['fallback_cb'] ) ) {
			return $args;
		}

		if ( $this->is_fallback_callback_wrapped( $args['fallback_cb'] ) ) {
			return $args;
		}

		$args['ic_catalog_original_fallback_cb'] = $args['fallback_cb'];
		$args['fallback_cb']                     = array( $this, 'show_fallback_menu' );

		return $args;
	}

	/**
	 * Renders a wrapped fallback menu and appends icons when the fallback target is eligible.
	 *
	 * @param array $args Menu arguments.
	 * @return string|false
	 */
	public function show_fallback_menu( $args ) {
		if ( empty( $args['ic_catalog_original_fallback_cb'] ) || ! is_callable( $args['ic_catalog_original_fallback_cb'] ) ) {
			return false;
		}

		ob_start();
		$result = call_user_func( $args['ic_catalog_original_fallback_cb'], $args );
		$output = ob_get_clean();

		if ( '' !== $output ) {
			$nav_menu = $output;
		} elseif ( is_string( $result ) ) {
			$nav_menu = $result;
		} else {
			return $result;
		}

		if ( $this->should_append_to_menu( $nav_menu, (object) $args ) ) {
			$nav_menu = $this->append_icons_to_fallback_menu( $nav_menu );
		}

		if ( ! empty( $args['echo'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Menu markup is already escaped by the original fallback callback and icon renderer.
			echo $nav_menu;
		}

		return $nav_menu;
	}

	/**
	 * Legacy no-op. Sitewide icons now use menu-only fallback targets.
	 *
	 * @return void
	 */
	public function render_fallback() {
		// Standalone fallback output was removed in favor of menu-only injection.
	}

	/**
	 * Builds the sitewide icons markup.
	 *
	 * @return string
	 */
	public function icons() {
		$this->rendered = true;
		ob_start();
		echo '<div id="ic-catalog-menu-bar">';
		ic_show_template_file( 'sitewide-icons/icon-bar.php' );
		echo '</div>';

		return ob_get_clean();
	}

	/**
	 * Outputs an icon container.
	 *
	 * @param string $content   Icon HTML content.
	 * @param string $css_class Optional container class.
	 * @return void
	 */
	public function icon_container( $content, $css_class = '' ) {
		if ( empty( $content ) ) {
			return;
		}
		if ( is_custom_product_listing_page() ) {
			if ( ! empty( $css_class ) ) {
				$css_class .= ' ';
			}
			$css_class .= 'current-menu-item';
		}
		?>
		<div class="ic-bar-icon <?php echo esc_attr( $css_class ); ?>">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: HTML assembled earlier in this scope, so escaping it here would print the tags.
			echo $content;
			?>
		</div>
		<?php
	}

	/**
	 * Builds a single icon link.
	 *
	 * @param string      $url             Icon URL or HTML content.
	 * @param string      $icon            Icon class suffix.
	 * @param string|null $content         Optional hidden content.
	 * @param string      $container_class Optional container class.
	 * @return void
	 */
	public function icon( $url, $icon, $content = null, $container_class = '' ) {
		if ( empty( $url ) || empty( $icon ) ) {
			return;
		}
		if ( ! $this->is_url( $url ) ) {
			$content = $url;
			$url     = '';
		}
		$design_schemes = ic_get_design_schemes();
		$class          = ' button ' . implode( ' ', array_filter( $design_schemes ) );
		if ( ! empty( $content ) ) {
			$class .= ' ic-show-content';
		}
		$icon_content  = '<a class="ic-icon-url' . $class . '" href="' . $url . '">';
		$icon_content .= '<span class="' . $this->icons_type() . $icon . '"></span>';
		$icon_content .= '</a>';
		if ( ! empty( $content ) ) {
			$icon_content .= '<div class="ic-icon-hidden-content"><div class="ic-icon-hidden-content-inside"><span class="ic-popup-close dashicons dashicons-no-alt"></span>' . $content . '</div></div>';
		}
		$this->icon_container( $icon_content, $container_class );
	}

	/**
	 * Outputs text inside the sitewide bar.
	 *
	 * @param string $text      Text or trusted HTML content.
	 * @param string $css_class Optional text class suffix.
	 * @return void
	 */
	public function text( $text, $css_class = '' ) {
		if ( empty( $text ) ) {
			return;
		}
		if ( ! empty( $css_class ) ) {
			$css_class = 'ic-bar-text-' . $css_class;
		}
		?>
		<div class="ic-bar-text <?php echo esc_attr( $css_class ); ?>">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: HTML assembled earlier in this scope, so escaping it here would print the tags.
			echo $text;
			?>
		</div>
		<?php
	}

	/**
	 * Returns the icon class prefix.
	 *
	 * @return string
	 */
	public function icons_type() {
		return apply_filters( 'ic_catalog_bar_icons_type', 'dashicons dashicons-' );
	}

	/**
	 * Determines whether icons should append to the current menu.
	 *
	 * @param string|null $nav_menu Existing menu HTML.
	 * @param object|null $args     Menu arguments.
	 * @return bool
	 */
	private function should_append_to_menu( $nav_menu, $args ) {
		if ( ! $this->is_displayed() || $this->rendered || ! $this->supports_classic_menu_auto_detection() ) {
			return false;
		}

		if ( empty( $nav_menu ) || empty( $args ) ) {
			return false;
		}

		return $this->matches_menu_target( $args );
	}

	/**
	 * Determines whether the current render matches the configured menu target.
	 *
	 * @param object $args Menu arguments.
	 * @return bool
	 */
	private function matches_menu_target( $args ) {
		$menu_target = $this->selected_menu_target();

		if ( 'auto' === $menu_target ) {
			return $this->get_menu_location_score( $args ) >= self::MENU_LOCATION_NEUTRAL;
		}

		if ( 'theme_header' === $menu_target ) {
			return self::MENU_LOCATION_PREFERRED === $this->get_menu_location_score( $args );
		}

		if ( 'theme_footer' === $menu_target ) {
			return self::MENU_LOCATION_FOOTER_FALLBACK === $this->get_menu_location_score( $args );
		}

		if ( 0 === strpos( $menu_target, 'location:' ) ) {
			$location = substr( $menu_target, 9 );
			if ( '' === $location ) {
				return false;
			}

			return in_array( $location, $this->get_menu_location_candidates( $args ), true );
		}

		return false;
	}

	/**
	 * Returns the current menu score for the provided menu args.
	 *
	 * @param object $args Menu arguments.
	 * @return int
	 */
	private function get_menu_location_score( $args ) {
		$registered_locations = get_registered_nav_menus();

		if ( ! empty( $args->theme_location ) && is_string( $args->theme_location ) ) {
			return $this->get_menu_location_candidate_score( $args->theme_location, $args, $registered_locations );
		}

		$menu_locations = $this->get_menu_locations_from_args( $args );
		if ( empty( $menu_locations ) ) {
			return $this->get_menu_location_candidate_score( '', $args, $registered_locations );
		}

		$score = self::MENU_LOCATION_EXCLUDED;
		foreach ( $menu_locations as $theme_location ) {
			$location_score = $this->get_menu_location_candidate_score( $theme_location, $args, $registered_locations );
			if ( $location_score > $score ) {
				$score = $location_score;
			}
		}

		return $score;
	}

	/**
	 * Returns runtime menu location candidates for the provided args.
	 *
	 * @param object $args Menu arguments.
	 * @return array
	 */
	private function get_menu_location_candidates( $args ) {
		if ( ! empty( $args->theme_location ) && is_string( $args->theme_location ) ) {
			return array( $args->theme_location );
		}

		return $this->get_menu_locations_from_args( $args );
	}

	/**
	 * Returns the score for a single menu location candidate.
	 *
	 * @param string $theme_location      Menu location slug.
	 * @param object $args                Menu arguments.
	 * @param array  $registered_locations Registered menu location labels.
	 * @return int
	 */
	private function get_menu_location_candidate_score( $theme_location, $args, $registered_locations ) {
		$location_label = ! empty( $registered_locations[ $theme_location ] ) ? $registered_locations[ $theme_location ] : '';
		$score          = self::MENU_LOCATION_NEUTRAL;

		if ( $this->matches_menu_location_terms( array( $location_label ), $this->excluded_menu_location_terms() ) || $this->matches_menu_location_terms( array( $theme_location ), $this->excluded_menu_location_terms() ) ) {
			$score = self::MENU_LOCATION_EXCLUDED;
		} elseif ( $this->matches_menu_location_terms( array( $location_label ), $this->footer_fallback_menu_location_terms() ) || $this->matches_menu_location_terms( array( $theme_location ), $this->footer_fallback_menu_location_terms() ) ) {
			$score = self::MENU_LOCATION_FOOTER_FALLBACK;
		} elseif ( $this->matches_menu_location_terms( array( $location_label ), $this->preferred_menu_location_terms() ) || $this->matches_menu_location_terms( array( $theme_location ), $this->preferred_menu_location_terms() ) ) {
			$score = self::MENU_LOCATION_PREFERRED;
		}

		return (int) apply_filters( 'ic_catalog_sitewide_menu_location_score', $score, $theme_location, $args, $registered_locations );
	}

	/**
	 * Returns assigned menu locations for the resolved menu in the args.
	 *
	 * @param object $args Menu arguments.
	 * @return array
	 */
	private function get_menu_locations_from_args( $args ) {
		$menu = $this->get_menu_object_from_args( $args );
		if ( empty( $menu->term_id ) ) {
			return array();
		}

		$menu_term_id       = (int) $menu->term_id;
		$assigned_locations = array();
		foreach ( get_nav_menu_locations() as $location => $term_id ) {
			if ( (int) $term_id === $menu_term_id ) {
				$assigned_locations[] = $location;
			}
		}

		return $assigned_locations;
	}

	/**
	 * Resolves the current menu object from the menu args.
	 *
	 * @param object $args Menu arguments.
	 * @return object|null
	 */
	private function get_menu_object_from_args( $args ) {
		if ( empty( $args->menu ) ) {
			return null;
		}

		$menu = wp_get_nav_menu_object( $args->menu );
		if ( ! empty( $menu ) && ! is_wp_error( $menu ) ) {
			return $menu;
		}

		return null;
	}

	/**
	 * Checks whether the provided callback is already wrapped by EPC.
	 *
	 * @param mixed $fallback_callback Fallback callback.
	 * @return bool
	 */
	private function is_fallback_callback_wrapped( $fallback_callback ) {
		return is_array( $fallback_callback ) && isset( $fallback_callback[0], $fallback_callback[1] ) && $this === $fallback_callback[0] && 'show_fallback_menu' === $fallback_callback[1];
	}

	/**
	 * Inserts the icon bar into fallback markup before the closing list tag when available.
	 *
	 * @param string $nav_menu Fallback menu markup.
	 * @return string
	 */
	private function append_icons_to_fallback_menu( $nav_menu ) {
		$icons                = $this->icons();
		$closing_tag_position = strripos( $nav_menu, '</ul>' );

		if ( false === $closing_tag_position ) {
			return $nav_menu . $icons;
		}

		return substr( $nav_menu, 0, $closing_tag_position ) . $icons . substr( $nav_menu, $closing_tag_position );
	}

	/**
	 * Checks if the current request should use classic menu auto-detection.
	 *
	 * @return bool
	 */
	private function supports_classic_menu_auto_detection() {
		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns excluded menu location terms.
	 *
	 * @return array
	 */
	private function excluded_menu_location_terms() {
		return array();
	}

	/**
	 * Returns footer fallback menu location terms.
	 *
	 * @return array
	 */
	private function footer_fallback_menu_location_terms() {
		return array(
			'footer',
			'bottom',
		);
	}

	/**
	 * Returns preferred menu location terms.
	 *
	 * @return array
	 */
	private function preferred_menu_location_terms() {
		return array(
			'primary',
			'main',
			'header',
			'top',
			'navigation',
			'menu-1',
		);
	}

	/**
	 * Checks whether any location value contains the provided terms.
	 *
	 * @param array $location_values Theme location values to inspect.
	 * @param array $terms           Terms to match.
	 * @return bool
	 */
	private function matches_menu_location_terms( $location_values, $terms ) {
		foreach ( $location_values as $location_value ) {
			if ( ! is_string( $location_value ) || '' === $location_value ) {
				continue;
			}

			foreach ( $terms as $term ) {
				if ( ic_string_contains( $location_value, $term, false ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Outputs the listing icon when enabled.
	 *
	 * @return void
	 */
	public function listing() {
		if ( ! is_ic_product_listing_enabled() ) {
			return;
		}

		if ( ! empty( $this->catalog ) ) {
			return;
		}
		$listing_page = product_listing_url();
		if ( ! empty( $listing_page ) ) {
			$this->icon( $listing_page, 'store' );
		}
	}

	/**
	 * Outputs the search icon when enabled.
	 *
	 * @return void
	 */
	public function search() {
		if ( ! empty( $this->search ) ) {
			return;
		}
		ob_start();
		ic_save_global( 'search_widget_instance', array( 'title' => '' ) );
		add_filter( 'ic_search_box_class', array( __CLASS__, 'box_class' ) );
		ic_show_search_widget_form();
		$search = ob_get_clean();
		if ( ! empty( $search ) ) {
			$this->icon( $search, 'search' );
		}
	}

	/**
	 * Adds the design scheme box class to the search widget.
	 *
	 * @param string $box_class Existing search widget classes.
	 * @return string
	 */
	public static function box_class( $box_class ) {
		if ( ! empty( $box_class ) ) {
			$box_class .= ' ';
		}
		$box_class .= design_schemes( 'box', 0 );

		return $box_class;
	}

	/**
	 * Registers the sitewide icons block.
	 *
	 * @return void
	 */
	public function register_block() {
		global $wp_version;
		register_block_type(
			__DIR__ . '/blocks/sitewide-icons/',
			array(
				'api_version'     => version_compare( $wp_version, '6.3', '>=' ) ? 3 : 2,
				'render_callback' => array( $this, 'icons' ),
			)
		);
	}

	/**
	 * Outputs the design settings fields.
	 *
	 * @param array $design_schemes Current design settings.
	 * @return void
	 */
	public function settings( $design_schemes ) {
		?>
		<h3><?php esc_html_e( 'Sitewide Icons', 'post-type-x' ); ?></h3>
		<table>
			<?php
			implecode_settings_radio( __( 'Icons Display', 'post-type-x' ), 'design_schemes[icons_display]', $design_schemes['icons_display'], $this->icons_display_options() );
			implecode_settings_dropdown( __( 'Menu Target', 'post-type-x' ), 'design_schemes[icons_menu_target]', $design_schemes['icons_menu_target'], $this->icons_menu_target_options() );
			implecode_settings_checkbox( __( 'Hide Catalog Icon', 'post-type-x' ), 'design_schemes[icons_display_catalog]', $design_schemes['icons_display_catalog'] );
			implecode_settings_checkbox( __( 'Hide Search Icon', 'post-type-x' ), 'design_schemes[icons_display_search]', $design_schemes['icons_display_search'] );
			implecode_settings_radio( __( 'Search Icon', 'post-type-x' ), 'design_schemes[icons_search]', $design_schemes['icons_search'], $this->icons_search_options() );
			do_action( 'ic_catalog_sitewide_icons_settings_html', $design_schemes );
			?>
		</table>
		<?php
	}

	/**
	 * Returns display mode options.
	 *
	 * @return array
	 */
	public function icons_display_options() {
		return array(
			'all'   => __( 'All devices', 'post-type-x' ),
			'small' => __( 'Small screens only', 'post-type-x' ),
			'none'  => __( 'Disabled', 'post-type-x' ),
		);
	}

	/**
	 * Returns menu target options.
	 *
	 * @return array
	 */
	public function icons_menu_target_options() {
		$options = array(
			'auto'         => __( 'Auto', 'post-type-x' ),
			'theme_header' => __( 'Theme Header', 'post-type-x' ),
			'theme_footer' => __( 'Theme Footer', 'post-type-x' ),
		);

		foreach ( get_registered_nav_menus() as $location => $label ) {
			$options[ 'location:' . $location ] = $this->menu_target_location_label( $location, $label );
		}

		return $options;
	}

	/**
	 * Returns search mode options.
	 *
	 * @return array
	 */
	public function icons_search_options() {
		return array(
			'field'    => __( 'Simple Field', 'post-type-x' ),
			'ic_popup' => __( 'Popup', 'post-type-x' ),
		);
	}

	/**
	 * Adds default values for the sitewide icon settings.
	 *
	 * @param array $settings Existing design settings.
	 * @return array
	 */
	public function settings_default( $settings ) {
		$settings['icons_display']         = isset( $settings['icons_display'] ) ? $settings['icons_display'] : 'none';
		$settings['icons_menu_target']     = isset( $settings['icons_menu_target'] ) ? $settings['icons_menu_target'] : 'auto';
		$settings['icons_display_catalog'] = isset( $settings['icons_display_catalog'] ) ? $settings['icons_display_catalog'] : '';
		$settings['icons_display_search']  = isset( $settings['icons_display_search'] ) ? $settings['icons_display_search'] : '';
		$settings['icons_search']          = isset( $settings['icons_search'] ) ? $settings['icons_search'] : 'ic_popup';

		return apply_filters( 'ic_catalog_sitewide_icons_settings', $settings );
	}

	/**
	 * Determines whether the sitewide bar should be displayed.
	 *
	 * @return bool
	 */
	public function is_displayed() {
		if ( empty( $this->display ) || ( ! empty( $this->display ) && 'none' === $this->display ) ) {
			return false;
		}
		if ( empty( $this->catalog ) || empty( $this->search ) ) {
			return true;
		}

		return apply_filters( 'ic_catalog_sitewide_icons_displayed', false );
	}

	/**
	 * Determines whether the provided value is a URL.
	 *
	 * @param string $url Value to validate.
	 * @return bool
	 */
	public function is_url( $url ) {
		if ( esc_url_raw( $url ) === $url ) {
			return true;
		}

		return false;
	}

	/**
	 * Returns the wrapper classes for the sitewide bar.
	 *
	 * @return string
	 */
	public static function container_class() {
		$design_schemes = ic_get_design_schemes();
		$class          = 'ic-catalog-bar device-' . $design_schemes['icons_display'] . ' ' . $design_schemes['icons_search'];

		return $class;
	}

	/**
	 * Registers Customizer sections for the sitewide bar.
	 *
	 * @param WP_Customize_Manager $wp_customize Customizer manager instance.
	 * @return void
	 */
	public function customizer_sections( $wp_customize ) {
		$message         = __( 'In Auto mode the icons will appear in the most appropriate classic theme menu.', 'post-type-x' );
		$site_editor_url = admin_url( 'site-editor.php' );
		if ( ! empty( $site_editor_url ) ) {
			$message .= ' ';
			/* translators: 1: opening link tag to the Site Editor, 2: closing link tag. */
			$message .= sprintf( __( 'You can also %1$sadd the Catalog Icons block to the menu%2$s if your theme supports site editing with blocks.', 'post-type-x' ), '<a href="' . esc_url( $site_editor_url ) . '">', '</a>' );
		}

		$wp_customize->add_section(
			'ic_product_catalog_icons',
			array(
				'title'       => __( 'Sitewide Icons', 'post-type-x' ),
				'priority'    => 30,
				'panel'       => 'ic_product_catalog',
				'description' => $message,
			)
		);
	}

	/**
	 * Registers Customizer settings for the sitewide bar.
	 *
	 * @param array  $settings   Existing Customizer settings.
	 * @param object $customizer Customizer helper instance.
	 * @return array
	 */
	public function customizer( $settings, $customizer ) {
		$settings[] = array(
			'name'    => 'design_schemes[icons_display]',
			'args'    => array(
				'type'    => 'option',
				'default' => 'none',
			),
			'control' => array(
				'name' => 'ic_pc_integration_icons_display',
				'args' => array(
					'label'    => __( 'Icons Display', 'post-type-x' ),
					'section'  => 'ic_product_catalog_icons',
					'settings' => 'design_schemes[icons_display]',
					'type'     => 'radio',
					'choices'  => $this->icons_display_options(),
				),
			),
		);
		$settings[] = array(
			'name'    => 'design_schemes[icons_menu_target]',
			'args'    => array(
				'type'              => 'option',
				'default'           => 'auto',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'control' => array(
				'name' => 'ic_pc_integration_icons_menu_target',
				'args' => array(
					'label'    => __( 'Menu Target', 'post-type-x' ),
					'section'  => 'ic_product_catalog_icons',
					'settings' => 'design_schemes[icons_menu_target]',
					'type'     => 'select',
					'choices'  => $this->icons_menu_target_options(),
				),
			),
		);
		$settings[] = array(
			'name'    => 'design_schemes[icons_display_catalog]',
			'args'    => array(
				'type'              => 'option',
				'default'           => '',
				'sanitize_callback' => array( $customizer, 'sanitize_checkbox' ),
			),
			'control' => array(
				'name' => 'ic_pc_integration_icons_display_catalog',
				'args' => array(
					'label'    => __( 'Hide Catalog Icon', 'post-type-x' ),
					'section'  => 'ic_product_catalog_icons',
					'settings' => 'design_schemes[icons_display_catalog]',
					'type'     => 'checkbox',
				),
			),
		);
		$settings[] = array(
			'name'    => 'design_schemes[icons_display_search]',
			'args'    => array(
				'type'              => 'option',
				'default'           => '',
				'sanitize_callback' => array( $customizer, 'sanitize_checkbox' ),
			),
			'control' => array(
				'name' => 'ic_pc_integration_icons_display_search',
				'args' => array(
					'label'    => __( 'Hide Search Icon', 'post-type-x' ),
					'section'  => 'ic_product_catalog_icons',
					'settings' => 'design_schemes[icons_display_search]',
					'type'     => 'checkbox',
				),
			),
		);
		$settings[] = array(
			'name'    => 'design_schemes[icons_search]',
			'args'    => array(
				'type'    => 'option',
				'default' => 'ic_popup',
			),
			'control' => array(
				'name' => 'ic_pc_integration_icons_search',
				'args' => array(
					'label'    => __( 'Search Icon', 'post-type-x' ),
					'section'  => 'ic_product_catalog_icons',
					'settings' => 'design_schemes[icons_search]',
					'type'     => 'radio',
					'choices'  => $this->icons_search_options(),
				),
			),
		);

		return $settings;
	}

	/**
	 * Returns the active menu target mode.
	 *
	 * @return string
	 */
	private function selected_menu_target() {
		if ( '' === $this->menu_target ) {
			return 'auto';
		}

		if ( 0 === strpos( $this->menu_target, 'location:' ) ) {
			$location = substr( $this->menu_target, 9 );
			if ( '' === $location || ! isset( get_registered_nav_menus()[ $location ] ) ) {
				return 'auto';
			}
		}

		return $this->menu_target;
	}

	/**
	 * Builds a menu-target option label for one registered theme location.
	 *
	 * @param string $location Theme location slug.
	 * @param string $label    Theme location label.
	 * @return string
	 */
	private function menu_target_location_label( $location, $label ) {
		if ( empty( $label ) ) {
			return $location;
		}

		/* translators: 1: menu location label, 2: menu location slug. */
		return sprintf( __( '%1$s (%2$s)', 'post-type-x' ), $label, $location );
	}
}

global $ic_sitewide_bar;
$ic_sitewide_bar = new IC_Sitewide_Bar();
