<?php


namespace Rocket\Async\CSS\Integration;


use pcfreak30\WordPress\Plugin\Framework\ComponentAbstract;

class ResponsiveImages extends ComponentAbstract {

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
				$new_image    = $image;
				$new_src_attr = $src_attr;
				$url          = parse_url( $src );
				if ( empty( $url['host'] ) ) {
					$new_src_attr = str_replace( $src, get_rocket_cdn_url( home_url( $src ) ), $src_attr );
					$new_image    = str_replace( $src_attr, $new_src_attr, $new_image );
				}


				if ( false !== strpos( $new_image, 'data-src' ) && preg_match( '/srcset=[\'"](.+)[\'"]/U', $new_image, $srcset ) && false !== strpos( $srcset[0], 'data-' ) ) {
					$new_srcset = str_replace( 'srcset', 'data-srcset', $srcset[0] );
					$new_image  = str_replace( $srcset[0], $new_srcset, $image );
				}

				$class = trim( "{$original_class} wp-image-{$attachment_id}" );
				if ( false === strpos( $image, 'class=' ) ) {
					$new_image = str_replace( $new_src_attr, "class=\"$class\" " . $new_src_attr, $new_image );
				}
				$new_image = str_replace( $original_class, $class, $new_image );
				$content   = str_replace( $image, $new_image, $content );
			}
		}
		if ( $this->is_lazyload_enabled() ) {
			$content = apply_filters( 'a3_lazy_load_html', $content );
			if ( function_exists( 'get_lazyloadxt_html' ) ) {
				$content = get_lazyloadxt_html( $content );
			}
		}

		return $content;
	}

	private function is_lazyload_enabled() {
		global $a3_lazy_load_global_settings;
		$lazy_load = false;
		if ( class_exists( 'A3_Lazy_Load' ) ) {
			$lazy_load = (bool) $a3_lazy_load_global_settings['a3l_apply_lazyloadxt'];
		}
		if ( class_exists( 'LazyLoadXT' ) ) {
			$lazy_load = true;
		}

		return $lazy_load;
	}
}