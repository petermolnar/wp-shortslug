=== wp-shortslug ===
Contributors: cadeyrn
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=AS8Y2GSMDTJZC
Tags: shortlink, shorturl, slug
Requires at least: 3.0
Tested up to: 4.3.1
Stable tag: 0.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Automatical, decodable short slugs for a post to replace shorturl

== Description ==

The plugin automatically adds ( for future and old posts as well ) a short slug entry, fitting the existing WordPress way of adding old slugs. (It populates the _wp_old_slug hidden post meta.) By this, these can be used as short permalinks to the post while they still look nice.

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

= 0.1 =
*2015-11-11*

* initial public release
