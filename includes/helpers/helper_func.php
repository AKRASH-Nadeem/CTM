<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Ensure the function is defined only once.
if ( ! function_exists( 'GetSlugByPluginDir' ) ) {
    /**
     * Retrieve the slug associated with a given plugin directory.
     *
     * @param string $plugin_dir The plugin directory name.
     * @return string|null The slug if found; otherwise, null.
     */
    function GetSlugByPluginDir( $plugin_dir ) {
        // Retrieve the tools_slugs option (defaults to an empty array).
        $tools_slugs = get_option( CTM_TOOLS_SLUGS_OPTION, array() );
        
        // Search for the plugin directory in the array values.
        $slug = array_search( $plugin_dir, $tools_slugs );
        
        // Return the slug if found; otherwise, return null.
        return ( false !== $slug ) ? $slug : null;
    }
}
