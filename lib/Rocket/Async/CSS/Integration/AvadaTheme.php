<?php


namespace Rocket\Async\CSS\Integration;

use ComposePress\Core\Abstracts\Component;

class AvadaTheme extends Component {

	/**
	 *
	 */
	public function init() {
		add_action( 'after_setup_theme', [ $this, 'is_theme_active' ] );
	}

	public function is_theme_active() {
		if ( function_exists( 'avada_dynamic_css_array' ) || class_exists( 'Avada' ) || class_exists( 'FusionCore_Plugin' ) ) {
			add_action( 'avada_dynamic_css_array', [ $this, 'clean_css' ] );
			add_filter( 'avada_setting_get_media_queries_async', '__return_false' );
		}
	}

	public function clean_css( $css ) {
		foreach ( $css as $media => $styles ) {
			foreach ( $styles as $style => $statements ) {
				if ( empty( $style ) ) {
					unset( $css[ $media ][ $style ] );
				}
			}
		}

		return $css;
	}
}