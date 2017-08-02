<?php

namespace Rocket\Async;

use Rocket\Async\CSS\Cache\Manager;
use Rocket\Async\CSS\ComponentAbstract;
use Rocket\Async\CSS\DOMDocument;
use Rocket\Async\CSS\DOMElement;
use Rocket\Async\CSS\Integration\Manager as IntegrationManager;
use Rocket\Async\CSS\Request;

/**
 * Class CSS
 * @package Rocket\Async
 */
class CSS {
	/**
	 * Plugin version
	 */
	const VERSION = '0.5.5';

	/**
	 * Plugin version
	 */
	const TRANSIENT_PREFIX = 'rocket_async_css_style_';

	/**
	 * @var string
	 */
	private $plugin_file;
	/**
	 * @var DOMDocument
	 */
	private $document;

	/**
	 * @var string
	 */
	private $home;

	/**
	 * @var string
	 */
	private $domain;
	/**
	 * @var array
	 */
	private $cache;

	/**
	 * @var array
	 */
	private $cache_list;
	/**
	 * @var DOMDocument
	 */
	private $style_document;
	/**
	 * @var string
	 */
	private $css = '';

	/**
	 * @var array
	 */
	private $cdn_domains = [];
	/**
	 * @var \SplQueue
	 */
	private $node_queue;
	/**
	 * @var array
	 */
	private $urls = [];
	/**
	 * @var string
	 */
	private $file;
	/**
	 * @var \SplObjectStorage
	 */
	private $node_map;

	/**
	 * CSS constructor.
	 *
	 * @param IntegrationManager $integration_manager
	 * @param Request $request
	 * @param Manager $cache_manager
	 * @param DOMDocument $document
	 * @param DOMDocument $style_document
	 */
	public function __construct( IntegrationManager $integration_manager, Request $request, Manager $cache_manager, DOMDocument $document, DOMDocument $style_document ) {
		$this->integration_manager = $integration_manager;
		$this->request             = $request;
		$this->cache_manager       = $cache_manager;
		$this->document            = $document;
		$this->style_document      = $style_document;
		$this->node_queue          = new \SplQueue();
		$this->node_map            = new \SplObjectStorage();
		$this->plugin_file         = dirname( dirname( dirname( __DIR__ ) ) ) . '/rocket-async-css.php';
	}

	public function activate() {
		if ( ! ( defined( 'ROCKET_ASYNC_CSS_COMPOSER_RAN' ) && ROCKET_ASYNC_CSS_COMPOSER_RAN ) ) {
			/** @noinspection PhpIncludeInspection */
			include_once dirname( $this->plugin_file ) . '/wordpress-web-composer/class-wordpress-web-composer.php';
			$web_composer = new \WordPress_Web_Composer( 'rocket_async_css' );
			$web_composer->set_install_target( dirname( $this->plugin_file ) );
			$web_composer->run();
		}
		$this->init();
		do_action( 'rocket_async_css_activate' );
	}

	public function deactivate() {
		$this->cache_manager->get_store()->delete_cache_branch();
	}

	/**
	 *
	 */
	public function init() {
		global $wpml_url_filters;
		if ( ! $this->get_dependancies_exist() ) {
			return;
		}
		if ( ! empty( $wpml_url_filters ) ) {
			remove_filter( 'home_url', array( $wpml_url_filters, 'home_url_filter' ), - 10 );
		}
		//Get home URL
		$this->home = set_url_scheme( home_url() );
		// Get our domain
		$this->domain = parse_url( $this->home, PHP_URL_HOST );
		if ( ! empty( $wpml_url_filters ) ) {
			add_filter( 'home_url', array( $wpml_url_filters, 'home_url_filter' ), - 10 );
		}
		foreach ( get_object_vars( $this ) as &$property ) {
			if ( $property instanceof ComponentAbstract ) {
				$property->set_app( $this );
				$property->init();
			}
		}
	}

