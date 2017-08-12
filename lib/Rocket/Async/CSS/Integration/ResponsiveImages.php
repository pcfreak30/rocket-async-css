<?php


namespace Rocket\Async\CSS\Integration;


class ResponsiveImages implements IntegrationInterface {

	public function init() {
		add_filter( 'the_content', [ $this, 'process' ], 11 );
		add_filter( 'widget_text', [ $this, 'process' ], PHP_INT_MAX );
		add_filter( 'rocket_async_css_request_buffer', [ $this, 'process' ] );
		add_filter( 'rocket_async_css_request_buffer', 'wp_make_content_images_responsive', PHP_INT_MAX );
	}

	public function process( $content ) {
		if ( ! preg_match_all( '/<img [^>]+>/', $content, $matches ) ) {
			return $content;
		}
		foreach ( $matches[0] as $image ) {
			if ( ! preg_match( '/wp-image-([0-9]+)/i', $image ) && ( preg_match( '/data-src=[\'"](.+)[\'"]/U', $image, $src ) || preg_match( '/src=[\'"](.+)[\'"]/U', $image, $src ) ) ) {
				$src_attr = array_shift( $src );
				$src      = end( $src );
				$path     = parse_url( $src, PHP_URL_PATH );

				if ( preg_match( '/\d+x\d+/', $path, $size ) ) {
					$size = end( $size );
					$src  = str_replace( "-{$size}", '', $src );
				}
				$attachment_id = attachment_url_to_postid( rocket_async_css_instance()->strip_cdn( $src ) );
				if ( empty( $attachment_id ) ) {
					continue;
				}
				$original_class = '';
				if ( preg_match( '/class=[\'"](.+)[\'"]/U', $image, $class ) ) {
					$original_class = end( $class );
				}
				$new_image = $image;
				if ( false !== strpos( $image, 'data-src' ) && preg_match( '/srcset=[\'"](.+)[\'"]/U', $image, $srcset ) && false !== strpos( $srcset[0], 'data-' ) ) {
					$new_srcset = str_replace( 'srcset', 'data-srcset', $srcset[0] );
					$new_image  = str_replace( $srcset[0], $new_srcset, $image );
				}

				$class = trim( "{$original_class} wp-image-{$attachment_id}" );
				if ( false === strpos( $image, 'class=' ) ) {
					$new_image = str_replace( $src_attr, "class=\"$class\" " . $src_attr, $new_image );
				}
				$new_image = str_replace( $original_class, $class, $new_image );
				$content   = str_replace( $image, $new_image, $content );
			}
		}

		return $content;
	}
}