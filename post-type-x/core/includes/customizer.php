<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Manages theme customizer
 *
 * @version        1.0.0
 * @package        catalog-me/framework/customizer
 * @author        impleCode
 */
class ic_catalog_customizer {

	public function __construct() {
		add_action( 'customize_register', array( $this, 'panel' ) );
		add_action( 'customize_register', array( $this, 'sections' ) );
		add_action( 'customize_register', array( $this, 'settings' ) );
		add_filter( 'ic_customizer_settings', array( $this, 'product_page_labels' ) );
		add_filter( 'ic_customizer_settings', array( $this, 'product_listing_labels' ) );
		//add_action( 'customize_register', array( $this, 'controls' ) );
	}

	public function register_settings() {
		$settings = array(
			array(
				'name'    => 'multi_single_options[template]',
				'args'    => array( 'type' => 'option', 'default' => 'boxed' ),
				'control' => array(
					'name' => 'ic_pc_integration_template',
					'args' => array(
						'label'    => __( 'Select template', 'post-type-x' ),
						'section'  => 'ic_product_catalog_integration',
						'settings' => 'multi_single_options[template]',
						'type'     => 'radio',
						'choices'  => array(
							'boxed' => __( 'Formatted', 'post-type-x' ),
							'plain' => __( 'Plain', 'post-type-x' )
						)
					)
				)
			)
		);
		if ( ! is_ic_shortcode_integration() ) {
			$settings[] = array(
				'name'    => 'archive_multiple_settings[disable_name]',
				'args'    => array(
					'type'              => 'option',
					'default'           => '',
					'sanitize_callback' => array( $this, 'sanitize_checkbox' )
				),
				'control' => array(
					'name' => 'ic_pc_integration_name',
					'args' => array(
						'label'    => __( 'Disable Product Name', 'post-type-x' ),
						'section'  => 'ic_product_catalog_integration',
						'settings' => 'archive_multiple_settings[disable_name]',
						'type'     => 'checkbox',
					)
				)
			);
		}
		$settings[] = array(
			'name'    => 'multi_single_options[enable_product_gallery]',
			'args'    => array(
				'type'              => 'option',
				'default'           => 1,
				'sanitize_callback' => array( $this, 'sanitize_checkbox' )
			),
			'control' => array(
				'name' => 'ic_pc_integration_gallery',
				'args' => array(
					'label'    => __( 'Enable image', 'post-type-x' ),
					'section'  => 'ic_product_catalog_integration',
					'settings' => 'multi_single_options[enable_product_gallery]',
					'type'     => 'checkbox',
				)
			)
		);

		$settings[] = array(
			'name'    => 'catalog_lightbox',
			'args'    => array(
				'type'              => 'option',
				'default'           => 1,
				'sanitize_callback' => array( $this, 'sanitize_checkbox' )
			),
			'control' => array(
				'name' => 'ic_pc_integration_lightbox',
				'args' => array(
					'label'    => __( 'Enable lightbox gallery', 'post-type-x' ),
					'section'  => 'ic_product_catalog_integration',
					'settings' => 'catalog_lightbox',
					'type'     => 'checkbox',
				)
			)
		);
		$settings[] = array(
			'name'    => 'catalog_magnifier',
			'args'    => array(
				'type'              => 'option',
				'default'           => 1,
				'sanitize_callback' => array( $this, 'sanitize_checkbox' )
			),
			'control' => array(
				'name' => 'ic_pc_integration_magnifier',
				'args' => array(
					'label'    => __( 'Enable image magnifier', 'post-type-x' ),
					'section'  => 'ic_product_catalog_integration',
					'settings' => 'catalog_magnifier',
					'type'     => 'checkbox',
				)
			)
		);
		$settings[] = array(
			'name'    => 'multi_single_options[enable_product_gallery_only_when_exist]',
			'args'    => array(
				'type'              => 'option',
				'default'           => '',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' )
			),
			'control' => array(
				'name' => 'ic_pc_integration_disable_default_image',
				'args' => array(
					'label'    => __( 'Enable image only when inserted', 'post-type-x' ),
					'section'  => 'ic_product_catalog_integration',
					'settings' => 'multi_single_options[enable_product_gallery_only_when_exist]',
					'type'     => 'checkbox',
				)
			)
		);
		$settings[] = array(
			'name'    => 'design_schemes[price-color]',
			'args'    => array( 'type' => 'option', 'default' => 'red-price' ),
			'control' => array(
				'name' => 'ic_pc_integration_price_color',
				'args' => array(
					'label'    => __( 'Price Color', 'post-type-x' ),
					'section'  => 'ic_product_catalog_integration',
					'settings' => 'design_schemes[price-color]',
					'type'     => 'select',
					'choices'  => array(
						'red-price'    => __( 'Red', 'post-type-x' ),
						'orange-price' => __( 'Orange', 'post-type-x' ),
						'green-price'  => __( 'Green', 'post-type-x' ),
						'blue-price'   => __( 'Blue', 'post-type-x' ),
						'grey-price'   => __( 'Grey', 'post-type-x' )
					)
				)
			)
		);
		$settings[] = array(
			'name'    => 'design_schemes[price-size]',
			'args'    => array( 'type' => 'option', 'default' => 'big-price' ),
			'control' => array(
				'name' => 'ic_pc_integration_price_size',
				'args' => array(
					'label'    => __( 'Price Size', 'post-type-x' ),
					'section'  => 'ic_product_catalog_integration',
					'settings' => 'design_schemes[price-size]',
					'type'     => 'select',
					'choices'  => array(
						'big-price'   => __( 'Big', 'post-type-x' ),
						'small-price' => __( 'Small', 'post-type-x' )
					)
				)
			)
		);
		$settings[] = array(
			'name'    => 'archive_template',
			'args'    => array( 'type' => 'option', 'default' => 'default' ),
			'control' => array(
				'name' => 'ic_pc_archive_template',
				'args' => array(
					'label'    => __( 'Product Listing Template', 'post-type-x' ),
					'section'  => 'ic_product_catalog_listing',
					'settings' => 'archive_template',
					'type'     => 'radio',
					'choices'  => ic_get_available_templates()
				)
			)
		);

		return apply_filters( 'ic_customizer_settings', $settings, $this );
	}

