<?php


namespace Rocket\Async\CSS\Integration;


use ComposePress\Core\Abstracts\Component;
use Elementor\Core\Base\Document;

class EssentialAddonsElementor extends Component {

	public function init() {
		if ( class_exists( '\Essential_Addons_Elementor\Classes\Bootstrap' ) ) {
			add_action( 'elementor/document/after_save', [ $this, 'clear_post_cache' ] );
			add_action( 'edited_terms', [ $this, 'clear_term_cache' ] );
		}
	}

	public function clear_post_cache( Document $document ) {
		$url = EAEL_ASSET_URL . DIRECTORY_SEPARATOR . 'post-eael-' . $document->get_post()->ID . '.min.css';

		$this->plugin->cache_manager->clear_minify_url( $url );
		do_action( 'rocket_async_css_webp_clear_minify_file_cache', $url );
	}

	public function clear_term_cache( $edited_term ) {
		$url = EAEL_ASSET_URL . DIRECTORY_SEPARATOR . 'term-eael-' . $edited_term . '.min.css';

		$this->plugin->cache_manager->clear_minify_url( $url );
		do_action( 'rocket_async_css_webp_clear_minify_file_cache', $url );
	}
}
