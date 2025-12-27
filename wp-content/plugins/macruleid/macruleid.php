<?php
/**
 * Plugin Name: Mac Rule ID
 * Description: Template management system for Elementor
 * Version: 1.0.3
 * Author: MacUsaOne
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MAC_RULEID_URI', plugin_dir_url(__FILE__));
define('MAC_RULEID_PATH', plugin_dir_path(__FILE__));

// Include Template ID Processor
require_once MAC_RULEID_PATH . 'template-id-processor.php';

// Include Mac Preview Module
require_once MAC_RULEID_PATH . 'modules/mac-preview/mac-preview-core.php';



// Create previews directory on plugin activation
register_activation_hook(__FILE__, 'mac_create_previews_directory');
function mac_create_previews_directory() {
    $preview_dir = MAC_RULEID_PATH . 'previews/';
    if (!file_exists($preview_dir)) {
        wp_mkdir_p($preview_dir);
        
        // Create .htaccess to allow direct access to images
        $htaccess_content = "Options +FollowSymLinks\nRewriteEngine Off\n<Files ~ \"\\.(jpg|jpeg|png|gif)$\">\nAllow from all\n</Files>";
        file_put_contents($preview_dir . '.htaccess', $htaccess_content);
    }
    
    // Activate Mac Preview Module
    if (function_exists('mac_preview_activate')) {
        mac_preview_activate();
    }
}

// Plugin deactivation hook
register_deactivation_hook(__FILE__, 'mac_ruleid_deactivate');
function mac_ruleid_deactivate() {
    // Deactivate Mac Preview Module
    if (function_exists('mac_preview_deactivate')) {
        mac_preview_deactivate();
    }
}

// Enqueue frontend scripts and styles
add_action('wp_enqueue_scripts', 'mac_enqueue_frontend_scripts');
function mac_enqueue_frontend_scripts()
{
    // Load ở frontend cho tất cả users
    if (!is_admin()) {
        // Enqueue CSS
        wp_enqueue_style(
            'mac-copy-button-css',
            MAC_RULEID_URI . 'css/copy-button.css',
            array(),
            '1.0.0'
        );
        
        // Enqueue JS
//         wp_enqueue_script(
//             'mac-copy-section-id',
//             MAC_RULEID_URI . 'js/copy-section-id.js',
//             array(),
//             '1.0.0',
//             true
//         );
    }
}

// Add template category column to Elementor library
add_filter('manage_elementor_library_posts_columns', 'template_add_column_to_elementor_templates');
function template_add_column_to_elementor_templates($columns)
{
    $columns['template_id'] = 'Template ID';
    $columns['html2canvas_preview'] = 'HTML2Canvas Preview';
    return $columns;
}

// Display content for template category column
add_action('manage_elementor_library_posts_custom_column', 'template_column_content_elementor_templates', 10, 2);
function template_column_content_elementor_templates($column_name, $post_id)
{
    if ($column_name == 'template_id') {
        display_template_id($post_id);
    }
    
    if ($column_name == 'html2canvas_preview') {
        $preview_image_url = get_post_meta($post_id, '_template_preview_url', true);
        display_template_preview($preview_image_url);
    }
}

// Enqueue admin CSS
add_action('admin_enqueue_scripts', 'template_enqueue_admin_css');
function template_enqueue_admin_css($hook)
{
    if ($hook === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'elementor_library') {
        wp_enqueue_style('template-library-admin-css', MAC_RULEID_URI . 'css/template-admin.css', array(), '1.0.0');
        return;
    }
    
    if ($hook != 'post.php' && $hook != 'post-new.php') {
        return;
    }
    
    global $post;
    if ($post && $post->post_type != 'elementor_library') {
        return;
    }
    
    wp_enqueue_style('template-library-admin-css', MAC_RULEID_URI . 'css/template-admin.css', array(), '1.0.0');
}

// Enqueue admin scripts  
add_action('admin_enqueue_scripts', 'template_enqueue_admin_scripts');
function template_enqueue_admin_scripts($hook)
{
    if ($hook === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'elementor_library') {
        
        wp_enqueue_script('html2canvas', '/wp-content/plugins/elementor/assets/lib/html2canvas/js/html2canvas.min.js', array(), '1.4.1', true);
        
        if (!wp_script_is('template-shared-functions-js', 'enqueued')) {
            wp_enqueue_script('template-shared-functions-js', MAC_RULEID_URI . 'js/template-shared-functions.js', array('jquery'), '1.0.0', true);
        }
        
        wp_enqueue_script('template-library-admin-js', MAC_RULEID_URI . 'js/template-library-admin.js', array('jquery', 'html2canvas', 'template-shared-functions-js'), '1.0.0', true);
        
        wp_localize_script('template-library-admin-js', 'templatePreviewAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('template_preview_nonce')
        ));
        
        wp_localize_script('template-library-admin-js', 'template_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('template_ajax_nonce')
        ));
        
        // Add ajaxurl for backwards compatibility
        wp_localize_script('template-library-admin-js', 'ajaxurl', admin_url('admin-ajax.php'));
        
        return;
    }
    
    if ($hook != 'post.php' && $hook != 'post-new.php') {
        return;
    }
    
    global $post;
    
    // Get current screen to determine post type
    $screen = get_current_screen();
    $post_type = '';
    
    if ($post) {
        $post_type = $post->post_type;
    } elseif ($screen && isset($screen->post_type)) {
        $post_type = $screen->post_type;
    } elseif (isset($_GET['post_type'])) {
        $post_type = sanitize_text_field($_GET['post_type']);
    } elseif (isset($_GET['post'])) {
        $temp_post = get_post(intval($_GET['post']));
        if ($temp_post) {
            $post_type = $temp_post->post_type;
        }
    } else {
        // Default to page for new posts without specific type
        $post_type = 'page';
    }
    
    if (in_array($post_type, array('page', 'post'))) {
        
        wp_enqueue_style('coloris-css', 'https://cdn.jsdelivr.net/gh/mdbassit/Coloris@latest/dist/coloris.min.css', array(), '1.0.0');
        wp_enqueue_script('coloris-js', 'https://cdn.jsdelivr.net/gh/mdbassit/Coloris@latest/dist/coloris.min.js', array(), '1.0.0', true);

        wp_enqueue_style('custom-colors-fonts-css', MAC_RULEID_URI . 'css/custom-colors-fonts.css', array('coloris-css'), '1.0.0');
        wp_enqueue_script('custom-colors-fonts-js', MAC_RULEID_URI . 'js/custom-colors-fonts.js', array('jquery', 'coloris-js'), '1.0.0', true);
        
        if (!wp_script_is('template-shared-functions-js', 'enqueued')) {
            wp_enqueue_script('template-shared-functions-js', MAC_RULEID_URI . 'js/template-shared-functions.js', array('jquery'), '1.0.0', true);
        }
        
        wp_enqueue_script('template-metabox-js', MAC_RULEID_URI . 'js/template-metabox.js', array('jquery', 'template-shared-functions-js'), '1.0.0', true);
        
        $post_id = $post ? $post->ID : 0;
        
        $preset_data = array();
        if ($post_id > 0) {
            $preset_data = get_post_meta($post_id, 'mac_custom_preset', true);
            if (!is_array($preset_data)) {
                $preset_data = array();
            }
        }
        
        wp_localize_script('custom-colors-fonts-js', 'customMetaboxData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('custom_metabox_nonce'),
            'post_id' => $post_id,
            'presetData' => $preset_data
        ));
        
        wp_localize_script('template-metabox-js', 'templateMetaboxData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('custom_metabox_nonce'),
            'post_id' => $post_id,
            'hook' => $hook,
            'post_type' => $post_type
        ));
        
        wp_localize_script('custom-colors-fonts-js', 'ajaxurl', admin_url('admin-ajax.php'));
        wp_localize_script('template-metabox-js', 'ajaxurl', admin_url('admin-ajax.php'));
        
    }
}

// AJAX handlers
add_action('wp_ajax_get_template_preview', 'template_get_preview');
add_action('wp_ajax_check_preview_needed', 'template_check_preview_needed');
function template_get_preview()
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'template_preview_nonce')) {
        wp_send_json_error('Security check failed');
    }

    $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
    if (!$template_id) {
        wp_send_json_error('Invalid template ID');
    }

    $template_url = get_permalink($template_id);
    if (!$template_url) {
        wp_send_json_error('Template not found');
    }

    wp_send_json_success(array(
        'template_url' => $template_url
    ));
}

// AJAX handler for checking if preview is needed
function template_check_preview_needed()
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'template_preview_nonce')) {
        wp_send_json_error('Security check failed');
    }

    $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
    if (!$template_id) {
        wp_send_json_error('Invalid template ID');
    }

    // Check if preview already exists
    $existing_preview = get_post_meta($template_id, '_template_preview_url', true);
    $needs_generation = get_post_meta($template_id, '_needs_preview_generation', true);
    
    $should_generate = empty($existing_preview) || $needs_generation === 'yes';
    
    // Clear the flag if it was set
    if ($needs_generation === 'yes') {
        delete_post_meta($template_id, '_needs_preview_generation');
    }

    wp_send_json_success(array(
        'needs_generation' => $should_generate,
        'has_existing' => !empty($existing_preview)
    ));
}

// AJAX handler for saving template preview
add_action('wp_ajax_save_template_preview', 'template_save_preview');
function template_save_preview()
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'template_preview_nonce')) {
        wp_send_json_error('Security check failed');
    }

    $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
    $preview_data = isset($_POST['preview_data']) ? $_POST['preview_data'] : '';

    if (!$template_id || !$preview_data) {
        wp_send_json_error('Missing required data');
    }

    // Save image to plugin directory
    $preview_dir = MAC_RULEID_PATH . 'previews/';
    $preview_url_dir = MAC_RULEID_URI . 'previews/';
    
    // Create directory if it doesn't exist
    if (!file_exists($preview_dir)) {
        wp_mkdir_p($preview_dir);
    }
    
    // Decode base64 image
    $image_data = str_replace('data:image/jpeg;base64,', '', $preview_data);
    $image_data = str_replace(' ', '+', $image_data);
    $decoded_image = base64_decode($image_data);
    
    // Generate filename
    $filename = 'template-' . $template_id . '-' . time() . '.jpg';
    $file_path = $preview_dir . $filename;
    $file_url = $preview_url_dir . $filename;
    
    // Delete old preview files for this template first
    $old_url = get_post_meta($template_id, '_template_preview_url', true);
    if ($old_url) {
        $old_filename = basename($old_url);
        $old_path = $preview_dir . $old_filename;
        if (file_exists($old_path)) {
            unlink($old_path);
        }
    }
    
    // Delete any other old files for this template (in case of multiple files)
    $pattern = $preview_dir . 'template-' . $template_id . '-*.jpg';
    $old_files = glob($pattern);
    if ($old_files) {
        foreach ($old_files as $old_file) {
            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }
    }
    
    // Save new file
    if (file_put_contents($file_path, $decoded_image)) {
        // Save new URL to meta
        update_post_meta($template_id, '_template_preview_url', $file_url);

    wp_send_json_success(array(
            'message' => 'Preview đã được lưu thành công',
            'preview_url' => $file_url
    ));
    } else {
        wp_send_json_error('Không thể lưu file preview');
    }
}

// AJAX handler for updating template category
add_action('wp_ajax_update_template_category', 'template_update_category');
function template_update_category()
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'template_ajax_nonce')) {
        wp_send_json_error('Security check failed');
    }

    $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
    if (!$template_id) {
        wp_send_json_error('Invalid template ID');
    }

    // You can add logic here to update template category if needed
    wp_send_json_success(array(
        'message' => 'Template category updated',
        'template_id' => $template_id
    ));
}

// ----------------------------------------------------------------------------------------------

// Add custom metabox
add_action('add_meta_boxes', 'add_custom_metabox');
function add_custom_metabox()
{
    $post_types = array('page', 'post');
    foreach ($post_types as $post_type) {
        add_meta_box(
            'mac-custom-metabox',
            'Mac - Custom Colors & Fonts',
            'custom_metabox_callback',
            $post_type
        );
        
        // Font classes metabox removed - using integrated className field
    }
}

// Custom metabox callback function  
function custom_metabox_callback($post)
{
    wp_nonce_field('custom_metabox_nonce', 'custom_metabox_nonce');
    
    // Get existing data
    $template_id = get_post_meta($post->ID, 'mac_custom_template_id', true);
    $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
    
    if (is_array($template_id)) {
        $template_id = implode(', ', $template_id);
    }
    
    // Kiểm tra xem có cả template ID và elementor data không
    $is_template_applied = !empty($template_id) && !empty($elementor_data);
    
    // Get color data
    $colors = get_post_meta($post->ID, 'mac_custom_colors', true);
    if (!is_array($colors)) {
        $colors = array();
    }
    
    // Đảm bảo các màu cơ bản luôn tồn tại
    $default_colors = array(
        0 => array('name' => 'primary', 'text' => '--e-global-color-primary', 'color' => '#F26212'),
        1 => array('name' => 'secondary', 'text' => '--e-global-color-secondary', 'color' => '#FBAE85'),
        2 => array('name' => 'text', 'text' => '--e-global-color-text', 'color' => '#333333'),
        3 => array('name' => 'accent', 'text' => '--e-global-color-accent', 'color' => '#FF6B35')
    );
    
    // Merge với defaults để đảm bảo không thiếu key nào
    foreach ($default_colors as $index => $default_color) {
        if (!isset($colors[$index]) || !is_array($colors[$index]) || !isset($colors[$index]['color'])) {
            $colors[$index] = $default_color;
        }
    }
    
    // Thêm 3 custom colors mặc định nếu chưa có
    $default_custom_colors = array(
        4 => array('name' => 'White', 'text' => '--e-global-color-041be46', 'color' => '#ffffff'),
        5 => array('name' => 'Black', 'text' => '--e-global-color-575bd41', 'color' => '#000000'),
        6 => array('name' => 'Transparent', 'text' => '--e-global-color-54f3520', 'color' => '#ffffff00')
    );
    
    // Chỉ thêm nếu chưa có dữ liệu custom colors
    $has_custom_colors = false;
    for ($i = 4; $i <= 6; $i++) {
        if (isset($colors[$i]) && !empty($colors[$i]['color'])) {
            $has_custom_colors = true;
            break;
        }
    }
    
    if (!$has_custom_colors) {
        foreach ($default_custom_colors as $index => $default_color) {
            $colors[$index] = $default_color;
        }
    }
    
    // Get font data
    $saved_fonts = get_post_meta($post->ID, 'mac_custom_fonts', true);
    if (!is_array($saved_fonts)) {
        $saved_fonts = array();
    }
    // Backward compatibility: chuyển 'accent' từ index 3 về 2 nếu cấu trúc cũ còn tồn tại
    if (isset($saved_fonts[3]) && !isset($saved_fonts[2])) {
        $saved_fonts[2] = $saved_fonts[3];
    }
    // Default fixed fonts structure
    $default_fonts = array(
        0 => array('name' => 'primary', 'font' => ''),
        1 => array('name' => 'secondary', 'font' => ''),
        2 => array('name' => 'accent', 'font' => '')
    );
    
    $fonts = array();
    foreach ($default_fonts as $index => $default_font) {
        $fonts[$index] = array(
            'name' => $default_font['name'],
            'font' => (isset($saved_fonts[$index]['font']) && !empty($saved_fonts[$index]['font'])) ? $saved_fonts[$index]['font'] : $default_font['font']
        );
    }
    
    // Get custom colors (index 4+)
    $custom_colors_extra = array();
    // Get from stored array starting at index 4
    for ($i = 4; $i <= 19; $i++) {
        if (isset($colors[$i])) {
            // Map back to display index (i + 1)
            $display_index = $i + 1;
            $custom_colors_extra[$display_index] = $colors[$i];
        }
    }

    // Get custom fonts (index 5+)
    $custom_fonts_extra = array();
    // Get from stored array starting at index 4
    for ($i = 4; $i <= 19; $i++) {
        if (isset($saved_fonts[$i])) {
            // Map back to display index (i + 1)
            $display_index = $i + 1;
            $custom_fonts_extra[$display_index] = $saved_fonts[$i];
        }
    }
    
    ?>
    <div class="custom-metabox-wrapper">
        <!-- Template ID Section -->
        <div class="custom-metabox-section">
            <label for="custom_template_id"><strong>Template ID:</strong></label>
            <input type="text" 
                id="custom_template_id" 
                name="custom_template_id" 
                value="<?php echo esc_attr($template_id); ?>" 
                class="widefat" 
                placeholder="Ví dụ: 123, page:446-tem:80b9a07, 889"
                <?php echo $is_template_applied ? 'disabled' : ''; ?>>
                
            <?php if ($is_template_applied): ?>
            <div class="template-id-notice" style="color: #856404; background-color: #fff3cd; border: 1px solid #ffeeba; padding: 10px; margin-top: 10px; border-radius: 4px;">
                Template ID đã được áp dụng. Để thay đổi, vui lòng xóa dữ liệu Elementor trước.
            </div>
            <?php else: ?>
            <p class="description">
                <strong>Format hỗ trợ:</strong><br>
                • <code>123</code> - Lấy tất cả containers từ post ID 123<br>
                • <code>page:446-tem:80b9a07</code> - Lấy container có ID 80b9a07 từ page 446<br>
                • <code>123, page:446-tem:80b9a07</code> - Merge nhiều nguồn<br>
                <em>Phân cách bằng dấu phẩy để merge từ nhiều sources.</em>
            </p>
            <?php endif; ?>
            
            <div class="custom-publish-section">
                <?php
                $post_status = get_post_status($post->ID);
                $is_published = ($post_status === 'publish');
                display_publish_button($post, $is_published);
                display_reset_button();
                
                // Add clear cache button if template is applied
                if ($is_template_applied): ?>
                <button type="button" 
                    id="clear-cache-btn" 
                    class="button button-secondary" 
                    data-post-id="<?php echo esc_attr($post->ID); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                        <path d="M3 3v5h5"/>
                    </svg>
                    Clear Cache
                </button>
                <?php endif; ?>
                
                <!-- Copy Meta Button -->
                <button type="button" 
                    id="copy-meta-btn" 
                    class="button button-secondary" 
                    data-post-id="<?php echo esc_attr($post->ID); ?>"
                    data-nonce="<?php echo wp_create_nonce('copy_meta_nonce'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                    </svg>
                    Copy Meta from Home
                </button>
            </div>
        </div>
        
        <!-- Custom Colors & Fonts Section -->
        <div class="custom-metabox-section">
            <label><strong>Custom Colors & Fonts:</strong></label>
            <div class="custom-style-tabs">
                <div class="tab-navigation">
                    <button type="button" class="tab-button active" data-tab="custom">Custom</button>
                    <button type="button" class="tab-button" data-tab="preset">Preset</button>
                </div>
                
                <div class="tab-content">
                    <div id="custom-tab" class="tab-panel active">
                        <div class="custom-controls">
                            <div class="colors-section">
                                <h4><?php _e('Colors', 'mac-ruleid'); ?></h4>
                                <div class="colors-layout" id="colors-container">
									<div class="color-control fixed-color fixed-item">
                                        <div class="color-header">
                                            <label><?php _e('Màu chính', 'mac-ruleid'); ?></label>
                                            <button type="button" class="copy-value-btn" data-copy-value="<?php echo esc_attr($colors[0]['color']); ?>" title="Copy color value">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                                </svg>
                                            </button>
                                        </div>
										<input type="text" data-coloris id="primary-color" name="primary_color" value="<?php echo esc_attr($colors[0]['color']); ?>" class="coloris">
                                    </div>
									<div class="color-control fixed-color fixed-item">
                                        <div class="color-header">
                                            <label><?php _e('Màu phụ', 'mac-ruleid'); ?></label>
                                            <button type="button" class="copy-value-btn" data-copy-value="<?php echo esc_attr($colors[1]['color']); ?>" title="Copy color value">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                                </svg>
                                            </button>
                                        </div>
										<input type="text" data-coloris id="secondary-color" name="secondary_color" value="<?php echo esc_attr($colors[1]['color']); ?>" class="coloris">
                                    </div>
									<div class="color-control fixed-color fixed-item">
                                        <div class="color-header">
                                            <label><?php _e('Màu chữ', 'mac-ruleid'); ?></label>
                                            <button type="button" class="copy-value-btn" data-copy-value="<?php echo esc_attr($colors[2]['color']); ?>" title="Copy color value">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                                </svg>
                                            </button>
                                        </div>
										<input type="text" data-coloris id="text-color" name="text_color" value="<?php echo esc_attr($colors[2]['color']); ?>" class="coloris">
                                    </div>
									<div class="color-control fixed-color fixed-item">
                                        <div class="color-header">
                                            <label><?php _e('Màu nhấn', 'mac-ruleid'); ?></label>
                                            <button type="button" class="copy-value-btn" data-copy-value="<?php echo esc_attr($colors[3]['color']); ?>" title="Copy color value">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                                </svg>
                                            </button>
                                        </div>
										<input type="text" data-coloris id="accent-color" name="accent_color" value="<?php echo esc_attr($colors[3]['color']); ?>" class="coloris">
                                    </div>
                                    
                                    <?php foreach ($custom_colors_extra as $index => $color_data): ?>
										<div class="color-control custom-item" data-index="<?php echo $index; ?>" data-type="color">
											<div class="color-label-row">
												<label><?php echo esc_html($color_data['name']); ?></label>
                                                <button type="button" class="copy-value-btn" data-copy-value="<?php echo esc_attr($color_data['color']); ?>" title="Copy color value">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                                    </svg>
                                                </button>
												<input type="text" id="mac_custom_color_name_<?php echo $index; ?>" name="mac_custom_color_name_<?php echo $index; ?>" value="<?php echo esc_attr($color_data['name']); ?>" class="color-name-input" placeholder="Insert Name">
												 <input type="text" id="mac_custom_color_text_<?php echo $index; ?>" name="mac_custom_color_text_<?php echo $index; ?>" value="<?php echo esc_attr($color_data['text']); ?>" class="color-text-input" placeholder="Insert Text">
											</div>
											<input type="text" data-coloris id="mac_custom_color_<?php echo $index; ?>" name="mac_custom_color_<?php echo $index; ?>" value="<?php echo esc_attr($color_data['color']); ?>" class="coloris">
											<button type="button" class="delete-item-btn" onclick="removeCustomItem(<?php echo $index; ?>, 'color')">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x-icon lucide-x">
                                                <path d="M18 6 6 18"/>
                                                <path d="m6 6 12 12"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
								<button type="button" class="add-item-btn" onclick="addCustomItem('color')">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus-icon lucide-plus">
                                        <path d="M5 12h14"/>
                                        <path d="M12 5v14"/>
									</svg>
                                </button>
                            </div>

                            <div class="typography-section">
                                <h4><?php _e('Typography', 'mac-ruleid'); ?></h4>
                            <div class="fonts-layout" id="fonts-container">
                            	<div class="font-control fixed-font fixed-item">
                                        <div class="font-header">
                                            <label><?php _e('Font chính', 'mac-ruleid'); ?>(h1,h2,h3,h4,h5,h6,.primary-font)</label>
                                            <button type="button" class="copy-value-btn copy-font-btn" data-copy-target="primary_font" title="Copy font value">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                                </svg>
                                            </button>
                                        </div>
                                        <select name="primary_font" class="font-select widefat primary-font" data-value = "<?php echo isset($saved_fonts[0]['font']) ? esc_attr($saved_fonts[0]['font']) : ''; ?>"> </select>
                                    </div>
                            	<div class="font-control fixed-font fixed-item">
                                        <div class="font-header">
                                            <label><?php _e('Font phụ', 'mac-ruleid'); ?>(span,p,body,div,.secondary-font)</label>
                                            <button type="button" class="copy-value-btn copy-font-btn" data-copy-target="secondary_font" title="Copy font value">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                                </svg>
                                            </button>
                                        </div>
                                        <select name="secondary_font" class="font-select widefat secondary-font" data-value = "<?php echo isset($saved_fonts[1]['font']) ? esc_attr($saved_fonts[1]['font']) : ''; ?>"></select>
                                    </div>
                                    <div class="font-control fixed-font fixed-item">
                                        <div class="font-header">
                                            <label><?php _e('Font nhấn', 'mac-ruleid'); ?>(.accent-font)</label>
                                            <button type="button" class="copy-value-btn copy-font-btn" data-copy-target="accent_font" title="Copy font value">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                                </svg>
                                            </button>
                                        </div>
                                        <select name="accent_font" class="font-select widefat accent-font" data-value = "<?php echo isset($saved_fonts[2]['font']) ? esc_attr($saved_fonts[2]['font']) : ''; ?>"></select>
                                    </div>
                                </div>
                            </div>

								<button type="button" class="reset-all-btn" onclick="resetAllToDefault()">
									<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                                    <path d="M3 3v5h5"/>
									</svg>
                                Reset All to Default
                                </button>
                        </div>
                    </div>
                    
                    <div id="preset-tab" class="tab-panel">
                        <div class="preset-controls">
							<div class="preset-grid" id="preset-grid">
                                <!-- Presets will be loaded here -->
                            </div>
                            <button type="button" class="add-preset-btn" onclick="showPresetCreator()">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M5 12h14"/>
                                    <path d="M12 5v14"/>
												</svg>
											</button>
                                    </div>
                                </div>
                                    </div>
                                </div>
                                    </div>
                                    </div>

						<!-- Preset Creator Modal -->
						<?php display_preset_modal(); ?>
    <?php
}

// Save post data
add_action('save_post', 'save_custom_metabox');



function save_custom_metabox($post_id)
{
    
    // Kiểm tra nonce - bật lại để bảo mật
    if (!isset($_POST['custom_metabox_nonce']) || !wp_verify_nonce($_POST['custom_metabox_nonce'], 'custom_metabox_nonce')) {
        return;
    }
            
    // Kiểm tra autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Kiểm tra quyền
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // Chỉ xử lý cho page và post, không xử lý cho elementor_library
    $post_type = get_post_type($post_id);
    if (!in_array($post_type, array('page', 'post'))) {
        return;
    }
    
    // Lưu Template ID và process merge containers
    if (isset($_POST['custom_template_id'])) {
        $template_id_string = sanitize_text_field($_POST['custom_template_id']);
        if (!empty($template_id_string)) {
            // Lưu template ID array để backup
            $template_id_array = array_map('trim', explode(',', $template_id_string));
            $template_id_array = array_filter($template_id_array);
            update_post_meta($post_id, 'mac_custom_template_id', $template_id_array);
            
            // Process template IDs để merge containers
            try {
                $processor = new Template_ID_Processor();
                $merged_containers_json = $processor->process_template_ids($template_id_string);
                
                // Decode để kiểm tra valid JSON
                $merged_containers = json_decode($merged_containers_json, true);
                if (is_array($merged_containers) && !empty($merged_containers)) {
                    // Save merged containers vào _elementor_data (với wp_slash để escape đúng)
                    update_post_meta($post_id, '_elementor_data', wp_slash($merged_containers_json));
                    
                    // Set các meta cần thiết cho Elementor
                    update_post_meta($post_id, '_elementor_edit_mode', 'builder');
                    update_post_meta($post_id, '_elementor_template_type', 'wp-page');
                    update_post_meta($post_id, '_elementor_version', ELEMENTOR_VERSION);
                    update_post_meta($post_id, '_wp_page_template', 'elementor_canvas');
                    
                    // Thêm controls usage để Elementor biết cách render widgets
                    $controls_usage = array(
                        'container' => array(
                            'count' => count($merged_containers),
                            'control_percent' => 1,
                            'controls' => array(
                                'layout' => array('flex-container' => array('size' => count($merged_containers))),
                                'style' => array('section_background' => array('size' => count($merged_containers)))
                            )
                        )
                    );
                    update_post_meta($post_id, '_elementor_controls_usage', $controls_usage);
                    
                    // Thêm thông báo thành công
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-success is-dismissible"><p>Template ID đã được cập nhật thành công.</p></div>';
                    });
                    
                } else {
                    // Thông báo lỗi nếu không có containers hợp lệ
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-error is-dismissible"><p>Template ID không hợp lệ hoặc không tìm thấy dữ liệu.</p></div>';
                    });
                    delete_post_meta($post_id, '_elementor_data');
                }
            } catch (Exception $e) {
                // Thông báo lỗi chi tiết
                add_action('admin_notices', function() use ($e) {
                    echo '<div class="notice notice-error is-dismissible"><p>Lỗi khi xử lý Template ID: ' . esc_html($e->getMessage()) . '</p></div>';
                });
                error_log("Template merge error for post $post_id: " . $e->getMessage());
            }
        } else {
            update_post_meta($post_id, 'mac_custom_template_id', array());
        }
    }
    
    // Lưu custom colors và fonts
    save_custom_colors($post_id);
    save_custom_fonts($post_id);
}





// Custom function to sanitize hex colors including alpha channel
function sanitize_hex_color_with_alpha($color) {
    // Remove any whitespace
    $color = trim($color);
    
    // Check if it's a valid hex color (6 or 8 characters)
    if (preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/', $color)) {
        return $color;
    }
    
    // If not valid, return empty string
    return '';
}

// Save custom colors
function save_custom_colors($post_id)
{
	$colors = array();

    // Save fixed colors (index 0-3)
	$fixed_colors = array(
		0 => array('field' => 'primary_color', 'name' => 'primary', 'text' => '--e-global-color-primary'),
		1 => array('field' => 'secondary_color', 'name' => 'secondary', 'text' => '--e-global-color-secondary'),
		2 => array('field' => 'text_color', 'name' => 'text', 'text' => '--e-global-color-text'),
		3 => array('field' => 'accent_color', 'name' => 'accent', 'text' => '--e-global-color-accent')
	);

    foreach ($fixed_colors as $index => $color_config) {
        $field_name = $color_config['field'];
        
        // Lưu dữ liệu ngay cả khi field không được gửi trong POST
        $color_value = isset($_POST[$field_name]) ? sanitize_hex_color_with_alpha($_POST[$field_name]) : '';
        $colors[$index] = array(
            'name' => $color_config['name'],
            'text' => $color_config['text'],
            'color' => $color_value
        );
    }

	// Save custom colors (starting from index 5, but store at index 4+)
    for ($i = 5; $i <= 20; $i++) {
        $color_field = 'mac_custom_color_' . $i;
        $name_field = 'mac_custom_color_name_' . $i;
		$text_field = 'mac_custom_color_text_' . $i;
        
        if (isset($_POST[$color_field]) && !empty($_POST[$color_field])) {
            $custom_name = isset($_POST[$name_field]) ? sanitize_text_field($_POST[$name_field]) : 'Name Color ' . $i;
			$text_field = isset($_POST[$text_field]) ? sanitize_text_field($_POST[$text_field]) : 'Text Color ' . $i;
            
            // Store at index 4+ (i - 1)
            $store_index = $i - 1;
            $colors[$store_index] = array(
                'name' => $custom_name,
                'text' => $text_field,
                'color' => sanitize_hex_color_with_alpha($_POST[$color_field])
            );
		}
	}

    // Only save the main array, remove individual meta keys
    update_post_meta($post_id, 'mac_custom_colors', $colors);
    
    // Clean up old individual meta keys
//     for ($i = 5; $i <= 20; $i++) {
//         delete_post_meta($post_id, 'mac_custom_color_' . $i);
//         delete_post_meta($post_id, 'mac_custom_color_name_' . $i);
//     }
}

// Save custom fonts
function save_custom_fonts($post_id)
{
    $fonts = array();

    // Save fixed fonts (index 0-3)
    $fixed_fonts = array(
        0 => array('field' => 'primary_font', 'name' => 'primary'),
        1 => array('field' => 'secondary_font', 'name' => 'secondary'),
        2 => array('field' => 'accent_font', 'name' => 'accent')
    );

    foreach ($fixed_fonts as $index => $font_config) {
        $field_name = $font_config['field'];
        
        // Lưu dữ liệu ngay cả khi field không được gửi trong POST
        $font_value = isset($_POST[$field_name]) ? sanitize_text_field($_POST[$field_name]) : '';
        $fonts[$index] = array(
            'name' => $font_config['name'],
            'font' => $font_value
        );
    }

    // Remove saving of custom fonts (disable additional items)

    // Only save the main array, remove individual meta keys and old arrays
    update_post_meta($post_id, 'mac_custom_fonts', $fonts);
    
    // Clean up old individual meta keys
    for ($i = 5; $i <= 20; $i++) {
        delete_post_meta($post_id, 'mac_custom_font_' . $i);
        delete_post_meta($post_id, 'mac_custom_font_name_' . $i);
        delete_post_meta($post_id, 'mac_custom_font_class_' . $i);
    }
    
    // Clean up old separate font classes array
    delete_post_meta($post_id, 'mac_custom_font_classes');
}

// ----------------------------------------------------------------------------------------------

// AJAX handler để lưu preset data
add_action('wp_ajax_mac_save_preset', 'save_preset_data_ajax');

function save_preset_data_ajax() {
    try {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'custom_metabox_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
            return;
        }

        if (!isset($_POST['post_id']) || !isset($_POST['preset_data'])) {
            wp_send_json_error('Missing required fields');
            return;
        }

        $post_id = intval($_POST['post_id']);
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
            return;
        }

        $raw_preset_data = stripslashes($_POST['preset_data']);
        $preset_data = json_decode($raw_preset_data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Invalid JSON data: ' . json_last_error_msg());
            return;
        }

        if (!isset($preset_data['name']) || empty($preset_data['name'])) {
            wp_send_json_error('Preset name is required');
            return;
        }

        if (!isset($preset_data['colors']) || !isset($preset_data['fonts'])) {
            wp_send_json_error('Colors and fonts data are required');
            return;
        }

        // Validate colors structure
        foreach ($preset_data['colors'] as $index => $color) {
            if (!isset($color['name']) || !isset($color['text']) || !isset($color['color'])) {
                wp_send_json_error('Invalid color structure at index ' . $index);
                return;
            }
        }

        // Validate fonts structure
        foreach ($preset_data['fonts'] as $index => $font) {
            if (!isset($font['name']) || !isset($font['text']) || !isset($font['font'])) {
                wp_send_json_error('Invalid font structure at index ' . $index);
                return;
            }
        }

        // Get existing presets or create new array
        $mac_custom_presets = get_post_meta($post_id, 'mac_custom_preset', true);
        if (!is_array($mac_custom_presets)) {
            $mac_custom_presets = array();
        }

        // Add/Update the preset
        $preset_name = sanitize_text_field($preset_data['name']);
        $mac_custom_presets[$preset_name] = array(
            'name' => $preset_name,
            'colors' => $preset_data['colors'],
            'fonts' => $preset_data['fonts']
        );

        // Save to database
        $result = update_post_meta($post_id, 'mac_custom_preset', $mac_custom_presets);

        if ($result !== false) {
            wp_send_json_success(array(
                'message' => 'Preset saved successfully',
                'preset_name' => $preset_name,
                'preset_data' => $mac_custom_presets[$preset_name],
                'presets' => $mac_custom_presets
            ));
        } else {
            wp_send_json_error('Failed to save preset to database');
        }

    } catch (Exception $e) {
        wp_send_json_error('An unexpected error occurred: ' . $e->getMessage());
    }
}

// AJAX handler để xóa preset
add_action('wp_ajax_delete_preset_data', 'delete_preset_data_ajax');
function delete_preset_data_ajax() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'custom_metabox_nonce')) {
        wp_send_json_error('Security check failed');
    }

    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Permission denied');
    }

    $post_id = intval($_POST['post_id']);
    $preset_name = sanitize_text_field($_POST['preset_name']);

    if (!$post_id || empty($preset_name)) {
        wp_send_json_error('Invalid data');
    }

    $existing_presets = get_post_meta($post_id, 'mac_custom_preset', true);
    if (!is_array($existing_presets)) {
        wp_send_json_error('No presets found');
    }

    if (isset($existing_presets[$preset_name])) {
        unset($existing_presets[$preset_name]);
        $result = update_post_meta($post_id, 'mac_custom_preset', $existing_presets);

        if ($result !== false) {
            wp_send_json_success(array(
                'message' => 'Preset đã được xóa thành công',
                'remaining_presets' => count($existing_presets),
                'presets' => $existing_presets // Trả về toàn bộ presets mới
            ));
        } else {
            wp_send_json_error('Failed to update presets');
        }
    } else {
        wp_send_json_error('Preset not found');
    }
}

// AJAX handler để update preset
add_action('wp_ajax_mac_update_preset', 'update_preset_data_ajax');

function update_preset_data_ajax() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'custom_metabox_nonce')) {
        wp_send_json_error('Security check failed');
    }

    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Permission denied');
    }

    $post_id = intval($_POST['post_id']);
    $original_preset_name = sanitize_text_field($_POST['original_name']);
    $preset_data = json_decode(stripslashes($_POST['preset_data']), true);

    if (!$post_id || !$original_preset_name || !$preset_data) {
        wp_send_json_error('Invalid data');
    }

    $existing_presets = get_post_meta($post_id, 'mac_custom_preset', true);
    if (!is_array($existing_presets)) {
        $existing_presets = array();
    }

    $new_preset_name = $preset_data['name'];
    if ($original_preset_name !== $new_preset_name && isset($existing_presets[$original_preset_name])) {
        unset($existing_presets[$original_preset_name]);
    }

    $existing_presets[$new_preset_name] = $preset_data;
    $result = update_post_meta($post_id, 'mac_custom_preset', $existing_presets);

    if ($result !== false) {
        wp_send_json_success(array(
            'message' => 'Preset updated successfully',
            'presets' => $existing_presets,
            'preset_name' => $new_preset_name,
            'original_name' => $original_preset_name,
            'total_presets' => count($existing_presets)
        ));
    } else {
        wp_send_json_error('Failed to update preset');
    }
}

// Font Classes Metabox removed - using integrated className field

// Enqueue admin styles
function macruleid_enqueue_admin_styles() {
    wp_enqueue_style(
        'macruleid-admin-styles',
        plugins_url('css/custom-colors-fonts.css', __FILE__),
        array(),
        '1.0.0'
    );
}
add_action('admin_enqueue_scripts', 'macruleid_enqueue_admin_styles');

// AJAX handler để kiểm tra template ID
add_action('wp_ajax_check_template_id', 'check_template_id_ajax');

function check_template_id_ajax() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'custom_metabox_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce', 'should_reload' => false));
    }

    if (!isset($_POST['template_id'])) {
        wp_send_json_error(array('message' => 'Vui lòng nhập Template ID', 'should_reload' => false));
    }

    $template_id_string = sanitize_text_field($_POST['template_id']);
    $template_ids = array_map('trim', explode(',', $template_id_string));
    $invalid_ids = array();
    $has_elementor_data = false;
    
    // Kiểm tra post hiện tại đã publish chưa
    $current_post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $is_published = false;
    
    if ($current_post_id) {
        $current_post = get_post($current_post_id);
        if ($current_post) {
            $saved_template_ids = get_post_meta($current_post_id, 'mac_custom_template_id', true);
            $elementor_data = get_post_meta($current_post_id, '_elementor_data', true);
            
            // Chuyển saved_template_ids thành array nếu là string
            if (is_string($saved_template_ids)) {
                $saved_template_ids = array_map('trim', explode(',', $saved_template_ids));
            }
            
            // So sánh arrays thay vì strings
            $current_template_ids = array_map('trim', $template_ids);
            
            if ($saved_template_ids && 
                $elementor_data && 
                !empty($saved_template_ids) &&
                !array_diff($current_template_ids, $saved_template_ids) && 
                !array_diff($saved_template_ids, $current_template_ids)) {
                $is_published = true;
            }
        }
    }
    
    foreach ($template_ids as $id) {
        if (strpos($id, 'page:') !== false && strpos($id, '-tem:') !== false) {
            $parts = explode('-tem:', $id);
            $page_id = str_replace('page:', '', $parts[0]);
            $post = get_post($page_id);
        } else {
            $post = get_post($id);
        }

        if (!$post) {
            $invalid_ids[] = $id;
            continue;
        }

        $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
        if (!empty($elementor_data)) {
            $has_elementor_data = true;
        }
    }

    if (!empty($invalid_ids)) {
        wp_send_json_error(array(
            'message' => 'Không tìm thấy template với ID: ' . implode(', ', $invalid_ids),
            'should_reload' => false
        ));
    }

    if (!$has_elementor_data) {
        wp_send_json_error(array(
            'message' => 'Template ID hợp lệ nhưng không có dữ liệu Elementor',
            'should_reload' => false
        ));
    }

    wp_send_json_success(array(
        'message' => $is_published ? '' : 'Template ID hợp lệ và có dữ liệu',
        'should_reload' => true,
        'is_published' => $is_published
    ));
}

// AJAX handler để lưu template ID
add_action('wp_ajax_save_template_id', 'save_template_id_ajax');

function save_template_id_ajax() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'custom_metabox_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
    }

    if (!isset($_POST['template_id']) || !isset($_POST['post_id'])) {
        wp_send_json_error(array('message' => 'Missing required data'));
    }

    $post_id = intval($_POST['post_id']);
    $template_id_string = sanitize_text_field($_POST['template_id']);

    try {
        $template_id_array = array_map('trim', explode(',', $template_id_string));
        $template_id_array = array_filter($template_id_array);
        update_post_meta($post_id, 'mac_custom_template_id', $template_id_array);
        
        $processor = new Template_ID_Processor();
        $merged_containers_json = $processor->process_template_ids($template_id_string);
        
        $merged_containers = json_decode($merged_containers_json, true);
        if (!is_array($merged_containers) || empty($merged_containers)) {
            wp_send_json_error(array('message' => 'Không thể xử lý dữ liệu template'));
            return;
        }

        update_post_meta($post_id, '_elementor_data', wp_slash($merged_containers_json));
        update_post_meta($post_id, '_elementor_edit_mode', 'builder');
        update_post_meta($post_id, '_elementor_template_type', 'wp-page');
        update_post_meta($post_id, '_elementor_version', ELEMENTOR_VERSION);
        update_post_meta($post_id, '_wp_page_template', 'elementor_canvas');
        
        update_post_meta($post_id, '_elementor_controls_usage', array(
            'container' => array(
                'count' => count($merged_containers),
                'control_percent' => 1,
                'controls' => array(
                    'layout' => array('flex-container' => array('size' => count($merged_containers))),
                    'style' => array('section_background' => array('size' => count($merged_containers)))
                )
            )
        ));
        
        // Update post status to published if it's not already
        $post = get_post($post_id);
        if ($post && $post->post_status !== 'publish') {
            wp_update_post(array(
                'ID' => $post_id,
                'post_status' => 'publish'
            ));
        }
        
        // Clear Elementor cache and regenerate CSS
        mac_clear_elementor_cache_and_regenerate($post_id);
        
        wp_send_json_success(array(
            'message' => 'Template ID đã được cập nhật và publish thành công',
            'redirect_url' => get_edit_post_link($post_id, 'raw')
        ));
    } catch (Exception $e) {
        wp_send_json_error(array('message' => 'Lỗi: ' . $e->getMessage()));
    }
}
// AJAX handler để reset template
add_action('wp_ajax_reset_template', 'reset_template_ajax');

function reset_template_ajax() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'template_reset_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
    }

    if (!isset($_POST['post_id'])) {
        wp_send_json_error(array('message' => 'Missing post ID'));
    }

    $post_id = intval($_POST['post_id']);
    
    // Kiểm tra quyền
    if (!current_user_can('edit_post', $post_id)) {
        wp_send_json_error(array('message' => 'Không có quyền chỉnh sửa post này'));
    }

    try {
        // Xóa dữ liệu Elementor
        delete_post_meta($post_id, '_elementor_data');
        delete_post_meta($post_id, '_elementor_edit_mode');
        delete_post_meta($post_id, '_elementor_template_type');
        delete_post_meta($post_id, '_elementor_version');
        delete_post_meta($post_id, '_elementor_controls_usage');
        
        // Xóa template ID
        delete_post_meta($post_id, 'mac_custom_template_id');
        
        // Đặt lại page template về default
        update_post_meta($post_id, '_wp_page_template', 'default');
        
        // Clear Elementor cache and regenerate CSS
        mac_clear_elementor_cache_and_regenerate($post_id);
        
        wp_send_json_success(array(
            'message' => 'Template đã được reset thành công',
            'redirect_url' => get_edit_post_link($post_id, 'raw')
        ));
    } catch (Exception $e) {
        wp_send_json_error(array('message' => 'Lỗi khi reset template: ' . $e->getMessage()));
    }
}

// Function to clear Elementor cache and regenerate CSS
function mac_clear_elementor_cache_and_regenerate($post_id) {
    // Check if Elementor is active
    if (!did_action('elementor/loaded')) {
        return false;
    }
    
    try {
        // Clear Elementor cache
        if (class_exists('\Elementor\Plugin')) {
            // Clear general cache
            \Elementor\Plugin::$instance->files_manager->clear_cache();
            
            // Clear post specific CSS cache
            if (class_exists('\Elementor\Core\Files\CSS\Post')) {
                $css_file = \Elementor\Core\Files\CSS\Post::create($post_id);
                if ($css_file) {
                    $css_file->delete();
                    $css_file->update();
                }
            }
            
            // Clear post meta cache
            delete_post_meta($post_id, '_elementor_css');
            delete_post_meta($post_id, '_elementor_post_css');
            delete_transient('elementor_' . $post_id);
            delete_transient('elementor_css_' . $post_id);
            
            // Force regeneration by updating version
            update_post_meta($post_id, '_elementor_version', ELEMENTOR_VERSION);
            if (defined('ELEMENTOR_PRO_VERSION')) {
                update_post_meta($post_id, '_elementor_pro_version', ELEMENTOR_PRO_VERSION);
            }
            
            // Clear WordPress object cache for this post
            wp_cache_delete($post_id, 'posts');
            wp_cache_delete($post_id, 'post_meta');
            
            // Clear any page caching
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
            
            return true;
        }
    } catch (Exception $e) {
        return false;
    }
    
    return false;
}

// Hiển thị template ID
function display_template_id($post_id) {
    echo '<span class="template-id-value">' . esc_html($post_id) . '</span>';
}

// Hiển thị preview template
function display_template_preview($preview_image_url = '') {
    global $post;
    $template_id = $post ? $post->ID : 0;
    
    echo '<div class="template-preview-container" data-template-id="' . esc_attr($template_id) . '">';
    
    // Loading div
    echo '<div class="template-preview-loading" style="display: none;">Đang tải preview...</div>';
    
    // Image div
    echo '<div class="template-preview-image">';
    if ($preview_image_url) {
        echo '<img src="' . esc_url($preview_image_url) . '" alt="Template Preview" style="width: 100%; height: auto; object-fit: contain; display: block;">';
    }
    echo '</div>';
    
    echo '</div>';
}

// Hiển thị thông báo template ID
function display_template_notice($message) {
    echo '<div class="template-id-notice">' . esc_html($message) . '</div>';
}

// Hiển thị nút copy với icon
function display_copy_button($text, $class = '') {
    echo '<button class="copy-button ' . esc_attr($class) . '">';
    echo '<span class="dashicons dashicons-clipboard"></span> ' . esc_html($text);
    echo '</button>';
}

// Hiển thị modal tạo preset
function display_preset_modal() {
    ?>
    <div id="preset-creator-modal" class="preset-modal" style="display: none;">
        <div class="preset-modal-content">
            <div class="preset-modal-header">
                <div class="header-content">
                    <div class="header-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                        </svg>
                    </div>
                    <div class="header-text">
                        <h3 id="preset-modal-title">Tạo Preset Mới</h3>
                        <p id="preset-modal-description">Tùy chỉnh màu sắc và typography cho template của bạn</p>
                    </div>
                </div>
                <button type="button" class="preset-modal-close" onclick="hidePresetCreator()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18" />
                        <path d="m6 6 12 12" />
                    </svg>
                </button>
            </div>
            <div class="preset-modal-body">
                <div class="preset-creator-form">
                    <div class="preset-form-section">
                        <label for="preset-name">Tên Preset</label>
                        <input type="text" id="preset-name" placeholder="Nhập tên preset..." class="preset-name-input" value="">
                    </div>

                    <div class="preset-controls">
                        <div class="colors-section">
                            <h4><?php _e('Colors', 'mac-ruleid'); ?></h4>
                                <!-- Fixed Colors -->
                                <div class="color-control fixed-color fixed-item">
                                    <label>Màu chính</label>
                                    <input type="text" data-coloris id="preset-primary-color" class="coloris" value="#F26212">
                                </div>

                                <div class="color-control fixed-color fixed-item">
                                    <label>Màu phụ</label>
                                    <input type="text" data-coloris id="preset-secondary-color" class="coloris" value="#FBAE85">
                                </div>

                                <div class="color-control fixed-color fixed-item">
                                    <label>Màu chữ</label>
                                    <input type="text" data-coloris id="preset-text-color" class="coloris" value="#333333">
                                </div>

                                <div class="color-control fixed-color fixed-item">
                                    <label>Màu nhấn</label>
                                    <input type="text" data-coloris id="preset-accent-color" class="coloris" value="#FF6B35">
                                </div>

                                <!-- Custom Colors Container -->
                                <div id="preset-custom-colors-container">
                                    <!-- Custom colors sẽ được add động qua JavaScript -->
                                </div>
                            
                            <!-- Add Custom Color Button -->
                            <button type="button" class="add-item-btn" onclick="addPresetCustomColor()">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M5 12h14" />
                                    <path d="M12 5v14" />
                                </svg>
                            </button>
                        </div>

                        <div class="typography-section">
                            <h4><?php _e('Typography', 'mac-ruleid'); ?></h4>
                            
                                <!-- Fixed Fonts -->
                                <div class="font-control fixed-font fixed-item">
                                <label><?php _e('Font chính', 'mac-ruleid'); ?></label>
                                <select id="preset-primary-font" name="preset_primary_font" class="font-select widefat"></select>
                                </div>
                                <div class="font-control fixed-font fixed-item">
                                <label><?php _e('Font phụ', 'mac-ruleid'); ?></label>
                                <select id="preset-secondary-font" name="preset_secondary_font" class="font-select widefat"></select>
                                </div>
                                <div class="font-control fixed-font fixed-item">
                                <label><?php _e('Font chữ', 'mac-ruleid'); ?></label>
                                <select id="preset-text-font" name="preset_text_font" class="font-select widefat"></select>
                                </div>
                                <div class="font-control fixed-font fixed-item">
                                <label><?php _e('Font nhấn', 'mac-ruleid'); ?></label>
                                <select id="preset-accent-font" name="preset_accent_font" class="font-select widefat"></select>
                                </div>

                                <!-- Custom Fonts Container -->
                                <div id="preset-custom-fonts-container">
                                    <!-- Custom fonts sẽ được add động qua JavaScript -->
                                </div>
                            
                            <!-- Add Custom Font Button -->
                            <button type="button" class="add-item-btn" onclick="addPresetCustomFont()">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M5 12h14" />
                                    <path d="M12 5v14" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="preset-modal-footer">
                <button type="button" id="preset-save-button" class="preset-save-btn" onclick="saveCustomPreset()">Lưu Preset</button>
            </div>
        </div>
    </div>
    <?php
}

// Hiển thị nút publish
function display_publish_button($post, $is_published) {
    $button_text = $is_published ? 'Update' : 'Publish';
    $button_class = $is_published ? 'button-primary' : 'button-primary button-large';
    ?>
    <button type="button" 
        id="mac-custom-publish-btn" 
        class="button <?php echo esc_attr($button_class); ?>" 
        data-post-id="<?php echo esc_attr($post->ID); ?>">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="publish-icon">
            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
            <polyline points="17,21 17,13 7,13 7,21"/>
            <polyline points="7,3 7,8 15,8"/>
        </svg>
        <?php echo esc_html($button_text); ?>
    </button>
    <span id="custom-publish-status" class="custom-publish-status"></span>
    <?php
}

// Hiển thị nút reset
function display_reset_button() {
    ?>
    <button type="button" 
        id="reset-template-btn" 
        class="button button-secondary reset-button" 
        data-nonce="<?php echo wp_create_nonce('template_reset_nonce'); ?>">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="reset-icon">
            <path d="M3 3h18v18H3z"/>
            <line x1="15" y1="9" x2="9" y2="15"/>
            <line x1="9" y1="9" x2="15" y2="15"/>
        </svg>
        Reset Template
    </button>
    <?php
}

// Add AJAX handler for saving custom data
add_action('wp_ajax_mac_save_custom_data', 'save_custom_data_ajax');
function save_custom_data_ajax() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'custom_metabox_nonce')) {
        wp_send_json_error('Invalid nonce');
    }

    // Verify post ID
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$post_id || !current_user_can('edit_post', $post_id)) {
        wp_send_json_error('Invalid post ID or insufficient permissions');
    }

    // Get and validate custom data
    $custom_data = isset($_POST['custom_data']) ? json_decode(stripslashes($_POST['custom_data']), true) : null;
    if (!$custom_data) {
        wp_send_json_error('Invalid custom data');
    }

    // Get existing data to merge with fixed items (0-3)
    $existing_colors = get_post_meta($post_id, 'mac_custom_colors', true);
    $existing_fonts = get_post_meta($post_id, 'mac_custom_fonts', true);
    
    if (!is_array($existing_colors)) $existing_colors = array();
    if (!is_array($existing_fonts)) $existing_fonts = array();

    // Save colors - merge custom items (4+) with existing fixed items (0-3)
    if (isset($custom_data['colors'])) {
        $final_colors = $existing_colors;
        
        // Update only custom items (index 4+)
        foreach ($custom_data['colors'] as $index => $color_data) {
            $final_colors[$index] = $color_data;
        }
        
        update_post_meta($post_id, 'mac_custom_colors', $final_colors);
    }

    // Save fonts - merge custom items (4+) with existing fixed items (0-3) 
    if (isset($custom_data['fonts'])) {
        $final_fonts = $existing_fonts;
        
        // Update only custom items (index 4+)
        foreach ($custom_data['fonts'] as $index => $font_data) {
            $final_fonts[$index] = $font_data;
        }
        
        update_post_meta($post_id, 'mac_custom_fonts', $final_fonts);
    }

    wp_send_json_success('Custom data saved successfully');
}

// Hook vào save_post để tự động lưu custom data
add_action('save_post', 'auto_save_custom_data', 10, 3);
function auto_save_custom_data($post_id, $post, $update) {
    // Tránh auto-save, revision, và bulk edit
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id) || 
        (defined('DOING_BULK_EDIT') && DOING_BULK_EDIT)) {
        return;
    }
    
    // Chỉ xử lý với post types được hỗ trợ
    if (!in_array($post->post_type, ['post', 'page'])) {
        return;
    }
    
    // Chỉ lưu khi có POST data từ form của chúng ta
    if (!isset($_POST['custom_metabox_nonce'])) {
        return;
    }
    
    // Kiểm tra nonce
    if (!wp_verify_nonce($_POST['custom_metabox_nonce'], 'custom_metabox_nonce')) {
        return;
    }
    
    // Kiểm tra quyền edit
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // Lưu custom colors và fonts
    save_custom_colors($post_id);
    save_custom_fonts($post_id);
}



// Force regenerate CSS on frontend if needed
add_action('template_redirect', 'mac_maybe_regenerate_css');
function mac_maybe_regenerate_css() {
    if (is_admin() || !is_singular()) {
        return;
    }
    
    global $post;
    if (!$post) {
        return;
    }
    
    // Check if this post has our template
    $template_id = get_post_meta($post->ID, 'mac_custom_template_id', true);
    if (!$template_id) {
        return;
    }
    
    // Check if CSS files exist and are fresh
    $css_meta = get_post_meta($post->ID, '_elementor_css', true);
    $css_path = '';
    
    // Safely check if CSS meta exists and has path
    if (is_array($css_meta) && isset($css_meta['path']) && !empty($css_meta['path'])) {
        $css_path = $css_meta['path'];
    }
    
    if (!$css_meta || empty($css_path) || !file_exists($css_path)) {
        // CSS file missing, trigger regeneration
        if (did_action('elementor/loaded') && class_exists('\Elementor\Core\Files\CSS\Post')) {
            $css_file = \Elementor\Core\Files\CSS\Post::create($post->ID);
            if ($css_file) {
                $css_file->update();
            }
        }
    }
}

// Force CSS update when viewing pages with our templates
add_action('wp_head', 'mac_ensure_elementor_css', 1);
function mac_ensure_elementor_css() {
    if (is_admin() || !is_singular()) {
        return;
    }
    
    global $post;
    if (!$post) {
        return;
    }
    
    // Check if this post has our template
    $template_id = get_post_meta($post->ID, 'mac_custom_template_id', true);
    if (!$template_id) {
        return;
    }
    
    // Force enqueue Elementor frontend CSS
    if (did_action('elementor/loaded')) {
        // Ensure post CSS is loaded
        if (class_exists('\Elementor\Core\Files\CSS\Post')) {
            $css_file = \Elementor\Core\Files\CSS\Post::create($post->ID);
            if ($css_file) {
                $css_file->enqueue();
            }
        }
        
        // Ensure global CSS is loaded  
        if (class_exists('\Elementor\Core\Files\CSS\Global_CSS')) {
            $global_css = \Elementor\Core\Files\CSS\Global_CSS::create('global.css');
            if ($global_css) {
                $global_css->enqueue();
            }
        }
    }
}

// (Removed) Output CSS here. Font CSS mapping will be handled by mac-live-style plugin.

// Function to auto generate template preview
function mac_auto_generate_template_preview($post_id) {
    // Check if this is an Elementor template
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'elementor_library') {
        return false;
    }
    
    // Check if preview already exists
    $existing_preview = get_post_meta($post_id, '_template_preview_url', true);
    if ($existing_preview) {
        return true; // Preview already exists
    }
    
    // Schedule preview generation for next request
    wp_schedule_single_event(time() + 5, 'mac_generate_template_preview', array($post_id));
    
    return true;
}

// Hook to generate preview
add_action('mac_generate_template_preview', 'mac_generate_template_preview_callback');
function mac_generate_template_preview_callback($post_id) {
    // This will be handled by JavaScript when the page loads
    // We just need to trigger the preview generation
    update_post_meta($post_id, '_needs_preview_generation', 'yes');
}

// AJAX handler để manually clear cache
add_action('wp_ajax_mac_clear_cache', 'mac_clear_cache_ajax');
function mac_clear_cache_ajax() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'custom_metabox_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
    }

    if (!isset($_POST['post_id'])) {
        wp_send_json_error(array('message' => 'Missing post ID'));
    }

    $post_id = intval($_POST['post_id']);
    
    // Kiểm tra quyền
    if (!current_user_can('edit_post', $post_id)) {
        wp_send_json_error(array('message' => 'Không có quyền chỉnh sửa post này'));
    }

    try {
        $result = mac_clear_elementor_cache_and_regenerate($post_id);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Cache đã được xóa và CSS đã được tạo lại thành công'
            ));
        } else {
            wp_send_json_error(array('message' => 'Không thể xóa cache hoặc tạo lại CSS'));
        }
    } catch (Exception $e) {
        wp_send_json_error(array('message' => 'Lỗi: ' . $e->getMessage()));
    }
}

// ===== COPY META FUNCTIONS START =====

/**
 * Tìm page "home" tương ứng theo suffix
 * 
 * @param int $current_page_id ID của page hiện tại
 * @return int|false ID của page home tương ứng hoặc false nếu không tìm thấy
 */
