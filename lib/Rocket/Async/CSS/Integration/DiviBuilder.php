<?php


namespace Rocket\Async\CSS\Integration;


use ComposePress\Core\Abstracts\Component;

class DiviBuilder extends Component {

	/**
	 *
	 */
	public function init() {
		add_action( 'after_setup_theme', [ $this, 'theme_check' ] );

	}

	public function theme_check() {
		if ( function_exists( 'et_setup_builder' ) ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ] );
		}
	}

	public function scripts() {
		wp_add_inline_script( 'jquery-core', '(function($){(function check(){if(window.css_loaded && window.preloader_loaded && typeof salvattore !== "undefined"){$(".et_pb_blog_grid").each(function(){salvattore.rescanMediaQueries(this)});return}setTimeout(check,10)})()})(jQuery);' );
	}
}