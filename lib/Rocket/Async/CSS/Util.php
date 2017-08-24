<?php

namespace Rocket\Async\CSS;

use pcfreak30\WordPress\Plugin\Framework\ComponentAbstract;


/**
 * Class Util
 * @package Rocket\Footer\JS
 */
class Util extends ComponentAbstract {

	/**
	 *
	 */
	public function init() {

	}

	public function maybe_decode_script( $data ) {
		if ( $this->is_base64_encoded( $data ) ) {
			return json_decode( base64_decode( $data ) );
		}

		return $data;
	}

	protected function is_base64_encoded( $data ) {
		if ( base64_decode( $data, true ) && json_decode( base64_decode( $data ) ) ) {
			return true;
		}

		return false;
	}

	public function encode_script( $data ) {
		return base64_encode( json_encode( $data ) );
	}
}