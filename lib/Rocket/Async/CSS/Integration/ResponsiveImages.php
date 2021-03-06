<?php


namespace Rocket\Async\CSS\Integration;


use ComposePress\Core\Abstracts\Component;
use Rocket\Async\CSS\DOMCollection;
use Rocket\Async\CSS\DOMDocument;

/**
 * Class ResponsiveImages
 * @package Rocket\Async\CSS\Integration
 */
class ResponsiveImages extends Component {
	/**
	 *
	 */
	const CACHE_NAME_URL_TO_ID = 'rocket_async_css_responsive_images_urls';
	/**
	 *
	 */
	const CACHE_NAME_ID_TO_URL = 'rocket_async_css_responsive_images';
	/**
	 * @var
	 */
	private $current_guid;
	/**
	 * @var DOMDocument
	 */
	private $document;

	/**
	 * ResponsiveImages constructor.
	 *
	 * @param DOMDocument $document
	 */
	public function __construct( DOMDocument $document ) {
		$this->document = $document;
	}

	/**
	 *
	 */
	public function init() {
		add_filter( 'wp', [ $this, 'wp_loaded' ] );
	}

	/**
	 *
	 */
	public function wp_loaded() {
		if ( is_admin() || wp_is_xml_request() || wp_is_json_request() || is_feed() || ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) ) {
			return;
		}
		add_filter( 'the_content', [ $this, 'process' ], 8 );
		add_filter( 'the_content', [ $this, 'process' ], 13 );
		add_filter( 'widget_text', [ $this, 'process' ], 9999 );
		add_filter( 'widget_text', [ $this, 'process' ], 10001 );
		add_action( 'elementor/widget/render_content', [ $this, 'process' ] );
		add_filter( 'do_shortcode_tag', 'wp_make_content_images_responsive', 9998 );
		add_filter( 'do_shortcode_tag', [ $this, 'process' ], 9999 );
		add_filter( 'do_shortcode_tag', 'wp_make_content_images_responsive', 10000 );
		add_filter( 'do_shortcode_tag', [ $this, 'process' ], 10001 );
		add_filter( 'rocket_async_css_request_buffer', 'wp_make_content_images_responsive' );
		add_filter( 'rocket_async_css_request_buffer', [ $this, 'process' ], 11 );
		add_filter( 'rocket_async_css_request_buffer', 'wp_make_content_images_responsive', 9999 );
		add_filter( 'rocket_async_css_request_buffer', [ $this, 'process' ], 10000 );
		add_filter( 'wp_get_attachment_image_attributes', [ $this, 'maybe_remove_src' ], 10, 2 );
		add_action( 'attachment_updated', [ $this, 'clear_attachment_cache' ] );
		add_action( 'delete_attachment', [ $this, 'clear_attachment_cache' ] );
	}

	/**
	 * @param $content
	 *
	 * @return string|string[]|null
	 */
	public function process( $content ) {

		if ( wp_doing_ajax() ) {
			if ( json_decode( $content ) ) {
				return $content;
			}
		}

		$new_content = $content;

		$partial = false;

		if ( has_filter( current_filter(), 'wpautop' ) ) {
			$new_content = wpautop( $new_content );
			$new_content = shortcode_unautop( $new_content );
		}

		if ( ! preg_match( '/<html[^>]*>/', $new_content ) ) {
			$new_content = "<html><head></head><body><div id=\"domdoc_content\">{$new_content}</div></body></html>";
			$partial     = true;
		}

		if ( ! @$this->document->loadHTML( $new_content ) ) {
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
				$striped_src = $this->plugin->strip_cdn( $src );
				if ( ! ( $attachments = wp_cache_get( $striped_src, self::CACHE_NAME_URL_TO_ID ) ) ) {
					add_filter( 'posts_where_paged', [ $this, 'filter_where' ] );
					$this->current_guid = $striped_src;
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
					wp_cache_set( $striped_src, $attachment_id, self::CACHE_NAME_URL_TO_ID );
					wp_cache_set( $attachment_id, $striped_src, self::CACHE_NAME_ID_TO_URL );
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
					$new_image = preg_replace( '/\=([\'"])(.+)\1/U', '="$2"', $new_image );
					$new_image = str_replace( [
						' srcset=""',
						" srcset=''",
					], '', $new_image );
				}
			}

			$image_document = new DOMDocument();
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

	/**
	 * @param $where
	 *
	 * @return string
	 */
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

	/**
	 * @param $attr
	 * @param \WP_Post $attachment
	 *
	 * @return mixed
	 */
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

	/**
	 * @param $post_id
	 */
	public function clear_attachment_cache( $post_id ) {
		if ( $url = wp_cache_get( $post_id, self::CACHE_NAME_ID_TO_URL ) ) {
			wp_cache_delete( $url, self::CACHE_NAME_URL_TO_ID );
			wp_cache_delete( $post_id, self::CACHE_NAME_ID_TO_URL );
		}
	}
}
