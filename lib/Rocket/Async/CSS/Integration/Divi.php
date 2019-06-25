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
		}
	}

	public function return_false() {
		return 'false';
	}
}
