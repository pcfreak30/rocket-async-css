<?php


namespace Rocket\Async\CSS;


class Request extends ComponentAbstract {
	public function init() {
		parent::init();
		if ( ! is_admin() ) {
			add_filter( 'rocket_async_css_process_style', array( $this, 'exclude_wpadminbar' ), 10, 2 );
			add_filter( 'rocket_buffer', [ $this->app, 'process_buffer' ], PHP_INT_MAX - 1 );
			add_filter( 'pre_get_rocket_option_minify_css', '__return_zero' );
			add_filter( 'pre_get_rocket_option_minify_google_fonts', array( $this, 'return_one' ) );
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


}