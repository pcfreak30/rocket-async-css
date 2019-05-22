<?php

namespace Rocket\Async\CSS\Integration\Elementor;

use Elementor\Core\DynamicTags\Dynamic_CSS;

/**
 * Class Dynamic_CSS_WebP
 * @package Rocket\Async\CSS\Integration\Elementor
 */
class Dynamic_CSS_WebP extends Dynamic_CSS {
	/**
	 *
	 */
	const META_KEY = '_elementor_css_webp';
	/**
	 * @var int
	 */
	private $post_id;

	/**
	 * Dynamic_CSS_WebP constructor.
	 *
	 * @param $post_id
	 * @param $post_id_for_data
	 */
	public function __construct( $post_id, $post_id_for_data ) {
		$this->post_id = $post_id;
		parent::__construct( $post_id, $post_id_for_data );
	}

	/**
	 * @return string
	 */
	public function get_name() {
		return 'dynamic-css-webp';
	}

	/**
	 * @return string
	 */
	public function get_file_handle_id() {
		return 'elementor-post-dynamic-' . $this->post_id . '-webp';
	}

}
