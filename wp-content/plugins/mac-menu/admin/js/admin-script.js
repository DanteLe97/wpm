/* js
-- coder LTP MAC 
----------------------------------*/
var MAC = MAC || {};
(function ($) {
    // USE STRICT
    "use strict";
    var $window = $(window);
    var $document = $(document);
    MAC.MAC_Media = {
        init: function () {
            MAC.MAC_Media.mediaMultipleUpload();
            MAC.MAC_Media.mediaUpload();
        },

        /*  ==================
            ***** mediaUploader
            ================== 
        */
        mediaMultipleUpload: function (button = '') {

            var btnUploadGallery = $('.upload-gallery-button');
            btnUploadGallery.each(function () {

                var file_frame;
                var selected_images = [];
                var gallery = $(this).parents('.mac-gallery-wrap').find('.gallery');
                var imgAttachmentIds = $(this).parents('.mac-gallery-wrap').find('.image-attachment-ids');

                if (imgAttachmentIds.val() != '') {
                    selected_images.push(imgAttachmentIds.val());
                }

                $(this).off('click').click(function (event) {
                    event.preventDefault();
                    // If the media frame already exists, reopen it.
                    if (file_frame) {
                        file_frame.open();
                        return;
                    }
                    file_frame = wp.media.frames.file_frame = wp.media({
                        title: 'Select or Upload Images',
                        button: {
                            text: 'Use these images'
                        },
                        multiple: 'add'  // Set to true to allow multiple files to be selected
                    });
                    // Chọn các ảnh đã có sẵn ban đầu
                    file_frame.on('open', function () {
                        var selection = file_frame.state().get('selection');
                        var filArray = imgAttachmentIds.val().split('|');
                        var array = [];
                        filArray.filter(function (item) {
                            array.push(item);
                        });
                        array.forEach(function (id) {
                            var attachment = wp.media.attachment(id);
                            attachment.fetch();
                            selection.add(attachment ? [attachment] : []);
                        });
                    });

                    // When images are selected, run a callback.
                    file_frame.on('select', function () {
                        var attachments = file_frame.state().get('selection').toArray();
                        selected_images = [];
                        gallery.html('');
                        imgAttachmentIds.val('');
                        updateHiddenInput();
                        attachments.forEach(function (attachment) {
                            attachment = attachment.toJSON();
                            selected_images.push(attachment);
                            displayImage(attachment);
                        });
                        updateHiddenInput();
                    });

                    // Finally, open the modal
                    file_frame.open();
                });

                function displayImage(attachment) {
                    if (attachment.url != '' && attachment.url != null) {
                        var imageHtml = '<div class="image-preview" data-id="' + attachment.id + '">';
                        imageHtml += '<img src="' + attachment.url + '">';
                        imageHtml += '<span class="remove-img-button" data-id="' + attachment.id + '">x</span>';
                        imageHtml += '</div>';
                        gallery.append(imageHtml);
                    }
                }

                function updateHiddenInput(id = null) {
                    if (imgAttachmentIds.val() != '') {
                        var filteredArray = imgAttachmentIds.val().split('|');
                        var ids = selected_images.map(function (img) { return img.id; });

                        if (id) {
                            var newArray = [];
                            filteredArray.filter(function (item) {
                                if (item != id) {
                                    newArray.push(item);
                                }
                            });
                            var idsvalue = [];
                            newArray.forEach(element => {
                                if (element != '' && element != null) {
                                    idsvalue.push(element);
                                }
                            });
                            idsvalue = idsvalue.join('|');
                            imgAttachmentIds.val(idsvalue);
                        } else {
                            var value = imgAttachmentIds.val() + ids.join('|');
                            imgAttachmentIds.val(value);
                        }

                    } else {
                        var ids = selected_images.map(function (img) { return img.id; });
                        imgAttachmentIds.val(ids.join('|'));
                    }

                }

                gallery.on('click', '.remove-img-button', function (event) {
                    event.preventDefault();
                    var id = $(this).data('id');
                    selected_images = selected_images.filter(function (img) {
                        return img.id !== id;
                    });
                    $(this).parent().remove();
                    updateHiddenInput(id);
                });
            });

        },

        mediaUpload: function (button = '') {

            if (button) {
                var addMedia = button;
            } else {
                var addMedia = ('.add_media_button');
            }
            $(addMedia).each(function () {
                $(this).on('click', function (e) {
                    var mediaUploader;
                    var partentElement = $(this).parent('.mac-add-media');
                    e.preventDefault();
                    if (mediaUploader) {
                        mediaUploader.open();
                        return;
                    }
                    mediaUploader = wp.media.frames.file_frame = wp.media({
                        title: 'Choose Media',
                        button: {
                            text: 'Choose Media'
                        },
                        multiple: false
                    });
                    mediaUploader.on('select', function () {
                        var attachment = mediaUploader.state().get('selection').first().toJSON();
                        if (attachment.type === 'image') {
                            partentElement.find('.custom_media_url').val(attachment.url);
                            partentElement.find('.media_preview').attr('src', attachment.url).show();
                            partentElement.find('.remove_media_button').show()
                        } else {
                            partentElement.find('.media_preview').hide();
                        }
                    });
                    mediaUploader.open();
                });
                $('.remove_media_button').on('click', function (e) {
                    var partentElement = $(this).closest('.mac-add-media');
                    e.preventDefault();
                    partentElement.find('.custom_media_url').val('');
                    partentElement.find('.media_preview').attr('src', '').hide();
                    $(this).hide();
                });

            });
        },
    }
    MAC.documentOnReady = {
        init: function () {
            MAC.documentOnReady.sortableDefault();
            MAC.documentOnReady.sortableRepeater();
            //MAC.documentOnReady.sortableRepeater1();
            MAC.documentOnReady.sortableRepeaterChildCat();
            MAC.documentOnReady.formRepeater();
            MAC.MAC_Media.init();
            MAC.documentOnReady.isTable();
            MAC.documentOnReady.collapsible();
            MAC.documentOnReady.confirmDialog();
            MAC.documentOnReady.confirmDialogFormSubmit();
            MAC.documentOnReady.confirmDialogUpdateKey();
            MAC.documentOnReady.onOffSelect();
            MAC.documentOnReady.switcherBTN();
            MAC.documentOnReady.duplicateBTN();
            MAC.documentOnReady.changeTextBTN();
            MAC.documentOnReady.handleRepeaterDelete(); // Xử lý cập nhật position khi delete item
            MAC.documentOnReady.callApiDomainManager();
            MAC.documentOnReady.selectionData();
            MAC.documentOnReady.debugFormSubmit();
        },

        selectionData: function () {
            var selection = $('.mac-selection-data');
            $(selection).each(function () {
                $(this).on('change', function () {
                    var $select = $(this);
                    var selectedData = $select.val();
                    // Xoá các class bắt đầu bằng 'mac-selection-data-'
                    var classes = $select.attr('class').split(' ').filter(function (c) {
                        return !c.startsWith('mac-selection-value-');
                    });
                    $select.attr('class', classes.join(' '));

                    // Thêm class mới nếu có data
                    if (selectedData !== undefined && selectedData !== '') {
                        $select.addClass('mac-selection-value-' + selectedData);
                    }
                });
            });
        },

        callApiDomainManager: function () {
            $('#kvp-form').on('submit', function (e) {
                e.preventDefault();
                if ($('#kvp-key-input').val() != '' && $('#kvp-key-input').val() != 'MAC Menu') {
                    var key = $('#kvp-key-input').val();
                    $.ajax({
                        url: kvp_params.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'kvp_handle_ajax_request',
                            key: key
                        },
                        success: function (response) {
                            location.reload();
                            if (response.success) {
                                $('#kvp-result').text(response.data).css('color', 'green');
                            } else {
                                $('#kvp-result').text(response.data).css('color', 'red');
                            }
                        },
                        error: function () {
                            $('#kvp-result').text('Error occurred.').css('color', 'red');
                        }
                    });
                }

            });
        },

        changeTextBTN: function () {
            var btnChangeText = $('.repater-item__name');
            $(btnChangeText).each(function () {
                $(this).on('input', function () {
                    var $textHeading = $(this).closest('.repater-item-wrap').find('.mac-heading-title');
                    $textHeading.text($(this).val()); // Cập nhật div với giá trị nhập vào
                });
            });

        },
        
        /**
         * Cập nhật position cho tất cả items trong repeater list
         * Hỗ trợ cả position-item và category-position
         * @param {jQuery} $list - jQuery object của .repeater-list-item hoặc .ui-sortable
         */
        updateRepeaterPositions: function ($list) {
            if (!$list || $list.length === 0) {
                return;
            }
            
            // Cập nhật position cho tất cả children div (theo logic của sortableRepeater)
            $list.children('div').each(function (index) {
                var $item = $(this);
                
                // 1. Cập nhật position-item
                $item.find('.position-item > input').val(index);
                
                // 2. Cập nhật category-position (theo logic của sortableRepeater)
                $item.find('.form-table input.category-position').val(index);
                $item.find('.form-table input.category-position').attr('value', index);
                
                // 3. Cập nhật category-position cho list-item repater-item-wrap (theo logic của duplicateBTN)
                $item.children('.form-table').find('tbody > tr > td > input.category-position').val(index);
            });
        },
        
        /**
         * Xử lý cập nhật position sau khi delete item
         * Sử dụng event delegation để bắt sự kiện click trên [data-repeater-delete]
         */
        handleRepeaterDelete: function () {
            // Sử dụng event delegation để bắt sự kiện từ các element được tạo động
            $(document).off('click', '[data-repeater-delete]').on('click', '[data-repeater-delete]', function() {
                var $button = $(this);
                var $formRepeater = $button.closest('.form-repeater');
                
                // Chờ jquery.repeater.js xử lý remove xong (setIndexes được gọi)
                // Thời gian delay để đảm bảo DOM đã được cập nhật
                setTimeout(function() {
                    // Tìm lại .repeater-list-item sau khi item đã bị xóa
                    var $list = $formRepeater.find('.repeater-list-item').first();
                    
                    // Nếu không tìm thấy .repeater-list-item, thử tìm .ui-sortable
                    if ($list.length === 0) {
                        $list = $formRepeater.find('.ui-sortable').first();
                    }
                    
                    // Nếu vẫn không tìm thấy, thử tìm từ .repeater-list-item.sortable
                    if ($list.length === 0) {
                        $list = $formRepeater.find('.repeater-list-item.sortable').first();
                    }
                    
                    // Cập nhật position cho tất cả items còn lại
                    if ($list.length > 0) {
                        MAC.documentOnReady.updateRepeaterPositions($list);
                    }
                }, 150); // Delay 150ms để đảm bảo jquery.repeater.js đã xử lý xong
            });
        },
        
        duplicateBTN: function () {
            $('.form-repeater').off('click').on('click', 'input[data-repeater-duplicate]', function () {
                var $item = $(this).closest('.repeater-item');
                //var $clone = $item.clone();
                $(this).closest('.form-repeater').find('> input[data-repeater-create]').click();
                // var $itemNew = $(this).closest('.form-repeater').find('> .repeater-item:last-child');

                setTimeout(() => {
                    var $itemNew = $(this).closest('.form-repeater').find('.repeater-item').last();
                    if ($itemNew.length === 0) {
                        console.error('Target element not found.');
                        return;
                    }
                    cloneItem($item, $itemNew);
                    MAC.documentOnReady.collapsible();
                    MAC.documentOnReady.switcherBTN();
                    $(this).closest('.ui-sortable').children('.ui-sortable-handle').each(function (index) {
                        $(this).find('.position-item > input').val(index);
                        $(this).children('.form-table').find('tbody > tr > td > input.category-position').val(index);
                    });
                }, 100); // Tăng lên 200–300ms nếu vẫn lỗi
            });
            // $('.form-repeater-1').off('click').on('click', 'input[data-repeater-1-duplicate]', function () {
            //     var $item = $(this).closest('.repeater-item');
            //     var $repeater1 = $(this).closest('.form-repeater-1');
            //     var $template = $repeater1.find('> .repeater-list-item > [data-repeater-item]').first().clone();
            //     var $listItems = $repeater1.find('> .repeater-list-item > [data-repeater-item]');
            //     var newIndex = $listItems.length;
            //     var idCatMenu = $repeater1.closest('.form-table').find('.id-cat-menu').val();
                
            //     // Update all input names with new index
            //     $template.find('input, textarea, select').each(function() {
            //         var $input = $(this);
            //         var name = $input.attr('name');
            //         if (name) {
            //             name = name.replace(/\[\d+\]/, '[' + newIndex + ']');
            //             $input.attr('name', name);
            //         }
            //     });
                
            //     $template.slideDown();
            //     $repeater1.find('>[data-repeater-list]').append($template);
                
            //     // Clone content from original item
            //     cloneItem($item, $template);
                
            //     // Initialize the new item
            //     var btn_load = $template.find('.add_media_button');
            //     MAC.MAC_Media.mediaUpload(btn_load);
            //     MAC.documentOnReady.collapsible();
            //     MAC.documentOnReady.duplicateBTN();
            //     MAC.documentOnReady.changeTextBTN();
            //     MAC.documentOnReady.switcherBTN();
                
            //     // Handle price list for new item
            //     var totalCol = $repeater1.closest('.form-table').find('.mac-table-total-col');
            //     let totalColNumber = parseInt($(totalCol).find('.mac-is-selection').val());
            //     let $priceListContainer = $template.find('.form-repeater-child-1 .repeater-list-item');
                
            //     if (totalColNumber > 0) {
            //         for (let y = 1; y < totalColNumber; y++) {
            //             let htmlItem = `<div data-repeater-item class="ui-sortable-handle">
            //                 <label>Price: </label>
            //                 <input type="text" name="form_${idCatMenu}_group-repeater[${newIndex}][price-list][${y}][price]" value="">
            //             </div>`;
            //             $priceListContainer.append(htmlItem);
            //         }
            //     }
            // });
            function cloneItem($source, $target) {
                if ($target.length === 0) {
                    console.error('Target element not found.');
                    return;
                }
                if ($source.length === 0) {
                    console.error('Source element not found.');
                    return;
                }

                var newValueHeading = $source.find('.mac-heading-title').html();
                if (newValueHeading) {
                    $target.find('.mac-heading-title').html(newValueHeading);
                }
                $target.find('input[type="text"], textarea').each(function (index) {
                    var $input = $(this);
                    var newValue = $source.find('input[type="text"], textarea').eq(index).val();
                    $input.val(newValue);
                });
                $target.find('img').each(function (index) {
                    var $img = $(this);
                    var newSrc = $source.find('img').eq(index).attr('src');
                    $img.attr('src', newSrc);
                });
            }

        },
        switcherBTN: function () {
            var switcher = $('.mac-switcher-btn');
            $(switcher).each(function () {
                $(this).off('click').click(function () {

                    if ($(this).hasClass('active')) {
                        $(this).removeClass('active');
                        $(this).find('input').val('0').attr('value', '0');
                    } else {
                        $(this).addClass('active');
                        $(this).find('input').val('1').attr('value', '1');
                    }
                });
            });
        },
        onOffSelect: function () {
            var childCat = $('.is-child-category');
            $(childCat).each(function () {
                if ($(this).find('.list-item').length > 0) {
                    var isCatPrimary = $(this).parents('#post-body-content').find('.is-primary-category');
                    isCatPrimary.find('.mac-is-table').attr('disabled', 'disabled');
                }
            });
        },
        confirmDialogFormSubmit: function () {

            $('.btn-delete-menu').on('click', function (event) {
                event.preventDefault();
                var tableDataName = $(this).parents('.form-add-cat-menu').find('#input-delete-data').attr('data-table');
                $(this).parents('.form-add-cat-menu').find('#input-delete-data').attr('value', tableDataName);
                $('#overlay, #confirmDialog').show();
            });
            $('.btn-delete-cat-menu').on('click', function (event) {
                event.preventDefault();
                var idDelete = $(this).parents('.list-item').find('.id-cat-menu').val();
                $('#confirmDialog').find('input').attr('value', idDelete);
                $('#overlay, #confirmDialog').show();
            });

            $('#export-table-data').on('click', function (event) {
                event.preventDefault();
                $('#overlayExport, #confirmDialogExport').show();

            });
        },
        confirmDialog: function () {

            $('#confirmOk').on('click', function () {
                $('#posts-filter').off('submit').submit();
            });

            $('#confirmOkExport').on('click', function () {
                $('#export-table-form').off('submit').submit();
                $('#overlay, #confirmDialog, #confirmDialogExport').hide();
            });

            $('#confirmCancel').on('click', function () {
                $('#overlay, #confirmDialog, #confirmDialogExport').hide();
            });

            $('#confirmCancelExport').on('click', function () {
                $('#overlay, #confirmDialog, #confirmDialogExport').hide();
            });

            $('#overlay').on('click', function () {
                $('#overlay, #confirmDialog, #confirmDialogExport').hide();
            });
            $('#overlayExport').on('click', function () {
                $('#overlayExport, #confirmDialog, #confirmDialogExport').hide();
            });
        },
        confirmDialogUpdateKey: function () {
            if ($('#overlayConfirmDialogUpdateKey').length > 0) {
                $('#confirmUpdateKeyOk').on('click', function () {
                    $('#overlayConfirmDialogUpdateKey.overlay, #confirmDialogUpdateKey.confirm-dialog').hide();
                    $('#formCategorySettingsMain').off('submit').submit();
                });

                $('#overlayConfirmDialogUpdateKey').on('click', function () {
                    $('#overlayConfirmDialogUpdateKey.overlay, #confirmDialogUpdateKey.confirm-dialog').hide();
                    $('#formCategorySettingsMain').off('submit').submit();
                });
            }

        },
        /*  ==================
            ***** button collapsible 
            ================== 
        */
        collapsible: function () {
            var collapsible = $('.mac-collapsible-btn');
            $(collapsible).each(function () {
                $(this).off('click').click(function () {
                    var listItem = $(this).closest('.repeater-list-item');
                    if ($(this).hasClass('collapsible-show')) {
                        $(this).removeClass('collapsible-show');
                        listItem.sortable("enable");
                    } else {
                        listItem.find(
                            '> .list-item > .mac-collapsible-btn, > .repeater-item > .repater-item-wrap > .mac-collapsible-btn'
                        ).removeClass('collapsible-show');
                        if ($(this).hasClass('mac-list-cat-child__heading')) {
                            var listCatChild = $(this).closest('.mac-list-cat-child');
                            listCatChild.find('.mac-collapsible-btn').removeClass('collapsible-show');
                        }
                        listItem.sortable("disable");
                        $(this).addClass('collapsible-show');
                    }
                });

            });
        },
        /*  ==================
            ***** Sortable
            ================== 
        */
        sortableDefault: function () {
            var sortable = $('.sortable > tbody');
            $(sortable).each(function () {
                $(this).sortable({
                    update: function (event, ui) {
                        // Lấy danh sách vị trí mới
                        $(this).find('tr').each(function (index) {
                            //$(this).find('td.position').text(index);
                            $(this).find('td.position').attr('order', index);
                        });
                    }
                });
                $(this).disableSelection();

            });
        },
        sortableRepeater: function () {
            var sortable = $('.repeater-list-item.sortable');
            $(sortable).each(function () {
                $(this).sortable({
                    update: function (event, ui) {
                        // Lấy danh sách vị trí mới
                        // $(this).children('.ui-sortable-handle').each(function (index) {
                        $(this).children('div').each(function (index) {
                            //console.log(index);
                            $(this).find('.position-item > input').val(index);
                            $(this).find('.form-table input.category-position').val(index);
                            $(this).find('.form-table input.category-position').attr('value', index);
                            //console.log($(this).find('.form-table input.category-position').val());
                            // $(this).children('.form-table').find('tbody > tr > td > input.category-position').val(index);
                            // console.log($(this).children('.form-table').find('tbody > tr > td > input.category-position'));
                            
                        });
                    }
                });
                $(this).disableSelection();
                $(this).on('keydown', 'input, textarea', function (event) {
                    if (event.ctrlKey && event.key === 'a') {
                        $(this).select();
                        event.preventDefault();
                    }
                });
            });
        },
        // sortableRepeater1: function () {
            
        //     var sortable = $('.mac-list-cat-child .repeater-list-item.sortable');
        //     $(sortable).each(function () {
                
        //     //console.log($(this).children('.ui-sortable-handle').find('.position-item > input'));
        //         $(this).sortable({
        //             update: function (event, ui) {
        //                 // Lấy danh sách vị trí mới
                        
        //                 $(this).children('div').each(function (index) {
        //                     console.log(index);
        //                     $(this).find('.position-item > input').val(index);
        //                     $(this).children('.form-table').find('tbody > tr > td > input.category-position').val(index);
        //                 });
        //             }
        //         });
        //         $(this).disableSelection();
        //         $(this).on('keydown', 'input, textarea', function (event) {
        //             if (event.ctrlKey && event.key === 'a') {
        //                 $(this).select();
        //                 event.preventDefault();
        //             }
        //         });
        //     });
        // },
        sortableRepeaterChildCat: function () {
            var sortable = $('.mac-list-cat-child.sortable');
            $(sortable).each(function () {
                $(this).sortable({
                    update: function (event, ui) {
                        // Lấy danh sách vị trí mới
                        $(this).find('.list-item.ui-sortable-handle').each(function (index) {
                            $(this).find('td input.position').attr('value', index);
                        });
                    }
                });
                $(this).disableSelection();
                $(this).on('keydown', 'input, textarea', function (event) {
                    if (event.ctrlKey && event.key === 'a') {
                        $(this).select();
                        event.preventDefault();
                    }
                });
            });
        },
        
        /*  ==================
            ***** Repeater
            ================== 
        */

        uniqid: function (prefix = '', moreEntropy = false) {
            let result;
            const base = Math.floor(new Date().getTime() / 1000).toString(16);
            const seed = Math.floor(Math.random() * 0x75bcd15).toString(16);
            result = base + seed;

            if (moreEntropy) {
                result += (Math.floor(Math.random() * 10)).toString();
            }

            return prefix + result;
        },
        changeHTMLItemRepeater: function (html, editorId, idValues) {
            var htmlContent = html.html();
            var regex = new RegExp(editorId, 'g');
            var newHtmlContent = htmlContent.replace(regex, idValues);
            return newHtmlContent;
        },
        formRepeater: function () {
            console.log('=== MAC Menu: formRepeater() called ===');
            var repeater = $('.form-repeater');
            console.log('Found repeater elements:', repeater.length);
            if (repeater) {
                clearInterval(repeater);
            }
            $(repeater).each(function () {
                console.log('Initializing repeater for:', $(this));
                $(this).repeater({
                    initEmpty: false,
                    show: function () {
                        $(this).slideDown();
                        var btn_load = $(this).find('.add_media_button');
                        MAC.MAC_Media.mediaUpload(btn_load);
                        MAC.documentOnReady.collapsible();
                        MAC.documentOnReady.duplicateBTN();
                        MAC.documentOnReady.changeTextBTN();

                        $(this).closest('.repeater-list-item').children('div').each(function (index) {
                            console.log(index);
                            //$(this).find('.position-item > input').attr('value', index);
                            $(this).find('.position-item > input').val(index);
                        });
                        
                        var $formTable = $(this).closest('.form-table');
                        var totalCol = $formTable.find('.mac-table-total-col');
                        let totalColNumber = parseInt($(totalCol).find('.mac-is-selection').val());
                        let $listItem = $formTable.find('>tbody>tr>td>.form-repeater .repeater-list-item .repeater-item');
                        let $priceListContainer = $(this).find('.form-repeater-child .repeater-list-item');
                        let idCatMenu = $formTable.find('.id-cat-menu').val();
                        var idValues = MAC.documentOnReady.uniqid('description-');
                        
                        $(this).find('.mac-collapsible-btn').addClass('collapsible-show');
                        
                        // Handle price list items
                        if (totalColNumber > 0) {
                            let $currentPriceList = $priceListContainer.eq($listItem.length - 1);
                            let existingPriceItems = $currentPriceList.children('div').length;
                            
                            for (let y = existingPriceItems; y < totalColNumber; y++) {
                                let htmlItem = `<div data-repeater-item class="ui-sortable-handle">
                                    <label>Price: </label>
                                    <input type="text" name="form_${idCatMenu}_group-repeater[${$listItem.length - 1}][price-list][${y}][price]" value="">
                                </div>`;
                                $currentPriceList.append(htmlItem);
                            }
                        }
                        
                        // Handle editor
                        var editorId = $(this).find('.wp-editor-area').attr('id');
                        if (editorId) {
                            var htmlNewRepeater = MAC.documentOnReady.changeHTMLItemRepeater(
                                $(this).find('.mac-menu-custom-wp-editor'), 
                                editorId, 
                                idValues
                            );
                            $(this).find('.mac-menu-custom-wp-editor').html(htmlNewRepeater);
                        }
                        
                        if (typeof QTags !== 'undefined') {
                            QTags({ id: idValues });
                            QTags._buttonsInit();
                        }
                        
                        $(this).find('.switch-tmce').click(function () {
                            tinyMCE.execCommand('mceAddEditor', true, idValues);
                        });
                        
                        MAC.documentOnReady.switcherBTN();
                    },
                    hide: function (deleteElement) {
                        $(this).slideUp(deleteElement);
                    },
                    ready: function (setIndexes) {
                        /* Do something when the repeater is ready */
                    },
                    isFirstItemUndeletable: true,
                    repeaters: [{
                        selector: '.form-repeater-child',
                        initEmpty: false,
                        show: function () {
                            $(this).slideDown();
                        },
                        hide: function (deleteElement) {
                            $(this).slideUp(deleteElement);
                        },
                    }],
                });
            });

            // Handle form-repeater-1
            // var repeater1 = $('.form-repeater-1');
            // if (repeater1.length) {
            //     $(repeater1).each(function () {
            //         $(this).repeater({
            //             initEmpty: false,
            //             show: function () {
            //                 $(this).slideDown();
            //                 var btn_load = $(this).find('.add_media_button');
            //                 MAC.MAC_Media.mediaUpload(btn_load);
            //                 MAC.documentOnReady.collapsible();
            //                 MAC.documentOnReady.duplicateBTN();
            //                 MAC.documentOnReady.changeTextBTN();
            //                 MAC.documentOnReady.switcherBTN();
            //                 //MAC.documentOnReady.isTable();
                            
            //                 var $formTable = $(this).closest('.form-table');
            //                 var totalCol = $formTable.find('.mac-table-total-col');
            //                 let totalColNumber = parseInt($(totalCol).find('.mac-is-selection').val());
            //                 let $listItem = $(this).children('.repeater-list-item').children('.repeater-item');
            //                 let $priceListContainer = $(this).find('.form-repeater-child-1 .repeater-list-item');
            //                 let idCatMenu = $formTable.find('.id-cat-menu').val();
            //                 var idValues = MAC.documentOnReady.uniqid('description-');
                            
            //                 $(this).find('.mac-collapsible-btn').addClass('collapsible-show');
            //                 if (totalColNumber > 0) {
            //                     let $currentPriceList = $priceListContainer.eq($listItem.length - 1);
            //                     let existingPriceItems = $currentPriceList.children('div').length;
                                
            //                     for (let y = existingPriceItems; y < totalColNumber; y++) {
            //                         let htmlItem = `<div data-repeater-item class="ui-sortable-handle">
            //                             <label>Price: </label>
            //                             <input type="text" name="form_${idCatMenu}_group-repeater[${$listItem.length - 1}][price-list][${y}][price]" value="">
            //                         </div>`;
            //                         $currentPriceList.append(htmlItem);
            //                     }
            //                 }
            //                 var editorId = $(this).find('.wp-editor-area').attr('id');
            //                 if (editorId) {
            //                     var htmlNewRepeater = MAC.documentOnReady.changeHTMLItemRepeater(
            //                         $(this).find('.mac-menu-custom-wp-editor'), 
            //                         editorId, 
            //                         idValues
            //                     );
            //                     $(this).find('.mac-menu-custom-wp-editor').html(htmlNewRepeater);
            //                 }
                            
            //                 if (typeof QTags !== 'undefined') {
            //                     QTags({ id: idValues });
            //                     QTags._buttonsInit();
            //                 }
                            
            //                 $(this).find('.switch-tmce').click(function () {
            //                     tinyMCE.execCommand('mceAddEditor', true, idValues);
            //                 });
            //             },
            //             hide: function (deleteElement) {
            //                 $(this).slideUp(deleteElement);
            //             },
            //             repeaters: [{
            //                 selector: '.form-repeater-child-1',
            //                 initEmpty: false,
            //                 show: function () {
            //                     $(this).slideDown();
            //                 },
            //                 hide: function (deleteElement) {
            //                     $(this).slideUp(deleteElement);
            //                 },
            //             }],
            //         });

            //         // Add click handler for create button
            //         $(this).find('[data-repeater-1-create]').click(function (e) {
            //             e.preventDefault();
            //             var $repeater1 = $(this).closest('.form-repeater-1');
            //             var $template = $repeater1.find('> .repeater-list-item > [data-repeater-item]').first().clone();
            //             var $listItems = $repeater1.find('> .repeater-list-item > [data-repeater-item]');
            //             var newIndex = $listItems.length;
            //             var idCatMenu = $repeater1.closest('.form-table').find('.id-cat-menu').val();
                        
            //             // Update all input names with new index
            //             $template.find('input, textarea, select').each(function() {
            //                 var $input = $(this);
            //                 var name = $input.attr('name');
            //                 if (name) {
            //                     // Replace the index in the name attribute
            //                     name = name.replace(/\[\d+\]/, '[' + newIndex + ']');
            //                     $input.attr('name', name);
            //                 }
            //             });
                        
            //             $template.slideDown();
            //             $repeater1.find('>[data-repeater-list]').append($template);
                        
            //             // Initialize the new item
            //             var btn_load = $template.find('.add_media_button');
            //             MAC.MAC_Media.mediaUpload(btn_load);
            //             MAC.documentOnReady.collapsible();
            //             MAC.documentOnReady.duplicateBTN();
            //             MAC.documentOnReady.changeTextBTN();
            //             MAC.documentOnReady.switcherBTN();
                        
            //             // Handle price list for new item
            //             var totalCol = $repeater1.closest('.form-table').find('.mac-table-total-col');
            //             let totalColNumber = parseInt($(totalCol).find('.mac-is-selection').val());
            //             let $priceListContainer = $template.find('.form-repeater-child-1 .repeater-list-item');
                        
            //             if (totalColNumber > 0) {
            //                 for (let y = 1; y < totalColNumber; y++) {
            //                     let htmlItem = `<div data-repeater-item class="ui-sortable-handle">
            //                         <label>Price: </label>
            //                         <input type="text" name="form_${idCatMenu}_group-repeater[${newIndex}][price-list][${y}][price]" value="">
            //                     </div>`;
            //                     $priceListContainer.append(htmlItem);
            //                 }
            //             }
            //         });
            //     });
            // }
        },

        /** */
        isTable: function () {
            var totalCol = $('.mac-table-total-col');
            $(totalCol).each(function () {
                $(this).change(
                    function () {
                        let idCatMenu = $(this).closest('.form-table').find('.id-cat-menu').val();
                        let totalColNumber = parseInt($(this).find('.mac-is-selection').val());
                        // xử lý Cat
                        let tableHeading = $(this).next('.mac-table-col-heading').find('.data_table');
                        let totalTD = tableHeading.find('td');
                        var html = '<td><lable>Name </lable><input name="form_' + idCatMenu + '_table_col[]" class="large-text" value=""></input></td>';
                        if (totalColNumber > totalTD.length) {
                            for (var i = 0; i < totalColNumber; i++) {
                                if (i >= totalTD.length) {
                                    tableHeading.find('tr').append(html);
                                }
                            }
                        } else {
                            for (var i = 0; i < totalTD.length; i++) {
                                if ((i >= totalColNumber) || (0 == totalColNumber)) {
                                    tableHeading.find('td:nth-child(' + (i + 1) + ')').hide();
                                    //tableHeading.find('td:nth-child('+(i+1)+')').remove();
                                }
                            }
                            tableHeading.find('td[style="display: none;"]').remove();

                        }
                        // xử lý item Menu

                        let formRepeater = $(this).closest('.form-table').find('>tbody>tr>td>div');
                        let listItem = formRepeater.children('.repeater-list-item').children('.repeater-item');
                        let priceList = listItem.find('.form-repeater-child .repeater-list-item');
                        let totalPriceList = priceList.children('div');
                        //let idCatMenu = $(this).parents('.form-table').find('.id-cat-menu').val();
                        if (listItem && listItem.length > 0) {
                            for (var j = 0; j < listItem.length; j++) {
                                if (totalColNumber > totalPriceList.length / listItem.length) {
                                    for (var y = 0; y < totalColNumber; y++) {
                                        let htmlItem = '<div data-repeater-item class="ui-sortable-handle"><label>Price: </label><input type="text" name="form_' + idCatMenu + '_group-repeater[' + (j) + '][price-list][' + (y) + '][price]" value=""></div>';
                                        if (y >= totalPriceList.length / listItem.length) {
                                            priceList.eq(j).append(htmlItem);
                                        }
                                    }
                                } else {
                                    for (var z = 0; z < totalPriceList.length / listItem.length; z++) {
                                        if ((z >= totalColNumber) || (0 == totalColNumber)) {
                                            priceList.children('div:nth-child(' + (z + 1) + ')').remove();
                                        }

                                    }

                                }
                            }
                        }
                    }

                );
            });
        },
        
        debugFormSubmit: function () {
            console.log('=== MAC Menu: debugFormSubmit() called ===');
            $('.mac-form-menu').on('submit', function(e) {
                console.log('Form submit triggered');
                console.log('Form data:', $(this).serialize());
                
                // Check repeater data
                var repeaterData = {};
                $('[data-repeater-list]').each(function() {
                    var listName = $(this).data('repeater-list');
                    console.log('Repeater list:', listName);
                    console.log('Repeater items:', $(this).find('[data-repeater-item]').length);
                    
                    // Get all input values in this repeater
                    $(this).find('input, textarea, select').each(function() {
                        var name = $(this).attr('name');
                        var value = $(this).val();
                        if (name) {
                            console.log('  ' + name + ': ' + value);
                        }
                    });
                });
            });
        },
    };
    MAC.documentOnLoad = {
        init: function () {
            //MAC.documentOnReady.confirmDialogUpdateKey();
        }
    };

    $document.ready(MAC.documentOnReady.init);
    $window.on('load', MAC.documentOnLoad.init);
})(jQuery);

