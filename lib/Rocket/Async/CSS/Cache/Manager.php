<?php

namespace Rocket\Async\CSS\Cache;

use pcfreak30\WordPress\Cache\Store;
use ComposePress\Core\Abstracts\Component;
use Rocket\Async\CSS;

class Manager extends Component {
	/**
	 * @var \pcfreak30\WordPress\Cache\Store
	 */
	private $store;

	/**
	 * Manager constructor.
	 *
	 * @param \pcfreak30\WordPress\Cache\Store $store
	 */
	public function __construct( Store $store ) {
		$this->store = $store;
	}

	/**
	 * @return \pcfreak30\WordPress\Cache\Store
	 */
	public function get_store() {
		return $this->store;
	}

	public function init() {
		add_action( 'after_rocket_clean_domain', [ $this, 'purge_cache' ], 10, 0 );
		add_action( 'after_rocket_clean_post', [ $this, 'purge_post' ] );
		add_action( 'after_rocket_clean_term', [ $this, 'purge_term' ] );
		add_action( 'after_rocket_clean_files', [ $this, 'purge_url' ] );
		$this->store->set_prefix( CSS::TRANSIENT_PREFIX );
		$interval = 0;
		if ( function_exists( 'get_rocket_purge_cron_interval' ) ) {
			$interval = get_rocket_purge_cron_interval();
		}
		$this->store->set_expire( apply_filters( 'rocket_async_css_cache_expire_period', $interval ) );
		$this->store->set_max_branch_length( apply_filters( 'rocket_async_css_max_branch_length', 50 ) );
	}

	/**
	 *
	 */
	public function purge_cache() {
		$this->store->delete_cache_branch();
		rocket_rrmdir( $this->plugin->get_cache_path() );
	}

	/**
	 * @param \WP_Post $post
	 */
	public function purge_post( $post ) {
		$this->store->delete_cache_branch( [ 'cache', "post_{$post->ID}" ] );
	}

	/**
	 * @param \WP_Term $term
	 */
	public function purge_term( $term ) {
		$this->store->delete_cache_branch( [ 'cache', "term_{$term->term_id}" ] );
	}

	/**
	 * @param string $url
	 */
	public function purge_url( $url ) {
		$url = md5( $url );
		$this->store->delete_cache_branch( [ 'cache', "url_{$url}" ] );
	}
}