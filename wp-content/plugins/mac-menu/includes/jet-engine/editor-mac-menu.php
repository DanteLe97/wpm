<?php
/**
 * Mac Menu Query Editor
 * 
 * Defines UI in JetEngine Query Builder Admin
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load Base_Query class nếu chưa có
if ( ! class_exists( '\Jet_Engine\Query_Builder\Query_Editor\Base_Query' ) ) {
    // Try multiple ways to find the base file
    $base_file = null;
    
    // Method 1: Use jet_engine() function
    if ( function_exists( 'jet_engine' ) ) {
        $base_file = jet_engine()->plugin_path( 'includes/components/query-builder/editor/base.php' );
    }
    
    // Method 2: Direct path from plugin directory
    if ( ! $base_file || ! file_exists( $base_file ) ) {
        $base_file = WP_PLUGIN_DIR . '/jet-engine/includes/components/query-builder/editor/base.php';
    }
    
    // Method 3: Relative from current file
    if ( ! $base_file || ! file_exists( $base_file ) ) {
        $base_file = dirname( dirname( dirname( __FILE__ ) ) ) . '/jet-engine/includes/components/query-builder/editor/base.php';
    }
    
    if ( $base_file && file_exists( $base_file ) ) {
        require_once $base_file;
    }
}

use Jet_Engine\Query_Builder\Query_Editor\Base_Query;

class Mac_Menu_Query_Editor extends Base_Query {

    /**
     * Query type ID (unique identifier)
     */
    public function get_id() {
        return 'mac-menu';
    }

    /**
     * Query type name (displayed in dropdown)
     */
    public function get_name() {
        return __( 'Mac Menu Categories', 'mac-menu' );
    }

    /**
     * Vue component name for Query Builder UI
     */
    public function editor_component_name() {
        return 'jet-mac-menu-query';
    }

    /**
     * Data for Vue component
     * 
     * Returns list of categories to display in preview/options
     */
    public function editor_component_data() {
        
        $categories_list = array();
        
        // Check if macMenu class exists
        if ( ! class_exists( 'macMenu' ) ) {
            return array(
                'categories' => $categories_list,
            );
        }
        
        try {
            $mac_menu = new macMenu();
            $all_categories = $mac_menu->all_cat();
            
            if ( $all_categories ) {
                foreach ( $all_categories as $category ) {
                    $categories_list[] = array(
                        'value' => $category->id,
                        'label' => $category->category_name . ( $category->parents_category > 0 ? ' (Child)' : '' ),
                    );
                }
            }
        } catch ( Exception $e ) {
            // If error, return empty array
            error_log( 'Mac Menu Query Editor Error: ' . $e->getMessage() );
        }
        
        return array(
            'categories' => $categories_list,
        );
    }

    /**
     * Vue component template (HTML content)
     * 
     * @return string HTML template content
     */
    public function editor_component_template() {
        $template_file = plugin_dir_path( __FILE__ ) . 'editor-mac-menu.html';
        if ( file_exists( $template_file ) ) {
            ob_start();
            include $template_file;
            return ob_get_clean();
        }
        return '';
    }

    /**
     * Vue component JS file URL
     * 
     * @return string URL of JS file
     */
    public function editor_component_file() {
        // Calculate correct path from current file
        $file_url = plugin_dir_url( __FILE__ ) . 'assets/js/admin/types/mac-menu.js';
        return $file_url;
    }

    /**
     * Default query args
     */
    public function get_default_args() {
        return array(
            'parents_only' => true,
            'parent_id' => '',
            'search_ids' => '',
            'search' => '',
            'order_by' => 'order',
            'order' => 'ASC',
            'limit' => 10,
            'offset' => 0,
        );
    }

    /**
     * Query args help/description
     */
    public function get_args_help() {
        return array(
            'parents_only' => __( 'Only get parent categories (parents_category = 0)', 'mac-menu' ),
            'order_by' => __( 'Sort by: order, id, category_name', 'mac-menu' ),
            'order' => __( 'Order: ASC or DESC', 'mac-menu' ),
            'limit' => __( 'Maximum number of items', 'mac-menu' ),
            'offset' => __( 'Skip number of items from the beginning', 'mac-menu' ),
        );
    }
}

