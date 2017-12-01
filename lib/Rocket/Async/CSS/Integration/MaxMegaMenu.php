<?php


namespace Rocket\Async\CSS\Integration;


use pcfreak30\WordPress\Plugin\Framework\ComponentAbstract;

class MaxMegaMenu extends ComponentAbstract {

	/**
	 *
	 */
	public function init() {
		add_filter( 'megamenu_custom_icon_url', 'get_rocket_cdn_url' );
	}
}