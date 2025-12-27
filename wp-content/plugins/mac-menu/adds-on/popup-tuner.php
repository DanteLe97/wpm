<?php
// Add-on: Popup Tuner
// Chức năng: Tinh chỉnh popup cho plugin Mac Menu

class Custom_Popup_Settings {
    
    public function __construct() {
        // Thêm AJAX handler để lưu giá trị
        add_action('wp_ajax_save_custom_datetime', array($this, 'save_custom_datetime_ajax'));
        
        // Hook vào save post để lưu dữ liệu khi post được lưu
        add_action('save_post_jet-popup', array($this, 'save_post_meta'));
        //add_action('save_post', array($this, 'save_post_meta'));
        
        // Thêm cột custom datetime vào trang danh sách popup
        add_filter('manage_jet-popup_posts_columns', array($this, 'add_custom_datetime_column'));
        add_action('manage_jet-popup_posts_custom_column', array($this, 'display_custom_datetime_column'), 10, 2);
        add_filter('manage_edit-jet-popup_sortable_columns', array($this, 'make_custom_datetime_sortable'));
        
        // Thêm CSS cho cột custom datetime
        add_action('admin_head', array($this, 'add_custom_datetime_css'));

        //add_action('wp', array($this, 'mac_auto_update_all_popup_open_trigger'));  


         // Thay thế hook wp bằng hook JetPopup
        //add_action('jet-popup/render-manager/define-popups/after', array($this, 'check_all_popups_on_define'), 10, 2);
        add_action('jet-popup/get_conditions/template_id', array($this, 'check_popup_conditions'), 10, 1);
        
    }

    public function check_popup_conditions($popup_id) {
        if (is_admin()) {
            return $popup_id;
        }
        
        // Kiểm tra thời gian ẩn cho popup này
        if ($this->mac_update_popup_open_trigger_if_expired($popup_id)) {
            $this->log_action($popup_id, 'Conditions hook - expired popup found');
            
            // Trả về false để không hiển thị popup này
            return false;
        }
        
        return $popup_id;
    }

    /**
     * Thay thế cho hàm mac_auto_update_all_popup_open_trigger()
     * Chỉ chạy khi có popup thực sự được định nghĩa
     */
    public function check_all_popups_on_define($defined_popup_list, $ajax_popup_defined) {
        if (is_admin()) {
            return;
        }
        
        if (!empty($defined_popup_list)) {
            
            $popups_to_remove = [];
            $processed_popups = [];
            
            foreach ($defined_popup_list as $key => $popup_data) {
                $popup_id = $popup_data['id'];
                
                // Sử dụng logic cũ của bạn
                if ($this->mac_update_popup_open_trigger_if_expired($popup_id)) {
                    $this->log_action($popup_id, 'PHP hook - removed from list');
                }
            }
            
            
        }else{
            $this->log_action('empty popup');
        }
    }
    // Cập nhật open_trigger và conditions cho popup nếu đã hết hạn
    public function mac_update_popup_open_trigger_if_expired($popup_id) {
        // Lấy custom_datetime
        $custom_datetime = get_post_meta($popup_id, 'custom_datetime', true);
    
        if (empty($custom_datetime)) {
            return;
        }
        $current_time = current_time('Y-m-d H:i:s');
        if (strtotime($current_time) >= strtotime($custom_datetime)) {
            $settings = get_post_meta($popup_id, '_settings', true);
            $meta_key = '_settings';
            if (!$settings) {
                $settings = get_post_meta($popup_id, '_jet_popup_settings', true);
                $meta_key = '_jet_popup_settings';
            }
    
            if (is_string($settings)) {
                $settings = @unserialize($settings);
            }
            if (is_array($settings) && isset($settings['jet_popup_open_trigger'])) {
                $settings['jet_popup_open_trigger'] = 'attach';
                update_post_meta($popup_id, $meta_key, $settings);
                error_log("Đã đổi open_trigger cho popup ID $popup_id về 'attach'");
            }
            $this->delete_jetpopup_conditions_full($popup_id); // 123 là ID của popup bạn muốn clear
        }
    }
    // xóa  Visibility Conditions
    function delete_jetpopup_conditions_full($popup_id) {
        if (empty($popup_id) || get_post_type($popup_id) !== 'jet-popup') {
            return false;
        }
    
        // 1. Xóa post meta _conditions
        delete_post_meta($popup_id, '_conditions');
    
        // 2. Xóa trong _elementor_page_settings
        $page_settings = get_post_meta($popup_id, '_elementor_page_settings', true);
        $changed = false;
        if (isset($page_settings['jet_popup_conditions'])) {
            unset($page_settings['jet_popup_conditions']);
            $changed = true;
        }
        if (isset($page_settings['jet_popup_relation_type'])) {
            unset($page_settings['jet_popup_relation_type']);
            $changed = true;
        }
        // Xóa các key conditions_* nếu có
        foreach ($page_settings as $key => $val) {
            if (strpos($key, 'conditions_') === 0) {
                unset($page_settings[$key]);
                $changed = true;
            }
        }
        if ($changed) {
            update_post_meta($popup_id, '_elementor_page_settings', $page_settings);
        }
    
        // 3. Xóa post meta cũ nếu có
        delete_post_meta($popup_id, '_jet_popup_conditions');
    
        // 4. Xóa trong option (cho chắc)
        $options = get_option('jet_popup_conditions', []);
        if (isset($options['jet-popup'][$popup_id])) {
            unset($options['jet-popup'][$popup_id]);
            update_option('jet_popup_conditions', $options);
        }
        if (isset($options[$popup_id])) {
            unset($options[$popup_id]);
            update_option('jet_popup_conditions', $options);
        }
    
        return true;
    }
   






