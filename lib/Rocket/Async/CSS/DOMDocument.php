<?php


namespace Rocket\Async\CSS;


class DOMDocument extends \DOMDocument {
	use DOMElementTrait;


	public function get_style_tags() {
		return $this->getElementsByTagName( 'script' );
	}
}