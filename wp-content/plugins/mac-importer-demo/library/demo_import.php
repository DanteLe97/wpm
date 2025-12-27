<?php
// Đăng ký AJAX action cho custom demo import form
add_action('wp_ajax_nopriv_bk_custom_demo_import', 'bk_custom_demo_import');
add_action('wp_ajax_bk_custom_demo_import', 'bk_custom_demo_import');

if ( ! function_exists('bk_custom_demo_import') ) {
    function bk_custom_demo_import() {
        // Kiểm tra nonce để bảo mật
        if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'bk_custom_demo_import' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ) );
        }
        
        // Tăng timeout và memory limit cho import process
        set_time_limit( 300 ); // 5 phút
        ini_set( 'memory_limit', '512M' );
        
        $page_names = isset( $_POST['page_names'] ) ? $_POST['page_names'] : array();
        $import_pages = isset( $_POST['import_pages'] ) ? (bool) $_POST['import_pages'] : true;
        $import_template_kit = isset( $_POST['import_template_kit'] ) ? (bool) $_POST['import_template_kit'] : true;
        $create_pages = isset( $_POST['create_pages'] ) ? (bool) $_POST['create_pages'] : true;
        $overwrite_existing = true; // Luôn overwrite existing pages
        
        // Template kit selection - always use minimal-kit
        $template_kit_select = 'minimal-kit';
        
        $results = array();
        $errors = array();
        $warnings = array();
        $success_count = 0;
        $skipped_count = 0;
        
        // Kiểm tra xem có file page nào được upload thực sự không
        $has_page_files = ! empty( $_FILES['page_files'] ) && 
                         ( is_array( $_FILES['page_files']['tmp_name'] ) ? ! empty( $_FILES['page_files']['tmp_name'][0] ) : false );
        
        // Import pages từ JSON files nếu có file được upload
        if ( $has_page_files ) {
            $page_slugs = array( 'home', 'about', 'services', 'gallery', 'contact' );
            $total_pages = count( $_FILES['page_files']['tmp_name'] );
            
            error_log( 'Starting import of ' . $total_pages . ' pages' );
            
            foreach ( $_FILES['page_files']['tmp_name'] as $index => $tmp_name ) {
                $page_name = isset( $page_names[$index] ) ? trim( $page_names[$index] ) : '';
                $file_name = $_FILES['page_files']['name'][$index];
                $file_error = $_FILES['page_files']['error'][$index];
                
                error_log( 'Processing page ' . ($index + 1) . ' of ' . $total_pages . ': ' . $page_name );
                
                // Bỏ qua nếu không có file
                if ( empty( $tmp_name ) || $file_error !== UPLOAD_ERR_OK ) {
                    $skipped_count++;
                    $display_name = ! empty( $page_name ) ? $page_name : 'Page ' . ($index + 1);
                    $warnings[] = $display_name . ': Skipped - No file uploaded or upload error.';
                    continue;
                }
                
                // Validate file type
                $file_extension = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
                if ( $file_extension !== 'json' ) {
                    $display_name = ! empty( $page_name ) ? $page_name : 'Page ' . ($index + 1);
                    $errors[] = $display_name . ': Invalid file type. Only JSON files are allowed.';
                    continue;
                }
                
                // Tạo slug từ page name hoặc sử dụng default
                if ( ! empty( $page_name ) ) {
                    $page_slug = sanitize_title( $page_name );
                } else {
                    $page_slug = isset( $page_slugs[$index] ) ? $page_slugs[$index] : 'page-' . ($index + 1);
                    $page_name = ucwords( str_replace( '-', ' ', $page_slug ) );
                }
                
                // Import từng trang một cách tuần tự
                $result = import_page_from_file( $tmp_name, $page_slug, $page_name, $create_pages, $overwrite_existing );
                
                if ( is_wp_error( $result ) ) {
                    $error_msg = $page_name . ': ' . $result->get_error_message();
                    $errors[] = $error_msg;
                    error_log( 'Import failed for ' . $page_name . ': ' . $result->get_error_message() );
                } else {
                    $success_count++;
                    $results[] = $page_name . ' imported successfully.';
                    error_log( 'Import successful for ' . $page_name . ' (ID: ' . $result . ')' );
                }
                
                // Thêm delay nhỏ giữa các trang để tránh overload
                if ( $index < $total_pages - 1 ) {
                    usleep( 500000 ); // 0.5 giây delay
                }
                
                // Kiểm tra timeout sau mỗi trang
                if ( time() - $_SERVER['REQUEST_TIME'] > 240 ) { // 4 phút
                    $warnings[] = 'Import stopped due to timeout. Only ' . ($index + 1) . ' of ' . $total_pages . ' pages processed.';
                    break;
                }
            }
            
            error_log( 'Completed import of ' . $success_count . ' pages successfully' );
        }
        
        // Import Template Kit (always minimal-kit)
        error_log( 'Starting template kit import for: ' . $template_kit_select );
        
        // Kiểm tra kit có tồn tại không
        $kit_path = plugin_dir_path( __FILE__ ) . '../template-kits/' . $template_kit_select;
        if ( ! is_dir( $kit_path ) ) {
            $errors[] = 'Template Kit: Selected kit "' . $template_kit_select . '" not found.';
        } else {
            // Xử lý site-settings file nếu có upload
            $site_settings_file = null;
            if ( ! empty( $_FILES['site_settings_file']['tmp_name'] ) ) {
                $site_settings_file = $_FILES['site_settings_file'];
                
                // Validate file type
                $file_extension = strtolower( pathinfo( $site_settings_file['name'], PATHINFO_EXTENSION ) );
                if ( $file_extension !== 'json' ) {
                    $errors[] = 'Site Settings: Invalid file type. Only JSON files are allowed.';
                } else {
                    // Validate JSON content
                    $json_content = file_get_contents( $site_settings_file['tmp_name'] );
                    $json_data = json_decode( $json_content, true );
                    if ( is_null( $json_data ) ) {
                        $errors[] = 'Site Settings: Invalid JSON format.';
                    }
                }
            }
            
            // Import template kit
            $kit_result = bk_import_template_kit_from_directory( $template_kit_select, $site_settings_file );
            
            if ( is_wp_error( $kit_result ) ) {
                $errors[] = 'Template Kit: ' . $kit_result->get_error_message();
                error_log( 'Template kit import failed: ' . $kit_result->get_error_message() );
            } else {
                $success_count++;
                if ( is_array( $kit_result ) && isset( $kit_result['message'] ) ) {
                    $results[] = 'Template kit "' . $template_kit_select . '" imported successfully. ' . $kit_result['message'];
                } else {
                    $results[] = 'Template kit "' . $template_kit_select . '" imported successfully.';
                }
                error_log( 'Template kit import successful' );
            }
        }
        
        // Kiểm tra xem có option nào được chọn không (minimal-kit is always selected)
        if ( ! $has_page_files ) {
            // minimal-kit is always selected, so we can proceed
        }
        
        // Tạo message tổng hợp
        $message = '';
        
        if ( $success_count > 0 ) {
            $message .= '<div class="success-summary">✅ Import completed successfully! ' . $success_count . ' items imported.</div>';
        }
        
        if ( $skipped_count > 0 ) {
            $message .= '<div class="warning-summary">⚠️ ' . $skipped_count . ' items skipped (no file uploaded).</div>';
        }
        
        if ( ! empty( $warnings ) ) {
            $message .= '<div class="warnings-section"><h4>Warnings:</h4><ul><li>' . implode( '</li><li>', $warnings ) . '</li></ul></div>';
        }
        
        if ( ! empty( $results ) ) {
            $message .= '<div class="success-section"><h4>Success Details:</h4><ul><li>' . implode( '</li><li>', $results ) . '</li></ul></div>';
        }
        
        if ( ! empty( $errors ) ) {
            $message .= '<div class="error-section"><h4>Errors:</h4><ul><li>' . implode( '</li><li>', $errors ) . '</li></ul></div>';
        }
        
        error_log( 'Import process completed. Success: ' . $success_count . ', Errors: ' . count( $errors ) );
        
        // Trả về kết quả
        if ( empty( $errors ) ) {
            wp_send_json_success( array( 'message' => $message ) );
        } else {
            wp_send_json_error( array( 'message' => $message ) );
        }
    }
}

