<?php
/**
 * Mac Menu Query Class
 * 
 * Xử lý logic query Mac Menu categories cho JetEngine
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load Base_Query class nếu chưa có
if ( ! class_exists( '\Jet_Engine\Query_Builder\Queries\Base_Query' ) ) {
    // Try multiple ways to find the base file
    $base_file = null;
    
    // Method 1: Use jet_engine() function
    if ( function_exists( 'jet_engine' ) ) {
        $base_file = jet_engine()->plugin_path( 'includes/components/query-builder/queries/base.php' );
    }
    
    // Method 2: Direct path from plugin directory
    if ( ! $base_file || ! file_exists( $base_file ) ) {
        $base_file = WP_PLUGIN_DIR . '/jet-engine/includes/components/query-builder/queries/base.php';
    }
    
    // Method 3: Relative from current file
    if ( ! $base_file || ! file_exists( $base_file ) ) {
        $base_file = dirname( dirname( dirname( __FILE__ ) ) ) . '/jet-engine/includes/components/query-builder/queries/base.php';
    }
    
    if ( $base_file && file_exists( $base_file ) ) {
        require_once $base_file;
    }
}

use Jet_Engine\Query_Builder\Queries\Base_Query;

class Mac_Menu_Query extends Base_Query {

    private $mac_menu;

    public function __construct( $args = array() ) {
        parent::__construct( $args );
        // Không khởi tạo macMenu ở đây, sẽ khởi tạo khi cần
    }
    
    /**
     * Get macMenu instance (lazy load)
     */
    private function get_mac_menu() {
        if ( ! $this->mac_menu && class_exists( 'macMenu' ) ) {
            $this->mac_menu = new macMenu();
        }
        return $this->mac_menu;
    }

    /**
     * Get items từ database
     * 
     * @return array Danh sách Mac Menu categories
     */
    public function _get_items() {
        
        global $wpdb;
        
        $this->setup_query();
        
        $mac_menu = $this->get_mac_menu();
        if ( ! $mac_menu ) {
            return array();
        }
        
        $table_name = $mac_menu->get_cat_menu_name();
        
        // Base query
        $sql = "SELECT * FROM {$table_name} WHERE `is_hidden` = '0'";
        
        // Debug: Log query args để kiểm tra
        error_log( 'Mac Menu Query - final_query: ' . print_r( $this->final_query, true ) );
        
        // Filter: Chỉ lấy parents categories (parents_category = 0)
        if ( ! empty( $this->final_query['parents_only'] ) ) {
            $sql .= " AND `parents_category` = '0'";
        }
        
        // Filter: Lấy theo parent ID cụ thể
        if ( isset( $this->final_query['parent_id'] ) && $this->final_query['parent_id'] !== '' ) {
            $parent_id = absint( $this->final_query['parent_id'] );
            $sql .= $wpdb->prepare( " AND `parents_category` = %d", $parent_id );
        }
        
        // Filter: Tìm kiếm theo ID (ưu tiên)
        // Kiểm tra cả string rỗng và trim để loại bỏ khoảng trắng
        $search_ids = isset( $this->final_query['search_ids'] ) ? trim( $this->final_query['search_ids'] ) : '';
        if ( ! empty( $search_ids ) ) {
            
            // Parse IDs: có thể là single ID hoặc comma-separated
            $ids = array();
            if ( is_string( $search_ids ) ) {
                // Split by comma và trim
                $ids = array_map( 'trim', explode( ',', $search_ids ) );
                $ids = array_filter( $ids, function( $id ) {
                    return is_numeric( $id ) && $id > 0;
                } );
                $ids = array_map( 'intval', $ids );
            } elseif ( is_array( $search_ids ) ) {
                $ids = array_map( 'intval', array_filter( $search_ids, 'is_numeric' ) );
            } elseif ( is_numeric( $search_ids ) ) {
                $ids = array( absint( $search_ids ) );
            }
            
            if ( ! empty( $ids ) ) {
                $ids_placeholder = implode( ',', array_map( 'absint', $ids ) );
                $sql .= " AND `id` IN ({$ids_placeholder})";
            }
        }
        // Filter: Tìm kiếm theo tên (chỉ chạy nếu không có search_ids)
        $search = isset( $this->final_query['search'] ) ? trim( $this->final_query['search'] ) : '';
        if ( empty( $search_ids ) && ! empty( $search ) ) {
            
            // Parse names: có thể là single name hoặc comma-separated
            $names = array();
            if ( is_string( $search ) ) {
                // Split by comma và trim
                $names = array_map( 'trim', explode( ',', $search ) );
                $names = array_filter( $names, function( $name ) {
                    return ! empty( $name );
                } );
            } elseif ( is_array( $search ) ) {
                $names = array_filter( $search, function( $name ) {
                    return ! empty( $name );
                } );
            } else {
                $names = array( $search );
            }
            
            if ( ! empty( $names ) ) {
                // Build LIKE conditions cho mỗi name
                $like_conditions = array();
                foreach ( $names as $name ) {
                    $escaped_name = $wpdb->esc_like( $name );
                    $like_conditions[] = $wpdb->prepare( "`category_name` LIKE %s", '%' . $escaped_name . '%' );
                }
                
                if ( ! empty( $like_conditions ) ) {
                    $sql .= " AND (" . implode( " OR ", $like_conditions ) . ")";
                }
            }
        }
        
        // Order By
        $order_by = ! empty( $this->final_query['order_by'] ) ? $this->final_query['order_by'] : 'order';
        $allowed_order_by = array( 'id', 'category_name', 'order' );
        
        if ( ! in_array( $order_by, $allowed_order_by ) ) {
            $order_by = 'order';
        }
        
        // Order direction
        $order = ! empty( $this->final_query['order'] ) ? strtoupper( $this->final_query['order'] ) : 'ASC';
        
        if ( ! in_array( $order, array( 'ASC', 'DESC' ) ) ) {
            $order = 'ASC';
        }
        
        $sql .= " ORDER BY `{$order_by}` {$order}";
        
        // Limit & Offset cho pagination
        if ( ! empty( $this->final_query['limit'] ) ) {
            $limit = absint( $this->final_query['limit'] );
            $offset = ! empty( $this->final_query['offset'] ) ? absint( $this->final_query['offset'] ) : 0;
            
            $sql .= $wpdb->prepare( " LIMIT %d OFFSET %d", $limit, $offset );
        }
        
        // Execute query
        error_log( 'Mac Menu Query - Final SQL: ' . $sql );
        $results = $wpdb->get_results( $sql );
        error_log( 'Mac Menu Query - Results count: ' . ( $results ? count( $results ) : 0 ) );
        
        // Format kết quả
        if ( $results ) {
            foreach ( $results as &$item ) {
                // Parse JSON fields nếu có
                if ( ! empty( $item->group_repeater ) && is_string( $item->group_repeater ) ) {
                    $item->group_repeater = json_decode( $item->group_repeater, true );
                }
                
                if ( ! empty( $item->data_table ) && is_string( $item->data_table ) ) {
                    $item->data_table = json_decode( $item->data_table, true );
                }
                
            }
        }
        
        return $results ? $results : array();
    }

    /**
     * Get total count (cho pagination)
     */
    public function get_items_total_count() {
        
        $cached = $this->get_cached_data( 'count' );
        
        if ( false !== $cached ) {
            return $cached;
        }
        
        global $wpdb;
        
        $this->setup_query();
        
        $mac_menu = $this->get_mac_menu();
        if ( ! $mac_menu ) {
            return 0;
        }
        
        $table_name = $mac_menu->get_cat_menu_name();
        
        // Count query
        $sql = "SELECT COUNT(*) FROM {$table_name} WHERE `is_hidden` = '0'";
        
        // Apply same filters as _get_items
        if ( ! empty( $this->final_query['parents_only'] ) ) {
            $sql .= " AND `parents_category` = '0'";
        }
        
        if ( isset( $this->final_query['parent_id'] ) && $this->final_query['parent_id'] !== '' ) {
            $parent_id = absint( $this->final_query['parent_id'] );
            $sql .= $wpdb->prepare( " AND `parents_category` = %d", $parent_id );
        }
        
        // Filter: Tìm kiếm theo ID (ưu tiên)
        // Kiểm tra cả string rỗng và trim để loại bỏ khoảng trắng
        $search_ids = isset( $this->final_query['search_ids'] ) ? trim( $this->final_query['search_ids'] ) : '';
        if ( ! empty( $search_ids ) ) {
            // Parse IDs: có thể là single ID hoặc comma-separated
            $ids = array();
            if ( is_string( $search_ids ) ) {
                // Split by comma và trim
                $ids = array_map( 'trim', explode( ',', $search_ids ) );
                $ids = array_filter( $ids, function( $id ) {
                    return is_numeric( $id ) && $id > 0;
                } );
                $ids = array_map( 'intval', $ids );
            } elseif ( is_array( $search_ids ) ) {
                $ids = array_map( 'intval', array_filter( $search_ids, 'is_numeric' ) );
            } elseif ( is_numeric( $search_ids ) ) {
                $ids = array( absint( $search_ids ) );
            }
            
            if ( ! empty( $ids ) ) {
                $ids_placeholder = implode( ',', array_map( 'absint', $ids ) );
                $sql .= " AND `id` IN ({$ids_placeholder})";
            }
        }
        // Filter: Tìm kiếm theo tên (chỉ chạy nếu không có search_ids)
        $search = isset( $this->final_query['search'] ) ? trim( $this->final_query['search'] ) : '';
        if ( empty( $search_ids ) && ! empty( $search ) ) {
            // Parse names: có thể là single name hoặc comma-separated
            $names = array();
            if ( is_string( $search ) ) {
                // Split by comma và trim
                $names = array_map( 'trim', explode( ',', $search ) );
                $names = array_filter( $names, function( $name ) {
                    return ! empty( $name );
                } );
            } elseif ( is_array( $search ) ) {
                $names = array_filter( $search, function( $name ) {
                    return ! empty( $name );
                } );
            } else {
                $names = array( $search );
            }
            
            if ( ! empty( $names ) ) {
                // Build LIKE conditions cho mỗi name
                $like_conditions = array();
                foreach ( $names as $name ) {
                    $escaped_name = $wpdb->esc_like( $name );
                    $like_conditions[] = $wpdb->prepare( "`category_name` LIKE %s", '%' . $escaped_name . '%' );
                }
                
                if ( ! empty( $like_conditions ) ) {
                    $sql .= " AND (" . implode( " OR ", $like_conditions ) . ")";
                }
            }
        }
        
        $count = $wpdb->get_var( $sql );
        
        $this->update_query_cache( $count, 'count' );
        
        return absint( $count );
    }

    /**
     * Get items per page
     */
    public function get_items_per_page() {
        $this->setup_query();
        return ! empty( $this->final_query['limit'] ) ? absint( $this->final_query['limit'] ) : 10;
    }

    /**
     * Get current page
     */
    public function get_current_items_page() {
        if ( ! empty( $this->final_query['_page'] ) ) {
            return absint( $this->final_query['_page'] );
        }
        return 1;
    }

    /**
     * Get items page count (tổng số pages)
     */
    public function get_items_page_count() {
        $total = $this->get_items_total_count();
        $per_page = $this->get_items_per_page();
        
        return $per_page > 0 ? ceil( $total / $per_page ) : 1;
    }

    /**
     * Get items pages count (alias for get_items_page_count)
     */
    public function get_items_pages_count() {
        return $this->get_items_page_count();
    }

    /**
     * Get item ID (unique identifier)
     */
    public function get_item_id( $item ) {
        if ( is_object( $item ) && isset( $item->id ) ) {
            return $item->id;
        }
        return null;
    }

    /**
     * Set filtered property (required by JetEngine base class)
     */
    public function set_filtered_prop( $prop = '', $value = null ) {
        // Mac Menu query không cần filter động trong runtime
        // Tất cả filters được set qua query args trong Query Builder
        return false;
    }
}

