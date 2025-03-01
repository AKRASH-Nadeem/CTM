<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

require_once CTM_PLUGIN_DIR . "includes/helpers/helper_func.php";

class ScriptTools{
    protected $tools = array();

    public function __construct() {
        $this->scan_tools();
        add_action('admin_menu', array($this, 'AdminMenu'));
        add_action('admin_init', array($this, 'HandleToolActions'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    private function scan_tools() {
        $tools_dir = CTM_SCRIPT_TOOL_DIR_PATH;
        foreach (glob($tools_dir . '*', GLOB_ONLYDIR) as $tool_dir) {
            $manifest = $tool_dir . '/manifest.json';
            if (file_exists($manifest)) {
                $config = json_decode(file_get_contents($manifest), true);
                if ($config) {
                    $config['slug'] = basename($tool_dir);
                    $this->tools[$config['slug']] = $config;
                }
            }
        }
    }
    public function AdminMenu() {
        // Submenu for uploading a Script new tool
        add_submenu_page(
            CTM_PAGE_SLUG,                   // Parent slug
            'Script Tools',                  // Page title
            'Script Tools',                  // Menu title
            'manage_options',              // Capability
            CTM_SCRIPT_TOOL_SLUG,              // Menu slug
            array($this,'AdminPage')          // Callback function
        );
        // Submenu for uploading a Script new tool
        add_submenu_page(
            CTM_PAGE_SLUG,                   // Parent slug
            'Add Script Tools',                  // Page title
            'Add Script Tools',                  // Menu title
            'manage_options',              // Capability
            'ctm-add-script-tool',              // Menu slug
            array($this,'AddScriptTool')          // Callback function
        );
        foreach ($this->tools as $tool) {
            add_submenu_page(
                CTM_PAGE_SLUG,
                $tool['name'],
                $tool['name'],
                'manage_options',
                'script-tools-' . $tool['slug'],
                array($this, 'render_tool_page')
            );
        }
    }
    public function AddScriptTool(){
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( isset( $_POST['ctm_upload_script_tool'] ) && check_admin_referer( 'ctm_add_script_tool_nonce' ) ) {
            $upload_result = $this->HandleScriptToolUpload();
            // Display error notices if there are any.
            if(!empty( $upload_result['errors'] )){
                echo '<div class="notice notice-error is-dismissible"><p>' . implode( '<br/>', $upload_result['errors'] ) . '</p></div>';
            }
            // Display success notices if there are any.
            if ( ! empty( $upload_result['successes'] ) ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . implode( '<br/>', $upload_result['successes'] ) . '</p></div>';
            }

        }

        ?>

        <div class="wrap">
            <h1>Add Script Tool</h1>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'ctm_add_script_tool_nonce' ); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Script Tool ZIP File(s)</th>
                        <td>
                            <input type="file" name="script_tool_zip[]" accept=".zip" multiple required />
                            <p class="description">You can select multiple ZIP files to upload at once.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Upload Tool(s)', 'primary', 'ctm_upload_script_tool' ); ?>
            </form>
        </div>
        <?php

    }
    public function HandleScriptToolUpload(){
        /**
         * Process multiple uploaded Script tool ZIP files.
         *
         * Loops through each file in the script_tool_zip[] array and processes them individually.
         *
         * @return array Contains 'success' (bool) and 'message' (string)
         */
        // Check if a file was uploaded
        if ( ! isset( $_FILES['script_tool_zip'] ) ) {
            return array(
                'successes' => array(),
                'errors'    => array( 'No files uploaded.' ),
            );
        }
    
        $files = $_FILES['script_tool_zip'];
        $success_messages = array();
        $error_messages   = array();
    
        for ( $i = 0; $i < count( $files['name'] ); $i++ ) {
            if ( $files['error'][ $i ] !== UPLOAD_ERR_OK ) {
                $error_messages[] = 'File ' . esc_html( $files['name'][ $i ] ) . ': Error uploading file.';
                continue;
            }
            $result = $this->ProcessSingleScriptToolUpload( $files['tmp_name'][ $i ], $files['name'][ $i ] );
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
    public function ProcessSingleScriptToolUpload($file_tmp, $file_name){
        /**
         * Process a single script tool ZIP file.
         *
         * Extracts the ZIP to a unique temporary directory, validates the tool structure,
         * moves the tool folder to the modules directory.
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
        $temp_dir = CTM_TEMP_DIR . uniqid( CTM_SCRIPT_TOOL_OPTION_PREFIX, true ) . '/';
        if ( ! wp_mkdir_p( $temp_dir ) ) {
            return array(
                'success' => false,
                'message' => "File {$file_name}: Failed to create temporary directory.",
            );
        }
    
        if ( ! $zip->extractTo( $temp_dir ) ) {
            $zip->close();
            RRMDIR( $temp_dir );
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
            RRMDIR( $temp_dir );
            return array(
                'success' => false,
                'message' => "File {$file_name}: Invalid tool structure. The ZIP file should contain exactly one folder.",
            );
        }
    
        $tool_folder = array_shift( $temp_items );

        $tool_folder_path = $temp_dir . $tool_folder . '/';
        $main_file = $tool_folder_path . 'manifest.json';
        if ( ! file_exists( $main_file ) ) {
            RRMDIR( $temp_dir );
            return array(
                'success' => false,
                'message' => "File {$file_name}: Invalid tool structure. The (manifest.json) file was not found.",
            );
        }
    
        $destination = CTM_SCRIPT_TOOL_DIR_PATH . $tool_folder;
        if ( file_exists( $destination ) ) {
            RRMDIR( $temp_dir );
            return array(
                'success' => false,
                'message' => "File {$file_name}: A tool with the same name already exists.",
            );
        }
    
        wp_mkdir_p( CTM_SCRIPT_TOOL_DIR_PATH );
        if ( ! rename( $tool_folder_path, $destination ) ) {
            RRMDIR( $temp_dir );
            return array(
                'success' => false,
                'message' => "File {$file_name}: Failed to move the Script tool folder.",
            );
        }
    
        RRMDIR( $temp_dir );
        return array(
            'success' => true,
            'message' => "File {$file_name}: Script Tool uploaded and installed successfully.",
        );
    }
    public function register_settings() {
        foreach ($this->tools as $tool) {
            $option_name = CTM_SCRIPT_TOOL_OPTION_PREFIX . $tool['slug'];
            register_setting($option_name, $option_name);

            add_settings_section(
                $tool['slug'] . '_section',
                $tool['name'] . ' Settings',
                null,
                $option_name
            );

            foreach ($tool['fields'] as $field) {
                add_settings_field(
                    $field['id'],
                    $field['title'],
                    array($this, 'render_field'),
                    $option_name,
                    $tool['slug'] . '_section',
                    array_merge($field, ['script_tool_slug' => $tool['slug']])
                );
            }

            // Add page selection field
            add_settings_field(
                'selected_page',
                'Attach to Page',
                array($this, 'render_page_selector'),
                $option_name,
                $tool['slug'] . '_section',
                ['script_tool_slug' => $tool['slug']]
            );
        }
    }
    public function render_field($args) {
        $option = get_option(CTM_SCRIPT_TOOL_OPTION_PREFIX . $args['script_tool_slug']);
        $value = $option[$args['id']] ?? $args['default'];
        echo '<input type="' . esc_attr($args['type']) . '" 
              name="' . CTM_SCRIPT_TOOL_OPTION_PREFIX . esc_attr($args['script_tool_slug']) . '[' . esc_attr($args['id']) . ']" 
              value="' . esc_attr($value) . '">';
    }
    public function render_page_selector($args) {
        $option = get_option(CTM_SCRIPT_TOOL_OPTION_PREFIX . $args['script_tool_slug']);
        $selected_page = $option['selected_page'] ?? '';
        wp_dropdown_pages(array(
            'selected' => $selected_page,
            'name' => CTM_SCRIPT_TOOL_OPTION_PREFIX . $args['script_tool_slug'] . '[selected_page]',
            'show_option_none' => 'Select a page',
        ));
    }
    public function render_tool_page() {
        // Get current tool slug from page query parameter
        $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        $slug = str_replace('script-tools-', '', $current_page);
        
        // Verify valid tool
        if (!isset($this->tools[$slug])) {
            wp_die(__('Invalid tool configuration.'));
        }
    
        $tool = $this->tools[$slug];
        $option_name = CTM_SCRIPT_TOOL_OPTION_PREFIX . $slug;
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($tool['name']); ?> Settings</h1>
            <form method="post" action="options.php">
                <?php
                // Output security fields
                settings_fields($option_name);
                
                // Output settings sections
                do_settings_sections($option_name);
                
                // Submit button
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    public function enqueue_assets() {
        if (is_page()) {
            $page_id = get_the_ID();
            foreach ($this->tools as $tool) {
                $option = get_option(CTM_SCRIPT_TOOL_OPTION_PREFIX . $tool['slug']);
                if (!empty($option['selected_page']) && $option['selected_page'] == $page_id) {
                    $tool_path = plugins_url(CTM_SCRIPT_TOOL_DIR . $tool['slug'], CTM_PLUGIN_DIR);
                    $tool_dir = CTM_SCRIPT_TOOL_DIR_PATH . $tool['slug'] . '/';

                    $js_data = [
                        'ajax_url' => admin_url('admin-ajax.php'),
                        'settings' => $option,
                        'tool' => $tool,
                        'page_id' => $page_id,
                        'nonce' => wp_create_nonce('script_tool_nonce_' . $tool['slug'])
                    ];

                    $config_handle = 'script-tool-' . $tool['slug'] . '-config';
                    wp_register_script($config_handle, false);
                    wp_add_inline_script(
                        $config_handle,
                        'var toolData = ' . wp_json_encode($js_data) . ';',
                        'before'
                    );
                    wp_enqueue_script($config_handle);

                    // Enqueue ALL CSS files
                    $css_dir = $tool_dir . 'css/';
                    if (file_exists($css_dir)) {
                        foreach (glob($css_dir . '*.css') as $css_file) {
                            $filename = basename($css_file);
                            wp_enqueue_style(
                                'tool-' . $tool['slug'] . '-css-' . sanitize_title($filename),
                                $tool_path . '/css/' . $filename,
                                array(),
                                filemtime($css_file) // Version based on file modification time
                            );
                        }
                    }
    
                    // Enqueue ALL JS files
                    $js_dir = $tool_dir . 'js/';
                    if (file_exists($js_dir)) {
                        foreach (glob($js_dir . '*.js') as $js_file) {
                            $filename = basename($js_file);
                            wp_enqueue_script(
                                'tool-' . $tool['slug'] . '-js-' . sanitize_title($filename),
                                $tool_path . '/js/' . $filename,
                                array('jquery'),
                                filemtime($js_file),
                                true
                            );
                        }
                    }
                }
            }
        }
    }
    public function AdminPage(){
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        // Ensure the get_plugin_data() function is available.
        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
    
        echo '<div class="wrap">';
        echo '<h1>Script Tools</h1>';
        echo '<table class="widefat fixed" cellspacing="0">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Tool Name</th>';
        echo '<th>Description</th>';
        echo '<th>Version</th>';
        echo '<th>Actions</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
    
        if ( ! is_dir( CTM_SCRIPT_TOOL_DIR_PATH ) ) {
            echo '<tr><td colspan="5">Script Tools directory not found.</td></tr>';
        } else {
            if ( empty( $this->tools ) ) {
                echo '<tr><td colspan="5">No Tool found.</td></tr>';
            } else {
                foreach ($this->tools as $tool_data) {
                    // Build action URLs.
                    $delete_url = wp_nonce_url(
                        add_query_arg(
                            array(
                                'script_tool_action' => 'delete',
                                'tool'     => $tool_data['slug'],
                            ),
                            admin_url( 'admin.php?page='.CTM_SCRIPT_TOOL_SLUG )
                        ),
                        'script_tool_delete_' . $tool_data['slug']
                    );
                    echo '<tr>';
                    echo '<td>' . ( ! empty( $tool_data['name'] ) ? esc_html( $tool_data['name'] ) : 'N/A' ) . '</td>';
                    echo '<td>' . ( ! empty( $tool_data['description'] ) ? esc_html( $tool_data['description'] ) : 'N/A' ) . '</td>';
                    echo '<td>' . ( ! empty( $tool_data['version'] ) ? esc_html( $tool_data['version'] ) : 'N/A' ) . '</td>';
                    echo '<td>';
                    // Show Delete link only if the module is deactivated.
                    echo '<a style="color: #b32d2e;" href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'Are you sure you want to delete this Tool?\');">Delete</a>';
                    echo '</td>';
                    echo '</tr>';
                }
            }
        }
    
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }
    public function HandleToolActions() {
        // Only process actions on our admin page.
        if ( ! isset( $_GET['page'] ) || CTM_SCRIPT_TOOL_SLUG !== $_GET['page'] ) {
            return;
        }
        if ( isset( $_GET['script_tool_action'] ) && isset( $_GET['tool'] ) ) {
            $action = sanitize_text_field( $_GET['script_tool_action'] );
            $tool = sanitize_text_field( $_GET['tool'] );
            if ( ! isset( $_GET['_wpnonce'] ) ) {
                wp_die( 'Nonce verification failed.' );
            }
            // Build nonce action name dynamically.
            $nonce_action = 'script_tool_' . $action . '_' . $tool;
            if ( ! wp_verify_nonce( $_GET['_wpnonce'], $nonce_action ) ) {
                wp_die( 'Nonce verification failed.' );
            }
            $module_path    = CTM_SCRIPT_TOOL_DIR_PATH . $tool;
            switch ( $action ) {
                case 'delete':
                    if ( is_dir( $module_path ) ) {
                        // Delete the tool folder recursively.
                        RRMDIR( $module_path );
                        $redirect_url = remove_query_arg( array( 'ctm_action', 'tool', '_wpnonce' ) );
                        wp_redirect( $redirect_url );
                        exit;
                    }
                    break;
            }
        }
    }
    public function Activate(){
    }
    public function Deactivate(){
        foreach ($this->tools as $tool){
            delete_option(CTM_SCRIPT_TOOL_OPTION_PREFIX . $tool['slug']);
        }
    }
}