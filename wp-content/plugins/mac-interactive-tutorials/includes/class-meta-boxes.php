<?php
/**
 * Meta Boxes Class
 * 
 * Handles meta boxes for tutorial steps and settings
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAC_Tutorial_Meta_Boxes {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_steps'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'mac_tutorial_steps',
            __('Tutorial Steps', 'mac-interactive-tutorials'),
            array($this, 'render_steps_meta_box'),
            array('post', 'page', 'mac_tutorial'),
            'normal',
            'high'
        );
        
        add_meta_box(
            'mac_tutorial_settings',
            __('Tutorial Settings', 'mac-interactive-tutorials'),
            array($this, 'render_settings_meta_box'),
            array('post', 'page', 'mac_tutorial'),
            'side',
            'default'
        );
    }
    
    /**
     * Render steps meta box
     */
    public function render_steps_meta_box($post) {
        wp_nonce_field('mac_tutorial_steps_nonce', 'mac_tutorial_steps_nonce');
        
        $steps = get_post_meta($post->ID, '_mac_tutorial_steps', true);
        $steps = !empty($steps) && is_array($steps) ? $steps : array();
        
        ?>
        <div id="mac-tutorial-steps-builder">
            <div class="mac-steps-list">
                <?php if (!empty($steps)): ?>
                    <?php foreach ($steps as $index => $step): ?>
                        <?php $this->render_step_item($index, $step); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <button type="button" class="button button-primary mac-add-step">
                <?php _e('+ Add Step', 'mac-interactive-tutorials'); ?>
            </button>
            
            <script type="text/template" id="mac-step-template">
                <?php $this->render_step_item('{{index}}', array(), true); ?>
            </script>
        </div>
        <?php
    }
    
    /**
     * Render single step item
     */
    private function render_step_item($index, $step = array(), $is_template = false) {
        $step = wp_parse_args($step, array(
            'title' => '',
            'description' => '',
            'target_url' => '',
            'target_selector' => '',
        ));
        
        $index_attr = $is_template ? '{{index}}' : $index;
        $name_prefix = $is_template ? 'mac_steps[{{index}}]' : 'mac_steps[' . $index . ']';
        ?>
        <div class="mac-step-item" data-index="<?php echo esc_attr($index); ?>">
            <div class="mac-step-header">
                <span class="mac-step-number"><?php echo ($is_template ? '{{number}}' : ($index + 1)); ?></span>
                <input type="text" 
                       name="<?php echo $name_prefix; ?>[title]" 
                       value="<?php echo esc_attr($step['title']); ?>"
                       placeholder="<?php esc_attr_e('Step Title', 'mac-interactive-tutorials'); ?>"
                       class="mac-step-title regular-text">
                <button type="button" class="button mac-remove-step"><?php _e('Remove', 'mac-interactive-tutorials'); ?></button>
            </div>
            <div class="mac-step-content">
                <p>
                    <label>
                        <strong><?php _e('Description:', 'mac-interactive-tutorials'); ?></strong>
                        <button type="button" class="button mac-switch-editor" data-step-index="<?php echo esc_attr($index); ?>">
                            <span class="mac-editor-mode-text"><?php _e('Switch to Visual Editor', 'mac-interactive-tutorials'); ?></span>
                            <span class="mac-editor-mode-visual" style="display: none;"><?php _e('Switch to Text', 'mac-interactive-tutorials'); ?></span>
                        </button>
                    </label>
                    <div class="mac-description-wrapper" data-step-index="<?php echo esc_attr($index); ?>">
                        <div class="mac-description-textarea-wrapper">
                            <textarea name="<?php echo $name_prefix; ?>[description]" 
                                      rows="3"
                                      class="large-text mac-step-description-textarea"
                                      placeholder="<?php esc_attr_e('Step Description', 'mac-interactive-tutorials'); ?>"><?php echo esc_textarea($step['description']); ?></textarea>
                        </div>
                        <div class="mac-description-editor-wrapper" style="display: none;">
                            <?php
                            $editor_id = 'mac_step_editor_' . $index;
                            $editor_content = isset($step['description']) ? $step['description'] : '';
                            wp_editor($editor_content, $editor_id, array(
                                'textarea_name' => $name_prefix . '[description]',
                                'textarea_rows' => 5,
                                'media_buttons' => false,
                                'teeny' => false,
                                'quicktags' => true,
                            ));
                            ?>
                        </div>
                    </div>
                </p>
                
                <p>
                    <label>
                        <strong><?php _e('Target URL:', 'mac-interactive-tutorials'); ?></strong><br>
                        <input type="url" 
                               name="<?php echo $name_prefix; ?>[target_url]" 
                               value="<?php echo esc_url($step['target_url']); ?>"
                               placeholder="admin.php?page=example"
                               class="regular-text">
                        <span class="description"><?php _e('URL to navigate to when this step is shown', 'mac-interactive-tutorials'); ?></span>
                    </label>
                </p>
                
                <p>
                    <label>
                        <strong><?php _e('Element Selector (optional):', 'mac-interactive-tutorials'); ?></strong><br>
                        <input type="text" 
                               name="<?php echo $name_prefix; ?>[target_selector]" 
                               value="<?php echo esc_attr($step['target_selector']); ?>"
                               placeholder="#element-id or .class-name"
                               class="regular-text">
                        <span class="description"><?php _e('CSS selector to highlight on page', 'mac-interactive-tutorials'); ?></span>
                    </label>
                </p>
                
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings meta box
     */
    public function render_settings_meta_box($post) {
        $settings = get_post_meta($post->ID, '_mac_tutorial_settings', true);
        $settings = !empty($settings) && is_array($settings) ? $settings : array();
        $enabled = get_post_meta($post->ID, '_mac_tutorial_enabled', true);
        $enabled = !empty($enabled);
        
        ?>
        <p>
            <label>
                <strong><?php _e('Enable tutorial for this post?', 'mac-interactive-tutorials'); ?></strong><br>
                <label style="display: inline-flex; align-items: center; gap: 6px;">
                    <input type="checkbox" name="mac_tutorial_enabled" value="1" <?php checked($enabled, true); ?> />
                    <?php _e('Enable', 'mac-interactive-tutorials'); ?>
                </label>
            </label>
        </p>
        <hr>
        <p>
            <label>
                <strong><?php _e('Difficulty:', 'mac-interactive-tutorials'); ?></strong><br>
                <select name="mac_tutorial_settings[difficulty]" style="width: 100%;">
                    <option value="beginner" <?php selected($settings['difficulty'] ?? '', 'beginner'); ?>>
                        <?php _e('Beginner', 'mac-interactive-tutorials'); ?>
                    </option>
                    <option value="intermediate" <?php selected($settings['difficulty'] ?? '', 'intermediate'); ?>>
                        <?php _e('Intermediate', 'mac-interactive-tutorials'); ?>
                    </option>
                    <option value="advanced" <?php selected($settings['difficulty'] ?? '', 'advanced'); ?>>
                        <?php _e('Advanced', 'mac-interactive-tutorials'); ?>
                    </option>
                </select>
            </label>
        </p>
        <p>
            <label>
                <strong><?php _e('Category:', 'mac-interactive-tutorials'); ?></strong><br>
                <input type="text" 
                       name="mac_tutorial_settings[category]" 
                       value="<?php echo esc_attr($settings['category'] ?? ''); ?>"
                       placeholder="<?php esc_attr_e('e.g., Post Types, Forms', 'mac-interactive-tutorials'); ?>"
                       class="regular-text" style="width: 100%;">
            </label>
        </p>
        <?php
    }
    
    /**
     * Save steps on post save
     */
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
            foreach ($_POST['mac_steps'] as $index => $step) {
                if (!empty($step['title'])) {
                    $steps[] = array(
                        'title' => sanitize_text_field($step['title']),
                        'description' => wp_kses_post($step['description'] ?? ''),
                        'target_url' => esc_url_raw($step['target_url'] ?? ''),
                        'target_selector' => sanitize_text_field($step['target_selector'] ?? ''),
                        'order' => $index,
                    );
                }
            }
            update_post_meta($post_id, '_mac_tutorial_steps', $steps);
        } else {
            // If no steps, clear meta
            delete_post_meta($post_id, '_mac_tutorial_steps');
        }
        
        // Save settings
        if (isset($_POST['mac_tutorial_settings'])) {
            $settings = array(
                'difficulty' => sanitize_text_field($_POST['mac_tutorial_settings']['difficulty'] ?? 'beginner'),
                'category' => sanitize_text_field($_POST['mac_tutorial_settings']['category'] ?? ''),
            );
            update_post_meta($post_id, '_mac_tutorial_settings', $settings);
        }
        
        // Save enable flag
        if (isset($_POST['mac_tutorial_enabled'])) {
            update_post_meta($post_id, '_mac_tutorial_enabled', 1);
        } else {
            delete_post_meta($post_id, '_mac_tutorial_enabled');
        }
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on post edit screens
        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }
        
        global $post_type;
        if (!in_array($post_type, array('post', 'page', 'mac_tutorial'), true)) {
            return;
        }
        
        // Enqueue WordPress editor scripts (for wp_editor)
        wp_enqueue_script('editor');
        wp_enqueue_script('quicktags');
        wp_enqueue_style('editor-buttons');
        
        // Enqueue scripts
        wp_enqueue_script(
            'mac-tutorial-admin',
            MAC_TUTORIALS_PLUGIN_URL . 'admin/assets/js/admin.js',
            array('jquery', 'jquery-ui-sortable', 'editor', 'quicktags'),
            MAC_TUTORIALS_VERSION,
            true
        );
        
        // Enqueue styles
        wp_enqueue_style(
            'mac-tutorial-admin',
            MAC_TUTORIALS_PLUGIN_URL . 'admin/assets/css/admin.css',
            array(),
            MAC_TUTORIALS_VERSION
        );
        
        // Localize script
        wp_localize_script('mac-tutorial-admin', 'macTutorialAdmin', array(
            'nonce' => wp_create_nonce('mac_tutorial_admin'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'strings' => array(
                'removeConfirm' => __('Are you sure you want to remove this step?', 'mac-interactive-tutorials'),
            ),
        ));
    }
    
    /**
     * AJAX handler to get editor HTML
     */
    public function ajax_get_editor() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mac_tutorial_admin')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'mac-interactive-tutorials')));
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Unauthorized.', 'mac-interactive-tutorials')));
        }
        
        $step_index = isset($_POST['step_index']) ? intval($_POST['step_index']) : 0;
        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
        $editor_id = isset($_POST['editor_id']) ? sanitize_text_field($_POST['editor_id']) : 'mac_step_editor_' . $step_index;
        $textarea_name = isset($_POST['textarea_name']) ? sanitize_text_field($_POST['textarea_name']) : 'mac_steps[' . $step_index . '][description]';
        
        // Start output buffering
        ob_start();
        
        wp_editor($content, $editor_id, array(
            'textarea_name' => $textarea_name,
            'textarea_rows' => 5,
            'media_buttons' => false,
            'teeny' => false,
            'quicktags' => true,
        ));
        
        $editor_html = ob_get_clean();
        
        wp_send_json_success(array(
            'editor_html' => $editor_html,
            'editor_id' => $editor_id
        ));
    }
}

