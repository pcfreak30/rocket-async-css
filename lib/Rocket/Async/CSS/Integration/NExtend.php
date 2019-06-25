<?php


namespace Rocket\Async\CSS\Integration;


use ComposePress\Core\Abstracts\Component;

class NExtend extends Component {

	/**
	 *
	 */
	public function init() {
		add_action( 'plugins_loaded', [ $this, 'check' ], 21 );

	}

	public function check() {
		if ( class_exists( 'N2WordpressAssetInjector' ) ) {
			add_filter( 'rocket_buffer', 'N2WordpressAssetInjector::platformRenderEnd' );
		}
	}
}
