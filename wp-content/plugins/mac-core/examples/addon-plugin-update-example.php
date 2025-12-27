<?php
/**
 * Example: How to add update functionality for your addon plugin
 * 
 * This file shows how to easily add update functionality for any addon plugin
 * without creating custom handlers for each plugin.
 */

// Example 1: Register update handler for your addon plugin
// Add this to your addon plugin's main file or admin file

function register_my_addon_update_handler() {
    // Get the plugin installer instance
    global $mac_core_plugin_installer;
    
    if ($mac_core_plugin_installer) {
        // Register update handler for your plugin
        $mac_core_plugin_installer->register_plugin_update_handler('my-addon-plugin');
    }
}
add_action('admin_init', 'register_my_addon_update_handler');

// Example 2: Add update button to your plugin's admin page
function add_my_addon_update_button() {
    global $mac_core_plugin_installer;
    
    if ($mac_core_plugin_installer) {
        // Generate update button HTML
        echo $mac_core_plugin_installer->get_plugin_update_button(
            'my-addon-plugin',           // Plugin slug
            'My Addon Plugin',           // Plugin display name
            'button button-primary'      // Button CSS class (optional)
        );
    }
}

// Example 3: Add update button with custom styling
function add_my_addon_update_button_custom() {
    global $mac_core_plugin_installer;
    
    if ($mac_core_plugin_installer) {
        // Generate update button with custom styling
        echo $mac_core_plugin_installer->get_plugin_update_button(
            'my-addon-plugin',
            'My Addon Plugin',
            'button button-primary mac-custom-update-btn'
        );
    }
}

// Example 4: Check if update is available and show button conditionally
function show_update_button_if_needed() {
    global $mac_core_plugin_installer;
    
    if (!$mac_core_plugin_installer) {
        return;
    }
    
    // Check if plugin is installed and active
    if (!is_plugin_active('my-addon-plugin/my-addon-plugin.php')) {
        return;
    }
    
    // Check for updates (you can implement your own update check logic)
    $has_update = check_my_addon_update_available();
    
    if ($has_update) {
        echo '<div class="notice notice-info">';
        echo '<p>Update available for My Addon Plugin!</p>';
        echo $mac_core_plugin_installer->get_plugin_update_button(
            'my-addon-plugin',
            'My Addon Plugin',
            'button button-primary'
        );
        echo '</div>';
    }
}

// Example 5: Add update button to plugin row in plugins page
function add_update_button_to_plugin_row($plugin_file, $plugin_data, $status) {
    if ($plugin_file === 'my-addon-plugin/my-addon-plugin.php') {
        global $mac_core_plugin_installer;
        
        if ($mac_core_plugin_installer) {
            echo '<tr class="plugin-update-tr active">';
            echo '<td colspan="3" class="plugin-update colspanchange">';
            echo '<div class="update-message notice inline notice-warning notice-alt">';
            echo '<p>Update available for My Addon Plugin!</p>';
            echo $mac_core_plugin_installer->get_plugin_update_button(
                'my-addon-plugin',
                'My Addon Plugin',
                'button button-small button-primary'
            );
            echo '</div>';
            echo '</td>';
            echo '</tr>';
        }
    }
}
add_action('after_plugin_row', 'add_update_button_to_plugin_row', 10, 3);

// Example 6: Custom update check function (implement your own logic)
function check_my_addon_update_available() {
    // Your custom update check logic here
    // Return true if update is available, false otherwise
    
    // Example: Check version from remote server
    $current_version = get_plugin_data(WP_PLUGIN_DIR . '/my-addon-plugin/my-addon-plugin.php')['Version'];
    $latest_version = get_latest_version_from_server('my-addon-plugin');
    
    return version_compare($current_version, $latest_version, '<');
}

// Example 7: Using the generic AJAX handler directly
function update_my_addon_via_ajax() {
    // This is how you would call the update via AJAX
    ?>
    <script>
    jQuery(document).ready(function($) {
        $('#update-my-addon-btn').on('click', function() {
            if (confirm('Are you sure you want to update My Addon Plugin?')) {
                const button = $(this);
                button.prop('disabled', true).text('Updating...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mac_update_plugin',
                        plugin_slug: 'my-addon-plugin',
                        nonce: '<?php echo wp_create_nonce('mac_core_install_plugin'); ?>',
                        retry: 0
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('✅ ' + response.data);
                            location.reload();
                        } else {
                            alert('❌ ' + response.data);
                            button.prop('disabled', false).text('Update My Addon Plugin');
                        }
                    },
                    error: function() {
                        alert('❌ An error occurred while updating.');
                        button.prop('disabled', false).text('Update My Addon Plugin');
                    }
                });
            }
        });
    });
    </script>
    <?php
}

/**
 * SUMMARY:
 * 
 * 1. Use register_plugin_update_handler() to register update handler
 * 2. Use get_plugin_update_button() to generate update button HTML
 * 3. Use generic AJAX action 'mac_update_plugin' with plugin_slug parameter
 * 4. All plugins will automatically get:
 *    - Deactivation before update
 *    - Update via CRM
 *    - Auto-activation after update
 *    - Retry mechanism for Windows file locks
 *    - Error handling and logging
 * 
 * No need to create custom handlers for each plugin!
 */