function find_corresponding_home_page($current_page_id) {
    $current_post = get_post($current_page_id);
    if (!$current_post) {
        return false;
    }
    
    $parent_id = $current_post->post_parent;
    $current_slug = $current_post->post_name;
    
    // Extract suffix from current page slug
    $suffix = '';
    if (preg_match('/-(\d+)$/', $current_slug, $matches)) {
        $suffix = '-' . $matches[1];
    }
    
    // Find home page with same suffix
    $home_slug = 'home' . $suffix;
    
    // Method 1: If has parent, search in same parent
    if ($parent_id > 0) {
        $pages = get_pages(array(
            'parent' => $parent_id,
            'post_status' => 'publish',
            'number' => -1
        ));
        
        foreach ($pages as $page) {
            if ($page->post_name === $home_slug) {
                return $page->ID;
            }
        }
    }
    
    // Method 2: Search by slug globally (fallback)
    $home_page = get_page_by_path($home_slug, OBJECT, 'page');
    if ($home_page) {
        return $home_page->ID;
    }
    
    // Method 3: Search for any page with slug starting with 'home' and same suffix
    $all_pages = get_pages(array(
        'post_status' => 'publish',
        'number' => -1
    ));
    
    foreach ($all_pages as $page) {
        if ($page->post_name === $home_slug) {
            return $page->ID;
        }
    }
    
    // Method 4: If no suffix, try to find any 'home' page
    if (empty($suffix)) {
        $home_page = get_page_by_path('home', OBJECT, 'page');
        if ($home_page) {
            return $home_page->ID;
        }
    }
    
    return false;
}

