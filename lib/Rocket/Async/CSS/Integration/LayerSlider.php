<?php


namespace Rocket\Async\CSS\Integration;


/**
 * Class LayerSlider
 * @package Rocket\Async\CSS\Integration
 */
/**
 * Class LayerSlider
 * @package Rocket\Async\CSS\Integration
 */
class LayerSlider implements IntegrationInterface {

	/**
	 * @var string
	 */
	private $default_skin;
	/**
	 * @var array
	 */
	private $skins_queue;

	/**
	 *
	 */
	public function init() {
		if ( class_exists( 'LS_Sliders' ) ) {
			add_filter( 'ls_use_cache', '__return_false' );
			add_filter( 'layerslider_pre_parse_defaults', [ $this, 'set_default_skin' ], PHP_INT_MAX );
			add_filter( 'ls_parse_defaults', [ $this, 'enqueue_skin' ], PHP_INT_MAX );
			add_filter( 'wp_footer', [ $this, 'print_skins' ] );
		}
	}

	/**
	 * @param $defaults
	 *
	 * @return mixed
	 */
	public function set_default_skin( $defaults ) {
		$this->default_skin             = $defaults['properties']['skin'];
		$defaults['properties']['skin'] = null;

		return $defaults;
	}

	/**
	 * @param $slider
	 *
	 * @return mixed
	 */
	public function enqueue_skin( $slider ) {

		if ( empty( $slider['attr']['skin'] ) ) {
			$skin = $this->default_skin;
		} else {
			$skin                   = $slider['attr']['skin'];
			$slider['attr']['skin'] = '';
		}
		$this->skins_queue[] = $skin;
		wp_enqueue_style( "layerslider-skin-{$skin}", \LS_Sources::urlForSkin( $skin ) . 'skin.css' );

		return $slider;
	}

	/**
	 *
	 */
	public function print_skins() {
		foreach ( $this->skins_queue as $skin ) :
			?>
            <link href="<?php echo \LS_Sources::urlForSkin( $skin ) . 'skin.css' ?>"/>
			<?php
		endforeach;
	}
}