/**
 * Import template Elementor từ file JSON qua API của Elementor
 * và trả về ID của template vừa tạo.
 *
 * @param string $file_path Đường dẫn đầy đủ tới file JSON.
 * @param string $template_title Tiêu đề cho template được import.
 * @return int|WP_Error ID của template vừa tạo hoặc WP_Error nếu có lỗi.
 */
function import_elementor_template_from_json( $file_path, $template_title = 'Imported Template' ) {
    if ( ! file_exists( $file_path ) ) {
        return new WP_Error('file_not_found', 'File JSON không tồn tại: ' . $file_path);
    }
    
    // Đọc nội dung file JSON
    $json_data = file_get_contents( $file_path );
    
    // Kiểm tra dữ liệu JSON hợp lệ
    $template_data = json_decode( $json_data, true );
    if ( is_null( $template_data ) ) {
        return new WP_Error('json_error', 'Lỗi trong quá trình decode JSON');
    }
    
    if ( ! class_exists('Elementor\Plugin') ) {
        return new WP_Error('elementor_not_active', 'Elementor không hoạt động.');
    }
    
    // Dùng phương thức import của templates_manager của Elementor
    $template_manager = \Elementor\Plugin::$instance->templates_manager;
    $imported_template = $template_manager->import_template( [
        // Sử dụng file_get_contents để lấy nội dung file rồi mã hóa nó với base64_encode
        'fileData' => base64_encode( $json_data ),
        'fileName' => basename($file_path),
    ] );
    
    if ( is_wp_error($imported_template) ) {
        return $imported_template;
    }
    // Cập nhật tiêu đề của template nếu cần
    $template_id = $imported_template[0]['template_id'];

    if ( is_wp_error( $template_id ) ) {
        error_log('Import error: ' . $template_id->get_error_message());
    } else {
        error_log('Template ID: ' . $template_id);
    }

    wp_update_post( array(
        'ID'         => $template_id,
        'post_title' => $template_title,
    ) );
    
    return $template_id;
}

