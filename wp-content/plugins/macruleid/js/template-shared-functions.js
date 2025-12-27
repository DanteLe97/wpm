/**
 * Template Shared Functions
 * Common functions used by both template-library-admin.js and template-metabox.js
 */

window.TemplateSharedFunctions = (function ($) {
    'use strict';

    return {
        /**
         * Hiển thị thông báo validation
         */
        showValidationMessage: function (message, isError = false) {
            $('.template-validation').remove();
            if (message) {
                $('<div>').addClass('template-validation').addClass(isError ? 'template-validation--error' : 'template-validation--success').text(message).insertAfter('#custom_template_id');
            }
        },

        /**
         * Thêm nút Edit vào giao diện
         */
        addEditButton: function () {
            if ($('#template-edit-btn').length > 0) {
                return; // Already exists
            }

            var $publishBtn = $('#mac-custom-publish-btn');
            if ($publishBtn.length === 0) {
                return;
            }

            var $editButton = $('<button>').attr('id', 'template-edit-btn').addClass('button button-secondary template-edit-btn').html('<span class="dashicons dashicons-edit"></span> Edit').hide();

            $publishBtn.after($editButton);

            $editButton.on('click', function (e) {
                e.preventDefault();
                TemplateSharedFunctions.enableEditing();
            });
        },

        /**
         * Bật chế độ chỉnh sửa
         */
        enableEditing: function () {
            var $button = $('#mac-custom-publish-btn');
            var $input = $('#custom_template_id');
            var $editButton = $('#template-edit-btn');

            $button.prop('disabled', false).removeClass('button-disabled').addClass('button-primary').html('<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="publish-icon"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17,21 17,13 7,13 7,21"/><polyline points="7,3 7,8 15,8"/></svg> Publish');
            $input.prop('disabled', false);
            $editButton.hide();

            // Validate lại template ID hiện tại
            var currentValue = $input.val();
            if (currentValue) {
                TemplateSharedFunctions.validateTemplateId(currentValue).catch(() => {});
            }
        },

        /**
         * Cập nhật trạng thái nút Update và field input
         */
        updateButtonState: function (isValid, isPublished = false) {
            var $button = $('#mac-custom-publish-btn');
            var $input = $('#custom_template_id');
            var $editButton = $('#template-edit-btn');

            if (isPublished) { // Sau khi publish thành công
                $button.prop('disabled', true).addClass('button-disabled').removeClass('button-primary').html('Published');
                $input.prop('disabled', true);
                $editButton.show(); // Hiện nút Edit
                TemplateSharedFunctions.showValidationMessage(''); // Xóa thông báo validation
            } else if (isValid) { // Template ID hợp lệ, chưa publish
                $button.prop('disabled', false).removeClass('button-disabled').addClass('button-primary').html('<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="publish-icon"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17,21 17,13 7,13 7,21"/><polyline points="7,3 7,8 15,8"/></svg> Publish');
                $input.prop('disabled', false);
                $editButton.hide(); // Ẩn nút Edit
            } else { // Template ID không hợp lệ
                $button.prop('disabled', true).addClass('button-disabled').removeClass('button-primary').html('Update Template ID');
                $input.prop('disabled', false);
                $editButton.hide(); // Ẩn nút Edit
            }
        },

        /**
         * Kiểm tra template ID
         */
        validateTemplateId: function (templateId) {
            return new Promise((resolve, reject) => {
                if (! templateId) {
                    TemplateSharedFunctions.updateButtonState(false);
                    TemplateSharedFunctions.showValidationMessage('Vui lòng nhập Template ID', true);
                    reject({message: 'No template ID'});
                    return;
                }

                if (typeof ajaxurl === 'undefined') {
                    TemplateSharedFunctions.showValidationMessage('Lỗi: ajaxurl không được định nghĩa', true);
                    reject({message: 'ajaxurl undefined'});
                    return;
                }

                var nonce = $('#custom_metabox_nonce').val();
                if (! nonce) {
                    TemplateSharedFunctions.showValidationMessage('Lỗi: Nonce không tìm thấy', true);
                    reject({message: 'No nonce'});
                    return;
                }

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'check_template_id',
                        template_id: templateId,
                        nonce: nonce
                    },
                    success: function (response) {
                        if (response.success) {
                            if (! response.data.is_published) {
                                TemplateSharedFunctions.showValidationMessage(response.data.message, false);
                                TemplateSharedFunctions.updateButtonState(true);
                            } else {
                                TemplateSharedFunctions.updateButtonState(true, true);
                            }
                            resolve(response.data);
                        } else {
                            TemplateSharedFunctions.showValidationMessage(response.data.message, true);
                            TemplateSharedFunctions.updateButtonState(false);
                            reject(response.data);
                        }
                    },
                    error: function (xhr, status, error) {
                        TemplateSharedFunctions.showValidationMessage('Có lỗi xảy ra khi kiểm tra Template ID', true);
                        TemplateSharedFunctions.updateButtonState(false);
                        reject({message: 'Network error'});
                    }
                });
            });
        },

        /**
         * Kiểm tra template ID ban đầu
         */
        checkInitialState: function () {
            var $input = $('#custom_template_id');
            var templateId = $input.val();
            var $button = $('#mac-custom-publish-btn');
            var postId = $button.data('post-id');

            // Also try to get from templateMetaboxData if available
            if (! postId && typeof templateMetaboxData !== 'undefined') {
                postId = templateMetaboxData.post_id;
            }

            if (! templateId) {
                TemplateSharedFunctions.updateButtonState(false);
                return;
            }

            if (! postId || postId === 0) {
                TemplateSharedFunctions.updateButtonState(false);
                $input.prop('disabled', false);
                return;
            }

            // Disable controls initially
            $input.prop('disabled', true);
            $button.prop('disabled', true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'check_template_id',
                    template_id: templateId,
                    post_id: postId,
                    nonce: $('#custom_metabox_nonce').val()
                },
                success: function (response) {
                    if (response.success) {
                        TemplateSharedFunctions.updateButtonState(true, response.data.is_published);
                    } else {
                        TemplateSharedFunctions.updateButtonState(false);
                    }
                },
                error: function (xhr, status, error) {
                    TemplateSharedFunctions.updateButtonState(false);
                }
            });
        },

        /**
         * Ngăn form WordPress submit khi nhấn Enter trong input template ID
         */
        preventFormSubmissionOnEnter: function () {
            $('#post').on('submit', function (e) {
                if ($(document.activeElement).attr('id') === 'custom_template_id') {
                    e.preventDefault();
                }
            });
        },

        /**
         * Xử lý nút Reset Template
         */
        handleResetButton: function () {
            $(document).on('click', '#reset-template-btn', function (e) {
                e.preventDefault();

                if (!confirm('Bạn có chắc muốn xóa dữ liệu template hiện tại?')) {
                    return;
                }

                var $button = $(this);
                var postId = $('#mac-custom-publish-btn').data('post-id');

                // Also try to get from templateMetaboxData
                if (! postId && typeof templateMetaboxData !== 'undefined') {
                    postId = templateMetaboxData.post_id;
                }

                if (! postId || postId === 0) {
                    alert('Không thể reset template cho bài viết chưa được lưu. Vui lòng lưu bài viết trước.');
                    return;
                }

                var originalText = $button.html();
                $button.prop('disabled', true).html('<span class="dashicons dashicons-update-alt loading-spinner"></span> Đang xử lý...');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'reset_template',
                        post_id: postId,
                        nonce: $button.data('nonce')
                    },
                    success: function (response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Có lỗi xảy ra: ' + (
                                response.data.message || 'Unknown error'
                            ));
                            $button.prop('disabled', false).html(originalText);
                        }
                    },
                    error: function (xhr, status, error) {
                        alert('Có lỗi khi kết nối với server. Vui lòng thử lại sau.');
                        $button.prop('disabled', false).html(originalText);
                    }
                });
            });
        },

        /**
         * Xử lý nút Update/Publish template
         */
        handlePublishButton: function () {
            $(document).on('click', '#mac-custom-publish-btn', function (e) {
                e.preventDefault();

                var templateId = $('#custom_template_id').val();

                if (! templateId) {
                    TemplateSharedFunctions.showValidationMessage('Vui lòng nhập Template ID', true);
                    return;
                }

                var $button = $(this);
                if ($button.prop('disabled')) {
                    return;
                }

                var postId = $button.data('post-id');

                // Also try to get from templateMetaboxData
                if (! postId && typeof templateMetaboxData !== 'undefined') {
                    postId = templateMetaboxData.post_id;
                }

                if (! postId || postId === 0) {
                    alert('Không thể áp dụng template cho bài viết chưa được lưu. Vui lòng lưu bài viết trước.');
                    return;
                }

                var originalText = $button.html();
                $button.prop('disabled', true).html('<span class="dashicons dashicons-update-alt loading-spinner"></span> Đang xử lý...');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'save_template_id',
                        template_id: templateId,
                        post_id: postId,
                        nonce: $('#custom_metabox_nonce').val()
                    },
                    success: function (response) {
                        if (response.success) { // Redirect đến edit page thay vì submit form
                            if (response.data.redirect_url) {
                                window.location.href = response.data.redirect_url;
                            } else { // Fallback: reload trang hiện tại
                                location.reload();
                            }
                        } else {
                            TemplateSharedFunctions.showValidationMessage('Có lỗi khi lưu template: ' + response.data.message, true);
                            $button.prop('disabled', false).html(originalText);
                        }
                    },
                    error: function (xhr, status, error) {
                        TemplateSharedFunctions.showValidationMessage('Có lỗi khi kết nối với server', true);
                        $button.prop('disabled', false).html(originalText);
                    }
                });
            });
        },

        /**
         * Setup input validation với timeout
         */
        setupInputValidation: function () {
            var validateTimeout;
            $(document).on('input', '#custom_template_id', function () {
                var value = $(this).val();

                clearTimeout(validateTimeout);
                $('.template-validation').remove();

                if (! value) {
                    TemplateSharedFunctions.updateButtonState(false);
                    return;
                }

                validateTimeout = setTimeout(function () {
                    TemplateSharedFunctions.validateTemplateId(value).catch(() => {});
                }, 500);
            });
        },

        /**
         * Khởi tạo tất cả shared handlers
         */
        initializeSharedHandlers: function () {
            TemplateSharedFunctions.preventFormSubmissionOnEnter();
            TemplateSharedFunctions.handleResetButton();
            TemplateSharedFunctions.handlePublishButton();
            TemplateSharedFunctions.setupInputValidation();
        }
    };

})(jQuery);
