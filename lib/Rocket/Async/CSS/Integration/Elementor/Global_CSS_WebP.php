<?php

namespace Rocket\Async\CSS\Integration\Elementor;

use Elementor\Core\Files\CSS\Global_CSS;
use Elementor\Scheme_Base;
use Elementor\Settings;

/**
 * Class Global_CSS_WebP
 * @package Rocket\Async\CSS\Integration\Elementor
 */
class Global_CSS_WebP extends Global_CSS {

	/**
	 * @var string
	 */
	private $path;
	/**
	 * @var string
	 */
	private $files_dir;

	/**
	 * Global_CSS_WebP constructor.
	 *
	 * @param $file_name
	 */
	public function __construct( $file_name ) {
		$file_name       = str_replace( '.css', '-webp.css', $file_name );
		$this->files_dir = static::DEFAULT_FILES_DIR;

		parent::__construct( $file_name );


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

	public function get_file_handle_id() {
		return self::FILE_HANDLER_ID . '-webp';
	}

	public function write() {
		return file_put_contents( $this->path, $this->get_content() );
	}

	protected function is_update_required() {
		$file_last_updated = $this->get_meta( 'time' );

		$schemes_last_update = get_option( Scheme_Base::LAST_UPDATED_META . '' );

		if ( $file_last_updated < $schemes_last_update ) {
			return true;
		}

		$elementor_settings_last_updated = get_option( Settings::UPDATE_TIME_FIELD );

		if ( $file_last_updated < $elementor_settings_last_updated ) {
			return true;
		}

		return false;
	}

	public function delete() {
		if ( file_exists( $this->path ) ) {
			unlink( $this->path );
		}
		parent::delete();
	}

}
