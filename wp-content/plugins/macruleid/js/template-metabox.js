/**
 * Template ID Metabox JavaScript
 * Handles Template ID input validation, publish and reset buttons
 */

jQuery(document).ready(function($) {
    
    // Initialize shared handlers first
    TemplateSharedFunctions.initializeSharedHandlers();
    
    // Re-initialize handlers when page is loaded (for duplicate pages)
    $(window).on('load', function() {
        // Re-initialize shared handlers
        if (typeof TemplateSharedFunctions !== 'undefined') {
            TemplateSharedFunctions.initializeSharedHandlers();
        }
    });
    
    // Use shared function for validation messages
    var showValidationMessage = TemplateSharedFunctions.showValidationMessage;

    // Use shared function for adding edit button
    var addEditButton = TemplateSharedFunctions.addEditButton;

    // Use shared function for enabling editing
    var enableEditing = TemplateSharedFunctions.enableEditing;

    // Use shared function for updating button state
    var updateButtonState = TemplateSharedFunctions.updateButtonState;

    // Use shared function for validating template ID
    var validateTemplateId = TemplateSharedFunctions.validateTemplateId;
    
    // Reset template handler is now handled by shared functions
    
    // Publish template handler is now handled by shared functions
    
    // Xử lý nút Clear Cache
    $(document).on('click', '#clear-cache-btn', function(e) {
        e.preventDefault();

        var $button = $(this);
        var postId = $button.data('post-id');
        
        // Also try to get from templateMetaboxData
        if (!postId && typeof templateMetaboxData !== 'undefined') {
            postId = templateMetaboxData.post_id;
        }

        if (!postId || postId === 0) {
            alert('Không thể clear cache cho bài viết chưa được lưu.');
            return;
        }

        var originalText = $button.html();
        $button.prop('disabled', true)
              .html('<span class="dashicons dashicons-update-alt loading-spinner"></span> Đang xóa cache...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mac_clear_cache',
                post_id: postId,
                nonce: $('#custom_metabox_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    // Optionally reload page
                    // location.reload();
                } else {
                    alert('Có lỗi xảy ra: ' + (response.data.message || 'Unknown error'));
                }
                $button.prop('disabled', false).html(originalText);
            },
            error: function(xhr, status, error) {
                alert('Có lỗi khi kết nối với server. Vui lòng thử lại sau.');
                $button.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Xử lý nút Copy Meta from Home - sử dụng event delegation để đảm bảo hoạt động
    $(document).on('click', '#copy-meta-btn', function(e) {
        e.preventDefault();

        var $button = $(this);
        var postId = $button.data('post-id');
        var nonce = $button.data('nonce');
        
        // Also try to get from templateMetaboxData
        if (!postId && typeof templateMetaboxData !== 'undefined') {
            postId = templateMetaboxData.post_id;
        }

        if (!postId || postId === 0) {
            alert('Không thể copy meta cho bài viết chưa được lưu.');
            return;
        }

        // Confirm before copying
        if (!confirm('Bạn có chắc chắn muốn copy meta data từ page "home" tương ứng? Dữ liệu hiện tại sẽ bị ghi đè.')) {
            return;
        }

        var originalText = $button.html();
        $button.prop('disabled', true)
              .html('<span class="dashicons dashicons-update-alt loading-spinner"></span> Đang copy...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mac_copy_meta_from_home',
                post_id: postId,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message + '\nTừ page: ' + response.data.home_page_title);
                    // Reload page to show updated data
                    location.reload();
                } else {
                    // Show debug message in a more readable format
                    var errorMsg = response.data.message || 'Unknown error';
                    if (errorMsg.includes('<br>')) {
                        // If it's HTML debug message, show in a more readable format
                        var cleanMsg = errorMsg.replace(/<br>/g, '\n').replace(/<[^>]*>/g, '');
                        alert('Có lỗi xảy ra:\n\n' + cleanMsg);
                    } else {
                        alert('Có lỗi xảy ra: ' + errorMsg);
                    }
                    // Still reload page even if there's an error, as data might have been copied
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                }
                $button.prop('disabled', false).html(originalText);
            },
            error: function(xhr, status, error) {
                alert('Có lỗi khi kết nối với server. Vui lòng thử lại sau.');
                $button.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Input validation handler is now handled by shared functions
    
    // Use shared function for checking initial state
    var checkInitialState = TemplateSharedFunctions.checkInitialState;

    // Khởi tạo khi DOM ready
    function initialize() {
        if ($('#mac-custom-publish-btn').length > 0) {
            addEditButton();
            
            // Delay một chút để đảm bảo tất cả elements đã load
            setTimeout(function() {
                checkInitialState();
            }, 100);
        } else {
            setTimeout(initialize, 1000);
        }
    }
    
    // Start initialization
    initialize();
    
    // Also try on window load as backup
    $(window).on('load', function() {
        if ($('#mac-custom-publish-btn').length > 0 && $('#template-edit-btn').length === 0) {
            addEditButton();
            checkInitialState();
        }
    });
    
    // Re-initialize on page show (for duplicate pages)
    $(document).on('page:show', function() {
        setTimeout(function() {
            if ($('#mac-custom-publish-btn').length > 0 && $('#template-edit-btn').length === 0) {
                addEditButton();
                checkInitialState();
            }
        }, 500);
    });
    
    // Check for copy meta button and ensure it works
    function ensureCopyMetaButtonWorks() {
        if ($('#copy-meta-btn').length > 0) {
            // Button exists, check if it has event handlers
            var $btn = $('#copy-meta-btn');
            if (!$btn.data('events')) {
                // Re-bind the event
                $btn.off('click').on('click', function(e) {
                    // This will be handled by the document delegation above
                });
            }
        }
    }
    
    // Check every 2 seconds for the button
    setInterval(ensureCopyMetaButtonWorks, 2000);
}); 