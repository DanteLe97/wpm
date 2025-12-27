<?php

/**
 * Addon: Lưu màu sắc
 */

if (!defined('ABSPATH')) {
  exit;
}

class ElementorLiveColor_Save_Colors
{
  private $parent;
  private $option_name = 'elementor_live_colors';
  private $addon_option_name = 'elementor_live_colors_save_enabled';

  public function __construct($parent)
  {
    $this->parent = $parent;
    $this->init();
  }

  public function init()
  {
    // AJAX handlers
    add_action('wp_ajax_save_live_colors', array($this, 'save_colors'));
    add_action('wp_ajax_nopriv_save_live_colors', array($this, 'save_colors'));

    // Register settings
    add_action('admin_init', array($this, 'register_settings'));
  }

  public function get_name()
  {
    return 'save-colors';
  }

  public function get_script_data()
  {
    return array(
      'enabled' => $this->is_enabled()
    );
  }

  public function is_enabled()
  {
    return get_option($this->addon_option_name, 'yes') === 'yes';
  }

  public function register_settings()
  {
    register_setting(
      'elementor_live_colors_group',
      $this->addon_option_name,
      array(
        'type' => 'string',
        'default' => 'yes',
        'sanitize_callback' => 'sanitize_text_field'
      )
    );
  }

  public function render_settings()
  {
?>
    <tr>
      <th scope="row"><?php _e('Lưu màu sắc', 'elementor-live-color'); ?></th>
      <td>
        <label>
          <input type="checkbox"
            name="<?php echo esc_attr($this->addon_option_name); ?>"
            value="yes"
            <?php checked($this->is_enabled()); ?>>
          <?php _e('Bật chức năng lưu màu sắc', 'elementor-live-color'); ?>
        </label>
        <p class="description">
          <?php _e('Khi bật, người dùng có thể lưu và đặt lại màu sắc đã chọn.', 'elementor-live-color'); ?>
        </p>
      </td>
    </tr>
  <?php
  }

  public function render_controls()
  {
    if (!$this->is_enabled()) {
      return;
    }
  ?>
    <button id="reset-colors" class="reset-btn"><?php _e('Đặt lại', 'elementor-live-color'); ?></button>
    <script>
      jQuery(document).ready(function($) {
        // Xử lý đặt lại màu
        $('#reset-colors').on('click', function() {
          const defaultColors = {
            primary: '#007cba',
            secondary: '#6c757d',
            text: '#333333',
            accent: '#28a745'
          };

          $('#primary-color').val(defaultColors.primary);
          $('#secondary-color').val(defaultColors.secondary);
          $('#text-color').val(defaultColors.text);
          $('#accent-color').val(defaultColors.accent);

          // Cập nhật preview
          $('.color-control input[type="color"]').each(function() {
            const preview = $(this).siblings('.color-preview');
            preview.css('background-color', $(this).val());
          });

          // Cập nhật website
          if (typeof updateWebsiteColors === 'function') {
            updateWebsiteColors(defaultColors);
          }
        });
      });
    </script>
<?php
  }

  public function save_colors()
  {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'elementor_live_color_nonce')) {
      wp_die('Security check failed');
    }

    // Check permissions
    if (!current_user_can('edit_theme_options')) {
      wp_die('Insufficient permissions');
    }

    // Sanitize and save colors
    $colors = array(
      'primary' => sanitize_hex_color($_POST['primary']),
      'secondary' => sanitize_hex_color($_POST['secondary']),
      'text' => sanitize_hex_color($_POST['text']),
      'accent' => sanitize_hex_color($_POST['accent'])
    );

    update_option($this->option_name, $colors);

    wp_send_json_success(array(
      'message' => __('Màu sắc đã được lưu thành công!', 'elementor-live-color'),
      'colors' => $colors
    ));
  }
}
