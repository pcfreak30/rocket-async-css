<?php


namespace Rocket\Async\CSS\Integration;

use ComposePress\Core\Abstracts\Component;

class FontAwesome extends Component {
	public function init() {
		add_filter( 'rocket_async_css_font_display', [ $this, 'process' ], 10, 2 );
	}

	public function process( $mode, $rules ) {
		$family = $rules['font-family'];
		$family = strtolower( $family );
		if ( false !== strpos( $family, 'fontawesome' ) || false !== strpos( $family, 'font awesome' ) ) {
			$mode = 'block';
		}

		return $mode;
	}
}
