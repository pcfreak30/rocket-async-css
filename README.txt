=== Plugin Name ===
Contributors: pcfreak30
Donate link: https://www.paypal.me/pcfreak30
Tags: optimize, wp-rocket, async css
Requires at least: 4.5
Tested up to: 4.5.1
Stable tag: 0.3.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

WordPress plugin to combine all inline, external, and remote CSS and load async. Depends on WP-Rocket

This is NOT an official addon to WP-Rocket!

== Description ==

This plugin will combine all inline and external CSS in the order found on the page and save it to WP-Rocket's cache folder as a new file. Files with media attributes are wrapped in `@media` selectors during processing. Async is powered by [https://github.com/filamentgroup/loadCSS](https://github.com/filamentgroup/loadCSS).

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