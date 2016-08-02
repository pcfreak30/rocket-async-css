<?php

/**
 * Fired during plugin deactivation
 *
 * @link       http://example.com
 * @since      0.1.0
 *
 * @package    Rocket_async_css
 * @subpackage Rocket_async_css/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      0.1.0
 * @package    Rocket_async_css
 * @subpackage Rocket_async_css/includes
 * @author     Derrick Hammer <derrick@derrickhammer.com>
 */
class Rocket_Async_Css_Deactivator {

	/**
	 * Purge Cache
	 *
	 * @since    0.1.0
	 */
	public static function deactivate() {
		rocket_async_css()->get_loader()->add_action( 'deactivated_plugin', rocket_async_css(), 'purge_cache' );
		rocket_async_css()->get_loader()->run();
	}

}
