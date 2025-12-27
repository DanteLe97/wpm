<?php
/**
 * MAC Interactive Tutorials - Implementation Example
 * 
 * File này minh họa cách implement các thành phần chính
 */

// ============================================
// 1. REGISTER CUSTOM POST TYPE
// ============================================

class MAC_Tutorial_Post_Type {
    
    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
    }
    
    public function register_post_type() {
        $args = array(
            'public'             => false, // Không hiển thị public
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'menu_icon'          => 'dashicons-welcome-learn-more',
            'menu_position'      => 25,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'supports'           => array('title', 'editor', 'thumbnail', 'excerpt'),
            'labels'             => array(
                'name'               => __('Interactive Tutorials', 'mac-tutorials'),
                'singular_name'      => __('Tutorial', 'mac-tutorials'),
                'add_new'            => __('Add New Tutorial', 'mac-tutorials'),
                'add_new_item'       => __('Add New Tutorial', 'mac-tutorials'),
                'edit_item'          => __('Edit Tutorial', 'mac-tutorials'),
                'new_item'           => __('New Tutorial', 'mac-tutorials'),
                'view_item'          => __('View Tutorial', 'mac-tutorials'),
                'search_items'       => __('Search Tutorials', 'mac-tutorials'),
                'not_found'          => __('No tutorials found', 'mac-tutorials'),
                'not_found_in_trash' => __('No tutorials found in Trash', 'mac-tutorials'),
            ),
        );
        
        register_post_type('mac_tutorial', $args);
    }
}

// ============================================
// 2. META BOXES CHO STEPS
// ============================================

class MAC_Tutorial_Meta_Boxes {
    
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_steps'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    public function add_meta_boxes() {
        add_meta_box(
            'mac_tutorial_steps',
            __('Tutorial Steps', 'mac-tutorials'),
            array($this, 'render_steps_meta_box'),
            'mac_tutorial',
            'normal',
            'high'
        );
        
