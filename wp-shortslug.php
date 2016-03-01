<?php
/*
Plugin Name: wp-shortslug
Plugin URI: https://github.com/petermolnar/wp-shortslug
Description: reversible automatic short slug based on post pubdate epoch for WordPress
Version: 0.3
Author: Peter Molnar <hello@petermolnar.eu>
Author URI: http://petermolnar.eu/
License: GPLv3
Required minimum PHP version: 5.3
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
	const base_camel = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

	public function __construct () {
		register_activation_hook( __FILE__ , array( &$this, 'plugin_activate' ) );

		// init all the things!
		add_action( 'init', array( &$this, 'init'));

		// replace shortlink
		add_action( 'wp_head', array(&$this, 'shortlink'));

		// trigger fallback redirection by _wp_old_slug
		add_action( 'wp_head', array(&$this, 'try_redirect'));

		// register new posts
		add_action( "transition_post_status", array(&$this, "maybe_generate_slug"), 1, 3 );
		add_action( "transition_post_status", array( &$this, 'check_shorturl' ), 2, 3 );

		/*
		if (function_exists('is_admin') && is_admin() && !defined('DOING_AJAX')) {
			$statuses = array ('new', 'draft', 'auto-draft', 'pending', 'private', 'future' );
			foreach ($statuses as $status) {
				add_action("{$status}_to_publish", array(&$this, "check_shorturl"), 2);
				add_action("{$status}_to_publish", array(&$this, "maybe_generate_slug"), 1);
			}
		}
		*/

	}

	public function init() {
		// shortlink replacement
		add_filter( 'get_shortlink', array(&$this, 'shorturl'), 1, 4 );
	}

	/**
	 * activate hook
	 */
	public static function plugin_activate() {
		if ( version_compare( phpversion(), 5.3, '<' ) ) {
			die( 'The minimum PHP version required for this plugin is 5.3' );
		}
	}

	/**
	 * try to redirect by old slug in case the current result is 404
	 *
	 */
	public static function try_redirect () {
		global $wp_query;
		if ($wp_query->is_404 == true) {
			wp_old_slug_redirect();
		}
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

		if ( defined ('SHORTSLUG_BASE') )
			$base = SHORTSLUG_BASE . '/';
		else
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
	public static function check_shorturl( $new_status = false, $old_status = false, $post = null ) {
		$post = static::fix_post($post);

		if ($post === false)
			return false;

		$meta = get_post_meta( $post->ID, '_wp_old_slug', false);
		$url36 = static::shortslug($post);
		static::debug("current slug for #{$post->ID}: '{$url36}'" );
		$epoch = get_the_time('U', $post->ID);

		foreach ($meta as $key => $slug ) {
			$decoded = static::url2epoch ( $slug );

			// base36 matches which are older than the publish date should be deleted
			if (preg_match('/^[0-9a-z]{5,6}$/', $slug) && $decoded < $epoch && $slug != $url36 ) {
				static::debug( "deleting slug '{$slug}' from #{$post->ID} - it's older than publish date so it can't be in use" );
				delete_post_meta($post->ID, '_wp_old_slug', $slug);
				unset($meta[$key]);
			}
		}

		// back away at this point if this post is not yet published
		if ( 'publish' != $new_status )
			return false;

		// if the generated url is the same as the current slug, walk away
		if ( $url36 == $post->post_name )
			return true;

		/*
		 * there should be only our shorturl living here, so in case
		 * WP did something nasty, clean up the too many shortslugs
		 *
		 * 3 is a by-the-guts number; posts with more then 3 _wp_old_slug,
		 * matching the criteria of lowecase-with-nums, 5 at max 6 char length
		 * shouldn't really exist
		 *
		 * also do this if this is a fresh post publish to clean up leftovers
		 *
		 *
		if ( count($meta) > 3 ) ) {
			foreach ($meta as $key => $slug ) {
				// base36 matches
				if (preg_match('/^[0-9a-z]{5,6}$/', $slug)) {
					static::debug('deleting slug ' . $slug . ' from ' . $post->ID );
					delete_post_meta($post->ID, '_wp_old_slug', $slug);
					unset($meta[$key]);
				}
			}
		}
		*/

		// if we somehow deleted the actual slug, fix it
		if ( !in_array($url36,$meta)) {
			static::debug('adding slug ' . $url36 . ' to ' . $post->ID );
			add_post_meta($post->ID, '_wp_old_slug', $url36);
		}

		return true;
	}

	/**
	 * since WordPress has it's built-in rewrite engine, it's eaiser to use
	 * that for adding the short urls
	 */
	public static function maybe_generate_slug( $new_status, $old_status, $post ) {
		$post = static::fix_post($post);

		if ($post === false)
			return false;

		// auto-generated slug for empty title; this we should replace
		$pattern = '/^' . $post->ID . '-[0-9]$/';

		if ( !preg_match( $pattern, $post->post_name ) && !empty($post->post_title) ) {
			static::debug( 'post '. $post->ID .' name is ' . $post->post_name . ' which does not match pattern ' . $pattern .' and the post_title is not empty, so not replacing slug with shortslug.' );
			return false;
		}

		static::debug( 'replacing slug of '. $post->ID .' with shortslug: ' . $url36 );

		// generate new
		$url36 = static::shortslug($post);
		// save old, just in case
		add_post_meta( $post->ID, '_wp_old_slug', $post->post_name );

		/*
		 * this is depricated, but I'll leave it in the code for the future me:
		 * in case you use wp_update_post, it will trigger a post transition, so
		 * that will trigger _everything_ that hooks to that, which is not what we
		 * want. In that case we'll end up with a duplicate event, for both the old
		 * and the new slug and, for example, in a webmention hook's case that is
		 * far from ideal.

		$_post = array(
			'ID' => $post->ID,
			'post_name' => $url36,
		);

		$wp_error = false;
		wp_update_post( $_post, $wp_error );

		if (is_wp_error($wp_error)) {
			$errors = json_encode($post_id->get_error_messages());
			static::debug( $errors );
		}
		*/

		global $wpdb;
		$dbname = "{$wpdb->prefix}posts";
		$req = false;

		static::debug("Updating post slug for #{$post->ID}");

		$q = $wpdb->prepare( "UPDATE `{$dbname}` SET `post_name`='%s' WHERE `ID`='{$post->ID}' LIMIT 1", $url36 );

		try {
			$req = $wpdb->query( $q );
		}
		catch (Exception $e) {
			static::debug('Something went wrong: ' . $e->getMessage());
		}


		$meta = get_post_meta( $post->ID, '_wp_old_slug', false);
		if (in_array($url36,$meta)) {
			static::debug('removing slug ' . $url36 . ' from ' . $post->ID );
			delete_post_meta($post->ID, '_wp_old_slug', $url36);
		}

		return true;
	}


	/**
	 * decode short string and covert it back to UNIX EPOCH
	 *
	 */
	public static function url2epoch( $str, $b = 36 ) {

		if ($b <= 36 )
			$base = static::base;
		else
			$base = static::base_camel;

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
	public static function epoch2url($num, $b = 36 ) {

		if ($b <= 36 )
			$base = static::base;
		else
			$base = static::base_camel;

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
	 *
	 * debug messages; will only work if WP_DEBUG is on
	 * or if the level is LOG_ERR, but that will kill the process
	 *
	 * @param string $message
	 * @param int $level
	 *
	 * @output log to syslog | wp_die on high level
	 * @return false on not taking action, true on log sent
	 */
	public static function debug( $message, $level = LOG_NOTICE ) {
		if ( empty( $message ) )
			return false;

		if ( @is_array( $message ) || @is_object ( $message ) )
			$message = json_encode($message);

		$levels = array (
			LOG_EMERG => 0, // system is unusable
			LOG_ALERT => 1, // Alert 	action must be taken immediately
			LOG_CRIT => 2, // Critical 	critical conditions
			LOG_ERR => 3, // Error 	error conditions
			LOG_WARNING => 4, // Warning 	warning conditions
			LOG_NOTICE => 5, // Notice 	normal but significant condition
			LOG_INFO => 6, // Informational 	informational messages
			LOG_DEBUG => 7, // Debug 	debug-level messages
		);

		// number for number based comparison
		// should work with the defines only, this is just a make-it-sure step
		$level_ = $levels [ $level ];

		// in case WordPress debug log has a minimum level
		if ( defined ( 'WP_DEBUG_LEVEL' ) ) {
			$wp_level = $levels [ WP_DEBUG_LEVEL ];
			if ( $level_ < $wp_level ) {
				return false;
			}
		}

		// ERR, CRIT, ALERT and EMERG
		if ( 3 >= $level_ ) {
			wp_die( '<h1>Error:</h1>' . '<p>' . $message . '</p>' );
			exit;
		}

		$trace = debug_backtrace();
		$caller = $trace[1];
		$parent = $caller['function'];

		if (isset($caller['class']))
			$parent = $caller['class'] . '::' . $parent;

		return error_log( "{$parent}: {$message}" );
	}

}

$WP_SHORTSLUG = new WP_SHORTSLUG();

endif;