	public function register_integration_settings() {
		$theme = get_option( 'template' );

		$settings = array(
			array(
				'name'    => 'archive_multiple_settings[container_bg][' . $theme . ']',
				'args'    => array( 'type' => 'option', 'default' => '' ),
				'control' => array(
					'name' => 'ic_pc_integration_bg',
					'args' => array(
						'label'    => __( 'Background', 'post-type-x' ),
						'section'  => 'ic_product_catalog_integration',
						'settings' => 'archive_multiple_settings[container_bg][' . $theme . ']',
						'type'     => 'color',
					)
				)
			),
			array(
				'name'    => 'archive_multiple_settings[container_text][' . $theme . ']',
				'args'    => array( 'type' => 'option', 'default' => '' ),
				'control' => array(
					'name' => 'ic_pc_integration_text',
					'args' => array(
						'label'    => __( 'Text Color', 'post-type-x' ),
						'section'  => 'ic_product_catalog_integration',
						'settings' => 'archive_multiple_settings[container_text][' . $theme . ']',
						'type'     => 'color',
					)
				)
			),
			array(
				'name'    => 'archive_multiple_settings[default_sidebar]',
				'args'    => array( 'type' => 'option', 'default' => '' ),
				'control' => array(
					'name' => 'ic_pc_integration_sidebar',
					'args' => array(
						'label'    => __( 'Sidebar', 'post-type-x' ),
						'section'  => 'ic_product_catalog_integration',
						'settings' => 'archive_multiple_settings[default_sidebar]',
						'type'     => 'radio',
						'choices'  => array(
							'none'  => __( 'Disabled', 'post-type-x' ),
							'left'  => __( 'Left', 'post-type-x' ),
							'right' => __( 'Right', 'post-type-x' )
						)
					)
				)
			)
		);

		return apply_filters( 'ic_customizer_integration_settings', $settings );
	}

	function sanitize_checkbox( $checked ) {
		return $checked == 1 ? 1 : '';
	}

	public function product_page_labels( $settings ) {
		$single_names = default_single_names();
		foreach ( $single_names as $key => $names ) {
			$label      = ucwords( trim( str_replace( array( '_', 'product' ), ' ', $key ) ) );
			$settings[] = array(
				'name'    => 'single_names[' . $key . ']',
				'args'    => array( 'type' => 'option', 'default' => $names ),
				'control' => array(
					'name' => 'ic_pc_single_' . $key,
					'args' => array(
						'label'    => sprintf( __( '%s Label', 'post-type-x' ), $label ),
						'section'  => 'ic_product_catalog_integration',
						'settings' => 'single_names[' . $key . ']',
						'type'     => 'text',
					)
				)
			);
		}

		return $settings;
	}

