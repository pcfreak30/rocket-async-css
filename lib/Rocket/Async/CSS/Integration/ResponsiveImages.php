<?php


namespace Rocket\Async\CSS\Integration;


use ComposePress\Core\Abstracts\Component;
use Rocket\Async\CSS\DOMCollection;
use Rocket\Async\CSS\DOMDocument;

class ResponsiveImages extends Component {
	private $current_guid;
	private $document;

	/**
	 * ResponsiveImages constructor.
	 *
	 * @param DOMDocument $document
	 */
	public function __construct( DOMDocument $document ) {
		$this->document = $document;
	}

	public function init() {
		add_filter( 'the_content', [ $this, 'process' ], 8 );
		add_filter( 'the_content', [ $this, 'process' ], 13 );
		add_filter( 'widget_text', [ $this, 'process' ], 9999 );
		add_filter( 'widget_text', [ $this, 'process' ], 10001 );
		add_action( 'elementor/widget/render_content', [ $this, 'process' ] );
		add_filter( 'do_shortcode_tag', [ $this, 'process' ], 9999 );
		add_filter( 'do_shortcode_tag', 'wp_make_content_images_responsive', 10000 );
		add_filter( 'do_shortcode_tag', [ $this, 'process' ], 10001 );
		add_filter( 'rocket_async_css_request_buffer', [ $this, 'process' ] );
		add_filter( 'rocket_async_css_request_buffer', 'wp_make_content_images_responsive', 9999 );
		add_filter( 'rocket_async_css_request_buffer', [ $this, 'process' ], 10000 );
		add_filter( 'wp_get_attachment_image_attributes', [ $this, 'maybe_remove_src' ], 10, 2 );
	}

