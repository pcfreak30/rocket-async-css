<?php

namespace Rocket\Async;

use ComposePress\Core\Abstracts\Plugin;
use Rocket\Async\CSS\Cache\Manager;
use Rocket\Async\CSS\DOMDocument;
use Rocket\Async\CSS\DOMElement;
use Rocket\Async\CSS\Integration\Manager as IntegrationManager;
use Rocket\Async\CSS\Request;
use Rocket\Async\CSS\Util;

/**
 * Class CSS
 *
 * @package Rocket\Async
 */
class CSS extends Plugin {
	/**
	 * Plugin version
	 */
	const VERSION = '0.7.1.0';

	/**
	 *  Transient Prefix
	 */
	const TRANSIENT_PREFIX = 'rocket_async_css_style_';

	/**
	 *
	 */
	const PLUGIN_SLUG = 'rocket-async-css';

	/**
	 *
	 */
	const URL_SRC_REGEX = '/url\\(\\s*([\'"]?(.*?)[\'"]?|[^\\)\\s]+)\\s*\\)/';
	/**
	 *
	 */
	const IMPORT_REGEX = '/@import\s*(?:url\s*\()?["\']?(.*?)["\']?\)?\s*;/';
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
	 * @var DOMDocument[]
	 */
	private $media_documents;
	/**
	 * @var DOMDocument[]
	 */
	private $starter_document;
	/**
	 * @var string
	 */
	private $css = [];

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
	private $files;
	/**
	 * @var \SplObjectStorage
	 */
	private $node_map;
	/**
	 * @var Request
	 */
	private $request;
	/**
	 * @var IntegrationManager
	 */
	private $integration_manager;
	/**
	 * @var Manager
	 */
	private $cache_manager;
	/**
	 * @var Util
	 */
	private $util;
	/**
	 * @var array
	 */
	private $ie_conditionals;
	/**
	 * @var array
	 */
	private $remote_url_cache = [];
	/**
	 * @var array
	 */
	private $file_hash_cache = [];

	/**
	 * CSS constructor.
	 *
	 * @param IntegrationManager $integration_manager
	 * @param Request $request
	 * @param Manager $cache_manager
	 * @param DOMDocument $document
	 * @param DOMDocument $starter_document
	 * @param Util $util
	 *
	 * @internal param DOMDocument $style_document
	 */
	public function __construct(
		IntegrationManager $integration_manager,
		Request $request,
		Manager $cache_manager,
		DOMDocument $document,
		DOMDocument $starter_document,
		Util $util
	) {
		$this->integration_manager = $integration_manager;
		$this->request             = $request;
		$this->cache_manager       = $cache_manager;
		$this->document            = $document;
		$this->starter_document    = $starter_document;
		$this->node_queue          = new \SplQueue();
		$this->node_map            = new \SplObjectStorage();
		$this->util                = $util;
		parent::__construct();
	}

	/**
	 * @return string
	 */
	public function get_domain() {
		return $this->domain;
	}

	/**
	 * @return array
	 */
	public function get_cdn_domains() {
		return $this->cdn_domains;
	}

	/**
	 * @return Manager
	 */
	public function get_cache_manager() {
		return $this->cache_manager;
	}

	/**
	 * @return IntegrationManager
	 */
	public function get_integration_manager() {
		return $this->integration_manager;
	}

	/**
	 * @return Request
	 */
	public function get_request() {
		return $this->request;
	}

	/**
	 *
	 */
	public function activate() {
		$this->init();
		do_action( 'rocket_async_css_activate' );
	}

	/**
	 *
	 */
	public function init() {
		if ( ! $this->get_dependancies_exist() ) {
			return;
		}
		if ( isset( $this->wpml_url_filters ) ) {
			remove_filter( 'home_url', array( $this->wpml_url_filters, 'home_url_filter' ), - 10 );
		}
		//Get home URL
		$this->home = set_url_scheme( home_url() );
		// Get our domain
		$this->domain = parse_url( $this->home, PHP_URL_HOST );
		$this->normalize_cdn_domains();
		if ( isset( $this->wpml_url_filters ) ) {
			add_filter( 'home_url', array( $this->wpml_url_filters, 'home_url_filter' ), - 10, 4 );
		}

		parent::init();
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
		if ( ! did_action( 'wp_rocket_loaded' ) ) {
			$error = true;
		}


		return ! $error;
	}

	/**
	 *
	 */
	private function normalize_cdn_domains() {
		add_filter( 'pre_get_rocket_option_cdn', '__return_one' );
		// Remote fetch external scripts
		$this->cdn_domains = get_rocket_cdn_cnames( [
			'all',
			'css',
			'js',
			'css_and_js',
			'images',
		] );
		remove_filter( 'pre_get_rocket_option_cdn', '__return_one' );
		// Get the hostname for each CDN CNAME
		foreach ( array_keys( (array) $this->cdn_domains ) as $index ) {
			$cdn_domain       = &$this->cdn_domains[ $index ];
			$cdn_domain_parts = parse_url( $cdn_domain );
			if ( empty( $cdn_domain_parts['host'] ) ) {
				$cdn_domain       = "//{$cdn_domain}";
				$cdn_domain       = set_url_scheme( $cdn_domain );
				$cdn_domain_parts = parse_url( $cdn_domain );
			}
			$cdn_domain = $cdn_domain_parts['host'];
		}
		// Cleanup
		unset( $cdn_domain_parts, $cdn_domain );
	}


