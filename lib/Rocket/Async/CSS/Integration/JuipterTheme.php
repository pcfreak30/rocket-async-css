<?php


namespace Rocket\Async\CSS\Integration;


use pcfreak30\WordPress\Plugin\Framework\ComponentAbstract;

class JuipterTheme extends ComponentAbstract {

	public function init() {
		if ( 'juipter' === wp_get_theme()->get_template() ) {
			add_filter( 'mk_google_fonts', [ $this, 'enqueue_google_fonts' ], PHP_INT_MAX );
		}
	}

	public function enqueue_google_fonts( $families ) {
		foreach ( $families as $family ) {
			$family_name = explode( ':', $family );
			$family_name = array_shift( $family_name );
			wp_enqueue_style( "google-fonts-{$family_name}", add_query_arg( 'family', $family, 'https://fonts.googleapis.com/css' ) );
		}
		wp_dequeue_style( 'mk-webfontloader-init' );
		wp_dequeue_style( 'mk-webfontloader' );

		return [];
	}
}