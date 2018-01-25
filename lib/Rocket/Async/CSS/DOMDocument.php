<?php


namespace Rocket\Async\CSS;


use DOMNode;

class DOMDocument extends \DOMDocument {
	use DOMElementTrait;

	public function get_style_tags() {
		return $this->getElementsByTagName( 'script' );
	}

	public function loadHTML( $source, $options = 0 ) {
		return @parent::loadHTML( $this->pre_process_scripts( $source ), $options );
	}

	public function pre_process_scripts( $buffer ) {
		return preg_replace_callback( '~(<script[^>]*>)(.*)(<\/script>)~isU', [
			$this,
			'pre_process_scripts_callback',
		], $buffer );
	}

	public function saveHTML( DOMNode $node = null ) {
		$html = parent::saveHTML( $node );

		return $this->post_process_scripts( $html );
	}

	public function post_process_scripts( $buffer ) {
		return preg_replace_callback( '~(<script[^>]*>)(.*)(<\/script>)~isU', [
			$this,
			'post_process_scripts_callback',
		], $buffer );
	}

	protected function pre_process_scripts_callback( $match ) {
		if ( 0 === strlen( trim( $match[2] ) ) ) {
			return $match[0];
		}

		return "{$match[1]}" . rocket_async_css_instance()->util->encode_script( $match[2] ) . "{$match[3]}";
	}

	protected function post_process_scripts_callback( $match ) {
		if ( 0 === strlen( trim( $match[2] ) ) ) {
			return $match[0];
		}

		return "{$match[1]}" . rocket_async_css_instance()->util->maybe_decode_script( $match[2] ) . "{$match[3]}";
	}

}