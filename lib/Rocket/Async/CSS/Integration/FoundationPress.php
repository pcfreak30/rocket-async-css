<?php


namespace Rocket\Async\CSS\Integration;

use ComposePress\Core\Abstracts\Component;

class FoundationPress extends Component {

	public function init() {
		add_action( 'after_setup_theme', [ $this, 'process' ] );
	}

	public function process() {
		if ( isset( $this->Foundationpress_img_rebuilder ) ) {
			remove_filter( 'img_caption_shortcode', array(
				$this->Foundationpress_img_rebuilder,
				'img_caption_shortcode'
			), 1 );
			remove_filter( 'get_avatar', array( $this->Foundationpress_img_rebuilder, 'recreate_img_tag' ) );
			remove_filter( 'the_content', array( $this->Foundationpress_img_rebuilder, 'the_content' ) );
		}
	}
}
