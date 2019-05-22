<?php

namespace Rocket\Async\CSS\Integration\Elementor;

use Elementor\Core\Responsive\Files\Frontend;

/**
 * Class Frontend_WebP
 * @package Rocket\Async\CSS\Integration\Elementor
 */
class Frontend_WebP extends Frontend {
	/**
	 *
	 */
	const META_KEY = 'elementor-custom-breakpoints-files-webp';
	/**
	 * @var string
	 */
	private $path;
	/**
	 * @var string
	 */
	private $files_dir;

	/**
	 * Frontend_WebP constructor.
	 *
	 * @param $file_name
	 * @param null $template_file
	 */
	public function __construct( $file_name, $template_file = null ) {
		$file_name       = str_replace( '.css', '-webp.css', $file_name );
		$this->files_dir = static::DEFAULT_FILES_DIR;

		parent::__construct( $file_name, $template_file );


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
	public function get_name() {
		return 'frontend-webp';
	}

	public function write() {
		return file_put_contents( $this->path, $this->get_content() );
	}
}
