<?php
/**
 * Plugin Name: Simple Post Duplicator
 * Plugin URI: https://ashrafulislamsaon.netlify.app/plugins/simple-post-duplicator
 * Description: A simple plugin to duplicate any post type with a single click.
 * Version: 1.0.0
 * Author: Ashraful Islam
 * Author URI: https://ashrafulislamsaon.netlify.app/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: simple-post-duplicator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Simple_Post_Duplicator {
    
    public function __construct() {
        // Add the duplicate link to post row actions
        add_filter('post_row_actions', array($this, 'add_duplicate_link'), 10, 2);
        add_filter('page_row_actions', array($this, 'add_duplicate_link'), 10, 2);
        
        // Add duplicate link for all custom post types
        add_action('admin_init', array($this, 'add_duplicate_link_to_custom_post_types'));
        
        // Handle the duplication process
        add_action('admin_action_duplicate_post', array($this, 'duplicate_post'));
        
        // Add admin notice for successful duplication
        add_action('admin_notices', array($this, 'duplication_admin_notice'));
    }
    
    /**
     * Add duplicate link to post row actions
     */
    public function add_duplicate_link($actions, $post) {
        if (current_user_can('edit_posts')) {
            $actions['duplicate'] = '<a href="' . wp_nonce_url('admin.php?action=duplicate_post&post=' . $post->ID, 'duplicate-post_' . $post->ID) . '" title="Duplicate this item" rel="permalink">Duplicate</a>';
        }
        return $actions;
    }
    
    /**
     * Add duplicate link to all custom post types
     */
    public function add_duplicate_link_to_custom_post_types() {
        $post_types = get_post_types(array('_builtin' => false), 'names');
        foreach ($post_types as $post_type) {
            add_filter($post_type . '_row_actions', array($this, 'add_duplicate_link'), 10, 2);
        }
    }
    
    /**
     * Handle the post duplication process
     */
    public function duplicate_post() {
        // Check if we're duplicating a post
        if (empty($_GET['post'])) {
            wp_die('No post to duplicate has been supplied!');
        }
        
        // Nonce verification
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'duplicate-post_' . $_GET['post'])) {
            wp_die('Security check failed!');
        }
        
        // Get the original post ID
        $post_id = absint($_GET['post']);
        
        // Get the original post data
        $post = get_post($post_id);
        
        // Check if post exists
        if (!$post) {
            wp_die('Post creation failed, could not find original post: ' . $post_id);
        }
        
        // Check if current user has permission to duplicate this post
        $current_user = wp_get_current_user();
        if (!current_user_can('edit_post', $post_id)) {
            wp_die('You do not have permission to duplicate this post.');
        }
        
        // Create the duplicate post
        $new_post_id = $this->create_duplicate($post);
        
        // Redirect to the edit screen for the new post
        wp_redirect(admin_url('post.php?action=edit&post=' . $new_post_id . '&duplicated=true'));
        exit;
    }
    
    /**
     * Create a duplicate of the post
     */
    private function create_duplicate($post) {
        // Create new post data array
        $new_post_data = array(
            'post_author'    => $post->post_author,
            'post_content'   => $post->post_content,
            'post_excerpt'   => $post->post_excerpt,
            'post_name'      => $post->post_name,
            'post_parent'    => $post->post_parent,
            'post_password'  => $post->post_password,
            'post_status'    => 'draft',
            'post_title'     => $post->post_title . ' (Duplicate)',
            'post_type'      => $post->post_type,
            'comment_status' => $post->comment_status,
            'ping_status'    => $post->ping_status,
            'menu_order'     => $post->menu_order,
            'to_ping'        => $post->to_ping,
        );
        
        // Insert the new post
        $new_post_id = wp_insert_post($new_post_data);
        
        // Copy post taxonomies
        $taxonomies = get_object_taxonomies($post->post_type);
        foreach ($taxonomies as $taxonomy) {
            $post_terms = wp_get_object_terms($post->ID, $taxonomy, array('fields' => 'slugs'));
            wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
        }
        
        // Copy post meta
        $post_meta = get_post_meta($post->ID);
        foreach ($post_meta as $meta_key => $meta_values) {
            foreach ($meta_values as $meta_value) {
                add_post_meta($new_post_id, $meta_key, maybe_unserialize($meta_value));
            }
        }
        
        return $new_post_id;
    }
    
    /**
     * Display admin notice after successful duplication
     */
    public function duplication_admin_notice() {
        if (isset($_GET['duplicated']) && $_GET['duplicated'] == 'true') {
            echo '<div class="notice notice-success is-dismissible"><p>Post duplicated successfully.</p></div>';
        }
    }
}

// Initialize the plugin
new Simple_Post_Duplicator();