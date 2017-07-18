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
 * Version:           0.5.5
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

/**
 * Activation hooks
 */
register_activation_hook( __FILE__, array( 'Rocket_Async_Css', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Rocket_Async_Css', 'deactivate' ) );

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
endif;
if ( ! function_exists( 'get_rocket_async_css' ) ):

	/**
	 * Function wrapper to get instance of plugin
	 *
	 * @return Rocket_async_css
	 */
	function get_rocket_async_css() {
		return Rocket_Async_Css::get_instance();
	}

	add_action( 'plugins_loaded', 'get_rocket_async_css', 11 );

endif;

if ( ! function_exists( 'avada_revslider' ) ):
	function avada_revslider( $name ) {
		if ( function_exists( 'putRevSlider' ) ) {
			ob_start();
			putRevSlider( $name );
			$slider = ob_get_clean();
			echo str_replace( 'tpj(document).ready(function() {', 'tpj(window).load(function() {', $slider );

		}
	}
endif;