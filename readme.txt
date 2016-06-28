=== wp-shortslug ===
Contributors: cadeyrn
Donate link: https://paypal.me/petermolnar/3
Tags: shortlink, shorturl, slug
Requires at least: 3.0
Tested up to: 4.4
Stable tag: 0.4
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Required minimum PHP version: 5.3

Automatic, decodable short slugs for a post to replace shorturl

== Description ==

The plugin automatically adds ( for future and old posts as well ) a short slug entry, fitting the existing WordPress way of adding old slugs. (It populates the _wp_old_slug hidden post meta.) This is then replaces the shortlink.

The entry is generated from the publish date epoch, by a base 36 conversion, therefore contains only numbers and lowercase letters; by this method the entry is reversible and re-generatable in case of need.

== Installation ==

1. Upload contents of `wp-shortslug.zip` to the `/wp-content/plugins/` directory
2. Activate the plugin through the `Plugins` menu in WordPress

== Frequently Asked Questions ==

== Changelog ==

Version numbering logic:

* every A. indicates BIG changes.
* every .B version indicates new features.
* every ..C indicates bugfixes for A.B version.

= 0.4 =
*2016-06-28*

* code refactor from static:: hell to namespace

= 0.3 =
*2016-03-01*

* added auto-cleanup on publish
* added auto-cleanup for impossible shortslugs
* added better logging

= 0.2 =
*2015-12-03*

* Tested up till WordPress 4.4
* added the option to have base larger than 36; in this case [a-zA-Z0-9] are allowed instead of [a-z0-9]
* auto-trigger slug generation & cleanup if needed
* auto-trigger old slug redirection in case the current query hits a 404

= 0.1 =
*2015-11-11*

* initial public release
