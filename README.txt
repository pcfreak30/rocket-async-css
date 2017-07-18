=== Plugin Name ===
Contributors: pcfreak30
Donate link: https://www.paypal.me/pcfreak30
Tags: optimize, wp-rocket, async css
Requires at least: 4.5
Tested up to: 4.7
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

WordPress plugin to combine all inline, external, and remote CSS and load async. Depends on WP-Rocket

This is NOT an official addon to WP-Rocket!

== Description ==

This plugin will combine all inline and external CSS in the order found on the page and save it to WP-Rocket's cache folder as a new file. Files with media attributes are wrapped in `@media` selectors during processing. Async is powered by [https://github.com/filamentgroup/loadCSS](https://github.com/filamentgroup/loadCSS).

If you need dedicated/professional assistance with this plugin or just want an expert to get your site to run the fastest it can be, you may hire me at [Codeable](https://codeable.io/developers/derrick-hammer/?ref=rvtGZ)

Filters `rocket_async_css_process_style` and `rocket_async_css_process_file` can be used to selectively exclude any inline CSS or external CSS from minify and async loading.

Examples are:

~~~
function exclude_css($skip, $css){
    if ( false !== strpos( $css, 'something' ) ) {
			return false;
		}

	return $skip;
}
add_filter('rocket_async_css_process_style','exclude_css', 10, 2);
~~~

and

~~~
function exclude_file($skip, $url){
    if ( 'some url' == $url ) {
			return false;
	}

	return $skip;
}
add_filter('rocket_async_css_process_style','exclude_file', 10, 2);
~~~

== Installation ==

This section describes how to install the plugin and get it working.

1. Upload the plugin files to the `/wp-content/plugins/rocket-async-css` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
4. View HTML source, and test it out!

== Changelog ==

### 0.5.5 ###

* Rename instance function wrapper to not conflict with wp-rocket async css
* Ensure local files are not url encoded

### 0.5.4 ###

* Disable cache busting in wp-rocket

### 0.5.3 ###

* Improve UTF-8 character handling in the-preloader integration
* Simulate a window resize after preloader div is gone as well as before in the-preloader integration
* Remove stray wp_cache_flush

### 0.5.2 ###

* Improve UTF-8 character handling

### 0.5.1 ###

* Remove use of user id in cache tree

### 0.5.0 ###

* Use deactivated_plugin hook on deactivation
* Rebuild cache system without using SQL

### 0.4.16 ###

* Ensure home uses the active URL scheme

### 0.4.15 ###

* Add compatibility hack for older libxml

### 0.4.14 ###

* Add compatibility with avada theme and revslider to act the same as the revslider shortcode hack
* Split remote minification to a public function so that it can be called by other plugins and add filter rocket_async_css_minify_remote_file

### 0.4.13 ###

* Fix transient timeout names in purge methods

### 0.4.12 ###

* Bugfix purging post transients
* Add purging for home, terms, and generic urls

### 0.4.11 ###

* Make post_cache user specific
* Used wrong post cache ID prefix

### 0.4.10 ###

* Ensure url scheme is set correctly when converting from a CDN domain

### 0.4.9 ###

* Disable minify on AMP pages
* Move preloader init to new wp_action method

### 0.4.8.1 ###

* Missed non-PHP 5.3 code

### 0.4.8 ###

* Ensure PHP 5.3 compatibility

### 0.4.7 ###

* Prevent false positives on exclude_wpadminbar by using a md5 hash

### 0.4.6 ###

* Use rocket_remove_url_protocol and rocket_add_url_protocol on prependRelativePath for remote css
* Ensure no query exists for rebuilding url

### 0.4.5 ###

* Use require_once on http_build_url polyfill
* Always create $url_parts and update $href_host to $url_parts['host']
* Prepend the directory url to correct referenced assets for remote CSS

### 0.4.4 ###

* Fix bug caused in 0.4.2 due to wrong variable

### 0.4.3 ###

* If we can't get the head element, just stop

### 0.4.2 ###

* Tags should always be removed, not just when the cache is empty

### 0.4.1 ###

* Inject javascript hack to trigger a window resize event on load to fix any layout issues due to the-preloader

### 0.4.0 ###

* Near complete refactor of plugin structure to remove un-needed code
* Add new minify cache system to reduce computation time required to minify a page

**Notice: This new cache system could cause unknown issues. While it has been tested, not every situation can be accounted for. Contact me if you hit a problem.**

**Notice: Cache is stored in transients, so only a normal wp-rocket purge will clear everything**

### 0.3.12 ###

* Bug fix revolution slider compatibility

### 0.3.11 ###

* Add compatibility support for revolution slider

### 0.3.10 ###

* Check for relative URL's

### 0.3.9 ###

* Ensure normal array styles are used
* Pass null instead of stdClass to WP_Filesystem_Direct
* Recursively process CSS imports with regex

### 0.3.8 ###

* Check for WPML and temporarily remove its home_url hook

### 0.3.7.1 ###

* Use createTextNode as nodeValue encodes input

### 0.3.7 ###

* Use nodeValue due to php bug in < 5.6 that causes it to not actually save/write it

### 0.3.6 ###

* Fix argument order of rocket_async_css_process_file filter

### 0.3.5.1 ###

* Goofed 0.3.5 version release

### 0.3.5 ###

* If any media in the list is screen or projection, set it to all

### 0.3.4 ###

* Set generated filename to a global variable `$rocket_async_css_file`

### 0.3.3 ###

* Missing filter to disable WP-Rockets css minify

### 0.3.2 ###

* Search for stylesheets inside style tags due to possible malform from wprocket and add to tags list

### 0.3.1 ###

* New minify method to combine to 1 file by wrapping specific media selectors in a @media block
* Set crossorigin attribute for CORS friendly css if on CDN

### 0.3.0 ###

* Revert to using loadCSS as a inline script and dump the external dependencies

### 0.2.2 ###

* Rocket_Async_Css should not be ran until plugins_loaded

### 0.2.1 ###

* Remove duplicate filter for google fonts that could break themes
* Skip css media group if css is empty

### 0.2.0 ###

Changes are code breaking to other plugins using Rocket ASYNC CSS!

* Major refactoring
* New function wrapper for singleton
* Initialization runs on plugins_loaded after wp-rocket now (priority 11)
* Purge cache on deactivation
* Use constants for version and slug

### 0.1.1 ###

* Web fetch dynamic styles being defined as not having a CSS extension
* Add onload attribute for supported browsers
* Remove onload attribute since preload isn't supported in csspreload.js
* Use removeAttribute, don't set to null in csspreload.js

### 0.1.1 ###

* Reverse the order of outputting tags so they are in the correct order
* Reformat code

### 0.1.0 ###

* Initial version