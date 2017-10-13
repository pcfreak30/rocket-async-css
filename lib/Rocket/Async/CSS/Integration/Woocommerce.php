<?php


namespace Rocket\Async\CSS\Integration;

use pcfreak30\WordPress\Plugin\Framework\ComponentAbstract;

class Woocommerce extends ComponentAbstract {

	/**
	 *
	 */
	public function init() {
		if ( class_exists( '\WooCommerce' ) && method_exists( 'WC_Cache_Helper', 'set_nocache_constants' ) ) {
			remove_filter( 'nocache_headers', [ 'WC_Cache_Helper', 'set_nocache_constants' ] );
		}
	}
}