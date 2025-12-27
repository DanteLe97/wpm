<?php

/**
 * Plugin Name: MAC LiveStyle
 * Plugin URI: https://yourwebsite.com
 * Description: Thay đổi màu sắc & font chữ trực tiếp trên frontend
 * Version: 1.1.1
 * Author: mac-marketing
 * License: GPL v2 or later
 * Text Domain: elementor-live-color
 */

// Prevent direct access
if (!defined('ABSPATH')) {
  exit;
}

class ElementorLiveColorChanger {

  private $addons = array();

  public function __construct() {
    add_action('init', array($this, 'init'));
  }

  public function init() {
    // Hook vào frontend
    add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
    add_action('wp_footer', array($this, 'render_color_control'));

    // AJAX handlers
    add_action('wp_ajax_export_page_settings', array($this, 'export_page_settings'));
    add_action('wp_ajax_nopriv_export_page_settings', array($this, 'export_page_settings'));
    add_action('wp_ajax_export_site_settings', array($this, 'export_site_settings'));
    add_action('wp_ajax_nopriv_export_site_settings', array($this, 'export_site_settings'));
  }

  /**
   * Enqueue frontend assets
   */
  public function enqueue_frontend_assets() {
    // Chỉ load trên frontend và khi user có quyền
    // if (is_admin() || !current_user_can('edit_theme_options')) {
    //   return;
    // }

    wp_enqueue_script('jquery');
	      // Enqueue Select2 for font selects
    wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0');
    wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0', true);
    wp_enqueue_style(
      'elementor-live-color-css',
      plugin_dir_url(__FILE__) . 'assets/live-color.css',
      array(),
      '1.0.0'
    );
    wp_enqueue_script(
      'elementor-live-color-js',
      plugin_dir_url(__FILE__) . 'assets/live-color.js',
      array('jquery'),
      '1.0.0',
      true
    );

    // Localize script cho AJAX
    $post_id = get_the_ID();
    $mac_custom_colors = get_post_meta($post_id, 'mac_custom_colors', true);
    if (empty($mac_custom_colors)) {
      $mac_custom_colors = [];
    }

    // Load fonts from macruleid if available
    $fonts_list = array();
    if (function_exists('macruleid_get_google_fonts_list')) {
      $fonts_list = macruleid_get_google_fonts_list(false, false);
    }

    $script_data = array(
      'ajaxurl' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('elementor_live_color_nonce'),
      'colors' => $mac_custom_colors,
      'fonts' => $fonts_list,
      'addons' => array()
    );

    // Thêm dữ liệu từ các addon
    foreach ($this->addons as $addon) {
      if (method_exists($addon, 'get_script_data')) {
        $script_data['addons'][$addon->get_name()] = $addon->get_script_data();
      }
    }

    wp_localize_script('elementor-live-color-js', 'elementorLiveColor', $script_data);
    // Enqueue live-content-edit.js
//     wp_enqueue_script(
//       'elementor-live-content-edit-js',
//       plugin_dir_url(__FILE__) . 'assets/live-content-edit.js',
//       array('jquery'),
//       '1.0.0',
//       true
//     );
  }

