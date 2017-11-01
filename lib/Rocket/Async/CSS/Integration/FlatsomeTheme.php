<?php


namespace Rocket\Async\CSS\Integration;


use pcfreak30\WordPress\Plugin\Framework\ComponentAbstract;

class FlatsomeTheme extends ComponentAbstract {

	/**
	 *
	 */
	public function init() {
		add_filter( 'after_setup_theme', [ $this, 'theme_check' ] );
	}

	public function theme_check() {
		if ( class_exists( '\Flatsome_Option' ) ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ], 101 );
		}
	}

	public function scripts() {
		if ( wp_script_is( 'flatsome-js' ) ) {
			wp_add_inline_script( 'flatsome-js', '(function($){$(window).load(function(){$(\'[data-flickity-options],.flickity-enabled\').flickity(\'resize\')});(function check(){if(window.css_loaded){$(\'.resize-select\').change();return;}setTimeout(check, 10);})();})(jQuery);' );
		}
	}
}