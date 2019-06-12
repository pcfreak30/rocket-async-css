<?php


namespace Rocket\Async\CSS\Integration;

use ComposePress\Core\Abstracts\Component;


class GoogleFonts extends Component {
	public function init() {
		add_filter( 'style_loader_src', [ $this, 'process_src' ] );
		add_filter( 'rocket_async_css_font_display', [ $this, 'process_css' ], 10, 4 );
	}

	public function process_src( $url ) {
		$url_parts = parse_url( $url );
		if ( isset( $url_parts['host'] ) && 'fonts.googleapis.com' === $url_parts['host'] ) {
			$url = add_query_arg( 'display', 'swap', $url );
		}

		return $url;
	}

	public function process_css( $mode, $rules, $url, $srces ) {
		foreach ( $srces as $src ) {
			$url_parts = parse_url( $src );
			if ( isset( $url_parts['host'] ) && 'fonts.gstatic.com' === $url_parts['host'] ) {
				$mode = 'swap';
				break;
			}
		}

		return $mode;
	}
}