/**
 * Copy post meta từ source page sang target page
 * 
 * @param int $source_id ID của page nguồn
 * @param int $target_id ID của page đích
 * @return bool True nếu thành công, false nếu thất bại
 */
function copy_mac_meta_data($source_id, $target_id) {
    if (!$source_id || !$target_id) {
        return false;
    }
    
    // Get source meta data
    $source_colors = get_post_meta($source_id, 'mac_custom_colors', true);
    $source_fonts = get_post_meta($source_id, 'mac_custom_fonts', true);
    
    $colors_copied = false;
    $fonts_copied = false;
    
    // Copy colors (even if empty, we still want to copy the structure)
    $result = update_post_meta($target_id, 'mac_custom_colors', $source_colors);
    if ($result !== false) {
        $colors_copied = true;
    }
    
    // Copy fonts (even if empty, we still want to copy the structure)
    $result = update_post_meta($target_id, 'mac_custom_fonts', $source_fonts);
    if ($result !== false) {
        $fonts_copied = true;
    }
    
    // Return true if at least one meta was copied successfully
    return $colors_copied || $fonts_copied;
}

// AJAX handler để copy meta data
add_action('wp_ajax_mac_copy_meta_from_home', 'mac_copy_meta_from_home_ajax');
function mac_copy_meta_from_home_ajax() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'copy_meta_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
    }
    
    // Verify post ID
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$post_id || !current_user_can('edit_post', $post_id)) {
        wp_send_json_error(array('message' => 'Invalid post ID or insufficient permissions'));
    }
    
    // Find corresponding home page
    $home_page_id = find_corresponding_home_page($post_id);
    if (!$home_page_id) {
        // Debug information
        $current_post = get_post($post_id);
        $parent_id = $current_post->post_parent;
        $parent_title = $parent_id ? get_the_title($parent_id) : 'No Parent';
        $current_slug = $current_post->post_name;
        
        // Extract suffix
        $suffix = '';
        if (preg_match('/-(\d+)$/', $current_slug, $matches)) {
            $suffix = '-' . $matches[1];
        }
        $expected_home_slug = 'home' . $suffix;
        
        // Get all pages in same parent for debugging
        $pages_in_parent = get_pages(array(
            'parent' => $parent_id,
            'post_status' => 'publish',
            'number' => -1
        ));
        
        $page_slugs = array();
        foreach ($pages_in_parent as $page) {
            $page_slugs[] = $page->post_name . ' (ID: ' . $page->ID . ')';
        }
        
        // Get all pages for debugging
        $all_pages = get_pages(array(
            'post_status' => 'publish',
            'number' => 20 // Limit to first 20 pages for debugging
        ));
        
        $all_page_slugs = array();
        foreach ($all_pages as $page) {
            $all_page_slugs[] = $page->post_name . ' (ID: ' . $page->ID . ')';
        }
        
        $debug_message = sprintf(
            'Không tìm thấy page "home" tương ứng.<br><br>' .
            '<strong>Debug Info:</strong><br>' .
            'Current Page: %s (ID: %d)<br>' .
            'Parent Page: %s (ID: %d)<br>' .
            'Current Slug: %s<br>' .
            'Expected Home Slug: %s<br>' .
            'Pages in Parent: %s<br>' .
            'All Pages (first 20): %s',
            $current_post->post_title,
            $post_id,
            $parent_title,
            $parent_id,
            $current_slug,
            $expected_home_slug,
            implode(', ', $page_slugs),
            implode(', ', $all_page_slugs)
        );
        
        wp_send_json_error(array('message' => $debug_message));
    }
    
    // Copy meta data
    $success = copy_mac_meta_data($home_page_id, $post_id);
    
    if ($success) {
        wp_send_json_success(array(
            'message' => 'Đã copy thành công meta data từ page "home" tương ứng',
            'home_page_id' => $home_page_id,
            'home_page_title' => get_the_title($home_page_id)
        ));
    } else {
        wp_send_json_error(array('message' => 'Không thể copy meta data'));
    }
}

