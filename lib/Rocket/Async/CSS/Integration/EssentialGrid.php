<?php


namespace Rocket\Async\CSS\Integration;


use ComposePress\Core\Abstracts\Component;

/**
 * Class EssentialGrid
 * @package Rocket\Async\CSS\Integration
 *
 * @property $parent ComposePress\Core\Abstracts\Manager
 *
 * Since EssentialGrid doesn't have proper filters for output we have to hijack it with output buffering :(
 */
class EssentialGrid extends Component {
	/**
	 * @var bool
	 */
	private $in_essentialgrid = true;

	/**
	 *
	 */
	public function init() {

		$lazyload_enabled = $this->plugin->util->is_lazyload_enabled();
		if ( class_exists( '\Essential_Grid' ) && $lazyload_enabled ) {
			add_action( 'essgrid_output_by_posts_pre', [ $this, 'in_essentialgrid' ] );
			add_action( 'essgrid_output_by_specific_posts_pre', [ $this, 'in_essentialgrid' ] );
			add_action( 'essgrid_output_by_posts_post', [ $this, 'out_essentialgrid_buffer' ] );
			add_filter( 'essgrid_output_by_specific_posts_return', [ $this, 'out_essentialgrid' ] );
			add_filter( 'rocket_async_css_process_responsive_image', [ $this, 'block_image' ] );
			add_filter( 'a3_lazy_load_skip_images_classes', [ $this, 'skip_class' ] );
			add_filter( 'wp_get_attachment_image_src', [ $this, 'cdnify_images' ] );
		}
	}

	/**
	 * @param $result
	 * @param $tag
	 */
	public function in_essentialgrid() {
		ob_start();
	}

	public function out_essentialgrid_buffer() {
		echo $this->out_essentialgrid( ob_get_clean() );
	}

	/**
	 * @param $result
	 * @param $tag
	 */
	public function out_essentialgrid( $output ) {
		$this->in_essentialgrid = true;
		$output                 = $this->parent->get_module( 'ResponsiveImages' )->process( $output );
		$this->in_essentialgrid = false;
		return $output;
	}

	/**
	 * @param $value
	 *
	 * @return mixed
	 */
	public function block_image( $value ) {
		if ( $this->in_essentialgrid ) {
			$value = str_replace( 'class="', 'class="no-lazyload ', $value );
		}
		return $value;
	}

	/**
	 * @param $classes
	 *
	 * @return array|string
	 */
	public function skip_class( $classes ) {
		$classes   = array_map( 'trim', explode( ',', $classes ) );
		$classes[] = 'no-lazyload';
		$classes   = array_unique( $classes );
		$classes   = implode( ',', $classes );
		return $classes;
	}

	public function cdnify_images( $image ) {
		if ( ! (bool) $image ) {
			return $image;
		}

		if ( ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) || is_preview() || is_feed() ) {
			return $image;
		}

		$zones = array( 'all', 'images' );

		if ( ! (bool) get_rocket_cdn_cnames( $zones ) ) {
			return $image;
		}

		$image[0] = get_rocket_cdn_url( $image[0], $zones );

		return $image;
	}
}