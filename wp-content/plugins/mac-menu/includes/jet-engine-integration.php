<?php
/**
 * JetEngine Query Builder Integration for Mac Menu
 * 
 * Integrates Mac Menu with JetEngine Query Builder to:
 * - Query categories from Mac Menu in Query Builder
 * - Use in JetTabs and other widgets
 * - Dynamic tags can use "Current Category" option
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Mac_Menu_JetEngine_Integration {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {

        
        // Hook into admin_init to register types (higher priority than Query_Editor to run after)
        add_action( 'admin_init', array( $this, 'register_query_type_admin' ), 100 );
        
        // Hook into query-editor/register (backup)
        add_action( 'jet-engine/query-builder/query-editor/register', array( $this, 'register_query_type' ) );
        
        // Hook into query gateway to set custom array for dynamic tags
            add_action( 'jet-engine-query-gateway/do-item', array( $this, 'set_current_category_context' ) );
            
            // Hook into jet-tabs/widget/loop-items with high priority to ensure query is registered BEFORE Query Gateway processes
            add_filter( 'jet-tabs/widget/loop-items', array( $this, 'ensure_query_registered' ), 5, 3 );
            
            // Hook into filter to debug items
            add_filter( 'jet-tabs/widget/loop-items', array( $this, 'debug_tabs_items' ), 10, 3 );
            
            // Hook into Query Gateway to debug (low priority to run AFTER query_items)
            add_filter( 'jet-engine-query-gateway/query', array( $this, 'debug_query_gateway' ), 999, 3 );
            
            // Hook into JetTabs filter to debug (low priority to run AFTER Query Gateway)
            add_filter( 'jet-tabs/widget/loop-items', array( $this, 'debug_tabs_items_after_query' ), 999, 3 );
            
            // Hook into query_items() to debug BEFORE processing
            add_filter( 'jet-engine-query-gateway/query', array( $this, 'debug_query_gateway_before' ), 5, 3 );
    }
    
    /**
     * Register query type in admin_init (similar to what Query_Editor does)
     */
    public function register_query_type_admin() {
        if ( ! class_exists( '\Jet_Engine\Query_Builder\Manager' ) ) {
            return;
        }
        
        $query_manager = \Jet_Engine\Query_Builder\Manager::instance();
        if ( ! $query_manager || ! isset( $query_manager->editor ) ) {
            return;
        }
        
        // Ensure Query_Editor has registered its types first
        if ( method_exists( $query_manager->editor, 'register_query_types' ) ) {
            // Only register if not already registered (avoid duplicate)
            $existing_types = method_exists( $query_manager->editor, 'get_types' ) 
                ? $query_manager->editor->get_types() 
                : array();
            
            if ( empty( $existing_types ) ) {
                $query_manager->editor->register_query_types();
            }
        }
        
        
        $this->register_query_type( $query_manager->editor );
        
        // Verify registration
        $types_after = method_exists( $query_manager->editor, 'get_types' ) 
            ? $query_manager->editor->get_types() 
            : array();
    }

    /**
     * Register Mac Menu query type with JetEngine Query Builder
     * 
     * @param Query_Editor $query_editor Instance of Query_Editor (not Manager)
     */
    public function register_query_type( $query_editor ) {
        
        
        try {
            // Register Editor (for admin UI)
            $editor_file = plugin_dir_path( __FILE__ ) . 'jet-engine/editor-mac-menu.php';
            
            if ( ! file_exists( $editor_file ) ) {
                return;
            }
            
            require_once $editor_file;
            
            if ( ! class_exists( 'Mac_Menu_Query_Editor' ) ) {
                return;
            }
            
            $editor_instance = new Mac_Menu_Query_Editor();
            $query_editor->register_type( $editor_instance );
            
            // Register Query Class (for processing logic)
            $query_file = plugin_dir_path( __FILE__ ) . 'jet-engine/query-mac-menu.php';
            
            if ( ! file_exists( $query_file ) ) {
                return;
            }
            
            require_once $query_file;
            
            if ( ! class_exists( 'Mac_Menu_Query' ) ) {
                return;
            }
            
            // Load Query_Factory if not already loaded
            if ( ! class_exists( '\Jet_Engine\Query_Builder\Query_Factory' ) ) {
                $factory_file = jet_engine()->plugin_path( 'includes/components/query-builder/query-factory.php' );
                if ( file_exists( $factory_file ) ) {
                    require_once $factory_file;
                }
            }
            
            // Ensure queries are loaded first (loads base classes)
            if ( class_exists( '\Jet_Engine\Query_Builder\Query_Factory' ) ) {
                \Jet_Engine\Query_Builder\Query_Factory::ensure_queries();
                
                // Register query class
                \Jet_Engine\Query_Builder\Query_Factory::register_query( 'mac-menu', 'Mac_Menu_Query' );
            }
            
        } catch ( Exception $e ) {
            // Silent fail
        }
    }

    /**
     * Ensure query is registered BEFORE Query Gateway processes
     * Hook into jet-tabs/widget/loop-items with priority 5 (before Query Gateway)
     */
    public function ensure_query_registered( $items, $control_name, $widget ) {
        // Only process if query is enabled
        $query_enabled = $widget->get_settings( 'jet_engine_query_' . $control_name );
        $query_id = $widget->get_settings( 'jet_engine_query_id_' . $control_name );
        
        if ( ! $query_enabled || ! $query_id ) {
            return $items;
        }
        
        // Check if query is registered
        if ( ! class_exists( '\Jet_Engine\Query_Builder\Manager' ) ) {
            return $items;
        }
        
        $query_manager = \Jet_Engine\Query_Builder\Manager::instance();
        $all_queries = $query_manager->get_queries();
        
        // If queries are not loaded, force load
        if ( empty( $all_queries ) ) {
            if ( method_exists( $query_manager, 'setup_queries' ) ) {
                $query_manager->setup_queries();
                $all_queries = $query_manager->get_queries();
            }
        }
        
        // Check if query exists
        $query = $query_manager->get_query_by_id( $query_id );
        
        if ( ! $query ) {
            // Query not registered, force register
            
            // Ensure Query_Factory is loaded
            if ( ! class_exists( '\Jet_Engine\Query_Builder\Query_Factory' ) ) {
                $factory_file = jet_engine()->plugin_path( 'includes/components/query-builder/query-factory.php' );
                if ( file_exists( $factory_file ) ) {
                    require_once $factory_file;
                }
            }
            
            // Ensure Mac_Menu_Query is registered
            if ( class_exists( '\Jet_Engine\Query_Builder\Query_Factory' ) ) {
                \Jet_Engine\Query_Builder\Query_Factory::ensure_queries();
                
                // Check if mac-menu query is registered
                $reflection = new \ReflectionClass( '\Jet_Engine\Query_Builder\Query_Factory' );
                $property = $reflection->getStaticPropertyValue( '_queries' );
                
                if ( ! isset( $property['mac-menu'] ) ) {
                    // Load Mac_Menu_Query class
                    if ( ! class_exists( 'Mac_Menu_Query' ) ) {
                        $query_file = plugin_dir_path( __FILE__ ) . 'jet-engine/query-mac-menu.php';
                        if ( file_exists( $query_file ) ) {
                            require_once $query_file;
                        }
                    }
                    
                    // Register query
                    if ( class_exists( 'Mac_Menu_Query' ) ) {
                        \Jet_Engine\Query_Builder\Query_Factory::register_query( 'mac-menu', 'Mac_Menu_Query' );
                    }
                }
                
                // Force setup_queries() again
                if ( method_exists( $query_manager, 'setup_queries' ) ) {
                    $query_manager->setup_queries();
                }
            }
        }
        
        return $items;
    }

    public function debug_query_gateway_before( $items, $control_name, $widget ) {
        return $items;
    }

    /**
     * Debug Query Gateway to see if it's being called
     */
    public function debug_query_gateway( $items, $control_name, $widget ) {
        return $items;
    }

    /**
     * Debug tabs items AFTER Query Gateway has processed
     */
    public function debug_tabs_items_after_query( $tabs, $control_name, $widget ) {
        return $tabs;
    }

    /**
     * Debug tabs items to see if query returns data
     */
    public function debug_tabs_items( $tabs, $control_name, $widget ) {
        return $tabs;
    }

    /**
     * Set context for Mac Menu dynamic tags when using with Query Gateway
     * 
     * When JetEngine Query Gateway loops through items, we set $custom_array
     * so Mac Menu dynamic tags can use "Current Category"
     */
    public function set_current_category_context( $item ) {
        // Get queried object from item array (if exists)
        $queried_object = null;
        if ( is_array( $item ) && isset( $item['_jet_engine_queried_object'] ) ) {
            $queried_object = $item['_jet_engine_queried_object'];
        } elseif ( is_object( $item ) ) {
            $queried_object = $item;
        }
        
        // Check if queried object is a Mac Menu category
        $category_id = null;
        $category_name = null;
        
        if ( is_object( $queried_object ) ) {
            $category_id = isset( $queried_object->id ) ? $queried_object->id : null;
            $category_name = isset( $queried_object->category_name ) ? $queried_object->category_name : null;
        } elseif ( is_array( $queried_object ) ) {
            $category_id = isset( $queried_object['id'] ) ? $queried_object['id'] : ( isset( $queried_object['ID'] ) ? $queried_object['ID'] : null );
            $category_name = isset( $queried_object['category_name'] ) ? $queried_object['category_name'] : null;
        }
        
        if ( $category_id && $category_name ) {
            // Set custom array so dynamic tags can access
            if ( function_exists( 'set_custom_array' ) ) {
                $context_data = array(
                    'id' => $category_id,
                    'category_name' => $category_name,
                );
                
                // Add other fields if available
                if ( is_object( $queried_object ) ) {
                    $context_data['slug_category'] = $queried_object->slug_category ?? '';
                    $context_data['category_description'] = $queried_object->category_description ?? '';
                    $context_data['price'] = $queried_object->price ?? '';
                    $context_data['featured_img'] = $queried_object->featured_img ?? '';
                    $context_data['parents_category'] = $queried_object->parents_category ?? 0;
                } elseif ( is_array( $queried_object ) ) {
                    $context_data['slug_category'] = $queried_object['slug_category'] ?? '';
                    $context_data['category_description'] = $queried_object['category_description'] ?? '';
                    $context_data['price'] = $queried_object['price'] ?? '';
                    $context_data['featured_img'] = $queried_object['featured_img'] ?? '';
                    $context_data['parents_category'] = $queried_object['parents_category'] ?? 0;
                }
                
                // Set custom array BEFORE dynamic tag renders
                set_custom_array( $context_data );
                
                // Also set current object in JetEngine listings data to ensure sync
                if ( function_exists( 'jet_engine' ) && isset( jet_engine()->listings ) && isset( jet_engine()->listings->data ) ) {
                    jet_engine()->listings->data->set_current_object( $queried_object );
                }
            }
        }
    }
}

