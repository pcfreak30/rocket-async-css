<?php

namespace Rocket\Async\CSS;

use ComposePress\Core\Abstracts\Component;


/**
 * Class Util
 * @package Rocket\Footer\JS
 */
class Util extends Component {

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

	public function is_lazyload_enabled() {
		global $a3_lazy_load_global_settings;
		$lazy_load = false;
		if ( class_exists( 'A3_Lazy_Load' ) ) {
			$lazy_load = (bool) $this->a3_lazy_load_global_settings['a3l_apply_lazyloadxt'] && apply_filters( 'a3_lazy_load_run_filter', true );
		}
		if ( class_exists( 'LazyLoadXT' ) ) {
			$lazy_load = true;
		}

		return $lazy_load;
	}

}