	/**
	 *
	 */
	public function deactivate() {
		do_action( 'rocket_async_css_deactivate' );
		$this->cache_manager->get_store()->delete_cache_branch();
	}

	/**
	 * @param $buffer
	 *
	 * @return mixed|string
	 */
	public function process_buffer( $buffer ) {
		$this->disable_minify_overrides();
		if ( $this->do_process_page( $buffer ) ) {
			// Import HTML
			if ( ! @$this->document->loadHTML( mb_convert_encoding( $buffer, 'HTML-ENTITIES', 'UTF-8' ) ) ) {
				return $buffer;
			}

			$head = $this->document->getElementsByTagName( 'head' )->item( 0 );
			if ( null === $head ) {
				$buffer = $this->inject_ie_conditionals( $buffer );

				return $buffer;
			}

			do_action( 'rocket_async_css_do_rewrites' );

			$this->build_style_list();
			$this->cleanup_nodes();

			if ( $this->node_queue->isEmpty() ) {
				return $buffer;
			}

			$this->maybe_fetch_cache();

			$filename = [];
			if ( empty( $this->cache ) ) {
				$filename = $this->get_cache_filenames();
			}
			$this->process_styles();

			extract( $this->write_cache( $filename ), EXTR_OVERWRITE );

			/** @var string $href */
			$this->add_main_styles( $href );

			$this->fix_old_libxml();

			//Get HTML
			$buffer = $this->document->saveHTML();
			$buffer = $this->inject_ie_conditionals( $buffer );

			$this->files = $filename;
		}


		return $buffer;
	}

	/**
	 *
	 */
	private function disable_minify_overrides() {
		remove_filter( 'pre_get_rocket_option_minify_css', '__return_zero' );
	}

	/**
	 * @param $buffer
	 *
	 * @return bool
	 */
	private function do_process_page( $buffer ) {
		return get_rocket_option( 'minify_css' ) && ! ( defined( 'DONOTMINIFYCSS' ) && DONOTMINIFYCSS ) && ! is_rocket_post_excluded_option( 'minify_css' ) && ! is_admin() && ! is_feed() && ! is_preview() && ! empty( $buffer );
	}

	/**
	 * Add conditional comments back in
	 *
	 * @param $buffer
	 *
	 * @return mixed
	 */
	private function inject_ie_conditionals(
		$buffer
	) {
		foreach ( $this->ie_conditionals as $conditional ) {
			if ( false !== stripos( $buffer, '<!-- WP_ROCKET_CONDITIONAL -->' ) ) {
				$buffer = preg_replace( '/<!-- WP_ROCKET_CONDITIONAL -->/i', $conditional, $buffer, 1 );
			} else {
				break;
			}
		}

		return $buffer;
	}

	/**
	 * @param DOMElement $nested_tag
	 */
	private function build_style_list( $nested_tag = null ) {
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
		$excluded_css = implode( '|', function_exists( 'get_rocket_exclude_files' ) ? get_rocket_exclude_files( 'css' ) : get_rocket_exclude_css() );
		$excluded_css = str_replace( '//' . $this->domain, '', $excluded_css );
		foreach ( $xpath->query( '((//style|//STYLE)|(//link|//LINK)[@rel="stylesheet"])' ) as $tag ) {
			/** @var DOMElement $tag */
			//Get all attributes
			$name  = strtolower( $tag->tagName );
			$rel   = $tag->getAttribute( 'rel' );
			$media = $tag->getAttribute( 'media' );
			$href  = $tag->getAttribute( 'href' );
			foreach ( array_map( 'trim', array_filter( explode( ',', $media ) ) ) as $type_item ) {
				if ( in_array( $type_item, array(
					'screen',
					'projection',
				) ) ) {
					$media = 'all';
				}
				break;
			}
			if ( empty( $media ) ) {
				$media = 'all';
			}
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
			$this->get_media_document( $media )->appendChild( $tag );
		}
	}

	/**
	 * @param $media
	 *
	 * @return DOMDocument
	 */
	private function get_media_document( $media ) {
		if ( ! isset( $this->media_documents[ $media ] ) ) {
			$this->media_documents[ $media ] = clone $this->starter_document;
		}

		return $this->media_documents[ $media ];
	}

	/**
	 *
	 */
	private function cleanup_nodes() {
		/** @var DOMElement $tag */
		// Remove all elements from DOM
		foreach ( $this->media_documents as $document ) {
			foreach ( iterator_to_array( $document->childNodes ) as $tag ) {
				$this->node_queue->enqueue( $tag );
				$this->node_map[ $tag ]->remove();
			}
		}

	}

	/**
	 *
	 */
	private function maybe_fetch_cache() {
		if ( null != $this->cache ) {
			return;
		}

		$this->cache = $this->get_cache_fragment( $this->get_cache_id() );

		if ( ! empty( $this->cache ) ) {
			// Cached file is gone, we dont have cache
			foreach ( (array) $this->cache  ['filename'] as $filename ) {
				if ( ! file_exists( $filename ) ) {
					$this->cache = false;
					break;
				}
			}
			$files = [];
			if ( ! empty( $this->cache['file_hash_cache'] ) ) {
				foreach ( $this->cache['file_hash_cache'] as $hash => $filename ) {
					$filepath = ABSPATH . $filename;
					if ( file_exists( $filepath ) && $hash === md5_file( $filepath ) ) {
						$files[ $hash ] = $filename;
					}
				}
				$this->file_hash_cache = $files;
			}

			return;
		}
		$this->cache = false;
	}

