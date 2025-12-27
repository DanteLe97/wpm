jQuery(document).ready(function($) {
    'use strict';
    

    // Handle license actions
    $('.mac-core-license-action').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        var action = $button.data('action');
        var licenseId = $button.data('license-id');

        if (action === 'delete' && !confirm(macCoreAdmin.i18n.confirmDelete)) {
            return;
        }

        $button.prop('disabled', true);

        $.ajax({
            url: macCoreAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mac_core_license_action',
                nonce: macCoreAdmin.nonce,
                license_action: action,
                license_id: licenseId
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    var message = 'An error occurred.';
                    if (response && response.data && response.data.message) {
                        message = response.data.message;
                    } else if (response && response.message) {
                        message = response.message;
                    }
                    alert(message);
                }
            },
            error: function() {
                alert(macCoreAdmin.i18n.error);
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });

    // Check Update MAC Menu (moved from dashboard.php)
    $(document).on('click', '.mac-core-check-update-mac-menu', function() {
        var $button = $(this);
        var $updateButton = $('.mac-core-update-mac-menu');
        var $status = $('#mac-core-update-status');
        var originalText = $button.text();

        $button.prop('disabled', true).text('Checking...');
        $status.html('<div class="notice notice-info"><p>🔄 Checking for updates...</p></div>').show();

        $.ajax({
            url: macCoreAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mac_core_check_update_mac_menu',
                nonce: macCoreAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data || {};
                    if (data.needs_update) {
                        $status.html('<div class="notice notice-success"><p>✅ Update available! Current: ' + data.current_version + ' → New: ' + data.version + '</p></div>');
                        $updateButton.show();
                    } else {
                        $status.html('<div class="notice notice-info"><p>ℹ️ No updates available. Current version: ' + data.current_version + '</p></div>');
                        $updateButton.hide();
                    }
                } else {
                    $status.html('<div class="notice notice-error"><p>❌ Error: ' + (response.data || 'Unknown error') + '</p></div>');
                    $updateButton.hide();
                }
            },
            error: function(xhr, status, error) {
                $status.html('<div class="notice notice-error"><p>❌ AJAX Error: ' + error + '</p></div>');
                $updateButton.hide();
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Add License Form submit
    $(document).on('submit', '#kvp-form', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $button = $form.find('button[type="submit"]');
        var $result = $('#kvp-result');
        var originalButtonText = $button.text();
        
        if ($('#kvp-key-input').val() !== '') {
            var key = $('#kvp-key-input').val();
            $button.prop('disabled', true).text('Validating...');
            $result.html('<div class="notice notice-info"><p>🔄 Sending request...</p></div>');

            $.ajax({
                url: macCoreAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mac_core_add_license',
                    key: key,
                    nonce: $('#mac_core_license_nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        $result.append('<div class="notice notice-success"><p>✅ Success: ' + response.data + '</p></div>');
                        setTimeout(function() { location.reload(); }, 2000);
                    } else {
                        $result.append('<div class="notice notice-error"><p>❌ Error: ' + (response.data || 'Unknown error') + '</p></div>');
                    }
                },
                error: function(xhr, status, error) {
                    $result.append('<div class="notice notice-error"><p>❌ AJAX Error: ' + status + ' - ' + error + '</p></div>');
                    if (xhr && xhr.responseText) {
                        $result.append('<div class="notice notice-warning"><p>📋 Response Text: ' + xhr.responseText + '</p></div>');
                    }
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalButtonText);
                }
            });
        } else {
            $result.html('<div class="notice notice-warning"><p>⚠️ Vui lòng nhập key hợp lệ</p></div>');
        }
    });

    // Toggle license form when key already exists (moved from dashboard.php)
    $(document).on('click', '#toggle-license-form', function() {
        $('#kvp-container').toggle();
    });
    // Handle plugin actions
    $('.mac-core-plugin-action').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        var action = $button.data('action');
        var pluginSlug = $button.data('plugin-slug');

        if (action === 'uninstall' && !confirm(macCoreAdmin.i18n.confirmDelete)) {
            return;
        }

        $button.prop('disabled', true);

        $.ajax({
            url: macCoreAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mac_core_plugin_action',
                nonce: macCoreAdmin.nonce,
                plugin_action: action,
                plugin_slug: pluginSlug
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    var message = 'An error occurred.';
                    if (response && response.data && response.data.message) {
                        message = response.data.message;
                    } else if (response && response.message) {
                        message = response.message;
                    }
                    alert(message);
                }
            },
            error: function() {
                alert(macCoreAdmin.i18n.error);
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });

    // Handle settings form
    $('#mac-core-settings-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $submitButton = $form.find('button[type="submit"]');

        $submitButton.prop('disabled', true);

        $.ajax({
            url: macCoreAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mac_core_save_settings',
                nonce: macCoreAdmin.nonce,
                api_url: $form.find('#api_url').val(),
                api_key: $form.find('#api_key').val(),
                debug_mode: $form.find('#debug_mode').is(':checked') ? 1 : 0
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    var message = 'An error occurred.';
                    if (response && response.data && response.data.message) {
                        message = response.data.message;
                    } else if (response && response.message) {
                        message = response.message;
                    }
                    alert(message);
                }
            },
            error: function() {
                alert(macCoreAdmin.i18n.error);
            },
            complete: function() {
                $submitButton.prop('disabled', false);
            }
        });
    });

    // Handle license key validation
    $('#license_key').on('blur', function() {
        var $input = $(this);
        var licenseKey = $input.val();

        if (!licenseKey) {
            return;
        }

        $.ajax({
            url: macCoreAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mac_core_validate_license',
                nonce: macCoreAdmin.nonce,
                license_key: licenseKey
            },
            success: function(response) {
                if (response.success) {
                    $input.removeClass('error').addClass('valid');
                } else {
                    $input.removeClass('valid').addClass('error');
                    var message = 'An error occurred.';
                    if (response && response.data && response.data.message) {
                        message = response.data.message;
                    } else if (response && response.message) {
                        message = response.message;
                    }
                    alert(message);
                }
            }
        });
    });

    // Handle plugin installation
    $('.mac-core-install-plugin').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        var pluginSlug = $button.data('plugin-slug');
        var $addonCard = $button.closest('.mac-core-addon-card');

        // First debug tokens
        $.ajax({
            url: macCoreAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mac_core_debug_tokens',
                nonce: macCoreAdmin.nonce
            },
            success: function(response) {
                console.log('Token debug:', response);
                if (response.success && response.data) {
                    var tokens = response.data;
                    var tokenInfo = [];
                    
                    for (var addon in tokens) {
                        if (addon === '_system') {
                            var sys = tokens[addon];
                            tokenInfo.push('=== SYSTEM STATUS ===');
                            tokenInfo.push('License Key: ' + sys.license_key);
                            tokenInfo.push('Domain Status: ' + sys.domain_status);
                            tokenInfo.push('Domain Key: ' + sys.domain_key);
                        } else {
                            var info = tokens[addon];
                            tokenInfo.push(addon + ': ' + (info.has_token ? 'FOUND (' + info.token_length + ' chars)' : 'NOT FOUND'));
                        }
                    }
                    
                    console.log('Token status:\n' + tokenInfo.join('\n'));
                    
                    // Check if current plugin has token
                    if (tokens[pluginSlug] && !tokens[pluginSlug].has_token) {
                        var sys = tokens['_system'];
                        var message = 'License not found for ' + pluginSlug + '.\n\n';
                        message += 'Token option: ' + tokens[pluginSlug].option_name + '\n\n';
                        message += 'System Status:\n';
                        message += '- License Key: ' + sys.license_key + '\n';
                        message += '- Domain Status: ' + sys.domain_status + '\n';
                        message += '- Domain Key: ' + sys.domain_key + '\n\n';
                        message += 'Please check your license key and domain validation.';
                        
                        alert(message);
                        return;
                    } else if (tokens[pluginSlug] && tokens[pluginSlug].has_token) {
                        // License is valid, continue with system requirements check
                        console.log('License is valid, checking system requirements...');
                        checkSystemRequirements();
                        return;
                    }
                    
                    // Continue with system requirements check
                    checkSystemRequirements();
                } else {
                    alert('Failed to check tokens.');
                }
            },
            error: function(xhr, status, error) {
                console.error('Token debug error:', xhr.responseText);
                alert('Failed to check tokens. Please try again.');
            }
        });
        
        function checkSystemRequirements() {
            // Check system requirements
            $.ajax({
                url: macCoreAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mac_core_check_system_requirements',
                    nonce: macCoreAdmin.nonce
                },
                success: function(response) {
                    console.log('System requirements:', response);
                    if (response.success && response.data) {
                        var req = response.data;
                        var issues = [];
                        
                        if (!req.plugins_dir_writable) {
                            issues.push('Plugins directory not writable: ' + req.plugins_dir_path);
                        }
                        if (!req.content_dir_writable) {
                            issues.push('Content directory not writable: ' + req.content_dir_path);
                        }
                        if (!req.php_version_ok) {
                            issues.push('PHP version too old: ' + req.php_version + ' (need 7.0+)');
                        }
                        if (!req.curl_available) {
                            issues.push('cURL not available');
                        }
                        
                        if (issues.length > 0) {
                            alert('System requirements not met:\n\n' + issues.join('\n'));
                            return;
                        }
                        
                        // System requirements OK, check plugin status
                        checkPluginStatus();
                    } else {
                        alert('Failed to check system requirements.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('System requirements check error:', xhr.responseText);
                    alert('Failed to check system requirements. Please try again.');
                }
            });
        }
        
        function checkPluginStatus() {
            // Check if CRM license is available
            $.ajax({
                url: macCoreAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mac_core_check_install_status',
                    nonce: macCoreAdmin.nonce,
                    plugin_slug: pluginSlug
                },
                success: function(response) {
                    console.log('Status check response:', response);
                    if (response.success && response.data) {
                        if (!response.data.has_token) {
                            alert('License not found. Please check your license key and domain validation.');
                            return;
                        }
                        
                        if (response.data.installed) {
                            alert('Plugin is already installed.');
                            return;
                        }
                        
                        // Proceed with installation
                        installPlugin();
                    } else {
                        var errorMsg = 'Failed to check plugin status.';
                        if (response && response.data && response.data.message) {
                            errorMsg = response.data.message;
                        }
                        alert(errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Status check error:', xhr.responseText);
                    alert('Failed to check plugin status. Please try again.');
                }
            });
        }

        function installPlugin() {
            $button.prop('disabled', true).text('Installing...');
            
            // Add loading indicator
            $addonCard.addClass('installing');
            
            $.ajax({
                url: macCoreAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mac_core_install_plugin',
                    nonce: macCoreAdmin.nonce,
                    plugin_slug: pluginSlug
                },
                success: function(response) {
                    console.log('Install response:', response);
                    if (response.success) {
                        // Don't set button to disabled yet, let the activation logic handle it
                        $addonCard.removeClass('installing').addClass('installed');
                        
                        // Show success message
                        var message = response.data.message || 'Plugin installed successfully!';
                        
                        // Check if manual activation is required
                        if (response.data.manual_activation_required) {
                            // Change button to "Activate"
                            $button.text('Activate').removeClass('button-disabled').addClass('mac-core-activate-plugin');
                        }
                        
                        // Check if activation failed (legacy support)
                        if (response.data.activation_failed) {
                            message += '\n\nPlugin has been installed but could not be activated automatically.';
                            message += '\nYou can try to activate it manually using the "Activate" button.';
                            message += '\n\nActivation error: ' + response.data.activation_error;
                            
                            // Change button to "Activate"
                            $button.text('Activate').removeClass('button-disabled').addClass('mac-core-activate-plugin');
                        }
                         
                         // Check if force activated
                         if (response.data.force_activated) {
                             message += '\n\nPlugin was force activated due to output issues.';
                             message += '\nThis is normal for MAC Menu plugin.';
                         }
                        
                        // Check if there are function conflicts
                        if (response.data.message && response.data.message.includes('Function conflicts detected')) {
                            message += '\n\nThere are function conflicts. You can force remove the conflicting plugin and try again.';
                            
                            // Change button to "Force Remove"
                            $button.text('Force Remove').removeClass('button-disabled').addClass('mac-core-force-remove-plugin');
                        }
                        
                        alert(message);
                        
                        // Reload page after a short delay
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        var message = 'Installation failed.';
                        if (response && response.data && response.data.message) {
                            message = response.data.message;
                        } else if (response && response.message) {
                            message = response.message;
                        }
                        alert(message);
                        $button.prop('disabled', false).text('Install Now');
                        $addonCard.removeClass('installing');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Installation error:', xhr.responseText);
                    console.error('Status:', status);
                    console.error('Error:', error);
                    console.error('HTTP Status:', xhr.status);
                    
                    var errorMsg = 'Installation failed. Please check your license and try again.';
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response && response.message) {
                            errorMsg = response.message;
                        }
                    } catch(e) {
                        // Use default error message
                        console.error('JSON parse error:', e);
                    }
                    
                    // Show detailed error for debugging
                    var detailedError = 'HTTP ' + xhr.status + ': ' + error + '\n\nResponse: ' + xhr.responseText.substring(0, 500);
                    console.error('Detailed error:', detailedError);
                    
                    alert(errorMsg + '\n\nDebug info: ' + detailedError);
                    $button.prop('disabled', false).text('Install Now');
                    $addonCard.removeClass('installing');
                }
            });
        }
    });
    
    // Handle manual plugin activation
    $(document).on('click', '.mac-core-activate-plugin', function(e) {
        e.preventDefault();
        var $button = $(this);
        var pluginSlug = $button.data('plugin-slug');
        var $addonCard = $button.closest('.mac-core-addon-card');
        
        $button.prop('disabled', true).text('Activating...');
        $addonCard.addClass('installing');
        
        $.ajax({
            url: macCoreAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mac_core_activate_plugin',
                nonce: macCoreAdmin.nonce,
                plugin_slug: pluginSlug
            },
            success: function(response) {
                console.log('Activate response:', response);
                                 if (response.success) {
                     $button.text('Activated!').addClass('button-disabled');
                     $addonCard.removeClass('installing').addClass('installed');
                     
                     var message = response.data.message || 'Plugin activated successfully!';
                     alert(message);
                     
                                           // Check if reload is required
                      if (response.data.reload_required) {
                          if (response.data.force_restart) {
                              // Force restart for MAC Menu to clear all caches
                              alert('Plugin activated successfully! The page will now restart to complete activation.');
                              setTimeout(function() {
                                  window.location.href = window.location.href.split('?')[0] + '?restart=1&t=' + Date.now();
                              }, 1000);
                          } else {
                              // Immediate reload for other plugins
                              location.reload();
                          }
                      } else {
                          // Reload page after a short delay
                          setTimeout(function() {
                              location.reload();
                          }, 1500);
                      }
                } else {
                    var message = 'Activation failed.';
                    if (response && response.data && response.data.message) {
                        message = response.data.message;
                    } else if (response && response.message) {
                        message = response.message;
                    }
                    alert(message);
                    $button.prop('disabled', false).text('Activate');
                    $addonCard.removeClass('installing');
                }
            },
            error: function(xhr, status, error) {
                console.error('Activation error:', xhr.responseText);
                var errorMsg = 'Activation failed. Please try again.';
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response && response.message) {
                        errorMsg = response.message;
                    }
                } catch(e) {
                    // Use default error message
                }
                alert(errorMsg);
                $button.prop('disabled', false).text('Activate');
                $addonCard.removeClass('installing');
            }
        });
    });
    
    // Handle force remove plugin
    $(document).on('click', '.mac-core-force-remove-plugin', function(e) {
        e.preventDefault();
        var $button = $(this);
        var pluginSlug = $button.data('plugin-slug');
        var $addonCard = $button.closest('.mac-core-addon-card');
        
        if (!confirm('Are you sure you want to force remove this plugin? This will completely delete the plugin files.')) {
            return;
        }
        
        $button.prop('disabled', true).text('Removing...');
        $addonCard.addClass('installing');
        
        $.ajax({
            url: macCoreAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mac_core_force_remove_plugin',
                nonce: macCoreAdmin.nonce,
                plugin_slug: pluginSlug
            },
            success: function(response) {
                console.log('Force remove response:', response);
                if (response.success) {
                    $button.text('Removed!').addClass('button-disabled');
                    $addonCard.removeClass('installing').addClass('installed');
                    
                    var message = response.data.message || 'Plugin removed successfully!';
                    message += '\n\nYou can now try installing the plugin again.';
                    alert(message);
                    
                    // Reload page after a short delay
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    var message = 'Force remove failed.';
                    if (response && response.data && response.data.message) {
                        message = response.data.message;
                    } else if (response && response.message) {
                        message = response.message;
                    }
                    alert(message);
                    $button.prop('disabled', false).text('Force Remove');
                    $addonCard.removeClass('installing');
                }
            },
            error: function(xhr, status, error) {
                console.error('Force remove error:', xhr.responseText);
                var errorMsg = 'Force remove failed. Please try again.';
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response && response.message) {
                        errorMsg = response.message;
                    }
                } catch(e) {
                    // Use default error message
                }
                alert(errorMsg);
                $button.prop('disabled', false).text('Force Remove');
                $addonCard.removeClass('installing');
            }
        });
    });
    
    // Handle check options status
    $(document).on('click', '.mac-core-check-options-status', function() {
        const button = $(this);
        const resultDiv = $('#mac-core-options-status-result');
        
        button.prop('disabled', true).text('Checking...');
        resultDiv.hide();
        
        $.ajax({
            url: macCoreAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mac_core_check_options_status',
                nonce: macCoreAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    let html = '<h4>Critical Options Status:</h4><table class="widefat"><thead><tr><th>Option</th><th>Exists</th><th>Value</th><th>Length</th></tr></thead><tbody>';
                    
                    Object.keys(response.data).forEach(function(option) {
                        const data = response.data[option];
                        html += '<tr>';
                        html += '<td><strong>' + option + '</strong></td>';
                        html += '<td>' + (data.exists ? '✅ Yes' : '❌ No') + '</td>';
                        html += '<td>' + data.value + '</td>';
                        html += '<td>' + data.length + '</td>';
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table>';
                    resultDiv.html(html).show();
                } else {
                    resultDiv.html('<p class="error">Error: ' + response.data + '</p>').show();
                }
            },
            error: function() {
                resultDiv.html('<p class="error">An error occurred while checking options status.</p>').show();
            },
            complete: function() {
                button.prop('disabled', false).text('Check Options Status');
            }
        });
    });
    
    // Handle check URL
    $(document).on('click', '.mac-core-check-url', function() {
        const button = $(this);
        
        button.prop('disabled', true).text('Checking URL...');
        
        $.ajax({
            url: macCoreAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mac_core_check_url',
                nonce: macCoreAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ ' + response.data + '\n\nCheck error log for detailed API information.');
                    // Reload page to show updated status
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    var message = '❌ URL check failed.';
                    if (response && response.data && response.data.message) {
                        message = response.data.message;
                    } else if (response && response.message) {
                        message = response.message;
                    }
                    alert(message);
                }
            },
            error: function() {
                alert('❌ An error occurred while checking URL.');
            },
            complete: function() {
                button.prop('disabled', false).text('Check URL');
            }
        });
    });
    
    // Handle test validate URL
    $(document).on('click', '.mac-core-test-validate-url', function() {
        const button = $(this);
        
        button.prop('disabled', true).text('Testing Validate URL...');
        
        $.ajax({
            url: macCoreAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mac_core_test_validate_url',
                nonce: macCoreAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Test Validate URL completed successfully!\n\nCheck error log for detailed API request/response information.');
                } else {
                    var message = 'Test Validate URL failed.';
                    if (response && response.data && response.data.message) {
                        message = response.data.message;
                    } else if (response && response.message) {
                        message = response.message;
                    }
                    alert(message);
                }
            },
            error: function() {
                alert('An error occurred while testing Validate URL.');
            },
            complete: function() {
                button.prop('disabled', false).text('Test Validate URL');
            }
        });
    });

    // Install MAC Menu button
    $(document).on('click', '.mac-core-install-mac-menu', function() {
        const button = $(this);
        button.prop('disabled', true).text('Installing MAC Menu...');
        $.ajax({
            url: macCoreAdmin.ajaxUrl,
            type: 'POST',
            data: { action: 'mac_core_install_mac_menu', nonce: macCoreAdmin.nonce },
            success: function(response) {
                if (response.success) {
                    alert('✅ ' + response.data + '\n\nPage will reload to show updated status.');
                    setTimeout(function() { location.reload(); }, 2000);
                } else {
                    var message = 'Failed to install MAC Menu.';
                    if (response && response.data && response.data.message) { message = response.data.message; }
                    else if (response && response.message) { message = response.message; }
                    alert('❌ ' + message);
                }
            },
            error: function() {
                alert('❌ An error occurred while installing MAC Menu.');
            },
            complete: function() {
                button.prop('disabled', false).text('Install MAC Menu');
            }
        });
    });

    // Generic update plugin function
    function updatePlugin(pluginSlug, button, originalText) {
        function doUpdate(retry) {
            $.ajax({
                url: macCoreAdmin.ajaxUrl,
                type: 'POST',
                data: { 
                    action: 'mac_update_plugin', 
                    plugin_slug: pluginSlug,
                    nonce: macCoreAdmin.nonce, 
                    retry: retry ? 1 : 0 
                },
                success: function(response) {
                    if (response && response.success) {
                        if (response.data && response.data.require_retry) {
                            // Server requested retry after deactivation
                            button.text('Retrying update...');
                            setTimeout(function(){ doUpdate(true); }, 400);
                            return;
                        }
                        alert('✅ ' + (response.data || pluginSlug + ' updated successfully!') + '\n\nPage will reload to show updated status.');
                        setTimeout(function() { location.reload(); }, 1200);
                    } else {
                        var message = 'Failed to update ' + pluginSlug + '.';
                        if (response && response.data) { message = response.data; }
                        else if (response && response.message) { message = response.message; }
                        alert('❌ ' + message);
                        button.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    alert('❌ An error occurred while updating ' + pluginSlug + '.');
                    button.prop('disabled', false).text(originalText);
                }
            });
        }
        doUpdate(false);
    }

    // Update MAC Menu button
    $(document).on('click', '.mac-core-update-mac-menu', function() {
        if (confirm('Are you sure you want to update MAC Menu? This will download the latest version from CRM.')) {
            const button = $(this);
            button.prop('disabled', true).text('Updating MAC Menu...');
            updatePlugin('mac-menu', button, 'Update MAC Menu');
        }
    });

    // Activate MAC Menu button
    $(document).on('click', '.mac-core-activate-mac-menu', function() {
        const button = $(this);
        button.prop('disabled', true).text('Activating MAC Menu...');
        $.ajax({
            url: macCoreAdmin.ajaxUrl,
            type: 'POST',
            data: { action: 'mac_core_activate_mac_menu', nonce: macCoreAdmin.nonce },
            success: function(response) {
                if (response.success) {
                    alert('✅ ' + response.data + '\n\nPage will reload to show updated status.');
                    setTimeout(function() { location.reload(); }, 2000);
                } else {
                    var message = 'Failed to activate MAC Menu.';
                    if (response && response.data && response.data.message) { message = response.data.message; }
                    else if (response && response.message) { message = response.message; }
                    alert('❌ ' + message);
                }
            },
            error: function() {
                alert('❌ An error occurred while activating MAC Menu.');
            },
            complete: function() {
                button.prop('disabled', false).text('Activate MAC Menu');
            }
        });
    });

    // Reset Options button
    $(document).on('click', '.mac-core-reset-options', function() {
        if (confirm('Are you sure you want to reset all MAC Menu options? This will clear all domain keys and status.')) {
            const button = $(this);
            button.prop('disabled', true).text('Resetting Options...');
            $.ajax({
                url: macCoreAdmin.ajaxUrl,
                type: 'POST',
                data: { action: 'mac_core_reset_options', nonce: macCoreAdmin.nonce },
                success: function(response) {
                    if (response.success) {
                        alert('✅ ' + response.data);
                        setTimeout(function() { location.reload(); }, 2000);
                    } else {
                        var message = 'Failed to reset options.';
                        if (response && response.data && response.data.message) { message = response.data.message; }
                        else if (response && response.message) { message = response.message; }
                        alert('❌ ' + message);
                    }
                },
                error: function() {
                    alert('❌ An error occurred while resetting options.');
                },
                complete: function() {
                    button.prop('disabled', false).text('Reset Options');
                }
            });
        }
    });

    // Restore MAC Core button
    $(document).on('click', '.mac-core-restore-mac-core', function() {
        if (confirm('Are you sure you want to restore MAC Core? This will restore the main MAC Core file if it was disabled.')) {
            const button = $(this);
            button.prop('disabled', true).text('Restoring MAC Core...');
            $.ajax({
                url: macCoreAdmin.ajaxUrl,
                type: 'POST',
                data: { action: 'mac_core_restore_mac_core', nonce: macCoreAdmin.nonce },
                success: function(response) {
                    if (response.success) {
                        alert('✅ ' + response.data);
                        setTimeout(function() { location.reload(); }, 2000);
                    } else {
                        var message = 'Failed to restore MAC Core.';
                        if (response && response.data && response.data.message) { message = response.data.message; }
                        else if (response && response.message) { message = response.message; }
                        alert('❌ ' + message);
                    }
                },
                error: function() {
                    alert('❌ An error occurred while restoring MAC Core.');
                },
                complete: function() {
                    button.prop('disabled', false).text('Restore MAC Core');
                }
            });
        }
    });

    // Force Delete removed: use WP's native plugin deletion UI
    
    // Check update MAC Core
    $(document).on('click', '.mac-core-check-update-mac-core', function() {
        const button = $(this);
        const statusDiv = $('#mac-core-self-update-status');
        const currentVersion = button.data('current-version') || 'Unknown';
        button.prop('disabled', true).text('Checking update...');
        statusDiv.html('<div class="notice notice-info"><p>🔄 Sending request...</p></div>').show();
        
        $.ajax({
            url: macCoreAdmin.ajaxUrl,
            type: 'POST',
            data: { action: 'mac_core_check_update_mac_core', nonce: macCoreAdmin.nonce },
            success: function(response) {
                if (response.success) {
                    const data = response.data || {};
                    if (data.needs_update) {
                        statusDiv.html('<div class="notice notice-success"><p>✅ Update available! Current: ' + currentVersion + ' → New: ' + data.version + '</p></div>').show();
                        $('.mac-core-update-mac-core').show();
                    } else {
                        statusDiv.html('<div class="notice notice-info"><p>ℹ️ No updates available. Current version: ' + currentVersion + '</p></div>').show();
                        $('.mac-core-update-mac-core').hide();
                    }
                } else {
                    var message = 'Failed to check for updates.';
                    if (response && response.data) { message = response.data; }
                    else if (response && response.message) { message = response.message; }
                    statusDiv.html('<div class="notice notice-error"><p>❌ ' + message + '</p></div>').show();
                }
            },
            error: function() {
                statusDiv.html('<div class="notice notice-error"><p>❌ An error occurred while checking MAC Core updates.</p></div>').show();
            },
            complete: function() {
                button.prop('disabled', false).text('Check update MAC Core');
            }
        });
    });

    // Update MAC Core button
    $(document).on('click', '.mac-core-update-mac-core', function() {
        if (confirm('Are you sure you want to update MAC Core? This will download the latest version from CRM.')) {
            const button = $(this);
            button.prop('disabled', true).text('Updating MAC Core...');
            
            $.ajax({
                url: macCoreAdmin.ajaxUrl,
                type: 'POST',
                data: { 
                    action: 'mac_core_update_mac_core', 
                    nonce: macCoreAdmin.nonce 
                },
                success: function(response) {
                    if (response && response.success) {
                        if (response.data && response.data.redirect) {
                            // Redirect to standalone update script
                            window.location.href = response.data.url;
                        } else {
                            alert('✅ ' + (response.data || 'MAC Core updated successfully!') + '\n\nPage will reload to show updated status.');
                            setTimeout(function() { location.reload(); }, 1200);
                        }
                    } else {
                        var message = 'Failed to update MAC Core.';
                        if (response && response.data) { message = response.data; }
                        else if (response && response.message) { message = response.message; }
                        alert('❌ ' + message);
                        button.prop('disabled', false).text('Update MAC Core');
                    }
                },
                error: function() {
                    alert('❌ An error occurred while updating MAC Core.');
                    button.prop('disabled', false).text('Update MAC Core');
                }
            });
        }
    });

    // Generic update plugin button handler
    $(document).on('click', '.mac-core-update-plugin', function() {
        const pluginSlug = $(this).data('plugin-slug');
        const pluginName = $(this).data('plugin-name') || pluginSlug;
        
        if (confirm('Are you sure you want to update ' + pluginName + '? This will download the latest version from CRM.')) {
            const button = $(this);
            const originalText = button.text();
            button.prop('disabled', true).text('Updating ' + pluginName + '...');
            updatePlugin(pluginSlug, button, originalText);
        }
    });

    // Generic check update button handler for addon plugins
    $(document).on('click', '.mac-core-check-update', function() {
        const button = $(this);
        const pluginSlug = button.data('plugin-slug');
        const currentVersion = button.data('current-version') || 'Unknown';
        const statusDiv = $('.mac-core-status-result[data-plugin-slug="' + pluginSlug + '"]');
        const updateButton = $('.mac-core-update-plugin[data-plugin-slug="' + pluginSlug + '"]');
        
        if (!pluginSlug) {
            alert('❌ Plugin slug not found.');
            return;
        }
        
        const originalText = button.text();
        button.prop('disabled', true).text('Checking...');
        statusDiv.html('<div class="notice notice-info"><p>🔄 Checking for updates...</p></div>').show();
        
        $.ajax({
            url: macCoreAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mac_core_check_update_plugin',
                plugin_slug: pluginSlug,
                nonce: macCoreAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data || {};
                    if (data.needs_update) {
                        statusDiv.html('<div class="notice notice-success"><p>✅ Update available! Current: ' + currentVersion + ' → New: ' + data.version + '</p></div>').show();
                        updateButton.show();
                    } else {
                        statusDiv.html('<div class="notice notice-info"><p>ℹ️ No updates available. Current version: ' + currentVersion + '</p></div>').show();
                        updateButton.hide();
                    }
                } else {
                    var message = 'Failed to check for updates.';
                    if (response && response.data) { message = response.data; }
                    else if (response && response.message) { message = response.message; }
                    statusDiv.html('<div class="notice notice-error"><p>❌ ' + message + '</p></div>').show();
                    updateButton.hide();
                }
            },
            error: function(xhr, status, error) {
                statusDiv.html('<div class="notice notice-error"><p>❌ AJAX Error: ' + error + '</p></div>').show();
                updateButton.hide();
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
});