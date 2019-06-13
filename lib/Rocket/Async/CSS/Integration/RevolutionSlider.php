<?php


namespace Rocket\Async\CSS\Integration;


use ComposePress\Core\Abstracts\Component;

/**
 * Class RevolutionSlider
 * @package Rocket\Async\CSS\Integration
 */
class RevolutionSlider extends Component {

	/**
	 * @var bool
	 */
	private $in_slider = false;


	/**
	 *
	 */
	public function init() {
		if ( shortcode_exists( 'rev_slider' ) ) {
			remove_shortcode( 'rev_slider' );
			add_shortcode( 'rev_slider', array( $this, 'rev_slider_compatibility' ) );
			add_filter( 'rocket_async_css_lazy_load_responsive_image', [ $this, 'check_image' ], 10, 4 );
			add_action( 'revslider_modify_core_settings', [ $this, 'in_slider' ] );
			add_filter( 'revslider_add_js_delay', [ $this, 'out_slider' ], 10, 2 );
			add_filter( 'a3_lazy_load_skip_images_classes', [ $this, 'skip_class' ] );
			add_filter( 'rocket_async_css_process_responsive_image', [ $this, 'block_image' ] );
			add_filter( 'do_shortcode_tag', [ $this, 'post_process_shortcode' ], 10, 2 );
		}
	}

	/**
	 * A hack to ensure rev slider runs on winsow load
	 *
	 * @return mixed
	 */
	public function rev_slider_compatibility() {
		$output = str_replace( 'tpj(document).ready(function() {', 'tpj(window).load(function() {', call_user_func_array( 'rev_slider_shortcode', func_get_args() ) );

		return $output;
	}

	/**
	 * @param bool $value
	 * @param array $classes
	 * @param string $src
	 * @param string $image
	 *
	 * @return bool
	 */
	public function check_image( $value, $classes, $src, $image ) {
		if ( $this->in_slider || in_array( 'rev-slidebg', (array) $classes ) ) {
			$value = false;
		}

		return $value;
	}

	/**
	 *
	 */
	public function in_slider() {
		$this->in_slider = true;
	}

	/**
	 * @param $value
	 *
	 * @return mixed
	 */
	public function out_slider( $value ) {
		$this->in_slider = false;

		return $value;
	}

	public function skip_class( $classes ) {
		$classes   = array_map( 'trim', explode( ',', $classes ) );
		$classes[] = 'no-lazyload';
		$classes   = array_unique( array_filter( $classes ) );
		$classes   = implode( ',', $classes );
		return $classes;
	}

	public function block_image( $value ) {
		if ( $this->in_slider ) {
			if ( false === strpos( $value, 'no-lazyload' ) ) {
				$value = str_replace( 'class="', 'class="no-lazyload ', $value );
				$value = str_replace( "class='", "class='no-lazyload ", $value );
			}
		}
		return $value;
	}

	public function post_process_shortcode( $value, $tag ) {
		if ( 'rev_slider' === $tag ) {
			if ( false === strpos( $value, 'no-lazyload' ) ) {
				$value = str_replace( 'class="', 'class="no-lazyload ', $value );
				$value = str_replace( "class='", "class='no-lazyload ", $value );
			}
		}
		return $value;
	}
}