        add_meta_box(
            'mac_tutorial_settings',
            __('Tutorial Settings', 'mac-tutorials'),
            array($this, 'render_settings_meta_box'),
            'mac_tutorial',
            'side',
            'default'
        );
    }
    
    public function render_steps_meta_box($post) {
        wp_nonce_field('mac_tutorial_steps_nonce', 'mac_tutorial_steps_nonce');
        
        $steps = get_post_meta($post->ID, '_mac_tutorial_steps', true);
        $steps = !empty($steps) && is_array($steps) ? $steps : array();
        
        ?>
        <div id="mac-tutorial-steps-builder">
            <div class="mac-steps-list">
                <?php foreach ($steps as $index => $step): ?>
                    <div class="mac-step-item" data-index="<?php echo $index; ?>">
                        <div class="mac-step-header">
                            <span class="mac-step-number"><?php echo $index + 1; ?></span>
                            <input type="text" 
                                   name="mac_steps[<?php echo $index; ?>][title]" 
                                   value="<?php echo esc_attr($step['title']); ?>"
                                   placeholder="<?php esc_attr_e('Step Title', 'mac-tutorials'); ?>"
                                   class="mac-step-title">
                            <button type="button" class="button mac-remove-step"><?php _e('Remove', 'mac-tutorials'); ?></button>
                        </div>
                        <div class="mac-step-content">
                            <textarea name="mac_steps[<?php echo $index; ?>][description]" 
                                      rows="3"
                                      placeholder="<?php esc_attr_e('Step Description', 'mac-tutorials'); ?>"><?php echo esc_textarea($step['description']); ?></textarea>
                            
                            <label>
                                <?php _e('Target URL:', 'mac-tutorials'); ?>
                                <input type="url" 
                                       name="mac_steps[<?php echo $index; ?>][target_url]" 
                                       value="<?php echo esc_url($step['target_url']); ?>"
                                       placeholder="admin.php?page=..."
                                       class="regular-text">
                            </label>
                            
                            <label>
                                <?php _e('Element Selector (optional):', 'mac-tutorials'); ?>
                                <input type="text" 
                                       name="mac_steps[<?php echo $index; ?>][target_selector]" 
                                       value="<?php echo esc_attr($step['target_selector']); ?>"
                                       placeholder="#element-id or .class-name"
                                       class="regular-text">
                            </label>
                            
                            <div class="mac-step-time">
                                <label>
                                    <?php _e('Min Time (minutes):', 'mac-tutorials'); ?>
                                    <input type="number" 
                                           name="mac_steps[<?php echo $index; ?>][min_time]" 
                                           value="<?php echo esc_attr($step['min_time'] ?? 1); ?>"
                                           min="1" max="60">
                                </label>
                                <label>
                                    <?php _e('Max Time (minutes):', 'mac-tutorials'); ?>
                                    <input type="number" 
                                           name="mac_steps[<?php echo $index; ?>][max_time]" 
                                           value="<?php echo esc_attr($step['max_time'] ?? 5); ?>"
                                           min="1" max="60">
                                </label>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <button type="button" class="button button-primary mac-add-step">
                <?php _e('+ Add Step', 'mac-tutorials'); ?>
            </button>
        </div>
        
        <script type="text/template" id="mac-step-template">
            <div class="mac-step-item" data-index="{{index}}">
                <div class="mac-step-header">
                    <span class="mac-step-number">{{number}}</span>
                    <input type="text" 
                           name="mac_steps[{{index}}][title]" 
                           placeholder="<?php esc_attr_e('Step Title', 'mac-tutorials'); ?>"
                           class="mac-step-title">
                    <button type="button" class="button mac-remove-step"><?php _e('Remove', 'mac-tutorials'); ?></button>
                </div>
                <div class="mac-step-content">
                    <textarea name="mac_steps[{{index}}][description]" 
                              rows="3"
                              placeholder="<?php esc_attr_e('Step Description', 'mac-tutorials'); ?>"></textarea>
                    <label>
                        <?php _e('Target URL:', 'mac-tutorials'); ?>
                        <input type="url" 
                               name="mac_steps[{{index}}][target_url]" 
                               placeholder="admin.php?page=..."
                               class="regular-text">
                    </label>
                    <label>
                        <?php _e('Element Selector (optional):', 'mac-tutorials'); ?>
                        <input type="text" 
                               name="mac_steps[{{index}}][target_selector]" 
                               placeholder="#element-id or .class-name"
                               class="regular-text">
                    </label>
                    <div class="mac-step-time">
                        <label>
                            <?php _e('Min Time (minutes):', 'mac-tutorials'); ?>
                            <input type="number" 
                                   name="mac_steps[{{index}}][min_time]" 
                                   value="1"
                                   min="1" max="60">
                        </label>
                        <label>
                            <?php _e('Max Time (minutes):', 'mac-tutorials'); ?>
                            <input type="number" 
                                   name="mac_steps[{{index}}][max_time]" 
                                   value="5"
                                   min="1" max="60">
                        </label>
                    </div>
                </div>
            </div>
        </script>
        <?php
    }
    
    public function render_settings_meta_box($post) {
        $settings = get_post_meta($post->ID, '_mac_tutorial_settings', true);
        $settings = !empty($settings) && is_array($settings) ? $settings : array();
        
        ?>
        <p>
            <label>
                <?php _e('Difficulty:', 'mac-tutorials'); ?><br>
                <select name="mac_tutorial_settings[difficulty]">
                    <option value="beginner" <?php selected($settings['difficulty'] ?? '', 'beginner'); ?>>
                        <?php _e('Beginner', 'mac-tutorials'); ?>
                    </option>
                    <option value="intermediate" <?php selected($settings['difficulty'] ?? '', 'intermediate'); ?>>
                        <?php _e('Intermediate', 'mac-tutorials'); ?>
                    </option>
                    <option value="advanced" <?php selected($settings['difficulty'] ?? '', 'advanced'); ?>>
                        <?php _e('Advanced', 'mac-tutorials'); ?>
                    </option>
                </select>
            </label>
        </p>
        <p>
            <label>
                <?php _e('Category:', 'mac-tutorials'); ?><br>
                <input type="text" 
                       name="mac_tutorial_settings[category]" 
                       value="<?php echo esc_attr($settings['category'] ?? ''); ?>"
                       placeholder="<?php esc_attr_e('e.g., Post Types, Forms', 'mac-tutorials'); ?>">
            </label>
        </p>
        <?php
    }
    
    public function save_steps($post_id, $post) {
        // Verify nonce
        if (!isset($_POST['mac_tutorial_steps_nonce']) || 
            !wp_verify_nonce($_POST['mac_tutorial_steps_nonce'], 'mac_tutorial_steps_nonce')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save steps
        if (isset($_POST['mac_steps']) && is_array($_POST['mac_steps'])) {
            $steps = array();
            foreach ($_POST['mac_steps'] as $step) {
                if (!empty($step['title'])) {
                    $steps[] = array(
                        'title' => sanitize_text_field($step['title']),
                        'description' => wp_kses_post($step['description']),
                        'target_url' => esc_url_raw($step['target_url']),
                        'target_selector' => sanitize_text_field($step['target_selector'] ?? ''),
                        'min_time' => absint($step['min_time'] ?? 1),
                        'max_time' => absint($step['max_time'] ?? 5),
                    );
                }
            }
            update_post_meta($post_id, '_mac_tutorial_steps', $steps);
        }
        
        // Save settings
        if (isset($_POST['mac_tutorial_settings'])) {
            $settings = array(
                'difficulty' => sanitize_text_field($_POST['mac_tutorial_settings']['difficulty'] ?? 'beginner'),
                'category' => sanitize_text_field($_POST['mac_tutorial_settings']['category'] ?? ''),
            );
            update_post_meta($post_id, '_mac_tutorial_settings', $settings);
        }
    }
    
    public function enqueue_admin_scripts($hook) {
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }
        
        global $post_type;
        if ('mac_tutorial' !== $post_type) {
            return;
        }
        
        wp_enqueue_script(
            'mac-tutorial-admin',
            MAC_TUTORIALS_URL . 'admin/js/admin.js',
            array('jquery', 'jquery-ui-sortable'),
            MAC_TUTORIALS_VERSION,
            true
        );
        
        wp_enqueue_style(
            'mac-tutorial-admin',
            MAC_TUTORIALS_URL . 'admin/css/admin.css',
            array(),
            MAC_TUTORIALS_VERSION
        );
    }
}

