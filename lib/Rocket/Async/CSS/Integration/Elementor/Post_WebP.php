<?php

namespace Rocket\Async\CSS\Integration\Elementor;

use Elementor\Core\Files\CSS\Post;

/**
 * Class Post_WebP
 * @package Rocket\Async\CSS\Integration\Elementor
 */
class Post_WebP extends Post {

	/**
	 *
	 */
	const META_KEY = '_elementor_css_webp';
	/**
	 * @var string
	 */
	private $path;
	/**
	 * @var string
	 */
	private $files_dir;

	/**
	 * @var int
	 */
	private $post_id;

	/**
	 * Post_WebP constructor.
	 *
	 * @param $post_id
	 */
	public function __construct( $post_id ) {
		$this->post_id   = $post_id;
		$this->files_dir = static::DEFAULT_FILES_DIR;

		parent::__construct( $post_id );
		$this->set_file_name( str_replace( '.css', '-webp.css', $this->get_file_name() ) );
		$this->set_path();

	}

	/**
	 *
	 */
	private function set_path() {
		$dir_path = self::get_base_uploads_dir() . $this->files_dir;

		if ( ! is_dir( $dir_path ) ) {
			wp_mkdir_p( $dir_path );
		}

		$this->path = $dir_path . $this->get_file_name();
	}

	/**
	 * @return string
	 */
	public function get_file_handle_id() {
		return 'elementor-post-' . $this->post_id . '-webp';
	}

	public function write() {
		return file_put_contents( $this->path, $this->get_content() );
	}

	public function delete() {
		if ( file_exists( $this->path ) ) {
			unlink( $this->path );
		}
		parent::delete();
	}
}