/**
 * Import page từ file JSON
 */
function import_page_from_file( $file_path, $page_slug, $page_name, $create_pages = true, $overwrite_existing = false ) {
    // Đọc nội dung file JSON
    $json_content = file_get_contents( $file_path );
    
    if ( $json_content === false ) {
        return new WP_Error( 'file_read_error', 'Failed to read JSON file.' );
    }
    
    // Debug: Log file content length
    error_log( 'JSON file content length: ' . strlen( $json_content ) );
    
    // Debug: Log first 1000 characters of JSON content
    error_log( 'JSON content preview: ' . substr( $json_content, 0, 1000 ) );
    
    // Kiểm tra file có rỗng không
    if ( empty( trim( $json_content ) ) ) {
        return new WP_Error( 'json_error', 'JSON file is empty.' );
    }
    
    // Debug: Check for common JSON issues
    error_log( 'JSON content starts with: ' . substr( $json_content, 0, 50 ) );
    error_log( 'JSON content ends with: ' . substr( $json_content, -50 ) );
    
    // Thử decode JSON gốc trước
    $template_data = json_decode( $json_content, true );
    
    // Nếu JSON gốc không decode được, mới thử sửa lỗi
    if ( is_null( $template_data ) ) {
        error_log( 'Original JSON failed to decode, attempting to fix...' );
        
        $original_content = $json_content;
        $json_content = fix_elementor_json( $json_content );
        
        // Debug: Log if content was modified
        if ( $original_content !== $json_content ) {
            error_log( 'JSON content was modified by fix_elementor_json function' );
            error_log( 'Modified JSON preview: ' . substr( $json_content, 0, 500 ) );
        }
        
        // Thử decode lại sau khi sửa
        $template_data = json_decode( $json_content, true );
    } else {
        error_log( 'Original JSON decode successful, no fixing needed' );
    }
    
    // Kiểm tra lỗi JSON
    if ( is_null( $template_data ) ) {
        $json_error = json_last_error();
        $json_error_msg = json_last_error_msg();
        
        error_log( 'JSON decode error: ' . $json_error . ' - ' . $json_error_msg );
        error_log( 'JSON content that failed: ' . substr( $json_content, 0, 1000 ) );
        
        // Trả về thông báo lỗi chi tiết
        switch ( $json_error ) {
            case JSON_ERROR_DEPTH:
                return new WP_Error( 'json_error', 'JSON file has maximum stack depth exceeded.' );
            case JSON_ERROR_STATE_MISMATCH:
                return new WP_Error( 'json_error', 'JSON file has invalid or malformed JSON.' );
            case JSON_ERROR_CTRL_CHAR:
                return new WP_Error( 'json_error', 'JSON file contains control character error.' );
            case JSON_ERROR_SYNTAX:
                return new WP_Error( 'json_error', 'JSON syntax error: ' . $json_error_msg . '. Please check your Elementor export.' );
            case JSON_ERROR_UTF8:
                return new WP_Error( 'json_error', 'JSON file contains invalid UTF-8 characters.' );
            default:
                return new WP_Error( 'json_error', 'Invalid JSON format: ' . $json_error_msg );
        }
    }
    
    // Debug: Log successful decode
    error_log( 'JSON decode successful. Template data type: ' . gettype( $template_data ) );
    
    // Kiểm tra và chuẩn hóa dữ liệu Elementor template
    $template_data = normalize_elementor_template_data( $template_data );
    
    if ( is_wp_error( $template_data ) ) {
        return $template_data;
    }
    
    // Debug: Log template structure
    error_log( 'Template data type: ' . gettype( $template_data ) );
    if ( is_array( $template_data ) ) {
        error_log( 'Template data keys: ' . implode( ', ', array_keys( $template_data ) ) );
    }
    
    // Luôn tạo trang mới, tránh ghi đè hoặc bỏ qua nếu tồn tại
    $unique_slug = $page_slug;
    $suffix = 2;
    while ( get_page_by_path( $unique_slug, OBJECT, 'page' ) ) {
        $unique_slug = $page_slug . '-' . $suffix;
        $suffix++;
    }

    $page_data = array(
        'post_title'   => $page_name,
        'post_name'    => $unique_slug,
        'post_status'  => 'publish',
        'post_type'    => 'page',
    );

    $page_id = wp_insert_post( $page_data );
    
    if ( is_wp_error( $page_id ) ) {
        return $page_id;
    }
    
    // Áp dụng template Elementor
    if ( class_exists( 'Elementor\Plugin' ) ) {
        $result = apply_elementor_template_to_page( $template_data, $page_id );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
    }
    
    return $page_id;
}