	/**
	 * @return bool
	 */
	protected function get_dependancies_exist() {
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		$error = false;
		if ( validate_plugin( 'wp-rocket/wp-rocket.php' ) ) {
			$error = true;
			add_action( 'admin_notices', [ $this, 'activate_error_no_wprocket' ] );
		} else if ( ! is_plugin_active( 'wp-rocket/wp-rocket.php' ) ) {
			$error = true;
			add_action( 'admin_notices', [ $this, 'activate_error_wprocket_inactive' ] );
		}
		if ( ! class_exists( 'DOMDocument' ) ) {
			$error = true;
			add_action( 'admin_notices', [ $this, 'activate_error_no_domdocument' ] );
		}

		return ! $error;
	}

	/**
	 * @param $buffer
	 *
	 * @return mixed|string
	 */
	public function process_buffer( $buffer ) {
		$this->disable_minify_overrides();
		if ( get_rocket_option( 'minify_css' ) && ! ( defined( 'DONOTMINIFYCSS' ) && DONOTMINIFYCSS ) && ! is_rocket_post_excluded_option( 'minify_css' ) && ! is_admin() && ! is_feed() && ! is_preview() && ! empty( $buffer ) ) {
			//Custom extract method based on wp-rockets
			list( $buffer, $conditionals ) = $this->extract_ie_conditionals( $buffer );

			// Import HTML
			if ( ! @$this->document->loadHTML( mb_convert_encoding( $buffer, 'HTML-ENTITIES', 'UTF-8' ) ) ) {
				return $buffer;
			}
			$head = $this->document->getElementsByTagName( 'head' )->item( 0 );
			if ( null === $head ) {
				return $buffer;
			}
			$this->normalize_cdn_domains();
			$this->build_style_list();
			$this->cleanup_nodes();

			if ( $this->node_queue->isEmpty() ) {
				return $buffer;
			}

			$this->fetch_cache();

			$filename = '';
			if ( empty( $this->cache ) ) {
				$filename = $this->get_cache_filename();
			}
			$this->process_styles();

			extract( $this->write_cache( $filename ), EXTR_OVERWRITE );

			/** @var string $href */
			$this->add_main_style( $href );

			$this->fix_old_libxml();

			//Get HTML
			$buffer = $this->document->saveHTML();

			$buffer     = $this->inject_ie_conditionals( $buffer, $conditionals );
			$this->file = $filename;
		}

		return $buffer;
	}

	/**
	 *
	 */
	protected function disable_minify_overrides() {
		remove_filter( 'pre_get_rocket_option_minify_css', '__return_zero' );/**/
	}

	/**
	 * @param $buffer
	 *
	 * @return array
	 */
	private function extract_ie_conditionals( $buffer ) {
		preg_match_all( '/<!--\[if[^\]]*?\]>.*?<!\[endif\]-->/is', $buffer, $conditionals_match );
		$conditionals = array();
		foreach ( $conditionals_match[0] as $conditional ) {
			if ( ! preg_match( '/<html[^>]*>/i', $buffer ) ) {
				$conditionals[] = $conditional;
				$buffer         = preg_replace( '/<!--\[if[^\]]*?\]>.*?<!\[endif\]-->/is', '<WP_ROCKET_CONDITIONAL />', $buffer, 1 );
			}
		}

		return array( $buffer, $conditionals );
	}

