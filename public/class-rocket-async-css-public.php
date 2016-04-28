<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://github.com/pcfreak30/rocket-async-css
 * @since      0.1.0
 *
 * @package    Rocket_Async_Css
 * @subpackage Rocket_Async_Css/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Rocket_Async_Css
 * @subpackage Rocket_Async_Css/public
 * @author     Derrick Hammer <derrick@derrickhammer.com>
 */
class Rocket_Async_Css_Public {
	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    0.1.0
	 */
	public function enqueue_scripts() {

		/**
		 *
		 * This method enqueues the css loader and a polyfill to load link tags with rel=reload that aren't supported in some browsers
		 * It also runs last via PHP_INT_MAX so that it can inject rocket-async-css-loadcss as a dependency to every script to ensure it runs 1st.
		 *
		 */
		if ( function_exists( 'get_rocket_option' ) && get_rocket_option( 'minify_css' ) ) {
			wp_enqueue_script( 'rocket-async-css-loadcss', plugin_dir_url( __FILE__ ) . 'js/loadcss.js' );
			wp_enqueue_script( 'rocket-async-css-cssrelpreload', plugin_dir_url( __FILE__ ) . 'js/cssrelpreload.js', array( 'rocket-async-css-loadcss' ) );
			foreach ( wp_scripts()->registered as $id => &$script ) {
				if ( ! in_array( $id, array( 'rocket-async-css-loadcss', 'rocket-async-css-cssrelpreload' ) ) ) {
					/** @var _WP_Dependency $script */
					$script->deps[] = 'rocket-async-css-loadcss';
					$script->deps[] = 'rocket-async-css-cssrelpreload';
				}
			}
			add_filter( 'pre_get_rocket_option_minify_css', '__return_zero' );
		}
	}

}