/**
 * Áp dụng template Elementor vào trang
 */
function apply_elementor_template_to_page( $template_data, $page_id ) {
    if ( ! class_exists( 'Elementor\Plugin' ) ) {
        return new WP_Error( 'elementor_not_active', 'Elementor is not active.' );
    }
    
    // Chuẩn hóa dữ liệu template
    $normalized_data = normalize_elementor_template_data( $template_data );
    if ( is_wp_error( $normalized_data ) ) {
        return $normalized_data;
    }
    
    // Tạo document cho trang
    $document = \Elementor\Plugin::$instance->documents->get( $page_id );
    if ( ! $document ) {
        return new WP_Error( 'document_not_found', 'Could not get document for page ID: ' . $page_id );
    }
    
    // Cập nhật content của trang với template data
    // Tạm tắt Content Sanitizer của Elementor để tránh loại bỏ containers khi import
    $bk_disable_sanitizer_cb = function() { return false; };
    add_filter( 'elementor/content_sanitizer/enabled', $bk_disable_sanitizer_cb, 10, 0 );
    // Dùng callback gán vào biến để có thể remove đúng cách
    $bk_enable_container_cb = function($settings){
        if (is_array($settings)) {
            if (!isset($settings['features'])) { $settings['features'] = array(); }
            $settings['features']['container'] = true;
        }
        return $settings;
    };
    add_filter( 'elementor/editor/localize_settings', $bk_enable_container_cb, 10, 1 );

    // Lưu nội dung mà không ép template = elementor_canvas để tránh trắng trang do theme
    $document->save( array(
        'elements' => $normalized_data,
    ) );

    // Khôi phục các filter tạm thời
    remove_filter( 'elementor/content_sanitizer/enabled', $bk_disable_sanitizer_cb, 10 );
    remove_filter( 'elementor/editor/localize_settings', $bk_enable_container_cb, 10 );
    
    // Fallback: nếu vì lý do nào đó _elementor_data chưa được Elementor ghi, tự ghi thủ công
    $current_data_meta = get_post_meta( $page_id, '_elementor_data', true );
    if ( empty( $current_data_meta ) ) {
        $json_elements = wp_json_encode( $normalized_data );
        if ( is_string( $json_elements ) ) {
            update_post_meta( $page_id, '_elementor_data', wp_slash( $json_elements ) );
            error_log( 'Elementor data was empty after save; wrote fallback meta. Bytes: ' . strlen( $json_elements ) );
        }
    } else {
        error_log( 'Elementor data meta present. Bytes: ' . strlen( is_string($current_data_meta) ? $current_data_meta : json_encode($current_data_meta) ) );
    }

    // Cập nhật meta data
    update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
    update_post_meta( $page_id, '_elementor_template_type', 'page' );
    // Ghi phiên bản Elementor để đồng bộ
    if ( defined('ELEMENTOR_VERSION') ) {
        update_post_meta( $page_id, '_elementor_version', ELEMENTOR_VERSION );
    }
    
    // Clear cache
    \Elementor\Plugin::$instance->files_manager->clear_cache();
    
    error_log( 'Template applied successfully to page ID: ' . $page_id );
    
    return true;
}

