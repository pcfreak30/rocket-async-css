<?php


namespace Rocket\Async\CSS\Integration;


use ComposePress\Core\Abstracts\Component;

class DiviBooster extends Component {
	private $path;

	/**
	 *
	 */
	public function init() {
		add_action( 'activate_divi-booster/divi-booster.php', [ $this, 'plugin_toggle' ] );
		add_action( 'deactivate_divi-booster/divi-booster.php', [ $this, 'plugin_toggle' ] );
		$this->hooks();
	}

	private function hooks() {
		if ( function_exists( 'divibooster_load_settings' ) ) {
			add_filter( 'option_' . BOOSTER_SLUG_OLD, [ $this, 'override' ] );
			add_filter( 'default_option_' . BOOSTER_SLUG_OLD, [ $this, 'override' ] );
			add_action( 'rocket_async_css_deactivate', [ $this, 'update' ] );
			add_action( 'rocket_async_css_activate', [ $this, 'update' ] );
			$settings = get_option( BOOSTER_SLUG_OLD );
			if ( ! empty( $settings ) && is_array( $settings ) && isset( $settings['fixes'], $settings['fixes']['124-fix-divi-anchor-link-scrolling'] ) && $settings['fixes']['124-fix-divi-anchor-link-scrolling']['enabled'] ) {
				add_action( 'rocket_async_css_preloader_event_bypass', '__return_true' );
			}

			$this->path = trailingslashit( '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . $this->plugin->slug ) . 'overrides/divi-booster/124-fix-divi-anchor-link-scrolling';

		}
	}

	public function override( $settings ) {
		if ( empty( $settings ) || ! is_array( $settings ) || ! isset( $settings['fixes'], $settings['fixes']['124-fix-divi-anchor-link-scrolling'] ) ) {
			return $settings;
		}
		if ( doing_action( 'deactivate_divi-booster/divi-booster.php' ) || doing_action( 'rocket_async_css_deactivate' ) ) {
			return $settings;
		}
		if ( ! doing_action( 'booster_update' ) && ! doing_action( 'init' ) && ! doing_action( 'divibooster_settings_page_init' ) ) {
			return $settings;
		}
		$settings['fixes'][ $this->path ] = $settings['fixes']['124-fix-divi-anchor-link-scrolling'];
		unset( $settings['fixes']['124-fix-divi-anchor-link-scrolling'] );

		return $settings;
	}

	public function plugin_toggle() {
		$this->hooks();
		$this->update();
	}

	public function update() {
		global $wtfdivi;
		$old = get_option( BOOSTER_VERSION_OPTION );
		$new = BOOSTER_VERSION;
		do_action( 'booster_update', $wtfdivi, $old, $new );
	}
}