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


use Dice\Dice;


/**
 * @SuppressWarnings(PHPMD.StaticAccess)
 * @return \Rocket\Async\CSS
 */
function rocket_async_css_instance() {
	return rocket_async_css_container()->create( '\\Rocket\\Async\\CSS' );
}

function rocket_async_css_container( $env = 'prod' ) {
	static $container;
	if ( empty( $container ) ) {
		$container = new Dice();
		include __DIR__ . "/config_{$env}.php";
	}

	return $container;
}

/**
 *
 */
function rocket_async_css_init() {
	rocket_async_css_instance()->init();
}

function rocket_async_css_activate() {
	rocket_async_css_instance()->activate();
}

function rocket_async_css_deactivate() {
	rocket_async_css_instance()->deactivate();
}

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

if ( version_compare( PHP_VERSION, '5.4.0' ) < 0 ) {
	add_action( 'admin_notices', 'rocket_async_css_php_upgrade_notice' );
} else {
	if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
		include_once __DIR__ . '/vendor/autoload.php';
		add_action( 'plugins_loaded', 'rocket_async_css_init', 11 );
		register_activation_hook( __FILE__, 'rocket_async_css_activate' );
		register_deactivation_hook( __FILE__, 'rocket_async_css_deactivate' );
	} else {
		include_once __DIR__ . '/wordpress-web-composer/class-wordpress-web-composer.php';
		$web_composer = new \WordPress_Web_Composer( 'rocket_async_css' );
		$web_composer->set_install_target( __DIR__ );
		if ( $web_composer->run() ) {
			include_once __DIR__ . '/vendor/autoload.php';
			register_deactivation_hook( __FILE__, 'rocket_async_css_activate' );
			register_deactivation_hook( __FILE__, 'rocket_async_css_deactivate' );
			define( 'ROCKET_ASYNC_CSS_COMPOSER_RAN', true );
		}
	}
}
