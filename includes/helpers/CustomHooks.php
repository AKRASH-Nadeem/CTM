<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $module_activation_hooks, $module_deactivation_hooks, $module_style_hooks;
$module_activation_hooks   = array();
$module_deactivation_hooks = array();
$module_style_hooks = array();



/**
 * Registers a module Style callback.
 *
 * @param string   $module_file The module's main file path (or unique identifier).
 * @param callable $callback    The function to call when the module is activated.
 */
if ( ! function_exists( 'register_module_styling' ) ) {
    function register_module_styling( $module_file, $callback ) {
        global $module_style_hooks;
        $module_style_hooks[ $module_file ] = $callback;
    }
}
/**
 * Registers a module activation callback.
 *
 * @param string   $module_basename The module's main file path (or unique identifier).
 * @param callable $callback    The function to call when the module is activated.
 */
if ( ! function_exists( 'register_module_activation' ) ) {
    function register_module_activation( $module_basename, $callback ) {
        global $module_activation_hooks;
        $module_activation_hooks[ $module_basename ] = $callback;
    }
}

/**
 * Registers a module deactivation callback.
 *
 * @param string   $module_basename The module's main file path (or unique identifier).
 * @param callable $callback    The function to call when the module is deactivated.
 */
if ( ! function_exists( 'register_module_deactivation' ) ) {
    function register_module_deactivation( $module_basename, $callback ) {
        global $module_deactivation_hooks;
        $module_deactivation_hooks[ $module_basename ] = $callback;
    }
}
/**
 * Registers a module page.
 *
 * @param string   $module_basename The module's main file path (or unique identifier).
 * @param string   $content    The function to call when the module is deactivated.
 */
if ( ! function_exists( 'register_module_page' ) ) {
    function register_module_page( $module_basename, $content ) {
        
        $pages = ctm_module_page_exists($module_basename);
        if($pages){
            // echo $module_basename . " exists";

        }
        else{
            $page_data = array(
                'post_title'    => $module_basename,
                'post_content'  => $content,
                'post_status'   => 'draft',
                'post_type'     => 'page',
                'post_author'   => 1,
            );
            $page_id = wp_insert_post($page_data);
            // Add metadata (custom fields)
            if (!is_wp_error($page_id)) {
                add_post_meta($page_id, CTM_TOOLS_META, $module_basename);
            }
        }
    }
}
/**
 * UnRegisters a module page.
 *
 * @param string   $module_basename The module's main file path (or unique identifier).
 */
if ( ! function_exists( 'unregister_module_page' ) ) {
    function unregister_module_page( $module_basename ) {
        
        $pages = ctm_module_page_exists($module_basename);
        // print_r($pages);
        if($pages){
            foreach($pages as $page){
                wp_delete_post($page->ID,true);
            }
        }
        else{
            
        }
    }
}
/**
 * Check if the module page exists.
 *
 * @param string   $module_basename The module's main file path (or unique identifier).
 */
if ( ! function_exists( 'ctm_module_page_exists' ) ) {
    function ctm_module_page_exists( $module_basename=null ) {
        $args = array(
            'post_type'  => 'page', // Replace with your post type (e.g., 'page', 'product')
            'posts_per_page' => -1, // Retrieve all matching posts
        );
        if ($module_basename !== null) {
            $args['meta_query'] = array(
                array(
                    'key'   => CTM_TOOLS_META,
                    'value' => $module_basename, // The exact value to match
                    'compare' => '=', // Default is '=', but you can use 'LIKE', '>', '<', etc.
                ),
            );
        }
        else{
            $args['meta_key'] = CTM_TOOLS_META;
            $args['meta_query'] = array(
                array(
                    'key'     => CTM_TOOLS_META,
                    'compare' => 'EXISTS', // Checks if the meta key exists
                ),
            );
        }
        $query = new WP_Query($args);

        if ($query->have_posts()) {
            // while ($query->have_posts()) {
            //     $query->the_post();
            //     echo 'Post ID: ' . get_the_ID() . ' has the "_tools" meta with value "hammer".<br>';
            // }
            return $query->posts;
        } else {
            return null;
        }
    }
}