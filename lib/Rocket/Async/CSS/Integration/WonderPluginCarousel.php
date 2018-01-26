<?php


namespace Rocket\Async\CSS\Integration;


use ComposePress\Core\Abstracts\Component;
use Rocket\Async\CSS\DOMDocument;

class WonderPluginCarousel extends Component {
	public function init() {
		if ( class_exists( '\WonderPlugin_Carousel_Plugin' ) ) {
			add_action( 'rocket_async_css_do_rewrites', [ $this, 'process' ] );
		}
	}

	public function process() {
		/** @var DOMDocument $document */
		$document = $this->plugin->document;
		$xpath    = new \DOMXPath( $document );
		$social   = false;
		foreach ( $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " wonderplugincarousel ")]' ) as $tag ) {
			$data_social = $tag->getAttribute( 'data-initsocialmedia' );

			if ( $data_social !== 'false' ) {
				$social = true;
				$tag->setAttribute( 'data-initsocialmedia', 'false' );
			}
			$tag->setAttribute( 'data-jsfolder', get_rocket_cdn_url( $tag->getAttribute( 'data-jsfolder' ) ) );
		}

		if ( $social ) {
			$style = $document->createElement( 'link' );
			$style->setAttribute( 'rel', 'stylesheet' );
			$style->setAttribute( 'href', get_rocket_cdn_url( WONDERPLUGIN_CAROUSEL_URL . 'icons/css/fontello.css' ) );
			$document->documentElement->appendChild( $style );
		}
	}
}