	/**
	 * @param DOMElement $nested_tag
	 */
	protected function build_style_list( $nested_tag = null ) {
		if ( null !== $nested_tag ) {
			/** @noinspection NotOptimalRegularExpressionsInspection */
			if ( ! preg_match( '/<([a-z]+) rel="stylesheet"([^<]+)*(?:>(.*)|\s+\/?>)/U', $nested_tag->textContent ) ) {
				return;
			}
			$document = new DOMDocument();
			if ( ! @$document->loadHTML( $nested_tag->textContent ) ) {
				return;
			}
			$nested_tag->textContent = strip_tags( $nested_tag->textContent );
		}
		$xpath        = new \DOMXPath( isset( $document ) ? $document : $this->document );
		$excluded_css = implode( '|', get_rocket_exclude_css() );
		$excluded_css = str_replace( '//' . $this->domain, '', $excluded_css );
		foreach ( $xpath->query( '((//style|//STYLE)|(//link|//LINK)[@rel="stylesheet"])' ) as $tag ) {
			/** @var DOMElement $tag */
			//Get all attributes
			$name  = strtolower( $tag->tagName );
			$rel   = $tag->getAttribute( 'rel' );
			$media = $tag->getAttribute( 'media' );
			$href  = $tag->getAttribute( 'href' );
			$type  = 'all';
			//Skip if data-no-minify if found
			if ( 0 < strlen( trim( $tag->getAttribute( 'data-no-minify' ) ) ) ) {
				continue;
			}
			if ( 'link' === $name ) {
				if ( 'stylesheet' === $rel ) {
					$css_url = parse_url( set_url_scheme( $href ) );
				}
				// If not a stylesheet, rocket_async_css_process_file return false, or exclude regex/file extension matches, move on
				if ( 'stylesheet' !== $rel
				     || ( has_filter( 'rocket_async_css_process_file' )
				          && ! apply_filters( 'rocket_async_css_process_file', true, $href ) ) ||
				     ( preg_match( '#^(' . $excluded_css . ')$#', $css_url['path'] )
				       && 'css' === pathinfo( $css_url['path'], PATHINFO_EXTENSION ) )
				) {
					continue;
				}
			}
			if ( 'style' === $name ) {
				if ( null === $nested_tag ) {
					if ( has_filter( 'rocket_async_css_process_style' ) && ! apply_filters( 'rocket_async_css_process_style', true, $tag->textContent ) ) {
						continue;
					}
					$this->build_style_list( $tag );
				}

			}
			if ( 'link' === $name ) {
				$this->cache_list['external'][] = $href;
			} else {
				$this->cache_list['inline'][] = $tag->textContent;
			}
			$this->style_document->appendChild( $tag );
		}
	}

	/**
	 *
	 */
	protected function cleanup_nodes() {
		/** @var DOMElement $tag */
		// Remove all elements from DOM
		foreach ( iterator_to_array( $this->style_document->childNodes ) as $tag ) {
			$this->node_queue->enqueue( $tag );
			$this->node_map[ $tag ]->remove();
		}
	}

	/**
	 *
	 */
	protected function fetch_cache() {
		$this->cache = $this->cache_manager->get_store()->get_cache_fragment( $this->get_cache_id() );
		if ( ! empty( $this->cache ) ) {
			// Cached file is gone, we dont have cache
			if ( ! file_exists( $this->cache  ['filename'] ) ) {
				$this->cache = false;
			}
		}
	}

	/**
	 * @return array
	 */
	protected function get_cache_id() {
		$post_cache_id_hash = md5( serialize( $this->cache_list ) );
		$post_cache_id      = array();
		if ( is_singular() ) {
			$post_cache_id [] = 'post_' . get_the_ID();
		} else if ( is_tag() || is_category() || is_tax() ) {
			$post_cache_id [] = 'tax_' . get_queried_object()->term_id;
		} else if ( is_author() ) {
			$post_cache_id [] = 'author_' . get_the_author_meta( 'ID' );
		} else {
			$post_cache_id [] = 'generic';
		}
		$post_cache_id [] = $post_cache_id_hash;
		if ( is_user_logged_in() ) {
			$post_cache_id [] = wp_get_current_user()->roles[0];
		}

		return $post_cache_id;
	}

	/**
	 *
	 */
	protected function normalize_cdn_domains() {
		// Remote fetch external scripts
		$this->cdn_domains = get_rocket_cdn_cnames();
		// Get the hostname for each CDN CNAME
		foreach ( array_keys( (array) $this->cdn_domains ) as $index ) {
			$cdn_domain       = &$this->cdn_domains[ $index ];
			$cdn_domain_parts = parse_url( $cdn_domain );
			$cdn_domain       = $cdn_domain_parts['host'];
		}
		// Cleanup
		unset( $cdn_domain_parts, $cdn_domain );
	}

	/**
	 * @return string
	 */
	protected function get_cache_filename() {
		$js_key     = get_rocket_option( 'minify_css_key' );
		$cache_path = $this->get_cache_path();
		// If we have a user logged in, include user role in filename to be unique as we may have user only CSS content. Otherwise file will be a hash of (minify-global-[js_key]-[content_hash]).js
		if ( is_user_logged_in() ) {
			$filename = $cache_path . md5( 'minify-' . wp_get_current_user()->roles[0] . '-' . $js_key . '-' . $this->get_cache_hash() ) . '.css';
		} else {
			$filename = $cache_path . md5( 'minify-global' . '-' . $js_key ) . '.css';
		}
		// Create post_cache dir if needed
		if ( ! is_dir( $cache_path ) ) {
			rocket_mkdir_p( $cache_path );
		}

		return $filename;
	}

