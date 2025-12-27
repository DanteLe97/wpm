// Đảm bảo Handsontable đã load trước khi chạy code
if (typeof Handsontable === 'undefined') {
    console.error('Handsontable is not loaded. Please check if the JS file is accessible.');
}

jQuery(document).ready(function($) {
    // Kiểm tra lại Handsontable trước khi khởi tạo
    if (typeof Handsontable === 'undefined') {
        alert('Error: Handsontable library is not loaded. Please refresh the page or check your network connection.');
        return;
    }
    
    // Lấy data từ localized script
    var container = document.getElementById('mac-bulk-edit-spreadsheet');
    var hotData = macBulkEdit.hotData;
    var columnHeaders = macBulkEdit.columnHeaders;
    var columns = macBulkEdit.columns;
    var columnsCount = macBulkEdit.columnsCount;
    var nonce = macBulkEdit.nonce;
    var redirectUrl = macBulkEdit.redirectUrl;
    
    // Định nghĩa các cột text có thể format (bỏ qua numeric, ID, URLs)
    var textColumns = [1, 2, 3, 5, 8, 9, 10, 11, 16]; // category_name, category_description, price, parents_category, table_heading, item_list_name, item_list_price, item_list_description, category_inside_order
    
    var hot = new Handsontable(container, {
        data: hotData,
        colHeaders: columnHeaders,
        rowHeaders: true,
        width: '100%',
        height: 600,
        licenseKey: 'non-commercial-and-evaluation',
        stretchH: 'all',
        contextMenu: true,
        manualColumnResize: true,
        manualRowResize: true,
        filters: true,
        dropdownMenu: true,
        minSpareRows: 10, // Tự động tạo 10 row trống ở cuối (giống CSV)
        allowInsertRow: true, // Cho phép insert row từ context menu
        allowRemoveRow: true, // Cho phép xóa row
        columns: [
            { data: 0, type: 'numeric', width: 80 }, // id
            { data: 1, type: 'text', width: 150 }, // category_name
            { data: 2, type: 'text', width: 200 }, // category_description
            { data: 3, type: 'text', width: 100 }, // price
            { data: 4, type: 'text', width: 150 }, // featured_img
            { data: 5, type: 'text', width: 120 }, // parents_category
            { data: 6, type: 'numeric', width: 80 }, // is_hidden
            { data: 7, type: 'numeric', width: 80 }, // is_table
            { data: 8, type: 'text', width: 150 }, // table_heading
            { data: 9, type: 'text', width: 150 }, // item_list_name
            { data: 10, type: 'text', width: 120 }, // item_list_price
            { data: 11, type: 'text', width: 200 }, // item_list_description
            { data: 12, type: 'numeric', width: 100 }, // item_list_fw
            { data: 13, type: 'text', width: 150 }, // item_list_img
            { data: 14, type: 'numeric', width: 100 }, // item_list_position
            { data: 15, type: 'numeric', width: 120 }, // category_inside
            { data: 16, type: 'text', width: 150 } // category_inside_order
        ]
    });
    
    // Lưu selection khi context menu được mở
    var savedSelection = null;
    var contextMenuCell = null;
    
    hot.addHook('beforeContextMenuShow', function(menu) {
        // Lưu selection trước khi menu hiển thị
        savedSelection = hot.getSelected();
        contextMenuCell = null;
        
        // Nếu không có selection, lấy từ cell đang được right-click
        if (!savedSelection || savedSelection.length === 0) {
            var coords = hot.getSelectedLast();
            if (coords && coords.length >= 2) {
                contextMenuCell = {
                    row: coords[0],
                    col: coords[1]
                };
                // Tạo selection từ cell này - format toàn bộ cột từ row 0
                var data = hot.getData();
                var lastRow = data.length > 0 ? data.length - 1 : 0;
                savedSelection = [[0, coords[1], lastRow, coords[1]]];
            }
        } else {
            // Kiểm tra xem có phải chọn cột không (selection có thể là array rỗng hoặc format khác)
            // Nếu selected[0] không tồn tại, lấy từ getSelectedLast
            if (!savedSelection[0] || savedSelection[0].length < 4) {
                var coords = hot.getSelectedLast();
                if (coords && coords.length >= 2) {
                    contextMenuCell = {
                        row: coords[0],
                        col: coords[1]
                    };
                    var data = hot.getData();
                    var lastRow = data.length > 0 ? data.length - 1 : 0;
                    savedSelection = [[0, coords[1], lastRow, coords[1]]];
                }
            }
        }
        
        console.log('Saved selection:', savedSelection);
        console.log('Context menu cell:', contextMenuCell);
    });
    
    // Thêm format text menu vào context menu bằng hook
    hot.addHook('afterContextMenuDefaultOptions', function(defaultOptions) {
        // Thêm separator
        defaultOptions.items.push(Handsontable.plugins.ContextMenu.SEPARATOR);
        
        // Thêm các format text menu items riêng biệt
        defaultOptions.items.push({
            key: 'format_uppercase',
            name: 'Format: UPPERCASE',
            callback: function() {
                try {
                    console.log('Format UPPERCASE clicked, savedSelection:', savedSelection);
                    formatSelectedColumn('uppercase', savedSelection);
                } catch(e) {
                    console.error('Error in format_uppercase:', e);
                    alert('Error: ' + e.message);
                }
            }
        });
        
        defaultOptions.items.push({
            key: 'format_lowercase',
            name: 'Format: lowercase',
            callback: function() {
                try {
                    console.log('Format lowercase clicked, savedSelection:', savedSelection);
                    formatSelectedColumn('lowercase', savedSelection);
                } catch(e) {
                    console.error('Error in format_lowercase:', e);
                    alert('Error: ' + e.message);
                }
            }
        });
        
        defaultOptions.items.push({
            key: 'format_capitalize',
            name: 'Format: Capitalize Words',
            callback: function() {
                try {
                    console.log('Format Capitalize clicked, savedSelection:', savedSelection);
                    formatSelectedColumn('capitalize', savedSelection);
                } catch(e) {
                    console.error('Error in format_capitalize:', e);
                    alert('Error: ' + e.message);
                }
            }
        });
        
        defaultOptions.items.push({
            key: 'format_sentence',
            name: 'Format: Sentence case',
            callback: function() {
                try {
                    console.log('Format Sentence case clicked, savedSelection:', savedSelection);
                    formatSelectedColumn('sentence_case', savedSelection);
                } catch(e) {
                    console.error('Error in format_sentence:', e);
                    alert('Error: ' + e.message);
                }
            }
        });
        
        return defaultOptions;
    });
    
    // Hàm format text theo loại
    function formatText(text, formatType) {
        if (!text || typeof text !== 'string') {
            return text;
        }
        
        switch(formatType) {
            case 'uppercase':
                return text.toUpperCase();
            case 'lowercase':
                return text.toLowerCase();
            case 'capitalize':
                return text.replace(/\b\w/g, function(char) {
                    return char.toUpperCase();
                });
            case 'sentence_case':
                // Chữ hoa đầu câu, các chữ còn lại thường
                return text.charAt(0).toUpperCase() + text.slice(1).toLowerCase();
            default:
                return text;
        }
    }
    
    // Hàm format cột được chọn
    function formatSelectedColumn(formatType, selection) {
        // Sử dụng selection đã lưu hoặc lấy từ hot
        var selected = selection || hot.getSelected();
        
        console.log('formatSelectedColumn called with:', formatType, 'Selection:', selected);
        
        // Nếu không có selection, thử lấy từ cell đang được right-click
        if (!selected || selected.length === 0) {
            if (contextMenuCell) {
                // Format toàn bộ cột của cell đang được right-click
                var col = contextMenuCell.col;
                if (textColumns.indexOf(col) !== -1) {
                    var data = hot.getData();
                    var modified = false;
                    
                    for (var i = 0; i < data.length; i++) {
                        var cellValue = data[i][col];
                        if (cellValue !== null && cellValue !== undefined && cellValue !== '') {
                            var formattedValue = formatText(String(cellValue), formatType);
                            if (formattedValue !== cellValue) {
                                data[i][col] = formattedValue;
                                modified = true;
                            }
                        }
                    }
                    
                    if (modified) {
                        hot.loadData(data);
                        console.log('Formatted column ' + col + ' with ' + formatType + ' format');
                    }
                    return;
                } else {
                    alert('This column cannot be formatted. Only text columns can be formatted.');
                    return;
                }
            } else {
                alert('Please select a column or cells first.');
                return;
            }
        }
        
        // Kiểm tra selected có hợp lệ không
        if (!selected || !Array.isArray(selected) || selected.length === 0) {
            // Nếu không có selection hợp lệ, thử format cột từ contextMenuCell
            if (contextMenuCell) {
                var col = contextMenuCell.col;
                if (textColumns.indexOf(col) !== -1) {
                    var data = hot.getData();
                    var modified = false;
                    
                    for (var i = 0; i < data.length; i++) {
                        var cellValue = data[i][col];
                        if (cellValue !== null && cellValue !== undefined && cellValue !== '') {
                            var formattedValue = formatText(String(cellValue), formatType);
                            if (formattedValue !== cellValue) {
                                data[i][col] = formattedValue;
                                modified = true;
                            }
                        }
                    }
                    
                    if (modified) {
                        hot.loadData(data);
                        console.log('Formatted column ' + col + ' with ' + formatType + ' format');
                    }
                    return;
                } else {
                    alert('This column cannot be formatted. Only text columns can be formatted.');
                    return;
                }
            } else {
                alert('Please select a column or cells first.');
                return;
            }
        }
        
        // Kiểm tra selected[0] có tồn tại và hợp lệ không
        if (!selected[0] || !Array.isArray(selected[0]) || selected[0].length < 4) {
            // Nếu selected[0] không hợp lệ, thử format cột từ contextMenuCell
            if (contextMenuCell) {
                var col = contextMenuCell.col;
                if (textColumns.indexOf(col) !== -1) {
                    var data = hot.getData();
                    var modified = false;
                    
                    for (var i = 0; i < data.length; i++) {
                        var cellValue = data[i][col];
                        if (cellValue !== null && cellValue !== undefined && cellValue !== '') {
                            var formattedValue = formatText(String(cellValue), formatType);
                            if (formattedValue !== cellValue) {
                                data[i][col] = formattedValue;
                                modified = true;
                            }
                        }
                    }
                    
                    if (modified) {
                        hot.loadData(data);
                        console.log('Formatted column ' + col + ' with ' + formatType + ' format');
                    }
                    return;
                } else {
                    alert('This column cannot be formatted. Only text columns can be formatted.');
                    return;
                }
            } else {
                alert('Please select a column or cells first.');
                return;
            }
        }
        
        // Lấy phạm vi selection - luôn bắt đầu từ row 0 khi chọn cột
        var startRow = 0; // Luôn bắt đầu từ row đầu tiên khi format cột
        var startCol = selected[0][1];
        var endRow = selected[selected.length - 1] && selected[selected.length - 1][2] !== undefined 
            ? selected[selected.length - 1][2] 
            : (hot.getData().length - 1); // Nếu không có endRow, lấy row cuối cùng
        var endCol = selected[selected.length - 1] && selected[selected.length - 1][3] !== undefined 
            ? selected[selected.length - 1][3] 
            : startCol; // Nếu không có endCol, dùng startCol
        
        console.log('Selection range:', {startRow, startCol, endRow, endCol});
        
        // Kiểm tra xem các cột có phải là text column không
        var colsToFormat = [];
        for (var col = startCol; col <= endCol; col++) {
            if (textColumns.indexOf(col) !== -1) {
                colsToFormat.push(col);
            }
        }
        
        if (colsToFormat.length === 0) {
            alert('Selected column(s) cannot be formatted. Only text columns can be formatted.');
            return;
        }
        
        // Lấy data hiện tại
        var data = hot.getData();
        var modified = false;
        
        // Format các cells trong phạm vi được chọn - luôn từ row 0 đến row cuối
        for (var i = 0; i < data.length; i++) {
            for (var j = 0; j < colsToFormat.length; j++) {
                var colIndex = colsToFormat[j];
                
                var cellValue = data[i][colIndex];
                
                if (cellValue !== null && cellValue !== undefined && cellValue !== '') {
                    var formattedValue = formatText(String(cellValue), formatType);
                    if (formattedValue !== cellValue) {
                        data[i][colIndex] = formattedValue;
                        modified = true;
                    }
                }
            }
        }
        
        if (modified) {
            // Cập nhật data vào Handsontable
            hot.loadData(data);
            console.log('Formatted column(s) ' + colsToFormat.join(', ') + ' with ' + formatType + ' format');
        } else {
            console.log('No changes made');
        }
    }
    
    // Xử lý Add Row button
    $('#mac-bulk-edit-add-row').on('click', function() {
        // Thêm 20 row trống mới
        var currentData = hot.getData();
        var emptyRow = [];
        for (var i = 0; i < columnsCount; i++) {
            emptyRow.push('');
        }
        
        // Thêm 20 row trống
        for (var i = 0; i < 20; i++) {
            currentData.push(emptyRow.slice()); // Copy array
        }
        
        hot.loadData(currentData);
    });
    
    // Xử lý Save
    $('#mac-bulk-edit-save').on('click', function() {
        if (!confirm('Are you sure you want to save changes? This will replace all existing menu data.')) {
            return;
        }
        
        var $button = $(this);
        var $status = $('#mac-bulk-edit-status');
        
        $button.prop('disabled', true);
        $status.html('<span class="spinner is-active" style="float: none; margin: 0 10px;"></span> Saving...');
        
        // Lấy data từ Handsontable
        var data = hot.getData();
        
        // Chuyển đổi về format array và filter bỏ row trống hoàn toàn
        var formattedData = [];
        for (var i = 0; i < data.length; i++) {
            var row = {};
            var hasData = false; // Kiểm tra row có data không
            
            for (var j = 0; j < columns.length; j++) {
                var value = data[i][j] !== null && data[i][j] !== undefined ? data[i][j] : '';
                row[columns[j]] = value;
                
                // Kiểm tra nếu có ít nhất 1 giá trị không rỗng
                if (value !== '' && value !== null && value !== undefined) {
                    hasData = true;
                }
            }
            
            // Chỉ thêm row nếu có ít nhất 1 giá trị
            if (hasData) {
                formattedData.push(row);
            }
        }
        
        // Kiểm tra nếu không có data nào
        if (formattedData.length === 0) {
            $status.html('<span style="color: orange;">⚠ No data to save. Please enter at least one row of data.</span>');
            $button.prop('disabled', false);
            return;
        }
        
        // Gửi AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json', // Đảm bảo parse response thành JSON
            data: {
                action: 'mac_bulk_edit_save',
                nonce: nonce,
                data: JSON.stringify(formattedData)
            },
            success: function(response) {
                console.log('Full Response:', response); // Debug
                console.log('Response Type:', typeof response);
                console.log('Response.success:', response ? response.success : 'undefined');
                console.log('Response.data:', response ? response.data : 'undefined');
                
                // Kiểm tra response có tồn tại
                if (!response) {
                    console.error('Response is null or undefined');
                    $status.html('<span style="color: red;">✗ Error: No response received</span>');
                    $button.prop('disabled', false);
                    return;
                }
                
                // Kiểm tra response có success property và giá trị là true
                if (typeof response === 'object' && response.hasOwnProperty('success') && response.success === true) {
                    var message = 'Changes saved successfully!';
                    if (response.data && typeof response.data === 'object') {
                        if (response.data.message) {
                            message = response.data.message;
                        }
                    }
                    $status.html('<span style="color: green;">✓ ' + message + '</span>');
                    setTimeout(function() {
                        window.location.href = redirectUrl;
                    }, 2000);
                } else {
                    // Xử lý trường hợp response.success = false hoặc không có success property
                    var errorMsg = 'Unknown error occurred';
                    
                    if (typeof response === 'object') {
                        if (response.data && typeof response.data === 'object') {
                            if (response.data.message) {
                                errorMsg = response.data.message;
                            } else if (response.data.errors && Array.isArray(response.data.errors) && response.data.errors.length > 0) {
                                errorMsg = response.data.errors.join('; ');
                            }
                        } else if (response.message) {
                            errorMsg = response.message;
                        }
                    } else if (typeof response === 'string') {
                        // Nếu response là string, có thể là JSON string
                        try {
                            var parsed = JSON.parse(response);
                            if (parsed.data && parsed.data.message) {
                                errorMsg = parsed.data.message;
                            }
                        } catch(e) {
                            errorMsg = response;
                        }
                    }
                    
                    // Nếu vẫn là "Unknown error" nhưng có response, log để debug
                    if (errorMsg === 'Unknown error occurred') {
                        console.error('Unexpected response format:', response);
                        console.error('Response keys:', Object.keys(response || {}));
                    }
                    
                    $status.html('<span style="color: red;">✗ Error: ' + errorMsg + '</span>');
                    $button.prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', xhr, status, error); // Debug
                var errorMsg = error;
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMsg = xhr.responseJSON.data.message;
                } else if (xhr.responseText) {
                    try {
                        var parsed = JSON.parse(xhr.responseText);
                        if (parsed.data && parsed.data.message) {
                            errorMsg = parsed.data.message;
                        }
                    } catch(e) {
                        errorMsg = xhr.responseText.substring(0, 200);
                    }
                }
                $status.html('<span style="color: red;">✗ Error: ' + errorMsg + '</span>');
                $button.prop('disabled', false);
            }
        });
    });
});