/**
 * Chuẩn hóa dữ liệu Elementor template từ các định dạng khác nhau
 */
function normalize_elementor_template_data( $template_data ) {
    // Format 1: Array of elements (most common)
    if ( is_array( $template_data ) && ! empty( $template_data ) ) {
        // Kiểm tra xem có phải array của elements không
        if ( isset( $template_data[0]['elType'] ) || isset( $template_data[0]['id'] ) ) {
            return $template_data;
        }
        // Nếu array rỗng hoặc không phải elements
        if ( empty( $template_data ) ) {
            return new WP_Error( 'json_error', 'JSON array is empty or does not contain valid Elementor elements.' );
        }
    }
    
    // Format 2: Object with content property
    if ( is_array( $template_data ) && isset( $template_data['content'] ) ) {
        if ( is_array( $template_data['content'] ) ) {
            return $template_data['content'];
        }
        return new WP_Error( 'json_error', 'Content property is not a valid array.' );
    }
    
    // Format 3: Direct object with Elementor structure
    if ( is_array( $template_data ) && ( isset( $template_data['elements'] ) || isset( $template_data['settings'] ) ) ) {
        // Nếu có elements, trả về elements
        if ( isset( $template_data['elements'] ) && is_array( $template_data['elements'] ) ) {
            return $template_data['elements'];
        }
        // Nếu chỉ có settings, tạo wrapper
        if ( isset( $template_data['settings'] ) ) {
            return array( $template_data );
        }
    }
    
    // Format 4: Single element object
    if ( is_array( $template_data ) && isset( $template_data['elType'] ) ) {
        return array( $template_data );
    }
    
    // Format 5: Object (not array) - convert to array
    if ( ! is_array( $template_data ) && is_object( $template_data ) ) {
        $template_data = (array) $template_data;
        return normalize_elementor_template_data( $template_data );
    }
    
    // Nếu không match với format nào
    return new WP_Error( 'json_error', 'JSON file does not contain valid Elementor template data. Please check your export format.' );
}

/**
 * Sửa lỗi JSON từ Elementor export
 */
