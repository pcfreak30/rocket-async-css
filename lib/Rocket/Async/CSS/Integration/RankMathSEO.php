<?php


namespace Rocket\Async\CSS\Integration;

use ComposePress\Core\Abstracts\Component;

class RankMathSEO extends Component {
	public function init() {
		if ( class_exists( 'RankMath' ) && \RankMath\Helper::is_module_active( 'sitemap' ) ) {
			add_action( 'parse_query', [ $this, 'check_sitemap' ], 0 );
		}
	}

	public function check_sitemap() {
		if ( get_query_var( 'sitemap' ) || get_query_var( 'xsl' ) ) {
			add_filter( 'rocket_async_css_do_request_buffer', '__return_false' );
		}
	}
}