  /**
   * Render color control panel
   */
  public function render_color_control() {
    // Chỉ hiển thị cho user có quyền
    // if (is_admin() || !current_user_can('edit_theme_options')) {
    //   return;
    // }
    global $post;
    if (!$post || $post->post_parent == 0) {
      return; // Không hiển thị nếu không phải page con
    }
    
    // Kiểm tra trạng thái đăng nhập
    $is_logged_in = is_user_logged_in();
?>
<!-- Button quay về Front Page -->
    <div id="back-to-frontpage-toggle">
      <a href="<?php echo esc_url(get_home_url()); ?>" class="back-button" title="<?php _e('Quay về trang chủ', 'elementor-live-color'); ?>">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-home">
          <path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/>
          <path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
        </svg>
      </a>
    </div>
    <div id="color-control-toggle">
      <button class="toggle-button">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-columns3-cog-icon lucide-columns-3-cog">
          <path d="M10.5 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5.5" />
          <path d="m14.3 19.6 1-.4" />
          <path d="M15 3v7.5" />
          <path d="m15.2 16.9-.9-.3" />
          <path d="m16.6 21.7.3-.9" />
          <path d="m16.8 15.3-.4-1" />
          <path d="m19.1 15.2.3-.9" />
          <path d="m19.6 21.7-.4-1" />
          <path d="m20.7 16.8 1-.4" />
          <path d="m21.7 19.4-.9-.3" />
          <path d="M9 3v18" />
          <circle cx="18" cy="18" r="3" />
        </svg>
      </button>
    </div>

    <div id="elementor-color-control-panel" data-logged-in="<?php echo $is_logged_in ? '1' : '0'; ?>">
      <div class="panel-header">
        <h3><?php _e('Custom Color', 'elementor-live-color'); ?></h3>
         <div class="panel-header-actions">
          <button class="copy-url-btn" id="copy-color-url" title="<?php _e('Copy link with current colors', 'elementor-live-color'); ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect width="14" height="14" x="8" y="8" rx="2" ry="2"/>
              <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>
            </svg>
            <span class="copy-label"><?php _e('Get Demo', 'elementor-live-color'); ?></span>
          </button>
          <button class="close-panel">×</button>
        </div>
      </div>
      <div class="panel-tabs">
        <button class="tab-button active" data-tab="custom"><?php _e('Tùy chỉnh', 'elementor-live-color'); ?></button>
        <button class="tab-button" data-tab="preset"><?php _e('Preset', 'elementor-live-color'); ?></button>
        <?php /* if ($is_logged_in): ?>
        <button class="tab-button logged-in-only" data-tab="edit-content"><?php _e('Edit Content', 'elementor-live-color'); ?></button>
        <?php endif; */ ?>
      </div>
      <?php
      $post_id = get_the_ID();
      $mac_custom_font = get_post_meta($post_id, 'mac_custom_fonts', true);
      if (empty($mac_custom_font)) {
        $mac_custom_font = [];
      }

      $mac_array_colors = get_post_meta($post_id, 'mac_custom_colors', true);
      if (empty($mac_array_colors)) {
        $mac_array_colors = [];
      }
      ?>
      <div class="tab-content" style="height: 50vh; max-height: 420px; overflow-y: auto;">
        <div id="custom-tab" class="tab-pane active">
          <div class="color-controls">


            <?php /* Colors */ ?>
            <h4><?php _e('Colors', 'elementor-live-color'); ?></h4>
            <!-- 			  <div id="color-picker"></div> -->
            <?php
            // Tạo HTML động cho các màu từ database
            if (!empty($mac_array_colors) && is_array($mac_array_colors)) {
				$color_index = 1;
              foreach ($mac_array_colors as $color_item) {
                if (isset($color_item['name']) && isset($color_item['color'])) {
                  // Tạo slug từ name
                  $color_slug = sanitize_title($color_item['name']);
                  $prefix = "--e-global-color-";
                  $result = str_replace($prefix, "", $color_item['text']);
            ?>
          
                  <div class="color-control">
                    <?php if($color_index < 5){ ?>
					  <label><?php echo esc_html($color_item['name']); ?></label>
					  <?php } else{ ?>
					  <label> Color <?php echo esc_html($color_index - 4); ?> </label>
					  <?php } ?>
                    <input
                      type="text"
                      class="coloris"
                      data-color="<?php echo esc_attr($result); ?>"
                      value="<?php echo esc_attr($color_item['color']); ?>">
                    <div
                      class="color-preview"
                      id="<?php echo esc_attr($color_slug); ?>-preview"
                      style="background-color: <?php echo esc_attr($color_item['color']); ?>;">
                    </div>
                  </div>

              <?php
					$color_index +=1;
                }
              }
            } else {
              // Fallback nếu không có dữ liệu từ database
              ?>
              <div class="color-control">
                <label><?php _e('Màu chính', 'elementor-live-color'); ?></label>
                <input type="color" id="primary-color" value="#f26212">
                <div class="color-preview" id="primary-preview"></div>
              </div>
              <div class="color-control">
                <label><?php _e('Màu phụ', 'elementor-live-color'); ?></label>
                <input type="color" id="secondary-color" value="#6c757d">
                <div class="color-preview" id="secondary-preview"></div>
              </div>
              <div class="color-control">
                <label><?php _e('Màu chữ', 'elementor-live-color'); ?></label>
                <input type="color" id="text-color" value="#333333">
                <div class="color-preview" id="text-preview"></div>
              </div>
              <div class="color-control">
                <label><?php _e('Màu nhấn', 'elementor-live-color'); ?></label>
                <input type="color" id="accent-color" value="#28a745">
                <div class="color-preview" id="accent-preview"></div>
              </div>
            <?php
            }
            ?>

            <?php //if ($is_logged_in): ?>
            <?php /* Fonts */ ?>
            <h4><?php _e('Typography', 'elementor-live-color'); ?></h4>
            <?php
            // Tạo HTML động cho các font từ database
            if (!empty($mac_custom_font) && is_array($mac_custom_font)) {
              foreach ($mac_custom_font as $font_item) {
                if (isset($font_item['name']) && isset($font_item['font'])) {
                  // Tạo slug từ name
                  $font_slug = sanitize_title($font_item['name']);
                  $allowed_fonts = ['Clash Display', 'Times New Roman', 'Archivo', 'Arial', 'Helvetica', 'Georgia', 'Verdana'];
                  if (in_array($font_item['font'], $allowed_fonts, true)) {
                    $defaultSelected = false;
                  } else {
                    $defaultSelected = true;
                  }
            ?>
                  <div class="font-control">
                    <select id="<?php echo esc_attr($font_slug); ?>-font" class="font-select" data-current="<?php echo esc_attr($font_item['font']); ?>"></select>
                  </div>
              <?php
                }
              }
            } else {
              // Fallback nếu không có dữ liệu từ database
              ?>
              <div class="font-control">
                <label><?php _e('Font chính', 'elementor-live-color'); ?></label>
                <select id="primary-font" class="font-select" data-current=""></select>
              </div>
              <div class="font-control">
                <label><?php _e('Font phụ', 'elementor-live-color'); ?></label>
                <select id="secondary-font" class="font-select" data-current=""></select>
              </div>
            <?php
            }
            ?>
            <?php //endif; ?>
          </div>
        </div>
        <div id="preset-tab" class="tab-pane">
          <div class="preset-controls">
            <div class="preset-grid">
              <div class="preset-item" data-preset="modern">
                <div class="preset-preview" style="background: linear-gradient(45deg, #F26212, #FBAE85);">
                  <span class="preset-name">Modern</span>
                </div>
              </div>
              <div class="preset-item" data-preset="minimal">
                <div class="preset-preview" style="background: linear-gradient(45deg, #333333, #666666);">
                  <span class="preset-name">Minimal</span>
                </div>
              </div>
              <div class="preset-item" data-preset="nature">
                <div class="preset-preview" style="background: linear-gradient(45deg, #28a745, #20c997);">
                  <span class="preset-name">Nature</span>
                </div>
              </div>
              <div class="preset-item" data-preset="ocean">
                <div class="preset-preview" style="background: linear-gradient(45deg, #007bff, #17a2b8);">
                  <span class="preset-name">Ocean</span>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php /* if ($is_logged_in): ?>
        <div id="edit-content-tab" class="tab-pane logged-in-only" style="max-height: 420px; overflow-y: auto;">
          <div class="edit-content-controls">
            <?php $this->render_edit_content_tab(); ?>
          </div>
        </div>
        <?php endif; */ ?>
      </div>

      <?php if ($is_logged_in): ?>
      <div class="control-actions logged-in-only">
        <?php /* <button class="download-btn" id="export-json-page" title="<?php _e('Export Page Settings', 'elementor-live-color'); ?>">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-download-icon lucide-download">
            <path d="M12 15V3" />
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
            <path d="m7 10 5 5 5-5" />
          </svg>
          <span><?php _e('Page', 'elementor-live-color'); ?></span>
        </button> */ ?>
        <button class="download-btn site-export-btn" id="export-site-settings" title="<?php _e('Export Site Settings', 'elementor-live-color'); ?>">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-globe-icon lucide-globe">
            <circle cx="12" cy="12" r="10" />
            <path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20" />
            <path d="M2 12h20" />
          </svg>
          <span><?php _e('Site', 'elementor-live-color'); ?></span>
        </button>
      </div>
      <?php endif; ?>
      
      <div class="control-actions">
        <button class="reset-btn" id="reset-colors" title="<?php _e('Reset Colors', 'elementor-live-color'); ?>">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-refresh-cw">
            <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/>
            <path d="M21 3v5h-5"/>
            <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/>
            <path d="M8 16H3v5"/>
          </svg>
          <span><?php _e('Reset', 'elementor-live-color'); ?></span>
        </button>
      </div>
    </div>

    <style>
      .edit-content-controls {
        max-height: 420px;
        overflow-y: auto;
      }
    </style>
    <?php
  }

