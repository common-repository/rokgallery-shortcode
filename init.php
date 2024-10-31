<?php
/*
Plugin Name:    RokGallery Shortcode
Description:    An extension to the mighty RokGallery plugin from RocketTheme which enables you to use [rokgallery] shortcode to embed galleries in your posts or pages.
Author:         Hassan Derakhshandeh
Version:        0.1

		This program is free software; you can redistribute it and/or modify
		it under the terms of the GNU General Public License as published by
		the Free Software Foundation; either version 2 of the License, or
		(at your option) any later version.

		This program is distributed in the hope that it will be useful,
		but WITHOUT ANY WARRANTY; without even the implied warranty of
		MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
		GNU General Public License for more details.

		You should have received a copy of the GNU General Public License
		along with this program; if not, write to the Free Software
		Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class RokGallery_Shortcode {

	function __construct() {
		add_action( 'init', array( &$this, 'register' ) );
		if( is_admin() ) {
			add_action( 'add_meta_boxes', array( &$this, 'add_meta_boxes' ), 10, 2 );
		}
	}

	function register() {
		add_shortcode( 'rokgallery', array( &$this, 'shortcode' ) );
	}

	/**
	 * [rokgallery] shortcode callback
	 * Either "gallery" or "id" parameters must be defined. With "gallery" parameter you can specify the
	 * gallery to pull the images from. The "id" is the ID of the RokGallery Page to get the options from, example: [rokgallery id="34"]
	 *
	 * @since 0.1
	 */
	function shortcode( $atts, $content = '' ) {
		if( isset( $atts['id'] ) ) {
			$gallery_id = $atts['id'];
		} elseif( isset( $atts['gallery'] ) ) { /* @todo */
			global $wpdb;
			$gallery_id = $wpdb->get_var( $wpdb->prepare(
				"SELECT id
				FROM {$wpdb->prefix}rokgallery_galleries
				WHERE name = %s
				",
				$atts['gallery']
				)
			);
			if( null == $gallery_id	) return;
		} else {
			// bail. no gallery is defined.
			return;
		}

		$tmpl = RokGallery_Request::getString('view', 'gallery');
		$className = 'RokGallery_Views_' . ucfirst($tmpl) . '_View';

		//get instance
		$instance = rg_parse_custom_post(get_post_custom($gallery_id)); //put custom object into an array and get rid of weird arrays of one
		$instance = rg_parse_options($instance, RokGallery_Posttypes_RokGallery::get_defaults()); //fills in missing values with defaults
		$instance = rg_parse_options($instance, get_post($gallery_id, ARRAY_A)); //adds post data without overwriting custom fields

		// override instance object with shortcode attributes; cleaner result than wp_parse_args?
		foreach( RokGallery_Posttypes_RokGallery::get_defaults() as $key => $value ) {
			if( isset( $atts[$key] ) )
				$instance[$key] = $atts[$key];
		}

		//set up view
		$rokgallery_view = new $className;
		$view = $rokgallery_view->getView($instance);

		//TODO these are included by the template files, but that is too late for the WP Header
		RokCommon_Header::addStyle(RokCommon_Composite::get($view->context)->getUrl($tmpl . '.css'));
		RokCommon_Header::addStyle(RokCommon_Composite::get($view->style_context)->getUrl('style.css'));
		RokCommon_Header::addInlineScript(RokCommon_Composite::get('rokgallery')->load('js-settings.php', array('that' => $view)));

		$browser = new RokCommon_Browser();
		if ($browser->getShortName() == 'ie7') {
			RokCommon_Header::addStyle(RokCommon_Composite::get('rokgallery')->getUrl('rokgallery-ie7.css'));
		}
		RokCommon_Header::addScript(RokCommon_Composite::get('rokgallery')->getUrl('loves' . RokGallery_Helper::getJSVersion() . '.js'));

		ob_start();
		echo RokCommon_Composite::get('wp_views.' . $tmpl)->load('default.php', array('view' => $view));
		$new_content = ob_get_contents();
		ob_end_clean();

		return $content . '<br />' . $new_content;
	}

	function add_meta_boxes( $post_type, $post ) {
		add_meta_box( 'rgshortcode-meta-box', __( 'Shortcode' ), array( &$this, 'shortcode_meta_box' ), 'rokgallery', 'side', 'default' );
	}

	function shortcode_meta_box( $post ) {
		if( 'publish' == $post->post_status )
			echo '<code>[rokgallery id="' . $post->ID . '"]</code>'; 
	}
}
new RokGallery_Shortcode;
