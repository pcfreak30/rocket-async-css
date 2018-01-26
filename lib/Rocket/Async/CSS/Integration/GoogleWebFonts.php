<?php


namespace Rocket\Async\CSS\Integration;


use ComposePress\Core\Abstracts\Component;
use Rocket\Async\CSS\DOMCollection;
use Rocket\Async\CSS\DOMDocument;
use Rocket\Async\CSS\JSON;

/**
 * Class GoogleWebFonts
 * @package Rocket\Async\CSS\Integration
 */
class GoogleWebFonts extends Component {
	/**
	 * @var DOMCollection
	 */
	private $tags;

	/**
	 * @var DOMDocument
	 */
	private $document;

	/**
	 *
	 */
	public function init() {
		add_filter( 'rocket_async_css_do_rewrites', [ $this, 'process' ] );
	}

	/**
	 * @param DOMDocument $document
	 */
	public function process( $document = null ) {
		if ( ! $document ) {
			/** @noinspection CallableParameterUseCaseInTypeContextInspection */
			$document = $this->plugin->document;
		}
		$this->document = $document;
		$this->tags     = $this->plugin->container->create( '\\Rocket\\Async\\CSS\\DOMCollection', [
			$this->document,
			'script',
		] );
		while ( $this->tags->valid() ) {
			$tag = $this->tags->current();
			$src = $tag->getAttribute( 'src' );
			if ( ! empty( $src ) ) {
				$this->tags->next();
				continue;
			}
			$content = $tag->textContent;
			if ( empty( $src ) ) {
				$content = $this->plugin->util->maybe_decode_script( $content );
			}
			$content = str_replace( [ "\n", "\r" ], '', $content );
			$content = trim( $content, '/' );
			$this->process_tag( $content );
			$this->tags->next();
		}
	}

	private function process_tag( $content ) {
		if ( preg_match( '~(?:WebFontConfig\s*=\s{.*families\s*:\s*(\[.*\]).*};)?\s*\(\s*function\s*\(\s*\)\s*{\s*var\s*wf\s*=\s*document\s*\.\s*createElement\s*\(\s*\'script\'\s*\)\s*;.*s\s*.\s*parentNode\s*.insertBefore\s*\(\s*wf\s*,\s*s\)\s*;\s*}\s*\)\s*\(\s*\);~s', $content, $matches ) ) {

			$fonts = JSON::decode( $matches[1] );

			foreach ( $fonts as $index => $font ) {
				$subset = explode( ':', $font );
				if ( 3 > count( $subset ) ) {
					continue;
				}
				$style = $this->create_tag( 'link' );
				$style->setAttribute( 'rel', 'stylesheet' );
				$style->setAttribute( 'href', add_query_arg( [
					'family' => $fonts,
					'subset' => $subset
				], 'https://fonts.googleapis.com/css' ) );
				$this->inject_tag( $style );
				unset( $fonts[ $index ] );
			}

			$style = $this->create_tag( 'link' );
			$style->setAttribute( 'rel', 'stylesheet' );

			$fonts = implode( '|', $fonts );

			$style->setAttribute( 'href', add_query_arg( 'family', $fonts, 'https://fonts.googleapis.com/css' ) );

			$this->inject_tag( $style );

			$content = trim( str_replace( $matches[0], '', $content ) );
			if ( ! empty( $content ) ) {
				$script = $this->create_tag( 'script', $content );
				$script->setAttribute( 'type', 'text/javascript' );
				$this->inject_tag( $script, true );
			}

			$this->tags->remove();
		}
	}

	protected function create_tag( $type, $content = null ) {
		return $this->document->createElement( $type, $content );
	}

	private function inject_tag( $tag, $next = false ) {
		$this->tags->current()->parentNode->insertBefore( $tag, $this->tags->current() );
		if ( $next ) {
			$this->tags->next();
		}
	}
}