    // // Hàm tự động kiểm tra và cập nhật cho tất cả popup
    // public function mac_auto_update_all_popup_open_trigger() {
    //     if(is_admin()){ return;}
    //     // Lấy danh sách popup ID từ option jet_popup_conditions
    //     $all_conditions = get_option('jet_popup_conditions', []);
    //     $popup_ids = [];
    //     if (isset($all_conditions['jet-popup']) && is_array($all_conditions['jet-popup'])) {
    //         foreach ($all_conditions['jet-popup'] as $popup_id => $popup_data) {
    //             if (!empty($popup_data['conditions'])) {
    //                 $popup_ids[] = $popup_id;
    //             }
    //         }
    //     }
    //     foreach ($popup_ids as $popup_id) {
    //         $this->mac_update_popup_open_trigger_if_expired($popup_id);
    //     }
    // }
    // // Cập nhật open_trigger và conditions cho popup nếu đã hết hạn
    // public function mac_update_popup_open_trigger_if_expired($popup_id) {
    //     // Lấy custom_datetime
    //     $custom_datetime = get_post_meta($popup_id, 'custom_datetime', true);
    
    //     if (empty($custom_datetime)) {
    //         return;
    //     }
    //     $current_time = current_time('Y-m-d H:i:s');
    //     if (strtotime($current_time) >= strtotime($custom_datetime)) {
    //         $settings = get_post_meta($popup_id, '_settings', true);
    //         $meta_key = '_settings';
    //         if (!$settings) {
    //             $settings = get_post_meta($popup_id, '_jet_popup_settings', true);
    //             $meta_key = '_jet_popup_settings';
    //         }
    
    //         if (is_string($settings)) {
    //             $settings = @unserialize($settings);
    //         }
    //         if (is_array($settings) && isset($settings['jet_popup_open_trigger'])) {
    //             $settings['jet_popup_open_trigger'] = 'attach';
    //             update_post_meta($popup_id, $meta_key, $settings);
    //             error_log("Đã đổi open_trigger cho popup ID $popup_id về 'attach'");
    //         }
    //         $this->delete_jetpopup_conditions_full($popup_id); // 123 là ID của popup bạn muốn clear
    //     }
    // }
    // // xóa  Visibility Conditions
    // function delete_jetpopup_conditions_full($popup_id) {
    //     if (empty($popup_id) || get_post_type($popup_id) !== 'jet-popup') {
    //         return false;
    //     }
    
    //     // 1. Xóa post meta _conditions
    //     delete_post_meta($popup_id, '_conditions');
    
    //     // 2. Xóa trong _elementor_page_settings
    //     $page_settings = get_post_meta($popup_id, '_elementor_page_settings', true);
    //     $changed = false;
    //     if (isset($page_settings['jet_popup_conditions'])) {
    //         unset($page_settings['jet_popup_conditions']);
    //         $changed = true;
    //     }
    //     if (isset($page_settings['jet_popup_relation_type'])) {
    //         unset($page_settings['jet_popup_relation_type']);
    //         $changed = true;
    //     }
    //     // Xóa các key conditions_* nếu có
    //     foreach ($page_settings as $key => $val) {
    //         if (strpos($key, 'conditions_') === 0) {
    //             unset($page_settings[$key]);
    //             $changed = true;
    //         }
    //     }
    //     if ($changed) {
    //         update_post_meta($popup_id, '_elementor_page_settings', $page_settings);
    //     }
    
    //     // 3. Xóa post meta cũ nếu có
    //     delete_post_meta($popup_id, '_jet_popup_conditions');
    
