<?php


namespace Rocket\Async\CSS;


class Request extends ComponentAbstract {
	public function init() {
		parent::init();
		if ( ! is_admin() ) {
			add_filter( 'rocket_async_css_process_style', array( $this, 'exclude_wpadminbar' ), 10, 2 );
			add_filter( 'rocket_buffer', [ $this->app, 'process_buffer' ], PHP_INT_MAX - 1 );
			add_filter( 'pre_get_rocket_option_minify_google_fonts', array( $this, 'return_one' ) );
			remove_filter( 'the_content', 'wp_make_content_images_responsive' );
			add_filter( 'the_content', 'wp_make_content_images_responsive', 12 );
			if ( is_plugin_active( 'rocket-footer-js/rocket-footer-js.php' ) ) {
				remove_filter( 'rocket_buffer', 'rocket_minify_process', 13 );
			} else {
				add_filter( 'pre_get_rocket_option_minify_js', '__return_zero' );
			}
		}

	}

	public function init_action() {

	}

	/**
	 * Callback to prevent wp-admin bar from getting async loaded since the inline css is so small
	 *
	 * @param $skip
	 * @param $css
	 *
	 * @return bool
	 */
	public function exclude_wpadminbar( $skip, $css ) {

		if ( 'b69140c3eb73819f6f3ea6139522efc9' === md5( $css ) ) {
			return false;
		}

		return $skip;
	}

	public function return_one() {
		return 1;
	}

	public function enqueue_scripts() {
		wp_enqueue_script( 'picturefill', plugins_url( 'assets/js/picturefill.min.js', $this->app->get_plugin_file() ), '3.0.3' );
	}


}