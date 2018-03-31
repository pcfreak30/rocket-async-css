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
 * Version:           0.7.0.6
 * Author:            Derrick Hammer
 * Author URI:        http://www.derrickhammer.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       rocket-async-css
 */


use Dice\Dice;


/**
 * @SuppressWarnings(PHPMD.StaticAccess)
 * @return \Rocket\Async\CSS
 */
function rocket_async_css_instance() {
	return rocket_async_css_container()->create( '\\Rocket\\Async\\CSS' );
}

/**
 * @param string $env
 *
 * @return Dice
 */
function rocket_async_css_container( $env = 'prod' ) {
	static $container;
	if ( empty( $container ) ) {
		$container = new Dice();
		include __DIR__ . "/config_{$env}.php";
	}

	return $container;
}

/**
 * Init function shortcut
 */
function rocket_async_css_init() {
	rocket_async_css_instance()->init();
}

/**
 * Activate function shortcut
 */
function rocket_async_css_activate() {
	rocket_async_css_instance()->activate();
}

/**
 * Deactivate function shortcut
 */
function rocket_async_css_deactivate() {
	rocket_async_css_instance()->deactivate();
}

if ( ! function_exists( '__return_one' ) ) {
	function __return_one() {
		return 1;
	}
}


/**
 * Error for older php
 */
function rocket_async_css_php_upgrade_notice() {
	$info = get_plugin_data( __FILE__ );
	_e(
		sprintf(
			'
	<div class="error notice">
		<p>Opps! %s requires a minimum PHP version of 5.4.0. Your current version is: %s. Please contact your host to upgrade.</p>
	</div>', $info['Name'], PHP_VERSION
		)
	);
}

/**
 * Error if vendors autoload is missing
 */
function rocket_async_css_php_vendor_missing() {
	$info = get_plugin_data( __FILE__ );
	_e(
		sprintf(
			'
	<div class="error notice">
		<p>Opps! %s is corrupted it seems, please re-install the plugin.</p>
	</div>', $info['Name']
		)
	);
}

if ( version_compare( PHP_VERSION, '5.4.0' ) < 0 ) {
	add_action( 'admin_notices', 'rocket_async_css_php_upgrade_notice' );
} else {
	if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
		include_once __DIR__ . '/vendor/autoload.php';
		add_action( 'plugins_loaded', 'rocket_async_css_init', 11 );
		register_activation_hook( __FILE__, 'rocket_async_css_activate' );
		register_deactivation_hook( __FILE__, 'rocket_async_css_deactivate' );
	} else {
		add_action( 'admin_notices', 'rocket_async_css_php_vendor_missing' );
	}
}
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