// ============================================
// 3. STATE MANAGER
// ============================================

class MAC_Tutorial_State_Manager {
    
    public function __construct() {
        add_action('wp_ajax_mac_tutorial_state', array($this, 'handle_ajax'));
    }
    
    public function get_user_state($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $state = get_user_meta($user_id, 'mac_tutorial_state', true);
        return !empty($state) && is_array($state) ? $state : array();
    }
    
    public function update_state($tutorial_id, $step_index, $status = 'in-progress') {
        $user_id = get_current_user_id();
        $state = $this->get_user_state($user_id);
        
        $state[$tutorial_id] = array(
            'tutorial_id' => $tutorial_id,
            'current_step' => $step_index,
            'status' => $status,
            'updated_at' => current_time('mysql'),
        );
        
        update_user_meta($user_id, 'mac_tutorial_state', $state);
        
        return $state[$tutorial_id];
    }
    
    public function handle_ajax() {
        check_ajax_referer('mac_tutorial_nonce', 'nonce');
        
        if (!current_user_can('read')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $action = sanitize_text_field($_POST['action_type'] ?? '');
        $tutorial_id = absint($_POST['tutorial_id'] ?? 0);
        $step_index = absint($_POST['step_index'] ?? 0);
        
        switch ($action) {
            case 'start':
                $this->update_state($tutorial_id, 0, 'in-progress');
                wp_send_json_success();
                break;
                
            case 'update_step':
                $this->update_state($tutorial_id, $step_index, 'in-progress');
                wp_send_json_success();
                break;
                
            case 'pause':
                $this->update_state($tutorial_id, $step_index, 'pause');
                wp_send_json_success();
                break;
                
            case 'complete':
                $this->update_state($tutorial_id, $step_index, 'complete');
                wp_send_json_success();
                break;
                
            default:
                wp_send_json_error(array('message' => 'Invalid action'));
        }
    }
}

// ============================================
// 4. FRONTEND WIDGET LOADER
// ============================================

class MAC_Tutorial_Frontend {
    
    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_footer', array($this, 'render_widget_container'));
    }
    
    public function enqueue_scripts($hook) {
        // Chỉ load trên admin pages
        if (strpos($hook, 'admin.php') === false && strpos($hook, 'post.php') === false) {
            return;
        }
        
        // Check nếu có active tutorial
        $state_manager = new MAC_Tutorial_State_Manager();
        $state = $state_manager->get_user_state();
        
        $active_tutorial = null;
        foreach ($state as $tutorial_id => $tutorial_state) {
            if (isset($tutorial_state['status']) && $tutorial_state['status'] === 'in-progress') {
                $active_tutorial = get_post($tutorial_id);
                if ($active_tutorial && $active_tutorial->post_type === 'mac_tutorial') {
                    break;
                }
            }
        }
        
        if (!$active_tutorial) {
            return;
        }
        
        wp_enqueue_script(
            'mac-tutorial-widget',
            MAC_TUTORIALS_URL . 'frontend/js/widget.js',
            array('jquery'),
            MAC_TUTORIALS_VERSION,
            true
        );
        
        wp_enqueue_style(
            'mac-tutorial-widget',
            MAC_TUTORIALS_URL . 'frontend/css/widget.css',
            array(),
            MAC_TUTORIALS_VERSION
        );
        
        // Pass data to JavaScript
        $steps = get_post_meta($active_tutorial->ID, '_mac_tutorial_steps', true);
        $current_step = isset($state[$active_tutorial->ID]['current_step']) 
            ? $state[$active_tutorial->ID]['current_step'] 
            : 0;
        
        wp_localize_script('mac-tutorial-widget', 'MacTutorialData', array(
            'tutorial' => array(
                'id' => $active_tutorial->ID,
                'title' => $active_tutorial->post_title,
                'content' => $active_tutorial->post_content,
                'steps' => $steps,
            ),
            'current_step' => $current_step,
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mac_tutorial_nonce'),
        ));
    }
    
    public function render_widget_container() {
        ?>
        <div id="mac-tutorial-widget-container"></div>
        <?php
    }
}

// ============================================
// 5. INITIALIZATION
// ============================================

function mac_tutorials_init() {
    new MAC_Tutorial_Post_Type();
    new MAC_Tutorial_Meta_Boxes();
    new MAC_Tutorial_State_Manager();
    new MAC_Tutorial_Frontend();
}
add_action('plugins_loaded', 'mac_tutorials_init');

