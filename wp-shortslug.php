<?php
/*
Plugin Name: wp-shortslug
Plugin URI: https://github.com/petermolnar/wp-shortslug
Description: reversible automatic short slug based on post pubdate epoch for WordPress
Version: 0.1
Author: Peter Molnar <hello@petermolnar.eu>
Author URI: http://petermolnar.eu/
License: GPLv3
*/

/*  Copyright 2015 Peter Molnar ( hello@petermolnar.eu )

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 3, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if (!class_exists('WP_SHORTSLUG')):

class WP_SHORTSLUG {
	const base = '0123456789abcdefghijklmnopqrstuvwxyz';
	const b = 36;

	public function __construct () {
		// init all the things!
		add_action( 'init', array( &$this, 'init'));

		// replace shortlink
		add_action( 'wp_head', array(&$this, 'shortlink'));

		if (function_exists('is_admin') && is_admin() && !defined('DOING_AJAX')) {
			$statuses = array ('new', 'draft', 'auto-draft', 'pending', 'private', 'future' );
			foreach ($statuses as $status) {
				add_action("{$status}_to_publish", array(&$this, "check_shorturl"));
			}
		}

	}

	public function init() {
		// shortlink replacement
		add_filter( 'get_shortlink', array(&$this, 'shorturl'), 1, 4 );
	}

	/**
	 * absolute short url
	 *
	 */
	public static function shorturl ( $shortlink = '', $id = '', $context = '', $allow_slugs = '' ) {
		global $post;
		$post = static::fix_post($post);

		if ($post === false )
			return $shortlink;

		$url = static::shortslug($post);

		$base = rtrim( get_bloginfo('url'), '/' ) . '/';
		return $base.$url;
	}

	/**
	 * print meta shortlink
	 *
	 */
	public function shortlink () {
		if (function_exists('is_singular') && is_singular()) {
			$url = static::shorturl();
			if ( !empty($url) ) {
				/* this is a good hook location to also check for the existence of
				 * the _wp_old_slug entry: before printing a non-existent url, make
				 * sure it exists in the DB
				 *
				 * this is mostly for retro-fitting non-existant entries for posts
				 * that were published earlier than the plugin was intalled/activated
				 *
				 */
				static::check_shorturl();

				printf ('<link rel="shortlink" href="%s" />%s', static::shorturl() , "\n");
			}
		}
	}

	/**
	 *
	 */
	public static function shortslug ( &$post ) {
		$post = static::fix_post($post);

		if ($post === false)
			return false;

		$epoch = get_the_time('U', $post->ID);
		$url36 = static::epoch2url($epoch);

		return $url36;
	}

	/**
	 * since WordPress has it's built-in rewrite engine, it's eaiser to use
	 * that for adding the short urls
	 */
	public static function check_shorturl(&$post = null) {
		$post = static::fix_post($post);

		if ($post === false)
			return false;

		$url36 = static::shortslug($post);

		// if the generated url is the same as the current slug, walk away
		if ( $url36 == $post->post_name )
			return true;

		$meta = get_post_meta( $post->ID, '_wp_old_slug', false);

		/*
		 * there should be only our shorturl living here, so in case
		 * WP did something nasty, clean up the too many shortslugs
		 *
		 * 3 is a by-the-guts number; posts with more then 3 _wp_old_slug,
		 * matching the criteria of lowecase-with-nums, 5 at max 6 char length
		 * shouldn't really exist
		 *
		 */
		if ( count($meta) > 3 ) {
			foreach ($meta as $key => $slug ) {
				// base36 matches
				if (preg_match('/^[0-9a-z]{5,6}$/', $slug)) {
					static::debug('deleting slug ' . $slug . ' from ' . $post->ID );
					delete_post_meta($post->ID, '_wp_old_slug', $slug);
					unset($meta[$key]);
				}
			}
		}

		if ( !in_array($url36,$meta)) {
			static::debug('adding slug ' . $url36 . ' to ' . $post->ID );
			add_post_meta($post->ID, '_wp_old_slug', $url36);
		}

		return true;
	}

	/**
	 * decode short string and covert it back to UNIX EPOCH
	 *
	 */
	public static function url2epoch( $str ) {

		$b = static::b;
		$base = static::base;

		$limit = strlen($str);
		$res=strpos($base,$str[0]);
		for($i=1;$i<$limit;$i++) {
			$res = $b * $res + strpos($base,$str[$i]);
		}

		return $res;
	}

	/**
	 * convert UNIX EPOCH to short string
	 *
	* thanks to https://stackoverflow.com/questions/4964197/converting-a-number-base-10-to-base-62-a-za-z0-9
	*/
	public static function epoch2url($num) {

		$b = static::b;
		$base = static::base;

		$r = $num  % $b ;
		$res = $base[$r];
		$q = floor($num/$b);
		while ($q) {
			$r = $q % $b;
			$q =floor($q/$b);
			$res = $base[$r].$res;
		}

		return $res;
	}

	/**
	 * do everything to get the Post object
	 */
	public static function fix_post ( &$post = null ) {
		if ($post === null || !static::is_post($post))
			global $post;

		if (static::is_post($post))
			return $post;

		return false;
	}

	/**
	 * test if an object is actually a post
	 */
	public static function is_post ( &$post ) {
		if ( !empty($post) && is_object($post) && isset($post->ID) && !empty($post->ID) )
			return true;

		return false;
	}

	/**
	 * debug log messages, if needed
	 */
	public static function debug( $message) {
		if (is_object($message) || is_array($message))
			$message = json_encode($message);

		if ( defined('WP_DEBUG') && WP_DEBUG == true )
			error_log ( __CLASS__ . ' => ' . $message);
	}

}

$WP_SHORTSLUG = new WP_SHORTSLUG();

endif;