function fix_elementor_json( $json_content ) {
    // Debug: Log original content length
    error_log( 'fix_elementor_json: Original content length: ' . strlen( $json_content ) );
    
    // Loại bỏ BOM nếu có
    $json_content = str_replace( "\xEF\xBB\xBF", '', $json_content );
    
    // Loại bỏ các ký tự control không hợp lệ
    $json_content = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $json_content );
    
    // Loại bỏ các dòng trống và whitespace thừa ở đầu/cuối
    $json_content = trim( $json_content );
    
    // Sửa lỗi trailing comma (dấu phẩy thừa) - cẩn thận hơn
    $json_content = preg_replace( '/,\s*([}\]])/m', '$1', $json_content );
    
    // Sửa lỗi unescaped quotes trong strings - cải thiện regex
    $json_content = preg_replace_callback( '/"([^"]*)"([^"]*)"([^"]*)"/', function( $matches ) {
        return '"' . addslashes( $matches[1] ) . $matches[2] . addslashes( $matches[3] ) . '"';
    }, $json_content );
    
    // Sửa lỗi unescaped backslashes - cẩn thận hơn
    $json_content = str_replace( '\\', '\\\\', $json_content );
    $json_content = str_replace( '\\\\"', '\\"', $json_content );
    
    // Loại bỏ các dòng trống và whitespace thừa
    $json_content = preg_replace( '/\s+/', ' ', $json_content );
    
    // Sửa lỗi missing quotes cho property names - cải thiện regex
    $json_content = preg_replace( '/([{,]\s*)([a-zA-Z_][a-zA-Z0-9_]*)\s*:/', '$1"$2":', $json_content );
    
    // Sửa lỗi single quotes thành double quotes - cẩn thận hơn
    // Chỉ thay thế single quotes bên ngoài strings
    $json_content = preg_replace( "/(?<!\\\\)'/", '"', $json_content );
    
    // Sửa lỗi HTML entities
    $json_content = html_entity_decode( $json_content, ENT_QUOTES, 'UTF-8' );
    
    // Loại bỏ các ký tự null
    $json_content = str_replace( "\0", '', $json_content );
    
    // Sửa lỗi unclosed strings - cẩn thận hơn
    $json_content = preg_replace( '/"([^"]*)$/', '"$1"', $json_content );
    
    // Sửa lỗi unclosed objects/arrays - cải thiện logic
    $open_braces = substr_count( $json_content, '{' ) + substr_count( $json_content, '[' );
    $close_braces = substr_count( $json_content, '}' ) + substr_count( $json_content, ']' );
    
    if ( $open_braces > $close_braces ) {
        $json_content .= str_repeat( '}', $open_braces - $close_braces );
    }
    
    // Debug: Log final content length
    error_log( 'fix_elementor_json: Final content length: ' . strlen( $json_content ) );
    
    return $json_content;
}

/**
 * Import Elementor template kit từ file
 */