    //     // 4. Xóa trong option (cho chắc)
    //     $options = get_option('jet_popup_conditions', []);
    //     if (isset($options['jet-popup'][$popup_id])) {
    //         unset($options['jet-popup'][$popup_id]);
    //         update_option('jet_popup_conditions', $options);
    //     }
    //     if (isset($options[$popup_id])) {
    //         unset($options[$popup_id]);
    //         update_option('jet_popup_conditions', $options);
    //     }
    
    //     return true;
    // }






    // Thêm cột custom datetime vào trang danh sách popup
    public function add_custom_datetime_column($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            // Thêm cột custom datetime sau cột title
            if ($key === 'title') {
                $new_columns['custom_datetime'] = 'Time to Stop Displaying';
            }
        }
        
        return $new_columns;
    }
    // Hiển thị nội dung cột custom datetime
    public function display_custom_datetime_column($column, $post_id) {
        if ($column === 'custom_datetime') {
            $datetime_value = get_post_meta($post_id, 'custom_datetime', true);
            
            // Tạo input field trực tiếp
            $input_value = '';
            $status_text = '';
            $status_class = '';
            
            if (!empty($datetime_value)) {
                // Format datetime để hiển thị đẹp hơn
                $formatted_date = date('d/m/Y H:i', strtotime($datetime_value));
                $current_time = current_time('timestamp');
                $end_time = strtotime($datetime_value);
                
                // Chuyển đổi sang format datetime-local
                $input_value = date('Y-m-d\TH:i', strtotime($datetime_value));
                
                // Kiểm tra trạng thái popup
                if ($current_time > $end_time) {
                    // Popup đã hết hạn
                    $status_text = ' (Expired)';
                    $status_class = 'expired';
                } else {
                    // Popup còn hiệu lực
                    $time_left = $end_time - $current_time;
                    $days_left = floor($time_left / (24 * 60 * 60));
                    
                    if ($days_left > 0) {
                        $status_text = ' (' . $days_left . ' ngày)';
                    } else {
                        $hours_left = floor($time_left / (60 * 60));
                        $status_text = ' (' . $hours_left . ' giờ)';
                    }
                    $status_class = 'active';
                }
            }
            
            echo '<div class="datetime-cell" data-post-id="' . $post_id . '">';
            echo '<div class="datetime-input-wrapper">';
            echo '<input type="datetime-local" class="datetime-input" value="' . esc_attr($input_value) . '" placeholder="Chọn thời gian">';
            echo '<button type="button" class="save-datetime-btn">Save</button>';
            echo '<button type="button" class="clear-datetime-btn">Clear</button>';
            echo '</div>';
            if (!empty($status_text)) {
                echo '<div class="datetime-status ' . $status_class . '">' . esc_html($status_text) . '</div>';
            }
            echo '</div>';
        }
    }
    //Làm cho cột custom datetime có thể sắp xếp
    public function make_custom_datetime_sortable($columns) {
        $columns['custom_datetime'] = 'custom_datetime';
        return $columns;
    }
    //AJAX handler để lưu giá trị
    public function save_custom_datetime_ajax() {
        error_log("DEBUG: save_custom_datetime_ajax called");
        error_log("DEBUG: POST data: " . print_r($_POST, true));
        
        // Kiểm tra nonce
        if (!wp_verify_nonce($_POST['nonce'], 'save_datetime_nonce')) {
            error_log("DEBUG: Nonce verification failed");
            wp_die('Security check failed');
        }
        
        $popup_id = intval($_POST['popup_id']);
        $datetime_value = $_POST['datetime_value'];
        
        error_log("DEBUG: popup_id: $popup_id, datetime_value: $datetime_value");
        
        if ($popup_id > 0) {
            $result = update_post_meta($popup_id, 'custom_datetime', $datetime_value);
            error_log("DEBUG: update_post_meta result: " . var_export($result, true));
            
            // Kiểm tra lại xem đã lưu chưa
            $saved_value = get_post_meta($popup_id, 'custom_datetime', true);
            error_log("DEBUG: Saved value check: " . var_export($saved_value, true));
            
            wp_send_json_success('Datetime saved successfully');
        } else {
            error_log("DEBUG: Invalid popup ID");
            wp_send_json_error('Invalid popup ID');
        }
    }
    // Lưu dữ liệu vào post meta khi post được lưu
    public function save_post_meta($post_id) {
        // Kiểm tra quyền
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Kiểm tra nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'update-post_' . $post_id)) {
            return;
        }
        
        // Lưu từ hidden field
        if (isset($_POST['custom_datetime_hidden'])) {
            update_post_meta($post_id, 'custom_datetime', sanitize_text_field($_POST['custom_datetime_hidden']));
        }
        
        // Lưu từ field trực tiếp
        if (isset($_POST['custom_datetime'])) {
            update_post_meta($post_id, 'custom_datetime', sanitize_text_field($_POST['custom_datetime']));
        }
    }
    // Thêm CSS cho cột custom datetime
    public function add_custom_datetime_css() {
        $screen = get_current_screen();
        
        // Chỉ áp dụng CSS trên trang danh sách popup
        if (!$screen || $screen->id !== 'edit-jet-popup') {
            return;
        }
        
        ?>
        <style type="text/css">
        .column-custom_datetime {
            width: 220px;
        }
        
        .datetime-cell {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
        }
        
        .datetime-input-wrapper {
            display: flex;
            align-items: center;
            gap: 4px;
            width: 100%;
        }
        
        .datetime-input {
            flex: 1;
            padding: 4px 6px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 11px;
            min-width: 140px;
        }
        
        .datetime-input:focus {
            border-color: #0073aa;
            outline: none;
            box-shadow: 0 0 0 1px #0073aa;
        }
        
        .save-datetime-btn {
            background-color: #0073aa;
            color: white;
            border: none;
            padding: 4px 6px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 10px;
            white-space: nowrap;
        }
        
        .save-datetime-btn:hover {
            background-color: #005a87;
        }
        
        .clear-datetime-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 4px 6px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 10px;
            white-space: nowrap;
        }
        
        .clear-datetime-btn:hover {
            background-color: #c82333;
        }
        
        .datetime-status {
            font-size: 10px;
            padding: 2px 4px;
            border-radius: 2px;
            font-weight: 500;
        }
        
        .datetime-status.active {
            background-color: #d4edda;
            color: #155724;
        }
        
        .datetime-status.expired {
            background-color: #f8d7da;
            color: #721c24;
        }
        

        
        .save-notification {
            position: fixed;
            top: 32px;
            right: 20px;
            background-color: #d4edda;
            color: #155724;
            padding: 12px 20px;
            border-radius: 4px;
            border: 1px solid #c3e6cb;
            z-index: 999999;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transform: translateX(100%);
            transition: transform 0.3s ease;
        }
        
        .save-notification.show {
            transform: translateX(0);
        }
        </style>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Xử lý lưu datetime
            $(document).on('click', '.save-datetime-btn', function() {
                var cell = $(this).closest('.datetime-cell');
                var postId = cell.data('post-id');
                var input = cell.find('.datetime-input');
                var newValue = input.val();
                
                // Lưu qua AJAX
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'save_custom_datetime',
                        popup_id: postId,
                        datetime_value: newValue,
                        nonce: '<?php echo wp_create_nonce("save_datetime_nonce"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            // Reload trang để cập nhật hiển thị
                            location.reload();
                            showSaveNotification('Đã lưu thời gian thành công!');
                        } else {
                            alert('Có lỗi xảy ra khi lưu thời gian!');
                        }
                    },
                    error: function() {
                        alert('Có lỗi xảy ra khi lưu thời gian!');
                    }
                });
            });
            
            // Xử lý xóa datetime
            $(document).on('click', '.clear-datetime-btn', function() {
                var cell = $(this).closest('.datetime-cell');
                var postId = cell.data('post-id');
                
                // Gửi AJAX với giá trị rỗng để xóa meta
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'save_custom_datetime',
                        popup_id: postId,
                        datetime_value: '', // giá trị rỗng
                        nonce: '<?php echo wp_create_nonce("save_datetime_nonce"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                            showSaveNotification('Đã xóa thời gian thành công!');
                        } else {
                            alert('Có lỗi xảy ra khi xóa thời gian!');
                        }
                    },
                    error: function() {
                        alert('Có lỗi xảy ra khi xóa thời gian!');
                    }
                });
            });
            
            // Xử lý Enter key
            $(document).on('keypress', '.datetime-input', function(e) {
                if (e.which === 13) {
                    $(this).closest('.datetime-input-wrapper').find('.save-datetime-btn').click();
                }
            });
            
            function showSaveNotification(message) {
                var notification = $('<div class="save-notification">' + message + '</div>');
                $('body').append(notification);
                
                setTimeout(function() {
                    notification.addClass('show');
                }, 100);
                
                setTimeout(function() {
                    notification.removeClass('show');
                    setTimeout(function() {
                        notification.remove();
                    }, 300);
                }, 3000);
            }
        });
        </script>
        <?php
    }
}

// Khởi tạo plugin
new Custom_Popup_Settings(); 
