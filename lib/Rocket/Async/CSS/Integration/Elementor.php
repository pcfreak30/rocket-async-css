<?php


namespace Rocket\Async\CSS\Integration;


use ComposePress\Core\Abstracts\Component;
use Elementor\Core\DynamicTags\Dynamic_CSS;
use Elementor\Core\Files\Base;
use Elementor\Core\Files\CSS\Global_CSS;
use Elementor\Core\Files\CSS\Post;
use Elementor\Core\Responsive\Files\Frontend;
use Elementor\Plugin;
use Rocket\Async\CSS\Integration\Elementor\Dynamic_CSS_WebP;
use Rocket\Async\CSS\Integration\Elementor\Frontend_WebP;
use Rocket\Async\CSS\Integration\Elementor\Global_CSS_WebP;
use Rocket\Async\CSS\Integration\Elementor\Post_WebP;
use WebPExpress\AlterHtmlImageUrls;
use WebPExpress\Option;

/**
 * Class Elementor
 * @package Rocket\Async\CSS\Integration
 */
class Elementor extends Component {

	/**
	 * @var bool
	 */
	private $conditional = false;

	/**
	 * @var null|AlterHtmlImageUrls
	 */
	private $image_replace;
	/**
	 * @var Dynamic_CSS_WebP|Frontend_WebP|Global_CSS_WebP|Post_WebP[]
	 */
	private $files = [];

	/**
	 *
	 */
	public function init() {

		if ( class_exists( '\Elementor\Plugin' ) ) {
			if ( class_exists( '\WebPExpress\Config' ) && Option::getOption( 'webp-express-alter-html', false ) ) {
				add_action( 'deleted_post', [ $this, 'on_delete_post' ] );
				add_action( 'wxr_export_skip_postmeta', [ $this, 'on_export_post_meta' ], 10, 2 );
			}

			$options = json_decode( Option::getOption( 'webp-express-alter-html-options', null ), true );
			if ( 'url' === Option::getOption( 'webp-express-alter-html-replacement' ) && $options['only-for-webp-enabled-browsers'] ) {
				$this->conditional = true;
			}

			$this->process();
		}
	}


	/**
	 *
	 */
	private function process() {
		add_action( 'elementor/files/file_name', [ $this, 'process_file' ], 10, 2 );
		add_action( 'elementor/core/files/clear_cache', [ $this, 'clear_cache' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ], 11 );
		add_action( 'rocket_async_css_webpexpress_process', [ $this, 'webp_exclude_file' ], 10, 3 );
	}

	/**
	 * @param $file_name
	 * @param Base $file
	 *
	 * @return mixed
	 */
	public function process_file( $file_name, Base $file ) {
		if ( ( $this->conditional && false !== strpos( $_SERVER['HTTP_ACCEPT'], 'image/webp' ) ) || ! $this->conditional ) {
			$new_file = $this->get_file_instance( $file, $file_name );
			if ( $new_file ) {
				$this->files[] = $new_file;
			}
		}

		return $file_name;
	}

	/**
	 * @param Base $file
	 *
	 * @param $file_name
	 *
	 * @return Dynamic_CSS_WebP|Frontend_WebP|Global_CSS_WebP|Post_WebP|null
	 */
	private function get_file_instance( Base $file, $file_name ) {
		$new_file = null;
		remove_action( 'elementor/files/file_name', [ $this, 'process_file' ] );

		if ( $file instanceof Dynamic_CSS ) {
			$new_file = new Dynamic_CSS_WebP( $file->get_post_id(), $file->get_post_id() );
		}
		if ( ! $new_file && $file instanceof Post ) {
			$new_file = new Post_WebP( $file->get_post_id() );
		}
		if ( ! $new_file && $file instanceof Frontend ) {
			$new_file = new Frontend_WebP( $file_name );
		}

		add_action( 'elementor/files/file_name', [ $this, 'process_file' ], 10, 2 );
		return $new_file;
	}

	/**
	 * @param $post_id
	 */
	public function on_delete_post( $post_id ) {
		remove_action( 'elementor/files/file_name', [ $this, 'process_file' ] );

		$css_file = new Post_WebP( $post_id );
		$css_file->delete();

		add_action( 'elementor/files/file_name', [ $this, 'process_file' ], 10, 2 );
	}

	/**
	 *
	 */
	public function clear_cache() {
		delete_post_meta_by_key( Post::META_KEY . '_webp' );
		delete_option( Global_CSS::META_KEY . '_webp' );
		delete_option( Frontend::META_KEY . '_webp' );
	}

	/**
	 * @param $skip
	 * @param $meta_key
	 *
	 * @return bool
	 */
	public function on_export_post_meta( $skip, $meta_key ) {
		if ( Post::META_KEY . '_webp' === $meta_key ) {
			$skip = true;
		}

		return $skip;
	}

	/**
	 *
	 */
	public function enqueue_styles() {
		if ( is_singular() && Plugin::$instance->db->is_built_with_elementor( get_the_ID() ) ) {

			$autoload = WEBPEXPRESS_PLUGIN_DIR . '/vendor/autoload.php';
			if ( ! $this->plugin->wp_filesystem->is_file( $autoload ) ) {
				return;
			}

			require_once $autoload;

			$this->image_replace = new AlterHtmlImageUrls;

			if ( ! class_exists( '\WebPExpress\AlterHtmlImageUrls' ) ) {
				return;
			}

			add_filter( 'wp_get_attachment_url', [ $this, 'filter_attachment_url' ], 999999, 1 );
			add_filter( 'wp_get_attachment_image_src', [ $this, 'filter_attachment_image_src' ], 999999, 1 );

			foreach ( $this->files as $file ) {
				if ( $file instanceof Frontend_WebP ) {
					$this->enqueue_frontend( $file );
					continue;
				}

				wp_dequeue_style( str_replace( '-webp', '', $file->get_file_handle_id() ) );
				$file->enqueue();
			}
		}
	}

	/**
	 * @param Frontend_WebP $file
	 */
	private function enqueue_frontend( Frontend_WebP $file ) {
		$time = $file->get_meta( 'time' );

		if ( ! $time ) {
			$file->update();
		}

		$frontend_file_url = $file->get_url();
		wp_dequeue_style( 'elementor-frontend' );
		wp_enqueue_style( 'elementor-frontend', $frontend_file_url, [] );
	}

	/**
	 * @param $url
	 *
	 * @return mixed
	 */
	public function filter_attachment_url( $url ) {
		$new_url = $this->image_replace->replaceUrl( $url );
		if ( ! empty( $new_url ) ) {
			$url = $new_url;
		}
		return $url;
	}

	/**
	 * @param $image
	 *
	 * @return mixed
	 */
	public function filter_attachment_image_src( $image ) {
		$new_url = $this->image_replace->replaceUrl( $image[0] );
		if ( ! empty( $new_url ) ) {
			$image[0] = $new_url;
		}
		return $image;
	}

	public function webp_exclude_file( $process, $css, $url ) {
		$zones = [ 'images', 'all' ];
		if ( false !== strpos( get_rocket_cdn_url( $url, $zones ), get_rocket_cdn_url( Base::get_base_uploads_url(), $zones ) ) ) {
			$process = false;
		}

		return $process;
	}
}
