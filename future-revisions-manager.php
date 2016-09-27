<?php
/**
 * @package Future_Revisions_Manager
 * @version 1.0
 */
/*
Plugin Name: Future Revisions Manager
Plugin URI: http://wordpress.org/plugins/future-revisions-manager/
Description: This plugin will help you to create a copy of published post to restrict the direct submit for posts/ pages/ any other post type.
Author: Supriya Surve
Version: 1.0
Author URI: http://supriyasurve.com/

  Copyright 2016 Supriya Surve.

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

if ( !defined('WP_POST_REVISIONS') ){	
	define( 'WP_POST_REVISIONS', TRUE);
}

function manage_post_future_revisions($id) {
	if(isset($_GET['action']) && $_GET['action']=='trash'){
		// Return to the update post if moving current post to the trash
	 	return;
	}

	/* #TODO: Dynamic - Ignore post types list. */
	$ignore_posttypes_list = array('attachment', 'revision', 'nav_menu_item');

  global $post;
	if( !in_array($post->post_type, $ignore_posttypes_list) ){
		if ( $post->post_status=="publish" ) {
			// If post is published create a new revision.
			$postarry = $_POST;
			$postarry['ID'] = 0;
			$postarry['post_ID'] = 0;
			$postarry['post_parent'] = $_POST['post_ID'];
			$postarry['post_status'] = "pending";
			$postarry['guid'] = "";
	 		
	 		// Create custom revision for the current post.
	    $revision_id = wp_insert_post( $postarry );

	    // Kill process to keep the current post intact.
	  	$msg = edit_post_link('Back to Original Post ', '<p>', '</p>', $post->ID) . edit_post_link('Visit Revision Post ', '<p>', '</p>', $revision_id);
	  	wp_die( $msg, __('Pending Revision Created', 'slates'), array( 'response' => 0 ) );
	  }

	  if ( $post->post_status=="pending" && (isset($_POST['post_status']) && $_POST['post_status']=='publish') ) {

	  	$parent_post_id = $post->post_parent;
	  	$_POST['post_type'] = 'revision';
			$_POST['post_status'] = 'inherit';
				
			$post_id = wp_update_post($_POST, true);
			if (defined('WP_DEBUG') && true === WP_DEBUG) {
		 		if (is_wp_error($post_id)) {
					$errors = $post_id->get_error_messages();
					foreach ($errors as $error) {
						echo $error;
					}
				}
			}
	    	
			// Restore the parent post with the current updated revision.
			wp_restore_post_revision( $_POST['ID']);

			// Kill process to keep the current post intact as updated above.
			$msg = edit_post_link('Back to Original Post ', '<p>', '</p>', $parent_post_id);
	  	wp_die( $msg, __('Post approved and published', 'slates'), array( 'response' => 0 ) );
	  }
	}
}
/* #TODO: Roles and capabilities testing. */
add_action('pre_post_update', 'manage_post_future_revisions');