	/**
	 * @return string
	 */
	protected function get_cache_path() {
		return WP_ROCKET_MINIFY_CACHE_PATH . get_current_blog_id() . DIRECTORY_SEPARATOR;
	}

	/**
	 * @return string
	 */
	protected function get_cache_hash() {
		return md5( serialize( $this->cache_list ) );
	}

	/**
	 *
	 */
	protected function process_styles() {
		foreach ( $this->node_queue as $tag ) {
			$this->process_style( $tag );
		}
	}

	/**
	 * @param $tag
	 */
	protected function process_style( $tag ) {
		$href = $tag->getAttribute( 'href' );
		//Decode html entities
		$href = html_entity_decode( preg_replace( '/((?<!&)#.*;)/', '&$1', $href ) );
		// Decode url % encoding
		$href = urldecode( $href );
		if ( 'link' === $tag->tagName && ! empty( $href ) ) {
			$media = $tag->getAttribute( 'media' );
			$this->process_external_style( $href, $media );

			return;
		}
		$this->process_inline_style( $tag );
	}

	/**
	 * @param  string $src
	 * @param $media
	 */
	protected function process_external_style( $src, $media ) {
		if ( empty( $this->cache ) ) {
			if ( 0 === strpos( $src, '//' ) ) {
				//Handle no protocol urls
				$src = rocket_add_url_protocol( $src );
			}
			//Has it been processed before?
			if ( ! in_array( $src, $this->urls ) ) {
				// Get host of tag source
				$src_host = parse_url( $src, PHP_URL_HOST );
				// Being remote is defined as not having our home url and not being in the CDN list. However if the file does not have a CSS extension, assume its a dynamic script generating CSS, so we need to web fetch it.
				if ( 0 != strpos( $src, '/' ) && ( ( $src_host != $this->domain && ! in_array( $src_host, $this->cdn_domains ) ) || 'css' !== pathinfo( parse_url( $src, PHP_URL_PATH ), PATHINFO_EXTENSION ) ) ) {
					$this->process_remote_style( $src );
					$this->urls[] = $src;

					return;
				}

				$this->process_local_style( $src, $media );
				$this->urls[] = $src;
			}
		}
	}

	/**
	 * @param string $src
	 *
	 * @internal param DOMElement $tag
	 */
	protected function process_remote_style( $src ) {
		// Check item cache
		$item_cache_id = [ md5( $src ) ];
		$item_cache    = $this->cache_manager->get_store()->get_cache_fragment( $item_cache_id );
		// Only run if there is no item cache
		if ( empty( $item_cache ) ) {
			$file = $this->remote_fetch( $src );
			// Catch Error
			if ( ! empty( $file ) ) {
				$css_part = $this->minify_remote_file( $src, $file );
				$this->cache_manager->get_store()->update_cache_fragment( $item_cache_id, $css_part );
				$this->css .= $css_part;
			}
		} else {
			$this->css .= apply_filters( 'rocket_footer_js_process_remote_script', $item_cache, $src );
		}
	}

	/**
	 * @param $url
	 *
	 * @return bool|string
	 */
	public function remote_fetch( $url ) {
		$file = wp_remote_get( $url, [
			'user-agent' => 'WP-Rocket',
			'sslverify'  => false,
		] );
		if ( ! ( $file instanceof \WP_Error || ( is_array( $file ) && ( empty( $file['response']['code'] ) || ! in_array( $file['response']['code'], array(
						200,
						304,
					) ) ) )
		)
		) {
			return $file['body'];
		}

		return false;
	}

	/**
	 * @param $url
	 * @param $css
	 *
	 * @return string
	 * @internal param string $script
	 */
	public function minify_remote_file( $url, $css ) {
		add_filter( 'rocket_url_no_dots', '__return_false', PHP_INT_MAX );
		$css_part = $this->minify_css( $css, array( 'prependRelativePath' => rocket_add_url_protocol( rocket_remove_url_protocol( trailingslashit( dirname( $url ) ) ) ) ), $url );
		remove_filter( 'rocket_url_no_dots', '__return_false', PHP_INT_MAX );

		return apply_filters( 'rocket_async_css_minify_remote_file', $css_part, $url );
	}

