<?php


namespace Rocket\Async\CSS\Integration;


use pcfreak30\WordPress\Plugin\Framework\ComponentAbstract;
use Rocket\Async\CSS;

/**
 * Class WPCriticalCSS
 * @package Rocket\Async\CSS\Integration
 * @property CSS $plugin
 */
class WPCriticalCSS extends ComponentAbstract {

	/**
	 *
	 */
	public function init() {
		if ( function_exists( 'wp_criticalcss' ) ) {
			add_filter( 'wp_criticalcss_print_styles_cache', [ $this, 'process_css' ] );
			add_filter( 'pre_get_rocket_option_async_css', '__return_zero' );
		}
	}

	/**
	 * @param $css
	 *
	 * @return mixed
	 */
	public function process_css( $css ) {
		return $this->plugin->download_remote_files( $css, home_url( $_SERVER['REQUEST_URI'] ) );
	}
}