  public function export_page_settings() {
    check_ajax_referer('elementor_live_color_nonce', 'nonce');

    // Mở quyền cho khách (không yêu cầu capability), đã có nonce bảo vệ

    $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
    if (!$page_id) {
      wp_send_json_error('Invalid page ID');
    }

    // Lấy nội dung trang
    $page = get_post($page_id);
    if (!$page) {
      wp_send_json_error('Page not found');
    }

    // Lấy nội dung Elementor
    $elementor_data = get_post_meta($page_id, '_elementor_data', true);
    if (!$elementor_data) {
      $elementor_data = $page->post_content;
    }

    // Tìm tất cả các section ID trong nội dung
    $section_ids = array();
    if (preg_match_all('/sectionID-(\d+)/', $elementor_data, $matches)) {
      $section_ids = array_unique($matches[1]);
    }

    // Lấy màu sắc hiện tại đã thay đổi từ frontend (được gửi qua AJAX)
    $current_colors = isset($_POST['current_colors']) ? $_POST['current_colors'] : array();

    // Nếu không có màu từ frontend, fallback về database
    if (empty($current_colors)) {
      $mac_custom_colors = get_post_meta($page_id, 'mac_custom_colors', true);
      if (empty($mac_custom_colors)) {
        $mac_custom_colors = [];
      }
      // Chuyển đổi format từ database để khớp với format frontend
      $current_colors = array();
      foreach ($mac_custom_colors as $color_item) {
        if (isset($color_item['name']) && isset($color_item['color'])) {
          $color_slug = sanitize_title($color_item['name']);
          $current_colors[$color_slug] = $color_item['color'];
        }
      }
    }

    // Lấy các cài đặt font hiện tại
    $current_fonts = isset($_POST['current_fonts']) ? $_POST['current_fonts'] : array();

    // Nếu không có font từ frontend, fallback về cách cũ
    if (empty($current_fonts)) {
      $fonts = array();
      if (isset($_POST['fonts']) && is_array($_POST['fonts'])) {
        $fonts = array(
          'primary' => isset($_POST['fonts'][0]) ? $_POST['fonts'][0] : '',
          'secondary' => isset($_POST['fonts'][1]) ? $_POST['fonts'][1] : ''
        );
      }
    } else {
      $fonts = $current_fonts;
    }

    // Tạo dữ liệu export với màu sắc hiện tại
    $export_data = array(
      'page_id' => $page_id,
      'page_title' => $page->post_title,
      'fonts' => $fonts,
      'current_colors' => $current_colors, // Màu sắc đã thay đổi hiện tại
      'original_colors' => get_post_meta($page_id, 'mac_custom_colors', true), // Màu gốc từ database để tham khảo
      'templates' => $section_ids,
      'export_date' => current_time('mysql'),
      'export_note' => 'Exported with current changed colors from frontend'
    );

    // Thêm dữ liệu từ các addon
    foreach ($this->addons as $addon) {
      if (method_exists($addon, 'get_export_data')) {
        $export_data['addons'][$addon->get_name()] = $addon->get_export_data();
      }
    }

    wp_send_json_success($export_data);
  }

