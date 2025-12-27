var BKADMINPANEL = BKADMINPANEL || {};

(function($){
    "use strict";

	var $window = $(window);
	var $document = $(document);
    
    BKADMINPANEL.documentOnReady = {
        
		init: function(){
            BKADMINPANEL.documentOnReady.ajaxDemoImport();
            BKADMINPANEL.documentOnReady.customDemoImport();
        },
        ajaxDemoImport: function() {
            var $ajaxImportActions = $('.bk_importer_start');
            var $this;
                                    
            $ajaxImportActions.on('click', function(e) {
                e.preventDefault();
                if($(this).hasClass('tnm-off-click')) return;
                                
                $this = $(this);
                
                $ajaxImportActions.addClass('tnm-off-click');
                
                $this.closest('.bk-demo-item-inner').siblings('.bk-import-process-bar').css('width', '1%' );
                $this.closest('.bk-demo-item-inner').removeClass('demo-waiting');
                $this.closest('.bk-demo-item-inner').addClass('demo-importing');
                $this.siblings('.plugin-installing').show();
                
                import_others();
                
            });
            
            function import_others() {
                //var $this = $(this);
                var $thisDemo = $this.closest('.bk-demo-item').attr('data-demo-url'); // Lấy URL từ thuộc tính data
                //var $thisDemo = $this.closest('.bk-demo-item').attr('class').split(' ').pop();
                $.ajax({
        			type: "POST",
        			url: ajaxurl,
        			data: {
        				action          : 'bk_demo_import_others',
                        demo_url : $thisDemo
        			},
        			dataType: 'json'
        		}).success(function(data){
                    console.log(data);
                    if(data.error == 0) {
        				$this.closest('.bk-demo-item-inner').siblings('.bk-import-process-bar').css('width', '100%' );
                        $this.closest('.bk-demo-item-inner').removeClass('demo-importing');
                        $this.closest('.bk-demo-item-inner').addClass('demo-done');         
                        $ajaxImportActions.removeClass('tnm-off-click');               
        			}
        		});
            }
        },
        
        // Custom Demo Import Form Handler
        customDemoImport: function() {
            var fieldCounter = 0;
            var defaultPageNames = ['Home', 'About Us', 'Services', 'Gallery', 'Contact Us'];
            
            // Add page field function
            function addPageField() {
                fieldCounter++;
                var pageName = defaultPageNames[fieldCounter - 1] || 'Page ' + fieldCounter;
                var fieldHtml = `
                    <div class="page-field-row" data-field-id="${fieldCounter}">
                        <div class="page-field-header">
                            <div class="page-field-name">
                                <label for="page_name_${fieldCounter}">Page Name:</label>
                                <input type="text" id="page_name_${fieldCounter}" name="page_names[]" value="${pageName}" placeholder="Enter page name" class="page-name-input">
                            </div>
                            <div class="page-field-file">
                                <label for="page_file_${fieldCounter}">JSON File:</label>
                                <input type="file" id="page_file_${fieldCounter}" name="page_files[]" accept=".json" class="page-file-input">
                                <p class="description">Select a JSON file for this page template.</p>
                                <div class="file-validation" id="validation_${fieldCounter}" style="display: none;"></div>
                            </div>
                            <div class="page-field-actions">
                                <button type="button" class="button button-small remove-field-btn" data-field-id="${fieldCounter}">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                
                $('#page-fields-container').append(fieldHtml);
                
                // Show/hide remove button
                if (fieldCounter > 1) {
                    $('#remove-page-field').show();
                }
                
                // Handle file validation
                $('#page_file_' + fieldCounter).on('change', function() {
                    validateJsonFile(this, fieldCounter);
                });
                
                // Handle remove individual field
                $('.remove-field-btn[data-field-id="' + fieldCounter + '"]').on('click', function() {
                    var fieldId = $(this).data('field-id');
                    $('.page-field-row[data-field-id="' + fieldId + '"]').remove();
                    updateFieldNumbers();
                });
            }
            
            // Validate JSON file function
            function validateJsonFile(input, fieldId) {
                var file = input.files[0];
                var validationDiv = $('#validation_' + fieldId);
                
                if (!file) {
                    validationDiv.hide();
                    return;
                }
                
                // Check file extension
                if (!file.name.toLowerCase().endsWith('.json')) {
                    validationDiv.html('<div class="error-message">❌ Please select a JSON file.</div>').show();
                    return;
                }
                
                // Check file size (max 5MB)
                if (file.size > 5 * 1024 * 1024) {
                    validationDiv.html('<div class="error-message">❌ File size too large. Maximum 5MB allowed.</div>').show();
                    return;
                }
                
                // Validate JSON content
                var reader = new FileReader();
                reader.onload = function(e) {
                    try {
                        var content = e.target.result;
                        
                        // Check if content is empty
                        if (!content.trim()) {
                            validationDiv.html('<div class="error-message">❌ JSON file is empty.</div>').show();
                            return;
                        }
                        
                        // Try to parse JSON
                        var jsonData = JSON.parse(content);
                        
                        // Check if JSON is valid (accept multiple Elementor formats)
                        var isValidElementorTemplate = false;
                        var templateInfo = '';
                        
                        // Format 1: Array of elements (most common)
                        if (Array.isArray(jsonData)) {
                            isValidElementorTemplate = true;
                            templateInfo = 'Array format with ' + jsonData.length + ' elements';
                        }
                        // Format 2: Object with content property
                        else if (jsonData && typeof jsonData === 'object' && jsonData.content) {
                            isValidElementorTemplate = true;
                            templateInfo = 'Object format with content property';
                        }
                        // Format 3: Direct object with Elementor structure
                        else if (jsonData && typeof jsonData === 'object' && (jsonData.elements || jsonData.settings)) {
                            isValidElementorTemplate = true;
                            templateInfo = 'Direct Elementor object format';
                        }
                        // Format 4: Single element object
                        else if (jsonData && typeof jsonData === 'object' && jsonData.elType) {
                            isValidElementorTemplate = true;
                            templateInfo = 'Single Elementor element format';
                        }
                        
                        if (isValidElementorTemplate) {
                            validationDiv.html('<div class="success-message">✅ Valid Elementor JSON file. ' + templateInfo + '.</div>').show();
                        } else {
                            validationDiv.html('<div class="error-message">❌ File does not appear to be a valid Elementor template. Please check your export.</div>').show();
                        }
                        
                    } catch (error) {
                        validationDiv.html('<div class="error-message">❌ Invalid JSON format: ' + error.message + '</div>').show();
                    }
                };
                
                reader.onerror = function() {
                    validationDiv.html('<div class="error-message">❌ Error reading file.</div>').show();
                };
                
                reader.readAsText(file);
            }
            
            // Validate site-settings.json file
            function validateSiteSettingsFile(input) {
                var file = input.files[0];
                var validationDiv = $('#site-settings-validation');
                
                if (!file) {
                    validationDiv.hide();
                    return;
                }
                
                // Check file extension
                if (!file.name.toLowerCase().endsWith('.json')) {
                    validationDiv.html('<div class="error-message">❌ Please select a JSON file.</div>').show();
                    return;
                }
                
                // Check file size (max 2MB)
                if (file.size > 2 * 1024 * 1024) {
                    validationDiv.html('<div class="error-message">❌ File size too large. Maximum 2MB allowed.</div>').show();
                    return;
                }
                
                // Validate JSON content
                var reader = new FileReader();
                reader.onload = function(e) {
                    try {
                        var content = e.target.result;
                        
                        // Check if content is empty
                        if (!content.trim()) {
                            validationDiv.html('<div class="error-message">❌ JSON file is empty.</div>').show();
                            return;
                        }
                        
                        // Try to parse JSON
                        var jsonData = JSON.parse(content);
                        
                        // Check if it's a valid site-settings structure
                        var isValidSiteSettings = false;
                        var settingsInfo = '';
                        
                        // Check for site-settings structure
                        if (jsonData && typeof jsonData === 'object') {
                            if (jsonData.settings && jsonData.settings.system_colors) {
                                isValidSiteSettings = true;
                                settingsInfo = 'Site settings with ' + jsonData.settings.system_colors.length + ' system colors';
                            } else if (jsonData.system_colors || jsonData.global_colors) {
                                isValidSiteSettings = true;
                                settingsInfo = 'Site settings format detected';
                            }
                        }
                        
                        if (isValidSiteSettings) {
                            validationDiv.html('<div class="success-message">✅ Valid site-settings.json file. ' + settingsInfo + '.</div>').show();
                        } else {
                            validationDiv.html('<div class="error-message">❌ File does not appear to be a valid site-settings.json. Please check your export.</div>').show();
                        }
                        
                    } catch (error) {
                        validationDiv.html('<div class="error-message">❌ Invalid JSON format: ' + error.message + '</div>').show();
                    }
                };
                
                reader.onerror = function() {
                    validationDiv.html('<div class="error-message">❌ Error reading file.</div>').show();
                };
                
                reader.readAsText(file);
            }
            
            // Remove last page field function
            function removePageField() {
                if (fieldCounter > 1) {
                    $('.page-field-row:last').remove();
                    fieldCounter--;
                    
                    if (fieldCounter === 1) {
                        $('#remove-page-field').hide();
                    }
                }
            }
            
            // Update field numbers after removal
            function updateFieldNumbers() {
                fieldCounter = $('.page-field-row').length;
                if (fieldCounter === 1) {
                    $('#remove-page-field').hide();
                } else {
                    $('#remove-page-field').show();
                }
            }
            
            // Initialize with first field
            addPageField();
            
            // Handle add field button
            $('#add-page-field').on('click', function() {
                addPageField();
            });
            
            // Handle remove field button
            $('#remove-page-field').on('click', function() {
                removePageField();
            });
            
            // Handle site-settings file validation
            $('#site_settings_file').on('change', function() {
                validateSiteSettingsFile(this);
            });
            
            // Handle form submission
            $('#demo-import-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $submitBtn = $('#import-demo-btn');
                var $progress = $('#import-progress');
                var $results = $('#import-results');
                
                // Show loading state
                $submitBtn.prop('disabled', true);
                $submitBtn.find('.button-text').hide();
                $submitBtn.find('.button-loading').show();
                $progress.show();
                $results.hide();
                
                // Create FormData for file upload
                var formData = new FormData();
                formData.append('action', 'bk_custom_demo_import');
                formData.append('_wpnonce', $('#_wpnonce').val());
                
                // Always set these to true since we removed the checkboxes
                formData.append('import_pages', '1');
                formData.append('import_template_kit', '1');
                formData.append('create_pages', '1');
                formData.append('overwrite_existing', '0');
                
                // Always use minimal-kit as default
                formData.append('template_kit_select', 'minimal-kit');
                
                // Add site-settings file if exists
                var siteSettingsFile = $('#site_settings_file')[0].files[0];
                if (siteSettingsFile) {
                    formData.append('site_settings_file', siteSettingsFile);
                }
                
                // Collect page data
                var hasPageFiles = false;
                $('.page-field-row').each(function() {
                    var $row = $(this);
                    var pageName = $row.find('.page-name-input').val();
                    var pageFile = $row.find('.page-file-input')[0].files[0];
                    
                    if (pageName && pageFile) {
                        hasPageFiles = true;
                        formData.append('page_names[]', pageName);
                        formData.append('page_files[]', pageFile);
                    }
                });
                
                // Check if at least one option is selected (now minimal-kit is always selected)
                if (!hasPageFiles) {
                    // If no page files, we still have minimal-kit selected, so proceed
                }
                
                // Start import process
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        $submitBtn.prop('disabled', false);
                        $submitBtn.find('.button-text').show();
                        $submitBtn.find('.button-loading').hide();
                        $progress.hide();
                        $results.show();
                        
                        if (response.success) {
                            $('#results-content').html('<div class="success-message">' + response.data.message + '</div>');
                        } else {
                            $('#results-content').html('<div class="error-message">' + response.data.message + '</div>');
                        }
                    },
                    error: function() {
                        $submitBtn.prop('disabled', false);
                        $submitBtn.find('.button-text').show();
                        $submitBtn.find('.button-loading').hide();
                        $progress.hide();
                        $results.show();
                        
                        $('#results-content').html('<div class="error-message">An error occurred during import. Please try again.</div>');
                    }
                });
            });
            
            // Handle clear form button
            $('#clear-form-btn').on('click', function() {
                $('#demo-import-form')[0].reset();
                $('#page-fields-container').empty();
                fieldCounter = 0;
                addPageField();
                $('.kit-info').hide();
                $('#site-settings-validation').hide();
                $('#import-results').hide();
            });
            
            // Download Images functionality
            BKADMINPANEL.documentOnReady.initDownloadImages();
        },
        
        initDownloadImages: function() {
            // Enable/disable download button based on page selection
            $('#download-page-id').on('change', function() {
                var pageId = $(this).val();
                $('#download-images-btn').prop('disabled', !pageId);
            });
            
            // Trigger change event on page load để enable button nếu đã có value
            $('#download-page-id').trigger('change');
            
            // Check external images button
            $('#check-images-btn').on('click', function() {
                var pageId = $('#download-page-id').val();
                if (!pageId) {
                    alert('Please select a page first');
                    return;
                }
                
                BKADMINPANEL.documentOnReady.checkExternalImages(pageId);
            });
            
            // Download images button
            $('#download-images-btn').on('click', function() {
                var pageId = $('#download-page-id').val();
                if (!pageId) {
                    alert('Please select a page first');
                    return;
                }
                
                BKADMINPANEL.documentOnReady.downloadImages(pageId);
            });
        },
        
        checkExternalImages: function(pageId) {
            if (typeof ajaxurl === 'undefined') {
                alert('Error: ajaxurl is not defined');
                return;
            }
            
            if (typeof mac_ajax_nonce === 'undefined') {
                alert('Error: mac_ajax_nonce is not defined');
                return;
            }
            
            $('#download-progress').show();
            $('.progress-text').text(pageId === 'all' ? 'Checking all pages...' : 'Checking external images...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'check_external_images',
                    page_id: pageId,
                    nonce: mac_ajax_nonce
                },
                timeout: 60000, // 1 phút timeout
                success: function(response) {
                    $('#download-progress').hide();
                    $('#download-results').show();
                    
                    // Kiểm tra response hợp lệ
                    if (!response || typeof response !== 'object') {
                        $('#download-results-content').html('<div class="notice notice-error"><p>Lỗi: Response không hợp lệ</p></div>');
                        return;
                    }
                    
                    // Xử lý lỗi từ server
                    if (!response.success) {
                        var errorMsg = (response.data && response.data.message) ? response.data.message : 'Lỗi không xác định';
                        $('#download-results-content').html('<div class="notice notice-error"><p>' + errorMsg + '</p></div>');
                        return;
                    }
                    
                    var total = (response.data && response.data.total) ? response.data.total : 0;
                    var html = '<div class="notice notice-info"><p>';
                    if (pageId === 'all') {
                        html += 'Found <strong>' + total + '</strong> external images across all pages';
                    } else {
                        html += 'Found <strong>' + total + '</strong> external images';
                    }
                    if (total > 0) {
                        html += '<br>Click "Download All Images" to download them to your media library.';
                        $('#download-images-btn').prop('disabled', false);
                    } else {
                        html += '<br>No external images found.';
                        $('#download-images-btn').prop('disabled', true);
                    }
                    html += '</p></div>';
                    
                    $('#download-results-content').html(html);
                },
                error: function(xhr, status, error) {
                    $('#download-progress').hide();
                    $('#download-results').show();
                    
                    var errorMsg = 'Error checking images';
                    if (status === 'timeout') {
                        errorMsg = 'Request timeout - vui lòng thử lại.';
                    } else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMsg = xhr.responseJSON.data.message;
                    }
                    
                    console.error('Check images error:', {status: status, error: error, response: xhr.responseText});
                    $('#download-results-content').html('<div class="notice notice-error"><p>' + errorMsg + '</p></div>');
                }
            });
        },
        
        downloadImages: function(pageId) {
            if (typeof ajaxurl === 'undefined') {
                alert('Error: ajaxurl is not defined');
                return;
            }
            
            if (typeof mac_ajax_nonce === 'undefined') {
                alert('Error: mac_ajax_nonce is not defined');
                return;
            }
            
            var $btn = $('#download-images-btn');
            var $btnText = $btn.find('.button-text');
            var $btnLoading = $btn.find('.button-loading');
            
            // Show loading state
            $btn.prop('disabled', true);
            $btnText.hide();
            $btnLoading.show();
            $('#download-progress').show();
            $('.progress-text').text(pageId === 'all' ? 'Downloading images from all pages...' : 'Downloading images...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'download_external_images',
                    page_id: pageId,
                    nonce: mac_ajax_nonce
                },
                timeout: 600000, // 10 phút timeout cho việc download nhiều hình
                success: function(response) {
                    // Hide loading state
                    $btn.prop('disabled', false);
                    $btnText.show();
                    $btnLoading.hide();
                    $('#download-progress').hide();
                    $('#download-results').show();
                    
                    // Kiểm tra response có hợp lệ không
                    if (!response || typeof response !== 'object') {
                        $('#download-results-content').html('<div class="notice notice-error"><p>Lỗi: Response không hợp lệ</p></div>');
                        return;
                    }
                    
                    // Xử lý lỗi từ server
                    if (!response.success) {
                        var errorMsg = (response.data && response.data.message) ? response.data.message : 'Lỗi không xác định';
                        $('#download-results-content').html('<div class="notice notice-error"><p>' + errorMsg + '</p></div>');
                        return;
                    }
                    
                    var html = '<div class="notice notice-success"><p>';
                    if (pageId === 'all') {
                        html += '<strong>Download completed for all pages!</strong><br>';
                        html += 'Pages processed: <strong>' + (response.data.pages_processed || 0) + '</strong><br>';
                        html += 'Downloaded: <strong>' + (response.data.total_downloaded || 0) + '</strong> images<br>';
                        html += 'Failed: <strong>' + (response.data.total_failed || 0) + '</strong> images<br><br>';
                        if (response.data.results && response.data.results.length > 0) {
                            html += '<strong>Results by page:</strong><br>';
                            response.data.results.forEach(function(result) {
                                html += '• ' + result.page_title + ' (ID: ' + result.page_id + '): ';
                                html += result.downloaded + ' downloaded, ' + result.failed + ' failed<br>';
                            });
                        }
                    } else {
                        html += '<strong>Download completed!</strong><br>';
                        html += 'Downloaded: ' + (response.data.downloaded || 0) + ' images<br>';
                        html += 'Failed: ' + (response.data.failed || 0) + ' images<br>';
                        html += 'Total: ' + (response.data.total || 0) + ' images';
                    }
                    html += '</p></div>';
                    
                    $('#download-results-content').html(html);
                },
                error: function(xhr, status, error) {
                    // Hide loading state
                    $btn.prop('disabled', false);
                    $btnText.show();
                    $btnLoading.hide();
                    $('#download-progress').hide();
                    $('#download-results').show();
                    
                    var errorMsg = 'Error downloading images';
                    if (status === 'timeout') {
                        errorMsg = 'Request timeout - quá trình download quá lâu. Vui lòng thử lại với ít page hơn.';
                    } else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMsg = xhr.responseJSON.data.message;
                    } else if (error) {
                        errorMsg = 'Lỗi: ' + error;
                    }
                    
                    console.error('Download images error:', {status: status, error: error, response: xhr.responseText});
                    $('#download-results-content').html('<div class="notice notice-error"><p>' + errorMsg + '</p></div>');
                }
            });
        }
    }
    $document.ready( BKADMINPANEL.documentOnReady.init );
})(jQuery);