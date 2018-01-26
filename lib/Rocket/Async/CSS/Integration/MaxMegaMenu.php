<?php


namespace Rocket\Async\CSS\Integration;


use ComposePress\Core\Abstracts\Component;

class MaxMegaMenu extends Component {

	/**
	 *
	 */
	public function init() {
		add_filter( 'megamenu_custom_icon_url', 'get_rocket_cdn_url' );
	}
}