	/**
	 * @param $css
	 * @param array $options
	 * @param bool $local
	 *
	 * @return mixed|string
	 */
	public function minify_css( $css, $options = array(), $url ) {
		if ( ! class_exists( 'Minify_Loader' ) ) {
			require( WP_ROCKET_PATH . 'min/lib/Minify/Loader.php' );
			\Minify_Loader::register();
		}
		$css = \Minify_CSS::minify( $css, $options );
		$css = $this->parse_css_imports( $css, $url );
		$css = $this->download_remote_files( $css, $url );

		return $css;
	}

	/**
	 * @param $css
	 * @param $local
	 *
	 * @return mixed
	 */
	protected function parse_css_imports( $css, $url ) {
		preg_match_all( '/@import\s*(?:url\s*\()?["\'](.*?)["\']\)?\s*;/', $css, $matches );
		//Ensure there are matches
		if ( ! empty( $matches ) && ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $pos => $match ) {
				// Ensure not an empty string
				if ( ! empty( $match ) ) {
					$host = parse_url( $match, PHP_URL_HOST );
					if ( empty( $host ) ) {
						$match = \phpUri::parse( $url )->join( $match );
					}
					if ( $host != $this->domain && ! in_array( $host, $this->cdn_domains ) ) {
						$imported_data = $this->remote_fetch( $match );
					} else {
						$match = $this->strip_cdn( $match );
						// Is this a URL? If so replace with ABSPATH
						$match         = str_replace( $this->home, ABSPATH, $match );
						$path          = untrailingslashit( ABSPATH ) . $match;
						$imported_data = $this->get_content( $path );
					}
					// Process css to minify it, passing the path of the found file
					$imported_data = $this->minify_css( $imported_data, array( 'prependRelativePath' => trailingslashit( dirname( $match ) ) ), $match );
					// Replace match wth fetched css
					$css = str_replace( $matches[0][ $pos ], $imported_data, $css );
				}
			}
		}

