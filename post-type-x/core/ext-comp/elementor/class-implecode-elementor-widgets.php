<?php
/**
 * Elementor integration bootstrap class.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers Elementor widgets for the catalog.
 */
class ImpleCode_Elementor_Widgets {

	/**
	 * Hooks Elementor widget registration.
	 */
	public function __construct() {
		add_action( 'elementor/widgets/widgets_registered', array( $this, 'register_widgets' ) );
		add_action( 'elementor/elements/categories_registered', array( $this, 'add_categories' ) );
	}

	/**
	 * Adds the impleCode Elementor category.
	 *
	 * @param object $elements_manager Elementor elements manager.
	 *
	 * @return void
	 */
	public function add_categories( $elements_manager ) {
		$elements_manager->add_category(
			'implecode',
			array(
				'title' => 'impleCode',
				'icon'  => 'fa fa-plug',
			)
		);
	}

	/**
	 * Registers catalog widgets with Elementor.
	 *
	 * @return void
	 */
	public function register_widgets() {
		require_once AL_BASE_PATH . '/ext-comp/elementor/class-elementor-ic-show-catalog-widget.php';
		if ( method_exists( \Elementor\Plugin::instance()->widgets_manager, 'register' ) ) {
			\Elementor\Plugin::instance()->widgets_manager->register( new \Elementor_IC_Show_Catalog_Widget() );
		} else {
			\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new \Elementor_IC_Show_Catalog_Widget() );
		}
	}
}
