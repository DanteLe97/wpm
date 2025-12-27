jQuery(document).ready(function($) {
    // Tab switching
    $('.tab-button').on('click', function() {
        $('.tab-button').removeClass('active');
        $('.tab-content').removeClass('active');
        
        $(this).addClass('active');
        $('#' + $(this).data('tab') + '-tab').addClass('active');
        
        // Load status when switching to status tab
        if ($(this).data('tab') === 'status') {
            loadDomainStatus();
        }
    });
    
    // Validate form
    $('#mac-core-validate-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submit = $form.find('button[type="submit"]');
        var originalText = $submit.text();
        
        $submit.prop('disabled', true).text(macCoreDomain.i18n.validating);
        
        $.ajax({
            url: macCoreDomain.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mac_core_validate_domain',
                nonce: macCoreDomain.nonce,
                license_key: $('#validate-key').val(),
                plugin_slug: $('#validate-plugin').val()
            },
            success: function(response) {
                if (response.success) {
                    var message = macCoreDomain.i18n.success;
                    if (response && response.data && response.data.message) {
                        message = response.data.message;
                    } else if (response && response.message) {
                        message = response.message;
                    }
                    showMessage(message, 'success');
                    // Reload status if on status tab
                    if ($('#status-tab').hasClass('active')) {
                        loadDomainStatus();
                    }
                } else {
                    var message = macCoreDomain.i18n.error;
                    if (response && response.data && response.data.message) {
                        message = response.data.message;
                    } else if (response && response.message) {
                        message = response.message;
                    }
                    showMessage(message, 'error');
                }
            },
            error: function() {
                showMessage(macCoreDomain.i18n.error, 'error');
            },
            complete: function() {
                $submit.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Register form
    $('#mac-core-register-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submit = $form.find('button[type="submit"]');
        var originalText = $submit.text();
        
        $submit.prop('disabled', true).text(macCoreDomain.i18n.registering);
        
        $.ajax({
            url: macCoreDomain.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mac_core_register_domain',
                nonce: macCoreDomain.nonce,
                license_key: $('#register-key').val(),
                plugin_slug: $('#register-plugin').val()
            },
            success: function(response) {
                if (response.success) {
                    var message = macCoreDomain.i18n.success;
                    if (response && response.data && response.data.message) {
                        message = response.data.message;
                    } else if (response && response.message) {
                        message = response.message;
                    }
                    showMessage(message, 'success');
                    // Reload status if on status tab
                    if ($('#status-tab').hasClass('active')) {
                        loadDomainStatus();
                    }
                } else {
                    var message = macCoreDomain.i18n.error;
                    if (response && response.data && response.data.message) {
                        message = response.data.message;
                    } else if (response && response.message) {
                        message = response.message;
                    }
                    showMessage(message, 'error');
                }
            },
            error: function() {
                showMessage(macCoreDomain.i18n.error, 'error');
            },
            complete: function() {
                $submit.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Load domain status
    function loadDomainStatus() {
        $('#mac-core-domain-status').html('<p>' + macCoreDomain.i18n.loading + '</p>');
        
        $.ajax({
            url: macCoreDomain.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mac_core_get_domain_status',
                nonce: macCoreDomain.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#mac-core-domain-status').html(response.data.html);
                } else {
                    var message = macCoreDomain.i18n.error;
                    if (response && response.data && response.data.message) {
                        message = response.data.message;
                    } else if (response && response.message) {
                        message = response.message;
                    }
                    $('#mac-core-domain-status').html('<p class="error">' + message + '</p>');
                }
            },
            error: function() {
                $('#mac-core-domain-status').html('<p class="error">' + macCoreDomain.i18n.error + '</p>');
            }
        });
    }
    
    // Check status button
    $(document).on('click', '.check-status', function() {
        var $button = $(this);
        var pluginSlug = $button.data('plugin');
        var originalText = $button.text();
        
        $button.prop('disabled', true).text(macCoreDomain.i18n.checking);
        
        $.ajax({
            url: macCoreDomain.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mac_core_check_plugin_status',
                nonce: macCoreDomain.nonce,
                plugin_slug: pluginSlug
            },
            success: function(response) {
                if (response.success) {
                    var message = macCoreDomain.i18n.success;
                    if (response && response.data && response.data.message) {
                        message = response.data.message;
                    } else if (response && response.message) {
                        message = response.message;
                    }
                    showMessage(message, 'success');
                    loadDomainStatus();
                } else {
                    var message = macCoreDomain.i18n.error;
                    if (response && response.data && response.data.message) {
                        message = response.data.message;
                    } else if (response && response.message) {
                        message = response.message;
                    }
                    showMessage(message, 'error');
                }
            },
            error: function() {
                showMessage(macCoreDomain.i18n.error, 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Show message function
    function showMessage(message, type) {
        var $message = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap h1').after($message);
        
        // Auto dismiss after 5 seconds
        setTimeout(function() {
            $message.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Load status on page load if on status tab
    if ($('#status-tab').hasClass('active')) {
        loadDomainStatus();
    }
}); 