  /**
   * Export site-wide settings with current changed colors and fonts
   */
  public function export_site_settings() {
    check_ajax_referer('elementor_live_color_nonce', 'nonce');

    // Bỏ kiểm tra quyền - cho phép tất cả user sử dụng

    // Lấy màu sắc hiện tại đã thay đổi từ frontend
    $current_colors = isset($_POST['current_colors']) ? $_POST['current_colors'] : array();

    // Lấy các cài đặt font hiện tại
    $current_fonts = isset($_POST['current_fonts']) ? $_POST['current_fonts'] : array();

    // Tạo system_colors từ current_colors
    $system_colors = array();
    $color_mapping = array(
      'primary' => 'Primary',
      'secondary' => 'Secondary',
      'text' => 'Text',
      'accent' => 'Accent'
    );

    foreach ($color_mapping as $color_key => $color_title) {
      $color_data = isset($current_colors[$color_key]) ? $current_colors[$color_key] : null;
      
      // Nếu color_data là array (object từ JavaScript), lấy giá trị color từ bên trong
      if (is_array($color_data) && isset($color_data['color'])) {
        $color_value = $color_data['color'];
      } elseif (is_string($color_data)) {
        // Nếu là string trực tiếp (hex color)
        $color_value = $color_data;
      } else {
        // Fallback
        $color_value = '#000000';
      }
      
      $system_colors[] = array(
        '_id' => $color_key,
        'title' => $color_title,
        'color' => $color_value
      );
    }

    // Tạo custom_colors từ current_colors (loại bỏ system colors)
    $custom_colors = array();
    $custom_color_id = 1;
    $has_transparent = false; // Track if transparent color exists
    
    foreach ($current_colors as $color_key => $color_data) {
      if (!array_key_exists($color_key, $color_mapping)) {
        // Nếu color_data là array có _id và title, giữ nguyên cấu trúc
        if (is_array($color_data) && isset($color_data['_id']) && isset($color_data['title'])) {
          // Luôn lấy giá trị color từ bên trong nếu có
          $color_value = isset($color_data['color']) ? $color_data['color'] : '#000000';
          
          // Kiểm tra nếu đây là màu Transparent (theo _id hoặc title)
          if ($color_data['_id'] === '54f3520' || 
              strtolower($color_data['title']) === 'transparent' ||
              preg_match('/^#[0-9A-Fa-f]{8}$/', $color_value)) { // Màu có 8 ký tự hex (có alpha)
            $has_transparent = true;
            // Đảm bảo màu transparent có đúng 8 ký tự hex
            if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color_value)) {
              $color_value = $color_value . '00'; // Thêm alpha = 0 nếu thiếu
            }
          }
          
          $custom_colors[] = array(
            '_id' => $color_data['_id'],
            'title' => $color_data['title'],
            'color' => $color_value // Giữ nguyên giá trị, không cắt bớt
          );
        } else {
          // Fallback: tạo cấu trúc mới nếu color_data chỉ là string
          $color_value = is_string($color_data) ? $color_data : '#000000';
          
          // Kiểm tra nếu đây là màu có alpha channel
          if (preg_match('/^#[0-9A-Fa-f]{8}$/', $color_value)) {
            $has_transparent = true;
          }
          
          $custom_colors[] = array(
            '_id' => 'custom_' . $custom_color_id,
            'title' => ucfirst($color_key),
            'color' => $color_value // Giữ nguyên giá trị, không cắt bớt
          );
          $custom_color_id++;
        }
      }
    }

    // Thêm một số custom colors mặc định nếu chưa có
    if (empty($custom_colors)) {
      $custom_colors = array(
        array('_id' => '575bd41', 'title' => 'Black', 'color' => '#000000'),
        array('_id' => '041be46', 'title' => 'White', 'color' => '#FFFFFF'),
        array('_id' => '54f3520', 'title' => 'Transparent', 'color' => '#00000000'),
        array('_id' => '2c30e4f', 'title' => 'Background', 'color' => '#FFF4EE'),
        array('_id' => '68c5c02', 'title' => 'Hover', 'color' => '#FF9256'),
        array('_id' => 'cf3521e', 'title' => 'Border', 'color' => '#F5F5F5'),
        array('_id' => 'success', 'title' => 'Success', 'color' => '#28A745'),
        array('_id' => 'warning', 'title' => 'Warning', 'color' => '#FFC107')
      );
    } elseif (!$has_transparent) {
      // Nếu đã có custom_colors nhưng chưa có màu Transparent, thêm vào
      $custom_colors[] = array('_id' => '54f3520', 'title' => 'Transparent', 'color' => '#00000000');
    }

    // Tạo system_typography từ current_fonts
    $system_typography = array();
    $font_mapping = array(
      'primary' => 'Primary',
      'secondary' => 'Secondary',
      'text' => 'Text',
      'accent' => 'Accent'
    );

    foreach ($font_mapping as $font_key => $font_title) {
      // Nếu thiếu 'text', lấy từ 'secondary' thay vì 'Arial'
      if ($font_key === 'text' && !isset($current_fonts[$font_key])) {
        $font_family = isset($current_fonts['secondary']) ? $current_fonts['secondary'] : 'Arial';
      } else {
        $font_family = isset($current_fonts[$font_key]) ? $current_fonts[$font_key] : 'Arial';
      }
      
      $system_typography[] = array(
        '_id' => $font_key,
        'title' => $font_title,
        'typography_typography' => 'custom',
        'typography_font_family' => $font_family,
        'typography_font_weight' => $font_key === 'primary' ? '700' : ($font_key === 'secondary' ? '700' : '400'),
        'typography_font_size' => array(
          'unit' => 'px',
          'size' => $font_key === 'primary' ? 36 : ($font_key === 'secondary' ? 28 : 18),
          'sizes' => array()
        ),
        'typography_font_size_tablet' => array(
          'unit' => 'px',
          'size' => $font_key === 'primary' ? 32 : ($font_key === 'secondary' ? 26 : 17),
          'sizes' => array()
        ),
        'typography_font_size_mobile' => array(
          'unit' => 'px',
          'size' => $font_key === 'primary' ? 28 : ($font_key === 'secondary' ? 24 : 16),
          'sizes' => array()
        )
      );
    }

    // Tạo custom_typography mặc định
    $custom_typography = array(

    );

    // Lấy body font từ current_fonts, nếu không có thì lấy từ secondary, cuối cùng mới dùng mặc định
    if (isset($current_fonts['body'])) {
      $body_font = $current_fonts['body'];
    } elseif (isset($current_fonts['secondary'])) {
      $body_font = $current_fonts['secondary'];
    } else {
      $body_font = 'Archivo';
    }

    // Tạo Elementor Kit JSON structure
    $export_data = array(
      'content' => array(),
      'settings' => array(
        'template' => 'default',
        'viewport_md' => 768,
        'viewport_lg' => 1025,
        'colors_enable_styleguide_preview' => 'yes',
        'system_colors' => $system_colors,
        'custom_colors' => $custom_colors,
        'typography_enable_styleguide_preview' => 'yes',
        'system_typography' => $system_typography,
        'custom_typography' => $custom_typography,
        'default_generic_fonts' => 'Sans-serif',
        'page_title_selector' => 'h1.entry-title',
        'hello_footer_copyright_text' => 'All rights reserved',
        'activeItemIndex' => 1,
        '__globals__' => array(
          'body_typography_typography' => ''
        ),
        'container_width' => array(
          'unit' => 'px',
          'size' => 1300,
          'sizes' => array()
        ),
        'body_typography_font_family' => $body_font,
        'body_typography_font_size' => array(
          'unit' => 'px',
          'size' => 18,
          'sizes' => array()
        ),
        'body_typography_font_size_tablet' => array(
          'unit' => 'px',
          'size' => 17,
          'sizes' => array()
        ),
        'body_typography_font_size_mobile' => array(
          'unit' => 'px',
          'size' => 16,
          'sizes' => array()
        ),
        'body_typography_font_weight' => '400'
      ),
      'metadata' => array(
        'template_id' => '606',
        'template_name' => 'MAC LiveStyle Kit',
        'template_category' => 'Section'
      )
    );

    wp_send_json_success($export_data);
  }

  /**
   * Render edit content tab
   */
  private function render_edit_content_tab() {
	  return;
    // Chỉ render khi đang ở frontend. Cho phép tất cả user (kể cả khách)
    if (is_admin()) {
      return;
    }
    // Bỏ điều kiện kiểm tra quyền - cho phép tất cả user sử dụng
    $post_id = get_the_ID();
    $elementor_data = get_post_meta($post_id, '_elementor_data', true);
    if (!empty($elementor_data)) {
      if (is_string($elementor_data)) {
        $elementor_data = json_decode($elementor_data, true);
      }
      if (is_array($elementor_data)) {
        function mac_livestyle_extract_text_fields_with_path($elements, $path = '', &$results = array(), $parent_id = null, $allow_keywords = []) {
          foreach ($elements as $idx => $el) {
            $current_path = $path . "[$idx]";
            $el_id = isset($el['id']) ? $el['id'] : $parent_id;
            if (isset($el['elements']) && is_array($el['elements'])) {
              mac_livestyle_extract_text_fields_with_path($el['elements'], $current_path . '[elements]', $results, $el_id, $allow_keywords);
            }
            if (isset($el['settings']) && is_array($el['settings'])) {
              // Kiểm tra widgetType để loại bỏ field text của divider
              $widget_type = isset($el['widgetType']) ? $el['widgetType'] : '';
              $is_divider = ($widget_type === 'divider');
              
              foreach ($el['settings'] as $key => $value) {
                // Loại bỏ field 'text' của widget divider
                if ($is_divider && strtolower($key) === 'text') {
                  continue;
                }
                
                if ((is_string($value) && trim($value) !== '') || (is_numeric($value) && $value !== '')) {
                  if (in_array(strtolower($key), $allow_keywords)) {
                    $results[] = array('field' => $key, 'value' => $value, 'path' => $current_path . "[settings][$key]", 'widget_id' => $el_id);
                  }
                }
                // Nếu là mảng, duyệt tiếp vào từng phần tử (có thể là tabs, item_list...)
                if (is_array($value)) {
                  foreach ($value as $sub_idx => $sub_item) {
                    if (is_array($sub_item)) {
                      foreach ($sub_item as $sub_key => $sub_value) {
                        // Cũng áp dụng logic loại bỏ field 'text' của divider cho sub_items
                        if ($is_divider && strtolower($sub_key) === 'text') {
                          continue;
                        }
                        
                        if ((is_string($sub_value) && trim($sub_value) !== '') || (is_numeric($sub_value) && $sub_value !== '')) {
                          if (in_array(strtolower($sub_key), $allow_keywords)) {
                            $results[] = array('field' => $sub_key, 'value' => $sub_value, 'path' => $current_path . "[settings][$key][$sub_idx][$sub_key]", 'widget_id' => $el_id);
                          }
                        }
                      }
                    }
                  }
                }
              }
            }
          }
          return $results;
        }
        $allow_keywords = [
          'title',
          'text',
          'editor',
          'content',
          'first_part',
          'second_part',
          'item_comment',
          'item_name',
          'item_position',
          'tab_title',
          'tab_content',
          'item_title',
          'item_date',
          'item_description',
          'item_subtitle',
          'item_text',
          'item_content',
          'title_text',
          'description_text',
          'number_text',
          'ending_number',
          'suffix'
        ];
        $results = array();
        mac_livestyle_extract_text_fields_with_path($elementor_data, '', $results, null, $allow_keywords);
        $text_fields = $results;
        // Đếm index cho từng giá trị text giống nhau
        $text_value_count = array();
        if (!empty($text_fields)) {
          echo '<div class="field-progress-counter">';
          echo '<span id="field-progress-text">Đã sửa: <span id="checked-count">0</span>/<span id="total-count">0</span> fields</span>';
          echo '</div>';
          echo '<form id="edit-content-fields-form">';
          foreach ($text_fields as $i => $field) {
            $field_name = strtolower($field['field']);
            $is_allowed = in_array($field_name, $allow_keywords);
            if (!$is_allowed) continue;

            $text_val_raw = $field['value'];
            $text_val = wp_strip_all_tags($text_val_raw);

            // Lấy index cha từ path nếu có (ví dụ: [settings][item_list][0][item_comment])
            $parent_index = '';
            if (preg_match('/\\[settings\\]\\[[a-zA-Z0-9_]+\\]\\[([0-9]+)\\]\\[[a-zA-Z0-9_]+\\]/', $field['path'], $matches)) {
              $parent_index = $matches[1];
            }

            $label = esc_html($field['field']);
            if ($parent_index !== '') {
              $label .= ' [' . $parent_index . ']';
            }

            echo '<div class="edit-content-field">';
            echo '<div class="field-header">';
            echo '<input type="checkbox" class="field-status-checkbox" data-field-id="' . esc_attr($field['widget_id'] . '_' . $field['field']) . '">';
            echo '<label>' . $label . '</label>';
            echo '</div>';
            // Tạo base-id từ widget_id (sử dụng hash để tạo số ngắn gọn và nhất quán)
            $base_id = abs(crc32($field['widget_id'])) % 1000 + 1;
            // Tạo field-index dựa trên field name để có ID chính xác
            $field_index = 1;
            if ($field['field'] === 'item_title') $field_index = 1;
            elseif ($field['field'] === 'item_text') $field_index = 2;
            elseif ($field['field'] === 'item_comment') $field_index = 3;
            elseif ($field['field'] === 'title') $field_index = 1;
            elseif ($field['field'] === 'text') $field_index = 2;
            elseif ($field['field'] === 'editor') $field_index = 3;
            else $field_index = 1;
            
            // Tạo field-data-id theo format: widgetId_slideIndex_fieldIndex
            $field_data_id = $field['widget_id'] . '_' . $parent_index . '_' . $field_index;
            
            echo '<textarea class="edit-content-input" data-path="' . esc_attr($field['path']) . '" data-index="' . esc_attr($parent_index) . '" data-widget-id="' . esc_attr($field['widget_id']) . '" data-field="' . esc_attr($field['field']) . '" data-field-index="' . esc_attr($field_index) . '" data-mac-field-data-id="' . esc_attr($field_data_id) . '" data-base-id="' . esc_attr($base_id) . '" rows="3" style="width:100%;margin-bottom:10px;">' . esc_html($text_val) . '</textarea>';
            echo '</div>';
          }
          echo '</form>';
          // Đã chuyển chức năng export JSON sang nút #export-json-page. Ẩn nút cũ.
          // echo '<button id="export-edit-content-json" type="button">Export JSON</button>';
        } else {
          echo '<div>Không tìm thấy nội dung text nào.</div>';
        }
        // Truyền JSON gốc và metadata sang JS để export đúng format import
        $elementor_page_settings = get_post_meta($post_id, '_elementor_page_settings', true);
        if (!is_array($elementor_page_settings)) { $elementor_page_settings = array(); }
        $page_url = get_permalink($post_id);
        $page_title = get_the_title($post_id);
        echo '<script>window.macElementorData = ' . json_encode($elementor_data) . ';</script>';
        echo '<script>window.macElementorPageSettings = ' . json_encode($elementor_page_settings) . ';</script>';
        echo '<script>window.macPageId = ' . json_encode((int)$post_id) . ';</script>';
        echo '<script>window.macPageUrl = ' . json_encode((string)$page_url) . ';</script>';
        echo '<script>window.macPageTitle = ' . json_encode((string)$page_title) . ';</script>';
        // Thêm JS cập nhật giá trị vào JSON gốc dựa trên data-path và highlight vùng HTML
        echo '<script>';
    ?>
        // Hàm annotate slides cho slick slider
        function annotateSlides() {
          // Tìm tất cả slick slider
          document.querySelectorAll('.slick-slider').forEach(function(slider) {
            const slides = slider.querySelectorAll('.slick-slide:not(.slick-cloned)');
            const clonedSlides = slider.querySelectorAll('.slick-slide.slick-cloned');
            
            // Gán base-id cho slide gốc (bắt đầu từ 1)
            slides.forEach(function(slide, index) {
              const baseId = index + 1;
              slide.setAttribute('data-mac-base-id', baseId);
              
              // Tìm widget_id trong slide để map với field
              const widgetId = slide.getAttribute('data-id');
              if (widgetId) {
                // Tạo base-id từ widget_id giống như trong field
                const fieldBaseId = Math.abs(crc32(widgetId)) % 1000 + 1;
                slide.setAttribute('data-mac-field-base-id', fieldBaseId);
              }
              slide.classList.add('mac-original-slide');
              
              // Thêm data attributes để tracking
              slide.setAttribute('data-mac-slide-index', index);
              slide.setAttribute('data-mac-widget-type', getWidgetType(slide));
              
              // Gán data-mac-json-index để map chính xác với JSON array index
              slide.setAttribute('data-mac-json-index', index);
            });
            
            // Gán base-id cho slide clone
            clonedSlides.forEach(function(slide, index) {
              const originalIndex = index % slides.length;
              const baseId = originalIndex + 1;
              slide.setAttribute('data-mac-base-id', baseId);
              
              // Lấy field-base-id từ slide gốc tương ứng
              const originalSlide = slides[originalIndex];
              if (originalSlide) {
                const fieldBaseId = originalSlide.getAttribute('data-mac-field-base-id');
                if (fieldBaseId) {
                  slide.setAttribute('data-mac-field-base-id', fieldBaseId);
                }
              }
              
              slide.classList.add('mac-clone-slide');
              slide.setAttribute('data-mac-clone-index', index);
              slide.setAttribute('data-mac-json-index', originalIndex);
            });
          });
        }
        
        // Hàm xác định loại widget
        function getWidgetType(slide) {
          // Kiểm tra các class để xác định widget type
          if (slide.querySelector('.jet-testimonials__comment')) return 'jet-testimonials';
          if (slide.querySelector('.elementor-image-carousel')) return 'image-carousel';
          if (slide.querySelector('.elementor-icon-list')) return 'icon-list';
          if (slide.querySelector('.elementor-text-editor')) return 'text-editor';
          return 'unknown';
        }
        
        // Hàm gắn data-mac-base-id cho field text dựa trên slide
        function mapFieldsToSlides() {
          const form = document.getElementById('edit-content-fields-form');
          if (!form) return;
          
          const fields = form.querySelectorAll('.edit-content-input');
          fields.forEach(function(field) {
            const widgetId = field.getAttribute('data-widget-id');
            const fieldIndex = field.getAttribute('data-index');
            
            if (widgetId) {
              // Tìm widget có data-id = widgetId
              const widget = document.querySelector('[data-id="' + widgetId + '"]');
              if (widget) {
                // Nếu có fieldIndex, tìm slide con trong carousel
                if (fieldIndex !== '' && fieldIndex !== null) {
                  const slides = widget.querySelectorAll('.slick-slide:not(.slick-cloned)');
                  const slideIndex = parseInt(fieldIndex);
                  if (slides[slideIndex]) {
                    const slide = slides[slideIndex];
                    // Tạo base-id duy nhất cho field này: widgetId + index
                    const fieldBaseId = widgetId + '_' + fieldIndex;
                    field.setAttribute('data-mac-base-id', fieldBaseId);
                    // Cũng gắn base-id cho slide để map
                    slide.setAttribute('data-mac-field-base-id', fieldBaseId);
                    console.log('Mapped carousel field to unique base-id:', fieldBaseId, 'for widget:', widgetId, 'index:', fieldIndex);
                  }
                } else {
                  // Nếu không có fieldIndex, dùng slide gốc
                  const slideBaseId = widget.getAttribute('data-mac-base-id');
                  if (slideBaseId) {
                    field.setAttribute('data-mac-base-id', slideBaseId);
                    console.log('Mapped field to slide base-id:', slideBaseId, 'for widget:', widgetId);
                  }
                }
              }
            }
          });
        }
        
        // Hàm cập nhật live cho slide cụ thể
        function updateSlidesByBaseId(baseId, field, newValue) {
          // Tìm slide có data-mac-field-base-id = baseId (cho carousel)
          let allSlides = document.querySelectorAll('[data-mac-field-base-id="' + baseId + '"]');
          
          // Nếu không tìm thấy, tìm theo data-mac-base-id (cho slick slides thông thường)
          if (allSlides.length === 0) {
            allSlides = document.querySelectorAll('[data-mac-base-id="' + baseId + '"]');
          }
          
          console.log('Found slides with base-id', baseId, ':', allSlides.length);
          
          // Chỉ update slide đầu tiên tìm thấy để tránh update tất cả slide
          if (allSlides.length > 0) {
            allSlides = [allSlides[0]];
            console.log('Updating only first slide to avoid affecting other slides');
          }
          
          allSlides.forEach(function(slide) {
            const widgetType = slide.getAttribute('data-mac-widget-type') || 'unknown';
            const jsonIndex = slide.getAttribute('data-mac-json-index');
            let targetElement = null;
            
            console.log('Processing slide:', {
              baseId: baseId,
              widgetType: widgetType,
              jsonIndex: jsonIndex,
              field: field
            });
            
            // Xử lý theo widget type
            switch (widgetType) {
              case 'jet-testimonials':
                targetElement = getTestimonialElement(slide, field);
                break;
              case 'image-carousel':
                targetElement = getCarouselElement(slide, field);
                break;
              case 'text-editor':
                targetElement = getTextEditorElement(slide, field);
                break;
              default:
                targetElement = getGenericElement(slide, field);
            }
            
            if (targetElement) {
              console.log('Updating element:', targetElement, 'with value:', newValue);
              updateElementContent(targetElement, newValue);
            } else {
              console.log('No target element found for field:', field, 'in slide:', slide);
              // Thử tìm element bằng cách khác
              const alternativeElement = findAlternativeElement(slide, field);
              if (alternativeElement) {
                console.log('Found alternative element:', alternativeElement);
                updateElementContent(alternativeElement, newValue);
              }
            }
          });
        }
        
        // Hàm tìm element thay thế
        function findAlternativeElement(slide, field) {
          // Thử các selector khác nhau
          const selectors = [
            '.' + field,
            '[data-field="' + field + '"]',
            '.elementor-widget-' + field,
            '.jet-testimonials__' + field.replace('item_', ''),
            'h1, h2, h3, h4, h5, h6',
            'p, .text, .content'
          ];
          
          for (let selector of selectors) {
            const element = slide.querySelector(selector);
            if (element) {
              console.log('Found element with selector:', selector);
              return element;
            }
          }
          
          return null;
        }
        
        // Hàm lấy element cho testimonial
        function getTestimonialElement(slide, field) {
          const selectors = {
            'item_comment': '.jet-testimonials__comment',
            'item_name': '.jet-testimonials__name',
            'item_position': '.jet-testimonials__position'
          };
          
          const selector = selectors[field];
          if (selector) {
            const element = slide.querySelector(selector);
            console.log('Looking for testimonial element:', selector, 'found:', element);
            return element;
          }
          
          return null;
        }
        
        // Hàm lấy element cho carousel
        function getCarouselElement(slide, field) {
          const selectors = {
            'title': '.elementor-image-carousel-title',
            'text': '.elementor-image-carousel-description'
          };
          
          const selector = selectors[field];
          if (selector) {
            const element = slide.querySelector(selector);
            console.log('Looking for carousel element:', selector, 'found:', element);
            return element;
          }
          
          return null;
        }
        
        // Hàm lấy element cho text editor
        function getTextEditorElement(slide, field) {
          const element = slide.querySelector('.elementor-widget-text-editor');
          console.log('Looking for text editor element:', '.elementor-widget-text-editor', 'found:', element);
          return element;
        }
        
        // Hàm lấy element generic
        function getGenericElement(slide, field) {
          const selectors = {
            'title': 'h1, h2, h3, h4, h5, h6, .title, .heading, .jet-carousel__item-title, .jet-testimonials__title',
            'text': 'p, .text, .content, .description, .jet-carousel__item-text, .jet-testimonials__comment',
            'item_title': '.jet-carousel__item-title, .jet-testimonials__title, .elementor-heading-title, h1, h2, h3, h4, h5, h6',
            'item_text': '.jet-carousel__item-text, .jet-testimonials__comment, .elementor-widget-text-editor, p',
            'item_comment': '.jet-testimonials__comment, .comment, p',
            'editor': '.elementor-widget-text-editor, .text-editor',
            'content': '.content, .description, .text-content'
          };
          
          if (selectors[field]) {
            const selectorList = selectors[field].split(', ');
            for (let selector of selectorList) {
              const element = slide.querySelector(selector);
              if (element) {
                console.log('Found generic element with selector:', selector);
                return element;
              }
            }
          }
          
          const fallbackElement = slide.querySelector('.' + field);
          console.log('Looking for generic element:', '.' + field, 'found:', fallbackElement);
          return fallbackElement;
        }
        
        // Hàm cập nhật nội dung element
        function updateElementContent(element, newValue) {
          if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
            element.value = newValue;
            console.log('Updated input/textarea value:', newValue);
          } else {
            element.textContent = newValue;
            console.log('Updated text content:', newValue);
          }
        }
        
        // Hàm pause/play slick slider
        function pauseSlickSliders() {
          document.querySelectorAll('.slick-slider').forEach(function(slider) {
            if (typeof $(slider).slickPause === 'function') {
              $(slider).slickPause();
            }
          });
        }
        
        function playSlickSliders() {
          document.querySelectorAll('.slick-slider').forEach(function(slider) {
            if (typeof $(slider).slickPlay === 'function') {
              $(slider).slickPlay();
            }
          });
        }
        
        // MutationObserver để re-annotate khi DOM thay đổi
        const observer = new MutationObserver(function(mutations) {
          mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
              // Re-annotate sau khi DOM thay đổi
              setTimeout(function() {
                annotateSlides();
                mapFieldsToSlides();
              }, 100);
            }
          });
        });
        
        // Bắt đầu observe
        observer.observe(document.body, {
          childList: true,
          subtree: true
        });
        
        // Hàm debug mapping
        function debugMapping() {
          console.log('=== DEBUG MAPPING ===');
          document.querySelectorAll('[data-mac-base-id]').forEach(function(slide) {
            const baseId = slide.getAttribute('data-mac-base-id');
            const jsonIndex = slide.getAttribute('data-mac-json-index');
            const widgetType = slide.getAttribute('data-mac-widget-type');
            const isClone = slide.classList.contains('mac-clone-slide');
            
            console.log('Slide:', {
              baseId: baseId,
              jsonIndex: jsonIndex,
              widgetType: widgetType,
              isClone: isClone,
              element: slide
            });
          });
        }
        
        // Annotate ban đầu
        document.addEventListener('DOMContentLoaded', function() {
          setTimeout(function() {
            annotateSlides();
            mapFieldsToSlides();
            debugMapping();
          }, 500);
        });
        
        // Annotate khi slick init
        if (typeof $ !== 'undefined') {
          $(document).on('init.slick', function() {
            setTimeout(annotateSlides, 100);
          });
        }
        
        document.querySelectorAll(".edit-content-input").forEach(function(textarea) {
        textarea.addEventListener("blur", function() {
        var path = this.getAttribute("data-path");
        var value = this.value;
        var baseId = this.getAttribute("data-index");
        var field = this.getAttribute("data-field");
        
        // Cập nhật JSON
        setValueByPath(window.macElementorData, path, value);
        
        // Cập nhật live HTML cho tất cả slide có cùng base-id
        if (baseId && field) {
          console.log('Updating slides with base-id:', baseId, 'field:', field, 'value:', value);
          console.log('Path:', path, 'Base ID:', baseId);
          updateSlidesByBaseId(baseId, field, value);
        }
        
        // Resume slick slider
        playSlickSliders();
        });
        
        textarea.addEventListener("focus", function() {
        // Pause slick slider khi edit
        pauseSlickSliders();
        
        var path = this.getAttribute("data-path");
        var match = path.match(/\[tabs\]\[(\d+)\]\[tab_content\]/);
        var htmlId = null;
        if (match) {
        var tabIndex = parseInt(match[1], 10);
        htmlId = "elementor-tab-content-" + tabIndex;
        }
        // Xóa highlight cũ
        document.querySelectorAll(".mac-highlight-html").forEach(function(el) {
        el.classList.remove("mac-highlight-html");
        });
        if (htmlId) {
        var htmlEl = document.getElementById(htmlId);
        if (htmlEl) {
        var parent = htmlEl.closest(".elementor-toggle-item");
        if (parent) {
        parent.classList.add("mac-highlight-html");
        setTimeout(function() {
        parent.classList.remove("mac-highlight-html");
        }, 1500);
        } else {
        htmlEl.classList.add("mac-highlight-html");
        setTimeout(function() {
        htmlEl.classList.remove("mac-highlight-html");
        }, 1500);
        }
        }
        }
        });
        });
        function setValueByPath(obj, path, value) {
        const keys = path.match(/\[([^\]]+)\]/g).map(function(k) {
        var key = k.replace(/\[|\]/g, "");
        return isNaN(key) ? key : parseInt(key, 10);
        });
        var ref = obj;
        for (var i = 0; i < keys.length - 1; i++) {
          ref=ref[keys[i]];
          }
          ref[keys[keys.length - 1]]=value;
          }
          function isEditContentTabActive() {
          var tab=document.getElementById("edit-content-tab");
          return tab && tab.classList.contains("active");
          }
          // Đã vô hiệu hoá click-to-focus field để tránh map sai khi có slider/clone
          <?php
          echo '</script>';
          echo '<style>
        .mac-highlight-html { 
          outline: 2px solid #f26212 !important; 
          background: #fffbe6 !important; 
          transition: outline 0.2s; 
        }
        .mac-highlight { 
          background: #fffbe6 !important; 
          border: 2px solid #f26212 !important; 
          transition: all 0.2s; 
        }
        /* Hover highlight removed as requested */
        </style>';
        } else {
          echo '<div>Dữ liệu Elementor không hợp lệ.</div>';
        }
      } else {
        echo '<div>Không có dữ liệu Elementor cho trang này.</div>';
      }
    }
  }

  // Initialize plugin
  new ElementorLiveColorChanger();
