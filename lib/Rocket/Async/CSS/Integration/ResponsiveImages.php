<?php


namespace Rocket\Async\CSS\Integration;


use pcfreak30\WordPress\Plugin\Framework\ComponentAbstract;

class ResponsiveImages extends ComponentAbstract {
	private $current_guid;

	public function init() {
		add_filter( 'the_content', [ $this, 'process' ], 11 );
		add_filter( 'widget_text', [ $this, 'process' ], PHP_INT_MAX );
		add_filter( 'rocket_async_css_request_buffer', [ $this, 'process' ] );
		add_filter( 'rocket_async_css_request_buffer', 'wp_make_content_images_responsive', PHP_INT_MAX );
		add_filter( 'rocket_async_css_request_buffer', [ $this, 'process' ], PHP_INT_MAX );
	}

	public function process( $content ) {
		if ( ! preg_match_all( '/(?![\'"])\s*<img [^>]+>\s*(?![\'"])/', $content, $matches ) ) {
			return $content;
		}
		$lazyload_enabled = $this->plugin->util->is_lazyload_enabled();
		foreach ( $matches[0] as $image ) {
			if ( ! ( ( ! $lazyload_enabled && ( $lazyload_enabled && preg_match( '/srcset=[\'"](.+)[\'"]/U', $image ) ) ) || preg_match( '/data-srcset=[\'"](.+)[\'"]/U', $image ) ) && ( preg_match( '/data-src=[\'"](.+)[\'"]/U', $image, $src ) || preg_match( '/src=[\'"](.+)[\'"]/U', $image, $src ) ) ) {
				$src_attr    = array_shift( $src );
				$src         = trim( end( $src ) );
				$cleaned_src = trim( $src );
				$path        = parse_url( $src, PHP_URL_PATH );

				if ( preg_match( '/\d+x\d+/', $path, $size ) ) {
					$size = end( $size );
					$src  = str_replace( "-{$size}", '', $src );
				}
				add_filter( 'posts_where_paged', [ $this, 'filter_where' ] );
				$this->current_guid = $this->plugin->strip_cdn( $src );
				$attachments        = get_posts( [
					'post_type' => 'attachment'
				] );
				remove_filter( 'posts_where_paged', [ $this, 'filter_where' ] );
				$attachment_id = 0;
				if ( ! empty( $attachments ) ) {
					$attachment_id = get_the_ID( end( $attachments ) );
				}
				if ( empty( $attachment_id ) ) {
					continue;
				}
				$original_class = [];
				if ( preg_match( '/class=[\'"](.*)[\'"]/U', $image, $class ) ) {
					$original_class = array_map( 'trim', explode( ' ', end( $class ) ) );
				}
				$new_image    = $image;
				$new_src_attr = $src_attr;
				$url          = parse_url( $cleaned_src );
				if ( empty( $url['host'] ) ) {
					$new_src_attr = str_replace( $src, get_rocket_cdn_url( home_url( $src ) ), $src_attr );
					$new_image    = str_replace( $src_attr, $new_src_attr, $new_image );
				}


				if ( false !== strpos( $new_image, 'data-src' ) && preg_match( '/srcset=[\'"](.+)[\'"]/U', $new_image, $srcset ) && false !== strpos( $srcset[0], 'data-' ) ) {
					$new_srcset = str_replace( 'srcset', 'data-srcset', $srcset[0] );
					$new_image  = str_replace( $srcset[0], $new_srcset, $image );
				}

				$class = array_merge( $original_class, [ "wp-image-{$attachment_id}" ] );
				$class = array_unique( $class );
				if ( false === strpos( $image, 'class=' ) ) {
					$new_image = str_replace( $new_src_attr, "class=\"" . implode( $class, ' ' ) . "\" " . $new_src_attr, $new_image );
				}
				$new_image = str_replace( trim( implode( $original_class, ' ' ) ), trim( implode( $class, ' ' ) ), $new_image );
				if ( $lazyload_enabled && apply_filters( 'rocket_async_css_lazy_load_responsive_image', true, $class, $cleaned_src, $new_image ) ) {
					$new_image = apply_filters( 'a3_lazy_load_html', $new_image );
					if ( function_exists( 'get_lazyloadxt_html' ) ) {
						$new_image = get_lazyloadxt_html( $new_image );
					}
				}
				$content = str_replace( $image, $new_image, $content );
			}
		}


		return $content;
	}

	public function filter_where( $where ) {
		$where .= $this->wpdb->prepare( " AND guid = %s", $this->current_guid );

		return $where;
	}
}