<?php


namespace Rocket\Async\CSS\Integration;


use pcfreak30\WordPress\Plugin\Framework\ComponentAbstract;
use Rocket\Async\CSS\DOMDocument;

class ThePreloader extends ComponentAbstract {
	private $document;

	public function __construct( DOMDocument $document ) {
		$this->document = $document;
	}

	public function init() {
		if ( is_plugin_active( 'the-preloader/preloader.php' ) || function_exists( 'WPTime_plugin_preloader_script' ) ) {
			add_filter( 'rocket_async_css_process_style', array( $this, 'check_css' ), 10, 2 );
			add_action( 'rocket_buffer', array( $this, 'inject_div' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'add_window_resize_js' ) );
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
		$body = $this->document->getElementsByTagName( 'body' )->item( 0 );
		$div  = $this->document->createElement( 'div' );
		$div->setAttribute( 'id', 'wptime-plugin-preloader' );
		$body->insertBefore( $div, $body->firstChild );
		$buffer = $this->document->saveHTML();

		return $buffer;
	}

	public function add_window_resize_js() {
		wp_add_inline_script( 'jquery-core', <<<JS
(function ($) {
    $(window).load(function () {
        $(window).trigger('resize');
        (function check() {
            if (0 < $('#wptime-plugin-preloader').length) {
                setTimeout(check, 1);
            }
            else {
                $(window).trigger('resize');
            }
        })();
    })
})(jQuery);
JS
		);
	}
}