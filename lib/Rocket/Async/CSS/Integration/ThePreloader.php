<?php


namespace Rocket\Async\CSS\Integration;


use ComposePress\Core\Abstracts\Component;
use Rocket\Async\CSS\DOMDocument;

class ThePreloader extends Component {
	private $document;

	public function __construct( DOMDocument $document ) {
		$this->document = $document;
	}

	public function init() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		if ( is_plugin_active( 'the-preloader/preloader.php' ) || function_exists( 'WPTime_plugin_preloader_script' ) ) {
			add_filter( 'rocket_async_css_process_style', array( $this, 'check_css' ), 10, 2 );
			add_action( 'rocket_buffer', array( $this, 'inject_div' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'add_window_resize_js' ) );
			add_filter( 'rocket_async_css_preloader_enabled', [ $this, 'check_status' ] );
			add_filter( 'after_setup_theme', [ $this, 'theme_hooks' ] );
		}
	}

	/**
	 * @param $skip
	 * @param $css
	 *
	 * @return bool
	 */
	public function check_css( $skip, $css ) {
		if ( false !== strpos( $css, '#wptime-plugin-preloader' ) ) {
			return false;
		}

		return $skip;
	}

	/**
	 * @param $buffer
	 *
	 * @return string
	 */
	public function inject_div( $buffer ) {
		if ( ! @$this->document->loadHTML( mb_convert_encoding( $buffer, 'HTML-ENTITIES', 'UTF-8' ) ) ) {
			return $buffer;
		}
		if ( null === $this->document->getElementById( 'wptime-plugin-preloader' ) ) {
			$body = $this->document->getElementsByTagName( 'body' )->item( 0 );
			$div  = $this->document->createElement( 'div' );
			$div->setAttribute( 'id', 'wptime-plugin-preloader' );
			$body->insertBefore( $div, $body->firstChild );
			$buffer = $this->document->saveHTML();

		}

		return $buffer;
	}

	public function add_window_resize_js() {
		wp_add_inline_script( 'jquery-core', <<<JS
(function ($) {
    $(window).on('load', function () {
        try {
            $(window).trigger('resize');
        } catch (e) {
        }
        (function check() {
            if (0 < $('#wptime-plugin-preloader').length || !window.preloader_event_registered) {
                setTimeout(check, 1);
            } else {
                try {
                    $(window).trigger('resize');
                } catch (e) {
                }
                window.preloader_loaded = true;
                if (window.CustomEvent) {
                    window.dispatchEvent(new CustomEvent("PreloaderDestroyed"));
                }
            }
        })();
    })
})(jQuery);
JS
		);
	}

	public function check_status() {
		return has_action( 'rocket_buffer', array( $this, 'inject_div' ) );
	}

	public function theme_hooks() {
		// Remove conflict with preloader on "The 7" theme
		remove_action( 'presscore_body_top', 'presscore_render_fullscreen_overlay' );
	}
}