// ===== COPY META FUNCTIONS END =====

// ===== GOOGLE FONTS API INTEGRATION START =====
add_action('wp_ajax_macruleid_refresh_google_fonts', 'macruleid_refresh_google_fonts_callback');
add_action('wp_ajax_nopriv_macruleid_refresh_google_fonts', '__return_false');

function macruleid_refresh_google_fonts_callback() {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }
    $fonts = macruleid_get_google_fonts_list(true);
    if (!empty($fonts)) {
        wp_send_json_success(['fonts' => $fonts]);
    } else {
        wp_send_json_error(['message' => 'Could not fetch font list']);
    }
}
// ===== GOOGLE FONTS API INTEGRATION END =====

// Truyền danh sách font sang JS cho custom-colors-fonts.js
add_action('admin_enqueue_scripts', function($hook) {
    global $post;
    if ($hook === 'post.php' || $hook === 'post-new.php') {
        $fonts = macruleid_get_google_fonts_list();
        wp_enqueue_script('jquery');
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0', true);
        wp_enqueue_script('mac-custom-colors-fonts-js', MAC_RULEID_URI . 'js/custom-colors-fonts.js', array('jquery', 'select2'), '1.0.0', true);
        wp_localize_script('mac-custom-colors-fonts-js', 'macFontsData', array(
            'fonts' => $fonts,
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('macruleid_fonts_nonce'),
        ));
    }
});


