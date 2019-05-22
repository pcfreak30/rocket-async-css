<?php


namespace Rocket\Async\CSS\Integration;


use ComposePress\Core\Abstracts\Component;
use Rocket\Async\CSS;

/**
 * Class WPCriticalCSS
 * @package Rocket\Async\CSS\Integration
 * @property CSS $plugin
 */
class WPCriticalCSS extends Component {

	/**
	 *
	 */
	public function init() {
		if ( function_exists( 'wp_criticalcss' ) ) {
			add_filter( 'wp_criticalcss_print_styles_cache', [ $this, 'process_css' ] );
		}
	}

	/**
	 * @param $css
	 *
	 * @return mixed
	 */
	public function process_css( $css ) {
		$webp = $this->plugin->integration_manager->get_module( 'WebPExpress' );
		/** @var WebPExpress $webp */
		if ( $webp && $webp->webp_available ) {
			$css = $webp->maybe_process( $css );
		}

		$home = home_url( $_SERVER['REQUEST_URI'] );

		$css = $this->plugin->lazy_load_fonts( $css, $home );
		$css = $this->plugin->download_remote_files( $css, $home );

		return $css;
	}
}
