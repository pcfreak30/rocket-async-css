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
	 * Plugin version
	 */
	const VERSION = '0.4.2';
	/**
	 * The current version of the plugin.
	 *
	 * @since    0.1.0
	 * @access   protected
	 * @var      Rocket_Async_Css $_instance Instance singleton.
	 */
	protected static $_instance;

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
		if ( $this->required_plugins_loaded() ) {
			$this->define_public_hooks();
			if ( is_admin() ) {
				$this->define_admin_hooks();
			}
		}
	}

	/**
	 * Hook to detect compatibility and only proceed on success
	 *
	 * @since 0.1.0
	 */
	public function required_plugins_loaded() {
		if ( did_action( 'deactivate_' . plugin_basename( plugin_dir_path( plugin_dir_path( __FILE__ ) ) . 'rocket-async-css.php' ) ) ) {
			return false;
		}
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		$error         = false;
		$wprocket_name = 'wp-rocket/wp-rocket.php';
		if ( validate_plugin( $wprocket_name ) ) {
			$error = true;
			add_action( 'admin_notices', array( $this, '_activate_error_no_wprocket' ) );
		} else {
			if ( ! function_exists( 'rocket_init' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/file.php' );
				activate_plugins( $wprocket_name );
			}
		}
		if ( ! class_exists( 'DOMDocument' ) ) {
			$error = true;
			add_action( 'admin_notices', array( $this, '_activate_error_no_domdocument' ) );
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
			add_filter( 'rocket_async_css_process_style', array( $this, 'exclude_wpadminbar' ), 10, 2 );
			add_filter( 'rocket_buffer', array( $this, 'process_css_buffer' ), PHP_INT_MAX - 1 );

			//A hack to get all revolution sliders to load on window load, not document load
			if ( shortcode_exists( 'rev_slider' ) ) {
				remove_shortcode( 'rev_slider' );
				add_shortcode( 'rev_slider', array( $this, 'rev_slider_compatibility' ) );
			}
			add_filter( 'pre_get_rocket_option_minify_css', '__return_zero' );
			add_filter( 'pre_get_rocket_option_minify_google_fonts', '__return_zero' );
		}
		add_action( 'after_rocket_clean_domain', array( $this, 'prune_transients' ) );
		add_action( 'after_rocket_clean_post', array( $this, 'prune_prune_post_transients' ) );
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

		add_filter( 'pre_get_rocket_option_minify_google_fonts', array( __CLASS__, 'return_one' ) );

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
	 *
	 */
	public static function activate() {
		add_action( 'activated_plugin', Rocket_Async_Css::get_instance(), 'purge_cache' );
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
	 *
	 */
	public static function deactivate() {
		add_action( 'activated_plugin', Rocket_Async_Css::get_instance(), 'purge_cache' );
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
		global $rocket_async_css_file, $wpml_url_filters;
		//Get debug status
		$display_errors = ini_get( 'display_errors' );
		$display_errors = ! empty( $display_errors ) && 'off' !== $display_errors;
		$debug          = ( defined( 'WP_DEBUG' ) && WP_DEBUG || $display_errors );
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
			if ( empty( $head ) ) {
				return $buffer;
			}
			$tags = array();
			$urls = array();
			//Get home URL
			if ( ! empty( $wpml_url_filters ) ) {
				remove_filter( 'home_url', array( $wpml_url_filters, 'home_url_filter' ), - 10 );
			}
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
					          && ! apply_filters( 'rocket_async_css_process_file', true, $href ) ) ||
					     ( preg_match( '#^(' . $excluded_css . ')$#', $css_url['path'] )
					       && 'css' == pathinfo( $css_url['path'], PATHINFO_EXTENSION ) )
					) {
						continue;
					}
					//If we have a media tag, set it to the for grouping
					if ( 0 < strlen( $media ) ) {
						$type = $media;
					}
					$urls[] = $href;
				} else {
					//If we have a media tag, set it to the for grouping
					if ( 0 < strlen( $media ) ) {
						$type = $media;
					}
				}
				foreach ( array_map( 'trim', array_filter( explode( ',', $type ) ) ) as $type_item ) {
					if ( in_array( $type_item, [
						'screen',
						'projection',
					] ) ) {
						$type = 'all';
					}
					break;
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
							       && ! apply_filters( 'rocket_async_css_process_file', true, $href ) ) ||
							     ( preg_match( '#^(' . $excluded_css . ')$#', $sub_css_url['path'] )
							       && 'css' == pathinfo( $sub_css_url['path'], PATHINFO_EXTENSION ) )
							) {
								continue;
							}
							//If we have a media tag, set it to the for grouping
							if ( 0 < strlen( $sub_media ) ) {
								$sub_type = $sub_media;
							}
							foreach ( array_map( 'trim', array_filter( explode( ',', $type ) ) ) as $type_item ) {
								if ( in_array( $type_item, [
									'screen',
									'projection',
								] ) ) {
									$type = 'all';
								}
								break;
							}
							//Import node to primary document
							$sub_tag = $document->importNode( $sub_tag, true );
							$head->appendChild( $sub_tag );
							$tags[] = array( $sub_type, $sub_tag );
							$urls[] = $sub_href;

						}
						$tag->textContent = strip_tags( $tag->textContent );
					}
					// If  rocket_async_css_process_style return false, move on
					if ( has_filter( 'rocket_async_css_process_style' ) && ! apply_filters( 'rocket_async_css_process_style', true, $tag->textContent ) ) {
						continue;
					}
				}

				// Add it
				$tags[] = array( $type, $tag );
			}
			if ( ! empty( $tags ) ) {
				foreach ( $tags as $item ) {
					//Remove tag
					$tag->parentNode->removeChild( $item[1] );
				}
				// Check post cache
				$post_cache_id_hash = md5( serialize( $urls ) );
				$post_cache_id      = 'wp_rocket_footer_js_script_';
				if ( is_singular() ) {
					$post_cache_id .= 'post_' . get_the_ID();
				} else if ( is_tag() || is_category() || is_tax() ) {
					$post_cache_id .= 'tax_' . get_queried_object()->term_id;
				} else if ( is_author() ) {
					$post_cache_id .= 'author_' . get_the_author_meta( 'ID' );
				} else {
					$post_cache_id .= 'generic';
				}
				$post_cache_id .= '_' . $post_cache_id_hash;
				$post_cache = get_transient( $post_cache_id );
				if ( ! empty( $post_cache ) ) {
					// Cached file is gone, we dont have cache
					if ( ! file_exists( $post_cache['filename'] ) ) {
						$post_cache = false;
					}
				}
				if ( empty( $post_cache ) ) {
					$urls = array();
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
						//Get source url
						$href = $tag->getAttribute( 'href' );
						if ( 'link' == $tag->tagName && ! empty( $href ) ) {
							if ( 0 === strpos( $href, '//' ) ) {
								//Handle no protocol urls
								$href = rocket_add_url_protocol( $href );
							}
							//Prevent duplicates
							if ( ! in_array( $href, $urls ) ) {
								// Get host of tag source
								$href_host = parse_url( $href, PHP_URL_HOST );
								// Being remote is defined as not having our home url, not being relative and not being in the CDN list
								if ( 0 != strpos( $href, '/' ) && ( ( $href_host != $domain && ! in_array( $href_host, $cdn_domains ) ) || 'css' != pathinfo( parse_url( $href, PHP_URL_PATH ), PATHINFO_EXTENSION ) ) ) {
									// Check item cache
									$item_cache_id = md5( $href );
									$item_cache_id = 'wp_rocket_async_css_style_' . $item_cache_id;
									$item_cache    = get_transient( $item_cache_id );
									$css_part      = '';
									// Only run if there is no item cache
									if ( empty( $item_cache ) ) {
										$file = wp_remote_get( set_url_scheme( $href ), [
											'user-agent' => 'WP-Rocket',
											'sslverify'  => false,
										] );                                    // Catch Error
										if ( $file instanceof \WP_Error || ( is_array( $file ) && ( empty( $file['response']['code'] ) || ! in_array( $file['response']['code'], [
														200,
														304,
													] ) ) )
										) {
											// Only log if debug mode is on
											if ( $debug ) {
												error_log( 'URL: ' . $href . ' Status:' . ( $file instanceof \WP_Error ? 'N/A' : $file['code'] ) . ' Error:' . ( $file instanceof \WP_Error ? $file->get_error_message() : 'N/A' ) );
											}
										} else {
											$css_part = $this->_minify_css( $file['body'], array(), false );
											set_transient( $item_cache_id, $css_part, get_rocket_purge_cron_interval() );
										}
									} else {
										$css_part = $item_cache;

									}
									if ( ! empty( $css_part ) ) {
										$css .= $css_part;
									}
								} else {
									if ( 0 == strpos( $href, '/' ) ) {
										$href = $home . $href;
									}
									$item_cache_id = md5( $href );
									$item_cache_id = 'wp_rocket_async_css_style_' . $item_cache_id;
									$item_cache    = get_transient( $item_cache_id );
									// Only run if there is no item cache
									if ( empty( $item_cache ) ) {
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
										} else {
											if ( ! function_exists( 'http_build_url' ) ) {
												require plugin_dir_path( __FILE__ ) . 'http_build_url.php';
											}

											$data = $this->_get_content( str_replace( $home, ABSPATH, http_build_url( $url_parts ) ) );
										}

										if ( ! empty( $type ) && 'all' != $type ) {
											$data = '@media ' . $type . ' {' . $data . '}';
										}

										$data = $this->_minify_css( $data, array( 'prependRelativePath' => trailingslashit( dirname( $url_parts['path'] ) ) ) );
										set_transient( $item_cache_id, $data, get_rocket_purge_cron_interval() );
									} else {
										$data = $item_cache;
									}
									$css .= $data;
									//Add to array so we don't process again
									$urls[] = $href;
								}
							}
						} else {
							// Check item cache
							$item_cache_id = md5( $tag->textContent );
							$item_cache_id = 'wp_rocket_async_css_style_' . $item_cache_id;
							$item_cache    = get_transient( $item_cache_id );
							// Only run if there is no item cache
							if ( empty( $item_cache ) ) {
								// Remove any conditional comments for IE that somehow was put in the style tag
								$css_part = preg_replace( '/(?:<!--)?\[if[^\]]*?\]>.*?<!\[endif\]-->/is', '', $tag->textContent );
								$css_part = $minify_inline_css ? $this->_minify_css( $css_part ) : $css_part;
								$css .= $css_part;
								set_transient( $item_cache_id, $css_part, get_rocket_purge_cron_interval() );
							} else {
								$css .= $item_cache;
							}

						}
					}
				}
				/** @var string $filename */
				// Only run if there is no item cache
				if ( empty( $post_cache ) ) {
					$css = trim( $css );
					if ( ! empty( $css ) ) {
						rocket_put_content( $filename, $css );
						$href = get_rocket_cdn_url( set_url_scheme( str_replace( ABSPATH, trailingslashit( $home ), $filename ) ) );
						set_transient( $post_cache_id, compact( 'filename', 'href' ), get_rocket_purge_cron_interval() );
					}
				} else {
					extract( $post_cache );
				}
				// Create link element
				$external_tag = $document->createElement( 'script' );
				$external_tag->setAttribute( 'data-no-minify', '1' );
				$js = '(function(h){var d=function(d,e,n){function k(a){if(b.body)return a();setTimeout(function(){k(a)})}function f(){a.addEventListener&&a.removeEventListener("load",f);a.media=n||"all"}var b=h.document,a=b.createElement("link"),c;if(e)c=e;else{var l=(b.body||b.getElementsByTagName("head")[0]).childNodes;c=l[l.length-1]}var m=b.styleSheets;a.rel="stylesheet";a.href=d;a.media="only x";k(function(){c.parentNode.insertBefore(a,e?c:c.nextSibling)});var g=function(b){for(var c=a.href,d=m.length;d--;)if(m[d].href===
