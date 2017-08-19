<?php


namespace Rocket\Async\CSS\Integration;


use pcfreak30\WordPress\Plugin\Framework\ComponentAbstract;

class Amp extends ComponentAbstract {

	public function init() {
		add_action( 'wp', [ $this, 'wp_action' ] );
	}

	public function wp_action() {
		if ( defined( 'AMP_QUERY_VAR' ) && function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
			remove_filter( 'rocket_buffer', [ $this->plugin, 'process_buffer' ], PHP_INT_MAX - 1 );
		}
	}
}