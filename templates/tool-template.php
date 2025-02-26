<?php
/**
 * Template Name: Tool Template
 * Description: Template for tools pages
 */

// Security check to prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load header
wp_head();


// load contents
$content = get_post_field( 'post_content', get_the_ID() );
echo $content;

// footer
wp_footer();
