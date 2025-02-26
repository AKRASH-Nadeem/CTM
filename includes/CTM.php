<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

require_once CTM_PLUGIN_DIR . "includes/helpers/helper_func.php";
require_once CTM_PLUGIN_DIR . "includes/helpers/CustomHooks.php";

class CTM{
    public function __construct() {
        add_action( 'plugins_loaded', array($this,'LoadModules'), 5 );
        add_action( 'admin_menu', array($this,'AdminMenu') );
        add_action( 'admin_init', array($this,'CTMHandleModuleActions') );
        // Gutenberg Editor settings
        add_filter('allowed_block_types', array($this,'RestrictGutenbergBlocks'), 10, 2);
        add_action('enqueue_block_editor_assets', array($this,'RestrictGutenbergEditor'));
        // Add "Deactivate Tool" link to the Pages list
        add_filter('page_row_actions', array($this,'CustomLinks'),10, 2);
        // display tool name
        add_filter('display_post_states', array($this,'PostState'),10, 2);
        // load tools template
        add_filter('page_template', array($this,'LoadToolTemplate'));
        add_action('wp_enqueue_scripts', array($this,'LoadToolsStyle'));
    }
    public function LoadToolsStyle(){
        global $post;
        global $module_style_hooks;

        if($post->post_type == 'page'){
            $tool = get_post_meta($post->ID,CTM_TOOLS_META,true);
            if($tool){
                if ( isset( $module_style_hooks[ $tool ] ) && is_callable( $module_style_hooks[ $tool ] ) ) {
                    call_user_func( $module_style_hooks[ $tool ],$post );
                }
            }
        }
    }
    private function IsTool($post_id=null){
        if($post_id === null){
            global $post;

            // Get the post ID (works for existing posts and auto-drafts)
            $post_id = isset($post->ID) ? $post->ID : null;

            // Fallback: Check URL parameter (useful if global $post isn't set)
            if (!$post_id && isset($_GET['post'])) {
                $post_id = intval($_GET['post']);
            }
        }
        if($post_id){
            $tool = get_post_meta($post_id,CTM_TOOLS_META,true);
            if($tool){
                return true;
            }
            return false;
        }
        return false;
    }
    public function LoadToolTemplate($template){
        global $post;

        if ($post->post_type == 'page'){
            $istool = $this->IsTool($post->ID);
            if($istool){
                $tool_template = CTM_PLUGIN_DIR . 'templates/tool-template.php';
                if (file_exists($tool_template)) {
                    return $tool_template;
                }
            }
        }
    }
    public function RestrictGutenbergEditor(){
        $istool = $this->IsTool();
        if($istool){
            wp_enqueue_script(
                'CTM-js',
                CTM_PLUGIN_DIR_URL . 'assets/js/CTM.js', // Adjust path
                array('wp-blocks', 'wp-dom-ready')
            );
        }
        
    }
    public function RestrictGutenbergBlocks($allowed_block_types, $post){
        $istool = $this->IsTool();
        if($istool){
            return array();
        }
        return $allowed_block_types;
    }
    public function CustomLinks($actions, $post){
        // Only for pages and if the user has edit permissions
        if ($post->post_type == 'page' && current_user_can('manage_options')){
            $istool = $this->IsTool($post->ID);
            if($istool){
                $edit_url = admin_url('admin.php?page=' . CTM_PAGE_SLUG);
                // Link to edit the page with your tool (adjust the URL as needed)
                $actions['deactivate_tool'] = sprintf(
                    '<a href="%s" aria-label="%s">%s</a>',
                    esc_url($edit_url),
                    esc_attr__('Deactivate Tool'),
                    esc_html__('Deactivate Tool')
                );
                
            }
        }
        return $actions;
    }
    public function PostState($post_states,$post){
        if ($post->post_type == 'page' && current_user_can('manage_options')){
            $istool = $this->IsTool($post->ID);
            if($istool){
                $module = get_post_meta($post->ID,CTM_TOOLS_META,true);
                $post_states['tool'] = "$module";
            }
        }
        return $post_states;
    }
    public function LoadModules() {
        /**
         * Load all valid modules.
         *
         * Scans the CTM_MODULES_DIR folder for subdirectories.
         * For each subdirectory, if a file with the same name as the folder exists (e.g., toolname/toolname.php),
         * it is included.
         */
        if ( ! is_dir( CTM_MODULES_DIR ) ) {
            return;
        }
    
        $active_modules = get_option( CTM_ACTIVE_MODULES_OPTION, array() );
        $module_dirs  = array_filter( scandir( CTM_MODULES_DIR ), function( $item ) {
            return $item !== '.' && $item !== '..' && is_dir( CTM_MODULES_DIR . $item );
        } );
    
        foreach ( $module_dirs as $module ) {
            if ( isset( $active_modules[ $module ] ) ) {
                $module_main_file = CTM_MODULES_DIR . $module . '/' . $module . '.php';
                if ( file_exists( $module_main_file ) ) {
                    include_once $module_main_file;
                }
            }
        }
    }
    public function UnLoadModules() {
        /**
         * UnLoad all valid modules.
         *
         * Scans the CTM_MODULES_DIR folder for subdirectories.
         * For each subdirectory, deactivate the module
         */
        global $module_deactivation_hooks;
        
        if ( ! is_dir( CTM_MODULES_DIR ) ) {
            return;
        }
        $module_dirs  = array_filter( scandir( CTM_MODULES_DIR ), function( $item ) {
            return $item !== '.' && $item !== '..' && is_dir( CTM_MODULES_DIR . $item );
        } );
    
        foreach ( $module_dirs as $module ) {
            // // Check if a deactivation callback is registered.
            if ( isset( $module_deactivation_hooks[ $module ] ) && is_callable( $module_deactivation_hooks[ $module ] ) ) {
                call_user_func( $module_deactivation_hooks[ $module ] );
            }
        }
    }
    public function CTMHandleModuleActions() {
        // Only process actions on our admin page.
        if ( ! isset( $_GET['page'] ) || 'ctm-admin' !== $_GET['page'] ) {
            return;
        }
        if ( isset( $_GET['ctm_action'] ) && isset( $_GET['tool'] ) ) {
            $action = sanitize_text_field( $_GET['ctm_action'] );
            $tool = sanitize_text_field( $_GET['tool'] );
            if ( ! isset( $_GET['_wpnonce'] ) ) {
                wp_die( 'Nonce verification failed.' );
            }
            // Build nonce action name dynamically.
            $nonce_action = 'ctm_' . $action . '_' . $tool;
            if ( ! wp_verify_nonce( $_GET['_wpnonce'], $nonce_action ) ) {
                wp_die( 'Nonce verification failed.' );
            }
            $active_modules = get_option( CTM_ACTIVE_MODULES_OPTION, array() );
            $module_path    = CTM_MODULES_DIR . $tool;
            $module_file    = $module_path . '/' . $tool . '.php';
            switch ( $action ) {
                case 'activate':
                    if ( ! is_dir( $module_path ) || ! file_exists( $module_file ) ) {
                        return new WP_Error( 'module_missing', "Module not found." );
                    }

                    // Temporarily include the module file so it registers its activation hook.
                    // This is similar to how WordPress temporarily loads a tool during activation.
                    include_once $module_file;

                    // Check if an activation callback is registered.
                    global $module_activation_hooks;
                    if ( isset( $module_activation_hooks[ $tool ] ) && is_callable( $module_activation_hooks[ $tool ] ) ) {
                        call_user_func( $module_activation_hooks[ $tool ] );
                    }

                    $active_modules[ $tool ] = true;
                    update_option( CTM_ACTIVE_MODULES_OPTION, $active_modules );
                    
                    $redirect_url = remove_query_arg( array( 'ctm_action', 'tool', '_wpnonce' ) );
                    wp_redirect( $redirect_url );
                    exit;
                    break;
                case 'deactivate':

                    // Temporarily include the module file so it registers its deactivation hook.
                    include_once $module_file;

                    // Check if a deactivation callback is registered.
                    global $module_deactivation_hooks;
                    if ( isset( $module_deactivation_hooks[ $tool ] ) && is_callable( $module_deactivation_hooks[ $tool ] ) ) {
                        call_user_func( $module_deactivation_hooks[ $tool ] );
                    }

                    // Mark the module as inactive.
                    if ( isset( $active_modules[ $tool ] ) ) {
                        unset( $active_modules[ $tool ] );
                        update_option( CTM_ACTIVE_MODULES_OPTION, $active_modules );
                    }
                    
                    $redirect_url = remove_query_arg( array( 'ctm_action', 'tool', '_wpnonce' ) );
                    wp_redirect( $redirect_url );
                    exit;
                    break;
                case 'delete':
                    if ( is_dir( $module_path ) ) {
                        // Delete the tool folder recursively.
                        $this->CTMRRMDIR( $module_path );
                        if ( isset( $active_modules[ $tool ] ) ) {
                            unset( $active_modules[ $tool ] );
                            update_option( CTM_ACTIVE_MODULES_OPTION, $active_modules );
                        }
                        $redirect_url = remove_query_arg( array( 'ctm_action', 'tool', '_wpnonce' ) );
                        wp_redirect( $redirect_url );
                        exit;
                    }
                    break;
            }
        }
    }
    public function AdminMenu() {
        /**
         * Add an admin page to list loaded custom modules and display their metadata.
         */
        add_menu_page(
            'Custom Tools Manager',       // Page title
            'Custom Tools',              // Menu title
            'manage_options',              // Capability
            CTM_PAGE_SLUG,                   // Menu slug
            array($this,"CTMAdminPage"),              // Callback function
            'dashicons-admin-plugins',     // Icon
            65                             // Position
        );
        // Submenu for uploading a new tool
        add_submenu_page(
            CTM_PAGE_SLUG,                   // Parent slug
            'Add Tools',                  // Page title
            'Add Tools',                  // Menu title
            'manage_options',              // Capability
            'ctm-add-tool',              // Menu slug
            array($this,'CTMAddTool')          // Callback function
        );
    }
    public function CTMRRMDIR( $dir ) {
        if ( is_dir( $dir ) ) {
            $objects = scandir( $dir );
            foreach ( $objects as $object ) {
                if ( $object !== '.' && $object !== '..' ) {
                    $path = $dir . DIRECTORY_SEPARATOR . $object;
                    if ( is_dir( $path ) ) {
                        $this->CTMRRMDIR( $path );
                    } else {
                        unlink( $path );
                    }
                }
            }
            rmdir( $dir );
        }
    }
    public function CTMHandleToolUpload() {
        /**
         * Process multiple uploaded tool ZIP files.
         *
         * Loops through each file in the tool_zip[] array and processes them individually.
         *
         * @return array Contains 'success' (bool) and 'message' (string)
         */
        // Check if a file was uploaded
        if ( ! isset( $_FILES['tool_zip'] ) ) {
            return array(
                'successes' => array(),
                'errors'    => array( 'No files uploaded.' ),
            );
        }
    
        $files = $_FILES['tool_zip'];
        $success_messages = array();
        $error_messages   = array();
    
        for ( $i = 0; $i < count( $files['name'] ); $i++ ) {
            if ( $files['error'][ $i ] !== UPLOAD_ERR_OK ) {
                $error_messages[] = 'File ' . esc_html( $files['name'][ $i ] ) . ': Error uploading file.';
                continue;
            }
            $result = $this->ProcessSingleToolUpload( $files['tmp_name'][ $i ], $files['name'][ $i ] );
            if ( $result['success'] ) {
                $success_messages[] = $result['message'];
            } else {
                $error_messages[] = $result['message'];
            }
        }
    
        return array(
            'successes' => $success_messages,
            'errors'    => $error_messages,
        );
    }
    public function ProcessSingleToolUpload( $file_tmp, $file_name ) {
        /**
         * Process a single tool ZIP file.
         *
         * Extracts the ZIP to a unique temporary directory, validates the tool structure,
         * prevents uploading the tool manager itself, and moves the tool folder to the modules directory.
         *
         * @param string $file_tmp  The temporary file path of the uploaded ZIP.
         * @param string $file_name The original name of the uploaded file.
         * @return array Contains 'success' (bool) and 'message' (string)
         */
        $file_ext = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
        if ( 'zip' !== $file_ext ) {
            return array(
                'success' => false,
                'message' => "File {$file_name}: Invalid file type. Only ZIP files are allowed.",
            );
        }
    
        $zip = new ZipArchive;
        if ( $zip->open( $file_tmp ) !== true ) {
            return array(
                'success' => false,
                'message' => "File {$file_name}: Failed to open the ZIP file.",
            );
        }
    
        // Create a unique temporary directory for this file.
        $temp_dir = CTM_TEMP_DIR . uniqid( 'tool_', true ) . '/';
        if ( ! wp_mkdir_p( $temp_dir ) ) {
            return array(
                'success' => false,
                'message' => "File {$file_name}: Failed to create temporary directory.",
            );
        }
    
        if ( ! $zip->extractTo( $temp_dir ) ) {
            $zip->close();
            $this->CTMRRMDIR( $temp_dir );
            return array(
                'success' => false,
                'message' => "File {$file_name}: Failed to extract the ZIP file.",
            );
        }
        $zip->close();
    
        // Expect exactly one folder in the extracted directory.
        $temp_items = array_filter( scandir( $temp_dir ), function( $item ) {
            return $item !== '.' && $item !== '..';
        } );
        if ( count( $temp_items ) !== 1 ) {
            $this->CTMRRMDIR( $temp_dir );
            return array(
                'success' => false,
                'message' => "File {$file_name}: Invalid tool structure. The ZIP file should contain exactly one folder.",
            );
        }
    
        $tool_folder = array_shift( $temp_items );
    
        // Prevent recursive loading: do not allow uploading the tool manager itself.
        if ( $tool_folder === basename( CTM_PLUGIN_DIR ) ) {
            $this->CTMRRMDIR( $temp_dir );
            return array(
                'success' => false,
                'message' => "File {$file_name}: Uploading the tool manager itself is not allowed.",
            );
        }
    
        $tool_folder_path = $temp_dir . $tool_folder . '/';
        $main_file = $tool_folder_path . $tool_folder . '.php';
        if ( ! file_exists( $main_file ) ) {
            $this->CTMRRMDIR( $temp_dir );
            return array(
                'success' => false,
                'message' => "File {$file_name}: Invalid tool structure. The main file ({$tool_folder}.php) was not found.",
            );
        }
    
        $destination = CTM_MODULES_DIR . $tool_folder;
        if ( file_exists( $destination ) ) {
            $this->CTMRRMDIR( $temp_dir );
            return array(
                'success' => false,
                'message' => "File {$file_name}: A tool with the same name already exists.",
            );
        }
    
        wp_mkdir_p( CTM_MODULES_DIR );
        if ( ! rename( $tool_folder_path, $destination ) ) {
            $this->CTMRRMDIR( $temp_dir );
            return array(
                'success' => false,
                'message' => "File {$file_name}: Failed to move the tool folder.",
            );
        }
    
        $this->CTMRRMDIR( $temp_dir );
        return array(
            'success' => true,
            'message' => "File {$file_name}: Tool uploaded and installed successfully.",
        );
    }
    public function CTMAddTool() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( isset( $_POST['ctm_upload_tool'] ) && check_admin_referer( 'ctm_add_tool_nonce' ) ) {
            $upload_result = $this->CTMHandleToolUpload();
    
            // Display error notices if there are any.
            if ( ! empty( $upload_result['errors'] ) ) {
                echo '<div class="notice notice-error is-dismissible"><p>' . implode( '<br/>', $upload_result['errors'] ) . '</p></div>';
            }
            // Display success notices if there are any.
            if ( ! empty( $upload_result['successes'] ) ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . implode( '<br/>', $upload_result['successes'] ) . '</p></div>';
            }
        }
        ?>
        
