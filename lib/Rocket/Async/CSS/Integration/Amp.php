<?php


namespace Rocket\Async\CSS\Integration;


use ComposePress\Core\Abstracts\Component;

class Amp extends Component {

	public function init() {
		add_action( 'wp', [ $this, 'wp_action' ] );
	}

	public function wp_action() {
		if ( defined( 'AMP_QUERY_VAR' ) && function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
			remove_filter( 'rocket_buffer', [ $this->plugin, 'process_buffer' ], PHP_INT_MAX - 1 );
		}
	}
}