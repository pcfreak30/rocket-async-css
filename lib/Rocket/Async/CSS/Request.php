<?php


namespace Rocket\Async\CSS;

use ComposePress\Core\Abstracts\Component;

class Request extends Component {
	public function init() {
		if ( ! is_admin() ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			add_filter( 'rocket_async_css_process_style', array( $this, 'exclude_wpadminbar' ), 10, 2 );
			add_filter( 'rocket_buffer', [ $this->plugin, 'process_buffer' ], PHP_INT_MAX - 1 );
			add_filter( 'pre_get_rocket_option_minify_google_fonts', [ $this, 'return_one' ] );
			remove_filter( 'the_content', 'wp_make_content_images_responsive' );
			add_filter( 'the_content', 'wp_make_content_images_responsive', 12 );
			add_filter( 'widget_text', 'wp_make_content_images_responsive', PHP_INT_MAX );
			add_action( 'init', [ $this, 'start_buffer' ], 10, 0 );
			remove_action( 'wp_print_styles', 'rocket_extract_excluded_css_files' );
			if ( is_plugin_active( 'rocket-footer-js/rocket-footer-js.php' ) ) {
				remove_filter( 'rocket_buffer', 'rocket_minify_process', 13 );
			} else {
				add_filter( 'pre_get_rocket_option_minify_js', '__return_zero' );
			}
			add_action( 'wp_footer', [ $this, 'scripts' ], PHP_INT_MAX );
		}

	}

	public function start_buffer() {
		ob_start( [ $this, 'process_buffer' ] );
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
		wp_enqueue_script( 'picturefill', plugins_url( 'assets/js/picturefill.min.js', $this->plugin->get_plugin_file() ), '3.0.3' );
	}

	public function process_buffer( $buffer ) {
		return apply_filters( 'rocket_async_css_request_buffer', $buffer );
	}

	public function scripts() {
		?>
        <script type="text/javascript">
            window.js_loaded = true;
            if (window.CustomEvent) {
                window.dispatchEvent(new CustomEvent("JSLoaded"));
            }
        </script>
		<?php
	}
}