function import_elementor_template_kit_from_file( $file_path ) {
    if ( ! class_exists( 'Elementor\Plugin' ) ) {
        return new WP_Error( 'elementor_not_active', 'Elementor is not active.' );
    }
    
    try {
        // Mở ZIP file
        $zip = new ZipArchive();
        if ( $zip->open( $file_path ) !== true ) {
            return new WP_Error( 'zip_open_failed', 'Failed to open ZIP file.' );
        }
        
        // Debug: Liệt kê tất cả file trong ZIP
        $zip_files = array();
        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $zip_files[] = $zip->getNameIndex( $i );
        }
        error_log( 'Files in ZIP: ' . implode( ', ', $zip_files ) );
        
        // Đọc site-settings.json
        $site_settings_content = $zip->getFromName( 'site-settings.json' );
        if ( ! $site_settings_content ) {
            $zip->close();
            return new WP_Error( 'no_site_settings', 'site-settings.json not found in ZIP file. Available files: ' . implode( ', ', $zip_files ) );
        }
        
        // Parse JSON
        $site_settings = json_decode( $site_settings_content, true );
        if ( is_null( $site_settings ) ) {
            $zip->close();
            return new WP_Error( 'invalid_json', 'Invalid JSON in site-settings.json.' );
        }
        
        $zip->close();
        
        // Debug: Log site settings structure
        error_log( 'Site settings structure: ' . print_r( array_keys( $site_settings ), true ) );
        
        // Import site settings vào Elementor
        $kit_id = \Elementor\Plugin::$instance->kits_manager->get_active_id();
        
        if ( ! $kit_id ) {
            // Tạo kit mới nếu chưa có
            $kit = new \Elementor\Core\Kits\Documents\Kit();
            $kit_id = $kit->save( array() );
        }
        
        if ( $kit_id ) {
            // Lấy settings từ structure đúng
            $settings = isset( $site_settings['settings'] ) ? $site_settings['settings'] : $site_settings;
            
            // Debug: Log settings keys
            error_log( 'Settings keys: ' . print_r( array_keys( $settings ), true ) );
            
            // Sử dụng Elementor Kit API để lưu settings
            $kit = \Elementor\Plugin::$instance->documents->get( $kit_id );
            
            if ( ! $kit ) {
                error_log( 'Failed to get kit document' );
                return array( 'success' => false, 'message' => 'Failed to get kit document' );
            }
            
            // Lấy settings hiện tại
            $current_settings = $kit->get_settings();
            if ( ! is_array( $current_settings ) ) {
                $current_settings = array();
            }
            
            // Merge settings mới vào settings hiện tại
            $merged_settings = array_merge( $current_settings, $settings );
            
            // Lưu settings thông qua Elementor API
            $kit->save( array( 'settings' => $merged_settings ) );
            
            // Debug log để verify
            error_log( 'Page settings saved via Elementor API with keys: ' . implode( ', ', array_keys( $merged_settings ) ) );
            
            if ( isset( $settings['system_typography'] ) && is_array( $settings['system_typography'] ) ) {
                error_log( 'System typography count: ' . count( $settings['system_typography'] ) );
                error_log( 'System typography data: ' . print_r( $settings['system_typography'], true ) );
            }
            
            if ( isset( $settings['custom_typography'] ) && is_array( $settings['custom_typography'] ) ) {
                error_log( 'Custom typography count: ' . count( $settings['custom_typography'] ) );
            }
            
            if ( isset( $settings['system_colors'] ) && is_array( $settings['system_colors'] ) ) {
                error_log( 'System colors count: ' . count( $settings['system_colors'] ) );
            }
            
            if ( isset( $settings['custom_colors'] ) && is_array( $settings['custom_colors'] ) ) {
                error_log( 'Custom colors count: ' . count( $settings['custom_colors'] ) );
            }
            
            // Enqueue fonts từ typography settings
            if ( class_exists( 'MAC_Importer_Import_API' ) ) {
                MAC_Importer_Import_API::enqueue_fonts_from_settings( $settings );
            }
            
            // Clear cache mạnh hơn
            \Elementor\Plugin::$instance->files_manager->clear_cache();
            
            // Clear all Elementor transients
            global $wpdb;
            $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_elementor_%'" );
            $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_elementor_%'" );
            
            // Force refresh Elementor data
            delete_transient( 'elementor_kit_' . $kit_id );
            
            // Verify data was saved thông qua Elementor API
            $verify_settings = $kit->get_settings();
            if ( isset( $verify_settings['system_typography'] ) && is_array( $verify_settings['system_typography'] ) ) {
                error_log( 'Verify: system_typography saved successfully with ' . count( $verify_settings['system_typography'] ) . ' items' );
            } else {
                error_log( 'Verify: system_typography NOT FOUND or NOT ARRAY' );
            }
            
            error_log( 'Site settings imported successfully to kit ID: ' . $kit_id );
            
            // Trả về thông tin chi tiết về việc import
            return array(
                'success' => true,
                'kit_id' => $kit_id,
                'message' => 'Site settings imported successfully. Check Elementor > Site Settings to see the imported colors, typography, and other settings.'
            );
        } else {
            return new WP_Error( 'kit_creation_failed', 'Failed to create or get active kit.' );
        }
        
    } catch ( Exception $e ) {
        error_log( 'Template kit import error: ' . $e->getMessage() );
        return new WP_Error( 'import_error', 'Error importing template kit: ' . $e->getMessage() );
    }
}

/**
 * Import template kit từ thư mục và tạo ZIP file để import vào Elementor
 *
 * @param string $kit_name Tên thư mục kit
 * @param array|null $site_settings_file File site-settings.json được upload (optional)
 * @return bool|WP_Error True nếu thành công, WP_Error nếu có lỗi
 */
