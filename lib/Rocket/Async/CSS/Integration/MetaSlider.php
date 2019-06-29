<?php


namespace Rocket\Async\CSS\Integration;


use ComposePress\Core\Abstracts\Component;

class MetaSlider extends Component {

	public function init() {
		if ( class_exists( 'MetaSliderPlugin' ) ) {
			add_filter( 'metaslider_image_slide_attributes', [ $this, 'add_image_class' ] );
			add_filter( 'metaslider_slideshow_output', 'wp_make_content_images_responsive' );
		}
	}

	public function add_image_class( $slide ) {
		if ( get_post_type( $slide['id'] ) === 'attachment' ) {
			$slide_id = $slide['id'];
		} else {
			$slide_id = get_post_thumbnail_id( $slide['id'] );
		}
		$slide['class'] .= " wp-image-{$slide_id}";

		return $slide;
	}
}
