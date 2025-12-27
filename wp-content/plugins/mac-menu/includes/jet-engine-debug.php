<?php
/**
 * Mac Menu JetEngine Integration Debug Page
 * 
 * Để truy cập: /wp-admin/admin.php?page=mac-cat-menu&debug_jetengine=1
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Kiểm tra xem có request debug không
add_action( 'admin_init', function() {
    if ( isset( $_GET['page'] ) && $_GET['page'] === 'mac-cat-menu' && isset( $_GET['debug_jetengine'] ) ) {
        mac_menu_jetengine_debug_page();
        exit;
    }
});

function mac_menu_jetengine_debug_page() {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Mac Menu - JetEngine Integration Debug</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
            .debug-container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            h1 { color: #2271b1; border-bottom: 3px solid #2271b1; padding-bottom: 10px; }
            h2 { color: #135e96; margin-top: 30px; border-bottom: 2px solid #ddd; padding-bottom: 5px; }
            .status { display: inline-block; padding: 5px 15px; border-radius: 4px; font-weight: bold; margin-left: 10px; }
            .status.success { background: #00a32a; color: white; }
            .status.error { background: #d63638; color: white; }
            .status.warning { background: #dba617; color: white; }
            .info-box { background: #f0f6fc; border-left: 4px solid #2271b1; padding: 15px; margin: 15px 0; }
            .error-box { background: #fcf0f1; border-left: 4px solid #d63638; padding: 15px; margin: 15px 0; }
            .success-box { background: #edfaef; border-left: 4px solid #00a32a; padding: 15px; margin: 15px 0; }
            pre { background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; }
            ul { line-height: 1.8; }
            .back-link { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #2271b1; color: white; text-decoration: none; border-radius: 4px; }
            .back-link:hover { background: #135e96; color: white; }
        </style>
    </head>
    <body>
        <div class="debug-container">
            <h1>🔍 Mac Menu - JetEngine Integration Debug</h1>
            
            <h2>1. WordPress Environment</h2>
            <ul>
                <li><strong>WordPress Version:</strong> <?php echo get_bloginfo( 'version' ); ?></li>
                <li><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></li>
                <li><strong>Debug Mode:</strong> 
                    <?php if ( WP_DEBUG ): ?>
                        <span class="status success">ENABLED</span>
                    <?php else: ?>
                        <span class="status warning">DISABLED</span>
                    <?php endif; ?>
                </li>
            </ul>

            <h2>2. Plugin Status</h2>
            <ul>
                <li><strong>Mac Menu Plugin:</strong> 
                    <?php if ( defined( 'MAC_PATH' ) ): ?>
                        <span class="status success">ACTIVE</span>
                        <br>&nbsp;&nbsp;&nbsp;&nbsp;Path: <code><?php echo MAC_PATH; ?></code>
                    <?php else: ?>
                        <span class="status error">NOT ACTIVE</span>
                    <?php endif; ?>
                </li>
                
                <li><strong>JetEngine Plugin:</strong> 
                    <?php if ( class_exists( '\Jet_Engine\Query_Builder\Manager' ) ): ?>
                        <span class="status success">ACTIVE</span>
                        <?php 
                        if ( defined( 'JET_ENGINE_VERSION' ) ) {
                            echo '<br>&nbsp;&nbsp;&nbsp;&nbsp;Version: <code>' . JET_ENGINE_VERSION . '</code>';
                        }
                        ?>
                    <?php else: ?>
                        <span class="status error">NOT ACTIVE</span>
                    <?php endif; ?>
                </li>
            </ul>

            <h2>3. Mac Menu Classes</h2>
            <ul>
                <li><strong>macMenu class:</strong> 
                    <?php if ( class_exists( 'macMenu' ) ): ?>
                        <span class="status success">EXISTS</span>
                        <?php
                        $mac_menu = new macMenu();
                        $categories = $mac_menu->all_cat();
                        echo '<br>&nbsp;&nbsp;&nbsp;&nbsp;Categories count: <code>' . count( $categories ) . '</code>';
                        ?>
                    <?php else: ?>
                        <span class="status error">NOT FOUND</span>
                    <?php endif; ?>
                </li>
            </ul>

            <h2>4. Integration Files</h2>
            <?php
            $integration_file = MAC_PATH . 'includes/jet-engine-integration.php';
            $editor_file = MAC_PATH . 'includes/jet-engine/editor-mac-menu.php';
            $query_file = MAC_PATH . 'includes/jet-engine/query-mac-menu.php';
            $html_file = MAC_PATH . 'includes/jet-engine/editor-mac-menu.html';
            ?>
            <ul>
                <li><strong>Integration File:</strong> 
                    <?php if ( file_exists( $integration_file ) ): ?>
                        <span class="status success">EXISTS</span>
                        <br>&nbsp;&nbsp;&nbsp;&nbsp;<code><?php echo $integration_file; ?></code>
                    <?php else: ?>
                        <span class="status error">NOT FOUND</span>
                    <?php endif; ?>
                </li>
                
                <li><strong>Editor File:</strong> 
                    <?php if ( file_exists( $editor_file ) ): ?>
                        <span class="status success">EXISTS</span>
                    <?php else: ?>
                        <span class="status error">NOT FOUND</span>
                    <?php endif; ?>
                </li>
                
                <li><strong>Query File:</strong> 
                    <?php if ( file_exists( $query_file ) ): ?>
                        <span class="status success">EXISTS</span>
                    <?php else: ?>
                        <span class="status error">NOT FOUND</span>
                    <?php endif; ?>
                </li>
                
                <li><strong>HTML Template:</strong> 
                    <?php if ( file_exists( $html_file ) ): ?>
                        <span class="status success">EXISTS</span>
                    <?php else: ?>
                        <span class="status error">NOT FOUND</span>
                    <?php endif; ?>
                </li>
            </ul>

            <h2>5. Integration Classes</h2>
            <?php
            // Force load integration nếu chưa có
            if ( ! class_exists( 'Mac_Menu_JetEngine_Integration' ) ) {
                if ( class_exists( '\Jet_Engine\Query_Builder\Manager' ) && file_exists( $integration_file ) ) {
                    echo '<div class="info-box"><strong>Force loading integration file...</strong></div>';
                    require_once $integration_file;
                }
            }
            ?>
            <ul>
                <li><strong>Mac_Menu_JetEngine_Integration:</strong> 
                    <?php if ( class_exists( 'Mac_Menu_JetEngine_Integration' ) ): ?>
                        <span class="status success">LOADED</span>
                    <?php else: ?>
                        <span class="status error">NOT LOADED</span>
                    <?php endif; ?>
                </li>
                
                <li><strong>Mac_Menu_Query_Editor:</strong> 
                    <?php if ( class_exists( 'Mac_Menu_Query_Editor' ) ): ?>
                        <span class="status success">LOADED</span>
                    <?php else: ?>
                        <span class="status warning">NOT YET LOADED (sẽ load khi cần)</span>
                    <?php endif; ?>
                </li>
                
                <li><strong>Mac_Menu_Query:</strong> 
                    <?php if ( class_exists( 'Mac_Menu_Query' ) ): ?>
                        <span class="status success">LOADED</span>
                    <?php else: ?>
                        <span class="status warning">NOT YET LOADED (sẽ load khi cần)</span>
                    <?php endif; ?>
                </li>
            </ul>

            <h2>6. JetEngine Query Types</h2>
            <?php if ( class_exists( '\Jet_Engine\Query_Builder\Manager' ) ): ?>
                <?php
                $query_manager = \Jet_Engine\Query_Builder\Manager::instance();
                
                echo '<div class="info-box">';
                echo '<strong>Debug Info:</strong><ul>';
                echo '<li>Query Manager exists: ' . ( $query_manager ? 'YES' : 'NO' ) . '</li>';
                echo '<li>Query Manager class: ' . ( $query_manager ? get_class( $query_manager ) : 'N/A' ) . '</li>';
                echo '<li>Editor property exists: ' . ( isset( $query_manager->editor ) ? 'YES' : 'NO' ) . '</li>';
                
                if ( isset( $query_manager->editor ) ) {
                    echo '<li>Editor class: ' . get_class( $query_manager->editor ) . '</li>';
                    echo '<li>get_types() method exists: ' . ( method_exists( $query_manager->editor, 'get_types' ) ? 'YES' : 'NO' ) . '</li>';
                    
                    if ( method_exists( $query_manager->editor, 'get_types' ) ) {
                        $types_count = count( $query_manager->editor->get_types() );
                        echo '<li>Types count: ' . $types_count . '</li>';
                    } else {
                        echo '<li style="color: red;">get_types() method NOT AVAILABLE!</li>';
                    }
                } else {
                    echo '<li style="color: red;">Editor property NOT SET!</li>';
                }
                echo '</ul></div>';
                
                if ( $query_manager && isset( $query_manager->editor ) && method_exists( $query_manager->editor, 'get_types' ) ) {
                    $types = $query_manager->editor->get_types();
                    ?>
                    <div class="success-box">
                        <strong>Registered Query Types (<?php echo count( $types ); ?>):</strong>
                        <ul>
                            <?php foreach ( $types as $type_id => $type_instance ): ?>
                                <li>
                                    <strong><?php echo $type_id; ?>:</strong> 
                                    <?php echo $type_instance->get_name(); ?>
                                    <?php if ( $type_id === 'mac-menu' ): ?>
                                        <span class="status success">✓ MAC MENU FOUND!</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <?php if ( ! isset( $types['mac-menu'] ) ): ?>
                        <div class="error-box">
                            <strong>⚠️ Mac Menu query type NOT REGISTERED!</strong>
                            <p><strong>Possible causes:</strong></p>
                            <ul>
                                <li>Hook <code>jet-engine/query-builder/init</code> chưa được fire</li>
                                <li>Có lỗi trong Editor hoặc Query class</li>
                                <li>Timing issue - hook chạy quá sớm/muộn</li>
                            </ul>
                            <p><strong>Solution:</strong> Click nút bên dưới để force register</p>
                            <p>
                                <a href="<?php echo plugins_url( 'force-register-jetengine.php', dirname( __FILE__ ) ); ?>" 
                                   class="button button-primary" target="_blank">
                                    Force Register Now
                                </a>
                            </p>
                        </div>
                    <?php endif; ?>
                <?php } else { ?>
                    <div class="error-box">
                        <strong>Cannot access JetEngine Query Editor types</strong>
                        <p>Query Manager hoặc Editor chưa được khởi tạo đúng cách.</p>
                        <p><strong>This usually means:</strong></p>
                        <ul>
                            <li>JetEngine chưa hoàn tất quá trình initialization</li>
                            <li>Hook <code>jet-engine/query-builder/init</code> chưa được fire</li>
                        </ul>
                        <p><strong>Try:</strong></p>
                        <ol>
                            <li>Reload page này</li>
                            <li>Hoặc vào <strong>JetEngine → Query Builder</strong> trước, sau đó quay lại đây</li>
                            <li>Hoặc click Force Register bên dưới</li>
                        </ol>
                        <p>
                            <a href="<?php echo plugins_url( 'force-register-jetengine.php', dirname( __FILE__ ) ); ?>" 
                               class="button button-primary" target="_blank">
                                Force Register Now
                            </a>
                        </p>
                    </div>
                <?php } ?>
            <?php else: ?>
                <div class="error-box">
                    <strong>JetEngine not active - cannot check query types</strong>
                </div>
            <?php endif; ?>

            <h2>7. Action Hooks</h2>
            <?php
            global $wp_filter;
            $has_init_hook = isset( $wp_filter['jet-engine/query-builder/init'] );
            $has_gateway_hook = isset( $wp_filter['jet-engine-query-gateway/do-item'] );
            ?>
            <ul>
                <li><strong>jet-engine/query-builder/init:</strong> 
                    <?php if ( $has_init_hook ): ?>
                        <span class="status success">REGISTERED</span>
                        <?php 
                        $callbacks = $wp_filter['jet-engine/query-builder/init']->callbacks;
                        foreach ( $callbacks as $priority => $hooks ) {
                            foreach ( $hooks as $hook ) {
                                if ( is_array( $hook['function'] ) && is_object( $hook['function'][0] ) ) {
                                    $class = get_class( $hook['function'][0] );
                                    if ( strpos( $class, 'Mac_Menu' ) !== false ) {
                                        echo '<br>&nbsp;&nbsp;&nbsp;&nbsp;Priority: ' . $priority . ', Class: ' . $class;
                                    }
                                }
                            }
                        }
                        ?>
                    <?php else: ?>
                        <span class="status error">NOT REGISTERED</span>
                    <?php endif; ?>
                </li>
                
                <li><strong>jet-engine-query-gateway/do-item:</strong> 
                    <?php if ( $has_gateway_hook ): ?>
                        <span class="status success">REGISTERED</span>
                    <?php else: ?>
                        <span class="status warning">NOT REGISTERED</span>
                    <?php endif; ?>
                </li>
            </ul>

            <h2>8. Troubleshooting</h2>
            <?php if ( ! class_exists( '\Jet_Engine\Query_Builder\Manager' ) ): ?>
                <div class="error-box">
                    <strong>❌ JetEngine chưa được kích hoạt</strong>
                    <p>Vui lòng cài đặt và kích hoạt plugin JetEngine trước.</p>
                </div>
            <?php elseif ( ! class_exists( 'Mac_Menu_JetEngine_Integration' ) ): ?>
                <div class="error-box">
                    <strong>❌ Integration class chưa được load</strong>
                    <p>Kiểm tra file: <code><?php echo $integration_file; ?></code></p>
                </div>
            <?php elseif ( class_exists( '\Jet_Engine\Query_Builder\Manager' ) && isset( $types ) && ! isset( $types['mac-menu'] ) ): ?>
                <div class="error-box">
                    <strong>❌ Mac Menu query type chưa được đăng ký</strong>
                    <p>Các nguyên nhân có thể:</p>
                    <ul>
                        <li>Hook <code>jet-engine/query-builder/init</code> chưa được call</li>
                        <li>Có lỗi trong file Editor hoặc Query class</li>
                        <li>Check PHP error log tại: <code>wp-content/debug.log</code></li>
                    </ul>
                    <p><strong>Giải pháp:</strong></p>
                    <ol>
                        <li>Bật WordPress Debug mode trong <code>wp-config.php</code>:
                            <pre>define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );</pre>
                        </li>
                        <li>Reload trang này</li>
                        <li>Kiểm tra file <code>wp-content/debug.log</code></li>
                    </ol>
                </div>
            <?php else: ?>
                <div class="success-box">
                    <strong>✅ Tất cả đều OK!</strong>
                    <p>Mac Menu query type đã được đăng ký thành công với JetEngine.</p>
                    <p>Bạn có thể sử dụng nó trong Query Builder:</p>
                    <ol>
                        <li>Vào <strong>JetEngine → Query Builder</strong></li>
                        <li>Click <strong>Add New Query</strong></li>
                        <li>Trong dropdown <strong>Query Type</strong>, chọn <strong>Mac Menu Categories</strong></li>
                    </ol>
                </div>
            <?php endif; ?>

            <a href="<?php echo admin_url( 'admin.php?page=mac-cat-menu' ); ?>" class="back-link">← Quay lại Mac Menu</a>
        </div>
    </body>
    </html>
    <?php
}

