<?php


namespace Rocket\Async\CSS\Integration;


class WPCriticalCSS implements IntegrationInterface {

	public function init() {
		add_filter( 'wp_criticalcss_print_styles_cache', [ $this, 'process_css' ] );
	}

	public function process_css( $css ) {
		return rocket_async_css_instance()->download_remote_files( $css, $_SERVER['REQUEST_URI'] );
	}
}