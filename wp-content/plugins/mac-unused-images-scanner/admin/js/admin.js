/**
 * MAC Unused Images Scanner - Admin JavaScript
 */

(function($) {
    'use strict';
    
    const MacUIS = {
        
        init: function() {
            this.bindEvents();
            this.checkProgress();
        },
        
        bindEvents: function() {
            // Select all checkbox
            $('#mac-uis-select-all').on('change', function() {
                $('.mac-uis-item-checkbox').prop('checked', this.checked);
            });
            
            // Individual checkbox - update select all state
            $(document).on('change', '.mac-uis-item-checkbox', function() {
                const total = $('.mac-uis-item-checkbox').length;
                const checked = $('.mac-uis-item-checkbox:checked').length;
                $('#mac-uis-select-all').prop('checked', total === checked);
            });
            
            // Bulk delete form submission
            $('#mac-uis-bulk-delete-form').on('submit', function(e) {
                const action = $('#mac-uis-bulk-action').val();
                
                if (action === 'delete') {
                    const checked = $('.mac-uis-item-checkbox:checked').length;
                    
                    if (checked === 0) {
                        alert(macUIS.i18n.selectAtLeastOne);
                        e.preventDefault();
                        return false;
                    }
                    
                    const confirmed = confirm(
                        macUIS.i18n.confirmDelete + ' ' + checked + ' ' + macUIS.i18n.confirmDeleteSuffix
                    );
                    
                    if (!confirmed) {
                        e.preventDefault();
                        return false;
                    }
                }
            });
        },
        
        checkProgress: function() {
            const statusBox = $('#mac-uis-scan-status');
            
            if (statusBox.length === 0) {
                return;
            }
            
            $.ajax({
                url: macUIS.ajaxUrl,
                type: 'GET',
                data: {
                    action: 'mac_uis_check_progress'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'running') {
                        const html = '<p><strong>' + macUIS.i18n.processing + '</strong> ' +
                                   response.processed + ' / ' + response.total + 
                                   ' ảnh (' + response.percent + '%)</p>';
                        statusBox.html(html).addClass('loading');
                        
                        // Check again in 3 seconds
                        setTimeout(function() {
                            MacUIS.checkProgress();
                        }, 3000);
                    } else if (response.status === 'done') {
                        const html = '<p><strong>' + macUIS.i18n.completed + '</strong> ' + 
                                   macUIS.i18n.reloadMessage + '</p>';
                        statusBox.html(html).removeClass('loading').addClass('completed');
                    } else {
                        const html = '<p><em>' + macUIS.i18n.notStarted + '</em></p>';
                        statusBox.html(html).removeClass('loading completed');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('MAC UIS Progress Check Error:', error);
                    statusBox.html('<p class="error">Error checking progress</p>').addClass('error');
                }
            });
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        MacUIS.init();
    });
    
})(jQuery);

