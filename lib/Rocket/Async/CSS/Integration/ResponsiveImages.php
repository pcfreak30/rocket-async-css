<?php


namespace Rocket\Async\CSS\Integration;


class ResponsiveImages implements IntegrationInterface {

	public function init() {
		add_filter( 'the_content', [ $this, 'process' ] );
	}

	public function process( $content ) {
		if ( ! preg_match_all( '/<img [^>]+>/', $content, $matches ) ) {
			return $content;
		}
		foreach ( $matches[0] as $image ) {
			if ( ! preg_match( '/wp-image-([0-9]+)/i', $image ) && preg_match( '/src=[\'"].+[\'"]/U', $image, $src ) ) {
				$src  = end( $src );
				$path = parse_url( $src, PHP_URL_PATH );

				if ( preg_match( '/\d+x\d+/', $path, $size ) ) {
					$size = end( $size );
					$src  = str_replace( "-{$size}", '', $src );
				}
				$attachment_id = attachment_url_to_postid( rocket_async_css_instance()->strip_cdn( $image ) );
				if ( empty( $attachment_id ) ) {
					continue;
				}
				$original_class = '';
				if ( preg_match( '/src=[\'"](.+)[\'"]/U', $image, $class ) ) {
					$original_class = end( $class );
				}
				$class     = trim( "{$original_class} wp-image-{$attachment_id}" );
				$new_image = str_replace( $original_class, $class, $image );
				$content   = str_replace( $image, $new_image, $content );
			}
		}

		return $content;
	}
}