	private function get_cache_fragment( $cache_id ) {
		if ( apply_filters( 'rocket_async_css_save_cache', true ) ) {
			return $this->cache_manager->get_store()->get_cache_fragment( $cache_id );
		}

		return false;
	}

	/**
	 * @return array
	 */
	private function get_cache_id() {
		$post_cache_id_hash = $this->get_cache_hash();
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


		return apply_filters( 'rocket_async_css_get_cache_id', $post_cache_id );
	}

	/**
	 * @return string
	 */
	private function get_cache_hash() {
		return md5( serialize( $this->cache_list ) );
	}

	/**
	 * @return array
	 */
	private function get_cache_filenames() {
		$js_key     = get_rocket_option( 'minify_css_key' );
		$cache_path = $this->get_cache_path();
		$filenames  = [];
		foreach ( array_keys( $this->media_documents ) as $media ) {
			// If we have a user logged in, include user role in filename to be unique as we may have user only CSS content. Otherwise file will be a hash of (minify-global-[js_key]-[content_hash]).js
			if ( is_user_logged_in() ) {
				$filename = [ 'minify', wp_get_current_user()->roles[0], $js_key, $this->get_cache_hash(), $media ];
			} else {
				$filename = [ 'minify-global', $js_key, $this->get_cache_hash(), $media ];
			}
			$filename            = (array) apply_filters( 'rocket_async_css_get_cache_filename', $filename );
			$filename            = implode( '-', $filename );
			$filename            = $cache_path . md5( $filename ) . '.css';
			$filenames[ $media ] = $filename;
		}

		return $filenames;
	}

	/**
	 * @return string
	 */
	public function get_cache_path() {
		$cache_path = WP_ROCKET_MINIFY_CACHE_PATH . get_current_blog_id() . DIRECTORY_SEPARATOR;
		// Create post_cache dir if needed
		if ( ! is_dir( $cache_path ) ) {
			rocket_mkdir_p( $cache_path );
		}

		return $cache_path;
	}

	/**
	 *
	 */
	private function process_styles() {
		if ( ! $this->cache ) {
			/** @var DOMElement $tag */
			foreach ( $this->node_queue as $tag ) {
				$media = false;
				foreach ( $this->media_documents as $the_media => $document ) {
					if ( $tag->ownerDocument->isSameNode( $document ) ) {
						$media = $the_media;
						break;
					}
				}
				if ( $media ) {
					$this->process_style( $tag, $media, false );
				}
			}
			$this->node_queue->rewind();
		}

		/** @var DOMElement $tag */
		foreach ( $this->node_queue as $tag ) {
			$media = false;
			foreach ( $this->media_documents as $the_media => $document ) {
				if ( $tag->ownerDocument->isSameNode( $document ) ) {
					$media = $the_media;
					break;
				}
			}
			if ( $media ) {
				$this->process_style( $tag, $media, true );
			}
		}
	}

	/**
	 * @param $tag
	 */
	private function process_style( $tag, $media, $minify ) {
		$href = $tag->getAttribute( 'href' );
		//Decode html entities
		$href = html_entity_decode( preg_replace( '/((?<!&)#.*;)/', '&$1', $href ) );
		// Decode url % encoding
		$href = urldecode( $href );

		if ( ! isset( $this->css[ $media ] ) ) {
			$this->css[ $media ] = '';
		}

		if ( 'link' === $tag->tagName && ! empty( $href ) ) {
			$this->process_external_style( $href, $media, $minify );

			return;
		}
		$this->process_inline_style( $tag, $media, $minify );
	}

	/**
	 * @param string $src
	 * @param        $media
	 */
	private function process_external_style( $src, $media, $minify ) {
		if ( empty( $this->cache ) ) {
			if ( 0 === strpos( $src, '//' ) ) {
				//Handle no protocol urls
				$src = rocket_add_url_protocol( $src );
			}
			//Has it been processed before?
			if ( ! in_array( $src, $this->urls ) ) {
				// Being remote is defined as not having our home url and not being in the CDN list. However if the file does not have a CSS extension, assume its a dynamic script generating CSS, so we need to web fetch it.
				if ( 0 != strpos( $src, '/' ) && ( $this->is_url_parts_remote( $src ) || 'css' !== pathinfo( parse_url( $src, PHP_URL_PATH ), PATHINFO_EXTENSION ) ) ) {
					$this->process_remote_style( $src, $media, $minify );
					if ( $minify ) {
						$this->urls[] = $src;
					}

					return;
				}

				$this->process_local_style( $src, $media, $minify );
				if ( $minify ) {
					$this->urls[] = $src;
				}
			}
		}
	}

	/**
	 * @param $url_parts
	 *
	 * @return bool
	 */
	public function is_url_parts_remote( $url_parts ) {
		if ( ! is_array( $url_parts ) ) {
			$url_parts = parse_url( $url_parts );
		}

		return isset( $url_parts['host'] ) && $url_parts['host'] !== $this->domain && ( empty( $this->cdn_domains ) || ( ! empty( $this->cdn_domains ) && ! in_array( $url_parts['host'], $this->cdn_domains ) ) );
	}