c)return b();setTimeout(function(){g(b)})};a.addEventListener&&a.addEventListener("load",f);a.onloadcssdefined=g;g(f);return a};"undefined"!==typeof exports?exports.loadCSS=d:h.loadCSS=d})("undefined"!==typeof global?global:this);';

				$js .= "loadCSS(" . wp_json_encode( $href ) . ',  document.getElementsByTagName("head")[0].childNodes[ document.getElementsByTagName("head")[0].childNodes.length-1]);';
				$external_tag->appendChild( $document->createTextNode( $js ) );
				$head->appendChild( $external_tag );
				$buffer                = $document->saveHTML();
				$rocket_async_css_file = $filename;
				$buffer                = $this->_inject_ie_conditionals( $buffer, $conditionals );
			}
			if ( ! empty( $wpml_url_filters ) ) {
				add_filter( 'home_url', array( $wpml_url_filters, 'home_url_filter' ), - 10 );
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
	 * @param       $css
	 * @param array $options
	 *
	 * @param bool $local
	 *
	 * @return string
	 */
	private function _minify_css( $css, $options = array(), $local = true ) {
		if ( ! class_exists( 'Minify_Loader' ) ) {
			require( WP_ROCKET_PATH . 'min/lib/Minify/Loader.php' );
			Minify_Loader::register();
		}
		$css = Minify_CSS::minify( $css, $options );
		if ( $local ) {
			$css = $this->_parse_css_imports( $css, $local );
		}

		return $css;
	}

	/**
	 * Process all css imports recursively wth regex
	 *
	 * @param $css
	 *
	 * @param $local
	 *
	 * @return mixed
	 * @since 0.3.9
	 */
	private function _parse_css_imports( $css, $local ) {
		$home = home_url();
		preg_match_all( '/@import\s*(?:url\s*\()?["\'](.*?)["\']\)?\s*;/', $css, $matches );
		//Ensure there are matches
		if ( ! empty( $matches ) && ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $pos => $match ) {
				// Ensure not an empty string
				if ( ! empty( $match ) ) {
					// Is this a URL? If so replace with ABSPATH since this only runs for local files
					if ( parse_url( $match, PHP_URL_HOST ) ) {
						$match = str_replace( $home, ABSPATH, $match[1] );
					}
					// Create path
					$path          = untrailingslashit( ABSPATH ) . $match;
					$imported_data = $this->_get_content( $path );
					// Process css to minify it, passing the path of the found file
					$imported_data = $this->_minify_css( $imported_data, array( 'prependRelativePath' => trailingslashit( dirname( $match ) ) ), $local );
					// Replace match wth fetched css
					$css = str_replace( $matches[0][ $pos ], $imported_data, $css );
				}
			}
		}

		return $css;
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
		$direct_filesystem = new WP_Filesystem_Direct( null );

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
	 * Callback to prevent wp-admin bar from getting async loaded since the inline css is so small
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

	/**
	 * @return mixed
	 */
	public function rev_slider_compatibility() {
		$output = str_replace( 'tpj(document).ready(function() {', 'tpj(window).load(function() {', call_user_func_array( 'rev_slider_shortcode', func_get_args() ) );

		return $output;
	}

	public function prune_transients() {
		global $wpdb;
		$wpdb->get_results( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_wp_rocket_async_css_style_%', '_transient_timeout_wp_rocket_async_css_style_%' ) );
		wp_cache_flush();
	}

	public function prune_prune_post_transients( $post ) {
		global $wpdb;
		$wpdb->get_results( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", "_transient_wp_rocket_async_css_style_{$post->ID}%", "_transient_timeout_wp_rocket_async_css_style_{$post->ID}%" ) );
		wp_cache_flush();
	}
}
