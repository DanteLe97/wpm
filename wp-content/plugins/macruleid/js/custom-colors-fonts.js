jQuery(document).ready(function($) {
    // Plugin initialization
    
    if (typeof customMetaboxData === 'undefined') {
        console.error('Mac Rule ID - customMetaboxData is not defined. Script may not be loaded correctly.');
        return;
    }

    // Global variables
    let isEditMode = false;
    let originalPresetName = '';
    let presetColorCounter = 5;
    let presetFontCounter = 5;

    // Global counter for new items
    var colorCounter = 5; // Bắt đầu từ 5
    var fontCounter = 5; // Bắt đầu từ 5

    // Popular Google Fonts list (most used fonts first)
    var popularGoogleFonts = [
        'Roboto', 'Open Sans', 'Lato', 'Montserrat', 'Roboto Condensed', 
        'Source Sans Pro', 'Oswald', 'Roboto Slab', 'Slabo 27px', 'Raleway',
        'Ubuntu', 'Merriweather', 'PT Sans', 'Playfair Display', 'Noto Sans',
        'Roboto Mono', 'Poppins', 'Nunito', 'Inter', 'Fira Sans',
        'Work Sans', 'Rubik', 'Lora', 'Mukti', 'IBM Plex Sans',
        'Crimson Text', 'Libre Baskerville', 'Droid Sans', 'Titillium Web', 'Cabin'
    ];

    // Common font list - used by all font controls
    var commonFonts = (typeof macFontsData !== 'undefined' && Array.isArray(macFontsData.fonts)) ? macFontsData.fonts : [
        { value: '', text: 'Select Font' }
    ];

    // Function to get popular fonts first, then others
    function getOrderedFonts(limit = 30) {
        if (!commonFonts || commonFonts.length === 0) {
            return [];
        }
        
        var orderedFonts = [];
        var usedFonts = new Set();
        
        // First, add popular fonts that exist in our font list
        popularGoogleFonts.forEach(function(popularFont) {
            var found = commonFonts.find(function(font) {
                return font.text && font.text.toLowerCase().includes(popularFont.toLowerCase());
            });
            if (found && !usedFonts.has(found.value)) {
                orderedFonts.push(found);
                usedFonts.add(found.value);
            }
        });
        
        
        // Then add remaining fonts up to the limit
        commonFonts.forEach(function(font) {
            if (orderedFonts.length >= limit) return;
            if (font.value && !usedFonts.has(font.value)) {
                orderedFonts.push(font);
                usedFonts.add(font.value);
            }
        });
        
        
        return orderedFonts.slice(0, limit);
    }

    // Function to generate font options HTML
    function generateFontOptions(selectedValue) {
        var optionsHtml = '';
        commonFonts.forEach(function(font) {
            var selected = selectedValue && font.value === selectedValue ? ' selected' : '';
            optionsHtml += '<option value="' + font.value + '"' + selected + '>' + font.text + '</option>';
        });
        return optionsHtml;
    }

    // ====== MODAL SCROLL MANAGEMENT ======
    function getScrollbarWidth() {
        // Create temporary div to measure scrollbar width
        const outer = document.createElement('div');
        outer.style.visibility = 'hidden';
        outer.style.overflow = 'scroll';
        outer.style.msOverflowStyle = 'scrollbar';
        document.body.appendChild(outer);
        
        const inner = document.createElement('div');
        outer.appendChild(inner);
        
        const scrollbarWidth = outer.offsetWidth - inner.offsetWidth;
        outer.parentNode.removeChild(outer);
        
        return scrollbarWidth;
    }

    function disableBodyScroll() {
        const scrollbarWidth = getScrollbarWidth();
        $('body').css({
            'overflow': 'hidden',
            'padding-right': scrollbarWidth + 'px'
        }).addClass('modal-open');
    }

    function enableBodyScroll() {
        $('body').css({
            'overflow': '',
            'padding-right': ''
        }).removeClass('modal-open');
    }

    // ====== SELECT2 & REFRESH FONT LIST BUTTON ======
    function initPresetModalSelect2() {
        
        $('#preset-creator-modal .font-select').each(function() {
            var $select = $(this);
            var selectId = $select.attr('id');
            
            
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.select2('destroy');
            }
            
            var currentVal = $select.data('current') || $select.val();
            $select.empty();
            
            $select.select2({
                width: '100%',
                placeholder: 'Select Font',
                allowClear: true,
                dropdownAutoWidth: true,
                minimumResultsForSearch: 10,
                ajax: {
                    transport: function (params, success, failure) {
                        var term = params.data.q ? params.data.q.toLowerCase() : '';
                        var results;
                        if (!term) {
                            results = getOrderedFonts(30).map(function(font) {
                                return { id: font.value, text: font.text };
                            });
                        } else {
                            results = macFontsData.fonts.filter(function(font) {
                                return font.text.toLowerCase().indexOf(term) !== -1;
                            }).map(function(font) {
                                return { id: font.value, text: font.text };
                            });
                        }
                        success({ results: results });
                    },
                    processResults: function (data) {
                        return { results: data.results };
                    }
                },
                templateSelection: function (data, container) {
                    return data.text || data.id;
                },
                templateResult: function (data) {
                    return data.text || data.id;
                },
                escapeMarkup: function (m) { return m; }
            });
            
            if (currentVal) {
                var selected = macFontsData.fonts.find(function(f){ return f.value === currentVal; });
                if (selected) {
                    var option = new Option(selected.text, selected.value, true, true);
                    $select.append(option).trigger('change');
                }
            }
            
        });
        
    }

    function initFontSelect2() {
        $('.font-select').each(function() {
            var $select = $(this);
            
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.select2('destroy');
            }
            
            // Store current value trước khi clear
            var currentVal = $select.data('current') || $select.val();
            $select.empty();
            
            $select.select2({
                width: '100%',
                placeholder: 'Select Font',
                allowClear: true,
                dropdownAutoWidth: true,
                minimumResultsForSearch: 10,
                ajax: {
                    transport: function (params, success, failure) {
                        var term = params.data.q ? params.data.q.toLowerCase() : '';
                        var results;
                        if (!term) {
                            results = getOrderedFonts(30).map(function(font) {
                                return { id: font.value, text: font.text };
                            });
                        } else {
                            results = macFontsData.fonts.filter(function(font) {
                                return font.text.toLowerCase().indexOf(term) !== -1;
                            }).map(function(font) {
                                return { id: font.value, text: font.text };
                            });
                        }
                        success({ results: results });
                    },
                    processResults: function (data) {
                        return { results: data.results };
                    }
                },
                templateSelection: function (data, container) {
                    return data.text || data.id;
                },
                templateResult: function (data) {
                    return data.text || data.id;
                },
                escapeMarkup: function (m) { return m; }
            });
            
            // Nếu select đã có value, set lại option đã chọn
            if (currentVal) {
                var selected = macFontsData.fonts.find(function(f){ return f.value === currentVal; });
                if (selected) {
                    var option = new Option(selected.text, selected.value, true, true);
                    $select.append(option).trigger('change');
                }
            }
        });
    }

    // Thêm nút Refresh Font List ngang hàng Typography
    function addRefreshFontListButton() {
        if ($('#refresh-font-list').length) return;
        var $typoHeader = $('.typography-section h4:contains("Typography")');
        if ($typoHeader.length) {
            var $btn = $('<button id="refresh-font-list" type="button" style="margin-left:16px;vertical-align:middle;">Refresh Font List</button>');
            $typoHeader.after($btn);
        }
    }

    // Xử lý sự kiện refresh font list
    $(document).on('click', '#refresh-font-list', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Refreshing...');
        $.post(macFontsData.ajax_url, {
            action: 'macruleid_refresh_google_fonts',
            _ajax_nonce: macFontsData.nonce
        }, function(response) {
            if (response.success && response.data.fonts) {
                commonFonts = response.data.fonts;
                // Update all font-select
                $('.font-select').each(function() {
                    var current = $(this).val();
                    $(this).html(generateFontOptions(current));
                });
                // Re-init select2
                $('.font-select').select2('destroy');
                initFontSelect2();
                alert('Font list refreshed!');
            } else {
                alert('Failed to refresh font list!');
            }
        }).always(function() {
            $btn.prop('disabled', false).text('Refresh Font List');
        });
    });

    // Khởi tạo select2 khi document ready và sau khi render lại option
    $(function() {
        setTimeout(initFontSelect2, 300); // Delay để đảm bảo select đã render
    });

    // Khi cập nhật lại danh sách font (ví dụ sau khi refresh), cũng phải re-init select2
    $(document).on('macFontsUpdated', function() {
        initFontSelect2();
    });

    // Expose functions to global scope for onclick handlers
    window.addCustomItem = addCustomItem;
    window.removeCustomItem = removeCustomItem;
    window.resetAllToDefault = resetAllToDefault;
    window.showPresetCreator = showPresetCreator;
    window.hidePresetCreator = hidePresetCreator;
    window.saveCustomPreset = saveCustomPreset;
    window.editPreset = editPreset;
    window.deletePreset = deletePreset;
    window.updateExistingPreset = updateExistingPreset;
    window.addPresetCustomColor = addPresetCustomColor;
    window.addPresetCustomFont = addPresetCustomFont;
    window.removePresetCustomColor = removePresetCustomColor;
    window.removePresetCustomFont = removePresetCustomFont;
    window.copyToClipboard = copyToClipboard;
    
    // Backward compatibility aliases
    window.openPresetCreator = showPresetCreator;
    window.savePreset = saveCustomPreset;
    window.closePresetCreator = hidePresetCreator;

    // Function to sync clr-field button color with input value
    function syncClrFieldButtonColor(input) {
        var $input = $(input);
        var $clrField = $input.closest('.clr-field');
        var color = $input.val();
        
        if ($clrField.length && color) {
            $clrField.css('--clr-field-color', color);
        }
    }

    // Function to initialize all clr-field button colors
    function initializeClrFieldColors() {
        $('.coloris').each(function() {
            syncClrFieldButtonColor(this);
        });
    }

    // Initialize Coloris for existing elements
    if (typeof Coloris !== 'undefined') {
        Coloris({
            el: '.coloris',
            theme: 'polaroid',
            themeMode: 'light',
            formatToggle: true,
            closeButton: true,
            clearButton: true,
            alpha: true, // Enable alpha channel support
            swatches: [
                '#F26212',
                '#FBAE85',
                '#333333',
                '#FF6B35',
                '#4CAF50',
                '#2196F3',
                '#9C27B0',
                '#FF9800',
                '#00000000' // Transparent color
            ]
        });
        
        // Initialize button colors for existing elements
        initializeClrFieldColors();
        
        // Sync color when coloris input changes
        $(document).on('input change', '.coloris', function() {
            syncClrFieldButtonColor(this);
        });
    }

    // Tab functionality
    $('.tab-button').on('click', function() {
        var targetTab = $(this).data('tab');
        
        // Update button states
        $('.tab-button').removeClass('active');
        $(this).addClass('active');
        
        // Update panel visibility
        $('.tab-panel').removeClass('active');
        $('#' + targetTab + '-tab').addClass('active');
        
        // Load presets if switching to preset tab
        if (targetTab === 'preset') {
            setTimeout(function() {
                loadPresetDataFromDatabase();
                initializeCounters();
            }, 100);
        }
    });

    // Migrate old font structure to new structure
    function migratePresetFontStructure(preset) {
        if (!preset || !preset.fonts) {
            return preset;
        }
        
        var newFonts = {};
        
        // Check if already using new structure
        if (preset.fonts[0] && typeof preset.fonts[0] === 'object') {
            return preset;
        }
        
        // Migrate old structure
        if (preset.fonts.primary) {
            newFonts[0] = {
                'name': 'primary',
                'text': '--e-global-typography-primary-font-family',
                'font': preset.fonts.primary
            };
        }
        
        if (preset.fonts.secondary) {
            newFonts[1] = {
                'name': 'secondary', 
                'text': '--e-global-typography-secondary-font-family',
                'font': preset.fonts.secondary
            };
        }
        
        // Handle custom fonts with custom_ prefix
        var customIndex = 4;
        for (var key in preset.fonts) {
            if (key.startsWith('custom_') && preset.fonts[key].value) {
                newFonts[customIndex] = {
                    'name': 'Mac Custom Font ' + customIndex,
                    'text': preset.fonts[key].name || 'Mac Custom Font ' + customIndex,
                    'font': preset.fonts[key].value
                };
                customIndex++;
            }
        }
        
        preset.fonts = newFonts;
        
        return preset;
    }

    // Initialize on page load
    loadPresetDataFromDatabase();
    
    // Add custom item function
    function addCustomItem(type) {
        var counter = type === 'color' ? colorCounter : fontCounter;
        var container = type === 'color' ? '#colors-container' : '#fonts-container';
        
        if (type === 'color') {
            var newItemHtml = `
                <div class="color-control custom-item" data-index="${counter}" data-type="color">
                    <div class="color-label-row">
                        <label>Mac Custom Color ${counter}</label>
                        <button type="button" class="copy-value-btn" data-copy-value="#F26212" title="Copy color value">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                            </svg>
                        </button>
                        <input type="text" id="mac_custom_color_name_${counter}" name="mac_custom_color_name_${counter}" value="Name Color ${counter}" class="color-name-input" placeholder="Insert Name">
<input type="text" id="mac_custom_color_text_${counter}" name="mac_custom_color_text_${counter}" value="Text Color ${counter}" class="color-name-input" placeholder="Insert Text">
                    </div>
                    <input type="text" data-coloris id="mac_custom_color_${counter}" name="mac_custom_color_${counter}" value="#F26212" class="coloris">
                    <button type="button" class="delete-item-btn" onclick="removeCustomItem(${counter}, 'color')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x-icon lucide-x">
                            <path d="M18 6 6 18"/>
                            <path d="m6 6 12 12"/>
                        </svg>
                    </button>
                </div>
            `;
            
            $(container).append(newItemHtml);
            
            // Initialize Coloris for new color input
            if (typeof Coloris !== 'undefined') {
                Coloris({
                    el: `#mac_custom_color_${counter}`,
                    theme: 'polaroid',
                    themeMode: 'light',
                    formatToggle: true,
                    closeButton: true,
                    clearButton: true,
                    alpha: true, // Enable alpha channel support
                    swatches: [
                        '#F26212',
                        '#FBAE85',
                        '#333333',
                        '#FF6B35',
                        '#4CAF50',
                        '#2196F3',
                        '#9C27B0',
                        '#FF9800',
                        '#00000000' // Transparent color
                    ]
                });
                
                // Sync button color
                syncClrFieldButtonColor($(`#mac_custom_color_${counter}`));
            }
            
            // Initialize copy button original HTML for new color button
            var $newColorControl = $(container).find('.custom-item').last();
            var $newCopyBtn = $newColorControl.find('.copy-value-btn');
            if (!$newCopyBtn.data('original-html')) {
                $newCopyBtn.data('original-html', $newCopyBtn.html());
            }
            
            colorCounter++;
        } else {
            var newItemHtml = `
                <div class="font-control custom-item" data-index="${counter}" data-type="font">
                    <div class="font-label-row">
                        <label>Mac Custom Font ${counter}</label>
                        <button type="button" class="copy-value-btn copy-font-btn" data-copy-target="mac_custom_font_${counter}" title="Copy font value">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                            </svg>
                        </button>
                        <input type="text" 
                            id="mac_custom_font_name_${counter}" 
                            name="mac_custom_font_name_${counter}" 
                            value="Mac Custom Font ${counter}" 
                            class="font-name-input" 
                            placeholder="Nhập tên tùy chỉnh">
                    </div>
                    <div class="font-class-row">
                        <label>Class Name</label>
                        <textarea id="mac_custom_font_class_${counter}" 
                                name="mac_custom_font_class_${counter}" 
                                class="font-class-input" 
                                placeholder="Nhập Custom Class Name" rows="3"></textarea>
                    </div>
                    <select id="mac_custom_font_${counter}" 
                            name="mac_custom_font_${counter}" 
                            class="font-select widefat">
                        ${generateFontOptions()}
                    </select>
                    <button type="button" class="delete-item-btn" onclick="removeCustomItem(${counter}, 'font')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18"/>
                            <path d="m6 6 12 12"/>
                        </svg>
                    </button>
                </div>
            `;
            
            $(container).append(newItemHtml);
            
            // Khởi tạo Select2 cho select vừa thêm
            setTimeout(function() {
                var $newSelect = $(`#mac_custom_font_${counter}`);
                $newSelect.select2({
                    width: '100%',
                    placeholder: 'Select Font',
                    allowClear: true,
                    dropdownAutoWidth: true,
                    minimumResultsForSearch: 10,
                    ajax: {
                        transport: function (params, success, failure) {
                            var term = params.data.q ? params.data.q.toLowerCase() : '';
                            var results;
                            if (!term) {
                                results = getOrderedFonts(30).map(function(font) {
                                    return { id: font.value, text: font.text };
                                });
                            } else {
                                results = macFontsData.fonts.filter(function(font) {
                                    return font.text.toLowerCase().indexOf(term) !== -1;
                                }).map(function(font) {
                                    return { id: font.value, text: font.text };
                                });
                            }
                            success({ results: results });
                        },
                        processResults: function (data) {
                            return { results: data.results };
                        }
                    },
                    templateSelection: function (data, container) {
                        return data.text || data.id;
                    },
                    templateResult: function (data) {
                        return data.text || data.id;
                    },
                    escapeMarkup: function (m) { return m; }
                });
                
                // Initialize copy button visibility for new font control
                var $newFontControl = $newSelect.closest('.font-control');
                updateFontCopyButtonVisibility($newFontControl);
                
                // Initialize copy button original HTML for new button
                var $newCopyBtn = $newFontControl.find('.copy-font-btn');
                if (!$newCopyBtn.data('original-html')) {
                    $newCopyBtn.data('original-html', $newCopyBtn.html());
                }
            }, 100);
            
            fontCounter = counter + 1;
        }
    }

    // Remove custom item function
    function removeCustomItem(index, type) {
        var container = type === 'color' ? '#colors-container' : '#fonts-container';
        $(container + ' .custom-item[data-index="' + index + '"]').remove();
    }

    // Reset all to default function
    function resetAllToDefault() {
        if (!confirm('Bạn có chắc chắn muốn reset tất cả về mặc định?')) {
            return;
        }

        // Reset fixed colors
        $('#primary-color').val('#F26212');
        $('#secondary-color').val('#FBAE85');
        $('#text-color').val('#333333');
        $('#accent-color').val('#FF6B35');

        // Reset fixed fonts với Select2
        $('.font-select').each(function() {
            var $select = $(this);
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.val('').trigger('change');
            } else {
                $select.val('');
            }
        });

        // Remove all custom items từ cả hai container
        $('#colors-container .custom-item').remove();
        $('#fonts-container .custom-item').remove();

        // Reset counters
        colorCounter = 5;
        fontCounter = 5;

        // Remove active preset selection
        $('.preset-item').removeClass('active');

        // Update Coloris elements và sync button colors
        if (typeof Coloris !== 'undefined') {
            Coloris({
                el: '.coloris',
                theme: 'polaroid',
                themeMode: 'light',
                formatToggle: true,
                closeButton: true,
                clearButton: true,
                alpha: true, // Enable alpha channel support
                swatches: [
                    '#F26212',
                    '#FBAE85',
                    '#333333',
                    '#FF6B35',
                    '#4CAF50',
                    '#2196F3',
                    '#9C27B0',
                    '#FF9800',
                    '#00000000' // Transparent color
                ]
            });
            
            // Sync button colors for reset values
            setTimeout(function() {
                $('#primary-color, #secondary-color, #text-color, #accent-color').each(function() {
                    syncClrFieldButtonColor(this);
                });
            }, 100);
        }

        // Re-initialize Select2 sau khi reset
        setTimeout(function() {
            initFontSelect2();
        }, 200);

        // Hiệu ứng visual feedback thay vì alert
        var $resetBtn = $('.reset-all-btn');
        var originalText = $resetBtn.html();
        
        $resetBtn.addClass('reset-completed')
                .html('<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Reset Hoàn Tất!')
                .css('background', 'linear-gradient(135deg, #28a745 0%, #20c997 100%)')
                .css('transform', 'translateY(-3px) scale(1.05)');
        
        setTimeout(function() {
            $resetBtn.removeClass('reset-completed')
                    .html(originalText)
                    .css('background', '')
                    .css('transform', '');
        }, 2000);
    }

    // Load preset data from database function
    function loadPresetDataFromDatabase() {
        if (!customMetaboxData || !customMetaboxData.presetData) {
            return;
        }

        // Clear existing grid
        var presetGrid = $('#preset-grid');
        if (!presetGrid.length) {
            presetGrid = $('<div id="preset-grid" class="preset-grid"></div>');
            $('#preset-tab').append(presetGrid);
        } else {
            presetGrid.empty();
        }

        // Add all presets to grid
        Object.values(customMetaboxData.presetData).forEach(function(preset) {
            if (preset && preset.colors && preset.fonts) {
                    addPresetToGrid(preset);
            }
        });
    }

    // Show preset creator function
    function showPresetCreator() {
        if (!isEditMode) {
            // Reset for new preset
            $('#preset-modal-title').text('Tạo Preset Mới');
            $('#preset-save-button').text('Lưu Preset').attr('onclick', 'saveCustomPreset()');
            $('#preset-name').val('');
            
            // Set default colors
            const defaultColors = {
                primary: '#F26212',
                secondary: '#FBAE85',
                text: '#333333',
                accent: '#FF6B35'
            };
            
            $('#preset-primary-color').val(defaultColors.primary);
            $('#preset-secondary-color').val(defaultColors.secondary);
            $('#preset-text-color').val(defaultColors.text);
            $('#preset-accent-color').val(defaultColors.accent);
            
            // Update color fields
            $('.coloris').each(function() {
                updateColorisField(this);
            });
            
            $('#preset-creator-modal').removeClass('editing-mode');
        }
        
        // Disable body scroll khi mở modal
        disableBodyScroll();
        
        $('#preset-creator-modal').fadeIn(300);
        
        // Re-initialize Select2 cho preset modal sau khi show
        setTimeout(function() {
            initPresetModalSelect2();
            
            // Initialize Coloris for preset modal fixed colors
            if (typeof Coloris !== 'undefined') {
                Coloris({
                    el: '#preset-primary-color, #preset-secondary-color, #preset-text-color, #preset-accent-color',
                    theme: 'polaroid',
                    themeMode: 'light',
                    formatToggle: true,
                    closeButton: true,
                    clearButton: true,
                    alpha: true, // Enable alpha channel support
                    swatches: [
                        '#F26212',
                        '#FBAE85',
                        '#333333',
                        '#FF6B35',
                        '#4CAF50',
                        '#2196F3',
                        '#9C27B0',
                        '#FF9800',
                        '#00000000' // Transparent color
                    ]
                });
            }
        }, 200);
    }

    // Update preset color variables
    function updatePresetColorVariables() {
        // Function logic if needed
    }

    // Hide preset creator function
    function hidePresetCreator() {
        $('#preset-creator-modal').fadeOut(300, function() {
            // Re-enable body scroll khi đóng modal
            enableBodyScroll();
            
            if (isEditMode) {
                $('#preset-creator-modal').removeClass('editing-mode');
                isEditMode = false;
            }
            resetPresetForm();
        });
    }

    // Reset preset form function
    function resetPresetForm() {
        $('#preset-name').val('');
        $('#preset-primary-color').val('#F26212');
        $('#preset-secondary-color').val('#FBAE85');
        $('#preset-text-color').val('#333333');
        $('#preset-accent-color').val('#FF6B35');
        $('#preset-primary-font').val('');
        $('#preset-secondary-font').val('');
        $('#preset-text-font').val('');
        $('#preset-accent-font').val('');
        
        // Clear custom items containers
        $('#preset-custom-colors-container').empty();
        $('#preset-custom-fonts-container').empty();

        // Reset counters
        presetColorCounter = 5;
        presetFontCounter = 5;
    }

    function collectCustomColors() {
        var colors = [];
        
        // Add fixed colors first (0-3)
        colors[0] = {
            name: 'primary',
            text: '--e-global-color-primary',
            color: $('#preset-primary-color').val() || '#F26212'
        };
        
        colors[1] = {
            name: 'secondary',
            text: '--e-global-color-secondary',
            color: $('#preset-secondary-color').val() || '#FBAE85'
        };
        
        colors[2] = {
            name: 'text',
            text: '--e-global-color-text',
            color: $('#preset-text-color').val() || '#333333'
        };
        
        colors[3] = {
            name: 'accent',
            text: '--e-global-color-accent',
            color: $('#preset-accent-color').val() || '#FF6B35'
        };
        
        // Add custom colors starting from index 4
        $('#preset-custom-colors-container .color-control').each(function() {
            var index = $(this).data('index');
            colors[index] = {
                name: $(`#preset_custom_color_name_${index}`).val() || `Mac Custom Color ${index}`,
                text: $(`#preset_custom_color_name_${index}`).val() || `Mac Custom Color ${index}`,
                color: $(`#preset_custom_color_${index}`).val() || '#F26212'
            };
        });
        
        return colors;
    }

    function collectCustomFonts() {
        var fonts = [];
        
        // Add fixed fonts first (0-3)
        fonts[0] = {
            name: 'primary',
            text: '--e-global-typography-primary-font-family',
            font: $('#preset-primary-font').val() || ''
        };
        
        fonts[1] = {
            name: 'secondary',
            text: '--e-global-typography-secondary-font-family',
            font: $('#preset-secondary-font').val() || ''
        };
        
        fonts[2] = {
            name: 'text',
            text: '--e-global-typography-text-font-family',
            font: $('#preset-text-font').val() || ''
        };
        
        fonts[3] = {
            name: 'accent',
            text: '--e-global-typography-accent-font-family',
            font: $('#preset-accent-font').val() || ''
        };
        
        // Add custom fonts starting from index 4
        $('#preset-custom-fonts-container .font-control').each(function() {
            var index = $(this).data('index');
            fonts[index] = {
                name: $(`#preset_custom_font_name_${index}`).val() || `Mac Custom Font ${index}`,
                text: $(`#preset_custom_font_name_${index}`).val() || `Mac Custom Font ${index}`,
                font: $(`#preset_custom_font_${index}`).val() || '',
                className: $(`#preset_custom_font_class_${index}`).val() || ''
            };
        });
        
        return fonts;
    }

    function showVisualFeedback(message, isSuccess = true) {
        // Remove existing feedback
        $('.visual-feedback').remove();
        
        // Create new feedback element
        var feedback = $('<div>', {
            class: 'visual-feedback ' + (isSuccess ? 'success' : 'error'),
            text: message
        });
        
        // Add to body
        $('body').append(feedback);
        
        // Trigger reflow
        feedback[0].offsetHeight;
        
        // Show feedback
        setTimeout(() => feedback.addClass('show'), 10);
        
        // Hide after delay
        setTimeout(() => {
            feedback.removeClass('show');
            setTimeout(() => feedback.remove(), 300);
        }, 3000);
    }

    function saveCustomPreset() {
        // Validate preset name
        var presetName = $('#preset-name').val().trim();
        if (!presetName) {
            showVisualFeedback('Vui lòng nhập tên preset', false);
            return;
        }

        // Show loading state
        $('#preset-save-button').prop('disabled', true).addClass('loading');
        showVisualFeedback('Đang lưu preset...', true);

        // Collect preset data
        var presetData = {
            name: presetName,
            colors: [
                {
                    name: 'primary',
                    text: '--e-global-color-primary',
                    color: $('#preset-primary-color').val() || '#F26212'
                },
                {
                    name: 'secondary',
                    text: '--e-global-color-secondary',
                    color: $('#preset-secondary-color').val() || '#FBAE85'
                },
                {
                    name: 'text',
                    text: '--e-global-color-text',
                    color: $('#preset-text-color').val() || '#333333'
                },
                {
                    name: 'accent',
                    text: '--e-global-color-accent',
                    color: $('#preset-accent-color').val() || '#FF6B35'
                }
            ],
            fonts: [
                {
                    name: 'primary',
                    text: '--e-global-typography-primary-font-family',
                    font: $('#preset-primary-font').val() || ''
                },
                {
                    name: 'secondary',
                    text: '--e-global-typography-secondary-font-family',
                    font: $('#preset-secondary-font').val() || ''
                },
                {
                    name: 'text',
                    text: '--e-global-typography-text-font-family',
                    font: $('#preset-text-font').val() || ''
                },
                {
                    name: 'accent',
                    text: '--e-global-typography-accent-font-family',
                    font: $('#preset-accent-font').val() || ''
                }
            ]
        };

        // Send AJAX request
        jQuery.ajax({
            url: customMetaboxData.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'mac_save_preset',
                nonce: customMetaboxData.nonce,
                post_id: customMetaboxData.post_id,
                preset_data: JSON.stringify(presetData)
            },
            success: function(response) {
                if (response.success) {
                    showVisualFeedback('Đã lưu preset thành công!', true);
                    
                    // Update grid
                    if (response.data.presets) {
                        customMetaboxData.presetData = response.data.presets;
                        $('#preset-grid').empty();
                        Object.values(response.data.presets).forEach(function(preset) {
                            if (preset.name && preset.name.trim()) {
                                addPresetToGrid(preset);
                            }
                        });
                    }
                    
                    hidePresetCreator();
                } else {
                    showVisualFeedback('Có lỗi xảy ra: ' + (response.data?.message || 'Không xác định'), false);
                }
            },
            error: function(xhr, status, error) {
                console.error('Save preset error:', error);
                showVisualFeedback('Có lỗi xảy ra khi lưu preset', false);
            },
            complete: function() {
                $('#preset-save-button').prop('disabled', false).removeClass('loading');
            }
        });
    }

    function addPresetToGrid(presetData) {
        // Validate preset data
        if (!presetData || !presetData.name || !presetData.name.trim()) {
            return;
        }
        
        var presetGrid = $('#preset-grid');
        var presetName = presetData.name.trim();
        
        // Remove existing preset with same name if exists
        $(`.preset-item[data-preset="${presetName}"]`).remove();
        
        // Generate color swatches HTML
        var colorSwatchesHtml = '<div class="color-swatches">';
        if (presetData.colors && Array.isArray(presetData.colors)) {
            presetData.colors.forEach(function(color) {
                if (color && color.color) {
                    colorSwatchesHtml += `
                        <div class="color-swatch">
                            <div class="swatch" style="background-color: ${color.color}"></div>
                            <div class="swatch-name">${color.name || ''}</div>
                        </div>
                    `;
                }
            });
        }
        colorSwatchesHtml += '</div>';
        
        var presetHtml = `
            <div class="preset-item" data-preset="${presetName}" data-preset-data="${encodeURIComponent(JSON.stringify(presetData))}">
                <div class="preset-header">
                    <div class="preset-name">${presetName}</div>
                    <div class="preset-actions">
                        <button type="button" class="edit-preset-btn" onclick="editPreset('${presetName}')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3Z"/>
                            </svg>
                        </button>
                        <button type="button" class="delete-preset-btn" onclick="deletePreset('${presetName}')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 6h18"/>
                                <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/>
                                <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="preset-preview">
                    ${colorSwatchesHtml}
                </div>
            </div>
        `;

        // Add to grid
        presetGrid.append(presetHtml);
    }

    // Update existing preset in grid
    function updatePresetInGrid(presetData) {
        var existingPreset = $('.preset-item[data-preset="' + presetData.name + '"]');
        
        // Update colors preview
        var colorsHtml = '';
        if (presetData.colors) {
            $.each(presetData.colors, function(index, colorData) {
                colorsHtml += '<div class="color-swatch" style="background-color: ' + colorData.color + '"></div>';
            });
        }
        existingPreset.find('.color-swatches').html(colorsHtml);
        
        // Update font info - cập nhật để match với structure mới
        var fontInfo = '';
        if (presetData.fonts && presetData.fonts[0] && presetData.fonts[0].font) {
            fontInfo = '<span>Primary: ' + presetData.fonts[0].font + '</span>';
        } else if (presetData.fonts && presetData.fonts.primary) {
            // Backward compatibility
            fontInfo = '<span>Primary: ' + presetData.fonts.primary + '</span>';
        }
        existingPreset.find('.font-info').html(fontInfo);
        
        // Update stored data
        existingPreset.attr('data-preset-data', encodeURIComponent(JSON.stringify(presetData)));
        
        // Add visual feedback for update
        existingPreset.css('background', '#f0fff0').animate({background: '#ffffff'}, 1000);
    }

    // Remove preset card from grid without page reload
    function removePresetFromGrid(presetName) {
        $('.preset-item[data-preset="' + presetName + '"]').fadeOut(300, function() {
            $(this).remove();
        });
    }

    // Delete preset function
    function deletePreset(presetName) {
        if (!confirm('Bạn có chắc chắn muốn xóa preset này?')) {
            return;
        }

        // Show loading state
        var presetItem = $(`.preset-item:has(.preset-name:contains("${presetName}"))`);
        presetItem.addClass('loading');
        
        $.ajax({
            url: customMetaboxData.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'delete_preset_data',
                nonce: customMetaboxData.nonce,
                post_id: customMetaboxData.post_id,
                preset_name: presetName
            },
            success: function(response) {
                if (response.success) {
                    showVisualFeedback(response.data.message || 'Đã xóa preset thành công');
                    
                    // Cập nhật lại customMetaboxData.presetData từ response
                    if (response.data.presets) {
                        customMetaboxData.presetData = response.data.presets;
                    }
                    
                    // Load lại toàn bộ preset grid
                    loadPresetDataFromDatabase();
                } else {
                    showVisualFeedback(response.data || 'Lỗi khi xóa preset', false);
                }
            },
            error: function(xhr, status, error) {
                var errorMessage = xhr.responseText ? xhr.responseText : 'Lỗi kết nối server';
                showVisualFeedback('Lỗi: ' + errorMessage, false);
            },
            complete: function() {
                presetItem.removeClass('loading');
            }
        });
    }

    // Hàm cập nhật màu cho Coloris field
    function updateColorisField(inputElement) {
        const color = $(inputElement).val();
        const clrField = $(inputElement).closest('.clr-field');
        if (clrField.length) {
            clrField.css('--clr-field-color', color);
            clrField.attr('style', `color: ${color}; --clr-field-color: ${color};`);
        }
    }

    function editPreset(presetName) {
        // Get preset data
        let presetData;
        if (Array.isArray(customMetaboxData.presetData)) {
            presetData = customMetaboxData.presetData.find(p => p.name === presetName);
        } else if (typeof customMetaboxData.presetData === 'object') {
            presetData = customMetaboxData.presetData[presetName];
        }

        if (!presetData) {
            showVisualFeedback('Không tìm thấy preset', false);
            return;
        }

        // Set preset name and update modal title
        $('#preset-name').val(presetName);
        $('#preset-modal-title').text(`Sửa Preset "${presetName}"`);
        $('#preset-save-button').text('Update Preset').attr('onclick', 'updateExistingPreset()');
        originalPresetName = presetName;

        // Set colors
        if (Array.isArray(presetData.colors)) {
            const colorInputs = [
                '#preset-primary-color',
                '#preset-secondary-color',
                '#preset-text-color',
                '#preset-accent-color'
            ];

            colorInputs.forEach((inputId, index) => {
                const color = presetData.colors[index]?.color || '';
                $(inputId).val(color);
                updateColorisField($(inputId)[0]);
            });
        }
    
        // Set fonts
        if (Array.isArray(presetData.fonts)) {
            $('#preset-primary-font').val(presetData.fonts[0]?.font || '').trigger('change');
            $('#preset-secondary-font').val(presetData.fonts[1]?.font || '').trigger('change');
            $('#preset-text-font').val(presetData.fonts[2]?.font || '').trigger('change');
            $('#preset-accent-font').val(presetData.fonts[3]?.font || '').trigger('change');
        }

        // Update modal state
        $('#preset-creator-modal').addClass('editing-mode');
        isEditMode = true;
        showPresetCreator();
    }

    // Update existing preset function
    function updateExistingPreset() {
        // Validate preset name
        var presetName = $('#preset-name').val().trim();
        if (!presetName) {
            showVisualFeedback('Vui lòng nhập tên preset', false);
            return;
        }

        // Show loading state
        $('#preset-save-button').prop('disabled', true).addClass('loading');
        showVisualFeedback('Đang cập nhật preset...', true);

        // Collect preset data
        var presetData = {
            name: presetName,
            colors: [
                {
                    name: 'primary',
                    text: '--e-global-color-primary',
                    color: $('#preset-primary-color').val()
                },
                {
                    name: 'secondary',
                    text: '--e-global-color-secondary',
                    color: $('#preset-secondary-color').val()
                },
                {
                    name: 'text',
                    text: '--e-global-color-text',
                    color: $('#preset-text-color').val()
                },
                {
                    name: 'accent',
                    text: '--e-global-color-accent',
                    color: $('#preset-accent-color').val()
                }
            ],
            fonts: [
                {
                    name: 'primary',
                    text: '--e-global-typography-primary-font-family',
                    font: $('#preset-primary-font').val()
                },
                {
                    name: 'secondary',
                    text: '--e-global-typography-secondary-font-family',
                    font: $('#preset-secondary-font').val()
                },
                {
                    name: 'text',
                    text: '--e-global-typography-text-font-family',
                    font: $('#preset-text-font').val()
                },
                {
                    name: 'accent',
                    text: '--e-global-typography-accent-font-family',
                    font: $('#preset-accent-font').val()
                }
            ]
        };

        // Send AJAX request
        jQuery.ajax({
            url: customMetaboxData.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'mac_update_preset',
                nonce: customMetaboxData.nonce,
                post_id: customMetaboxData.post_id,
                original_name: originalPresetName,
                preset_data: JSON.stringify(presetData)
            },
            success: function(response) {
                if (response.success) {
                    showVisualFeedback('Cập nhật preset thành công!', true);
                    
                    // Update grid
                    if (response.data.presets) {
                        customMetaboxData.presetData = response.data.presets;
                        $('#preset-grid').empty();
                        Object.values(response.data.presets).forEach(function(preset) {
                            addPresetToGrid(preset);
                        });
                    }
                    
                    hidePresetCreator();
                } else {
                    showVisualFeedback('Có lỗi xảy ra: ' + (response.data?.message || 'Không xác định'), false);
                }
            },
            error: function(xhr, status, error) {
                showVisualFeedback('Có lỗi xảy ra khi cập nhật preset', false);
            },
            complete: function() {
                $('#preset-save-button').prop('disabled', false).removeClass('loading');
            }
        });
    }

    // Clear preset creator form
    function clearPresetCreator() {
        $('#preset-name').val('');
        $('#preset-primary-color').val('#F26212');
        $('#preset-secondary-color').val('#FBAE85');
        $('#preset-text-color').val('#333333');
        $('#preset-accent-color').val('#FF6B35');
        $('#preset-primary-font').val('');
        $('#preset-secondary-font').val('');
        $('#preset-text-font').val('');
        $('#preset-accent-font').val('');
        $('#preset-custom-colors-container').empty();
        $('#preset-custom-fonts-container').empty();
        presetColorCounter = 5;
        presetFontCounter = 5;
    }

    // Add preset custom color function
    function addPresetCustomColor() {
        var container = $('#preset-custom-colors-container');
        var newItemHtml = `
            <div class="color-control custom-item" data-index="${presetColorCounter}" data-type="color">
                <div class="color-label-row">
                    <label>Mac Custom Color ${presetColorCounter}</label>
                    <input type="text" id="preset_custom_color_name_${presetColorCounter}" name="preset_custom_color_name_${presetColorCounter}" value="Mac Custom Color ${presetColorCounter}" class="color-name-input" placeholder="Nhập tên tùy chỉnh">
                </div>
                <input type="text" data-coloris id="preset_custom_color_${presetColorCounter}" name="preset_custom_color_${presetColorCounter}" value="#F26212" class="coloris">
                <button type="button" class="delete-item-btn" onclick="removePresetCustomColor(${presetColorCounter})">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x-icon lucide-x">
                        <path d="M18 6 6 18"/>
                        <path d="m6 6 12 12"/>
                    </svg>
                </button>
            </div>
        `;
        
        container.append(newItemHtml);
        
        // Initialize Coloris for new color input
        if (typeof Coloris !== 'undefined') {
        Coloris({
                el: `#preset_custom_color_${presetColorCounter}`,
            theme: 'polaroid',
            themeMode: 'light',
            formatToggle: true,
            closeButton: true,
                clearButton: true,
                alpha: true, // Enable alpha channel support
                swatches: [
                    '#F26212',
                    '#FBAE85',
                    '#333333',
                    '#FF6B35',
                    '#4CAF50',
                    '#2196F3',
                    '#9C27B0',
                    '#FF9800',
                    '#00000000' // Transparent color
                ]
            });
            
            // Sync button color
            syncClrFieldButtonColor($(`#preset_custom_color_${presetColorCounter}`));
        }
        
        presetColorCounter++;
    }

    // Remove preset custom color function
    function removePresetCustomColor(index) {
        $(`#preset-custom-colors-container .color-control[data-index="${index}"]`).remove();
    }

    // Add preset custom font function
    function addPresetCustomFont() {
        var container = $('#preset-custom-fonts-container');
        var newItemHtml = `
            <div class="font-control custom-item" data-index="${presetFontCounter}" data-type="font">
                <div class="font-label-row">
                    <label>Mac Custom Font ${presetFontCounter}</label>
                    <input type="text" id="preset_custom_font_name_${presetFontCounter}" name="preset_custom_font_name_${presetFontCounter}" value="Mac Custom Font ${presetFontCounter}" class="font-name-input" placeholder="Nhập tên tùy chỉnh">
                </div>
                <div class="font-class-row">
                    <label>Class Name</label>
                    <textarea id="preset_custom_font_class_${presetFontCounter}" name="preset_custom_font_class_${presetFontCounter}" class="font-class-input" placeholder="Nhập Custom Class Name" rows="2"></textarea>
                </div>
                <select id="preset_custom_font_${presetFontCounter}" name="preset_custom_font_${presetFontCounter}" class="font-select widefat">
                    ${generateFontOptions()}
                </select>
                <button type="button" class="delete-item-btn" onclick="removePresetCustomFont(${presetFontCounter})">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x-icon lucide-x">
                        <path d="M18 6 6 18"/>
                        <path d="m6 6 12 12"/>
                    </svg>
                </button>
            </div>
        `;
        
        container.append(newItemHtml);
        
        // Khởi tạo Select2 cho select vừa thêm trong preset modal
        setTimeout(function() {
            initPresetModalSelect2();
        }, 100);
        
        presetFontCounter++;
    }

    // Remove preset custom font function
    function removePresetCustomFont(index) {
        $(`#preset-custom-fonts-container .font-control[data-index="${index}"]`).remove();
    }

    // Close modal when clicking outs1ide
    $(document).on('click', '.preset-modal', function(e) {
        if (e.target === this) {
            hidePresetCreator();
        }
    });
    
    // Custom Publish Button Handler
    initCustomPublishButton();
    
    // Custom Publish Button Functionality - Chỉ monitoring, không can thiệp
    function initCustomPublishButton() {
        // Monitor cho sự thay đổi post status để cập nhật nút
        if (typeof MutationObserver !== 'undefined') {
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'value') {
                        // Button text changed
                    }
                });
            });
            
            var publishButton = document.getElementById('publish');
            if (publishButton) {
                observer.observe(publishButton, { 
                    attributes: true, 
                    attributeFilter: ['value'] 
                });
            }
        }
        
        // Kiểm tra và force update nút publish nếu cần thiết
        setTimeout(function() {
            checkAndUpdatePublishButton();
        }, 1000);
    }
    
    // Function để kiểm tra và cập nhật nút publish
    function checkAndUpdatePublishButton() {
        var currentStatus = $('#post_status').val();
        var originalStatus = $('#original_post_status').val();
        var $publishBtn = $('#publish');
        
        // Nếu post đã được publish nhưng nút vẫn hiển thị "Publish"
        if ((currentStatus === 'publish' || originalStatus === 'publish') && $publishBtn.val() === 'Publish') {
            $publishBtn.val('Update');
        }
    }

    // Initialize existing font controls with common fonts
    function initializeExistingFontControls() {
        $('.font-select').each(function() {
            var currentValue = $(this).val();
            $(this).html(generateFontOptions(currentValue));
        });
        // Re-init select2
        $('.font-select').select2('destroy');
        initFontSelect2();
    }

    // Initialize counters based on existing items
    function initializeCounters() {
        // Update color counter
        var maxColorIndex = 4; // Start from 4 since fixed colors are 0-3
        $('#colors-container .color-control').each(function() {
            var index = parseInt($(this).data('index'));
            if (!isNaN(index) && index > maxColorIndex) {
                maxColorIndex = index;
            }
        });
        colorCounter = maxColorIndex + 1;

        // Update font counter
        var maxFontIndex = 4; // Start from 4 since fixed fonts are 0-3
        $('#fonts-container .font-control').each(function() {
            var index = parseInt($(this).data('index'));
            if (!isNaN(index) && index > maxFontIndex) {
                maxFontIndex = index;
            }
        });
        fontCounter = maxFontIndex + 1;

        // Update preset color counter
        var maxPresetColorIndex = 4;
        $('#preset-custom-colors-container .color-control').each(function() {
            var index = parseInt($(this).data('index'));
            if (!isNaN(index) && index > maxPresetColorIndex) {
                maxPresetColorIndex = index;
            }
        });
        presetColorCounter = maxPresetColorIndex + 1;

        // Update preset font counter
        var maxPresetFontIndex = 4;
        $('#preset-custom-fonts-container .font-control').each(function() {
            var index = parseInt($(this).data('index'));
            if (!isNaN(index) && index > maxPresetFontIndex) {
                maxPresetFontIndex = index;
            }
        });
        presetFontCounter = maxPresetFontIndex + 1;
    }

    // Call initializeCounters when page loads
    initializeCounters();

    // Thêm event listener cho tất cả input color
    $(document).ready(function() {
        // Cập nhật màu khi input thay đổi
        $(document).on('input change', '.coloris', function() {
            updateColorisField(this);
        });

        // Cập nhật màu khi Coloris thay đổi
        if (typeof Coloris !== 'undefined') {
            Coloris.on('change', instance => {
                updateColorisField(instance.input);
            });
        }
    });

    function initFontSelect2InTab(tabSelector) {
    $(tabSelector + ' .font-select').each(function() {
        // Nếu đã init Select2 trước đó thì destroy để khởi tạo lại
        if ($(this).hasClass('select2-hidden-accessible')) {
            $(this).select2('destroy');
        }
        $(this).empty();

        // Lấy giá trị font ban đầu từ data-value
        var valueFont = $(this).data('value') || '';
        
        // Khởi tạo Select2
        $(this).select2({
            width: '100%',
            placeholder: 'Chọn font...',
            allowClear: true,
            dropdownAutoWidth: true,
            minimumResultsForSearch: 10,
            ajax: {
                transport: function (params, success, failure) {
                    var term = params.data.q ? params.data.q.toLowerCase() : '';
                    var results;
                    if (!term) {
                        results = getOrderedFonts(30).map(function(font) {
                            return { id: font.value, text: font.text };
                        });
                    } else {
                        results = macFontsData.fonts.filter(function(font) {
                            return font.text.toLowerCase().indexOf(term) !== -1;
                        }).map(function(font) {
                            return { id: font.value, text: font.text };
                        });
                    }
                    success({ results: results });
                },
                processResults: function (data) {
                    return { results: data.results };
                }
            },
            templateSelection: function (data, container) {
                return data.text || data.id;
            },
            templateResult: function (data) {
                return data.text || data.id;
            },
            escapeMarkup: function (m) { return m; }
        });

        // Nếu có valueFont → set làm giá trị ban đầu
        if (valueFont) {
            var selected = macFontsData.fonts.find(function(f){ return f.value === valueFont; });
            if (selected) {
                var option = new Option(selected.text, selected.value, true, true);
                $(this).append(option).trigger('change');
            }
        }
    });
}


    // Helper: log tab đang active
    function logActiveTab() {
        var $activeTab = $('.tab-panel.active');
        if ($activeTab.length) {
            var tabId = $activeTab.attr('id');
            var tabName = tabId ? tabId.replace('-tab', '') : '';
        } else {
        }
    }

    // Khi document ready, log tab active
    $(function() {
        logActiveTab();
        var $activeTab = $('.tab-panel.active');
        if ($activeTab.length) {
            initFontSelect2InTab('#' + $activeTab.attr('id'));
        }
    });

    // Khi chuyển tab, log tab active
    $('.tab-button').on('click', function() {
        var targetTab = $(this).data('tab');
        setTimeout(function() {
            logActiveTab();
            initFontSelect2InTab('#' + targetTab + '-tab');
        }, 100);
    });

    // Close modal when clicking on backdrop
    $(document).on('click', '.preset-modal', function(e) {
        if (e.target === this) {
            hidePresetCreator();
        }
    });

    // Close modal with Escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#preset-creator-modal').is(':visible')) {
            hidePresetCreator();
        }
    });

    // ====== COPY TO CLIPBOARD FUNCTIONALITY ======
    
    // Copy to clipboard function with visual feedback
    function copyToClipboard(value, button) {
        if (navigator.clipboard && window.isSecureContext) {
            // Modern clipboard API
            navigator.clipboard.writeText(value).then(function() {
                showCopySuccess(button, value);
            }).catch(function(err) {
                fallbackCopyTextToClipboard(value, button);
            });
        } else {
            // Fallback for older browsers
            fallbackCopyTextToClipboard(value, button);
        }
    }

    // Fallback copy method for older browsers
    function fallbackCopyTextToClipboard(text, button) {
        var textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.top = "0";
        textArea.style.left = "0";
        textArea.style.position = "fixed";
        textArea.style.opacity = "0";
        textArea.style.pointerEvents = "none";

        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            var successful = document.execCommand('copy');
            if (successful) {
                showCopySuccess(button, text);
            } else {
                showCopyError(button);
            }
        } catch (err) {
            showCopyError(button);
        }

        document.body.removeChild(textArea);
    }

    // Show copy success feedback
    function showCopySuccess(button, value) {
        var $button = $(button);
        
        // Store original HTML before any changes
        if (!$button.data('original-html')) {
            $button.data('original-html', $button.html());
        }
        
        // Add copied class for styling
        $button.addClass('copied');
        
        // Change icon to checkmark temporarily
        $button.html(`
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 6 9 17l-5-5"/>
            </svg>
        `);
        
        // Show toast notification
        showVisualFeedback(`Copied: ${value}`, true);
        
        // Reset after 1.5 seconds
        setTimeout(function() {
            $button.removeClass('copied');
            // Restore original copy icon
            var originalHtml = $button.data('original-html') || `
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                </svg>
            `;
            $button.html(originalHtml);
        }, 1500);
    }

    // Show copy error feedback
    function showCopyError(button) {
        showVisualFeedback('Failed to copy to clipboard', false);
    }

    // Event handler for copy color value buttons
    $(document).on('click', '.copy-value-btn:not(.copy-font-btn)', function(e) {
        e.preventDefault();
        var value = $(this).data('copy-value');
        if (value) {
            copyToClipboard(value, this);
        }
    });

    // Event handler for copy font value buttons
    $(document).on('click', '.copy-font-btn', function(e) {
        e.preventDefault();
        var target = $(this).data('copy-target');
        var $fontControl = $(this).closest('.font-control');
        var copyValue = '';
        
        // Try to get text from Select2 rendered element first
        var $select2Rendered = $fontControl.find('.select2-selection__rendered');
        if ($select2Rendered.length && $select2Rendered.text().trim()) {
            var renderedText = $select2Rendered.text().trim();
            if (renderedText && renderedText !== 'Select Font' && renderedText !== 'Chọn font...' && renderedText !== 'Select Font') {
                copyValue = renderedText;
            }
        }
        
        // Fallback: try to get from select element if Select2 text not available
        if (!copyValue) {
            var $select;
            
            // Handle both fixed fonts and custom fonts
            if (target) {
                // Custom font with specific ID
                $select = $('#' + target);
            } else {
                // Fixed font - find select in same container
                $select = $fontControl.find('.font-select');
            }
            
            if ($select.length === 0) {
                showVisualFeedback('Font select not found', false);
                return;
            }
            
            var value = $select.val();
            
            if (value) {
                // Get the display text instead of value if available
                var displayText = $select.find('option:selected').text();
                copyValue = displayText && displayText !== 'Select Font' ? displayText : value;
            }
        }
        
        if (copyValue) {
            copyToClipboard(copyValue, this);
        } else {
            showVisualFeedback('No font selected to copy', false);
        }
    });

    // Update copy button data-copy-value when color input changes
    $(document).on('input change', '.coloris', function() {
        var newColor = $(this).val();
        var $copyBtn = $(this).closest('.fixed-item, .custom-item').find('.copy-value-btn:not(.copy-font-btn)');
        if ($copyBtn.length) {
            $copyBtn.attr('data-copy-value', newColor);
        }
    });

    // Function to check and update font copy button visibility
    function updateFontCopyButtonVisibility($fontControl) {
        var $fontSelect = $fontControl.find('.font-select');
        var selectedValue = $fontSelect.val();
        var selectedText = '';
        
        // Check Select2 rendered text if available
        var $select2Rendered = $fontControl.find('.select2-selection__rendered');
        if ($select2Rendered.length) {
            selectedText = $select2Rendered.text().trim();
        }
        
        // Show copy button if font is selected and not placeholder
        var hasValidSelection = (selectedValue && selectedValue.trim() !== '') || 
                               (selectedText && selectedText !== 'Select Font' && selectedText !== 'Chọn font...' && selectedText !== '');
        
        if (hasValidSelection) {
            $fontControl.addClass('has-font-selected');
        } else {
            $fontControl.removeClass('has-font-selected');
        }
    }

    // Update copy button when font selection changes
    $(document).on('change', '.font-select', function() {
        var $fontControl = $(this).closest('.font-control');
        updateFontCopyButtonVisibility($fontControl);
    });

    // Check initial state of all font controls when page loads
    function initializeFontCopyButtons() {
        $('.font-control').each(function() {
            updateFontCopyButtonVisibility($(this));
        });
    }

    // Initialize all copy buttons (store original HTML)
    function initializeCopyButtons() {
        $('.copy-value-btn, .copy-font-btn').each(function() {
            var $button = $(this);
            if (!$button.data('original-html')) {
                $button.data('original-html', $button.html());
            }
        });
    }

    // Initialize font copy buttons on page load
    setTimeout(function() {
        initializeFontCopyButtons();
        initializeCopyButtons(); // Store original HTML for all copy buttons
    }, 500);

    // Re-initialize when Select2 is loaded/changed
    $(document).on('select2:select select2:unselect', '.font-select', function() {
        var $fontControl = $(this).closest('.font-control');
        setTimeout(function() {
            updateFontCopyButtonVisibility($fontControl);
        }, 100);
    });
}); 
