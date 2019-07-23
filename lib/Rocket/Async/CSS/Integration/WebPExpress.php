<?php


namespace Rocket\Async\CSS\Integration;


use ComposePress\Core\Abstracts\Component;
use Rocket\Async\CSS;
use WebPExpress\AlterHtmlImageUrls;
use WebPExpress\Option;

/**
 * Class WebPExpress
 *
 * @package Rocket\Async\CSS\Integration
 * @property CSS $plugin
 */
class WebPExpress extends Component {

	/**
	 * @var bool
	 */
	private $conditional = false;

	/**
	 * @var AlterHtmlImageUrls
	 */
	private $image_replace;
	/**
	 * @var bool
	 */
	private $webp_express_available = false;

	/**
	 *
	 */
	public function init() {
		if ( class_exists( '\WebPExpress\Config' ) && Option::getOption( 'webp-express-alter-html', false ) ) {
			$this->webp_express_available = true;
			$options                      = json_decode( Option::getOption( 'webp-express-alter-html-options', null ), true );
			if ( 'url' === Option::getOption( 'webp-express-alter-html-replacement' ) && $options['only-for-webp-enabled-browsers'] ) {
				$this->conditional = true;
			}

			if ( ! defined( 'WEBPEXPRESS_PLUGIN_DIR' ) ) {
				return;
			}

			$autoload = WEBPEXPRESS_PLUGIN_DIR . '/vendor/autoload.php';
			if ( ! $this->plugin->wp_filesystem->is_file( $autoload ) ) {
				return;
			}

			require_once $autoload;

			if ( ! class_exists( '\WebPExpress\AlterHtmlImageUrls' ) ) {
				return;
			}

			$this->image_replace = new AlterHtmlImageUrls;

			add_action( 'rocket_async_css_webp_clear_minify_file_cache', [ $this, 'clear_minify_file_cache' ] );
			if ( $this->is_webp_enabled() ) {
				add_filter( 'rocket_async_css_webp_enabled', [ $this, 'is_webp_enabled' ] );
				add_filter( 'rocket_async_css_after_process_local_files', [ $this, 'maybe_process' ], 10, 2 );
				add_filter( 'rocket_async_css_webpexpress_process', [ $this, 'process_url' ] );
				add_filter( 'rocket_async_css_process_responsive_image', '\WebPExpress\AlterHtmlInit::alterHtml' );
				foreach ( [ 'local', 'remote', 'inline' ] as $type ) {
					add_filter( "rocket_async_css_get_{$type}_style_cache_id", [ $this, 'modify_fragment_cache_key' ] );
				}
				add_filter( 'rocket_async_css_get_cache_id', [ $this, 'modify_page_cache_key' ] );
				add_filter( 'rocket_async_css_get_cache_filename', [ $this, 'modify_page_cache_filename' ] );
			}
		}
	}

	/**
	 * @return bool
	 */
	public function is_webp_enabled() {
		return ( $this->conditional && isset( $_SERVER['HTTP_ACCEPT'] ) && false !== strpos( $_SERVER['HTTP_ACCEPT'], 'image/webp' ) ) || ! $this->conditional;
	}

	public function clear_minify_file_cache( $url ) {
		$key = [ md5( $url ) ];
		$key = $this->modify_page_cache_key( $key );
		$this->plugin->cache_manager->get_store()->delete_cache_branch( $key );
	}

	/**
	 * @param $key
	 *
	 * @return array
	 */
	public function modify_page_cache_key( $key ) {
		if ( 2 === count( $key ) ) {
			$key[] = 'webp';
		} else {
			array_splice( $key, count( $key ) - 2, 0, [ 'webp' ] );
		}

		return $key;
	}

	public function modify_page_cache_filename( $filename ) {
		array_splice( $filename, count( $filename ) - 1, 0, [ 'webp' ] );

		return $filename;
	}

	/**
	 * @param $cache_id
	 *
	 * @return array
	 */
	public function modify_fragment_cache_key( $cache_id ) {
		$cache_id[] = 'webp';

		return $cache_id;
	}

	/**
	 * @param      $css
	 * @param null $url
	 *
	 * @return mixed
	 */
	public function maybe_process( $css, $url = null ) {
		if ( ! $url ) {
			$url = home_url( $_SERVER['REQUEST_URI'] );
		}

		if ( $this->is_webp_express_available() && $this->is_webp_enabled() ) {
			$css = $this->process( $css, $url );
		}

		return $css;
	}

	/**
	 * @return bool
	 */
	public function is_webp_express_available() {
		return $this->webp_express_available;
	}

	/**
	 * @param $css
	 * @param $url
	 *
	 * @return mixed
	 */
	private function process( $css, $url ) {

		add_filter( 'upload_dir', [ $this, 'override_upload_dir' ] );
		$css = $this->plugin->process_css_urls( $css, $url, [ $this, 'process_webp' ] );
		remove_filter( 'upload_dir', [ $this, 'override_upload_dir' ] );

		return $css;
	}

	public function process_webp( $matches, $match, $index, $match_parts, $css, $url ) {
		if ( $this->plugin->is_url_parts_remote( $match ) ) {
			return $css;
		}

		$new_url = $this->plugin->strip_cdn( http_build_url( $match_parts ) );

		if ( ! apply_filters( 'rocket_async_css_webpexpress_do_process', $new_url ) ) {
			return $css;
		}

		$final_url = $this->image_replace->replaceUrl( $new_url );
		if ( ! $final_url ) {
			return $css;
		}

		$url_parts = $this->plugin->get_url_parts( $final_url, $url );

		$do_cdn = home_url( $_SERVER['REQUEST_URI'] ) === $url && ! doing_filter( 'rocket_buffer' );

		if ( ! $do_cdn ) {
			unset( $url_parts['scheme'] );
			unset( $url_parts['host'] );
		}

		$final_url = http_build_url( $url_parts );

		if ( $do_cdn ) {
			$final_url = get_rocket_cdn_url( $final_url, [ 'images', 'all' ] );
		}

		$css_part = str_replace( $match, $final_url, $matches[0][ $index ] );
		return str_replace( $matches[0][ $index ], $css_part, $css );
	}

	/**
	 * @param $upload
	 *
	 * @return mixed
	 */
	public function override_upload_dir( $upload ) {
		if ( false === strpos( $upload['subdir'], $this->plugin->cache_path ) ) {
			foreach ( [ 'path', 'url', 'basedir', 'baseurl' ] as $key ) {
				$upload[ $key ] = str_replace( $upload['subdir'], '', $upload[ $key ] );
				$upload[ $key ] = trailingslashit( dirname( $upload[ $key ] ) );
			}
		}

		return $upload;
	}

	private function process_url( $url ) {

		if ( preg_match( '/\.webp$/', $url ) ) {
			return $url;
		}

		if ( ! apply_filters( 'rocket_async_css_webpexpress_do_process', $url ) ) {
			return $url;
		}

		$new_url = $this->plugin->strip_cdn( http_build_url( $this->plugin->get_url_parts( $url ) ) );
		add_filter( 'upload_dir', [ $this, 'override_upload_dir' ] );
		$new_url = $this->image_replace->replaceUrl( $new_url );
		remove_filter( 'upload_dir', [ $this, 'override_upload_dir' ] );
		if ( ! empty( $new_url ) ) {
			$url = $new_url;
			$url = get_rocket_cdn_url( $url, [ 'images', 'all' ] );
		}
		return $url;
	}
}