	/**
	 * @param string $src
	 *
	 * @param        $media
	 */
	private function process_remote_style( $src, $media, $minify ) {
		if ( $minify ) {
			// Check item cache
			$item_cache_id = [ md5( $src ) ];
			$item_cache_id = apply_filters( 'rocket_async_css_get_remote_style_cache_id', $item_cache_id );
			$item_cache    = $this->get_cache_fragment( $item_cache_id );
			// Only run if there is no item cache
			if ( empty( $item_cache ) ) {
				$file = $this->remote_fetch( $src );
				// Catch Error
				if ( ! empty( $file ) ) {
					$css_part = $this->minify_remote_file( $src, $file );
					$this->update_cache_fragment( $item_cache_id, $css_part );
					$this->css[ $media ] .= $css_part;
				}
			} else {
				$this->css[ $media ] .= apply_filters( 'rocket_aync_css_process_remote_style', $item_cache, $src, $media );
			}
		}
	}

	/**
	 * @param $url
	 *
	 * @return bool|string
	 */
	public function remote_fetch( $url, $cache = true ) {
		if ( isset( $this->remote_url_cache[ $url ] ) && $cache ) {
			return $this->remote_url_cache[ $url ];
		}

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
			if ( $cache ) {
				if ( ! isset( $this->remote_url_cache[ $url ] ) ) {
					$this->remote_url_cache[ $url ] = $file['body'];
				}
				$hash = md5( $file['body'] );
				if ( ! isset( $this->file_hash_cache[ $hash ] ) ) {
					$this->file_hash_cache[ $hash ] = $url;
				}
			}

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
		$css_part = $this->minify_css( $css, [], $url );
		remove_filter( 'rocket_url_no_dots', '__return_false', PHP_INT_MAX );

		return apply_filters( 'rocket_async_css_minify_remote_file', $css_part, $url );
	}

	/**
	 * @param       $css
	 * @param array $options
	 * @param bool $local
	 *
	 * @return mixed|string
	 */
	public function minify_css( $css, $options, $url ) {
		$css = apply_filters( 'rocket_async_css_before_minify', $css, $url );
		$css = $this->process_css( $css, $url );

		if ( ! class_exists( 'Minify_CSS' ) && $this->plugin->wp_filesystem->is_file( WP_ROCKET_PATH . 'min/lib/Minify/Loader.php' ) ) {
			require_once( WP_ROCKET_PATH . 'min/lib/Minify/Loader.php' );
			\Minify_Loader::register();
		}
		if ( class_exists( 'Minify_CSS' ) ) {
			if ( ! apply_filters( 'rocket_async_css_do_minify', true, $css, $url ) ) {
				$options['compress'] = false;
			}

			return \Minify_CSS::minify( $css, $options );
		}
		if ( ! empty( $options['prependRelativePath'] ) ) {
			$css = \Minify_CSS_UriRewriter::prepend( $css, $options['prependRelativePath'] );
		}
		if ( apply_filters( 'rocket_async_css_do_minify', true, $css, $url ) ) {
			if ( class_exists( '\MatthiasMullie\Minify\CSS' ) ) {
				$minify = new \MatthiasMullie\Minify\CSS( $css );
				$css    = $minify->minify();
				$css    = preg_replace( '/\n?\/\*(!|.*?@license|.*?@preserve).*?\*\/\n?/s', '', $css );
				$css    = preg_replace( '/\/\*.*?\*\//s', '', $css );
			} else {
				$css = rocket_minify_inline_css( $css );
			}

		}

		return apply_filters( 'rocket_async_css_after_minify', $css );
	}

	public function process_css( $css, $url ) {
		$css = $this->process_css_callback( self::IMPORT_REGEX, $css, $url, function ( $matches ) {
			return ! empty( $matches ) && ! empty( $matches[1] );
		}, function ( $matches ) {
			$new_matches = [];
			foreach ( $matches[1] as $index => $match ) {
				$new_matches[ $index ] = $match;
			}

			return $new_matches;
		}, 'parse_css_imports' );
		$css = apply_filters( 'rocket_async_css_after_parse_css_imports', $css, $url );

		$css = $this->lazy_load_fonts( $css, $url );
		$css = apply_filters( 'rocket_async_css_after_lazy_load_fonts', $css, $url );

		$css = $this->process_css_urls( $css, $url, 'download_remote_files' );
		$css = apply_filters( 'rocket_async_css_after_download_files', $css, $url );

		$css = $this->process_css_urls( $css, $url, 'process_local_files' );
		$css = apply_filters( 'rocket_async_css_after_process_local_files', $css, $url );

		return $css;
	}

