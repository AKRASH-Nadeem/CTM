<?php

/**
 * Plugin Name: Custom Tools Manager
 * Description: Loads modules from a custom directory. Each module should reside in its own folder under /modules/ with a main file that matches the folder name (including plugin headers).
 * Version: 1.0
 * Author: Akrash Nadeem
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
// Define constants for plugin paths
define( 'CTM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CTM_PLUGIN_DIR_URL', plugin_dir_url( __FILE__ ) );
define( 'CTM_PAGE_SLUG', 'ctm-admin' );
define( 'CTM_MODULES_DIR', CTM_PLUGIN_DIR . 'modules/' );
define( 'CTM_TEMP_DIR', CTM_PLUGIN_DIR . 'temp/' );
define( 'CTM_TOOLS_META', '_tool' );
define( 'CTM_ACTIVE_MODULES_OPTION', 'ctm_active_modules' );


require_once CTM_PLUGIN_DIR . "includes/helpers/CustomHooks.php";
require_once CTM_PLUGIN_DIR . "includes/CTM.php";

$CTM = new CTM();

register_activation_hook( __FILE__, array($CTM,'CTMActivate'));
register_deactivation_hook( __FILE__, array($CTM,'CTMDeactivate'));
