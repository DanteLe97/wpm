<?php
/**
 * Source Admin Class
 * 
 * Handles admin pages for source site (managing client sites)
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAC_Tutorial_Source_Admin {
    
    /**
     * Database instance
     */
    private $database;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->database = new MAC_Tutorial_Database();
        add_action('admin_menu', array($this, 'add_admin_page'));
        add_action('admin_init', array($this, 'handle_actions'));
    }
    
    /**
     * Add admin page
     */
    public function add_admin_page() {
        add_menu_page(
            __('Tutorial Sites', 'mac-interactive-tutorials'),
            __('Tutorial Sites', 'mac-interactive-tutorials'),
            'manage_options',
            'mac-tutorial-sites',
            array($this, 'render_page'),
            'dashicons-networking',
            30
        );
    }
    
    /**
     * Handle admin actions
     */
    public function handle_actions() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'mac-tutorial-sites') {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Handle approve
        if (isset($_GET['action']) && $_GET['action'] === 'approve' && isset($_GET['site_id'])) {
            check_admin_referer('approve_site_' . $_GET['site_id']);
            $this->approve_site(intval($_GET['site_id']));
        }
        
        // Handle reject
        if (isset($_GET['action']) && $_GET['action'] === 'reject' && isset($_GET['site_id'])) {
            check_admin_referer('reject_site_' . $_GET['site_id']);
            $this->reject_site(intval($_GET['site_id']));
        }
        
        // Handle delete
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['site_id'])) {
            check_admin_referer('delete_site_' . $_GET['site_id']);
            $this->delete_site(intval($_GET['site_id']));
        }
    }
    
    /**
     * Approve site
     */
    private function approve_site($site_id) {
        $api_key = $this->database->generate_api_key();
        $result = $this->database->update_site_status($site_id, 'active', $api_key);
        
        if ($result) {
            wp_redirect(add_query_arg(array('page' => 'mac-tutorial-sites', 'approved' => '1'), admin_url('admin.php')));
            exit;
        }
    }
    
    /**
     * Reject site
     */
    private function reject_site($site_id) {
        $result = $this->database->update_site_status($site_id, 'rejected');
        
        if ($result) {
            wp_redirect(add_query_arg(array('page' => 'mac-tutorial-sites', 'rejected' => '1'), admin_url('admin.php')));
            exit;
        }
    }
    
    /**
     * Delete site
     */
    private function delete_site($site_id) {
        $result = $this->database->delete_site($site_id);
        
        if ($result) {
            wp_redirect(add_query_arg(array('page' => 'mac-tutorial-sites', 'deleted' => '1'), admin_url('admin.php')));
            exit;
        }
    }
    
    /**
     * Render admin page
     */
    public function render_page() {
        // Show notices
        if (isset($_GET['approved'])) {
            echo '<div class="notice notice-success"><p>' . __('Site approved successfully.', 'mac-interactive-tutorials') . '</p></div>';
        }
        if (isset($_GET['rejected'])) {
            echo '<div class="notice notice-success"><p>' . __('Site rejected.', 'mac-interactive-tutorials') . '</p></div>';
        }
        if (isset($_GET['deleted'])) {
            echo '<div class="notice notice-success"><p>' . __('Site deleted successfully.', 'mac-interactive-tutorials') . '</p></div>';
        }
        
        $sites = $this->database->get_all_sites();
        ?>
        <div class="wrap">
            <h1><?php _e('Tutorial Sites', 'mac-interactive-tutorials'); ?></h1>
            
            <h2><?php _e('Registered Sites', 'mac-interactive-tutorials'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><?php _e('ID', 'mac-interactive-tutorials'); ?></th>
                        <th scope="col"><?php _e('Site URL', 'mac-interactive-tutorials'); ?></th>
                        <th scope="col"><?php _e('API Key', 'mac-interactive-tutorials'); ?></th>
                        <th scope="col"><?php _e('Status', 'mac-interactive-tutorials'); ?></th>
                        <th scope="col"><?php _e('Created', 'mac-interactive-tutorials'); ?></th>
                        <th scope="col"><?php _e('Actions', 'mac-interactive-tutorials'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sites)): ?>
                        <tr>
                            <td colspan="6"><?php _e('No sites registered yet.', 'mac-interactive-tutorials'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sites as $site): ?>
                            <tr>
                                <td><?php echo esc_html($site['id']); ?></td>
                                <td><strong><?php echo esc_html($site['site_url']); ?></strong></td>
                                <td>
                                    <?php if (!empty($site['api_key'])): ?>
                                        <code><?php echo esc_html($site['api_key']); ?></code>
                                    <?php else: ?>
                                        <span class="description"><?php _e('Pending', 'mac-interactive-tutorials'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status = $site['status'];
                                    $status_labels = array(
                                        'pending' => array('label' => __('Pending', 'mac-interactive-tutorials'), 'color' => '#f0ad4e'),
                                        'active' => array('label' => __('Active', 'mac-interactive-tutorials'), 'color' => '#5cb85c'),
                                        'rejected' => array('label' => __('Rejected', 'mac-interactive-tutorials'), 'color' => '#d9534f')
                                    );
                                    $status_info = isset($status_labels[$status]) ? $status_labels[$status] : array('label' => $status, 'color' => '#999');
                                    ?>
                                    <span style="color: <?php echo esc_attr($status_info['color']); ?>; font-weight: bold;">
                                        <?php echo esc_html($status_info['label']); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($site['created_at']))); ?></td>
                                <td>
                                    <?php if ($status === 'pending'): ?>
                                        <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('action' => 'approve', 'site_id' => $site['id'])), 'approve_site_' . $site['id'])); ?>" class="button button-small"><?php _e('Approve', 'mac-interactive-tutorials'); ?></a>
                                        <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('action' => 'reject', 'site_id' => $site['id'])), 'reject_site_' . $site['id'])); ?>" class="button button-small"><?php _e('Reject', 'mac-interactive-tutorials'); ?></a>
                                    <?php endif; ?>
                                    <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('action' => 'delete', 'site_id' => $site['id'])), 'delete_site_' . $site['id'])); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php _e('Are you sure you want to delete this site?', 'mac-interactive-tutorials'); ?>');"><?php _e('Delete', 'mac-interactive-tutorials'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

