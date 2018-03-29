<?php


namespace Rocket\Async\CSS\Integration;


use ComposePress\Core\Abstracts\Component;

class ResponsiveImages extends Component {
	private $current_guid;

	public function init() {
		add_filter( 'the_content', [ $this, 'process' ], 8 );
		add_filter( 'the_content', [ $this, 'process' ], 13 );
		add_filter( 'widget_text', [ $this, 'process' ], 9999 );
		add_filter( 'widget_text', [ $this, 'process' ], 10001 );
		add_filter( 'do_shortcode_tag', [ $this, 'process' ], 9999 );
		add_filter( 'do_shortcode_tag', 'wp_make_content_images_responsive', 10000 );
		add_filter( 'do_shortcode_tag', [ $this, 'process' ], 10001 );
		add_filter( 'rocket_async_css_request_buffer', [ $this, 'process' ] );
		add_filter( 'rocket_async_css_request_buffer', 'wp_make_content_images_responsive', 9999 );
		add_filter( 'rocket_async_css_request_buffer', [ $this, 'process' ], 10000 );
	}

	public function process( $content ) {
		if ( ! preg_match_all( '/(?![\'"])\s*<img [^>]+>\s*(?![\'"])/', $content, $matches ) ) {
			return $content;
		}
		$lazyload_enabled = $this->plugin->util->is_lazyload_enabled();
		foreach ( $matches[0] as $image ) {

			$srcset_match      = false !== strpos( $image, ' data-srcset=' ) && preg_match( '/srcset=[\'"](.+)[\'"]/U', $image );
			$data_srcset_match = false !== strpos( $image, ' srcset=' ) && preg_match( '/data-srcset=[\'"](.+)[\'"]/U', $image );
			$src_match         = preg_match( '/src=[\'"](.+)[\'"]/U', $image, $src ) && false !== strpos( $image, ' src=' );
			$data_src_match    = false;
			if ( ! $src_match ) {
				$data_src_match = preg_match( '/data-src=[\'"](.+)[\'"]/U', $image, $src ) && false !== strpos( $image, ' data-src=' );
			}
			if ( ( $lazyload_enabled && ! $data_srcset_match ) || ( $lazyload_enabled && ! $data_src_match ) || ! $srcset_match ) {
				$src_attr    = array_shift( $src );
				$src         = trim( end( $src ) );
				$cleaned_src = trim( $src );
				$path        = parse_url( $src, PHP_URL_PATH );

				$original_class      = [];
				$original_class_html = '';
				if ( preg_match( '/class=[\'"](.*)[\'"]/U', $image, $class ) ) {
					$original_class      = array_map( 'trim', explode( ' ', end( $class ) ) );
					$original_class_html = $class[0];
				}
				$wp_images = preg_grep( '/wp-image-\d+/', $original_class );
				if ( ! empty( $wp_images ) ) {
					$attachment_id = str_replace( 'wp-image-', '', end( $wp_images ) );
				}
				if ( preg_match( '/\d+x\d+/', $path, $size ) ) {
					$size = end( $size );
					$src  = str_replace( "-{$size}", '', $src );
				}
				if ( empty( $attachment_id ) ) {
					add_filter( 'posts_where_paged', [ $this, 'filter_where' ] );
					$this->current_guid = $this->plugin->strip_cdn( $src );
					$attachments        = get_posts( [
						'post_type'        => 'attachment',
						'suppress_filters' => false
					] );
					remove_filter( 'posts_where_paged', [ $this, 'filter_where' ] );
					$attachment_id = 0;
					if ( ! empty( $attachments ) ) {
						$attachment_id = end( $attachments )->ID;
					}
					if ( empty( $attachment_id ) ) {
						continue;
					}
				}

				$new_image    = $image;
				$new_src_attr = $src_attr;
				$url          = parse_url( $cleaned_src );
				if ( empty( $url['host'] ) ) {
					$new_src_attr = str_replace( $src, get_rocket_cdn_url( home_url( $src ) ), $src_attr );
					$new_image    = str_replace( $src_attr, $new_src_attr, $new_image );
				}

				if ( ! empty( $wp_images ) && $srcset_match ) {
					$new_image = str_replace( $new_src_attr, '', $new_image );
				}

				if ( $lazyload_enabled && false !== strpos( $new_image, 'data-src' ) && false !== strpos( $new_image, ' data-srcset=' ) && preg_match( '/data-srcset=[\'"](.+)[\'"]/U', $new_image, $srcset ) ) {
					$new_srcset = str_replace( ' srcset', ' data-srcset', $srcset[0] );
					$new_image  = str_replace( $srcset[0], $new_srcset, $image );
				}
				if ( empty( $wp_images ) ) {
					$class = array_merge( $original_class, [ "wp-image-{$attachment_id}" ] );
					$class = array_unique( $class );
					if ( false === strpos( $image, 'class=' ) ) {
						$new_image = str_replace( $new_src_attr, "class=\"" . implode( $class, ' ' ) . "\" " . $new_src_attr, $new_image );
					}
					$new_image_class_html = str_replace( trim( implode( $original_class, ' ' ) ), trim( implode( $class, ' ' ) ), $original_class_html );
					$new_image            = str_replace( $original_class_html, $new_image_class_html, $new_image );
				}

				$new_image = apply_filters( 'rocket_async_css_process_responsive_image', $new_image );
				if ( $lazyload_enabled && ! empty( $wp_images ) && apply_filters( 'rocket_async_css_lazy_load_responsive_image', true, $class, $cleaned_src, $new_image ) ) {
					$new_image = apply_filters( 'a3_lazy_load_html', $new_image );
					if ( function_exists( 'get_lazyloadxt_html' ) ) {
						$new_image = get_lazyloadxt_html( $new_image );
					}
					$new_image = str_replace( 'srcset=""', '', $new_image );
				}
				if ( $image !== $new_image ) {
					$content = str_replace( $image, $new_image, $content );
				}
			}
		}


		return $content;
	}

	public
	function filter_where(
		$where
	) {
		$url_parts           = parse_url( $this->current_guid );
		$url_parts['scheme'] = 'http';
		$url                 = http_build_url( $url_parts );

		$url_parts['scheme'] = 'https';
		$url_ssl             = http_build_url( $url_parts );

		$where .= $this->wpdb->prepare( " AND (guid = %s OR guid = %s)", $url, $url_ssl );

		return $where;
	}
}