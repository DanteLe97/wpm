<?php
/**
 * Synced Post Type Class
 * 
 * Handles Custom Post Type for synced tutorials (client site only)
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAC_Tutorial_Synced_Post_Type {
    
    /**
     * Post type name
     */
    const POST_TYPE = 'mac_tutorial';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'register_post_type'), 0);
        add_action('init', array($this, 'maybe_flush_rewrite_rules_init'), 999);
        add_action('registered_post_type', array($this, 'maybe_flush_rewrite_rules'), 10, 2);
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
    }
    
    /**
     * Register post type
     */
    public function register_post_type() {
        $labels = array(
            'name' => __('Tutorials', 'mac-interactive-tutorials'),
            'singular_name' => __('Tutorial', 'mac-interactive-tutorials'),
            'menu_name' => __('Tutorials', 'mac-interactive-tutorials'),
            'add_new' => __('Add New', 'mac-interactive-tutorials'),
            'add_new_item' => __('Add New Tutorial', 'mac-interactive-tutorials'),
            'edit_item' => __('Edit Tutorial', 'mac-interactive-tutorials'),
            'new_item' => __('New Tutorial', 'mac-interactive-tutorials'),
            'view_item' => __('View Tutorial', 'mac-interactive-tutorials'),
            'search_items' => __('Search Tutorials', 'mac-interactive-tutorials'),
            'not_found' => __('No tutorials found', 'mac-interactive-tutorials'),
            'not_found_in_trash' => __('No tutorials found in Trash', 'mac-interactive-tutorials'),
        );
        
        $args = array(
            'labels' => $labels,
            'public' => false, // Không hiển thị trong frontend menu
            'publicly_queryable' => true, // Cho phép có permalink để View button hoạt động
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_icon' => 'dashicons-welcome-learn-more',
            'capability_type' => 'post',
            'hierarchical' => false,
            'supports' => array('title', 'editor', 'excerpt'),
            'has_archive' => false,
            'rewrite' => array('slug' => 'tutorial'), // Cho phép rewrite để có permalink
            'query_var' => true,
        );
        
        register_post_type(self::POST_TYPE, $args);
    }
    
    /**
     * Maybe flush rewrite rules after init (backup method)
     * Only flush once to avoid performance issues
     */
    public function maybe_flush_rewrite_rules_init() {
        // Check if post type is registered
        if (!post_type_exists(self::POST_TYPE)) {
            return;
        }
        
        // Check if we've already flushed rewrite rules for this version
        $option_name = 'mac_tutorial_rewrite_flushed';
        $flushed_version = get_option($option_name);
        $current_version = MAC_TUTORIALS_VERSION;
        
        // Only flush if not already flushed for this version
        if ($flushed_version !== $current_version) {
            flush_rewrite_rules(false); // false = soft flush (faster)
            update_option($option_name, $current_version);
        }
    }
    
    /**
     * Maybe flush rewrite rules after registering post type
     * Only flush once to avoid performance issues
     */
    public function maybe_flush_rewrite_rules($post_type, $args) {
        if ($post_type !== self::POST_TYPE) {
            return;
        }
        
        // Check if we've already flushed rewrite rules for this version
        $option_name = 'mac_tutorial_rewrite_flushed';
        $flushed_version = get_option($option_name);
        $current_version = MAC_TUTORIALS_VERSION;
        
        // Only flush if not already flushed for this version
        if ($flushed_version !== $current_version) {
            flush_rewrite_rules(false); // false = soft flush (faster)
            update_option($option_name, $current_version);
        }
    }
    
    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'mac_tutorial_synced_info',
            __('Synced Tutorial Info', 'mac-interactive-tutorials'),
            array($this, 'render_synced_info_meta_box'),
            self::POST_TYPE,
            'side',
            'high'
        );
    }
    
    /**
     * Render synced info meta box
     */
    public function render_synced_info_meta_box($post) {
        wp_nonce_field('mac_tutorial_synced_info', 'mac_tutorial_synced_info_nonce');
        
        $is_synced = get_post_meta($post->ID, '_is_synced', true);
        $source_url = get_post_meta($post->ID, '_source_url', true);
        $source_post_id = get_post_meta($post->ID, '_source_post_id', true);
        
        ?>
        <p>
            <strong><?php _e('Synced:', 'mac-interactive-tutorials'); ?></strong>
            <?php echo $is_synced ? __('Yes', 'mac-interactive-tutorials') : __('No', 'mac-interactive-tutorials'); ?>
        </p>
        <?php if ($is_synced && $source_url): ?>
            <p>
                <strong><?php _e('Source URL:', 'mac-interactive-tutorials'); ?></strong><br>
                <a href="<?php echo esc_url($source_url); ?>" target="_blank"><?php echo esc_html($source_url); ?></a>
            </p>
            <?php if ($source_post_id): ?>
                <p>
                    <strong><?php _e('Source Post ID:', 'mac-interactive-tutorials'); ?></strong><br>
                    <?php echo esc_html($source_post_id); ?>
                </p>
            <?php endif; ?>
            <p class="description">
                <?php _e('This tutorial was synced from the source site. Do not edit manually.', 'mac-interactive-tutorials'); ?>
            </p>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Save meta boxes
     */
    public function save_meta_boxes($post_id) {
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check post type
        if (get_post_type($post_id) !== self::POST_TYPE) {
            return;
        }
        
        // Check nonce
        if (!isset($_POST['mac_tutorial_synced_info_nonce']) || 
            !wp_verify_nonce($_POST['mac_tutorial_synced_info_nonce'], 'mac_tutorial_synced_info')) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Meta boxes are read-only for synced tutorials, so we don't save anything here
    }
}