	public function process( $content ) {
		$new_content = $content;

		$partial = false;

		if ( ! preg_match( '/<html[^>]+>/', $new_content ) ) {
			$new_content = "<html><head></head><body><div id=\"domdoc_content\">{$new_content}</div></body></html>";
			$partial     = true;
		}

		if ( ! @$this->document->loadHTML( mb_convert_encoding( $new_content, 'HTML-ENTITIES', 'UTF-8' ) ) ) {
			return $content;
		}

		$lazyload_enabled = $this->plugin->util->is_lazyload_enabled();
		$collection       = new DOMCollection( $this->document, 'img' );

		while ( $collection->valid() ) {
			$image             = $collection->current();
			$srcset_match      = '' !== $image->getAttribute( 'srcset' );
			$data_srcset_match = '' !== $image->getAttribute( 'data-srcset' );
			$src_match         = '' !== $image->getAttribute( 'src' );
			$attachment_id     = 0;
			$path              = null;
			$class             = null;
			$original_class    = null;
			$wp_images         = null;
			$size              = null;
			$src               = null;
			$url               = null;
			$srcset            = null;
			$data_srcset       = null;
			$data_sizes        = null;
			$new_image         = null;
			if ( ! $src_match ) {
				$data_src_match = '' !== $image->getAttribute( 'data-src' );
			}

			if ( $src_match ) {
				$src_attr = 'src';
				$src      = $image->getAttribute( $src_attr );
			}

			if ( ! empty( $data_src_match ) ) {
				$src_attr = 'data-src';
				$src      = $image->getAttribute( $src_attr );
			}

			if ( ! empty( $src ) ) {
				if ( 0 === strpos( $src, 'data:' ) ) {
					$collection->next();
					continue;
				}

				$path = parse_url( $src, PHP_URL_PATH );
			}

			if ( ( $lazyload_enabled && ! $data_srcset_match ) || ( $lazyload_enabled && ! $data_src_match ) || ! $srcset_match ) {
				$class          = $image->getAttribute( 'class' );
				$original_class = array_map( 'trim', explode( ' ', $class ) );
			}

			if ( isset( $original_class ) ) {
				$wp_images = preg_grep( '/wp-image-\d+/', $original_class );
			}

			if ( ! empty( $wp_images ) ) {
				$attachment_id = str_replace( 'wp-image-', '', end( $wp_images ) );
			}

			if ( ! empty( $src ) && preg_match( '/\d+x\d+/', $path, $size ) ) {
				$size = end( $size );
				$src  = str_replace( "-{$size}", '', $src );
			}
			if ( empty( $attachment_id ) ) {
				if ( empty( $src ) ) {
					$collection->next();
					continue;
				}
				add_filter( 'posts_where_paged', [ $this, 'filter_where' ] );
				$this->current_guid = $this->plugin->strip_cdn( $src );
				$attachments        = get_posts( [
					'post_type'        => 'attachment',
					'suppress_filters' => false,
					'posts_per_page'   => 1,
					'order_by'         => 'none',
				] );
				remove_filter( 'posts_where_paged', [ $this, 'filter_where' ] );
				if ( ! empty( $attachments ) ) {
					$attachment_id = end( $attachments )->ID;
				}
				if ( empty( $attachment_id ) ) {
					$collection->next();
					continue;
				}
			}

			if ( ! empty( $src ) ) {
				$url = parse_url( $src );
				if ( empty( $url['host'] ) ) {
					$image->setAttribute( $src_attr, get_rocket_cdn_url( home_url( $src ) ) );
				}
			}

			if ( ! empty( $data_src_match ) || $data_srcset_match ) {
				$srcset = $image->getAttribute( 'srcset' );
				if ( ! empty( $srcset ) ) {
					if ( $lazyload_enabled ) {
						$image->setAttribute( 'data-srcset', $srcset );
						$image->removeAttribute( 'data-src' );
					}
					$image->removeAttribute( 'src' );
				}
			}

			$data_srcset = $image->getAttribute( 'data-srcset' );
			if ( ! empty( $data_srcset ) && $lazyload_enabled ) {
				$image->removeAttribute( 'data-src' );
				$image->removeAttribute( 'src' );
				$image->removeAttribute( 'srcset' );

				$data_sizes = $image->getAttribute( 'sizes' );
				if ( ! empty( $data_sizes ) ) {
					$image->setAttribute( 'data-sizes', $data_sizes );
					$image->removeAttribute( 'sizes' );
				}
			}


			if ( empty( $wp_images ) ) {
				$class = array_merge( $original_class ?: [], [ "wp-image-{$attachment_id}" ] );
				$class = array_filter( $class );
				$class = array_unique( $class );
				$class = implode( ' ', $class );
				$image->setAttribute( 'class', $class );
			}

			$new_image = $this->document->saveHTML( $image );

			$new_image = apply_filters( 'rocket_async_css_process_responsive_image', $new_image );
			if ( $lazyload_enabled && ! empty( $wp_images ) && apply_filters( 'rocket_async_css_lazy_load_responsive_image', true, $class, $src_match, $new_image ) ) {
				$new_image = apply_filters( 'a3_lazy_load_html', $new_image );
				if ( function_exists( 'get_lazyloadxt_html' ) ) {
					$new_image = get_lazyloadxt_html( $new_image );
				}
				if ( ! empty( $src ) ) {
					$new_image = preg_replace( '/\=([\'"])(.+)[\'"]/U', '="$2"', $new_image );
					$new_image = str_replace( [
						' srcset=""',
						" srcset=''",
					], '', $new_image );
				}
			}

			$image_document = new DOMDocument();
			$new_image      = mb_convert_encoding( $new_image, 'HTML-ENTITIES', 'UTF-8' );
			@$image_document->loadHTML( "<html><head></head><body>{$new_image}</body></html>" );
			$image->parentNode->replaceChild( $this->document->importNode( $image_document->getElementsByTagName( 'img' )->item( 0 ), true ), $image );
			$collection->next();
		}

		if ( ! $partial ) {
			return $this->document->saveHTML();
		}

		$content = '';

		$doc = $this->document->getElementById( 'domdoc_content' );
		foreach ( $doc->childNodes as $node ) {
			$content .= $this->document->saveHTML( $node );
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

	public function maybe_remove_src( $attr, \WP_Post $attachment ) {
		if ( ! empty( $attr['srcset'] ) || ! empty( $attr['data-srcset'] ) ) {
			if ( isset( $attr['src'] ) ) {
				unset( $attr['src'] );
			}
			if ( isset( $attr['data-src'] ) ) {
				unset( $attr['data-src'] );
			}
		}
		$classes = isset( $attr['class'] ) ? $attr['class'] : '';
		$classes = explode( ' ', $classes );

		$classes[] = "wp-image-{$attachment->ID}";
		$classes   = array_unique( $classes );

		$attr['class'] = implode( ' ', $classes );

		return $attr;
	}
}
