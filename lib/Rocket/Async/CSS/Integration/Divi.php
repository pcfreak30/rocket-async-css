<?php


namespace Rocket\Async\CSS\Integration;


use ComposePress\Core\Abstracts\Component;

class Divi extends Component {
	/**
	 *
	 */
	public function init() {
		if ( function_exists( 'et_setup_theme' ) ) {
			add_filter( 'et_get_option_divi_minify_combine_styles', [ $this, 'return_false' ] );
			add_filter( 'et_load_unminified_styles', '__return_false' );
			add_filter( 'rocket_async_css_font_display', [ $this, 'process_font' ], 10, 2 );
		}
	}

	public function return_false() {
		return 'false';
	}

	public function process_font( $mode, $rules ) {
		$family = $rules['font-family'];
		$family = strtolower( $family );
		if ( 'etmodules' === $family ) {
			$mode = 'block';
		}
		return $mode;
	}

}