	/**
	 * @param $regex
	 * @param $css
	 * @param $url
	 * @param $validate_callback
	 * @param $loop_callback
	 * @param $callback
	 *
	 * @return mixed
	 */
	private function process_css_callback( $regex, $css, $url, $validate_callback, $loop_callback, $callback ) {
		preg_match_all( $regex, $css, $matches );

		if ( ! is_callable( $validate_callback ) ) {
			$validate_callback = [ $this, $validate_callback ];
		}
		if ( ! is_callable( $loop_callback ) ) {
			$loop_callback = [ $this, $loop_callback ];
		}
		if ( ! is_callable( $callback ) ) {
			$callback = [ $this, $callback ];
		}

		//Ensure there are matches
		if ( call_user_func( $validate_callback, $matches ) ) {
			$this->maybe_fetch_cache();
			foreach ( call_user_func( $loop_callback, $matches ) as $index => $match ) {
				if ( 0 === strpos( $match, 'data:' ) ) {
					continue;
				}
				$match       = trim( $match, '"' . "'" );
				$fixed_match = $match;
				$url_parts   = $this->get_url_parts( $fixed_match, $url );
				$css         = call_user_func( $callback, $matches, $match, $index, $url_parts, $css, $url );
			}
		}

		return $css;
	}

	/**
	 * @param $url
	 *
	 * @return mixed
	 */
	public function get_url_parts( $url, $source = null ) {
		if ( 0 === strpos( $url, '//' ) ) {
			//Handle no protocol urls
			$url = rocket_add_url_protocol( $url );
		}
		$url_parts = parse_url( $url );
		if ( empty( $url_parts['host'] ) && $source ) {
			$fixed_match = \phpUri::parse( $source )->join( $url );
			$url_parts   = parse_url( $fixed_match );
		}

		return $url_parts;
	}

	/**
	 * @param $css
	 * @param $url
	 *
	 * @return mixed
	 */
	public function lazy_load_fonts( $css, $url ) {
		preg_match_all( '/@font-face\s*({.*})/sU', $css, $matches, PREG_SET_ORDER );
		if ( ! empty( $matches ) ) {
			$this->maybe_fetch_cache();
			foreach ( $matches as $font_face_match ) {
				preg_match_all( '/([\w\-]+)\s*:\s*(.*)(?:;|\s*})/sU', $font_face_match[1], $css_statement_matches, PREG_SET_ORDER );
				$css_rules = [];
				if ( ! empty( $css_statement_matches ) ) {
					foreach ( $css_statement_matches as $css_statement_match ) {
						$css_rules[ $css_statement_match[1] ] = $css_statement_match[2];
					}
					$chars  = "' " . '"';
					$srcses = [];
					if ( isset( $css_rules['src'] ) ) {
						preg_match_all( self::URL_SRC_REGEX, $css_rules['src'], $src_matches );
						if ( ! empty( $src_matches ) && ! empty( $src_matches[1] ) ) {
							$srcses = array_values( $src_matches[2] );
						}
					}
					$font_display = apply_filters( 'rocket_async_css_font_display', 'auto', array_merge( $css_rules, [ 'font-family' => ltrim( rtrim( $css_rules['font-family'], $chars ), $chars ) ] ), $url, $srcses );
					if ( 'auto' !== $font_display ) {
						$css_rules['font-display'] = $font_display;
						$css_rule_strings          = [];
						foreach ( $css_rules as $rule => $value ) {
							$value              = rtrim( $value );
							$value              = rtrim( $value, ';' );
							$css_rule_strings[] = "{$rule}: $value;";
						}
						$new_font_face = implode( '', $css_rule_strings );
						$css           = str_replace( $font_face_match[1], "{{$new_font_face}}", $css );
					}
				}
			}
		}

		return $css;
	}

	/**
	 * @param $css
	 * @param $url
	 * @param $callback
	 *
	 * @return mixed
	 */
	public function process_css_urls( $css, $url, $callback ) {
		return $this->process_css_callback( self::URL_SRC_REGEX, $css, $url, function ( $matches ) {
			return ! empty( $matches ) && ! empty( $matches[1] );
		}, function ( $matches ) {
			$new_matches = [];
			foreach ( $matches[2] as $index => $match ) {
				if ( empty( $match ) ) {
					$match = $matches[1][ $index ];
				}
				$new_matches[ $index ] = $match;
			}

			return $new_matches;
		}, $callback );
	}

	/**
	 * @param $cache_id
	 * @param $data
	 */
	private function update_cache_fragment( $cache_id, $data ) {
		if ( apply_filters( 'rocket_async_css_save_cache', true ) ) {
			$this->cache_manager->get_store()->update_cache_fragment( $cache_id, $data );
		}
	}

	/**
	 * @param string $href
	 *
	 * @param        $media
	 *
	 * @internal param DOMElement $tag
	 */
	private function process_local_style(
		$href, $media, $minify
	) {
		if ( 0 == strpos( $href, '/' ) ) {
			$href = $this->home . $href;
		}
		// Break up url
		$href      = $this->strip_cdn( $href );

		// Remove query strings
		$url_parts = parse_url( $href );

		if ( isset( $url_parts['query'] ) ) {
			unset( $url_parts['query'] );
		}
		if ( isset( $url_parts['hash'] ) ) {
			unset( $url_parts['hash'] );
		}

		$href = http_build_url( $url_parts );

		// Check item cache
		$item_cache_id = [ md5( $href ) ];
		$item_cache_id = apply_filters( 'rocket_async_css_get_local_style_cache_id', $item_cache_id );
		$item_cache    = $this->get_cache_fragment( $item_cache_id );
		// Only run if there is no item cache
		if ( empty( $item_cache ) ) {
			if ( $minify ) {
				$file                = $this->get_content( str_replace( $this->home, ABSPATH, $href ) );
				$css_part            = $file;
				$css_part            = $this->minify_css( $css_part, [ 'prependRelativePath' => trailingslashit( dirname( $url_parts['path'] ) ) ], $href );
				$this->css[ $media ] .= $css_part;
				$this->update_cache_fragment( $item_cache_id, $css_part );

				return;
			}
			$css = $this->get_content( $this->get_local_file_from_url( $href ) );
			$this->hash_local_files( $css, $href );
		} else {
			if ( $minify ) {
				$this->css[ $media ] .= $item_cache;
			}
		}
	}

