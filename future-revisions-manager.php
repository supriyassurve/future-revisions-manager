<?php
/**
 * @package Future_Revisions_Manager
 * @version 1.1.0
 */
/*
Plugin Name: Future Revisions Manager
Plugin URI: http://wordpress.org/plugins/future-revisions-manager/
Description: This plugin will help you to create a copy of published post to restrict the direct submit for posts/ pages/ any other post type.
Author: Supriya Surve
Version: 1.1.0
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

defined( 'ABSPATH' ) or die( 'Plugin file cannot be accessed directly.' );

if ( !defined('WP_POST_REVISIONS') ){	
  define( 'WP_POST_REVISIONS', TRUE);
}

define( 'FRM_VERSION', '1.1.0' );
define( 'FRM__MINIMUM_WP_VERSION', '4.3' );

class FutureRevisionsManager {
  /**
   * Holds the values to be used in the fields callbacks
   */
  private $options;
  private $all_post_types;

  /**
   * Construct
   */
  public function __construct() {
    if( is_admin() ){
      add_action( 'admin_menu', array( $this, 'add_frm_plugin_page' ) );
      add_action( 'admin_init', array( $this, 'frm_settings_page_init' ) );
    }
    add_action( 'pre_post_update', array($this, 'manage_post_future_revisions') );
  }

  /**
   * Add options page
   */
  public function add_frm_plugin_page() {
    /* This page will be under "Settings" */
    add_options_page(
      'Settings Admin', 
      'Future Revision Manager Settings', 
      'manage_options', 
      'frm-setting-admin', 
      array( $this, 'create_frm_admin_page' )
      );
  }

  /**
   * Options page callback
   */
  public function create_frm_admin_page() {
    /* Set class property */
    $this->options = get_option( 'frm_options' );
    $this->all_post_types = get_post_types();

    echo "<div class='wrap'>
    <h1>Future Revisions Manager Settings</h1>
    <form method='post' action='options.php'>";
      settings_fields( 'frm_option_group' );
      do_settings_sections( 'frm-setting-admin' );
      submit_button();
      echo "</form>
    </div>";            
  }

  /**
   * Register and add settings
   */
  public function frm_settings_page_init() {        
    register_setting('frm_option_group', 'frm_options' );

    add_settings_section(
      'setting_section_id', // ID
      'FRM Settings', // Title
      array( $this, 'print_section_info' ), // Callback
      'frm-setting-admin' // Page
      );

    add_settings_field(
      'frm-setting-fields-admin', // ID
      'FRM Fields', // Title 
      array( $this, 'fields_callback' ), // Callback
      'frm-setting-admin', // Page
      'setting_section_id' // Section
      );
  }

  /** 
   * Print the Section text
   */
  public function print_section_info() {
    print 'Update your settings below:';
  }

  /** 
   * Fields callback
   */
  public function fields_callback( $input ) {
    $options_fields = '<table width="100%">';
    foreach ($this->all_post_types as $key => $post_type) {   
      $field_name = "frm_options[ $post_type ]";
      $options_fields .=  '<tr>
      <td width="190px"><label for="'.$post_type.'">'. ucwords( $post_type ) .'</label></td>
      <td><input type="checkbox" id="'.$post_type.'" name="'.$field_name.'"'; 
        if(isset($this->options[$post_type])){
          $options_fields .=  ' checked ';
        }
        $options_fields .= '/></td>
      </tr>';
    }
    $options_fields .=  "</table>";
    echo $options_fields;
  }

  /** 
   * Manage revisions for future posts.
   */
  public function manage_post_future_revisions($id) {
    if(isset($_GET['action']) && $_GET['action']=='trash'){
      /* Return to the update post if moving current post to the trash */
      return;
    }

    global $post;

    if( !is_object($post) ) {
      return;
    }        

    $approved_posttypes = get_option( 'frm_options' );

    if( in_array($post->post_type, array_keys($approved_posttypes)) ){
      if ( $post->post_status=="publish" ) {
        /* If post is published create a new revision.*/
        $postarry = $_POST;
        $postarry['ID'] = 0;
        $postarry['post_ID'] = 0;
        $postarry['post_parent'] = $_POST['post_ID'];
        $postarry['post_status'] = "pending";
        $postarry['guid'] = "";

        /* Create custom revision for the current post.*/
        $revision_id = wp_insert_post( $postarry );

        /* Kill process to keep the current post intact.*/
        $msg = edit_post_link('Back to Original Post ', '<p>', '</p>', $post->ID) . edit_post_link('Visit Revision Post ', '<p>', '</p>', $revision_id);
        wp_die( $msg, __('Pending Revision Created', 'slates'), array( 'response' => 0 ) );
      }

      if ( $post->post_status=="pending" && (isset($_POST['post_status']) && $_POST['post_status']=='publish') ) {

        $parent_post_id = $post->post_parent;
        $_POST['post_type'] = 'revision';
        $_POST['post_status'] = 'inherit';

        $postid = wp_update_post($_POST, true);
        if (defined('WP_DEBUG') && true === WP_DEBUG) {
          if (is_wp_error($postid)) {
            $errors = $postid->get_error_messages();
            foreach ($errors as $error) {
              echo $error;
            }
          }
        }

        /* Restore the parent post with the current updated revision. */
        wp_restore_post_revision( $_POST['ID']);

        /* Kill process to keep the current post intact as updated above. */
        $msg = edit_post_link('Back to Original Post ', '<p>', '</p>', $parent_post_id);
        wp_die( $msg, __('Post approved and published', 'slates'), array( 'response' => 0 ) );
      }
    }
  }  
}
$future_revisions_manager = new FutureRevisionsManager();