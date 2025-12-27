<?php
/**
 * Settings Page Class
 * 
 * Handles settings page rendering and saving
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAC_Animation_Settings {
    
    private $manager;
    private $discovery;
    
    public function __construct($manager, $discovery) {
        $this->manager = $manager;
        $this->discovery = $discovery;
        
        add_action('admin_init', array($this, 'handle_save'));
        add_action('wp_ajax_mac_seasonal_effects_get_styles', array($this, 'ajax_get_styles'));
        add_action('wp_ajax_mac_seasonal_effects_get_customizations', array($this, 'ajax_get_customizations'));
        add_action('wp_ajax_mac_seasonal_effects_get_customization_html', array($this, 'ajax_get_customization_html'));
        add_action('wp_ajax_mac_seasonal_effects_preview', array($this, 'ajax_preview'));
    }
    
    /**
     * Handle form save (via admin_init hook - backup method)
     */
    public function handle_save() {
        // Only process on our settings page
        if (!isset($_GET['page']) || $_GET['page'] !== 'mac-seasonal-effects') {
            return;
        }
        
        // Check if form was submitted
        if (!isset($_POST['mac_seasonal_effects_save'])) {
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['mac_seasonal_effects_nonce']) || !wp_verify_nonce($_POST['mac_seasonal_effects_nonce'], 'mac_seasonal_effects_save')) {
            wp_die(__('Security check failed. Please try again.', 'mac-seasonal-effects'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.'));
        }
        
        $this->process_save();
    }
    
    /**
     * Process save (extracted to be called from multiple places)
     * @param bool $redirect Whether to redirect after save
     * @return bool Success status
     */
    private function process_save($redirect = true) {
        $settings = $this->manager->get_settings();
        
        // Basic settings
        $settings['enabled'] = isset($_POST['enabled']) ? true : false;
        
        // Get category and style from POST
        $category = isset($_POST['category']) ? trim(sanitize_text_field($_POST['category'])) : '';
        $style = isset($_POST['style']) ? trim(sanitize_text_field($_POST['style'])) : '';
        
        // Only save if not empty
        $settings['category'] = !empty($category) ? $category : null;
        $settings['style'] = !empty($style) ? $style : null;
        
        $settings['start_date'] = isset($_POST['start_date']) && !empty($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : date('Y-m-d');
        $settings['end_date'] = isset($_POST['end_date']) && !empty(trim($_POST['end_date'])) ? sanitize_text_field($_POST['end_date']) : null;
        
        // Only clear category/style if both are empty (user wants to clear)
        // Don't clear just because disabled - allow saving for later enable
        if (empty($settings['category']) && empty($settings['style'])) {
            $settings['category'] = null;
            $settings['style'] = null;
        }
        
        // Handle customizations
        if (!empty($settings['category']) && !empty($settings['style'])) {
            $custom_key = $settings['category'] . '-' . $settings['style'];
            $config = $this->discovery->get_config($settings['category'], $settings['style']);
            
            if ($config && isset($config['customizable'])) {
                $customizations = array();
                
                // Handle images
                if (isset($config['customizable']['images'])) {
                    $customizations['images'] = array();
                    foreach ($config['customizable']['images'] as $key => $image_config) {
                        if (isset($_POST['custom_image_' . $key])) {
                            $url = trim(esc_url_raw($_POST['custom_image_' . $key]));
                            
                            // Always save if not empty (user uploaded a custom image)
                            // Empty means use default from config
                            if (!empty($url)) {
                                $customizations['images'][$key] = $url;
                            } else {
                                // If empty, don't save (will use default from config)
                                // Remove from customizations if it was previously set
                                if (isset($customizations['images'][$key])) {
                                    unset($customizations['images'][$key]);
                                }
                            }
                        }
                    }
                }
                
                // Handle settings (numbers, text, checkbox)
                if (isset($config['customizable']['settings'])) {
                    $customizations['settings'] = array();
                    foreach ($config['customizable']['settings'] as $key => $setting_config) {
                        if ($setting_config['type'] === 'checkbox') {
                            // Checkbox: checked = true, unchecked = false
                            $customizations['settings'][$key] = isset($_POST['custom_setting_' . $key]) ? true : false;
                        } elseif (isset($_POST['custom_setting_' . $key])) {
                            $value = sanitize_text_field($_POST['custom_setting_' . $key]);
                            if ($setting_config['type'] === 'number') {
                                $value = intval($value);
                                if (isset($setting_config['min'])) {
                                    $value = max($value, $setting_config['min']);
                                }
                                if (isset($setting_config['max'])) {
                                    $value = min($value, $setting_config['max']);
                                }
                            }
                            $customizations['settings'][$key] = $value;
                        }
                    }
                }
                
                // Handle colors
                if (isset($config['customizable']['colors'])) {
                    $customizations['colors'] = array();
                    foreach ($config['customizable']['colors'] as $key => $color_config) {
                        if (isset($_POST['custom_color_' . $key])) {
                            $color = sanitize_hex_color($_POST['custom_color_' . $key]);
                            if ($color) {
                                $customizations['colors'][$key] = $color;
                            }
                        }
                    }
                }
                
                if (!empty($customizations)) {
                    $settings['customizations'][$custom_key] = $customizations;
                } else {
                    // If no customizations, remove the key to use defaults
                    if (isset($settings['customizations'][$custom_key])) {
                        unset($settings['customizations'][$custom_key]);
                    }
                }
            }
        }
        
        $result = $this->manager->save_settings($settings);
        
        if (is_wp_error($result)) {
            $error_message = $result->get_error_message();
            add_settings_error('mac_seasonal_effects', 'settings_error', $error_message, 'error');
            return false;
        } elseif ($result === true) {
            if ($redirect && !headers_sent()) {
                // Redirect to prevent duplicate submission
                wp_redirect(add_query_arg(array(
                    'page' => 'mac-seasonal-effects',
                    'settings-updated' => 'true'
                ), admin_url('admin.php')));
                exit;
            }
            return true;
        } else {
            // This should not happen
            add_settings_error('mac_seasonal_effects', 'settings_error', __('Error saving settings.', 'mac-seasonal-effects'), 'error');
            return false;
        }
    }
    
    /**
     * AJAX: Get styles for category
     */
    public function ajax_get_styles() {
        // Check nonce
        if (!isset($_POST['nonce'])) {
            wp_send_json_error(array('message' => 'Nonce not provided'));
        }
        
        $nonce_check = wp_verify_nonce($_POST['nonce'], 'mac_seasonal_effects_nonce');
        if (!$nonce_check) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
        
        if (empty($category)) {
            wp_send_json_error(array('message' => 'Category is required'));
        }
        
        $styles = $this->discovery->get_styles($category);
        
        wp_send_json_success(array('styles' => $styles));
    }
    
    /**
     * AJAX: Get customizations for category and style
     */
    public function ajax_get_customizations() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mac_seasonal_effects_nonce')) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
        $style = isset($_POST['style']) ? sanitize_text_field($_POST['style']) : '';
        
        if (empty($category) || empty($style)) {
            wp_send_json_error(array('message' => 'Category and style are required'));
        }
        
        // Get settings
        $settings = $this->manager->get_settings();
        $custom_key = $category . '-' . $style;
        $customizations = isset($settings['customizations'][$custom_key]) 
            ? $settings['customizations'][$custom_key] 
            : array();
        
        // Get config for defaults
        $config = $this->discovery->get_config($category, $style);
        
        // Merge with defaults from config
        $result = array(
            'customizations' => $customizations,
            'config' => $config
        );
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Get customization section HTML
     */
    public function ajax_get_customization_html() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mac_seasonal_effects_nonce')) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
        $style = isset($_POST['style']) ? sanitize_text_field($_POST['style']) : '';
        
        if (empty($category) || empty($style)) {
            wp_send_json_error(array('message' => 'Category and style are required'));
        }
        
        // Get config
        $config = $this->discovery->get_config($category, $style);
        
        if (!$config || !isset($config['customizable'])) {
            wp_send_json_success(array('html' => ''));
        }
        
        // Get settings and customizations
        $settings = $this->manager->get_settings();
        $custom_key = $category . '-' . $style;
        $customizations = isset($settings['customizations'][$custom_key]) 
            ? $settings['customizations'][$custom_key] 
            : array();
        
        // Render customization section HTML
        ob_start();
        $this->render_customization_section($config, $customizations);
        $html = ob_get_clean();
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * Render customization section (extracted for reuse)
     */
    private function render_customization_section($config, $customizations) {
        ?>
        <?php if (isset($config['customizable']['images'])): ?>
            <h3><?php _e('Images', 'mac-seasonal-effects'); ?></h3>
            <table class="form-table">
                <?php foreach ($config['customizable']['images'] as $key => $image_config): ?>
                    <?php
                    // Get current value: custom value if exists, otherwise empty (will use default from config)
                    $has_custom = isset($customizations['images'][$key]);
                    $current_value = $has_custom ? $customizations['images'][$key] : '';
                    
                    // Convert default URL to full URL for preview
                    $default_url = $image_config['default'];
                    if (!empty($default_url) && !preg_match('/^https?:\/\//', $default_url) && !preg_match('/^\/wp-content\//', $default_url)) {
                        // Relative path - convert to full URL
                        $default_url = MAC_SEASONAL_EFFECTS_PLUGIN_URL . ltrim($default_url, '/');
                    } elseif (!empty($default_url) && preg_match('/^\/wp-content\//', $default_url)) {
                        // Absolute path from WordPress root
                        $default_url = home_url($default_url);
                    }
                    
                    $display_value = $has_custom ? $customizations['images'][$key] : $default_url;
                    $is_using_default = !$has_custom;
                    ?>
                    <tr>
                        <th scope="row">
                            <label for="custom_image_<?php echo esc_attr($key); ?>"><?php echo esc_html($image_config['label']); ?></label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="custom_image_<?php echo esc_attr($key); ?>" 
                                   name="custom_image_<?php echo esc_attr($key); ?>" 
                                   value="<?php echo esc_url($current_value); ?>" 
                                   placeholder="<?php echo esc_attr($image_config['default']); ?>"
                                   class="regular-text">
                            <?php if ($is_using_default): ?>
                                <span class="description" style="color: #666; margin-left: 5px;">(<?php _e('Using default', 'mac-seasonal-effects'); ?>)</span>
                            <?php endif; ?>
                            <button type="button" class="button mac-upload-image" data-target="custom_image_<?php echo esc_attr($key); ?>">
                                <?php _e('Upload', 'mac-seasonal-effects'); ?>
                            </button>
                            <button type="button" class="button mac-use-default-image" 
                                    data-target="custom_image_<?php echo esc_attr($key); ?>" 
                                    data-default="<?php echo esc_attr($default_url); ?>">
                                <?php _e('Use Default', 'mac-seasonal-effects'); ?>
                            </button>
                            <?php if ($display_value): ?>
                                <img src="<?php echo esc_url($display_value); ?>" style="max-width: 100px; height: auto; margin-left: 10px; vertical-align: middle;" class="image-preview">
                            <?php endif; ?>
                            <p class="description">
                                <?php echo esc_html($image_config['label']); ?> (<?php echo esc_html(implode(', ', $image_config['accept'])); ?>)
                                <br>
                                <small><?php _e('Default:', 'mac-seasonal-effects'); ?> <?php echo esc_html($image_config['default']); ?></small>
                            </p>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
        
        <?php if (isset($config['customizable']['settings'])): ?>
            <h3><?php _e('Settings', 'mac-seasonal-effects'); ?></h3>
            <table class="form-table">
                <?php foreach ($config['customizable']['settings'] as $key => $setting_config): ?>
                    <?php
                    $current_value = isset($customizations['settings'][$key]) 
                        ? $customizations['settings'][$key] 
                        : $setting_config['default'];
                    ?>
                    <tr>
                        <th scope="row">
                            <label for="custom_setting_<?php echo esc_attr($key); ?>"><?php echo esc_html($setting_config['label']); ?></label>
                        </th>
                        <td>
                            <?php if ($setting_config['type'] === 'number'): ?>
                                <input type="number" 
                                       id="custom_setting_<?php echo esc_attr($key); ?>" 
                                       name="custom_setting_<?php echo esc_attr($key); ?>" 
                                       value="<?php echo esc_attr($current_value); ?>"
                                       min="<?php echo isset($setting_config['min']) ? esc_attr($setting_config['min']) : ''; ?>"
                                       max="<?php echo isset($setting_config['max']) ? esc_attr($setting_config['max']) : ''; ?>"
                                       class="small-text">
                            <?php elseif ($setting_config['type'] === 'checkbox'): ?>
                                <input type="checkbox" 
                                       id="custom_setting_<?php echo esc_attr($key); ?>" 
                                       name="custom_setting_<?php echo esc_attr($key); ?>" 
                                       value="1"
                                       <?php checked($current_value, true, true); ?>>
                                <label for="custom_setting_<?php echo esc_attr($key); ?>"><?php echo esc_html($setting_config['label']); ?></label>
                            <?php else: ?>
                                <input type="text" 
                                       id="custom_setting_<?php echo esc_attr($key); ?>" 
                                       name="custom_setting_<?php echo esc_attr($key); ?>" 
                                       value="<?php echo esc_attr($current_value); ?>"
                                       class="regular-text">
                            <?php endif; ?>
                            <?php if ($setting_config['type'] !== 'checkbox'): ?>
                                <p class="description">
                                    Default: <?php echo esc_html($setting_config['default']); ?>
                                    <?php 
                                    // Add Emojipedia link for emoji fields
                                    if (strpos(strtolower($key), 'emoji') !== false || strpos(strtolower($setting_config['label']), 'emoji') !== false): 
                                    ?>
                                        | <a href="https://emojipedia.org/" target="_blank" rel="noopener noreferrer"><?php _e('Get emojis from Emojipedia', 'mac-seasonal-effects'); ?></a>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
        
        <?php if (isset($config['customizable']['colors'])): ?>
            <h3><?php _e('Colors', 'mac-seasonal-effects'); ?></h3>
            <table class="form-table">
                <?php foreach ($config['customizable']['colors'] as $key => $color_config): ?>
                    <?php
                    $current_value = isset($customizations['colors'][$key]) 
                        ? $customizations['colors'][$key] 
                        : $color_config['default'];
                    ?>
                    <tr>
                        <th scope="row">
                            <label for="custom_color_<?php echo esc_attr($key); ?>"><?php echo esc_html($color_config['label']); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="custom_color_<?php echo esc_attr($key); ?>" 
                                   name="custom_color_<?php echo esc_attr($key); ?>" 
                                   value="<?php echo esc_attr($current_value); ?>"
                                   class="mac-color-picker"
                                   data-default-color="<?php echo esc_attr($color_config['default']); ?>">
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
        <?php
    }
    
    /**
     * AJAX: Preview animation
     */
    public function ajax_preview() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mac_seasonal_effects_nonce')) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
        $style = isset($_POST['style']) ? sanitize_text_field($_POST['style']) : '';
        
        if (empty($category) || empty($style)) {
            wp_send_json_error(array('message' => 'Invalid parameters'));
        }
        
        $preview_url = home_url('?animation_preview=' . $category . '-' . $style);
        wp_send_json_success(array('url' => $preview_url));
    }
    
    /**
     * Render settings page
     */
    public function render() {
        // Handle save if form was submitted (process before rendering)
        $save_success = false;
        if (isset($_POST['mac_seasonal_effects_save']) && check_admin_referer('mac_seasonal_effects_save', 'mac_seasonal_effects_nonce')) {
            if (current_user_can('manage_options')) {
                $save_success = $this->process_save(false); // false = don't redirect, just return result
            }
        }
        
        // Always reload settings from database after save to ensure we have latest values
        $settings = $this->manager->get_settings();
        
        // Check if category/style are passed via URL (for reload after style change)
        if (isset($_GET['category']) && isset($_GET['style'])) {
            $settings['category'] = sanitize_text_field($_GET['category']);
            $settings['style'] = sanitize_text_field($_GET['style']);
        }
        
        // Show success message if saved
        if ($save_success) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully.', 'mac-seasonal-effects') . '</p></div>';
        }
        $categories = $this->discovery->get_categories();
        $styles = !empty($settings['category']) ? $this->discovery->get_styles($settings['category']) : array();
        $config = !empty($settings['category']) && !empty($settings['style']) 
            ? $this->discovery->get_config($settings['category'], $settings['style']) 
            : null;
        
        // Get current customizations
        $custom_key = !empty($settings['category']) && !empty($settings['style']) 
            ? $settings['category'] . '-' . $settings['style'] 
            : '';
        $customizations = isset($settings['customizations'][$custom_key]) 
            ? $settings['customizations'][$custom_key] 
            : array();
        
        ?>
        <div class="wrap mac-seasonal-effects-settings">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php 
            // Show success message if redirected after save
            if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully.', 'mac-seasonal-effects') . '</p></div>';
            }
            settings_errors('mac_seasonal_effects'); 
            ?>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=mac-seasonal-effects')); ?>">
                <?php wp_nonce_field('mac_seasonal_effects_save', 'mac_seasonal_effects_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="enabled"><?php _e('Enable Seasonal Effect', 'mac-seasonal-effects'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="enabled" name="enabled" value="1" <?php checked($settings['enabled'], true); ?>>
                            <p class="description"><?php _e('Enable seasonal effect on your website.', 'mac-seasonal-effects'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="category"><?php _e('Category', 'mac-seasonal-effects'); ?></label>
                        </th>
                        <td>
                            <select id="category" name="category">
                                <option value=""><?php _e('-- Select Category --', 'mac-seasonal-effects'); ?></option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo esc_attr($cat); ?>" <?php selected($settings['category'], $cat, true); ?>>
                                        <?php echo esc_html(ucfirst($cat)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr id="style-row" style="<?php echo empty($settings['category']) ? 'display:none;' : ''; ?>">
                        <th scope="row">
                            <label for="style"><?php _e('Style', 'mac-seasonal-effects'); ?></label>
                        </th>
                        <td>
                            <select id="style" name="style">
                                <option value=""><?php _e('-- Select Style --', 'mac-seasonal-effects'); ?></option>
                                <?php foreach ($styles as $style_key => $style_data): ?>
                                    <option value="<?php echo esc_attr($style_key); ?>" <?php selected($settings['style'], $style_key, true); ?>>
                                        <?php echo esc_html($style_data['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="start_date"><?php _e('Start Date', 'mac-seasonal-effects'); ?></label>
                        </th>
                        <td>
                            <input type="date" id="start_date" name="start_date" value="<?php echo esc_attr($settings['start_date']); ?>" required>
                            <p class="description"><?php _e('Seasonal effect will start on this date.', 'mac-seasonal-effects'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="end_date"><?php _e('End Date', 'mac-seasonal-effects'); ?></label>
                        </th>
                        <td>
                            <input type="date" id="end_date" name="end_date" value="<?php echo esc_attr($settings['end_date']); ?>">
                            <p class="description"><?php _e('Seasonal effect will automatically stop on this date. Leave empty to run until manually disabled.', 'mac-seasonal-effects'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <div class="mac-customization-section" style="<?php echo (!$config || !isset($config['customizable'])) ? 'display:none;' : ''; ?>">
                <?php if ($config && isset($config['customizable'])): ?>
                    <h2><?php _e('Customization', 'mac-seasonal-effects'); ?></h2>
                    <?php $this->render_customization_section($config, $customizations); ?>
                <?php endif; ?>
                </div>
                
                
                <p class="submit">
                    <button type="button" id="preview-btn" class="button" style="margin-right: 10px;">
                        <?php _e('Preview', 'mac-seasonal-effects'); ?>
                    </button>
                    <input type="submit" name="mac_seasonal_effects_save" class="button button-primary" value="<?php _e('Save Changes', 'mac-seasonal-effects'); ?>">
                </p>
            </form>
        </div>
        <?php
    }
}