if ( ! function_exists( 'bk_import_template_kit_from_directory' ) ) {
    function bk_import_template_kit_from_directory( $kit_name, $site_settings_file = null ) {
        // Đường dẫn đến thư mục kit
        $kit_path = plugin_dir_path( __FILE__ ) . '../template-kits/' . $kit_name;
        
        if ( ! is_dir( $kit_path ) ) {
            return new WP_Error( 'kit_not_found', 'Template kit directory not found: ' . $kit_name );
        }
        
        // Tạo thư mục temp để xử lý
        $temp_dir = wp_tempnam( 'kit_' . $kit_name );
        if ( is_file( $temp_dir ) ) {
            unlink( $temp_dir );
        }
        mkdir( $temp_dir );
        
        try {
            // Copy toàn bộ thư mục kit vào temp
            bk_copy_directory( $kit_path, $temp_dir );
            
            // Thay thế site-settings.json nếu có file upload
            if ( $site_settings_file && ! empty( $site_settings_file['tmp_name'] ) ) {
                $site_settings_path = $temp_dir . '/site-settings.json';
                if ( file_exists( $site_settings_path ) ) {
                    // Backup file gốc
                    copy( $site_settings_path, $site_settings_path . '.backup' );
                    
                    // Copy file mới
                    copy( $site_settings_file['tmp_name'], $site_settings_path );
                    
                    error_log( 'Site settings replaced in kit: ' . $kit_name );
                }
            }
            
            // Tạo ZIP file
            $zip_file = wp_tempnam( 'kit_' . $kit_name . '.zip' );
            $zip_result = bk_create_zip_from_directory( $temp_dir, $zip_file );
            
            if ( is_wp_error( $zip_result ) ) {
                return $zip_result;
            }
            
            // Import ZIP vào Elementor
            $import_result = import_elementor_template_kit_from_file( $zip_file );
            
            // Cleanup
            if ( file_exists( $zip_file ) ) {
                unlink( $zip_file );
            }
            bk_delete_directory( $temp_dir );
            
            return $import_result;
            
        } catch ( Exception $e ) {
            // Cleanup on error
            if ( file_exists( $temp_dir ) ) {
                bk_delete_directory( $temp_dir );
            }
            return new WP_Error( 'import_error', 'Error importing template kit: ' . $e->getMessage() );
        }
    }
}

/**
 * Copy thư mục và tất cả file con
 *
 * @param string $source Đường dẫn nguồn
 * @param string $destination Đường dẫn đích
 */
if ( ! function_exists( 'bk_copy_directory' ) ) {
    function bk_copy_directory( $source, $destination ) {
        if ( ! is_dir( $source ) ) {
            return false;
        }
        
        if ( ! is_dir( $destination ) ) {
            mkdir( $destination, 0755, true );
        }
        
        $dir = opendir( $source );
        while ( ( $file = readdir( $dir ) ) !== false ) {
            if ( $file === '.' || $file === '..' ) {
                continue;
            }
            
            $source_path = $source . '/' . $file;
            $dest_path = $destination . '/' . $file;
            
            if ( is_dir( $source_path ) ) {
                bk_copy_directory( $source_path, $dest_path );
            } else {
                copy( $source_path, $dest_path );
            }
        }
        closedir( $dir );
        
        return true;
    }
}

/**
 * Xóa thư mục và tất cả file con
 *
 * @param string $dir Đường dẫn thư mục cần xóa
 */
if ( ! function_exists( 'bk_delete_directory' ) ) {
    function bk_delete_directory( $dir ) {
        if ( ! is_dir( $dir ) ) {
            return false;
        }
        
        $files = array_diff( scandir( $dir ), array( '.', '..' ) );
        foreach ( $files as $file ) {
            $path = $dir . '/' . $file;
            if ( is_dir( $path ) ) {
                bk_delete_directory( $path );
            } else {
                unlink( $path );
            }
        }
        
        return rmdir( $dir );
    }
}

/**
 * Tạo ZIP file từ thư mục
 *
 * @param string $source_dir Đường dẫn thư mục nguồn
 * @param string $zip_file Đường dẫn file ZIP đích
 * @return bool|WP_Error True nếu thành công, WP_Error nếu có lỗi
 */
if ( ! function_exists( 'bk_create_zip_from_directory' ) ) {
    function bk_create_zip_from_directory( $source_dir, $zip_file ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return new WP_Error( 'zip_not_supported', 'ZipArchive class not available' );
        }
        
        $zip = new ZipArchive();
        if ( $zip->open( $zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
            return new WP_Error( 'zip_create_failed', 'Failed to create ZIP file' );
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $source_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ( $iterator as $file ) {
            $file_path = $file->getRealPath();
            $relative_path = substr( $file_path, strlen( $source_dir ) + 1 );
            
            // Loại bỏ dấu \ ở đầu nếu có
            $relative_path = ltrim( $relative_path, '\\/' );
            
            error_log( 'Adding to ZIP: ' . $relative_path );
            
            if ( $file->isDir() ) {
                $zip->addEmptyDir( $relative_path );
            } else {
                $zip->addFile( $file_path, $relative_path );
            }
        }
        
        $zip->close();
        
        return true;
    }
}