        <div class="wrap">
            <h1>Add Tool</h1>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'ctm_add_tool_nonce' ); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Tool ZIP File(s)</th>
                        <td>
                            <input type="file" name="tool_zip[]" accept=".zip" multiple required />
                            <p class="description">You can select multiple ZIP files to upload at once.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Upload Tool(s)', 'primary', 'ctm_upload_tool' ); ?>
            </form>
        </div>
        <?php
    }
    public function CTMAdminPage() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        // Ensure the get_plugin_data() function is available.
        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
    
        echo '<div class="wrap">';
        echo '<h1>Custom Tools</h1>';
        echo '<table class="widefat fixed" cellspacing="0">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Module Folder</th>';
        echo '<th>Tool Name</th>';
        echo '<th>Description</th>';
        echo '<th>Version</th>';
        echo '<th>Actions</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
    
        if ( ! is_dir( CTM_MODULES_DIR ) ) {
            echo '<tr><td colspan="5">Modules directory not found.</td></tr>';
        } else {
            $module_dirs = array_filter( scandir( CTM_MODULES_DIR ), function( $item ) {
                return $item !== '.' && $item !== '..' && is_dir( CTM_MODULES_DIR . $item );
            } );
            if ( empty( $module_dirs ) ) {
                echo '<tr><td colspan="5">No modules found.</td></tr>';
            } else {
                $active_modules = get_option( CTM_ACTIVE_MODULES_OPTION, array() );
                foreach ( $module_dirs as $module ) {
                    $module_main_file = CTM_MODULES_DIR . $module . '/' . $module . '.php';
                    $tool_data     = array();
                    if ( file_exists( $module_main_file ) ) {
                        $tool_data = get_plugin_data( $module_main_file, false, false );
                    }
                    $is_active = isset( $active_modules[ $module ] );
                    // Build action URLs.
                    $action = $is_active ? 'deactivate' : 'activate';
                    $action_url = wp_nonce_url(
                        add_query_arg(
                            array(
                                'ctm_action' => $action,
                                'tool'     => $module,
                            ),
                            admin_url( 'admin.php?page=ctm-admin' )
                        ),
                        'ctm_' . $action . '_' . $module
                    );
                    $delete_url = wp_nonce_url(
                        add_query_arg(
                            array(
                                'ctm_action' => 'delete',
                                'tool'     => $module,
                            ),
                            admin_url( 'admin.php?page=ctm-admin' )
                        ),
                        'ctm_delete_' . $module
                    );
                    // Retrieve settings link(s) using the filter.
                    // This filter will be "plugin_action_links_pluginname/pluginname.php"
                    $tool_basename    = plugin_basename( $module_main_file );
                    $settings_links_arr = apply_filters( 'tool_action_links_' . $tool_basename, array() );
                    $settings_links     = ! empty( $settings_links_arr )
                        ? ' | ' . implode( ' | ', $settings_links_arr )
                        : '';
                    echo '<tr>';
                    echo '<td>' . esc_html( $module ) . '</td>';
                    echo '<td>' . ( ! empty( $tool_data['Name'] ) ? esc_html( $tool_data['Name'] ) : 'N/A' ) . '</td>';
                    echo '<td>' . ( ! empty( $tool_data['Description'] ) ? esc_html( $tool_data['Description'] ) : 'N/A' ) . '</td>';
                    echo '<td>' . ( ! empty( $tool_data['Version'] ) ? esc_html( $tool_data['Version'] ) : 'N/A' ) . '</td>';
                    echo '<td>';
                    // Always show the Activate/Deactivate link.
                    echo '<a href="' . esc_url( $action_url ) . '">' . ( $is_active ? 'Deactivate' : 'Activate' ) . '</a>';
                    // Show Delete link only if the module is deactivated.
                    if ( ! $is_active ) {
                        echo ' | <a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'Are you sure you want to delete this Tool?\');">Delete</a>';
                    }
                    // Append any settings links provided by the module.
                    echo $settings_links;
                    echo '</td>';
                    echo '</tr>';
                }
            }
        }
    
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }
    public function CTMActivate(){
        /**
         * Activation hook to create the modules directory if it doesn't exist.
         */
        if ( ! file_exists( CTM_MODULES_DIR ) ) {
            wp_mkdir_p( CTM_MODULES_DIR );
        }
        if ( ! file_exists( CTM_TEMP_DIR ) ) {
            wp_mkdir_p( CTM_TEMP_DIR );
        }
        // Initialize the active modules option if it doesn't exist.
        if ( false === get_option( CTM_ACTIVE_MODULES_OPTION ) ) {
            update_option( CTM_ACTIVE_MODULES_OPTION, array() );
        }
    }
    public function CTMDeactivate() {
        $this->UnLoadModules();
        delete_option(CTM_ACTIVE_MODULES_OPTION);
    }
}