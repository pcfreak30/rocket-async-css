<?php


namespace Rocket\Async\CSS\Integration;


use ComposePress\Core\Abstracts\Component;
use Elementor\Core\Base\Document;
use Elementor\Core\DynamicTags\Dynamic_CSS;
use Elementor\Core\Files\Base;
use Elementor\Core\Files\CSS\Base as CSS_Base_File;
use Elementor\Core\Files\CSS\Global_CSS;
use Elementor\Core\Files\CSS\Post;
use Elementor\Core\Responsive\Files\Frontend;
use Elementor\Core\Settings\Manager as Settings_Manager;
use Elementor\Core\Settings\Page\Manager as Page_Manager;
use Elementor\Element_Base;
use Elementor\Plugin;
use Elementor\Widget_Base;
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

	private $in_meta = false;

	/**
	 *
	 */
	public function init() {

		if ( class_exists( '\Elementor\Plugin' ) ) {

			add_action( 'deleted_post', [ $this, 'on_delete_post' ] );
			add_action( 'wxr_export_skip_postmeta', [ $this, 'on_export_post_meta' ], 10, 2 );
			add_filter( 'rocket_async_css_font_display', [ $this, 'process_font' ], 10, 2 );
			if ( class_exists( '\WebPExpress\Config' ) && Option::getOption( 'webp-express-alter-html', false ) ) {
				$options = json_decode( Option::getOption( 'webp-express-alter-html-options', null ), true );
				if ( 'url' === Option::getOption( 'webp-express-alter-html-replacement' ) && $options['only-for-webp-enabled-browsers'] ) {
					$this->conditional = true;
				}
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
		add_action( 'elementor/document/after_save', [ $this, 'clear_post_cache' ] );
		add_action( 'elementor/widget/render_content', [ $this, 'add_image_id_carousel' ], 10, 2 );
	}

	public function process_font( $mode, $rules ) {
		$family = $rules['font-family'];
		$family = strtolower( $family );
		if ( 'eicons' === $family ) {
			$mode = 'block';
		}
		return $mode;
	}

	public function add_image_id_carousel( $content, Widget_Base $widget ) {

		if ( 'image-carousel' === $widget->get_name() ) {
			$settings = $widget->get_settings_for_display();
			if ( ! empty( $settings['carousel'] ) ) {
				preg_match_all( '/<img[^>]+>/', $content, $matches );
				foreach ( $settings['carousel'] as $index => $attachment ) {
					if ( ! isset( $matches[0][ $index ] ) ) {
						continue;
					}
					$content = str_replace( $matches[0][ $index ], wp_get_attachment_image( $attachment['id'], 'thumbnail', [ 'class' => 'slick-slide-image' ] ), $content );
				}
			}
		}
		return $content;
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

			if ( ! defined( 'WEBPEXPRESS_PLUGIN_DIR' ) ) {
				return;
			}
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
			add_action( 'elementor/element/before_parse_css', [ $this, 'process_webp_background' ], 10, 2 );
			add_action( 'elementor/css-file/post/parse', [ $this, 'process_webp_page_background' ], 9 );

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
	 * @param $image
	 *
	 * @return mixed
	 */
	public function filter_attachment_image_src( $image ) {
		if ( apply_filters( 'rocket_async_css_webp_enabled', true, $image[0] ) ) {
			$image[0] = $this->process_url( $image[0] );
		}

		return $image;
	}

	private function process_url( $url ) {

		if ( preg_match( '/\.webp$/', $url ) ) {
			return $url;
		}

		$domain      = $this->plugin->domain;
		$cdn_domains = $this->plugin->cdn_domains;

		$url_parts = parse_url( $url );
		$cdn       = false;

		if ( in_array( $url_parts['host'], $cdn_domains ) ) {
			$url_parts['host'] = $domain;
			$url               = http_build_url( $url_parts );
			$cdn               = true;
		}

		$new_url = $this->image_replace->replaceUrl( $url );
		if ( ! empty( $new_url ) ) {
			$url = $new_url;
			if ( $cdn ) {
				$url = get_rocket_cdn_url( $url, [ 'images' ] );
			}
		}
		return $url;
	}

	public function webp_exclude_file( $process, $css, $url ) {
		$zones = [ 'images', 'all' ];
		if ( false !== strpos( get_rocket_cdn_url( $url, $zones ), get_rocket_cdn_url( Base::get_base_uploads_url(), $zones ) ) ) {
			$process = false;
		}

		return $process;
	}

	public function process_webp_background( Base $css, Element_Base $element ) {
		$settings = array_filter( $element->get_settings() );
		$settings = $this->modify_element_settings( $settings );
		foreach ( $settings as $setting => $value ) {
			$element->set_settings( $setting, $value );
		}

	}

	private function modify_element_settings( array $settings ) {
		$setting_keys_desktop = [
			'background_image'          => true,
			'background_overlay'        => true,
			'background_video_fallback' => true,
		];
		$setting_keys         = [];

		foreach ( array_keys( $setting_keys_desktop ) as $setting_key ) {
			$setting_keys ["{$setting_key}_mobile"] = true;
			$setting_keys ["{$setting_key}_tablet"] = true;
		}

		$setting_keys = array_merge( $setting_keys, $setting_keys_desktop );
		$found        = array_intersect_key( $settings, $setting_keys );
		if ( $found ) {
			foreach ( $found as $setting => $item ) {
				$item_filtered = array_filter( $item );
				if ( ! empty( $item_filtered ) ) {
					$item['url']          = $this->filter_attachment_url( $item['url'] );
					$settings[ $setting ] = $item;
				}
			}
		}
		return $settings;
	}

	/**
	 * @param $url
	 *
	 * @return mixed
	 */
	public function filter_attachment_url( $url ) {
		if ( apply_filters( 'rocket_async_css_webp_enabled', true, $url ) ) {
			$url = $this->process_url( $url );
		}
		return $url;
	}

	public function process_webp_page_background( CSS_Base_File $css_file ) {
		if ( $css_file instanceof Post ) {
			remove_filter( 'get_post_metadata', [ $this, 'process_page_meta' ] );
			$settings = get_post_meta( $css_file->get_post_id(), Page_Manager::META_KEY, true );
			if ( $settings ) {
				Settings_Manager::get_settings_managers( 'page' )->save_settings( $settings, $css_file->get_post_id() );
			}
			add_filter( 'get_post_metadata', [ $this, 'process_page_meta' ], 10, 4 );
		}


	}

	public function process_page_meta( $value, $post_id, $meta_key, $single ) {
		if ( ! $this->in_meta && $meta_key === Page_Manager::META_KEY ) {
			$this->in_meta = true;
			$settings      = get_post_meta( $post_id, Page_Manager::META_KEY, true );
			if ( is_array( $settings ) ) {
				$settings = $this->modify_element_settings( $settings );
				$value    = $single ? [ $settings ] : $settings;
			}

		}

		/** @noinspection SuspiciousAssignmentsInspection */
		$this->in_meta = false;
		return $value;
	}

	public function clear_post_cache( Document $document ) {
		$post_css = new Post_WebP( $document->get_post()->ID );

		$post_css->delete();
	}
}
