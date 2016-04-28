<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              http://www.derrickhammer.com
 * @since             0.1.0
 * @package           Rocket_Async_Css
 *
 * @wordpress-plugin
 * Plugin Name:       WP Rocket ASYNC CSS
 * Plugin URI:        https://github.com/pcfreak30/rocket-async-css
 * Description:       WordPress plugin to combine all CSS load async including inline scripts. Extends WP-Rocket
 * Version:           0.2.0
 * Author:            Derrick Hammer
 * Author URI:        http://www.derrickhammer.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       rocket-async-css
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'ROCKET_ASYNC_CSS_VERSION', '0.2.0' );
define( 'ROCKET_ASYNC_CSS_SLUG', 'rocket-async-css' );

/**
 * Activation hooks
 */
register_activation_hook( __FILE__, array( 'Rocket_Async_Css_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Rocket_Async_Css_Deactivator', 'deactivate' ) );

/**
 * Autoloader function
 *
 * Will search both plugin root and includes folder for class
 *
 * @param $class_name
 */
if ( ! function_exists( 'rocket_async_css_autoloader' ) ):
	function rocket_async_css_autoloader( $class_name ) {
		$file      = 'class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';
		$base_path = plugin_dir_path( __FILE__ );

		$paths = apply_filters( 'rocket_async_css_autoloader_paths', array(
			$base_path . $file,
			$base_path . 'includes/' . $file,
			$base_path . 'public/' . $file,
			$base_path . 'includes/preloaders/' . $file
		) );
		foreach ( $paths as $path ) {

			if ( is_readable( $path ) ) {
				include_once( $path );

				return;
			}
		}
	}

	spl_autoload_register( 'rocket_async_css_autoloader' );
	Rocket_Async_Css::get_instance()->run();
endif;
if ( ! function_exists( 'Rocket_async_css_init' ) ):

	/**
	 * Function to initialize plugin
	 */
	function Rocket_async_css_init() {
		rocket_async_css()->run();
	}

	add_action( 'plugins_loaded', 'rocket_async_css_init', 11 );

endif;
if ( ! function_exists( 'Rocket_async_css' ) ):

	/**
	 * Function wrapper to get instance of plugin
	 *
	 * @return Rocket_async_css
	 */
	function rocket_async_css() {
		return Rocket_async_css::get_instance();
	}

endif;