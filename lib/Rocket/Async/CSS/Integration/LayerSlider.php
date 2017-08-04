<?php


namespace Rocket\Async\CSS\Integration;


class LayerSlider implements IntegrationInterface {

	private $default_skin;

	public function init() {
		if ( class_exists( 'LS_Sliders' ) ) {
			add_filter( 'layerslider_pre_parse_defaults', [ $this, 'set_default_skin' ], PHP_INT_MAX );
			add_filter( 'ls_parse_defaults', [ $this, 'enqueue_skin' ], PHP_INT_MAX );
			add_action( 'rocket_async_css_activate', [ $this, 'purge_slider_cache' ] );
			add_action( 'activate_LayerSlider/layerslider.php', [ $this, 'purge_slider_cache' ] );
		}
	}

	public function set_default_skin( $defaults ) {
		$this->default_skin             = $defaults['properties']['skin'];
		$defaults['properties']['skin'] = null;

		return $defaults;
	}

	public function enqueue_skin( $slider ) {

		if ( empty( $slider['attr']['skin'] ) ) {
			$skin = $this->default_skin;
		} else {
			$skin                   = $slider['attr']['skin'];
			$slider['attr']['skin'] = '';
		}
		wp_enqueue_style( "layerslider-skin-{$skin}", \LS_Sources::urlForSkin( $skin ) . '/skin.css' );

		return $slider;
	}

	public function purge_slider_cache() {
		\LS_Sliders::find( [ 'limit' => 0 ] );
		$sliders = \LS_Sliders::find( [ 'limit' => \LS_Sliders::count() ] );
		foreach ( $sliders as $slider ) {
			delete_transient( 'ls-slider-data-' . $slider['id'] );
		}
	}
}