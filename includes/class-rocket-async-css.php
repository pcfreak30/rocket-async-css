<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://github.com/pcfreak30/rocket-async-css
 * @since      0.1.0
 *
 * @package    Rocket_Async_Css
 * @subpackage Rocket_Async_Css/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      0.1.0
 * @package    Rocket_Async_Css
 * @subpackage Rocket_Async_Css/includes
 * @author     Derrick Hammer <derrick@derrickhammer.com>
 */
class Rocket_Async_Css {

	/**
	 * The current version of the plugin.
	 *
	 * @since    0.1.0
	 * @access   protected
	 * @var      Rocket_Async_Css $_instance Instance singleton.
	 */
	protected static $_instance;
	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    0.1.0
	 * @access   protected
	 * @var      Rocket_Async_Css_Loader $loader Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    0.1.0
	 */
	public function __construct() {

		$this->load_dependencies();
		if ( $this->required_plugins_loaded() ) {
			$this->define_public_hooks();
			if ( is_admin() ) {
				$this->define_admin_hooks();
			}
		}
		$this->loader->run();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *     *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    0.1.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		$this->loader = new Rocket_Async_Css_Loader();

	}

	/**
	 * Hook to detect compatibility and only proceed on success
	 *
	 * @since 0.1.0
	 */
	public function required_plugins_loaded() {
		if ( did_action( 'deactivate_' . plugin_basename( plugin_dir_path( plugin_dir_path( __FILE__ ) ) . ROCKET_ASYNC_CSS_SLUG . '.php' ) ) ) {
			return false;
		}
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		$error         = false;
		$wprocket_name = 'wp-rocket/wp-rocket.php';
		if ( validate_plugin( $wprocket_name ) ) {
			$error = true;
			$this->loader->add_action( 'admin_notices', $this, '_activate_error_no_wprocket' );
		} else {
			if ( ! function_exists( 'rocket_init' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/file.php' );
				activate_plugins( $wprocket_name );
			}
		}
		if ( ! class_exists( 'DOMDocument' ) ) {
			$error = true;
			$this->loader->add_action( 'admin_notices', $this, '_activate_error_no_domdocument' );
		}
		if ( $error ) {
			deactivate_plugins( dirname( dirname( __FILE__ ) ) . '/rocket-async-css.php' );
		}

		return ! $error;
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    0.1.0
	 * @access   private
	 */
	private function define_public_hooks() {
		if ( ! is_admin() ) {
			$this->check_preloaders();
			$this->loader->add_filter( 'rocket_async_css_process_style', $this, 'exclude_wpadminbar', 10, 2 );
			$this->loader->add_filter( 'rocket_buffer', $this, 'process_css_buffer', PHP_INT_MAX - 1 );
			add_filter( 'pre_get_rocket_option_minify_css', '__return_zero' );
			add_filter( 'pre_get_rocket_option_minify_google_fonts', '__return_zero' );
		}

	}

	/**
	 * Support multiple preloaders to be extended in the future
	 *
	 * @since 0.1.0
	 */
	private function check_preloaders() {
		foreach ( glob( plugin_dir_path( __FILE__ ) . 'preloaders/*.php' ) as $file ) {
			$name = pathinfo( $file, PATHINFO_FILENAME );
			$name = str_replace( '-', '_', str_replace( 'class-', '', $name ) );

			call_user_func( array( $name, 'init' ), $this );
		}
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    0.2.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$this->loader->add_filter( 'pre_get_rocket_option_minify_google_fonts', __CLASS__, 'return_one' );

	}

	/**
	 * @return Rocket_Async_Css
	 */
	public static function get_instance() {
		if ( ! self::$_instance ) {
			self::$_instance = new self;
		}

		return self::$_instance;
	}

	/**
	 * Will search for wp-rocket and use its functions to purge
	 *
	 * @return bool
	 */
	public static function purge_cache() {
		$wprocket_name = 'wp-rocket/wp-rocket.php';
		$path          = trailingslashit( ABSPATH . PLUGINDIR ) . $wprocket_name;
		$mu_path       = trailingslashit( ABSPATH . WPMU_PLUGIN_DIR ) . $wprocket_name;
		if ( is_readable( $path ) ) {
			include_once $path;
		} else if ( is_readable( $mu_path ) ) {
			include_once $mu_path;
		} else {
			return false;
		}
		require_once( WP_ROCKET_FUNCTIONS_PATH . 'admin.php' );
		require_once( WP_ROCKET_ADMIN_PATH . 'admin.php' );
		rocket_clean_domain();

		// Remove all minify cache files
		rocket_clean_minify();

		// Generate a new random key for minify cache file
		$options                   = get_option( WP_ROCKET_SLUG );
		$options['minify_css_key'] = create_rocket_uniqid();
		$options['minify_js_key']  = create_rocket_uniqid();
		remove_all_filters( 'update_option_' . WP_ROCKET_SLUG );
		update_option( WP_ROCKET_SLUG, $options );

		rocket_dismiss_box( 'rocket_warning_plugin_modification' );

		return true;
	}

	/**
	 * Simple function since __return_one does not exist
	 *
	 * @return int
	 */
	public static function return_one() {
		return 1;
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    0.1.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     0.1.0
	 * @return    Rocket_Async_Css_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * @param $buffer
	 *
	 * @return string
	 *
	 * @since 0.1.0
	 */
	public function process_css_buffer( $buffer ) {
		//Disable minify_css option override
		remove_filter( 'pre_get_rocket_option_minify_css', '__return_zero' );
		//Ensure we actually want to do anything
		if ( get_rocket_option( 'minify_css' ) && ( ! defined( 'DONOTMINIFYCSS' ) || ! DONOTMINIFYCSS ) && ! is_rocket_post_excluded_option( 'minify_css' ) && ! is_admin() && ! is_feed() && ! is_preview() && ! empty( $buffer ) ) {
			//Custom extract method based on wp-rockets
			list( $buffer, $conditionals ) = $this->_extract_ie_conditionals( $buffer );

			// Import HTML
			$document = new DOMDocument();
			if ( ! @$document->loadHTML( $buffer ) ) {
				return $buffer;
			}
			$xpath = new DOMXpath( $document );
			$head  = $document->getElementsByTagName( 'head' )->item( 0 );
			$tags  = array();
			$urls  = array();
			//Get home URL
			$home = home_url();
			// Get our domain
			$domain = parse_url( $home, PHP_URL_HOST );
			//Prepare excluded CSS from options page
			$excluded_css = implode( '|', get_rocket_exclude_css() );
			$excluded_css = str_replace( '//' . $domain, '', $excluded_css );
			//Use XPATH to query
			foreach ( $xpath->query( '((//style|//STYLE)|(//link|//LINK)[@rel="stylesheet"])' ) as $tag ) {

				/** @var DOMElement $tag */
				//Get all attributes
				$name  = strtolower( $tag->tagName );
				$rel   = $tag->getAttribute( 'rel' );
				$media = $tag->getAttribute( 'media' );
				$href  = $tag->getAttribute( 'href' );
				$type  = 'all';
				//Skip if data-no-minify if found
				if ( 0 < strlen( trim( $tag->getAttribute( 'data-no-minify' ) ) ) ) {
					continue;
				}

				if ( 'link' == $name ) {
					if ( 'stylesheet' == $rel ) {
						$css_url = parse_url( set_url_scheme( $href ) );
					}
					// If not a stylesheet, rocket_async_css_process_file return false, or exclude regex/file extension matches, move on
					if ( 'stylesheet' != $rel
					     || ( has_filter( 'rocket_async_css_process_file' )
					          && ! apply_filters( 'rocket_async_css_process_file', $href, true ) ) ||
					     ( preg_match( '#^(' . $excluded_css . ')$#', $css_url['path'] )
					       && 'css' == pathinfo( $css_url['path'], PATHINFO_EXTENSION ) )
					) {
						continue;
					}
					//If we have a media tag, set it to the for grouping
					if ( 0 < strlen( $media ) ) {
						$type = $media;
					}
				} else {
					//If we have a media tag, set it to the for grouping
					if ( 0 < strlen( $media ) ) {
						$type = $media;
					}
				}
				if ( 'style' == $name ) {
					// Find any tags
					if ( preg_match( '/<([a-z]+) rel="stylesheet"([^<]+)*(?:>(.*)|\s+\/?>)/U', $tag->textContent ) ) {
						$sub_document = new DOMDocument();
						if ( ! @$sub_document->loadHTML( $tag->textContent ) ) {
							return $buffer;
						}
						$sub_xpath = new DOMXpath( $sub_document );
						$sub_type  = 'all';
						//Search for link tags
						foreach ( $sub_xpath->query( '((//link|//LINK)[@rel="stylesheet"])' ) as $sub_tag ) {
							$sub_media = $tag->getAttribute( 'media' );
							$sub_href  = $tag->getAttribute( 'href' );
							// rocket_async_css_process_file return false, or exclude regex/file extension matches, move on
							$sub_css_url = parse_url( set_url_scheme( $sub_href ) );
							if ( ( has_filter( 'rocket_async_css_process_file' )
							       && ! apply_filters( 'rocket_async_css_process_file', $href, true ) ) ||
							     ( preg_match( '#^(' . $excluded_css . ')$#', $sub_css_url['path'] )
							       && 'css' == pathinfo( $sub_css_url['path'], PATHINFO_EXTENSION ) )
							) {
								continue;
							}
							//If we have a media tag, set it to the for grouping
							if ( 0 < strlen( $sub_media ) ) {
								$sub_type = $sub_media;
							}
							//Import node to primary document
							$sub_tag = $document->importNode( $sub_tag, true );
							$head->appendChild( $sub_tag );
							$tags[] = [ $sub_type, $sub_tag ];

						}
						$tag->textContent = strip_tags( $tag->textContent );
					}
					// If  rocket_async_css_process_style return false, move on
					if ( has_filter( 'rocket_async_css_process_style' ) && ! apply_filters( 'rocket_async_css_process_style', true, $tag->textContent ) ) {
						continue;
					}
				}

				// Add it
				$tags[] = [ $type, $tag ];
			}
			if ( ! empty( $tags ) ) {
				//Get inline minify option
				$minify_inline_css = get_rocket_option( 'minify_html_inline_css', false );
				// Remote fetch external scripts
				$cdn_domains = get_rocket_cdn_cnames();
				// Get the hostname for each CDN CNAME
				foreach ( $cdn_domains as &$cdn_domain ) {
					$cdn_domain_parts = parse_url( $cdn_domain );
					$cdn_domain       = $cdn_domain_parts['host'];
				}
				// Cleanup
				unset( $cdn_domain_parts, $cdn_domain );
				// Get our cache path
				$cache_path = WP_ROCKET_MINIFY_CACHE_PATH . get_current_blog_id() . '/';
				// Create cache dir if needed
				if ( ! is_dir( $cache_path ) ) {
					rocket_mkdir_p( $cache_path );
				}
				// If we have a user logged in, include user ID in filename to be unique as we may have user only JS content. Otherwise file will be a hash of (minify-global-UNIQUEID).js
				if ( is_user_logged_in() ) {
					$filename = $cache_path . md5( 'minify-' . get_current_user_id() . '-' . create_rocket_uniqid() ) . '.css';
				} else {
					$filename = $cache_path . md5( 'minify-global' . create_rocket_uniqid() ) . '.css';
				}
				$css = '';
				/** @var array $tag */
				foreach ( $tags as $item ) {
					/** @var DOMEvent $tag */
					list( $type, $tag ) = $item;
					//Remove tag
					$tag->parentNode->removeChild( $tag );
					//Get source url
					$href = $tag->getAttribute( 'href' );
					if ( 'link' == $tag->tagName && ! empty( $href ) ) {
						//Prevent duplicates
						if ( ! in_array( $href, $urls ) ) {
							// Get host of tag source
							$href_host = parse_url( $href, PHP_URL_HOST );
							// Being remote is defined as not having our home url and not being in the CDN list
							if ( $href_host != $domain && ! in_array( $href_host, $cdn_domains ) || 'css' != pathinfo( parse_url( $href, PHP_URL_PATH ), PATHINFO_EXTENSION ) ) {
								$file = wp_remote_get( set_url_scheme( $href ), array(
									'user-agent' => 'WP-Rocket',
									'sslverify'  => false,
								) );
								$css .= $this->_minify_css( $file['body'] );
							} else {
								$href = strtok( $href, '?' );
								// Break up url
								$url_parts         = parse_url( $href );
								$url_parts['host'] = $domain;
								/*
								 * Check and see what version of php-http we have.
								 * 1.x uses procedural functions.
								 * 2.x uses OOP classes with a http namespace.
								 * Convert the address to a path, minify, and add to buffer.
								 */

								if ( class_exists( 'http\Url' ) ) {
									$url  = new \http\Url( $url_parts );
									$url  = $url->toString();
									$data = $this->_get_content( str_replace( $home, ABSPATH, $url ) );
									$css .= $this->_minify_css( $data, array( 'prependRelativePath' => trailingslashit( dirname( $url_parts['path'] ) ) ) );
								} else {
									if ( ! function_exists( 'http_build_url' ) ) {
										require plugin_dir_path( __FILE__ ) . 'http_build_url.php';
									}

									$data = $this->_get_content( str_replace( $home, ABSPATH, http_build_url( $url_parts ) ) );

									if ( ! empty( $type ) && 'all' != $type ) {
										$data = '@media ' . $type . ' {' . $data . '}';
									}

									$css .= $this->_minify_css( $data, array( 'prependRelativePath' => trailingslashit( dirname( $url_parts['path'] ) ) ) );
								}
								//Add to array so we don't process again
								$urls[] = $href;
							}
						}
					} else {
						// Remove any conditional comments for IE that somehow was put in the style tag
						$css_part = preg_replace( '/(?:<!--)?\[if[^\]]*?\]>.*?<!\[endif\]-->/is', '', $tag->textContent );
						$css .= $minify_inline_css ? $this->_minify_css( $css_part ) : $css_part;
					}
				}
				$css = trim( $css );
				if ( ! empty( $css ) ) {
					rocket_put_content( $filename, $css );
					$href = get_rocket_cdn_url( set_url_scheme( str_replace( ABSPATH, trailingslashit( $home ), $filename ) ) );
					// Create link element
					$external_tag = $document->createElement( 'script' );
					$external_tag->setAttribute( 'data-no-minify', '1' );
					$external_tag->textContent = '(function(h){var d=function(d,e,n){function k(a){if(b.body)return a();setTimeout(function(){k(a)})}function f(){a.addEventListener&&a.removeEventListener("load",f);a.media=n||"all"}var b=h.document,a=b.createElement("link"),c;if(e)c=e;else{var l=(b.body||b.getElementsByTagName("head")[0]).childNodes;c=l[l.length-1]}var m=b.styleSheets;a.rel="stylesheet";a.href=d;a.media="only x";k(function(){c.parentNode.insertBefore(a,e?c:c.nextSibling)});var g=function(b){for(var c=a.href,d=m.length;d--;)if(m[d].href===
c)return b();setTimeout(function(){g(b)})};a.addEventListener&&a.addEventListener("load",f);a.onloadcssdefined=g;g(f);return a};"undefined"!==typeof exports?exports.loadCSS=d:h.loadCSS=d})("undefined"!==typeof global?global:this);';

					$external_tag->textContent .= "loadCSS(" . wp_json_encode( $href ) . ',  document.getElementsByTagName("head")[0].childNodes[ document.getElementsByTagName("head")[0].childNodes.length-1]);';
					$head->appendChild( $external_tag );
					$buffer = $document->saveHTML();
				}

				$buffer = $this->_inject_ie_conditionals( $buffer, $conditionals );
			}

		}

		return $buffer;
	}

	/**
	 * Replace conditional comments with tag placeholder, but exclude html tags if found
	 *
	 * @param $buffer
	 *
	 * @return array
	 */
	private function _extract_ie_conditionals( $buffer ) {
		preg_match_all( '/<!--\[if[^\]]*?\]>.*?<!\[endif\]-->/is', $buffer, $conditionals_match );
		$conditionals = array();
		foreach ( $conditionals_match[0] as $conditional ) {
			if ( ! preg_match( '/<html[^>]*>/i', $buffer ) ) {
				$conditionals[] = $conditional;
				$buffer         = preg_replace( '/<!--\[if[^\]]*?\]>.*?<!\[endif\]-->/is', '<WP_ROCKET_CONDITIONAL />', $buffer, 1 );
			}
		}

		return array( $buffer, $conditionals );
	}

	/**
	 * Wrapper method to ensure autoloader is registered, and use Minify_CSS class instead since it rewrites urls
	 *
	 * @param $css
	 * @param array $options
	 *
	 * @return string
	 */
	private function _minify_css( $css, $options = array() ) {
		if ( ! class_exists( 'Minify_Loader' ) ) {
			require( WP_ROCKET_PATH . 'min/lib/Minify/Loader.php' );
			Minify_Loader::register();
		}

		return Minify_CSS::minify( $css, $options );
	}

	/**
	 * Utility function to get file contents
	 *
	 * @param $file
	 *
	 * @return bool|string
	 *
	 * @since 0.1.0
	 */
	private function _get_content( $file ) {
		require_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php' );
		require_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php' );
		$direct_filesystem = new WP_Filesystem_Direct( new StdClass() );

		return $direct_filesystem->get_contents( $file );
	}

	/**
	 * Add conditional comments back in
	 *
	 * @param $buffer
	 * @param $conditionals
	 *
	 * @return mixed
	 */
	private function _inject_ie_conditionals( $buffer, $conditionals ) {
		foreach ( $conditionals as $conditional ) {
			if ( false !== strpos( $buffer, '<WP_ROCKET_CONDITIONAL />' ) ) {
				$buffer = preg_replace( '/<WP_ROCKET_CONDITIONAL \/>/', $conditional, $buffer, 1 );
			} else {
				break;
			}
		}

		return $buffer;
	}

	/**
	 * Callbavk to prevent wp-admin bar from getting async loaded since the inline css is so small
	 *
	 * @param $skip
	 * @param $css
	 *
	 * @return bool
	 */
	public function exclude_wpadminbar( $skip, $css ) {
		if ( false !== strpos( $css, '#wpadminbar' ) ) {

			return false;
		}

		return $skip;
	}

	/**
	 * Error handler if WP-Rocket is missing
	 */
	public function _activate_error_no_wprocket() {
		$info = get_plugin_data( dirname( dirname( __FILE__ ) ) . '/rocket-async-css.php' );
		_e( sprintf( '
	<div class="error notice">
		<p>Opps! %s requires WP-Rocket! Please Download at <a href="http://www.wp-rocket.me">www.wp-rocket.me</a></p>
	</div>', $info['Name'] ) );
	}

	/**
	 * Error handler if PHP XML is not installed
	 */
	public function _activate_error_no_domdocument() {
		$info = get_plugin_data( dirname( dirname( __FILE__ ) ) . '/rocket-async-css.php' );
		_e( sprintf( '
	<div class="error notice">
		<p>Opps! %s requires PHP XML extension! Please contact your web host or system administrator to get this installed.</p>
	</div>', $info['Name'] ) );
	}
}
