<?php
/**
 * Force Register Mac Menu Query Type
 * 
 * Chạy file này một lần để force đăng ký query type
 * URL: /wp-content/plugins/mac-menu/force-register-jetengine.php
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';

// Check quyền admin
if (!current_user_can('manage_options')) {
    die('Bạn không có quyền truy cập!');
}

// Enable error display
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Set error handler để catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo '<div class="status error">';
        echo '<h3>❌ Fatal Error Detected:</h3>';
        echo '<p><strong>Message:</strong> ' . htmlspecialchars($error['message']) . '</p>';
        echo '<p><strong>File:</strong> ' . htmlspecialchars($error['file']) . '</p>';
        echo '<p><strong>Line:</strong> ' . $error['line'] . '</p>';
        echo '</div>';
    }
});

?>
<!DOCTYPE html>
<html>
<head>
    <title>Force Register Mac Menu Query Type</title>
    <style>
        body { font-family: Arial; padding: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
        h1 { color: #2271b1; }
        .status { padding: 15px; margin: 15px 0; border-radius: 4px; }
        .success { background: #edfaef; border-left: 4px solid #00a32a; }
        .error { background: #fcf0f1; border-left: 4px solid #d63638; }
        .warning { background: #fff8e5; border-left: 4px solid #dba617; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .btn { display: inline-block; padding: 10px 20px; background: #2271b1; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Force Register Mac Menu Query Type</h1>
        
        <?php
        
        echo '<h2>1. Checking JetEngine...</h2>';
        
        if (!class_exists('\Jet_Engine\Query_Builder\Manager')) {
            echo '<div class="status error"><strong>ERROR:</strong> JetEngine không được kích hoạt!</div>';
            echo '<p>Vui lòng kích hoạt plugin JetEngine trước.</p>';
            exit;
        }
        
        echo '<div class="status success">✅ JetEngine đã được kích hoạt</div>';
        
        echo '<h2>2. Loading Integration Files...</h2>';
        
        // Load macMenu class nếu chưa có
        if (!class_exists('macMenu')) {
            $mac_menu_class_file = dirname(__FILE__) . '/includes/classes/macMenu.php';
            if (file_exists($mac_menu_class_file)) {
                require_once $mac_menu_class_file;
                echo '<div class="status success">✅ macMenu class loaded</div>';
            } else {
                echo '<div class="status error">❌ macMenu class file not found</div>';
            }
        } else {
            echo '<div class="status success">✅ macMenu class already loaded</div>';
        }
        
        // Load Base_Query class for Editor first
        echo '<h3>Loading Base Classes...</h3>';
        $editor_base_file = WP_PLUGIN_DIR . '/jet-engine/includes/components/query-builder/editor/base.php';
        if (file_exists($editor_base_file)) {
            require_once $editor_base_file;
            echo '<div class="status success">✅ Editor Base_Query class loaded</div>';
        } else {
            echo '<div class="status warning">⚠️ Editor Base_Query file not found at: ' . $editor_base_file . '</div>';
        }
        
        $query_base_file = WP_PLUGIN_DIR . '/jet-engine/includes/components/query-builder/queries/base.php';
        if (file_exists($query_base_file)) {
            require_once $query_base_file;
            echo '<div class="status success">✅ Query Base_Query class loaded</div>';
        } else {
            echo '<div class="status warning">⚠️ Query Base_Query file not found at: ' . $query_base_file . '</div>';
        }
        
        // Load Query_Factory class
        $query_factory_file = WP_PLUGIN_DIR . '/jet-engine/includes/components/query-builder/query-factory.php';
        if (file_exists($query_factory_file)) {
            require_once $query_factory_file;
            echo '<div class="status success">✅ Query_Factory class loaded</div>';
        } else {
            echo '<div class="status error">❌ Query_Factory file not found at: ' . $query_factory_file . '</div>';
        }
        
        // Load Editor class
        $editor_file = dirname(__FILE__) . '/includes/jet-engine/editor-mac-menu.php';
        if (file_exists($editor_file)) {
            try {
                require_once $editor_file;
                echo '<div class="status success">✅ Editor file loaded</div>';
            } catch (Exception $e) {
                echo '<div class="status error">❌ Error loading Editor file: ' . $e->getMessage() . '</div>';
                echo '<pre>' . $e->getTraceAsString() . '</pre>';
                exit;
            } catch (Error $e) {
                echo '<div class="status error">❌ Fatal Error loading Editor file: ' . $e->getMessage() . '</div>';
                echo '<pre>' . $e->getTraceAsString() . '</pre>';
                exit;
            }
        } else {
            echo '<div class="status error">❌ Editor file not found: ' . $editor_file . '</div>';
            exit;
        }
        
        // Load Query class
        $query_file = dirname(__FILE__) . '/includes/jet-engine/query-mac-menu.php';
        if (file_exists($query_file)) {
            try {
                require_once $query_file;
                echo '<div class="status success">✅ Query file loaded</div>';
            } catch (Exception $e) {
                echo '<div class="status error">❌ Error loading Query file: ' . $e->getMessage() . '</div>';
                echo '<pre>' . $e->getTraceAsString() . '</pre>';
                exit;
            } catch (Error $e) {
                echo '<div class="status error">❌ Fatal Error loading Query file: ' . $e->getMessage() . '</div>';
                echo '<pre>' . $e->getTraceAsString() . '</pre>';
                exit;
            }
        } else {
            echo '<div class="status error">❌ Query file not found: ' . $query_file . '</div>';
            exit;
        }
        
        echo '<h2>3. Registering Query Type...</h2>';
        
        try {
            // Get JetEngine Query Manager
            $query_manager = \Jet_Engine\Query_Builder\Manager::instance();
            
            if (!$query_manager) {
                throw new Exception('Cannot get JetEngine Query Manager instance');
            }
            
            echo '<div class="status success">✅ JetEngine Query Manager obtained</div>';
            
            // Register Editor
            if (!class_exists('Mac_Menu_Query_Editor')) {
                throw new Exception('Mac_Menu_Query_Editor class not found');
            }
            
            $editor_instance = new Mac_Menu_Query_Editor();
            $query_manager->editor->register_type($editor_instance);
            
            echo '<div class="status success">✅ <strong>Editor registered with ID:</strong> ' . $editor_instance->get_id() . '</div>';
            
            // Register Query Class
            if (!class_exists('Mac_Menu_Query')) {
                throw new Exception('Mac_Menu_Query class not found');
            }
            
            echo '<div class="status success">✅ Mac_Menu_Query class exists</div>';
            
            // Register với Query Factory (không cần ensure_queries vì đã load base classes rồi)
            try {
                echo '<div class="info-box">Registering query class with Query Factory...</div>';
                \Jet_Engine\Query_Builder\Query_Factory::register_query('mac-menu', 'Mac_Menu_Query');
                echo '<div class="status success">✅ <strong>Query class registered successfully!</strong></div>';
            } catch (Exception $e) {
                echo '<div class="status error">❌ Exception: ' . $e->getMessage() . '</div>';
                echo '<pre>' . $e->getTraceAsString() . '</pre>';
                throw $e;
            } catch (Error $e) {
                echo '<div class="status error">❌ Fatal Error: ' . $e->getMessage() . '</div>';
                echo '<pre>' . $e->getTraceAsString() . '</pre>';
                throw $e;
            } catch (Throwable $e) {
                echo '<div class="status error">❌ Throwable: ' . $e->getMessage() . '</div>';
                echo '<pre>' . $e->getTraceAsString() . '</pre>';
                throw $e;
            }
            
            // Force register query types nếu chưa có
            if (method_exists($query_manager->editor, 'register_query_types')) {
                echo '<div class="info-box">Calling register_query_types()...</div>';
                $query_manager->editor->register_query_types();
                echo '<div class="status success">✅ register_query_types() completed</div>';
            }
            
            // List all registered types
            echo '<h2>4. Registered Query Types:</h2>';
            
            try {
                // Check editor và get types via method
                echo '<div class="info-box">';
                echo '<strong>Debug Editor:</strong><ul>';
                echo '<li>Editor exists: ' . (isset($query_manager->editor) ? 'YES' : 'NO') . '</li>';
                if (isset($query_manager->editor)) {
                    echo '<li>Editor class: ' . get_class($query_manager->editor) . '</li>';
                    echo '<li>get_types() method exists: ' . (method_exists($query_manager->editor, 'get_types') ? 'YES' : 'NO') . '</li>';
                }
                echo '</ul></div>';
                
                // Use get_types() method instead of direct property access
                if (method_exists($query_manager->editor, 'get_types')) {
                    $types = $query_manager->editor->get_types();
                    echo '<pre>';
                    foreach ($types as $type_id => $type_instance) {
                        try {
                            $marker = ($type_id === 'mac-menu') ? ' ← MAC MENU!' : '';
                            $name = method_exists($type_instance, 'get_name') ? $type_instance->get_name() : 'Unknown';
                            echo $type_id . ': ' . $name . $marker . "\n";
                        } catch (Exception $e) {
                            echo $type_id . ': ERROR - ' . $e->getMessage() . "\n";
                        }
                    }
                    echo '</pre>';
                    
                    if (isset($types['mac-menu'])) {
                        echo '<div class="status success">';
                        echo '<h3>✅ THÀNH CÔNG!</h3>';
                        echo '<p><strong>Mac Menu Categories</strong> đã được đăng ký thành công!</p>';
                        echo '<p>Bây giờ bạn có thể:</p>';
                        echo '<ol>';
                        echo '<li>Vào <strong>JetEngine → Query Builder</strong></li>';
                        echo '<li>Click <strong>Add New Query</strong></li>';
                        echo '<li>Trong dropdown <strong>Query Type</strong>, chọn <strong>Mac Menu Categories</strong></li>';
                        echo '</ol>';
                        echo '</div>';
                    } else {
                        echo '<div class="status warning">';
                        echo '<p><strong>Warning:</strong> mac-menu type không xuất hiện trong danh sách Editor types!</p>';
                        echo '<p>Nhưng query class đã được register với Query Factory, có thể vẫn hoạt động.</p>';
                        echo '</div>';
                    }
                } else {
                    echo '<div class="status warning">⚠️ get_types() method not available</div>';
                }
            } catch (Exception $e) {
                echo '<div class="status error">❌ Error listing types: ' . $e->getMessage() . '</div>';
                echo '<pre>' . $e->getTraceAsString() . '</pre>';
            } catch (Error $e) {
                echo '<div class="status error">❌ Fatal error listing types: ' . $e->getMessage() . '</div>';
                echo '<pre>' . $e->getTraceAsString() . '</pre>';
            }
            
        } catch (Exception $e) {
            echo '<div class="status error">';
            echo '<strong>ERROR:</strong> ' . $e->getMessage();
            echo '<pre>' . $e->getTraceAsString() . '</pre>';
            echo '</div>';
        }
        
        ?>
        
        <a href="<?php echo admin_url('admin.php?page=jet-engine-query'); ?>" class="btn">
            → Đến JetEngine Query Builder
        </a>
        
        <a href="<?php echo admin_url('admin.php?page=mac-cat-menu&debug_jetengine=1'); ?>" class="btn">
            → Xem Debug Page
        </a>
        
    </div>
</body>
</html>