// ===== ADMIN THEME OPTIONS MENU FOR FONT REFRESH =====
add_action('admin_menu', function() {
    add_menu_page(
        __('Theme Options', 'mac-ruleid'),
        __('Theme Options', 'mac-ruleid'),
        'manage_options',
        'macruleid-theme-options',
        'macruleid_theme_options_page',
        'dashicons-admin-generic',
        60
    );
});

function macruleid_theme_options_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    ?>
    <div class="wrap">
        <h1><?php _e('Theme Options', 'mac-ruleid'); ?></h1>
        
        <!-- Font Management Section -->
        <div class="card" style="margin-bottom: 20px;">
            <h2 class="title">Font Management</h2>
            <p><button id="refresh-font-list-admin" class="button button-primary">Refresh Font List</button></p>
            <div id="refresh-font-list-result"></div>
        </div>
        
        <!-- Mac Preview Module Section -->
        <div class="card">
            <h2 class="title">Mac Preview Module</h2>
            <p><strong>Status:</strong> 
                <?php if (function_exists('mac_preview_is_elementor_page')): ?>
                    <span style="color: green;">✓ Active</span>
                <?php else: ?>
                    <span style="color: red;">✗ Inactive</span>
                <?php endif; ?>
            </p>
            <p><strong>Description:</strong> Dynamic section export and preview functionality for Elementor containers.</p>
            <p><strong>Features:</strong></p>
            <ul>
                <li>Auto-scan Elementor containers on page load</li>
                <li>Floating export button on frontend</li>
                <li>JSON export functionality</li>
                <li>Drag & drop container management</li>
                <li>URL scanning for remote containers</li>
            </ul>
            <p><strong>Requirements:</strong> Elementor plugin must be installed and activated.</p>
            <?php if (!class_exists('\Elementor\Plugin')): ?>
                <div class="notice notice-warning inline">
                    <p>⚠️ <strong>Warning:</strong> Elementor plugin is not detected. Mac Preview module requires Elementor to function properly.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
    jQuery(document).ready(function($){
        $('#refresh-font-list-admin').on('click', function(){
            var $btn = $(this);
            $btn.prop('disabled', true).text('Refreshing...');
            $('#refresh-font-list-result').html('');
            $.post(ajaxurl, {
                action: 'macruleid_refresh_google_fonts_admin',
                _ajax_nonce: '<?php echo wp_create_nonce('macruleid_fonts_nonce'); ?>'
            }, function(response){
                if(response.success){
                    $('#refresh-font-list-result').html('<span style="color:green;">Font list refreshed! Total: '+response.data.count+'</span>');
                }else{
                    $('#refresh-font-list-result').html('<span style="color:red;">Failed: '+(response.data && response.data.message ? response.data.message : 'Unknown error')+'</span>');
                }
            }).always(function(){
                $btn.prop('disabled', false).text('Refresh Font List');
            });
        });
    });
    </script>
    <?php
}

