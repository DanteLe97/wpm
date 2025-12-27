jQuery(document).ready(function($) {
    'use strict';
    
    // Loại bỏ các notices/headers từ plugin khác
    cleanPageFromOtherPlugins();
    
    var modal = $('#mac-referral-modal');
    var form = $('#mac-referral-form');
    var modalTitle = $('#mac-referral-modal-title');
    var referralIdInput = $('#referral_id');
    
    /**
     * Format số điện thoại thành (XXX) XXX-XXXX
     */
    function formatPhoneNumber(phone) {
        // Chỉ lấy số
        var numbers = phone.replace(/\D/g, '');
        
        if (numbers.length === 10) {
            return '(' + numbers.substring(0, 3) + ') ' + numbers.substring(3, 6) + '-' + numbers.substring(6);
        }
        
        return phone; // Giữ nguyên nếu không đủ 10 số
    }
    
    /**
     * Lấy số thuần từ số điện thoại (loại bỏ format)
     */
    function normalizePhoneNumber(phone) {
        return phone.replace(/\D/g, '');
    }
    
    /**
     * Check if phone number has 10 digits
     */
    function validatePhoneNumber(phone) {
        var numbers = normalizePhoneNumber(phone);
        return numbers.length === 10;
    }
    
    // Auto format số điện thoại khi nhập (format live)
    $('#phone, #phone_referral, #quick_phone_referral_input').on('input', function() {
        var $this = $(this);
        var cursorPos = $this.prop('selectionStart');
        var value = $this.val();
        var numbers = normalizePhoneNumber(value);
        
        // Format ngay khi có số, không cần đợi đủ 10 số
        if (numbers.length > 0) {
            var formatted = '';
            if (numbers.length <= 3) {
                formatted = '(' + numbers;
            } else if (numbers.length <= 6) {
                formatted = '(' + numbers.substring(0, 3) + ') ' + numbers.substring(3);
            } else if (numbers.length <= 10) {
                formatted = '(' + numbers.substring(0, 3) + ') ' + numbers.substring(3, 6) + '-' + numbers.substring(6);
            } else {
                // Nếu quá 10 số, chỉ lấy 10 số đầu
                formatted = formatPhoneNumber(numbers.substring(0, 10));
            }
            
            $this.val(formatted);
            
            // Giữ vị trí cursor (cố gắng giữ vị trí tương đối)
            var newCursorPos = Math.min(cursorPos + (formatted.length - value.length), formatted.length);
            $this.prop('selectionStart', newCursorPos);
            $this.prop('selectionEnd', newCursorPos);
        }
    });
    
    /**
     * Hiển thị thông báo popup ở góc trên phải
     */
    function showNotification(message, type) {
        type = type || 'success'; // success hoặc error
        var className = type === 'success' ? 'notice-success' : 'notice-error';
        
        var $notification = $('<div class="mac-referral-notification ' + className + '">' + message + '</div>');
        
        // Thêm vào body nếu chưa có container
        if ($('#mac-referral-notification-container').length === 0) {
            $('body').append('<div id="mac-referral-notification-container"></div>');
        }
        
        $('#mac-referral-notification-container').append($notification);
        
        // Trigger animation
        setTimeout(function() {
            $notification.addClass('show');
        }, 10);
        
        // Tự động ẩn sau 3 giây
        setTimeout(function() {
            $notification.removeClass('show');
            setTimeout(function() {
                $notification.remove();
            }, 300);
        }, 3000);
    }
    
    /**
     * Làm sạch trang khỏi các output từ plugin khác
     */
    function cleanPageFromOtherPlugins() {
        // Loại bỏ các notices từ plugin khác (giữ lại của mac-referral)
        $('.notice, .updated, .error').each(function() {
            var $notice = $(this);
            // Chỉ giữ lại notices có id chứa "mac_referral" hoặc trong wrap của plugin
            if (!$notice.attr('id') || $notice.attr('id').indexOf('mac_referral') === -1) {
                // Check if within plugin wrap
                if ($notice.closest('.mac-referral-wrap').length === 0) {
                    $notice.remove();
                }
            }
        });
        
        // Loại bỏ các script/style được inject bởi plugin khác (nếu có)
        // Only apply when on mac-referral page
        if (window.location.href.indexOf('page=mac-referral') !== -1) {
            // Có thể thêm logic khác nếu cần
        }
        
        // Run again after a short time to handle notices added later
        setTimeout(function() {
            $('.notice, .updated, .error').not('[id*="mac_referral"]').not('.mac-referral-wrap .notice').remove();
        }, 100);
    }
    
    // Open modal when click "Add New"
    $('#mac-referral-add-new').on('click', function() {
        resetForm();
        modalTitle.text('Add New Referral');
        referralIdInput.val('0');
        modal.fadeIn(300);
    });
    
    // Open modal when click "Edit"
    $(document).on('click', '.edit-referral-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var id = $(this).data('id');
        // Mở modal ngay lập tức để tránh delay
        modalTitle.text('Loading...');
        modal.fadeIn(300);
        // Load data sau khi modal đã mở
        loadReferralData(id);
    });
    
    // Đóng modal chỉ khi click vào close hoặc cancel (chỉ cho modal chính)
    $(document).on('click', '#mac-referral-modal .mac-referral-modal-close, #mac-referral-modal .mac-referral-modal-cancel', function(e) {
        e.preventDefault();
        e.stopPropagation();
        modal.fadeOut(300);
        resetForm();
    });
    
    
    // Track verified phone_referral value
    var verifiedPhoneReferral = '';
    var isPhoneReferralVerified = false;
    var $saveButton = form.find('button[type="submit"]');
    
    // Function to update Save button state based on phone_referral verification
    function updateSaveButtonState() {
        var phoneReferralValue = normalizePhoneNumber($('#phone_referral').val().trim());
        
        // If phone_referral is empty, allow save
        if (!phoneReferralValue) {
            $saveButton.prop('disabled', false);
            return;
        }
        
        // If phone_referral has value, check if it's verified and matches current value
        if (isPhoneReferralVerified && verifiedPhoneReferral === phoneReferralValue) {
            $saveButton.prop('disabled', false);
        } else {
            $saveButton.prop('disabled', true);
        }
    }
    
    // Reset form
    function resetForm() {
        form[0].reset();
        referralIdInput.val('0');
        originalPhoneReferral = '';
        originalReferralId = '';
        modalTitle.text('Add New Referral');
        form.removeData('submitting'); // Reset submitting flag
        
        // Reset phone referral verification
        verifiedPhoneReferral = '';
        isPhoneReferralVerified = false;
        updateSaveButtonState();
        
        // Hide phone referral message
        $('#phone-referral-message').hide().removeClass('phone-referral-success phone-referral-error').text('');
        // Hide phone and email messages
        $('#phone-message').hide().removeClass('phone-message-success phone-message-error').text('');
        $('#email-message').hide().removeClass('email-message-success email-message-error').text('');
    }
    
    // Handle "Add Phone Referral" button in form - only to verify phone number exists
    $('#mac-referral-add-phone-btn').on('click', function() {
        var phone_referral = $('#phone_referral').val().trim();
        var $messageDiv = $('#phone-referral-message');
        var $btn = $(this);
        
        // Reset message
        $messageDiv.hide().removeClass('phone-referral-success phone-referral-error').text('');
        
        if (!phone_referral) {
            $messageDiv.addClass('phone-referral-error')
                .text('Please enter referrer phone number')
                .show();
            // Reset verification state
            isPhoneReferralVerified = false;
            verifiedPhoneReferral = '';
            updateSaveButtonState();
            return;
        }
        
        // Normalize phone number before searching (remove format)
        var normalizedPhoneReferral = normalizePhoneNumber(phone_referral);
        
        if (!validatePhoneNumber(normalizedPhoneReferral)) {
            $messageDiv.addClass('phone-referral-error')
                .text('Phone number must have 10 digits')
                .show();
            // Reset verification state
            isPhoneReferralVerified = false;
            verifiedPhoneReferral = '';
            updateSaveButtonState();
            return;
        }
        
        // Disable button while searching
        $btn.prop('disabled', true).text('Verifying...');
        
        // Find referral by phone to confirm
        $.ajax({
            url: macReferralAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'mac_referral_find_by_phone',
                nonce: macReferralAjax.nonce,
                phone: normalizedPhoneReferral
            },
            success: function(response) {
                $btn.prop('disabled', false).text('Verify');
                
                if (response.success) {
                    var referral = response.data;
                    // Format phone number for display
                    var displayPhone = referral.phone || '';
                    if (displayPhone && validatePhoneNumber(displayPhone)) {
                        displayPhone = formatPhoneNumber(displayPhone);
                    }
                    $messageDiv.addClass('phone-referral-success')
                        .html('✓ Referrer found: <strong>' + referral.fullname + '</strong> (ID: ' + referral.id + '). Points will be set to 10.')
                        .show();
                    
                    // Mark as verified and save the verified value
                    isPhoneReferralVerified = true;
                    verifiedPhoneReferral = normalizedPhoneReferral;
                    updateSaveButtonState();
                } else {
                    $messageDiv.addClass('phone-referral-error')
                        .html('✕ ' + (response.data.message || 'Referrer not found with this phone number. Points will be set to 0.'))
                        .show();
                    
                    // Reset verification state
                    isPhoneReferralVerified = false;
                    verifiedPhoneReferral = '';
                    updateSaveButtonState();
                }
            },
            error: function() {
                $btn.prop('disabled', false).text('Verify');
                $messageDiv.addClass('phone-referral-error')
                    .text('✕ An error occurred while searching')
                    .show();
                
                // Reset verification state
                isPhoneReferralVerified = false;
                verifiedPhoneReferral = '';
                updateSaveButtonState();
            }
        });
    });
    
    // Monitor phone_referral input changes
    $('#phone_referral').on('input', function() {
        var currentValue = normalizePhoneNumber($(this).val().trim());
        
        // If phone_referral is empty, allow save
        if (!currentValue) {
            isPhoneReferralVerified = false;
            verifiedPhoneReferral = '';
            $('#phone-referral-message').hide().removeClass('phone-referral-success phone-referral-error').text('');
            updateSaveButtonState();
            return;
        }
        
        // If value changed from verified value, reset verification
        if (isPhoneReferralVerified && verifiedPhoneReferral !== currentValue) {
            isPhoneReferralVerified = false;
            verifiedPhoneReferral = '';
            $('#phone-referral-message').hide().removeClass('phone-referral-success phone-referral-error').text('');
            updateSaveButtonState();
        }
    });
    
    // Handle click on referral link to show tooltip/popup
    $(document).on('click', '.mac-referral-referral-link', function(e) {
        e.preventDefault();
        var phone = $(this).data('phone');
        
        if (!phone) {
            return;
        }
        
        // Find referral by phone number
        $.ajax({
            url: macReferralAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'mac_referral_find_by_phone',
                nonce: macReferralAjax.nonce,
                phone: phone
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    // Format số điện thoại khi hiển thị
                    var displayPhone = data.phone || '';
                    if (displayPhone && validatePhoneNumber(displayPhone)) {
                        displayPhone = formatPhoneNumber(displayPhone);
                    }
                    
                    var info = 'Referrer Information:\n\n';
                    info += 'ID: ' + data.id + '\n';
                    info += 'Full Name: ' + data.fullname + '\n';
                    info += 'Email: ' + (data.email || '-') + '\n';
                    info += 'Phone Number: ' + displayPhone + '\n';
                    info += 'Points: ' + data.point + '\n';
                    info += 'Created Date: ' + data.create_date;
                    
                    alert(info);
                } else {
                    alert('Referrer not found with phone number: ' + phone);
                }
            },
            error: function() {
                alert('An error occurred');
            }
        });
    });
    
    // Handle edit points
    $('.point-edit-btn').on('click', function() {
        var row = $(this).closest('tr');
        var pointDisplay = row.find('.point-display');
        var pointControl = row.find('.point-control');
        
        pointDisplay.hide();
        pointControl.show();
        pointControl.find('.point-change-input').focus();
    });
    
    // Hủy sửa điểm
    $('.point-cancel-btn').on('click', function() {
        var row = $(this).closest('tr');
        var pointDisplay = row.find('.point-display');
        var pointControl = row.find('.point-control');
        var input = pointControl.find('.point-change-input');
        
        input.val('');
        pointControl.hide();
        pointDisplay.show();
    });
    
    // Function to update points (add or subtract)
    function updatePoints($btn, isAdd) {
        var id = $btn.data('id');
        var row = $btn.closest('tr');
        var input = row.find('.point-change-input');
        var pointDisplay = row.find('.point-display');
        var pointControl = row.find('.point-control');
        var currentPoint = parseInt(pointDisplay.text()) || 0;
        
        var points = parseInt(input.val()) || 0;
        if (points <= 0) {
            showNotification('Please enter a valid point value (greater than 0)', 'error');
            input.focus();
            return;
        }
        
        // Calculate new point
        var actualChange = isAdd ? points : -points;
        var newPoint = currentPoint + actualChange;
        
        // Check points cannot be negative
        if (newPoint < 0) {
            showNotification('Points cannot be negative. Current points: ' + currentPoint + ', you cannot subtract more than ' + currentPoint + ' points.', 'error');
            input.focus();
            return;
        }
        
        $.ajax({
            url: macReferralAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'mac_referral_update_point',
                nonce: macReferralAjax.nonce,
                id: id,
                point_change: actualChange
            },
            success: function(response) {
                if (response.success) {
                    pointDisplay.text(response.data.new_point);
                    input.val('');
                    pointControl.hide();
                    pointDisplay.show();
                    
                    // Show success notification
                    showNotification(response.data.message || 'Points updated successfully!', 'success');
                } else {
                    showNotification('Error: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showNotification('An error occurred while updating points', 'error');
            }
        });
    }
    
    // Add points button
    $(document).on('click', '.point-add-btn', function() {
        updatePoints($(this), true);
    });
    
    // Subtract points button
    $(document).on('click', '.point-subtract-btn', function() {
        updatePoints($(this), false);
    });
    
    // Save original phone_referral for comparison
    var originalPhoneReferral = '';
    var originalReferralId = '';
    
    // Validation and handle form via AJAX
    form.on('submit', function(e) {
        e.preventDefault();
        
        var fullname = $('#fullname').val().trim();
        var phone = $('#phone').val().trim();
        var phoneReferral = $('#phone_referral').val().trim();
        var referralId = referralIdInput.val();
        
        if (!fullname || !phone) {
            showNotification('Please fill in all required fields (Full Name and Phone Number).', 'error');
            return false;
        }
        
        // Check phone number has 10 digits
        if (!validatePhoneNumber(phone)) {
            $('#phone-message').addClass('phone-message-error')
                .text('Phone number must have 10 digits.')
                .show();
            $('#phone').focus();
            return false;
        }
        
        // Normalize phone numbers before submit (backend sẽ normalize, nhưng cần validate ở đây)
        phone = normalizePhoneNumber(phone);
        phoneReferral = phoneReferral ? normalizePhoneNumber(phoneReferral) : '';
        
        // Validate phone_referral nếu có
        if (phoneReferral && !validatePhoneNumber(phoneReferral)) {
            $('#phone-referral-message').addClass('phone-referral-error')
                .text('Phone number must have 10 digits.')
                .show();
            $('#phone_referral').focus();
            return false;
        }
        
        // Check if phone_referral is verified (if it has value)
        if (phoneReferral) {
            var currentNormalized = normalizePhoneNumber(phoneReferral);
            if (!isPhoneReferralVerified || verifiedPhoneReferral !== currentNormalized) {
                $('#phone-referral-message').addClass('phone-referral-error')
                    .text('Please verify the referrer phone number before saving.')
                    .show();
                $('#phone_referral').focus();
                return false;
            }
        }
        
        // Check for duplicates before submit
        checkDuplicatesAndSubmit(phone, $('#email').val().trim(), referralId);
    });
    
    // Function to check duplicates and submit form
    function checkDuplicatesAndSubmit(phone, email, excludeId) {
        var hasError = false;
        var checkPromises = [];
        
        // Check phone duplicate
        if (phone) {
            checkPromises.push(
                $.ajax({
                    url: macReferralAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mac_referral_check_duplicate',
                        nonce: macReferralAjax.nonce,
                        field: 'phone',
                        value: phone,
                        exclude_id: excludeId
                    }
                }).then(function(response) {
                    if (response.success && response.data.exists) {
                        $('#phone-message').addClass('phone-message-error')
                            .text('This phone number already exists in the system. Please enter a different phone number.')
                            .show();
                        hasError = true;
                    } else {
                        $('#phone-message').hide().removeClass('phone-message-error phone-message-success').text('');
                    }
                })
            );
        }
        
        // Check email duplicate (only if email is not empty)
        if (email) {
            checkPromises.push(
                $.ajax({
                    url: macReferralAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mac_referral_check_duplicate',
                        nonce: macReferralAjax.nonce,
                        field: 'email',
                        value: email,
                        exclude_id: excludeId
                    }
                }).then(function(response) {
                    if (response.success && response.data.exists) {
                        $('#email-message').addClass('email-message-error')
                            .text('This email already exists in the system. Please enter a different email.')
                            .show();
                        hasError = true;
                    } else {
                        $('#email-message').hide().removeClass('email-message-error email-message-success').text('');
                    }
                })
            );
        }
        
        // Wait for all checks to complete
        if (checkPromises.length === 0) {
            submitReferralForm();
            return;
        }
        
        $.when.apply($, checkPromises).done(function() {
            if (!hasError) {
                // All checks passed, submit form
                submitReferralForm();
            } else {
                // Focus on first error field
                if ($('#phone-message').is(':visible')) {
                    $('#phone').focus();
                } else if ($('#email-message').is(':visible')) {
                    $('#email').focus();
                }
            }
        });
    }
    
    // Real-time validation on blur for phone and email
    $('#phone').on('blur', function() {
        var phone = normalizePhoneNumber($(this).val().trim());
        var referralId = referralIdInput.val();
        
        if (phone && validatePhoneNumber(phone)) {
            $.ajax({
                url: macReferralAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'mac_referral_check_duplicate',
                    nonce: macReferralAjax.nonce,
                    field: 'phone',
                    value: phone,
                    exclude_id: referralId
                },
                success: function(response) {
                    if (response.success && response.data.exists) {
                        $('#phone-message').addClass('phone-message-error')
                            .text('This phone number already exists in the system. Please enter a different phone number.')
                            .show();
                    } else {
                        $('#phone-message').hide().removeClass('phone-message-error phone-message-success').text('');
                    }
                }
            });
        }
    });
    
    $('#email').on('blur', function() {
        var email = $(this).val().trim();
        var referralId = referralIdInput.val();
        
        if (email) {
            $.ajax({
                url: macReferralAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'mac_referral_check_duplicate',
                    nonce: macReferralAjax.nonce,
                    field: 'email',
                    value: email,
                    exclude_id: referralId
                },
                success: function(response) {
                    if (response.success && response.data.exists) {
                        $('#email-message').addClass('email-message-error')
                            .text('This email already exists in the system. Please enter a different email.')
                            .show();
                    } else {
                        $('#email-message').hide().removeClass('email-message-error email-message-success').text('');
                    }
                }
            });
        } else {
            $('#email-message').hide().removeClass('email-message-error email-message-success').text('');
        }
    });
    
    // Clear messages when user starts typing
    $('#phone').on('input', function() {
        $('#phone-message').hide().removeClass('phone-message-error phone-message-success').text('');
    });
    
    $('#email').on('input', function() {
        $('#email-message').hide().removeClass('email-message-error email-message-success').text('');
    });
    
    // Function to check and handle phone_referral change
    function checkAndHandlePhoneReferralChange(newPhoneReferral, oldPhoneReferral, callback) {
        var promises = [];
        
        // 1. Check if old phone_referral has corresponding ID
        if (oldPhoneReferral) {
            promises.push(
                $.ajax({
                    url: macReferralAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mac_referral_find_by_phone',
                        nonce: macReferralAjax.nonce,
                        phone: oldPhoneReferral
                    }
                }).then(function(response) {
                    if (response.success && response.data) {
                        // Old phone has ID, need to ask to subtract points
                        return {
                            type: 'subtract',
                            referral: response.data
                        };
                    }
                    return null;
                })
            );
        }
        
        // 2. Check if new phone_referral has corresponding ID
        if (newPhoneReferral) {
            promises.push(
                $.ajax({
                    url: macReferralAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mac_referral_find_by_phone',
                        nonce: macReferralAjax.nonce,
                        phone: newPhoneReferral
                    }
                }).then(function(response) {
                    if (response.success && response.data) {
                        // New phone has ID, need to ask to add points
                        return {
                            type: 'add',
                            referral: response.data
                        };
                    }
                    return null;
                })
            );
        }
        
        // Chờ tất cả AJAX hoàn thành
        if (promises.length === 0) {
            callback();
            return;
        }
        
        $.when.apply($, promises).done(function() {
            var results = [];
            
            // Handle arguments - can be 1 promise or multiple
            if (promises.length === 1) {
                results = [arguments[0]];
            } else {
                for (var i = 0; i < arguments.length; i++) {
                    results.push(arguments[i]);
                }
            }
            
            var subtractRef = null;
            var addRef = null;
            
            results.forEach(function(result) {
                if (result && result.type === 'subtract') {
                    subtractRef = result.referral;
                } else if (result && result.type === 'add') {
                    addRef = result.referral;
                }
            });
            
            // Handle subtract points (old phone)
            if (subtractRef) {
                var confirmSubtract = confirm(
                    'You have changed the referrer phone number.\n\n' +
                    'Old Phone Number: ' + oldPhoneReferral + '\n' +
                    'Old Referrer: ' + subtractRef.fullname + ' (ID: ' + subtractRef.id + ')\n' +
                    'Current Points: ' + subtractRef.point + '\n\n' +
                    'Do you want to SUBTRACT 10 points from the old referrer?'
                );
                
                if (confirmSubtract) {
                    // Subtract points
                    updateReferralPoint(subtractRef.id, -10, function() {
                        // After subtracting points, handle adding points for new phone
                        if (addRef) {
                            handleAddPoint(addRef, callback);
                        } else {
                            callback();
                        }
                    });
                    return;
                }
            }
            
            // Handle add points (new phone)
            if (addRef) {
                handleAddPoint(addRef, callback);
            } else {
                callback();
            }
        });
    }
    
    // Function to handle adding points
    function handleAddPoint(referral, callback) {
        var confirmAdd = confirm(
            'You have changed the referrer phone number.\n\n' +
            'New Phone Number: ' + referral.phone + '\n' +
            'New Referrer: ' + referral.fullname + ' (ID: ' + referral.id + ')\n' +
            'Current Points: ' + referral.point + '\n' +
            'Points After Addition: ' + (parseInt(referral.point) + 10) + '\n\n' +
            'Do you want to ADD 10 points to the new referrer?'
        );
        
        if (confirmAdd) {
            updateReferralPoint(referral.id, 10, callback);
        } else {
            callback();
        }
    }
    
    // Function to update points
    function updateReferralPoint(id, points, callback) {
        $.ajax({
            url: macReferralAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'mac_referral_update_point',
                nonce: macReferralAjax.nonce,
                id: id,
                point_change: points
            },
            success: function(response) {
                if (response.success) {
                    // Show success notification
                    showNotification(response.data.message || 'Points updated successfully!', 'success');
                    if (callback) callback();
                } else {
                    showNotification('Error updating points: ' + response.data.message, 'error');
                    if (callback) callback();
                }
            },
            error: function() {
                showNotification('An error occurred while updating points', 'error');
                if (callback) callback();
            }
        });
    }
    
    // Function to submit form via AJAX
    function submitReferralForm() {
        // Check if already submitting (prevent double submit)
        if (form.data('submitting')) {
            return;
        }
        
        form.data('submitting', true);
        
        // Normalize phone numbers before submitting (backend sẽ lưu normalized)
        var phone = normalizePhoneNumber($('#phone').val());
        var phoneReferral = normalizePhoneNumber($('#phone_referral').val());
        
        // Temporarily set normalized values
        var originalPhone = $('#phone').val();
        var originalPhoneReferral = $('#phone_referral').val();
        $('#phone').val(phone);
        $('#phone_referral').val(phoneReferral);
        
        var formData = form.serialize();
        
        // Restore formatted values for display
        $('#phone').val(originalPhone);
        $('#phone_referral').val(originalPhoneReferral);
        
        var $submitBtn = form.find('button[type="submit"]');
        var originalBtnText = $submitBtn.text();
        
        $submitBtn.prop('disabled', true).text('Saving...');
        
        // Change action to call AJAX handler instead of normal form submit
        // Remove mac_referral_action from formData and add new action
        formData = formData.replace(/mac_referral_action=save_referral/, '');
        formData += '&action=mac_referral_save_referral';
        
        // Ensure no extra & at the beginning
        if (formData.indexOf('&') === 0) {
            formData = formData.substring(1);
        }
        
        $.ajax({
            url: macReferralAjax.ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                form.data('submitting', false);
                if (response.success) {
                    // Show success notification
                    showNotification(response.data.message || 'Saved successfully!', 'success');
                    modal.fadeOut(300);
                    resetForm();
                    // Reload page after 1 second to update data
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    // Show error notification
                    var errorMessage = response.data.message || 'An error occurred';
                    
                    // Check if error is for specific field (phone or email)
                    if (response.data && response.data.field === 'phone') {
                        $('#phone-message').addClass('phone-message-error')
                            .text(errorMessage)
                            .show();
                        $('#phone').focus();
                    } else if (response.data && response.data.field === 'email') {
                        $('#email-message').addClass('email-message-error')
                            .text(errorMessage)
                            .show();
                        $('#email').focus();
                    } else {
                        showNotification(errorMessage, 'error');
                    }
                    
                    $submitBtn.prop('disabled', false).text(originalBtnText);
                }
            },
            error: function(xhr, status, error) {
                form.data('submitting', false);
                console.error('AJAX Error:', error);
                console.error('Response:', xhr.responseText);
                showNotification('An error occurred while saving data', 'error');
                $submitBtn.prop('disabled', false).text(originalBtnText);
            }
        });
    }
    
    // Save original phone_referral when loading edit form
    function loadReferralData(id) {
        // Reset form trước khi load
        form[0].reset();
        
        // Disable Save button khi đang loading
        $saveButton.prop('disabled', true);
        
        $.ajax({
            url: macReferralAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'mac_referral_get_referral',
                nonce: macReferralAjax.nonce,
                id: id
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    $('#fullname').val(data.fullname || '');
                    $('#email').val(data.email || '');
                    // Format phone numbers for display
                    var phone = data.phone || '';
                    var phoneReferral = data.phone_referral || '';
                    if (phone && validatePhoneNumber(phone)) {
                        phone = formatPhoneNumber(phone);
                    }
                    if (phoneReferral && validatePhoneNumber(phoneReferral)) {
                        phoneReferral = formatPhoneNumber(phoneReferral);
                    }
                    $('#phone').val(phone);
                    $('#phone_referral').val(phoneReferral);
                    
                    // Save original value for comparison (normalized phone_referral)
                    originalPhoneReferral = data.phone_referral || '';
                    
                    referralIdInput.val(data.id);
                    modalTitle.text('Edit Referral');
                    
                    // Reset messages
                    $('#phone-referral-message').hide().removeClass('phone-referral-success phone-referral-error').text('');
                    $('#phone-message').hide().removeClass('phone-message-error phone-message-success').text('');
                    $('#email-message').hide().removeClass('email-message-error email-message-success').text('');
                    
                    // Reset verification state when loading edit form
                    // If phone_referral exists, mark as verified (since it's already in database)
                    var loadedPhoneReferral = data.phone_referral || '';
                    if (loadedPhoneReferral) {
                        verifiedPhoneReferral = normalizePhoneNumber(loadedPhoneReferral);
                        isPhoneReferralVerified = true;
                    } else {
                        verifiedPhoneReferral = '';
                        isPhoneReferralVerified = false;
                    }
                    // Enable Save button sau khi load xong (updateSaveButtonState sẽ set lại state)
                    updateSaveButtonState();
                } else {
                    // Enable Save button nếu có lỗi
                    $saveButton.prop('disabled', false);
                    modal.fadeOut(300);
                    showNotification('Error: ' + response.data.message, 'error');
                }
            },
            error: function() {
                // Enable Save button nếu có lỗi
                $saveButton.prop('disabled', false);
                modal.fadeOut(300);
                showNotification('An error occurred while loading data.', 'error');
            }
        });
    }
    
    // Bulk Actions
    var bulkForm = $('#mac-referral-bulk-form');
    var selectAll = $('#mac-referral-select-all');
    var checkboxes = $('.mac-referral-checkbox');
    
    // Select/Deselect All
    selectAll.on('change', function() {
        checkboxes.prop('checked', $(this).prop('checked'));
    });
    
    // Update select all khi checkbox thay đổi
    checkboxes.on('change', function() {
        var allChecked = checkboxes.length === checkboxes.filter(':checked').length;
        selectAll.prop('checked', allChecked);
    });
    
    // Handle bulk action
    $('#mac-referral-do-bulk-action').on('click', function(e) {
        e.preventDefault();
        var action = $('#bulk-action-selector').val();
        var checked = checkboxes.filter(':checked');
        
        if (!action) {
            showNotification('Please select an action', 'error');
            return false;
        }
        
        if (checked.length === 0) {
            showNotification('Please select at least one item', 'error');
            return false;
        }
        
        if (action === 'delete') {
            if (!confirm('Are you sure you want to delete ' + checked.length + ' selected item(s)? This action cannot be undone!')) {
                return false;
            }
        }
        
        // Set action value to hidden input and submit form
        $('#bulk-action-hidden').val(action);
        bulkForm.submit();
    });
    
    // Quick Add Phone Referral Modal
    var quickModal = $('#mac-referral-quick-modal');
    var quickModalClose = quickModal.find('.mac-referral-modal-close');
    var quickModalCancel = quickModal.find('.mac-referral-quick-cancel');
    var quickResultDiv = $('#mac-referral-quick-result');
    var quickErrorDiv = $('#mac-referral-quick-error');
    
    // Mở quick modal
    $('#mac-referral-quick-add-btn').on('click', function() {
        resetQuickForm();
        quickModal.fadeIn(300);
        $('#quick_phone_referral_input').focus();
    });
    
    // Đóng quick modal chỉ khi click vào close hoặc cancel
    quickModalClose.add(quickModalCancel).on('click', function() {
        quickModal.fadeOut(300);
        resetQuickForm();
    });
    
    // Reset quick form
    function resetQuickForm() {
        $('#quick_phone_referral_input').val('');
        quickResultDiv.hide();
        quickErrorDiv.hide();
        $('#quick-result-referral-id').val('');
    }
    
    // Tìm kiếm số điện thoại
    $('#mac-referral-search-phone-btn').on('click', function() {
        searchPhoneReferral();
    });
    
    // Enter key để tìm kiếm
    $('#quick_phone_referral_input').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            searchPhoneReferral();
        }
    });
    
    function searchPhoneReferral() {
        var phone = $('#quick_phone_referral_input').val().trim();
        var $btn = $('#mac-referral-search-phone-btn');
        
        if (!phone) {
            quickErrorDiv.show().find('#quick-error-message').text('Please enter phone number');
            quickResultDiv.hide();
            return;
        }
        
        // Normalize phone number before searching (remove format)
        phone = normalizePhoneNumber(phone);
        
        if (!validatePhoneNumber(phone)) {
            quickErrorDiv.show().find('#quick-error-message').text('Phone number must have 10 digits');
            quickResultDiv.hide();
            return;
        }
        
        $btn.prop('disabled', true).text('Searching...');
        quickErrorDiv.hide();
        quickResultDiv.hide();
        
        $.ajax({
            url: macReferralAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'mac_referral_find_by_phone',
                nonce: macReferralAjax.nonce,
                phone: phone
            },
            success: function(response) {
                $btn.prop('disabled', false).text('Search');
                
                if (response.success) {
                    var referral = response.data;
                    
                    // Format phone number for display
                    var displayPhone = referral.phone || '';
                    if (displayPhone && validatePhoneNumber(displayPhone)) {
                        displayPhone = formatPhoneNumber(displayPhone);
                    }
                    
                    // Display information
                    $('#quick-result-id').text(referral.id);
                    $('#quick-result-fullname').text(referral.fullname || '-');
                    $('#quick-result-email').text(referral.email || '-');
                    $('#quick-result-phone').text(displayPhone);
                    $('#quick-result-point').text(referral.point || 0);
                    $('#quick-result-new-point').text((parseInt(referral.point || 0) + 10));
                    $('#quick-result-referral-id').val(referral.id);
                    
                    quickResultDiv.show();
                    quickErrorDiv.hide();
                } else {
                    quickErrorDiv.show().find('#quick-error-message').text(response.data.message || 'Referrer not found');
                    quickResultDiv.hide();
                }
            },
            error: function() {
                $btn.prop('disabled', false).text('Search');
                quickErrorDiv.show().find('#quick-error-message').text('An error occurred while connecting to server');
                quickResultDiv.hide();
            }
        });
    }
    
    // Confirm and add points
    $('#mac-referral-confirm-add-btn').on('click', function() {
        var referralId = $('#quick-result-referral-id').val();
        var fullname = $('#quick-result-fullname').text();
        var currentPoint = parseInt($('#quick-result-point').text()) || 0;
        var newPoint = currentPoint + 10;
        var phoneText = $('#quick-result-phone').text();
        
        if (!referralId) {
            showNotification('Referrer information not found', 'error');
            return;
        }
        
        var $btn = $(this);
        $btn.prop('disabled', true).text('Processing...');
        
        // Normalize phone number before sending
        var phoneNormalized = normalizePhoneNumber(phoneText);
        
        $.ajax({
            url: macReferralAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'mac_referral_add_phone_referral',
                nonce: macReferralAjax.nonce,
                phone_referral: phoneNormalized,
                points: 10
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Successfully added 10 points!', 'success');
                    quickModal.fadeOut(300);
                    resetQuickForm();
                    
                    // Reload page after 1 second to update points
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification('Error: ' + (response.data.message || 'An error occurred'), 'error');
                    $btn.prop('disabled', false).text('Confirm and Add Points');
                }
            },
            error: function() {
                showNotification('An error occurred while connecting to server', 'error');
                $btn.prop('disabled', false).text('Confirm and Add Points');
            }
        });
    });
    
    // History Button Handler
    var historyModal = $('#mac-referral-history-modal');
    var historyContent = $('#history-content');
    
    $(document).on('click', '.history-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var id = $(this).data('id');
        if (!id) {
            console.error('History button: No ID found');
            return;
        }
        
        historyModal.fadeIn(300);
        historyContent.html('<div style="text-align: center; padding: 20px;"><span class="spinner is-active"></span><p>Loading history...</p></div>');
        
        // Load logs via AJAX
        $.ajax({
            url: macReferralAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'mac_referral_get_logs',
                referral_id: id,
                limit: 50,
                nonce: macReferralAjax.nonce
            },
            success: function(response) {
                if (response.success && response.data.logs) {
                    displayHistoryLogs(response.data.logs);
                } else {
                    historyContent.html('<div style="text-align: center; padding: 20px;"><p>No history found for this referral.</p></div>');
                }
            },
            error: function() {
                historyContent.html('<div style="text-align: center; padding: 20px;"><p style="color: red;">Error loading history. Please try again.</p></div>');
            }
        });
    });
    
    // Close history modal
    $(document).on('click', '#mac-referral-history-modal .mac-referral-modal-close', function(e) {
        e.preventDefault();
        e.stopPropagation();
        historyModal.fadeOut(300);
        historyContent.html('');
    });
    
    // Helper function to format phone number in history display
    function formatPhoneForDisplay(phone) {
        if (!phone || phone === 'N/A') {
            return phone || 'N/A';
        }
        // Kiểm tra xem có phải là số điện thoại không (chứa số và có thể có format)
        var numbers = phone.toString().replace(/\D/g, '');
        if (numbers.length === 10) {
            return formatPhoneNumber(phone);
        }
        return phone;
    }
    
    // Display history logs
    function displayHistoryLogs(logs) {
        if (logs.length === 0) {
            historyContent.html('<div style="text-align: center; padding: 20px;"><p>No history found for this referral.</p></div>');
            return;
        }
        
        var html = '<div class="history-logs-list">';
        
        logs.forEach(function(log) {
            var actionBadge = getActionBadge(log.action);
            var logDate = log.log_date_formatted || log.log_date;
            
            html += '<div class="history-log-item">';
            html += '<div class="history-log-header">';
            html += '<span class="action-badge action-badge-' + actionBadge.class + '">' + actionBadge.label + '</span>';
            html += '<span class="history-log-date">' + logDate + '</span>';
            html += '<span class="history-log-user">by ' + (log.user_name || 'N/A') + '</span>';
            html += '</div>';
            
            // Display details based on action type
            if (log.action === 'point_update' && log.old_point !== null && log.new_point !== null) {
                var pointChange = log.point_change || (log.new_point - log.old_point);
                html += '<div class="history-log-details">';
                html += 'Points: <strong>' + log.old_point + '</strong> → <strong>' + log.new_point + '</strong>';
                if (pointChange) {
                    html += ' (<span style="color: ' + (pointChange > 0 ? 'green' : 'red') + ';">' + (pointChange > 0 ? '+' : '') + pointChange + '</span>)';
                }
                html += '</div>';
            } else if (log.action === 'update' && log.changes) {
                html += '<div class="history-log-details">';
                html += '<strong>Changes:</strong><ul>';
                for (var field in log.changes) {
                    if (log.changes.hasOwnProperty(field)) {
                        var fieldLabel = field.charAt(0).toUpperCase() + field.slice(1).replace(/_/g, ' ');
                        var oldValue = log.changes[field].old || 'N/A';
                        var newValue = log.changes[field].new || 'N/A';
                        
                        // Format phone numbers nếu field là phone hoặc phone_referral
                        if (field === 'phone' || field === 'phone_referral') {
                            oldValue = formatPhoneForDisplay(oldValue);
                            newValue = formatPhoneForDisplay(newValue);
                        }
                        
                        html += '<li><strong>' + fieldLabel + ':</strong> ';
                        html += '<span style="color: red;">' + oldValue + '</span> → ';
                        html += '<span style="color: green;">' + newValue + '</span>';
                        html += '</li>';
                    }
                }
                html += '</ul></div>';
            } else if (log.action === 'insert' && log.new_data) {
                html += '<div class="history-log-details">';
                html += '<strong>Created:</strong> ';
                html += 'Fullname: ' + (log.new_data.fullname || 'N/A') + ', ';
                html += 'Phone: ' + formatPhoneForDisplay(log.new_data.phone);
                html += '</div>';
            } else if (log.action === 'delete' && log.old_data) {
                html += '<div class="history-log-details">';
                html += '<strong>Deleted:</strong> ';
                html += 'Fullname: ' + (log.old_data.fullname || 'N/A') + ', ';
                html += 'Phone: ' + formatPhoneForDisplay(log.old_data.phone);
                html += '</div>';
            }
            
            html += '</div>';
        });
        
        html += '</div>';
        historyContent.html(html);
    }
    
    // Get action badge
    function getActionBadge(action) {
        var badges = {
            'insert': { label: 'Created', class: 'success' },
            'update': { label: 'Updated', class: 'info' },
            'delete': { label: 'Deleted', class: 'danger' },
            'point_update': { label: 'Point Updated', class: 'warning' }
        };
        
        return badges[action] || { label: action.charAt(0).toUpperCase() + action.slice(1), class: 'default' };
    }
    
    // Log Details Modal Handler (for logs page)
    var logModal = $('#mac-referral-log-modal');
    var logDetailsContent = $('#log-details-content');
    
    $(document).on('click', '.view-log-details-link', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var logId = $(this).data('log-id');
        if (!logId) {
            return;
        }
        
        logModal.fadeIn(300);
        logDetailsContent.html('<div style="text-align: center; padding: 20px;"><span class="spinner is-active"></span><p>Loading log details...</p></div>');
        
        // Load log details via AJAX
        $.ajax({
            url: macReferralAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'mac_referral_get_log_details',
                log_id: logId,
                nonce: macReferralAjax.nonce
            },
            success: function(response) {
                if (response.success && response.data.log) {
                    displayLogDetails(response.data.log);
                } else {
                    logDetailsContent.html('<div style="text-align: center; padding: 20px;"><p style="color: red;">Error loading log details. Please try again.</p></div>');
                }
            },
            error: function() {
                logDetailsContent.html('<div style="text-align: center; padding: 20px;"><p style="color: red;">Error loading log details. Please try again.</p></div>');
            }
        });
    });
    
    // Close log modal
    logModal.find('.mac-referral-modal-close').on('click', function() {
        logModal.fadeOut(300);
        logDetailsContent.html('');
    });
    
    // Display log details
    function displayLogDetails(log) {
        var actionBadge = getActionBadge(log.action);
        var logDate = log.log_date_formatted || log.log_date;
        
        var html = '<div class="log-details-wrapper">';
        
        // Header info
        html += '<div class="log-details-header">';
        html += '<table class="form-table" style="margin-bottom: 20px;">';
        html += '<tr><th>Log ID:</th><td>' + log.id + '</td></tr>';
        html += '<tr><th>Referral ID:</th><td><a href="' + macReferralAjax.ajaxurl.replace('admin-ajax.php', 'admin.php?page=mac-referral') + '">' + log.referral_id + '</a></td></tr>';
        html += '<tr><th>Action:</th><td><span class="action-badge action-badge-' + actionBadge.class + '">' + actionBadge.label + '</span></td></tr>';
        html += '<tr><th>User:</th><td>' + (log.user_name || 'N/A') + ' (ID: ' + (log.user_id || 'N/A') + ')</td></tr>';
        html += '<tr><th>Date:</th><td>' + logDate + '</td></tr>';
        if (log.ip_address) {
            html += '<tr><th>IP Address:</th><td>' + log.ip_address + '</td></tr>';
        }
        html += '</table>';
        html += '</div>';
        
        // Details based on action type
        html += '<div class="log-details-content">';
        
        if (log.action === 'point_update') {
            html += '<h3>Point Update Details</h3>';
            html += '<table class="form-table">';
            html += '<tr><th>Old Points:</th><td><strong>' + (log.old_point !== null ? log.old_point : 'N/A') + '</strong></td></tr>';
            html += '<tr><th>New Points:</th><td><strong>' + (log.new_point !== null ? log.new_point : 'N/A') + '</strong></td></tr>';
            if (log.point_change !== null) {
                var changeColor = log.point_change > 0 ? 'green' : 'red';
                html += '<tr><th>Point Change:</th><td style="color: ' + changeColor + ';"><strong>' + (log.point_change > 0 ? '+' : '') + log.point_change + '</strong></td></tr>';
            }
            html += '</table>';
        } else if (log.action === 'update' && log.changes) {
            html += '<h3>Changes</h3>';
            html += '<table class="form-table">';
            for (var field in log.changes) {
                if (log.changes.hasOwnProperty(field)) {
                    var fieldLabel = field.charAt(0).toUpperCase() + field.slice(1).replace(/_/g, ' ');
                    var oldValue = log.changes[field].old || 'N/A';
                    var newValue = log.changes[field].new || 'N/A';
                    
                    // Format phone numbers nếu field là phone hoặc phone_referral
                    if (field === 'phone' || field === 'phone_referral') {
                        oldValue = formatPhoneForDisplay(oldValue);
                        newValue = formatPhoneForDisplay(newValue);
                    }
                    
                    html += '<tr>';
                    html += '<th>' + fieldLabel + ':</th>';
                    html += '<td>';
                    html += '<span style="color: red; text-decoration: line-through;">' + oldValue + '</span> ';
                    html += '→ ';
                    html += '<span style="color: green; font-weight: bold;">' + newValue + '</span>';
                    html += '</td>';
                    html += '</tr>';
                }
            }
            html += '</table>';
            
            // Show old and new data if available
            if (log.old_data || log.new_data) {
                html += '<h3 style="margin-top: 20px;">Full Data</h3>';
                if (log.old_data) {
                    html += '<h4>Old Data:</h4>';
                    html += '<pre style="background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto;">' + JSON.stringify(log.old_data, null, 2) + '</pre>';
                }
                if (log.new_data) {
                    html += '<h4>New Data:</h4>';
                    html += '<pre style="background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto;">' + JSON.stringify(log.new_data, null, 2) + '</pre>';
                }
            }
        } else if (log.action === 'insert' && log.new_data) {
            html += '<h3>Created Data</h3>';
            html += '<pre style="background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto;">' + JSON.stringify(log.new_data, null, 2) + '</pre>';
        } else if (log.action === 'delete' && log.old_data) {
            html += '<h3>Deleted Data</h3>';
            html += '<pre style="background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto;">' + JSON.stringify(log.old_data, null, 2) + '</pre>';
        } else {
            html += '<p>No detailed information available for this log entry.</p>';
        }
        
        html += '</div>';
        html += '</div>';
        
        logDetailsContent.html(html);
    }
});

