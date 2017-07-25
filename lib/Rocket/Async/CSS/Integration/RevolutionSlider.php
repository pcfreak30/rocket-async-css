<?php


namespace Rocket\Async\CSS\Integration;


class RevolutionSlider implements IntegrationInterface {

	public function init() {
		if ( shortcode_exists( 'rev_slider' ) ) {
			remove_shortcode( 'rev_slider' );
			add_shortcode( 'rev_slider', array( $this, 'rev_slider_compatibility' ) );
		}
	}

	/**
	 * A hack to ensure rev slider runs on winsow load
	 *
	 * @return mixed
	 */
	public function rev_slider_compatibility() {
		$output = str_replace( 'tpj(document).ready(function() {', 'tpj(window).load(function() {', call_user_func_array( 'rev_slider_shortcode', func_get_args() ) );

		return $output;
	}
}