add_action('wp_ajax_macruleid_refresh_google_fonts_admin', function() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }
    $fonts = macruleid_get_google_fonts_list(true, true); // force refresh, save to options
    if (!empty($fonts)) {
        wp_send_json_success(['count' => count($fonts)]);
    } else {
        wp_send_json_error(['message' => 'Could not fetch font list']);
    }
});

function macruleid_get_google_fonts_list($force_refresh = false, $save_to_options = false) {
    $option_key = 'macruleid_google_fonts_list';
    $transient_key = 'macruleid_google_fonts_list';
    $api_key = 'AIzaSyAzwOkNxe0Oo6S4AbYLr3yUaiaYCzUBSuI';
    $cache_time = WEEK_IN_SECONDS;

    // Nếu không force_refresh, ưu tiên lấy từ options
    if (!$force_refresh) {
        $fonts = get_option($option_key);
        if (is_array($fonts) && !empty($fonts)) {
            return $fonts;
        }
    }
    // Nếu không có trong options, thử lấy từ transient (backward compatibility)
    if (!$force_refresh) {
        $fonts = get_transient($transient_key);
        if ($fonts !== false) {
            if (empty($fonts)) {
                delete_transient($transient_key);
            }
            return $fonts;
        }
    }

    $url = "https://www.googleapis.com/webfonts/v1/webfonts?sort=alpha&key={$api_key}";
    $response = wp_remote_get($url, array('timeout' => 20));
    if (is_wp_error($response)) {
        return array();
    }
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    $fonts = array();
    if (!empty($data['items'])) {
        foreach ($data['items'] as $item) {
            if (!empty($item['family'])) {
                $fonts[] = array('value' => $item['family'], 'text' => $item['family']);
            }
        }
    }
    if ($save_to_options) {
        update_option($option_key, $fonts);
    } else {
        set_transient($transient_key, $fonts, $cache_time);
    }
    if (empty($fonts)) {
        delete_option($option_key);
        delete_transient($transient_key);
    }
    return $fonts;
}

// add_filter('template_include', function($template) {
//     // Kiểm tra nếu truy cập đúng slug
//     if (get_query_var('pagename') === 'page-mac-dynamic-section') {
//         $custom_template = plugin_dir_path(__FILE__) . 'templates/page-mac-dynamic-section.php';
//         if (file_exists($custom_template)) {
//             return $custom_template;
//         }
//     }
//     return $template;
// });

// add_action('wp_ajax_mac_delete_temp_post', function() {
//     check_ajax_referer('mac_delete_temp_post');
//     $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
//     if ($post_id && get_post_status($post_id) === 'draft') {
//         wp_delete_post($post_id, true);
//         wp_send_json_success('Deleted');
//     }
//     wp_send_json_error('Invalid');
// });

add_action('elementor/library/import_template', function($template_id) {
    if (class_exists('\Elementor\\Core\\Files\\CSS\\Post')) {
        $css_file = \Elementor\Core\Files\CSS\Post::create($template_id);
        if ($css_file) {
            $css_file->update();
        }
    }
});
