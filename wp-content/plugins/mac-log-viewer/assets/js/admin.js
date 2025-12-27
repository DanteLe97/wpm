jQuery(document).ready(function($) {
    'use strict';

    // Auto-refresh log content every 30 seconds
    var autoRefreshInterval;
    
    function startAutoRefresh() {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
        }
        
        autoRefreshInterval = setInterval(function() {
            refreshLogContent();
        }, 30000); // 30 seconds
    }
    
    function stopAutoRefresh() {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
            autoRefreshInterval = null;
        }
    }
    
    function refreshLogContent() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mac_log_viewer_refresh',
                nonce: $('#mac_log_viewer_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    $('.mac-log-viewer-content').html(response.data.content);
                }
            },
            error: function() {
                console.log('Failed to refresh log content');
            }
        });
    }
    
    // Toggle auto-refresh
    $('#toggle-auto-refresh').on('click', function() {
        var $button = $(this);
        
        if ($button.hasClass('active')) {
            stopAutoRefresh();
            $button.removeClass('active').text('Start Auto Refresh');
        } else {
            startAutoRefresh();
            $button.addClass('active').text('Stop Auto Refresh');
        }
    });
    
    // Clear log confirmation
    $('input[name="clear_log"]').on('click', function(e) {
        if (!confirm('Are you sure you want to clear the log file? This action cannot be undone.')) {
            e.preventDefault();
        }
    });
    
    // Copy log content to clipboard
    $('#copy-log-content').on('click', function() {
        var logContent = $('.mac-log-viewer-content').text();
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(logContent).then(function() {
                alert('Log content copied to clipboard!');
            });
        } else {
            // Fallback for older browsers
            var textArea = document.createElement('textarea');
            textArea.value = logContent;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            alert('Log content copied to clipboard!');
        }
    });
    
    // Search in log content
    $('#search-log').on('keyup', function() {
        var searchTerm = $(this).val().toLowerCase();
        var $logContent = $('.mac-log-viewer-content');
        
        if (searchTerm === '') {
            $logContent.find('span').removeClass('highlight');
            return;
        }
        
        $logContent.find('span').removeClass('highlight');
        $logContent.find('span:contains("' + searchTerm + '")').addClass('highlight');
    });
    
    // Initialize
    startAutoRefresh();
});