// Initialize integration when file is loaded
// Class will automatically hook at the right time
Mac_Menu_JetEngine_Integration::get_instance();

// Admin notice for debugging
add_action( 'admin_notices', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    
    // Only show on Query Builder page
    $screen = get_current_screen();
    if ( ! $screen || strpos( $screen->id, 'jet-engine-query' ) === false ) {
        return;
    }
    
    $query_manager = \Jet_Engine\Query_Builder\Manager::instance();
    $types = array();
    
    if ( isset( $query_manager->editor ) && method_exists( $query_manager->editor, 'get_types' ) ) {
        $types = $query_manager->editor->get_types();
    }
    
    $has_mac_menu = isset( $types['mac-menu'] );
    
    if ( ! $has_mac_menu ) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><strong>⚠️ Mac Menu Query Type is not registered!</strong></p>
            <p>Registered types: <?php echo implode( ', ', array_keys( $types ) ); ?></p>
            <p>
                <a href="<?php echo plugin_dir_url( __FILE__ ) . '../force-register-jetengine.php'; ?>" 
                   class="button button-primary" target="_blank">
                    Force Register Mac Menu Query Type
                </a>
                <a href="<?php echo admin_url( 'admin.php?page=mac-cat-menu&debug_jetengine=1' ); ?>" 
                   class="button">
                    Debug Page
                </a>
            </p>
        </div>
        <?php
    } else {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><strong>✅ Mac Menu Query Type has been registered successfully!</strong></p>
            <p>You can select <strong>Mac Menu Categories</strong> from the "Query Type" dropdown below.</p>
        </div>
        <?php
    }
});

