<?php

/**
 * Fired during plugin activation
 *
 * @link       https://github.com/pcfreak30/rocket-async-css
 * @since      0.1.0
 *
 * @package    Rocket_Async_Css
 * @subpackage Rocket_Async_Css/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      0.1.0
 * @package    Rocket_Async_Css
 * @subpackage Rocket_Async_Css/includes
 * @author     Derrick Hammer <derrick@derrickhammer.com>
 */
class Rocket_Async_Css_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    0.1.0
	 */
	public static function activate() {
		Rocket_Async_Css::get_instance()->get_loader()->add_action( 'activated_plugin', Rocket_Async_Css::get_instance(), 'purge_cache' );
		Rocket_Async_Css::get_instance()->get_loader()->run();
	}

}
