<?php
/**
 * Catalog cron scheduler class.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles scheduled catalog events.
 */
class IC_EPC_Cron {

	/**
	 * Registers the cron hook bootstrap.
	 */
	public function __construct() {
		add_action( 'wp', array( $this, 'schedule_events' ) );
	}

	/**
	 * Schedules all catalog events.
	 *
	 * @return void
	 */
	public function schedule_events() {
		$this->weekly_events();
		$this->daily_events();
	}

	/**
	 * Schedules weekly events.
	 *
	 * @return void
	 */
	private function weekly_events() {
		if ( ! wp_next_scheduled( 'ic_epc_weekly_scheduled_events' ) ) {
			wp_schedule_event( time(), 'weekly', 'ic_epc_weekly_scheduled_events' );
		}
	}

	/**
	 * Schedules daily events.
	 *
	 * @return void
	 */
	private function daily_events() {
		if ( ! wp_next_scheduled( 'ic_epc_daily_scheduled_events' ) ) {
			wp_schedule_event( time(), 'daily', 'ic_epc_daily_scheduled_events' );
		}
	}
}
