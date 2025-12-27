<?php
/**
 * Sync Admin Class
 * 
 * Handles admin page for syncing tutorials from source site (client site only)
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAC_Tutorial_Sync_Admin {
    
    /**
     * Source URL (hard coded, can be overridden)
     */
    private $default_source_url = 'https://note.macmarketing.us';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Add admin page
     */
    public function add_admin_page() {
        add_submenu_page(
            'edit.php?post_type=mac_tutorial',
            __('Sync Tutorials', 'mac-interactive-tutorials'),
            __('Sync Tutorials', 'mac-interactive-tutorials'),
            'manage_options',
            'mac-tutorial-sync',
            array($this, 'render_page')
        );
    }
    
    /**
     * Enqueue scripts
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'mac-tutorial-sync') === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
    }
    
    /**
     * Get source URL
     */
    private function get_source_url() {
        $source_url = get_option('mac_tutorial_source_url', '');
        if (empty($source_url)) {
            return $this->default_source_url;
        }
        return $source_url;
    }
    
    /**
     * Get API key
     */
    private function get_api_key() {
        return get_option('mac_tutorial_api_key', '');
    }
    
    /**
     * Register site (auto register on first sync)
     */
    private function register_site() {
        $source_url = $this->get_source_url();
        $current_site_url = home_url();
        
        $response = wp_remote_post($source_url . '/wp-json/mac-tutorials/v1/register', array(
            'body' => json_encode(array('site_url' => $current_site_url)),
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return $data;
    }
    
    /**
     * Get tutorial list from source
     */
    private function get_tutorial_list() {
        $source_url = $this->get_source_url();
        $api_key = $this->get_api_key();
        
        if (empty($api_key)) {
            return array(
                'success' => false,
                'message' => 'API key not found. Please wait for approval.'
            );
        }
        
        $response = wp_remote_post($source_url . '/wp-json/mac-tutorials/v1/tutorial-list', array(
            'body' => json_encode(array('api_key' => $api_key)),
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return $data;
    }
    
    /**
     * Sync tutorials from source
     */
    private function sync_tutorials($ids) {
        $source_url = $this->get_source_url();
        $api_key = $this->get_api_key();
        
        if (empty($api_key)) {
            return array(
                'success' => false,
                'message' => 'API key not found. Please wait for approval.'
            );
        }
        
        $response = wp_remote_post($source_url . '/wp-json/mac-tutorials/v1/sync-tutorials', array(
            'body' => json_encode(array(
                'api_key' => $api_key,
                'ids' => $ids
            )),
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['success']) && $data['success'] && isset($data['tutorials'])) {
            // Save tutorials to CPT
            foreach ($data['tutorials'] as $tutorial) {
                $this->save_synced_tutorial($tutorial);
            }
        }
        
        return $data;
    }
    
    /**
     * Save synced tutorial to CPT
     */
    private function save_synced_tutorial($tutorial_data) {
        $source_url = $this->get_source_url();
        $source_post_id = isset($tutorial_data['id']) ? intval($tutorial_data['id']) : 0;
        
        // Check if tutorial already exists
        $existing = get_posts(array(
            'post_type' => 'mac_tutorial',
            'meta_query' => array(
                array(
                    'key' => '_source_post_id',
                    'value' => $source_post_id,
                    'compare' => '='
                ),
                array(
                    'key' => '_source_url',
                    'value' => $source_url,
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1
        ));
        
        $post_data = array(
            'post_title' => isset($tutorial_data['title']) ? $tutorial_data['title'] : '',
            'post_content' => isset($tutorial_data['content']) ? $tutorial_data['content'] : '',
            'post_excerpt' => isset($tutorial_data['excerpt']) ? $tutorial_data['excerpt'] : '',
            'post_type' => 'mac_tutorial',
            'post_status' => 'publish'
        );
        
        if (!empty($existing)) {
            $post_data['ID'] = $existing[0]->ID;
        }
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            return false;
        }
        
        // Save meta data
        update_post_meta($post_id, '_is_synced', true);
        update_post_meta($post_id, '_source_url', $source_url);
        update_post_meta($post_id, '_source_post_id', $source_post_id);
        
        if (isset($tutorial_data['enabled'])) {
            update_post_meta($post_id, '_mac_tutorial_enabled', $tutorial_data['enabled']);
        }
        
        if (isset($tutorial_data['steps']) && is_array($tutorial_data['steps'])) {
            update_post_meta($post_id, '_mac_tutorial_steps', $tutorial_data['steps']);
        }
        
        if (isset($tutorial_data['settings']) && is_array($tutorial_data['settings'])) {
            update_post_meta($post_id, '_mac_tutorial_settings', $tutorial_data['settings']);
        }
        
        return $post_id;
    }
    
    /**
     * Render admin page
     */
    public function render_page() {
        $source_url = $this->get_source_url();
        $api_key = $this->get_api_key();
        
        // Handle AJAX requests
        if (isset($_POST['action'])) {
            check_admin_referer('mac_tutorial_sync');
            
            if ($_POST['action'] === 'sync_list') {
                $register_error = '';
                // Check if we need to register first
                if (empty($api_key)) {
                    $register_result = $this->register_site();
                    if (isset($register_result['status']) && $register_result['status'] === 'pending') {
                        echo '<div class="notice notice-info"><p>' . __('Site registered. Waiting for approval from source site.', 'mac-interactive-tutorials') . '</p></div>';
                    } elseif (isset($register_result['status']) && $register_result['status'] === 'active' && isset($register_result['data']['api_key'])) {
                        update_option('mac_tutorial_api_key', $register_result['data']['api_key']);
                        $api_key = $register_result['data']['api_key'];
                    } else {
                        // Error registering
                        $register_error = isset($register_result['message']) ? $register_result['message'] : __('Failed to register this site with the source site.', 'mac-interactive-tutorials');
                        echo '<div class="notice notice-error"><p>' . esc_html($register_error) . '</p></div>';
                    }
                }
                
                // Get tutorial list
                if (!empty($api_key)) {
                    $list_result = $this->get_tutorial_list();
                    if (isset($list_result['success']) && $list_result['success']) {
                        $tutorials = isset($list_result['tutorials']) ? $list_result['tutorials'] : array();
                        if (empty($tutorials)) {
                            echo '<div class="notice notice-info"><p>' . __('No tutorials found on source site (need posts/pages with tutorial enabled).', 'mac-interactive-tutorials') . '</p></div>';
                        }
                    } else {
                        $error_message = isset($list_result['message']) ? $list_result['message'] : __('Failed to get tutorial list.', 'mac-interactive-tutorials');
                        echo '<div class="notice notice-error"><p>' . esc_html($error_message) . '</p></div>';
                        $tutorials = array();
                    }
                } else {
                    $tutorials = array();
                }
            } elseif ($_POST['action'] === 'sync_data' && isset($_POST['tutorial_ids'])) {
                $ids = array_map('intval', $_POST['tutorial_ids']);
                $sync_result = $this->sync_tutorials($ids);
                
                if (isset($sync_result['success']) && $sync_result['success']) {
                    echo '<div class="notice notice-success"><p>' . sprintf(__('Successfully synced %d tutorial(s).', 'mac-interactive-tutorials'), count($ids)) . '</p></div>';
                } else {
                    $error_message = isset($sync_result['message']) ? $sync_result['message'] : __('Failed to sync tutorials.', 'mac-interactive-tutorials');
                    echo '<div class="notice notice-error"><p>' . esc_html($error_message) . '</p></div>';
                }
            }
        }
        
        // Get tutorial list for display
        $tutorials = array();
        $initial_list_error = '';
        if (!empty($api_key)) {
            $list_result = $this->get_tutorial_list();
            if (isset($list_result['success']) && $list_result['success']) {
                $tutorials = isset($list_result['tutorials']) ? $list_result['tutorials'] : array();
                if (empty($tutorials)) {
                    $initial_list_error = __('No tutorials found on source site (need posts/pages with tutorial enabled).', 'mac-interactive-tutorials');
                }
            } else {
                $initial_list_error = isset($list_result['message']) ? $list_result['message'] : __('Failed to get tutorial list.', 'mac-interactive-tutorials');
            }
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Sync Tutorials', 'mac-interactive-tutorials'); ?></h1>
            
            <div class="card" style="max-width: 800px;">
                <h2><?php _e('Source Site', 'mac-interactive-tutorials'); ?></h2>
                <p>
                    <strong><?php _e('Source URL:', 'mac-interactive-tutorials'); ?></strong>
                    <code><?php echo esc_html($source_url); ?></code>
                    <span class="description"><?php _e('(Hard coded)', 'mac-interactive-tutorials'); ?></span>
                </p>
                <p>
                    <strong><?php _e('Status:', 'mac-interactive-tutorials'); ?></strong>
                    <?php if (empty($api_key)): ?>
                        <span style="color: #f0ad4e;"><?php _e('Pending Approval', 'mac-interactive-tutorials'); ?></span>
                        <p class="description"><?php _e('Click "Sync List" to register this site and wait for approval from the source site.', 'mac-interactive-tutorials'); ?></p>
                    <?php else: ?>
                        <span style="color: #5cb85c;"><?php _e('Active', 'mac-interactive-tutorials'); ?></span>
                        <br><small><code><?php echo esc_html($api_key); ?></code></small>
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2><?php _e('Sync Tutorial List', 'mac-interactive-tutorials'); ?></h2>
                <form method="post" action="">
                    <?php wp_nonce_field('mac_tutorial_sync'); ?>
                    <input type="hidden" name="action" value="sync_list">
                    <p>
                        <?php _e('Click the button below to fetch the list of available tutorials from the source site.', 'mac-interactive-tutorials'); ?>
                    </p>
                    <?php submit_button(__('Sync List', 'mac-interactive-tutorials'), 'primary', 'submit', false); ?>
                </form>
            </div>
            
            <?php if (!empty($initial_list_error)): ?>
                <div class="notice notice-error" style="margin-top: 10px;">
                    <p><?php echo esc_html($initial_list_error); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($tutorials)): ?>
                <div class="card" style="max-width: 800px; margin-top: 20px;">
                    <h2><?php _e('Available Tutorials', 'mac-interactive-tutorials'); ?></h2>
                    <form method="post" action="" id="sync-data-form">
                        <?php wp_nonce_field('mac_tutorial_sync'); ?>
                        <input type="hidden" name="action" value="sync_data">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th scope="col" style="width: 50px;">
                                        <input type="checkbox" id="select-all">
                                    </th>
                                    <th scope="col"><?php _e('ID', 'mac-interactive-tutorials'); ?></th>
                                    <th scope="col"><?php _e('Title', 'mac-interactive-tutorials'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tutorials as $tutorial): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="tutorial_ids[]" value="<?php echo esc_attr($tutorial['id']); ?>" class="tutorial-checkbox">
                                        </td>
                                        <td><?php echo esc_html($tutorial['id']); ?></td>
                                        <td><?php echo esc_html($tutorial['title']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p style="margin-top: 15px;">
                            <?php submit_button(__('Sync Selected Tutorials', 'mac-interactive-tutorials'), 'primary', 'submit', false); ?>
                        </p>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#select-all').on('change', function() {
                $('.tutorial-checkbox').prop('checked', $(this).prop('checked'));
            });
        });
        </script>
        <?php
    }
}

