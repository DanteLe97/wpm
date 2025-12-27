/**
 * Template Library & Preview System JavaScript
 * Handles Template List Display and Preview Generation
 * (Metabox functionality moved to template-metabox.js)
 */

// Test if jQuery is available and wrap everything in conditional
if (typeof jQuery === 'undefined') {
    // jQuery not available, skip initialization
} else {

jQuery(document).ready(function($) {
    
    // ==========================================================================
    // IMMEDIATE LOOP PREVENTION
    // ==========================================================================
    
    // Prevent infinite loops immediately
    let loopPreventionInitialized = false;
    
    function preventInfiniteLoops() {
        if (loopPreventionInitialized) {
            return;
        }
        loopPreventionInitialized = true;
        
        // Override location methods to prevent infinite reloads
        const originalReload = window.location.reload;
        const originalAssign = window.location.assign;
        const originalReplace = window.location.replace;
        
        let reloadCount = 0;
        const maxReloads = 2;
        
        window.location.reload = function(forceReload) {
            reloadCount++;
            if (reloadCount > maxReloads) {
                console.warn('Preventing infinite reload loop');
                return;
            }
            return originalReload.call(this, forceReload);
        };
        
        window.location.assign = function(url) {
            console.warn('Location.assign prevented to avoid loop');
            return;
        };
        
        window.location.replace = function(url) {
            console.warn('Location.replace prevented to avoid loop');
            return;
        };
        
        // Override console methods to prevent spam
        const originalConsoleError = console.error;
        const originalConsoleWarn = console.warn;
        const originalConsoleLog = console.log;
        
        let errorCount = 0;
        let warnCount = 0;
        let logCount = 0;
        const maxMessages = 5;
        
        console.error = function(...args) {
            errorCount++;
            if (errorCount > maxMessages) {
                return;
            }
            originalConsoleError.apply(console, args);
        };
        
        console.warn = function(...args) {
            warnCount++;
            if (warnCount > maxMessages) {
                return;
            }
            originalConsoleWarn.apply(console, args);
        };
        
        console.log = function(...args) {
            logCount++;
            if (logCount > maxMessages) {
                return;
            }
            originalConsoleLog.apply(console, args);
        };
        
        console.log('Loop prevention initialized');
    }
    
    // Run loop prevention immediately
    preventInfiniteLoops();
    
    // ==========================================================================
    // IMMEDIATE SCRIPT CLEANUP TO PREVENT LOOPS
    // ==========================================================================
    
    // Immediately remove problematic scripts that cause loops
    function cleanupProblematicScripts() {
        // Remove Facebook SDK scripts
        $('script[src*="facebook"]').remove();
        $('script[src*="fb.com"]').remove();
        $('script[src*="connect.facebook.net"]').remove();
        $('script[src*="1HAPyGK5af1.js"]').remove();
        
        // Remove jQuery Migrate scripts
        $('script[src*="jquery-migrate"]').remove();
        $('script[src*="migrate"]').remove();
        
        // Remove Facebook elements
        $('[class*="fb-"]').remove();
        $('[id*="fb-"]').remove();
        $('[data-href*="facebook.com"]').remove();
        
        // Disable Facebook Pixel
        if (typeof fbq !== 'undefined') {
            fbq = function() { return; };
        }
        
        // Disable Facebook SDK
        if (typeof FB !== 'undefined') {
            FB = {
                Event: { unsubscribe: function() { return; } },
                init: function() { return; },
                api: function() { return; }
            };
        }
        
        // Disable all external scripts that might cause loops
        const problematicScripts = [
            'facebook', 'fb.com', 'connect.facebook.net', '1HAPyGK5af1.js',
            'jquery-migrate', 'migrate', 'html2canvas', 'html2canvas.min.js',
            'content.js', 'content-loader.js'
        ];
        
        problematicScripts.forEach(script => {
            $(`script[src*="${script}"]`).remove();
        });
        
        // Disable all problematic elements
        const problematicElements = [
            '[class*="fb-"]', '[id*="fb-"]', '[data-href*="facebook.com"]',
            '[class*="jquery"]', '[id*="jquery"]'
        ];
        
        problematicElements.forEach(selector => {
            $(selector).remove();
        });
        
        // Disable html2canvas initially - only enable when needed
        if (typeof html2canvas !== 'undefined') {
            const originalHtml2Canvas = html2canvas;
            window.html2canvasOriginal = originalHtml2Canvas; // Store original for later use
            html2canvas = function(element, options) {
                return Promise.reject(new Error('html2canvas is disabled - only available during preview generation'));
            };
        }
        
        // Disable all external event listeners that might cause loops
        if (typeof window.addEventListener === 'function') {
            const originalAddEventListener = window.addEventListener;
            window.addEventListener = function(type, listener, options) {
                // Block problematic event types
                if (type === 'error' || type === 'unhandledrejection') {
                    return;
                }
                return originalAddEventListener.call(this, type, listener, options);
            };
        }
        
        console.log('Problematic scripts cleaned up to prevent loops');
    }
    
    // Run cleanup immediately
    cleanupProblematicScripts();
    
    // ==========================================================================
    // ERROR MONITORING AND LOOP PREVENTION
    // ==========================================================================
    
    // Monitor for continuous errors and prevent loops
    function setupErrorMonitoring() {
        let errorCount = 0;
        const maxErrors = 10;
        const errorWindow = 5000; // 5 seconds
        
        // Override console.error to track errors
        const originalConsoleError = console.error;
        console.error = function(...args) {
            errorCount++;
            
            // If too many errors in short time, prevent further execution
            if (errorCount > maxErrors) {
                console.warn('Too many errors detected, preventing further execution');
                return;
            }
            
            // Reset error count after window
            setTimeout(() => {
                errorCount = Math.max(0, errorCount - 1);
            }, errorWindow);
            
            // Call original console.error
            originalConsoleError.apply(console, args);
        };
        
        // Monitor for continuous console messages that indicate loops
        let messageCount = 0;
        const maxMessages = 20;
        const messageWindow = 3000; // 3 seconds
        
        const originalConsoleLog = console.log;
        console.log = function(...args) {
            const message = args.join(' ');
            
            // Check for repetitive messages that indicate loops
            if (message.includes('JQMIGRATE') || 
                message.includes('content.js loaded') || 
                message.includes('ErrorUtils caught an error')) {
                messageCount++;
                
                if (messageCount > maxMessages) {
                    console.warn('Too many repetitive messages detected, preventing further logging');
                    return;
                }
            }
            
            // Reset message count after window
            setTimeout(() => {
                messageCount = Math.max(0, messageCount - 1);
            }, messageWindow);
            
            // Call original console.log
            originalConsoleLog.apply(console, args);
        };
    }
    
    // Setup error monitoring
    setupErrorMonitoring();
    
    // ==========================================================================
    // FACEBOOK SDK ERROR PREVENTION
    // ==========================================================================
    
    // Prevent Facebook SDK infinite loops and errors
    function preventFacebookSDKErrors() {
        // Prevent multiple initializations
        if (window.facebookSDKPrevented) {
            return;
        }
        window.facebookSDKPrevented = true;
        
        // Override console.error to catch Facebook SDK errors
        const originalConsoleError = console.error;
        console.error = function(...args) {
            const message = args.join(' ');
            
            // Check if it's a Facebook SDK error
            if (message.includes('Could not find element') || 
                message.includes('ErrorUtils caught an error') ||
                message.includes('u_1_m_wU') ||
                message.includes('fburl.com/debugjs') ||
                message.includes('1HAPyGK5af1.js')) {
                
                // Log but don't throw error to prevent infinite loops
                console.warn('Facebook SDK Error prevented:', message);
                return;
            }
            
            // Call original console.error for other errors
            originalConsoleError.apply(console, args);
        };
        
        // Prevent Facebook SDK from causing infinite reloads
        if (typeof window !== 'undefined') {
            // Override location.reload to prevent infinite loops
            const originalReload = window.location.reload;
            window.location.reload = function(forceReload) {
                // Check if we're in a potential infinite loop
                const reloadCount = sessionStorage.getItem('reloadCount') || 0;
                if (reloadCount > 3) {
                    console.warn('Preventing infinite reload loop');
                    sessionStorage.removeItem('reloadCount');
                    return;
                }
                
                sessionStorage.setItem('reloadCount', parseInt(reloadCount) + 1);
                originalReload.call(this, forceReload);
            };
        }
        
        // Disable Facebook SDK if it's causing issues
        if (typeof FB !== 'undefined') {
            try {
                FB.Event.unsubscribe('xfbml.render');
                FB.Event.unsubscribe('auth.login');
                FB.Event.unsubscribe('auth.logout');
                FB.Event.unsubscribe('auth.statusChange');
                console.log('Facebook SDK events unsubscribed to prevent errors');
            } catch (e) {
                console.warn('Could not unsubscribe Facebook SDK events');
            }
        }
        
        // Remove Facebook scripts and elements immediately
        setTimeout(function() {
            $('script[src*="facebook"]').remove();
            $('script[src*="fb.com"]').remove();
            $('script[src*="connect.facebook.net"]').remove();
            $('[class*="fb-"]').remove();
            $('[id*="fb-"]').remove();
            $('[data-href*="facebook.com"]').remove();
        }, 100);
    }
    
    // Run Facebook SDK error prevention
    preventFacebookSDKErrors();
    
    // ==========================================================================
    // ELEMENTOR ERROR PREVENTION
    // ==========================================================================
    
    // Prevent Elementor from causing infinite loops
    function preventElementorErrors() {
        // Override Elementor's error handling
        if (typeof elementorFrontend !== 'undefined') {
            const originalInit = elementorFrontend.init;
            elementorFrontend.init = function() {
                try {
                    return originalInit.call(this);
                } catch (error) {
                    console.warn('Elementor initialization error prevented:', error.message);
                    return false;
                }
            };
        }
        
        // Prevent Elementor widgets from causing errors
        $(document).on('elementor/frontend/init', function() {
            if (typeof elementorFrontend !== 'undefined') {
                elementorFrontend.hooks.addAction('frontend/element_ready/global', function($element) {
                    try {
                        // Safe element initialization
                        $element.find('[data-elementor-id]').each(function() {
                            const $this = $(this);
                            if (!$this.data('initialized')) {
                                $this.data('initialized', true);
                            }
                        });
                    } catch (error) {
                        console.warn('Elementor element initialization error prevented:', error.message);
                    }
                });
            }
        });
    }
    
    // Run Elementor error prevention
    preventElementorErrors();
    
    // ==========================================================================
    // PAGE RELOAD PREVENTION
    // ==========================================================================
    
    // Prevent unwanted page reloads
    function preventUnwantedReloads() {
        // Prevent multiple initializations
        if (window.reloadPreventionInitialized) {
            return;
        }
        window.reloadPreventionInitialized = true;
        
        let reloadAttempts = 0;
        const maxReloadAttempts = 3;
        
        // Monitor for rapid reloads using sessionStorage for persistence
        const originalReload = window.location.reload;
        window.location.reload = function(forceReload) {
            const currentAttempts = parseInt(sessionStorage.getItem('reloadAttempts') || '0');
            
            if (currentAttempts > maxReloadAttempts) {
                console.warn('Too many reload attempts detected, preventing reload');
                sessionStorage.removeItem('reloadAttempts');
                return;
            }
            
            sessionStorage.setItem('reloadAttempts', currentAttempts + 1);
            
            // Reset counter after 10 seconds
            setTimeout(() => {
                sessionStorage.removeItem('reloadAttempts');
            }, 10000);
            
            return originalReload.call(this, forceReload);
        };
        
        // Prevent automatic form submissions that might cause reloads
        $(document).on('submit', 'form', function(e) {
            const $form = $(this);
            if ($form.data('prevent-reload')) {
                e.preventDefault();
                console.warn('Form submission prevented to avoid reload');
                return false;
            }
        });
        
        // Prevent any script-initiated reloads
        const originalLocationAssign = window.location.assign;
        window.location.assign = function(url) {
            console.warn('Location.assign prevented to avoid reload loop');
            return;
        };
        
        const originalLocationReplace = window.location.replace;
        window.location.replace = function(url) {
            console.warn('Location.replace prevented to avoid reload loop');
            return;
        };
    }
    
    // Run reload prevention
    preventUnwantedReloads();
    
    // ==========================================================================
    // JQUERY MIGRATE HANDLING
    // ==========================================================================
    
    // Handle jQuery Migrate warnings (prevent infinite loops)
    function handleJQueryMigrate() {
        // Prevent multiple initializations
        if (window.jQueryMigrateHandled) {
            return;
        }
        window.jQueryMigrateHandled = true;
        
        // Prevent infinite jQuery Migrate loops
        let migrateLogCount = 0;
        const maxMigrateLogs = 3;
        
        // Override console.warn to prevent infinite jQuery Migrate messages
        const originalWarn = console.warn;
        console.warn = function(...args) {
            const message = args.join(' ');
            
            // Check if it's a jQuery Migrate message
            if (message.includes('JQMIGRATE') || message.includes('jQuery Migrate')) {
                migrateLogCount++;
                
                // Only log the first few times, then suppress
                if (migrateLogCount <= maxMigrateLogs) {
                    originalWarn.apply(console, args);
                }
                return;
            }
            
            // For other warnings, log normally
            originalWarn.apply(console, args);
        };
        
        // Suppress jQuery Migrate if it's causing loops
        if (typeof jQuery !== 'undefined') {
            if (typeof jQuery.migrateMute === 'function') {
                jQuery.migrateMute = true;
            }
            
            // Disable jQuery Migrate completely if needed
            if (typeof jQuery.migrateTrace === 'function') {
                jQuery.migrateTrace = false;
            }
            
            // Set global flags to prevent jQuery Migrate from loading
            window.jQueryMigrateMute = true;
            window.jQueryMigrateTrace = false;
        }
        
        // Remove jQuery Migrate scripts immediately
        setTimeout(function() {
            $('script[src*="jquery-migrate"]').remove();
            $('script[src*="migrate"]').remove();
        }, 100);
    }
    
    // Run jQuery Migrate handling
    handleJQueryMigrate();
    
    // Check if we're on template library page
    const isElementorLibraryPage = $('body').hasClass('post-type-elementor_library') && $('.wp-list-table').length > 0;
    
    // Initialize shared handlers first
    TemplateSharedFunctions.initializeSharedHandlers();
    
    // Kiểm tra nếu đã có template ID thì disable các nút
    function checkTemplateId() {
        var templateId = $('#custom_template_id').val();
        var elementorData = $('input[name="_elementor_data"]').val();
        
        if (templateId && elementorData) {
            $('#custom_template_id').prop('disabled', true);
            $('#mac-custom-publish-btn').prop('disabled', true);
            
            // Thêm thông báo
            if (!$('.template-id-notice').length) {
                $('<div class="template-id-notice" style="color: #856404; background-color: #fff3cd; border: 1px solid #ffeeba; padding: 10px; margin-top: 10px; border-radius: 4px;">')
                    .text('Template ID đã được áp dụng. Để thay đổi, vui lòng xóa dữ liệu Elementor trước.')
                    .insertAfter('#custom_template_id');
            }
        }
    }
    
    // Chạy kiểm tra khi trang load
    checkTemplateId();
    
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
    
    // Input validation handler is now handled by shared functions
    
    // Use shared function for checking initial state
    var checkInitialState = TemplateSharedFunctions.checkInitialState;

    // Khởi tạo
    $(window).on('load', function() {
        addEditButton();
        checkInitialState();
    });
    
    // ==========================================================================
    // ELEMENTOR INTEGRATION
    // ==========================================================================
    
    // Handle Elementor template library interactions
    const $templateList = $('.elementor-template-library-list');
    
    if ($templateList.length) {
        $templateList.on('click', '.elementor-template-library-template-insert', function(e) {
            const templateId = $(this).data('template-id');
            if (templateId) {
                $.post(template_ajax.ajax_url, {
                    action: 'update_template_category',
                    template_id: templateId,
                    nonce: template_ajax.nonce
                });
            }
        });
    }
    
    // ==========================================================================
    // TEMPLATE PREVIEW SYSTEM
    // ==========================================================================
    
    /**
     * Function to capture template preview using html2canvas with retry mechanism
     */
    function captureTemplatePreview(container, onComplete, retryCount = 0) {
        const templateId = container.data('template-id');
        const loadingDiv = container.find('.template-preview-loading');
        const imageDiv = container.find('.template-preview-image');
        const maxRetries = 2; // Tối đa 3 lần thử (0, 1, 2)

        // Add loading class to container
        container.addClass('loading');
        
        // Ẩn image, hiện loading
        imageDiv.hide();
        loadingDiv.show();

        // Hiển thị thông tin retry nếu có
        if (retryCount > 0) {
            loadingDiv.text(`Đang tạo preview... (thử lại lần ${retryCount})`);
        }

        // Get template URL via Ajax
        $.post(templatePreviewAjax.ajax_url, {
            action: 'get_template_preview',
            template_id: templateId,
            nonce: templatePreviewAjax.nonce
        }, function(response) {
            if (response.success) {
                // Create an iframe to load the template
                const iframe = $('<iframe>', {
                    src: response.data.template_url,
                    style: 'position: absolute; left: -9999px; top: -9999px; width: 800px; height: 600px;'
                }).appendTo('body');

                // Timeout cho iframe load (120 giây - tăng thời gian)
                const iframeTimeout = setTimeout(function() {
                    container.removeClass('loading');
                    loadingDiv.text('Timeout load template (120s)').show();
                    imageDiv.hide();
                    iframe.remove();
                    
                    // Retry logic
                    if (retryCount < maxRetries) {
                        setTimeout(function() {
                            captureTemplatePreview(container, onComplete, retryCount + 1);
                        }, 2000); // Đợi 2 giây trước khi thử lại
                    } else {
                        // Call completion callback on final timeout
                        if (onComplete) onComplete(false); // Error
                    }
                }, 120000); // 120 giây

                // Wait for iframe to load
                iframe.on('load', function() {
                    clearTimeout(iframeTimeout); // Clear timeout khi load thành công
                    
                    try {
                        const iframeDoc = iframe[0].contentDocument || iframe[0].contentWindow.document;
                        
                        // Wait for Elementor to initialize
                        setTimeout(function() {
                            const templateElement = iframeDoc.querySelector(`[data-elementor-id="${templateId}"]`);
                            
                            if (templateElement) {
                                // Chỉ giới hạn width 700px, height auto (không giới hạn height)
                                const maxWidth = 700;
                                const elementWidth = templateElement.scrollWidth;
                                const elementHeight = templateElement.scrollHeight;

                                // Tính tỷ lệ scale chỉ dựa trên width, không giới hạn height
                                const finalScale = Math.min(maxWidth / elementWidth, 0.5); // Tăng lên 50% để ảnh sắc nét hơn

                                // Timeout cho html2canvas (600 giây = 10 phút cho mỗi page - tăng thời gian)
                                const canvasTimeout = setTimeout(function() {
                                    container.removeClass('loading');
                                    loadingDiv.text('Timeout chụp preview (600s)').show();
                                    imageDiv.hide();
                                    iframe.remove();
                                    
                                    // Retry logic
                                    if (retryCount < maxRetries) {
                                        setTimeout(function() {
                                            captureTemplatePreview(container, onComplete, retryCount + 1);
                                        }, 2000); // Đợi 2 giây trước khi thử lại
                                    } else {
                                        // Call completion callback on final timeout
                                        if (onComplete) onComplete(false); // Error
                                    }
                                }, 600000); // 600 giây = 10 phút

                                // Enable html2canvas for this capture
                                if (!enableHtml2Canvas()) {
                                    throw new Error('Failed to enable HTML2Canvas');
                                }
                                
                                // Use html2canvas to capture the template với kích thước tối ưu và cơ chế bảo vệ
                                html2canvas(templateElement, {
                                    scale: finalScale,
                                    useCORS: true,
                                    allowTaint: true,
                                    backgroundColor: '#ffffff',
                                    logging: false,
                                    width: elementWidth,
                                    height: elementHeight, // Giữ nguyên height tự nhiên
                                    timeout: 30000, // 30 giây timeout
                                    maxRetries: 1, // Chỉ retry 1 lần
                                    onclone: function(clonedDoc) {
                                        // Ensure all images are loaded in the cloned document
                                        const images = clonedDoc.getElementsByTagName('img');
                                        for (let i = 0; i < images.length; i++) {
                                            if (!images[i].complete) {
                                                images[i].src = images[i].src;
                                            }
                                        }
                                        
                                        // Remove any problematic elements that might cause loops
                                        const problematicElements = clonedDoc.querySelectorAll('[class*="fb-"], [id*="fb-"], [data-href*="facebook.com"]');
                                        problematicElements.forEach(el => el.remove());
                                    }
                                }).then(function(canvas) {
                                    clearTimeout(canvasTimeout); // Clear timeout khi chụp thành công
                                    
                                    try {
                                        // Nén canvas chỉ giới hạn width 700px, height auto
                                        const maxWidth = 700;
                                        
                                        // Tạo canvas mới với kích thước nhỏ hơn
                                        const resizedCanvas = document.createElement('canvas');
                                        const ctx = resizedCanvas.getContext('2d');
                                        
                                        // Tính tỷ lệ scale chỉ dựa trên width, giữ nguyên tỷ lệ height
                                        const ratio = maxWidth / canvas.width;
                                        resizedCanvas.width = canvas.width * ratio;
                                        resizedCanvas.height = canvas.height * ratio; // Height tự động theo tỷ lệ
                                        
                                        // Vẽ lại với kích thước mới
                                        ctx.drawImage(canvas, 0, 0, resizedCanvas.width, resizedCanvas.height);
                                        
                                        // Chuyển thành data URL với chất lượng cao nhất
                                        const previewData = resizedCanvas.toDataURL('image/jpeg', 1.0);
                                        
                                        // Timeout cho AJAX save (600 giây = 10 phút cho mỗi page - tăng thời gian)
                                        const saveTimeout = setTimeout(function() {
                                            container.removeClass('loading');
                                            loadingDiv.text('Timeout lưu preview (600s)').show();
                                            imageDiv.hide();
                                            iframe.remove();
                                            
                                            // Retry logic
                                            if (retryCount < maxRetries) {
                                                setTimeout(function() {
                                                    captureTemplatePreview(container, onComplete, retryCount + 1);
                                                }, 2000); // Đợi 2 giây trước khi thử lại
                                            } else {
                                                // Call completion callback on final timeout
                                                if (onComplete) onComplete(false); // Error
                                            }
                                        }, 600000); // 600 giây = 10 phút
                                        
                                        // Save the preview to database
                                        $.ajax({
                                            url: templatePreviewAjax.ajax_url,
                                            type: 'POST',
                                            data: {
                                                action: 'save_template_preview',
                                                template_id: templateId,
                                                preview_data: previewData,
                                                nonce: templatePreviewAjax.nonce
                                            },
                                            success: function(saveResponse) {
                                                                                    clearTimeout(saveTimeout); // Clear timeout khi save thành công
                                    
                                    if (saveResponse.success && saveResponse.data && saveResponse.data.preview_url) {
                                        // Update preview image with the new URL
                                        imageDiv.html('<img src="' + saveResponse.data.preview_url + '" style="width: 100%; height: auto; object-fit: contain; display: block;" alt="Template Preview">');
                                        
                                        // Ẩn loading, hiện image
                                        container.removeClass('loading');
                                        loadingDiv.hide();
                                        imageDiv.show();
                                        
                                        // Disable html2canvas after successful capture
                                        disableHtml2Canvas();
                                        
                                        // Call completion callback if provided
                                        if (onComplete) onComplete(true); // Success
                                    } else {
                                        container.removeClass('loading');
                                        loadingDiv.text('Lỗi lưu preview').show();
                                        
                                        // Disable html2canvas after failed capture
                                        disableHtml2Canvas();
                                        
                                        // Retry logic
                                        if (retryCount < maxRetries) {
                                            setTimeout(function() {
                                                captureTemplatePreview(container, onComplete, retryCount + 1);
                                            }, 2000); // Đợi 2 giây trước khi thử lại
                                        } else {
                                                                                                            // Call completion callback even on error
                                            if (onComplete) onComplete(false); // Error
                                            }
                                        }
                                    },
                                            error: function(xhr, status, error) {
                                                clearTimeout(saveTimeout); // Clear timeout khi có lỗi
                                                
                                                container.removeClass('loading');
                                                loadingDiv.text('Lỗi lưu preview').show();
                                                imageDiv.hide(); // Đảm bảo ẩn image khi có lỗi
                                                
                                                // Disable html2canvas after error
                                                disableHtml2Canvas();
                                                
                                                // Retry logic
                                                if (retryCount < maxRetries) {
                                                    setTimeout(function() {
                                                        captureTemplatePreview(container, onComplete, retryCount + 1);
                                                    }, 2000); // Đợi 2 giây trước khi thử lại
                                                } else {
                                                    // Call completion callback on error
                                                    if (onComplete) onComplete(false); // Error
                                                }
                                            }
                                        });
                                    } catch (error) {
                                        clearTimeout(canvasTimeout); // Clear timeout khi có lỗi
                                        
                                        container.removeClass('loading');
                                        loadingDiv.text('Lỗi xử lý preview').show();
                                        imageDiv.hide(); // Đảm bảo ẩn image khi có lỗi
                                        
                                        // Disable html2canvas after error
                                        disableHtml2Canvas();
                                        
                                        // Retry logic
                                        if (retryCount < maxRetries) {
                                            setTimeout(function() {
                                                captureTemplatePreview(container, onComplete, retryCount + 1);
                                            }, 2000); // Đợi 2 giây trước khi thử lại
                                        } else {
                                            // Call completion callback on error
                                            if (onComplete) onComplete(false); // Error
                                        }
                                    }
                                }).catch(function(error) {
                                    clearTimeout(canvasTimeout); // Clear timeout khi có lỗi
                                    
                                    container.removeClass('loading');
                                    loadingDiv.text('Lỗi chụp preview').show();
                                    imageDiv.hide(); // Đảm bảo ẩn image khi có lỗi
                                    
                                    // Disable html2canvas after error
                                    disableHtml2Canvas();
                                    
                                    // Retry logic
                                    if (retryCount < maxRetries) {
                                        setTimeout(function() {
                                            captureTemplatePreview(container, onComplete, retryCount + 1);
                                        }, 2000); // Đợi 2 giây trước khi thử lại
                                    } else {
                                        // Call completion callback on error
                                        if (onComplete) onComplete(false); // Error
                                    }
                                });
                            } else {
                                clearTimeout(iframeTimeout); // Clear timeout khi có lỗi
                                throw new Error('Template element not found');
                            }
                        }, 1000); // Wait 1 second for Elementor to initialize
                    } catch (error) {
                        clearTimeout(iframeTimeout); // Clear timeout khi có lỗi
                        
                        container.removeClass('loading');
                        loadingDiv.text('Lỗi chụp preview').show();
                        imageDiv.hide(); // Đảm bảo ẩn image khi có lỗi
                        
                        // Disable html2canvas after error
                        disableHtml2Canvas();
                        
                        // Retry logic
                        if (retryCount < maxRetries) {
                            setTimeout(function() {
                                captureTemplatePreview(container, onComplete, retryCount + 1);
                            }, 2000); // Đợi 2 giây trước khi thử lại
                        } else {
                            // Call completion callback on error
                            if (onComplete) onComplete(false); // Error
                        }
                    } finally {
                        // Remove iframe after a delay to ensure capture is complete
                        setTimeout(function() {
                            iframe.remove();
                        }, 2000);
                    }
                });

                // Handle iframe load error
                iframe.on('error', function() {
                    clearTimeout(iframeTimeout); // Clear timeout khi có lỗi
                    
                    container.removeClass('loading');
                    loadingDiv.text('Lỗi load template').show();
                    imageDiv.hide(); // Đảm bảo ẩn image khi có lỗi
                    iframe.remove();
                    
                    // Retry logic
                    if (retryCount < maxRetries) {
                        setTimeout(function() {
                            captureTemplatePreview(container, onComplete, retryCount + 1);
                        }, 2000); // Đợi 2 giây trước khi thử lại
                    } else {
                        // Call completion callback on error
                        if (onComplete) onComplete(false); // Error
                    }
                });
            } else {
                container.removeClass('loading');
                loadingDiv.text('Lỗi load template').show();
                imageDiv.hide(); // Đảm bảo ẩn image khi có lỗi
                
                // Retry logic
                if (retryCount < maxRetries) {
                    setTimeout(function() {
                        captureTemplatePreview(container, onComplete, retryCount + 1);
                    }, 2000); // Đợi 2 giây trước khi thử lại
                } else {
                    // Call completion callback on error
                    if (onComplete) onComplete(false); // Error
                }
            }
        }).fail(function(xhr, status, error) {
            container.removeClass('loading');
            loadingDiv.text('Lỗi load template').show();
            imageDiv.hide(); // Đảm bảo ẩn image khi có lỗi
            
            // Retry logic
            if (retryCount < maxRetries) {
                setTimeout(function() {
                    captureTemplatePreview(container, onComplete, retryCount + 1);
                }, 2000); // Đợi 2 giây trước khi thử lại
            } else {
                // Call completion callback on error
                if (onComplete) onComplete(false); // Error
            }
        });
    }

    // ==========================================================================
    // PREVIEW INITIALIZATION & CONTROLS
    // ==========================================================================
    
    // Function to create preview controls
    function createPreviewControls() {
        // Check if we're on the Elementor Library page and controls don't exist yet
        if ($('#preview-controls').length > 0) {
            console.log('Preview controls already exist');
            return; // Already exists
        }
        
        // Check if we're on the right page (All Templates)
        const isElementorLibraryPage = $('body').hasClass('post-type-elementor_library') && 
                                      $('.wp-list-table').length > 0;
        
        const isEditPage = window.location.href.includes('edit.php');
        const hasElementorInUrl = window.location.href.includes('elementor_library');
        const hasTable = $('.wp-list-table').length > 0;
        
        console.log('Page check:', {
            isElementorLibraryPage,
            isEditPage,
            hasElementorInUrl,
            hasTable,
            bodyClasses: $('body').attr('class'),
            url: window.location.href
        });
        
        if (!isElementorLibraryPage && !(isEditPage && hasElementorInUrl && hasTable)) {
            console.log('Not the right page for preview controls');
            return; // Not the right page
        }
        
        const controlsDiv = $('<div>', {
            id: 'preview-controls',
            class: 'preview-controls-wrapper',
            style: 'margin-bottom: 15px; margin-top: 15px; padding: 15px; background: #f9f9f9; border: 1px solid #e5e5e5; border-radius: 8px;'
        });
        
        const controlsTitle = $('<h4>', {
            style: 'margin: 0 0 10px 0; font-size: 14px; color: #333; display: flex; align-items: center;',
            html: '<i class="dashicons dashicons-visibility" style="margin-right: 5px; font-size: 16px;"></i>Preview Controls'
        });
        
        const buttonsContainer = $('<div>', {
            style: 'display: flex; gap: 10px; align-items: center;'
        });
        
        const refreshBtn = $('<button>', {
            id: 'refresh-all-previews',
            class: 'button button-secondary refresh-previews-btn',
            html: '<i class="dashicons dashicons-update"></i>Làm Mới Tất Cả',
            style: 'font-size: 12px; padding: 8px 12px; display: flex; align-items: center; gap: 5px;'
        });
        

        
        const toggleBtn = $('<button>', {
            id: 'toggle-all-previews',
            class: 'button button-primary toggle-previews-btn',
            html: '<i class="dashicons dashicons-visibility"></i>Hiện Tất Cả Preview',
            style: 'font-size: 12px; padding: 8px 12px; display: flex; align-items: center; gap: 5px;',
            'data-hidden': 'true'
        });
        
        const statusText = $('<span>', {
            id: 'preview-status',
            style: 'font-size: 12px; color: #666; margin-left: 10px;',
            text: 'Sẵn sàng'
        });
        
        buttonsContainer.append(toggleBtn).append(refreshBtn).append(statusText);
        controlsDiv.append(controlsTitle).append(buttonsContainer);
        
        // Insert above the table or after h1 if no table
        const table = $('.wp-list-table');
        if (table.length) {
            table.before(controlsDiv);
        } else {
            $('.wrap h1').after(controlsDiv);
        }
        
        setupPreviewControlsEvents(refreshBtn, toggleBtn);
        addPreviewControlsStyles();
    }
    
    // Function to setup events for preview controls
    function setupPreviewControlsEvents(refreshBtn, toggleBtn) {
        // Handle refresh button click
        refreshBtn.on('click', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const $status = $('#preview-status');
            
            $btn.prop('disabled', true)
                .html('<i class="dashicons dashicons-update spin"></i>Đang làm mới...')
                .css('opacity', '0.7');
            
            // Bước 1: Xóa tất cả ảnh preview cũ trước
            $status.text('Đang xóa ảnh cũ...').css('color', '#3182ce');
            
            // Timeout cho việc xóa ảnh cũ (5 phút)
            const clearTimeoutId = setTimeout(function() {
                $status.text('Timeout xóa ảnh cũ (5 phút)').css('color', '#f56565');
                $btn.prop('disabled', false)
                    .html('<i class="dashicons dashicons-update"></i>Làm Mới Tất Cả')
                    .css('opacity', '1');
            }, 300000); // 5 phút
            
            $.post(templatePreviewAjax.ajax_url, {
                action: 'clear_all_previews',
                nonce: templatePreviewAjax.nonce
            }, function(clearResponse) {
                window.clearTimeout(clearTimeoutId); // Clear timeout khi xóa thành công
                
                if (clearResponse.success) {
                    console.log('Đã xóa tất cả ảnh preview cũ:', clearResponse.data.deleted_count, '/', clearResponse.data.total_files, 'files');
                    
                    // Hiển thị thông tin chi tiết về việc xóa
                    let deleteMessage = `Đã xóa ${clearResponse.data.deleted_count}/${clearResponse.data.total_files} file preview cũ`;
                    if (clearResponse.data.deleted_meta_count > 0) {
                        deleteMessage += ` và ${clearResponse.data.deleted_meta_count} meta records`;
                    }
                    $status.text(deleteMessage).css('color', '#38a169');
                    
                    // Bước 2: Sau khi xóa xong, bắt đầu chụp ảnh mới
                    setTimeout(function() {
                        $status.text('Đang tạo ảnh mới...').css('color', '#3182ce');
                        
                        let completedCount = 0;
                        let successCount = 0;
                        let errorCount = 0;
                        
                        // QUÉT TẤT CẢ TEMPLATES (không filter theo section nữa)
                        const allContainers = $('.template-preview-container');
                        console.log('Total templates found:', allContainers.length);
                        
                        if (allContainers.length === 0) {
                            $status.text('Không tìm thấy templates nào để làm mới').css('color', '#f56565');
                            $btn.prop('disabled', false)
                                .html('<i class="dashicons dashicons-update"></i>Làm Mới Tất Cả')
                                .css('opacity', '1');
                            return;
                        }
                        
                        const totalContainers = allContainers.length;
                        $status.text(`Đang chụp 0/${totalContainers} templates...`).css('color', '#3182ce');
                        
                        allContainers.each(function(index) {
                            const container = $(this);
                            const templateId = container.data('template-id');
                            const imageDiv = container.find('.template-preview-image');
                            const loadingDiv = container.find('.template-preview-loading');
                            
                            // Reset preview state
                            imageDiv.hide();
                            loadingDiv.show().text('Đang tạo preview...');
                            container.addClass('loading');
                            
                            setTimeout(function() {
                                try {
                                    captureTemplatePreview(container, function(success = true) {
                                        completedCount++;
                                        if (success) {
                                            successCount++;
                                        } else {
                                            errorCount++;
                                        }
                                        
                                        const statusColor = errorCount > 0 ? '#f56565' : '#3182ce';
                                        $status.text(`Hoàn thành ${completedCount}/${totalContainers} preview (${successCount} thành công, ${errorCount} lỗi)...`).css('color', statusColor);
                                        
                                        if (completedCount >= totalContainers) {
                                            // All done - re-enable button
                                            $btn.prop('disabled', false)
                                                .html('<i class="dashicons dashicons-update"></i>Làm Mới Tất Cả')
                                                .css('opacity', '1');
                                            
                                            if (errorCount === 0) {
                                                $status.text(`Hoàn thành tất cả ${successCount} preview!`).css('color', '#38a169');
                                            } else {
                                                $status.text(`Hoàn thành: ${successCount} thành công, ${errorCount} lỗi`).css('color', '#f56565');
                                            }
                                            
                                            // Reset status after 5 seconds
                                            setTimeout(function() {
                                                $status.text('Sẵn sàng').css('color', '#666');
                                            }, 5000);
                                        }
                                    });
                                } catch (error) {
                                    console.error('Lỗi khi tạo preview cho template ID:', templateId, error);
                                    
                                    // Hiển thị lỗi cho template cụ thể
                                    container.removeClass('loading');
                                    loadingDiv.text(`Lỗi: ${error.message || 'Không thể tạo preview'}`).show();
                                    imageDiv.hide();
                                    
                                    // Vẫn tăng counter để tiếp tục
                                    completedCount++;
                                    errorCount++;
                                    $status.text(`Hoàn thành ${completedCount}/${totalContainers} preview (${successCount} thành công, ${errorCount} lỗi)...`).css('color', '#f56565');
                                    
                                    if (completedCount >= totalContainers) {
                                        // All done - re-enable button
                                        $btn.prop('disabled', false)
                                            .html('<i class="dashicons dashicons-update"></i>Làm Mới Tất Cả')
                                            .css('opacity', '1');
                                        $status.text(`Hoàn thành: ${successCount} thành công, ${errorCount} lỗi`).css('color', '#f56565');
                                        
                                        // Reset status after 5 seconds
                                        setTimeout(function() {
                                            $status.text('Sẵn sàng').css('color', '#666');
                                        }, 5000);
                                    }
                                }
                            }, index * 300); // Stagger requests to avoid overload
                        });
                        
                        // Fallback timeout in case of errors
                        setTimeout(function() {
                            if ($btn.prop('disabled')) {
                                $btn.prop('disabled', false)
                                    .html('<i class="dashicons dashicons-update"></i>Làm Mới Tất Cả')
                                    .css('opacity', '1');
                                $status.text('Timeout - vui lòng thử lại').css('color', '#f56565');
                            }
                        }, totalContainers * 300 + 10000);
                        
                        // Timeout tổng thể cho toàn bộ quá trình (60 phút - tăng thời gian)
                        setTimeout(function() {
                            if ($btn.prop('disabled')) {
                                $btn.prop('disabled', false)
                                    .html('<i class="dashicons dashicons-update"></i>Làm Mới Tất Cả')
                                    .css('opacity', '1');
                                $status.text('Timeout tổng thể (60 phút) - vui lòng thử lại').css('color', '#f56565');
                            }
                        }, 3600000); // 60 phút
                        
                    }, 1000); // Delay 1 giây để hiển thị thông tin xóa
                    
                } else {
                    // Nếu xóa thất bại
                    $status.text('Lỗi xóa ảnh cũ').css('color', '#f56565');
                    $btn.prop('disabled', false)
                        .html('<i class="dashicons dashicons-update"></i>Làm Mới Tất Cả')
                        .css('opacity', '1');
                }
            }).fail(function() {
                window.clearTimeout(clearTimeoutId); // Clear timeout khi có lỗi
                $status.text('Lỗi kết nối khi xóa ảnh cũ').css('color', '#f56565');
                $btn.prop('disabled', false)
                    .html('<i class="dashicons dashicons-update"></i>Làm Mới Tất Cả')
                    .css('opacity', '1');
            });
        });
        
        // Handle refresh sections only button click

        
        // Handle toggle button click
        toggleBtn.on('click', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const $status = $('#preview-status');
            const isHidden = $btn.data('hidden');
            
            if (isHidden) {
                // Show previews
                const containers = $('.template-preview-container');
                let autoGenCount = 0;
                
                // If no containers exist, show message
                if (containers.length === 0) {
                    $status.text('Không tìm thấy preview containers').css('color', '#f56565');
                    return;
                }
                
                containers.each(function() {
                    const container = $(this);
                    const hasImage = container.find('.template-preview-image img').length > 0;
                    
                    container.addClass('show-preview');
                    
                    // Chỉ hiển thị hình đã có sẵn, không tự động tạo preview
                    if (!hasImage) {
                        // Hiển thị thông báo nếu chưa có preview
                        const imageDiv = container.find('.template-preview-image');
                        imageDiv.html('<div style="padding: 20px; text-align: center; color: #666; font-size: 12px; background: #f8f9fa; border-radius: 6px;">Chưa có preview<br><small>Lưu template để tự động tạo</small></div>');
                    }
                });
                
                $btn.html('<i class="dashicons dashicons-hidden"></i>Ẩn Tất Cả Preview')
                    .removeClass('button-primary')
                    .addClass('button-secondary')
                    .data('hidden', false);
                
                if (autoGenCount > 0) {
                    $status.text(`Đang tự động tạo ${autoGenCount} preview...`).css('color', '#3182ce');
                    setTimeout(function() {
                        $status.text('Sẵn sàng').css('color', '#666');
                    }, 5000);
                } else {
                    $status.text('Đã hiện tất cả preview').css('color', '#38a169');
                    setTimeout(function() {
                        $status.text('Sẵn sàng').css('color', '#666');
                    }, 2000);
                }
            } else {
                // Hide previews
                $('.template-preview-container').removeClass('show-preview loading');
                
                $btn.html('<i class="dashicons dashicons-visibility"></i>Hiện Tất Cả Preview')
                    .removeClass('button-secondary')
                    .addClass('button-primary')
                    .data('hidden', true);
                
                $status.text('Đã ẩn tất cả preview').css('color', '#666');
                setTimeout(function() {
                    $status.text('Sẵn sàng').css('color', '#666');
                }, 2000);
            }
        });
    }
    
    // Function to add preview controls styles
    function addPreviewControlsStyles() {
        if ($('#mac-preview-controls-styles').length > 0) {
            return; // Already added
        }
        
        $('<style id="mac-preview-controls-styles">').text(`
            .refresh-previews-btn:hover {
                background: #f26212 !important;
                border-color: #f26212 !important;
                color: white !important;
            }
            .toggle-previews-btn:hover {
                opacity: 0.9;
            }
            .preview-controls-wrapper {
                animation: slideIn 0.3s ease-out;
            }
            @keyframes slideIn {
                from { opacity: 0; transform: translateY(-10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .spin {
                animation: spin 1s linear infinite;
            }
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        `).appendTo('head');
    }
    
    // Auto-capture preview cho templates cần thiết (DISABLED - chỉ tạo khi nhấn button)
    function initializeAutoCapture() {
        // Check if already initialized
        if (window.autoCaptureInitialized) {
            return; // Already initialized
        }
        window.autoCaptureInitialized = true;
        
        // DISABLED: Không tự động tạo preview nữa
        // Chỉ hiển thị trạng thái cho templates chưa có preview
        $('.template-preview-container').each(function(index) {
            const container = $(this);
            const templateId = container.data('template-id');
            const imageDiv = container.find('.template-preview-image');
            const hasExistingImage = imageDiv.find('img').length > 0;
            
            // Nếu đã có hình, không cần loading
            if (hasExistingImage) {
                container.removeClass('loading');
            } else {
                // Chỉ hiển thị thông báo, không tự động tạo preview
                const loadingDiv = container.find('.template-preview-loading');
                loadingDiv.text('Chưa có preview - nhấn "Làm Mới Tất Cả" để tạo').show();
                imageDiv.hide();
            }
        });
        
        console.log('Auto-capture disabled - previews will only be generated when clicking "Làm Mới Tất Cả"');
    }
    
    // Initialize auto-capture with multiple attempts
    $(document).ready(function() {
        setTimeout(function() {
            initializeAutoCapture();
        }, 2000);
    });
    
    $(window).on('load', function() {
        setTimeout(function() {
            initializeAutoCapture();
        }, 3000);
    });

    // Initialize preview controls when DOM is ready (prevent multiple calls)
    function initializePreviewControls() {
        // Prevent multiple initializations using window flag
        if (window.previewControlsInitialized) {
            return; // Already initialized
        }
        window.previewControlsInitialized = true;
        
        // Check if controls already exist
        if ($('#preview-controls').length > 0) {
            return; // Already exists
        }
        
        // Check if we're on the right page
        const isElementorLibraryPage = $('body').hasClass('post-type-elementor_library') && 
                                      $('.wp-list-table').length > 0;
        
        const isEditPage = window.location.href.includes('edit.php');
        const hasElementorInUrl = window.location.href.includes('elementor_library');
        const hasTable = $('.wp-list-table').length > 0;
        
        if (!isElementorLibraryPage && !(isEditPage && hasElementorInUrl && hasTable)) {
            return; // Not the right page
        }
        
        createPreviewControls();
    }
    
    // Multiple initialization attempts to ensure controls appear
    $(document).ready(function() {
        setTimeout(function() {
            initializePreviewControls();
        }, 500);
    });
    
    $(window).on('load', function() {
        setTimeout(function() {
            initializePreviewControls();
        }, 1000);
    });
    
    // Final attempt after 3 seconds
    setTimeout(function() {
        initializePreviewControls();
    }, 3000);
    
    // Additional attempts to ensure controls are created
    setTimeout(function() {
        if ($('#preview-controls').length === 0) {
            console.log('Controls not found, forcing creation...');
            createPreviewControls();
        }
    }, 5000);
    
    // One more attempt after 10 seconds
    setTimeout(function() {
        if ($('#preview-controls').length === 0) {
            console.log('Controls still not found, final attempt...');
            createPreviewControls();
        }
    }, 10000);
    
    // ==========================================================================
    // CLEAR OLD IMAGE REFERENCES
    // ==========================================================================
    
    // Function to clear old image references from database
    function clearOldImageReferences() {
        const $status = $('#preview-status');
        
        $status.text('Đang kiểm tra và xóa tham chiếu ảnh cũ...').css('color', '#3182ce');
        
        $.post(templatePreviewAjax.ajax_url, {
            action: 'clear_old_image_references',
            nonce: templatePreviewAjax.nonce
        }, function(response) {
            if (response.success) {
                $status.text(`Đã xóa ${response.data.cleared_count} tham chiếu ảnh cũ`).css('color', '#38a169');
                
                // Reset status after 3 seconds
                setTimeout(function() {
                    $status.text('Sẵn sàng').css('color', '#666');
                }, 3000);
            } else {
                $status.text('Lỗi khi xóa tham chiếu ảnh cũ').css('color', '#f56565');
            }
        }).fail(function() {
            $status.text('Lỗi kết nối khi xóa tham chiếu ảnh cũ').css('color', '#f56565');
        });
    }
    
    // Function to clear browser cache and reload
    function clearBrowserCacheAndReload() {
        const $status = $('#preview-status');
        
        $status.text('Đang xóa cache browser và reload trang...').css('color', '#3182ce');
        
        // Clear browser cache for images
        if ('caches' in window) {
            caches.keys().then(function(names) {
                for (let name of names) {
                    caches.delete(name);
                }
            });
        }
        
        // Force reload without cache
        setTimeout(function() {
            window.location.reload(true);
        }, 1000);
    }
    
    // Function to disable Facebook SDK
    function disableFacebookSDK() {
        const $status = $('#preview-status');
        
        $status.text('Đang tắt Facebook SDK...').css('color', '#3182ce');
        
        try {
            // Disable Facebook SDK if it exists
            if (typeof FB !== 'undefined') {
                // Unsubscribe from all events
                FB.Event.unsubscribe('xfbml.render');
                FB.Event.unsubscribe('auth.login');
                FB.Event.unsubscribe('auth.logout');
                FB.Event.unsubscribe('auth.statusChange');
                
                // Disable Facebook Pixel
                if (typeof fbq !== 'undefined') {
                    fbq('disable');
                }
                
                // Remove Facebook scripts
                $('script[src*="facebook"]').remove();
                $('script[src*="fb.com"]').remove();
                $('script[src*="connect.facebook.net"]').remove();
                
                // Remove Facebook elements
                $('[class*="fb-"]').remove();
                $('[id*="fb-"]').remove();
                $('[data-href*="facebook.com"]').remove();
                
                $status.text('Facebook SDK đã được tắt thành công').css('color', '#38a169');
            } else {
                $status.text('Facebook SDK không được tìm thấy').css('color', '#f56565');
            }
        } catch (error) {
            $status.text('Lỗi khi tắt Facebook SDK: ' + error.message).css('color', '#f56565');
        }
        
        // Reset status after 3 seconds
        setTimeout(function() {
            $status.text('Sẵn sàng').css('color', '#666');
        }, 3000);
    }
    
    // Function to disable jQuery Migrate
    function disableJQueryMigrate() {
        const $status = $('#preview-status');
        
        $status.text('Đang tắt jQuery Migrate...').css('color', '#3182ce');
        
        try {
            // Disable jQuery Migrate completely
            if (typeof jQuery !== 'undefined') {
                // Mute all jQuery Migrate warnings
                if (typeof jQuery.migrateMute === 'function') {
                    jQuery.migrateMute = true;
                }
                
                // Disable jQuery Migrate trace
                if (typeof jQuery.migrateTrace === 'function') {
                    jQuery.migrateTrace = false;
                }
                
                // Override console.warn to completely suppress jQuery Migrate messages
                const originalWarn = console.warn;
                console.warn = function(...args) {
                    const message = args.join(' ');
                    if (message.includes('JQMIGRATE') || message.includes('jQuery Migrate')) {
                        return; // Completely suppress
                    }
                    originalWarn.apply(console, args);
                };
                
                // Remove jQuery Migrate scripts
                $('script[src*="jquery-migrate"]').remove();
                $('script[src*="migrate"]').remove();
                
                $status.text('jQuery Migrate đã được tắt thành công').css('color', '#38a169');
            } else {
                $status.text('jQuery không được tìm thấy').css('color', '#f56565');
            }
        } catch (error) {
            $status.text('Lỗi khi tắt jQuery Migrate: ' + error.message).css('color', '#f56565');
        }
        
        // Reset status after 3 seconds
        setTimeout(function() {
            $status.text('Sẵn sàng').css('color', '#666');
        }, 3000);
    }
    
    // Function to debug controls
    function debugControls() {
        const $status = $('#preview-status');
        
        $status.text('Đang debug controls...').css('color', '#3182ce');
        
        try {
            const debugInfo = {
                controlsExist: $('#preview-controls').length > 0,
                bodyClasses: $('body').attr('class'),
                url: window.location.href,
                isElementorLibraryPage: $('body').hasClass('post-type-elementor_library') && $('.wp-list-table').length > 0,
                hasTable: $('.wp-list-table').length > 0,
                hasElementorInUrl: window.location.href.includes('elementor_library'),
                isEditPage: window.location.href.includes('edit.php'),
                jQueryLoaded: typeof jQuery !== 'undefined',
                templatePreviewAjax: typeof templatePreviewAjax !== 'undefined',
                autoCaptureInitialized: window.autoCaptureInitialized || false
            };
            
            console.log('Debug Controls Info:', debugInfo);
            
            // Force create controls if they don't exist
            if (!debugInfo.controlsExist) {
                console.log('Forcing creation of preview controls...');
                createPreviewControls();
            }
            
            $status.text('Debug hoàn thành - xem console').css('color', '#38a169');
            
        } catch (error) {
            $status.text('Lỗi debug: ' + error.message).css('color', '#f56565');
        }
        
        // Reset status after 5 seconds
        setTimeout(function() {
            $status.text('Sẵn sàng').css('color', '#666');
        }, 5000);
    }
    
    // Function to debug templates
    function debugTemplates() {
        const $status = $('#preview-status');
        
        $status.text('Đang debug templates...').css('color', '#3182ce');
        
        try {
            const allContainers = $('.template-preview-container');
            console.log('=== DEBUG TEMPLATES ===');
            console.log('Total containers found:', allContainers.length);
            
            if (allContainers.length === 0) {
                console.log('No template containers found!');
                console.log('Available elements with template-related classes:');
                $('[class*="template"]').each(function(index) {
                    console.log(`Element ${index + 1}:`, {
                        tag: this.tagName,
                        classes: this.className,
                        id: this.id,
                        data: $(this).data()
                    });
                });
            } else {
                // Log tất cả containers để xem data attributes
                allContainers.each(function(index) {
                    const container = $(this);
                    const templateId = container.data('template-id');
                    const templateType = container.data('template-type') || '';
                    const templateCategory = container.data('template-category') || '';
                    const templateName = container.data('template-name') || '';
                    const templatePostType = container.data('post-type') || '';
                    const templateStatus = container.data('post-status') || '';
                    const elementorType = container.data('elementor-type') || '';
                    const elementorCategory = container.data('elementor-category') || '';
                    
                    console.log(`Template ${index + 1}:`, {
                        id: templateId,
                        type: templateType,
                        category: templateCategory,
                        name: templateName,
                        postType: templatePostType,
                        status: templateStatus,
                        elementorType: elementorType,
                        elementorCategory: elementorCategory,
                        allData: container.data()
                    });
                    
                    // Kiểm tra xem có phải section không
                    const isSection = 
                        elementorType === 'section' ||  // data-elementor-type="section"
                        templateType === 'section' || 
                        templateCategory === 'section' ||
                        // Fallback patterns nếu không có data-elementor-type
                        templateId.toString().includes('section') ||
                        templateName.toLowerCase().includes('section');
                    
                    if (isSection) {
                        console.log(`✓ Template ${index + 1} is identified as SECTION`);
                    } else {
                        console.log(`✗ Template ${index + 1} is NOT identified as section`);
                    }
                });
            }
            
            $status.text('Debug templates hoàn thành - xem console').css('color', '#38a169');
            
        } catch (error) {
            $status.text('Lỗi debug templates: ' + error.message).css('color', '#f56565');
        }
        
        // Reset status after 10 seconds
        setTimeout(function() {
            $status.text('Sẵn sàng').css('color', '#666');
        }, 10000);
    }
    
    // Function to test filter logic
    function testFilterLogic() {
        const $status = $('#preview-status');
        
        $status.text('Đang test filter logic...').css('color', '#3182ce');
        
        try {
            const allContainers = $('.template-preview-container');
            console.log('=== TEST FILTER LOGIC ===');
            console.log('Total containers found:', allContainers.length);
            
            if (allContainers.length === 0) {
                console.log('No template containers found!');
                $status.text('Không tìm thấy template containers').css('color', '#f56565');
                return;
            }
            
            let sectionCount = 0;
            let nonSectionCount = 0;
            
            // Test từng container với các logic khác nhau
            allContainers.each(function(index) {
                const container = $(this);
                const templateId = container.data('template-id');
                const templateType = container.data('template-type') || '';
                const templateCategory = container.data('template-category') || '';
                const templateName = container.data('template-name') || '';
                const elementorType = container.data('elementor-type') || '';
                const elementorCategory = container.data('elementor-category') || '';
                
                console.log(`\n--- Testing Container ${index + 1} ---`);
                console.log('Data attributes:', {
                    id: templateId,
                    type: templateType,
                    category: templateCategory,
                    name: templateName,
                    elementorType: elementorType,
                    elementorCategory: elementorCategory
                });
                
                // Test các logic khác nhau
                const test1 = elementorType === 'section';
                const test2 = templateType === 'section';
                const test3 = templateCategory === 'section';
                const test4 = templateId.toString().includes('section');
                const test5 = templateName.toLowerCase().includes('section');
                
                console.log('Test results:');
                console.log(`  elementorType === 'section': ${test1}`);
                console.log(`  templateType === 'section': ${test2}`);
                console.log(`  templateCategory === 'section': ${test3}`);
                console.log(`  templateId includes 'section': ${test4}`);
                console.log(`  templateName includes 'section': ${test5}`);
                
                const isSection = test1 || test2 || test3 || test4 || test5;
                
                if (isSection) {
                    console.log(`✓ Container ${index + 1} is SECTION`);
                    sectionCount++;
                } else {
                    console.log(`✗ Container ${index + 1} is NOT section`);
                    nonSectionCount++;
                }
            });
            
            console.log(`\n=== SUMMARY ===`);
            console.log(`Total containers: ${allContainers.length}`);
            console.log(`Section templates: ${sectionCount}`);
            console.log(`Non-section templates: ${nonSectionCount}`);
            
            $status.text(`Test hoàn thành: ${sectionCount} section, ${nonSectionCount} non-section`).css('color', '#38a169');
            
        } catch (error) {
            $status.text('Lỗi test filter: ' + error.message).css('color', '#f56565');
        }
        
        // Reset status after 10 seconds
        setTimeout(function() {
            $status.text('Sẵn sàng').css('color', '#666');
        }, 10000);
    }
    
    // Function to force stop all processes
    function forceStopAll() {
        const $status = $('#preview-status');
        
        $status.text('Đang force stop tất cả processes...').css('color', '#dc3545');
        
        try {
            // Stop all timeouts
            for (let i = 1; i <= 10000; i++) {
                clearTimeout(i);
                clearInterval(i);
            }
            
            // Disable all external scripts
            $('script[src*="facebook"]').remove();
            $('script[src*="fb.com"]').remove();
            $('script[src*="connect.facebook.net"]').remove();
            $('script[src*="1HAPyGK5af1.js"]').remove();
            $('script[src*="jquery-migrate"]').remove();
            $('script[src*="migrate"]').remove();
            $('script[src*="html2canvas"]').remove();
            $('script[src*="content.js"]').remove();
            $('script[src*="content-loader.js"]').remove();
            
            // Remove all problematic elements
            $('[class*="fb-"]').remove();
            $('[id*="fb-"]').remove();
            $('[data-href*="facebook.com"]').remove();
            $('[class*="jquery"]').remove();
            $('[id*="jquery"]').remove();
            
            // Disable all external functions
            if (typeof FB !== 'undefined') {
                FB = { Event: { unsubscribe: function() { return; } }, init: function() { return; }, api: function() { return; } };
            }
            
            if (typeof fbq !== 'undefined') {
                fbq = function() { return; };
            }
            
            if (typeof html2canvas !== 'undefined') {
                html2canvas = function() { return Promise.reject(new Error('Force stopped')); };
            }
            
            // Clear all flags
            window.facebookSDKPrevented = false;
            window.jQueryMigrateHandled = false;
            window.reloadPreventionInitialized = false;
            window.previewControlsInitialized = false;
            window.autoCaptureInitialized = false;
            
            // Clear sessionStorage
            sessionStorage.clear();
            
            // Disable all event listeners
            $(document).off();
            $(window).off();
            
            $status.text('Force stop hoàn thành - trang sẽ reload sau 3 giây').css('color', '#38a169');
            
            // Reload page after 3 seconds
            setTimeout(function() {
                window.location.reload();
            }, 3000);
            
        } catch (error) {
            $status.text('Lỗi force stop: ' + error.message).css('color', '#f56565');
        }
    }
    
    // Function to enable HTML2Canvas for preview generation
    function enableHtml2Canvas() {
        if (window.html2canvasOriginal) {
            html2canvas = window.html2canvasOriginal;
            console.log('HTML2Canvas enabled for preview generation');
            return true;
        } else {
            console.error('HTML2Canvas original function not found');
            return false;
        }
    }
    
    // Function to disable HTML2Canvas after preview generation
    function disableHtml2Canvas() {
        const $status = $('#preview-status');
        
        $status.text('Đang tắt HTML2Canvas...').css('color', '#3182ce');
        
        try {
            // Disable html2canvas completely
            if (typeof html2canvas !== 'undefined') {
                html2canvas = function(element, options) {
                    return Promise.reject(new Error('HTML2Canvas has been disabled'));
                };
                
                // Remove html2canvas scripts
                $('script[src*="html2canvas"]').remove();
                $('script[src*="html2canvas.min.js"]').remove();
                
                $status.text('HTML2Canvas đã được tắt thành công').css('color', '#38a169');
            } else {
                $status.text('HTML2Canvas không được tìm thấy').css('color', '#f56565');
            }
        } catch (error) {
            $status.text('Lỗi khi tắt HTML2Canvas: ' + error.message).css('color', '#f56565');
        }
        
        // Reset status after 3 seconds
        setTimeout(function() {
            $status.text('Sẵn sàng').css('color', '#666');
        }, 3000);
    }
    
    // Function to reset all protection mechanisms
    function resetAllProtection() {
        const $status = $('#preview-status');
        
        $status.text('Đang reset tất cả cơ chế bảo vệ...').css('color', '#3182ce');
        
        try {
            // Clear all sessionStorage flags
            sessionStorage.removeItem('autoCaptureInitialized');
            sessionStorage.removeItem('controlsInitialized');
            sessionStorage.removeItem('saveHandlersInitialized');
            sessionStorage.removeItem('reloadAttempts');
            
            // Clear all window flags
            window.facebookSDKPrevented = false;
            window.jQueryMigrateHandled = false;
            window.reloadPreventionInitialized = false;
            
            // Clear all console overrides
            delete console.error;
            delete console.warn;
            delete console.log;
            
            // Remove all problematic scripts again
            $('script[src*="facebook"]').remove();
            $('script[src*="fb.com"]').remove();
            $('script[src*="connect.facebook.net"]').remove();
            $('script[src*="1HAPyGK5af1.js"]').remove();
            $('script[src*="jquery-migrate"]').remove();
            $('script[src*="migrate"]').remove();
            $('script[src*="html2canvas"]').remove();
            
            // Remove Facebook elements
            $('[class*="fb-"]').remove();
            $('[id*="fb-"]').remove();
            $('[data-href*="facebook.com"]').remove();
            
            $status.text('Đã reset tất cả cơ chế bảo vệ thành công').css('color', '#38a169');
            
            // Reload page after 2 seconds to apply reset
            setTimeout(function() {
                window.location.reload();
            }, 2000);
            
        } catch (error) {
            $status.text('Lỗi khi reset cơ chế bảo vệ: ' + error.message).css('color', '#f56565');
        }
    }
    
    // Add clear old references button to controls
    function addClearOldReferencesButton() {
        const existingBtn = $('#clear-old-references-btn');
        if (existingBtn.length > 0) {
            return; // Already exists
        }
        
        const clearBtn = $('<button>', {
            id: 'clear-old-references-btn',
            class: 'button button-secondary clear-references-btn',
            html: '<i class="dashicons dashicons-trash"></i>Xóa Tham Chiếu Ảnh Cũ',
            style: 'font-size: 12px; padding: 8px 12px; display: flex; align-items: center; gap: 5px;'
        });
        
        const clearCacheBtn = $('<button>', {
            id: 'clear-browser-cache-btn',
            class: 'button button-secondary clear-cache-btn',
            html: '<i class="dashicons dashicons-update"></i>Clear Browser Cache',
            style: 'font-size: 12px; padding: 8px 12px; display: flex; align-items: center; gap: 5px;'
        });
        
        const disableFacebookBtn = $('<button>', {
            id: 'disable-facebook-sdk-btn',
            class: 'button button-secondary disable-facebook-btn',
            html: '<i class="dashicons dashicons-dismiss"></i>Disable Facebook SDK',
            style: 'font-size: 12px; padding: 8px 12px; display: flex; align-items: center; gap: 5px;'
        });
        
        const disableJQueryMigrateBtn = $('<button>', {
            id: 'disable-jquery-migrate-btn',
            class: 'button button-secondary disable-jquery-migrate-btn',
            html: '<i class="dashicons dashicons-dismiss"></i>Disable jQuery Migrate',
            style: 'font-size: 12px; padding: 8px 12px; display: flex; align-items: center; gap: 5px;'
        });
        
        const resetProtectionBtn = $('<button>', {
            id: 'reset-protection-btn',
            class: 'button button-secondary reset-protection-btn',
            html: '<i class="dashicons dashicons-update"></i>Reset Protection',
            style: 'font-size: 12px; padding: 8px 12px; display: flex; align-items: center; gap: 5px;'
        });
        
        const disableHtml2CanvasBtn = $('<button>', {
            id: 'disable-html2canvas-btn',
            class: 'button button-secondary disable-html2canvas-btn',
            html: '<i class="dashicons dashicons-dismiss"></i>Disable HTML2Canvas',
            style: 'font-size: 12px; padding: 8px 12px; display: flex; align-items: center; gap: 5px;'
        });
        
        const debugBtn = $('<button>', {
            id: 'debug-controls-btn',
            class: 'button button-secondary debug-controls-btn',
            html: '<i class="dashicons dashicons-admin-tools"></i>Debug Controls',
            style: 'font-size: 12px; padding: 8px 12px; display: flex; align-items: center; gap: 5px;'
        });
        
        const debugTemplatesBtn = $('<button>', {
            id: 'debug-templates-btn',
            class: 'button button-secondary debug-templates-btn',
            html: '<i class="dashicons dashicons-admin-tools"></i>Debug Templates',
            style: 'font-size: 12px; padding: 8px 12px; display: flex; align-items: center; gap: 5px;'
        });
        
        const testFilterBtn = $('<button>', {
            id: 'test-filter-btn',
            class: 'button button-secondary test-filter-btn',
            html: '<i class="dashicons dashicons-search"></i>Test Filter',
            style: 'font-size: 12px; padding: 8px 12px; display: flex; align-items: center; gap: 5px; background-color: #28a745; color: white; border-color: #28a745;'
        });
        
        const forceStopBtn = $('<button>', {
            id: 'force-stop-btn',
            class: 'button button-secondary force-stop-btn',
            html: '<i class="dashicons dashicons-dismiss"></i>Force Stop All',
            style: 'font-size: 12px; padding: 8px 12px; display: flex; align-items: center; gap: 5px; background-color: #dc3545; color: white; border-color: #dc3545;'
        });
        
        clearBtn.on('click', function(e) {
            e.preventDefault();
            clearOldImageReferences();
        });
        
        clearCacheBtn.on('click', function(e) {
            e.preventDefault();
            clearBrowserCacheAndReload();
        });
        
        disableFacebookBtn.on('click', function(e) {
            e.preventDefault();
            disableFacebookSDK();
        });
        
        disableJQueryMigrateBtn.on('click', function(e) {
            e.preventDefault();
            disableJQueryMigrate();
        });
        
        disableHtml2CanvasBtn.on('click', function(e) {
            e.preventDefault();
            disableHtml2Canvas();
        });
        
        resetProtectionBtn.on('click', function(e) {
            e.preventDefault();
            resetAllProtection();
        });
        
        debugBtn.on('click', function(e) {
            e.preventDefault();
            debugControls();
        });
        
        debugTemplatesBtn.on('click', function(e) {
            e.preventDefault();
            debugTemplates();
        });
        
        testFilterBtn.on('click', function(e) {
            e.preventDefault();
            testFilterLogic();
        });
        
        forceStopBtn.on('click', function(e) {
            e.preventDefault();
            forceStopAll();
        });
        
        // Add to controls
        const buttonsContainer = $('#preview-controls .preview-controls-wrapper div:last-child');
        if (buttonsContainer.length > 0) {
            buttonsContainer.append(clearBtn).append(clearCacheBtn).append(disableFacebookBtn).append(disableJQueryMigrateBtn).append(disableHtml2CanvasBtn).append(debugBtn).append(debugTemplatesBtn).append(testFilterBtn).append(forceStopBtn).append(resetProtectionBtn);
        }
    }
    
    // Call this function when controls are created
    $(document).ready(function() {
        setTimeout(function() {
            addClearOldReferencesButton();
        }, 1000);
    });

    // ==========================================================================
    // AUTO PREVIEW GENERATION ON TEMPLATE SAVE
    // ==========================================================================
    
    // Hook vào Elementor save để tự động tạo preview (DISABLED - chỉ tạo khi nhấn button)
    function initializeSaveHandlers() {
        // Prevent multiple initializations using sessionStorage
        if (sessionStorage.getItem('saveHandlersInitialized')) {
            return; // Already initialized
        }
        sessionStorage.setItem('saveHandlersInitialized', 'true');
        
        // DISABLED: Không tự động tạo preview khi save nữa
        // Chỉ log để biết có save event
        if (typeof elementorFrontend !== 'undefined') {
            // Listen for Elementor save events (chỉ log, không tạo preview)
            $(document).on('elementor/editor/save', function(event, data) {
                const postId = data.id;
                if (postId) {
                    console.log('Elementor save detected for template:', postId, '- preview will be generated manually');
                }
            });
        }
        
        // Hook vào WordPress save post (chỉ log, không tạo preview)
        $(document).on('click', '#publish, #update', function() {
            const postId = $('input[name="post_ID"]').val();
            if (postId) {
                console.log('WordPress save detected for template:', postId, '- preview will be generated manually');
            }
        });
        
        // Hook vào Elementor editor save button (chỉ log, không tạo preview)
        $(document).on('click', '.elementor-button.elementor-button-success', function() {
            const postId = $('input[name="post_ID"]').val();
            if (postId) {
                console.log('Elementor button save detected for template:', postId, '- preview will be generated manually');
            }
        });
        
        console.log('Auto preview generation on save DISABLED - previews will only be generated when clicking "Làm Mới Tất Cả"');
    }
    
    // Function to auto generate preview for a specific template (DISABLED)
    function autoGeneratePreviewForTemplate(templateId) {
        // DISABLED: Không tự động tạo preview nữa
        console.log('Auto preview generation DISABLED for template:', templateId, '- use "Làm Mới Tất Cả" button instead');
        return;
        
        // Code cũ (đã disable):
        /*
        const container = $(`.template-preview-container[data-template-id="${templateId}"]`);
        if (container.length) {
            const imageDiv = container.find('.template-preview-image');
            const hasExistingImage = imageDiv.find('img').length > 0;
            
            // Only generate if no existing image
            if (!hasExistingImage) {
                console.log('Auto generating preview for template:', templateId);
                captureTemplatePreview(container);
            }
        }
        */
    }
    
    // Initialize save handlers only once with delay
    setTimeout(function() {
        initializeSaveHandlers();
    }, 1500);
    
}); 

} // Đóng else block 