	/**
	 * @param $url
	 *
	 * @return \http\Url|string
	 */
	public function strip_cdn( $url ) {
		$url_parts           = parse_url( $url );
		$url_parts['host']   = $this->domain;
		$url_parts['scheme'] = is_ssl() ? 'https' : 'http';
		$url                 = http_build_url( $url_parts );

		return $url;
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
	 * @param $url
	 *
	 * @return array|\http\Url|mixed|string
	 */
	private function get_local_file_from_url( $url ) {
		if ( is_array( $url ) ) {
			if ( isset( $url['query'] ) ) {
				unset( $url['query'] );
			}
			if ( isset( $url['fragment'] ) ) {
				unset( $url['fragment'] );
			}
			$url = http_build_url( $url );
		}

		$url = $this->strip_cdn( $url );
		$url = str_replace( $this->home, ABSPATH, $url );

		return $url;
	}

	/**
	 * @param $css
	 * @param $url
	 */
	private function hash_local_files( $css, $url ) {
		$this->process_css_callback( self::IMPORT_REGEX, $css, $url, function ( $matches ) {
			return ! empty( $matches ) && ! empty( $matches[1] );
		}, function ( $matches ) {
			$new_matches = [];
			foreach ( $matches[1] as $index => $match ) {
				$new_matches[ $index ] = $match;
			}

			return $new_matches;
		}, 'hash_local_css_imports' );
		$this->process_css_urls( $css, $url, 'hash_local_file_references' );
	}

	/**
	 * @param $tag
	 */
	private function process_inline_style(
		$tag, $media, $minify
	) {
		if ( $minify ) {
			// Check item cache
			$item_cache_id = [ md5( $tag->textContent ) ];
			$item_cache_id = apply_filters( 'rocket_async_css_get_inline_style_cache_id', $item_cache_id );
			$item_cache    = $this->get_cache_fragment( $item_cache_id );
			// Only run if there is no item cache
			if ( empty( $item_cache ) ) {
				// Remove any conditional comments for IE that somehow was put in the script tag
				$css_part = preg_replace( '/(?:<!--)?\[if[^\]]*?\]>.*?<!\[endif\]-->/is', '', $tag->textContent );
				//Minify ?
				$css_part = $this->minify_css( $css_part, [], home_url( $_SERVER['REQUEST_URI'] ) );
				$this->update_cache_fragment( $item_cache_id, $css_part );
			} else {
				$css_part = $item_cache;
			}
			//Add inline CSS to buffer
			$this->css[ $media ] .= $css_part;

			return;
		}

		$this->hash_local_files( $tag->textContent, home_url( $_SERVER['REQUEST_URI'] ) );
	}

	/**
	 * @param $filename
	 *
	 * @return array
	 */
	private function write_cache(
		$filenames
	) {
		if ( empty( $this->cache ) ) {
			$data = [ 'filename' => $filenames, 'file_hash_cache' => $this->file_hash_cache ];
			foreach ( $filenames as $media => $filename ) {
				$this->put_content( $filename, apply_filters( 'rocket_async_css_output', $this->css[ $media ], $media ) );
				$data['href'][ $media ] = get_rocket_cdn_url( set_url_scheme( str_replace( WP_CONTENT_DIR, WP_CONTENT_URL, $filename ) ), [
					'all',
					'css',
					'js',
					'css_and_js',
				] );
			}

			$this->update_cache_fragment( $this->get_cache_id(), $data );

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
	 * @param $hrefs
	 */
	private function add_main_styles(
		$hrefs
	) {
		// CustomEvent polyfill
		$external_tag = $this->document->createElement( 'script' );
		$external_tag->setAttribute( 'data-no-minify', '1' );
		$js = 'try{var ce=new window.CustomEvent("test");ce.preventDefault();if(!0!==ce.defaultPrevented)throw Error("Could not prevent default");}catch(e){var CustomEvent=function(c,a){a=a||{bubbles:!1,cancelable:!1,detail:void 0};var b=document.createEvent("CustomEvent");b.initCustomEvent(c,a.bubbles,a.cancelable,a.detail);var d=b.preventDefault;b.preventDefault=function(){d.call(this);try{Object.defineProperty(this,"defaultPrevented",{get:function(){return!0}})}catch(f){this.defaultPrevented=!0}};return b};CustomEvent.prototype=
window.Event.prototype;window.CustomEvent=CustomEvent};';
		$external_tag->appendChild( $this->document->createTextNode( $js ) );
		$this->document->getElementsByTagName( 'head' )->item( 0 )->appendChild( $external_tag );

		// loadCSS
		$js_tag = $this->document->createElement( 'script' );
		$js_tag->setAttribute( 'data-no-minify', '1' );
		$preloader_js = '';
		if ( ! apply_filters( 'rocket_async_css_preloader_enabled', false ) ) {
			$preloader_js .= 'window.preloader_loaded = true;';
			$preloader_js .= 'window.dispatchEvent(new CustomEvent("PreloaderDestroyed"));';
		}
		if ( ! apply_filters( 'rocket_async_css_preloader_event_bypass', false ) ) {
			$preloader_js .= 'window.preloader_event_registered = true;';
		}
		$do_blocking = apply_filters( 'rocket_async_css_do_blocking', false );
		if ( ! $do_blocking ) {
			$js = '(function(h){var d=function(d,e,n){function k(a){if(b.body)return a();setTimeout(function(){k(a)})}function f(){a.addEventListener&&a.removeEventListener("load",f);a.media=n||"all"; (window.addEventListener || window.attachEvent)((!window.addEventListener ? "on" : "") + "load", function(){window.css_loaded=true; window.dispatchEvent(new CustomEvent("CSSLoaded"));' . $preloader_js . '}, null);}var b=h.document,a=b.createElement("link"),c;if(e)c=e;else{var l=(b.body||b.getElementsByTagName("head")[0]).childNodes;c=l[l.length-1]}var m=b.styleSheets;a.rel="stylesheet";a.href=d;a.media="only x";k(function(){c.parentNode.insertBefore(a,e?c:c.nextSibling)});var g=function(b){for(var c=a.href,d=m.length;d--;)if(m[d].href===
c)return b();setTimeout(function(){g(b)})};a.addEventListener&&a.addEventListener("load",f);a.onloadcssdefined=g;g(f);return a};"undefined"!==typeof exports?exports.loadCSS=d:h.loadCSS=d})("undefined"!==typeof global?global:this);';
		}
		if ( $do_blocking ) {
			$js = '(function(){ ' . $preloader_js . '})();';
		}
		$js_tag->appendChild( $this->document->createTextNode( $js ) );
		if ( ! $do_blocking ) {
			$this->document->getElementsByTagName( 'head' )->item( 0 )->appendChild( $js_tag );
		}

		foreach ( $hrefs as $media => $href ) {
			if ( ! $do_blocking ) {
				$js           = "loadCSS(" . wp_json_encode( $href ) . ',  document.getElementsByTagName("head")[0].childNodes[ document.getElementsByTagName("head")[0].childNodes.length-1], ' . "'{$media}'" . ');';
				$external_tag = $this->document->createElement( 'script' );
				$external_tag->appendChild( $this->document->createTextNode( $js ) );
				$external_tag->setAttribute( 'data-no-minify', '1' );
			}
			if ( $do_blocking ) {
				$external_tag = $this->document->createElement( 'link' );
				$external_tag->setAttribute( 'rel', 'stylesheet' );
				$external_tag->setAttribute( 'href', $href );
				$external_tag->setAttribute( 'media', $media );
			}
			$this->document->getElementsByTagName( 'head' )->item( 0 )->appendChild( $external_tag );
		}
		if ( $do_blocking ) {
			$this->document->getElementsByTagName( 'head' )->item( 0 )->appendChild( $js_tag );
		}

	}

	/**
	 *
	 */
	private function fix_old_libxml() {
		// Hack to fix a bug with some libxml versions
		$body       = $this->document->getElementsByTagName( 'body' )->item( 0 );
		$body_class = $body->getAttribute( 'class' );
		if ( empty( $body_class ) ) {
			$body->setAttribute( 'class', implode( ' ', get_body_class() ) );
		}
	}

	/**
	 * @param $buffer
	 *
	 * @return array
	 */
	public function process_ie_conditionals( $buffer ) {
		$this->disable_minify_overrides();
		if ( $this->do_process_page( $buffer ) ) {
			$buffer = $this->extract_ie_conditionals( $buffer );
		}
		add_filter( 'pre_get_rocket_option_minify_css', '__return_zero' );

		return $buffer;
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
			$conditionals[] = $conditional;
			$buffer         = preg_replace( '/<!--\[if[^\]]*?\]>.*?<!\[endif\]-->/is', '<!-- WP_ROCKET_CONDITIONAL -->', $buffer, 1 );
		}

		$this->ie_conditionals = $conditionals;

		return $buffer;
	}

	/**
	 * @return DOMDocument
	 */
	public function get_document() {
		return $this->document;
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
	public function get_files() {
		return $this->files;
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

	/**
	 * @return Util
	 */
	public function get_util() {
		return $this->util;
	}

	/**
	 * @return void
	 */
	public function uninstall() {
		// noop
	}

	/**
	 * @return string
	 */
	public function get_transient_prefix() {
		return static::TRANSIENT_PREFIX;
	}

	/**
	 * @param $css
	 * @param $url
	 *
	 * @return mixed
	 */
	private function download_remote_files( $matches, $match, $index, $match_parts, $css, $url ) {
		$this->maybe_fetch_cache();
		if ( $this->is_url_parts_local( $match_parts ) ) {
			return $css;
		}
		$fixed_match = http_build_url( $this->get_url_parts( $match, $url ) );
		$data        = $this->remote_fetch( $fixed_match );
		if ( ! empty( $data ) ) {
			$content_hash = md5( $data );
			$match_parts  = parse_url( $this->file_hash_cache[ $content_hash ] );
			$info         = pathinfo( $match_parts['path'] );
			if ( empty( $match_parts['port'] ) ) {
				$match_parts['port'] = '';
			}
			if ( empty( $match_parts['scheme'] ) ) {
				$match_parts['scheme'] = 'http';
			}

			$hash      = md5( $match_parts['scheme'] . '://' . $info['dirname'] . ( ! empty( $match_parts['port'] ) ? ":{$match_parts['port']}" : '' ) . '/' . $info['filename'] );
			$filename  = $this->get_cache_path() . $hash . '.' . $info['extension'];
			$final_url = parse_url( get_rocket_cdn_url( set_url_scheme( str_replace( WP_CONTENT_DIR, WP_CONTENT_URL, $filename ) ), [
				'all',
				'css',
				'js',
				'css_and_js',
				'images',
			] ), PHP_URL_PATH );
			if ( ! $this->get_wp_filesystem()->is_file( $filename ) ) {
				$this->put_content( $filename, $data );
			}

			$css_part = str_replace( $match, $final_url, $matches[0][ $index ] );
			$css      = str_replace( $matches[0][ $index ], $css_part, $css );
		}

		return $css;
	}

	/**
	 * @param $url_parts
	 *
	 * @return bool
	 */
	public function is_url_parts_local( $url_parts ) {
		if ( ! is_array( $url_parts ) ) {
			$url_parts = parse_url( $url_parts );
		}

		return ( ! isset( $url_parts['host'] ) && isset( $url_parts['path'] ) ) || $url_parts['host'] === $this->domain || ( ! empty( $this->cdn_domains ) && in_array( $url_parts['host'], $this->cdn_domains ) );
	}

	/**
	 * @param $css
	 * @param $url
	 *
	 * @return mixed
	 */
	private function process_local_files( $matches, $match, $index, $match_parts, $css, $url ) {
		if ( $this->is_url_parts_remote( $match ) ) {
			return $css;
		}


		$file = $this->get_local_file_from_url( $match_parts );
		$hash = null;

		if ( $this->plugin->wp_filesystem->is_file( $file ) ) {
			$hash = md5_file( $file );
		}

		if ( $hash && isset( $this->file_hash_cache[ $hash ] ) ) {
			if ( ! isset( $this->remote_url_cache[ $this->file_hash_cache[ $hash ] ] ) ) {
				$match_parts = $this->get_url_parts( $this->file_hash_cache[ $hash ], $url );
			}
		}

		$do_cdn = home_url( $_SERVER['REQUEST_URI'] ) === $url && ! doing_filter( 'rocket_buffer' );

		if ( ! $do_cdn ) {
			unset( $match_parts['scheme'] );
			unset( $match_parts['host'] );
		}

		$final_url = http_build_url( $match_parts );

		if ( $do_cdn ) {
			$final_url = get_rocket_cdn_url( $final_url, [ 'images', 'all' ] );
		}

		$this->file_hash_cache[ $hash ] = $final_url;

		$css_part = str_replace( $match, $final_url, $matches[0][ $index ] );
		$css      = str_replace( $matches[0][ $index ], $css_part, $css );

		return $css;
	}

	/**
	 * @param $css
	 * @param $url
	 *
	 * @return mixed
	 */
	private function hash_local_file_references( $matches, $match, $index, $match_parts, $css, $url ) {
		if ( $this->is_url_parts_remote( $match_parts ) ) {
			return $css;
		}

		$file = $this->get_local_file_from_url( $match_parts );
		$hash = null;

		if ( $this->plugin->wp_filesystem->is_file( $file ) ) {
			$hash = md5_file( $file );
		}

		if ( ! $hash ) {
			return $css;
		}

		if ( isset( $this->file_hash_cache[ $hash ] ) && ! $this->is_url_parts_local( $match_parts ) ) {
			unset( $this->file_hash_cache[ $hash ] );
		}

		unset( $match_parts['scheme'] );
		unset( $match_parts['host'] );
		$final_url = http_build_url( $match_parts );

		if ( ! isset( $this->file_hash_cache[ $hash ] ) ) {
			$this->file_hash_cache[ $hash ] = $final_url;
		}

		return $css;
	}

	/**
	 * @param $css
	 * @param $local
	 *
	 * @return mixed
	 */
	private function hash_local_css_imports( $matches, $match, $index, $match_parts, $css, $url ) {
		// Is this a URL? If so replace with ABSPATH
		$path          = $this->get_local_file_from_url( $match );
		$imported_data = $this->get_content( $path );
		// Process css to find other files
		$this->hash_local_files( $imported_data, $match );

		return $css;
	}

	/**
	 * @param $css
	 * @param $local
	 *
	 * @return mixed
	 */
	private function parse_css_imports( $matches, $match, $index, $match_parts, $css, $url ) {
		if ( $this->is_url_parts_remote( $match_parts ) ) {
			$imported_data = $this->remote_fetch( $match );
		} else {
			$match = $this->strip_cdn( $match );
			// Is this a URL? If so replace with ABSPATH
			$path          = str_replace( $this->home, untrailingslashit( ABSPATH ), $match );
			$imported_data = $this->get_content( $path );
		}
		// Process css to minify it, passing the path of the found file
		$imported_data = $this->minify_css( $imported_data, [], $match );
		// Replace match wth fetched css
		$css = str_replace( $matches[0][ $index ], $imported_data, $css );

		return $css;
	}
}