		return $css;
	}

	protected function download_remote_files( $css, $url ) {
		preg_match_all( '/url\\(\\s*([\'"](.*?)[\'"]|[^\\)\\s]+)\\s*\\)/', $css, $matches );
		//Ensure there are matches
		if ( ! empty( $matches ) && ! empty( $matches[1] ) ) {
			foreach ( $matches[2] as $index => $match ) {
				if ( empty( $match ) ) {
					$match = $matches[1][ $index ];
				}
				if ( 0 === strpos( $match, 'data:' ) ) {
					continue;
				}
				$match     = trim( $match, '"' . "'" );
				$url_parts = parse_url( $match );
				if ( empty( $url_parts['host'] ) ) {
					$match     = \phpUri::parse( $url )->join( $match );
					$url_parts = parse_url( $match );
				}
				if ( ! ( $url_parts['host'] != $this->domain && ! in_array( $url_parts['host'], $this->cdn_domains ) ) ) {
					continue;
				}
				$data = $this->remote_fetch( $match );
				if ( ! empty( $data ) ) {
					$info      = pathinfo( $match );
					$hash      = md5( $info['dirname'] . '/' . $info['filename'] );
					$filename  = $this->get_cache_path() . $hash . '.' . $info['extension'];
					$final_url = parse_url( get_rocket_cdn_url( set_url_scheme( str_replace( WP_CONTENT_DIR, WP_CONTENT_URL, $filename ) ) ), PHP_URL_PATH );
					$css_part  = str_replace( $match, $final_url, $matches[0][ $index ] );
					$css       = str_replace( $matches[0][ $index ], $css_part, $css );
					if ( ! $this->get_wp_filesystem()->is_file( $filename ) ) {
						$this->put_content( $filename, $data );
					}
				}

			}
		}

		return $css;
	}

	/**
	 * @param $file
	 *
	 * @return bool|mixed
	 */
	public function get_content(
		$file
	) {
		return $this->get_wp_filesystem()->get_contents( $file );
	}

	/**
	 * @param string $href
	 *
	 * @param $media
	 *
	 * @internal param DOMElement $tag
	 */
	protected function process_local_style(
		$href, $media
	) {
		if ( 0 == strpos( $href, '/' ) ) {
			$href = $this->home . $href;
		}
		// Remove query strings
		$src_file = $href;
		if ( false !== strpos( $href, '?' ) ) {
			$src_file = substr( $href, 0, strpos( $href, strrchr( $href, '?' ) ) );
		}
		// Break up url
		$url       = $this->strip_cdn( $src_file );
		$url_parts = parse_url( $url );

		foreach ( array_map( 'trim', array_filter( explode( ',', $media ) ) ) as $type_item ) {
			if ( in_array( $type_item, array(
				'screen',
				'projection',
			) ) ) {
				$media = 'all';
			}
			break;
		}

		// Check item cache
		$item_cache_id = [ md5( $href ) ];
		$item_cache    = $this->cache_manager->get_store()->get_cache_fragment( $item_cache_id );
		// Only run if there is no item cache
		if ( empty( $item_cache ) ) {
			$file     = $this->get_content( str_replace( $this->home, ABSPATH, $url ) );
			$css_part = $file;
			if ( ! empty( $media ) && 'all' !== $media ) {
				$css_part = '@media ' . $media . ' {' . $css_part . '}';
			}
			$css_part  = $this->minify_css( $css_part, [ 'prependRelativePath' => trailingslashit( dirname( $url_parts['path'] ) ) ], $url );
			$this->css .= $css_part;
			$this->cache_manager->get_store()->update_cache_fragment( $item_cache_id, $css_part );
		} else {
			$this->css .= $item_cache;
		}
	}

	/**
	 * @param $tag
	 */
	protected function process_inline_style(
		$tag
	) {
		// Check item cache
		$item_cache_id = [ md5( $tag->textContent ) ];
		$item_cache    = $this->cache_manager->get_store()->get_cache_fragment( $item_cache_id );
		// Only run if there is no item cache
		if ( empty( $item_cache ) ) {
			// Remove any conditional comments for IE that somehow was put in the script tag
			$css_part = preg_replace( '/(?:<!--)?\[if[^\]]*?\]>.*?<!\[endif\]-->/is', '', $tag->textContent );
			//Minify ?
			if ( get_rocket_option( 'minify_html_inline_css', false ) ) {
				$css_part = $this->minify_css( $css_part, [], $_SERVER['REQUEST_URI'] );
			}
			$this->cache_manager->get_store()->update_cache_fragment( $item_cache_id, $css_part );
		} else {
			$css_part = $item_cache;
		}
		//Add inline CSS to buffer
		$this->css .= $css_part;
	}

	/**
	 * @param $filename
	 *
	 * @return array
	 */
	protected function write_cache(
		$filename
	) {
		if ( empty( $this->cache ) ) {
			$data = [ 'filename' => $filename ];
			$this->put_content( $filename, $this->css );
			$data['href'] = get_rocket_cdn_url( set_url_scheme( str_replace( WP_CONTENT_DIR, WP_CONTENT_URL, $filename ) ) );
			$this->cache_manager->get_store()->update_cache_fragment( $this->get_cache_id(), $data );

			return $data;
		}

		return $this->cache;
	}

	/**
	 * @param $file
	 * @param $data
	 *
	 * @return bool
	 */
	public function put_content(
		$file, $data
	) {
		return $this->get_wp_filesystem()->put_contents( $file, $data );
	}

	/**
	 * @return \WP_Filesystem_Base
	 */
	protected function get_wp_filesystem() {
		/** @var \WP_Filesystem_Base $wp_filesystem */
		global $wp_filesystem;
		if ( is_null( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		return $wp_filesystem;
	}

	/**
	 * @param $href
	 */
	protected function add_main_style(
		$href
	) {
		$external_tag = $this->document->createElement( 'script' );
		$external_tag->setAttribute( 'data-no-minify', '1' );
		$js = '(function(h){var d=function(d,e,n){function k(a){if(b.body)return a();setTimeout(function(){k(a)})}function f(){a.addEventListener&&a.removeEventListener("load",f);a.media=n||"all"}var b=h.document,a=b.createElement("link"),c;if(e)c=e;else{var l=(b.body||b.getElementsByTagName("head")[0]).childNodes;c=l[l.length-1]}var m=b.styleSheets;a.rel="stylesheet";a.href=d;a.media="only x";k(function(){c.parentNode.insertBefore(a,e?c:c.nextSibling)});var g=function(b){for(var c=a.href,d=m.length;d--;)if(m[d].href===
c)return b();setTimeout(function(){g(b)})};a.addEventListener&&a.addEventListener("load",f);a.onloadcssdefined=g;g(f);return a};"undefined"!==typeof exports?exports.loadCSS=d:h.loadCSS=d})("undefined"!==typeof global?global:this);';

		$js .= "loadCSS(" . wp_json_encode( $href ) . ',  document.getElementsByTagName("head")[0].childNodes[ document.getElementsByTagName("head")[0].childNodes.length-1]);';
		$external_tag->appendChild( $this->document->createTextNode( $js ) );
		$this->document->getElementsByTagName( 'head' )->item( 0 )->appendChild( $external_tag );
	}

	/**
	 *
	 */
	protected function fix_old_libxml() {
		// Hack to fix a bug with libxml versions earlier than 2.9.x
		$body = $this->document->getElementsByTagName( 'body' )->item( 0 );
		if ( 1 === version_compare( '2.9.0', LIBXML_DOTTED_VERSION ) ) {
			$body_class = $body->getAttribute( 'class' );
			if ( empty( $body_class ) ) {
				$body->setAttribute( 'class', implode( ' ', get_body_class() ) );
			}
		}
	}

	public function strip_cdn( $url ) {
		$url_parts           = parse_url( $url );
		$url_parts['host']   = $this->domain;
		$url_parts['scheme'] = is_ssl() ? 'https' : 'http';
		/*
		 * Check and see what version of php-http we have.
		 * 1.x uses procedural functions.
		 * 2.x uses OOP classes with a http namespace.
		 * Convert the address to a path, minify, and add to buffer.
		 */
		if ( class_exists( 'http\Url' ) ) {
			$url = new \http\Url( $url_parts );
			$url = $url->toString();
		} else {
			$url = http_build_url( $url_parts );
		}

		return $url;
	}

	/**
	 * Add conditional comments back in
	 *
	 * @param $buffer
	 * @param $conditionals
	 *
	 * @return mixed
	 */
	protected function inject_ie_conditionals(
		$buffer, $conditionals
	) {
		foreach ( $conditionals as $conditional ) {
			if ( false !== strpos( $buffer, '<WP_ROCKET_CONDITIONAL />' ) ) {
				$buffer = preg_replace( '/<WP_ROCKET_CONDITIONAL \/>/', $conditional, $buffer, 1 );
			} else {
				break;
			}
		}

		return $buffer;
	}

	/**
	 *
	 */
	public function activate_error_no_wprocket() {
		$info = get_plugin_data( $this->plugin_file );
		_e( sprintf( '
	<div class="error notice">
		<p>Opps! %s requires WP-Rocket! Please Download at <a href="http://www.wp-rocket.me">www.wp-rocket.me</a></p>
	</div>', $info['Name'] ) );
	}

	/**
	 *
	 */
	public function activate_error_wprocket_inactive() {
		$info = get_plugin_data( $this->plugin_file );
		$path = 'wp-rocket/wp-rocket.php';
		_e( sprintf( '
	<div class="error notice">
		<p>Opps! %s requires WP-Rocket! Please Enable the plugin <a href="%s">here</a></p>
	</div>', $info['Name'], wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . $path ), 'activate-plugin_' . $path ) ) );
	}

	/**
	 *
	 */
	public function activate_error_no_domdocument() {
		$info = get_plugin_data( $this->plugin_file );
		_e( sprintf( '
	<div class="error notice">
		<p>Opps! %s requires PHP XML extension! Please contact your web host or system administrator to get this installed.</p>
	</div>', $info['Name'] ) );
	}

	/**
	 * @return mixed
	 */
	public function get_file() {
		return $this->file;
	}

	/**
	 * @return \SplObjectStorage
	 */
	public function get_node_map() {
		return $this->node_map;
	}

	/**
	 * @return string
	 */
	public function get_plugin_file() {
		return $this->plugin_file;
	}


}