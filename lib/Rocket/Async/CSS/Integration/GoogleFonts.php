<?php


namespace Rocket\Async\CSS\Integration;

use ComposePress\Core\Abstracts\Component;


class GoogleFonts extends Component {
	public function init() {
		add_filter( 'style_loader_src', [ $this, 'process' ] );
	}

	public function process( $url ) {
		$url_parts = parse_url( $url );
		if ( 'fonts.googleapis.com' === $url_parts['host'] ) {
			$url = add_query_arg( 'display', 'swap', $url );
		}
		return $url;
	}
}
