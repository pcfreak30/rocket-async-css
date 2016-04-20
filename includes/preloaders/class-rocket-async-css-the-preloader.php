<?php
/**
 * Class supporting "Preloader" Plugin
 *
 * @link       https://github.com/pcfreak30/rocket-async-css
 * @since      0.1.0
 *
 * @package    Rocket_Async_Css
 * @subpackage Rocket_Async_Css/includes/preloader
 */

/**
 * The core plugin class.
 * Class supporting "Preloader" Plugin
 *
 * @since      0.1.0
 * @package    Rocket_Async_Css
 * @subpackage Rocket_Async_Css/includes/preloader
 * @author     Derrick Hammer <derrick@derrickhammer.com>
 */
class Rocket_Async_Css_The_Preloader {

	/**
	 *
	 */
	public static function init( Rocket_Async_Css $core ) {
		if ( is_plugin_active( 'the-preloader/preloader.php' ) || function_exists( 'WPTime_plugin_preloader_script' ) ) {
			$core->get_loader()->add_filter( 'rocket_async_css_process_style', __CLASS__, 'check_css', 10, 2 );
			$core->get_loader()->add_action( 'rocket_buffer', __CLASS__, 'inject_div' );
		}

	}

	/**
	 * @param $skip
	 * @param $css
	 *
	 * @return bool
	 */
	public static function check_css( $skip, $css ) {
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
	public static function inject_div( $buffer ) {
		$document = new DOMDocument();
		if ( ! @$document->loadHTML( $buffer ) ) {
			return $buffer;
		}
		$body = $document->getElementsByTagName( 'body' )->item( 0 );
		$div  = $document->createElement( 'div' );
		$div->setAttribute( 'id', 'wptime-plugin-preloader' );
		$body->insertBefore( $div, $body->firstChild );
		$buffer = $document->saveHTML();

		return $buffer;
	}
}