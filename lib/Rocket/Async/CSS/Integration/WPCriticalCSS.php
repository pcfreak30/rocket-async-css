<?php


namespace Rocket\Async\CSS\Integration;


use pcfreak30\WordPress\Plugin\Framework\ComponentAbstract;

class WPCriticalCSS extends ComponentAbstract {

	public function init() {
		if ( function_exists( 'WPCCSS' ) ) {
			add_filter( 'wp_criticalcss_print_styles_cache', [ $this, 'process_css' ] );
		}
	}

	public function process_css( $css ) {
		return rocket_async_css_instance()->download_remote_files( $css, home_url( $_SERVER['REQUEST_URI'] ) );
	}
}