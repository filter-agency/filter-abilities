<?php
/**
 * The API implemented by all subscribers.
 *
 * @package Filter\Vendor\StellarWP\Telemetry\Contracts
 */

namespace Filter\Vendor\StellarWP\Telemetry\Contracts;

/**
 * Interface Subscriber_Interface
 *
 * @package Filter\Vendor\StellarWP\Telemetry\Contracts
 */
interface Subscriber_Interface {

	/**
	 * Register action/filter listeners to hook into WordPress
	 *
	 * @return void
	 */
	public function register();
}