	public function product_listing_labels( $settings ) {
		$archive_names = default_archive_names();
		foreach ( $archive_names as $key => $names ) {
			$label      = ucwords( trim( str_replace( array( '_', 'bread' ), ' ', $key ) ) );
			$settings[] = array(
				'name'    => 'archive_names[' . $key . ']',
				'args'    => array( 'type' => 'option', 'default' => $names ),
				'control' => array(
					'name' => 'ic_pc_archive_' . $key,
					'args' => array(
						'label'    => sprintf( __( '%s Label', 'post-type-x' ), $label ),
						'section'  => 'ic_product_catalog_listing',
						'settings' => 'archive_names[' . $key . ']',
						'type'     => 'text',
					)
				)
			);
		}

		return $settings;
	}

	/**
	 * Registers customizer settings
	 *
	 * @param object $wp_customize
	 */
	public function panel( $wp_customize ) {
		$wp_customize->add_panel( 'ic_product_catalog', array(
			'priority'       => 10,
			'capability'     => 'manage_product_settings',
			'theme_supports' => '',
			'title'          => __( 'Product Catalog', 'post-type-x' ),
			'description'    => __( 'Go to catalog page that you want to style so you can see the effect immediately.', 'post-type-x' ),
		) );
	}

	public function sections( $wp_customize ) {

		$wp_customize->add_section( 'ic_product_catalog_integration', array(
			'title'       => __( 'Product Page Style', 'post-type-x' ),
			'priority'    => 30,
			'panel'       => 'ic_product_catalog',
			'description' => __( 'Please adjust these settings on a product page to see any effect.', 'post-type-x' )
		) );
		$wp_customize->add_section( 'ic_product_catalog_listing', array(
			'title'       => __( 'Product Listing Style', 'post-type-x' ),
			'priority'    => 30,
			'panel'       => 'ic_product_catalog',
			'description' => __( 'Please adjust these settings on a product listing to see any effect.', 'post-type-x' )
		) );

		do_action( 'ic_catalog_customizer_sections', $wp_customize );
	}

	public function settings( $wp_customize ) {
		if ( ! is_advanced_mode_forced() ) {
			$integration_settings = $this->register_integration_settings();
			foreach ( $integration_settings as $settings ) {
				$wp_customize->add_setting( $settings['name'], $settings['args'] );
				//$wp_customize->add_control( $settings[ 'control' ][ 'name' ], $settings[ 'control' ][ 'args' ] );
				if ( $settings['control']['args']['type'] === 'color' ) {
					$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, $settings['control']['name'], $settings['control']['args'] ) );
				} else {
					$wp_customize->add_control( $settings['control']['name'], $settings['control']['args'] );
				}
			}
		}
		$settings = $this->register_settings();
		foreach ( $settings as $setting ) {
			$wp_customize->add_setting( $setting['name'], $setting['args'] );
			if ( $setting['control']['args']['type'] === 'color' ) {
				$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, $setting['control']['name'], $setting['control']['args'] ) );
			} else {
				$wp_customize->add_control( $setting['control']['name'], $setting['control']['args'] );
			}
		}

		/** MORE * */
		$wp_customize->add_setting( 'implecode_more', array(
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		) );
		require_once( AL_BASE_PATH . '/includes/controls.php' );
		if ( class_exists( 'More_Catalog_impleCode_Control' ) ) {
			$wp_customize->add_control( new More_Catalog_impleCode_Control( $wp_customize, 'implecode_more', array(
				'label'    => __( 'Looking for more options?', 'catalog-me' ),
				'section'  => 'ic_product_catalog_integration',
				'settings' => 'implecode_more',
			) ) );

			$wp_customize->add_control( new More_Catalog_impleCode_Control( $wp_customize, 'implecode_more2', array(
				'label'    => __( 'Looking for more options?', 'catalog-me' ),
				'section'  => 'ic_product_catalog_listing',
				'settings' => 'implecode_more',
			) ) );

			$wp_customize->add_control( new More_Catalog_impleCode_Control( $wp_customize, 'implecode_more3', array(
				'label'    => __( 'Looking for more options?', 'catalog-me' ),
				'section'  => 'ic_product_catalog_icons',
				'settings' => 'implecode_more',
			) ) );
		}
	}

}

$ic_catalog_customizer = new ic_catalog_customizer;


