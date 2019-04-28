=== Plugin Name ===
Contributors: pcfreak30
Donate link: https://www.paypal.me/pcfreak30
Tags: optimize, wp-rocket, async css
Requires at least: 4.5
Tested up to: 4.9.4
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

### 0.7.0.13.1 ###

* Misc: Fix missing changes from 0.7.0.13 release

### 0.7.0.13 ###

* Bug: Limit query in responsive images module to 1 item and disable sorting
* Compatibility: Add compatibility with foundationpress theme

### 0.7.0.12 ###

* Bug: Switch back to using comments for IE conditionals since they are re-processed before the JS plugin can HTML minify
* Compatibility: Change file purge filter for compatibility with wp-rocket 3.2

### 0.7.0.11 ###

* Bug: Fix bug that avada setting media_queries_async is tested as a string 0 specifically and not boolean false
* Compatibility: WP Rocket update changed when IE conditions are processed and are done unconditionally, so refactoring needed to prevent issues. Side effect was that the title, meta, and link tags could be put in the body causing serious SEO problems.

### 0.7.0.10 ###

* Bug: Handle case where CDN domain may be just a domain and not a url
* Compatibility: Add compatibility with wp-rocket 3.1 due to JS minify class change
* Compatibility: Force disable Avada async css option

### 0.7.0.9 ###

* Bug: Various bug fixes in responsive image module including better handling of quotes
* Bug: Fix essential grid state defaulting to true
* Compatibility: Disable lazy loading completely for all revolution slider images
* Compatibility: Fix quotes in responsive image module due to bug in a3 lazy load

### 0.7.0.8 ###

* Bug: Don't use PHP_INT_MAX on rocket_buffer
* Enhancement: Major refactor/rewrite of the responsive image class with many bug fixes

### 0.7.0.7 ###

* Enhancement: Ensure get_rocket_cdn_url uses all css/js zones as well as the default all
* Enhancement: Major refactor/rewrite of the responsive image class with many bug fixes

### 0.7.0.6 ###

* Enhancement: Ensure get_rocket_cdn_url uses all css/js zones as well as the default all
* Enhancement: Major refactor/rewrite of the responsive image class with many bug fixes

### 0.7.0.5 ###

* Enhancement: Add rocket_async_css_process_responsive_image filter to process image before going to lazy load if enabled
* Integration: AAdd EssentialGrid integration module to prevent lazy load on images and force cdn'ifying the ajax response HTML

### 0.7.0.4 ###

* Don't check libxml version on body fix

### 0.7.0.3 ###

* Fix bug with plugin startup that could cause a crash

### 0.7.0.2 ###

* Update plugin framework

### 0.7.0.1 ###

* Prevent crash from undefined is_plugin_active in some situations

### 0.7.0 ###

This is considered  a ***MAJOR*** release due to the amount of effort that has been invested since the last release in 2017

* BUGS!: Too many bug fixes to give out in detail. It would be ideal to review git commits in this case

* Integration: Add AvadaTheme integration to work around css bug
* Integration: Add Google Web Fonts integration
* Integration: Add integration with Flatsome theme
* Integration: Add integration with wonder plugin carousel
* Integration: Add integration with max mega menu
* Integration: Add integration with divi builder
* Integration: Add integration with divi booster
* Integration: Add compatibility with The 7 theme
* Feature: Add rocket_async_css_do_blocking filter to force the CSS to output in normal blocking mode for odd edge case exceptions
* Enhancement: Add a footer JS to signal that js is fully loaded
* Enhancement: Add rocket_async_css_do_minify filter for css item to skip minify if it is problematic
* Enhancement: Run lazyload on every individual image and not the whole document to prevent altering javascript or other markup that doesn't need to be touched
* Enhancement: Refactor responsive images integration to look up based on GUID and not do a post meta query for performance
* Enhancement: Add filter, rocket_async_css_lazy_load_responsive_image, to determine if responsive image should be lazy loaded
* Compatibility: Add workaround technique for processing inline javascript that has html
* Compatibility: Don't lazyload images in revolution slider
* Compatibility: Add woocommerce integration to fix cache issues
* Compatibility: Fix compatibility with wp-rocket causing fatal errors

### 0.6.0 ###

This is a ***MAJOR*** release and over 50% of the code is rewritten. While it has been extensively tested, there may still be bugs! Please test in a development site before deploying! Due to the amount of work, only a summary of this version will be detailed below.

* ***Major*** rewrite using new composer based framework.
* Process any remote or local import
* Download any remote file referenced to the minify folder. This fixes google fonts too being remotely requested!
* Reduce files generated by minify
* Add integration with Juipter Theme
* Add integration with LaterSlider
* Add integration with Meta Slider
* Add integration with Revolution Slider
* Add integration with WP Critical CSS
* Add responsive images and lazy load support. This fixes almost ANY image that was not created with srcset that its your media. If a wordpress image size does not match though, Simple Image Sizes plugin is recommended.
* Many other changes

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
