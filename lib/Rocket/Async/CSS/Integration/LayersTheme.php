<?php


namespace Rocket\Async\CSS\Integration;

use ComposePress\Core\Abstracts\Component;

class LayersTheme extends Component {
	public function init() {
		add_filter( 'after_setup_theme', [ $this, 'theme_check' ] );
	}

	public function theme_check() {
		if ( function_exists( 'layers_setup' ) ) {
			add_filter( 'rocket_async_css_font_display', [ $this, 'process' ], 10, 2 );
		}
	}

	public function process( $mode, $rules ) {
		$family = $rules['font-family'];
		$family = strtolower( $family );
		if ( 'layers-icons' === $family ) {
			$mode = 'block';
		}
		return $mode;
	}
}
