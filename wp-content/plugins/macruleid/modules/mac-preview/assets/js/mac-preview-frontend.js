/**
 * Mac Dynamic Section Preview - Frontend JavaScript
 * 
 * @package Mac Preview
 * @version 2.0
 * @author Híu
 */

(function($) {
    'use strict';

    // Check if required dependencies exist
    if (typeof $ === 'undefined') {
        console.error('[Mac Preview] jQuery is not loaded');
        return;
    }

    if (typeof macPreviewData === 'undefined') {
        console.error('[Mac Preview] macPreviewData is not defined');
        return;
    }

    // Wait for DOM ready
//     $(document).ready(function() {
//         initMacPreview();
//     });

    /**
     * Initialize Mac Preview functionality
     */
    function initMacPreview() {
        // Only show for admin users
        if (!macPreviewData.isAdmin) {
            return;
        }

        // Create popup element
        const popup = createPopupElement();
        
        // Create floating button
        const floatBtn = createFloatingButton();
        
        // Initialize state management
        const state = new MacPreviewState();
        
        // Initialize UI controller
        const ui = new MacPreviewUI(popup, state);
        
        // Bind events
        bindEvents(floatBtn, popup, ui);
    }

    /**
     * Create popup element
     */
    function createPopupElement() {
        const popup = document.createElement('div');
        popup.id = 'mac-preview-popup';
        document.body.appendChild(popup);
        return popup;
    }

    /**
     * Create floating button
     */
    function createFloatingButton() {
        const floatBtn = document.createElement('button');
        floatBtn.id = 'mac-preview-float-btn';
        floatBtn.innerHTML = '📤';
        floatBtn.title = 'Mac Dynamic Section Export';
        document.body.appendChild(floatBtn);
        return floatBtn;
    }

    /**
     * State management class
     */
    class MacPreviewState {
        constructor() {
            this.containers = [];
            this.previewUrl = window.location.href;
            this.loadState();
        }

        loadState() {
            try {
                const saved = localStorage.getItem('macPreviewState');
                if (saved) {
                    const data = JSON.parse(saved);
                    
                    // Check if we have page-specific cache
                    const currentPageId = this.getCurrentPageId();
                    const cacheKey = `page_${currentPageId}`;
                    
                    if (data[cacheKey]) {
                        this.containers = data[cacheKey].containers || [];
                        this.previewUrl = data[cacheKey].previewUrl || window.location.href;
                    }
                }
            } catch (e) {
                console.error('[Mac Preview] Error loading state:', e);
                this.containers = [];
                this.previewUrl = window.location.href;
            }
        }

        saveState() {
            try {
                const currentPageId = this.getCurrentPageId();
                const cacheKey = `page_${currentPageId}`;
                
                // Load existing cache
                let allCache = {};
                const saved = localStorage.getItem('macPreviewState');
                if (saved) {
                    allCache = JSON.parse(saved);
                }
                
                // Update current page cache
                allCache[cacheKey] = {
                    containers: this.containers,
                    previewUrl: this.previewUrl,
                    timestamp: Date.now()
                };
                
                // Keep only last 10 pages to prevent storage bloat
                const keys = Object.keys(allCache);
                if (keys.length > 10) {
                    const sortedKeys = keys.sort((a, b) => {
                        const timeA = allCache[a].timestamp || 0;
                        const timeB = allCache[b].timestamp || 0;
                        return timeB - timeA;
                    });
                    
                    // Keep only the 10 most recent
                    const newCache = {};
                    sortedKeys.slice(0, 10).forEach(key => {
                        newCache[key] = allCache[key];
                    });
                    allCache = newCache;
                }
                
                localStorage.setItem('macPreviewState', JSON.stringify(allCache));
            } catch (e) {
                console.error('[Mac Preview] Error saving state:', e);
            }
        }

        getCurrentPageId() {
            // Try to get page ID from Elementor data
            const elementorRoot = document.querySelector('[data-elementor-id]');
            if (elementorRoot) {
                return elementorRoot.getAttribute('data-elementor-id');
            }
            
            // Fallback: use URL hash
            return btoa(window.location.pathname).replace(/[^a-zA-Z0-9]/g, '').substr(0, 10);
        }

        getContainers() {
            return this.containers;
        }

        addContainer(container) {
            this.containers.push(container);
            this.saveState();
        }

        removeContainer(index) {
            this.containers.splice(index, 1);
            this.saveState();
        }

        updateContainer(index, container) {
            if (index >= 0 && index < this.containers.length) {
                this.containers[index] = container;
                this.saveState();
            }
        }

        moveContainer(fromIndex, toIndex) {
            if (fromIndex >= 0 && fromIndex < this.containers.length && 
                toIndex >= 0 && toIndex < this.containers.length) {
                const item = this.containers.splice(fromIndex, 1)[0];
                this.containers.splice(toIndex, 0, item);
                this.saveState();
            }
        }

        clearContainers() {
            this.containers = [];
            this.saveState();
        }

        setPreviewUrl(url) {
            this.previewUrl = url;
            this.saveState();
        }

        getPreviewUrl() {
            return this.previewUrl;
        }
    }

    /**
     * UI management class
     */
    class MacPreviewUI {
        constructor(popup, state) {
            this.popup = popup;
            this.state = state;
            this.dragSrcIdx = null;
            this.dragOverIdx = null;
        }

        render() {
            const containers = this.state.getContainers();
            const previewUrl = this.state.getPreviewUrl();
            
            const html = `
                <div class="mac-preview-popup-content">
                    <div class="mac-preview-header">
                        <div class="mac-header-top">
                            <div class="mac-header-content">
                                <div class="mac-header-icon">🚀</div>
                                <div class="mac-header-text">
                                    <h3>Mac Dynamic Section Export</h3>
                                    <p>Export and preview your Elementor containers with ease</p>
                                </div>
                            </div>
                            <button class="mac-preview-close">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M18 6L6 18M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        <div class="mac-url-section">
                            <label>Preview URL:</label>
                            <input id="mac-preview-url" type="text" value="${this.escapeHtml(previewUrl)}" placeholder="Enter URL to scan..."/>
                            <button class="mac-preview-btn scan-btn" id="mac-scan-btn">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
                                    <path d="m21 21-4.35-4.35" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                Scan URL
                            </button>
                        </div>
                    </div>
                    <div class="mac-preview-body">
                        <div class="mac-containers-header">
                            <span class="icon">🗂️</span> 
                            Containers Selected 
                            <span class="mac-containers-count">${containers.length}</span>
                        </div>
                        <div id="mac-containers-list">
                            ${containers.map((c, idx) => this.renderContainerRow(c, idx)).join('')}
                            <div style="text-align:center;">
                                <button class="mac-preview-btn secondary" id="mac-add-container-btn">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" style="width:16px;height:16px;stroke-width:2;">
                                        <path d="M12 5v14M5 12h14"/>
                                    </svg> 
                                    Add Container
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="mac-preview-footer">
                        <div class="mac-preview-stats">
                            <span class="stat-label">Containers:</span> 
                            <span>${containers.length}</span>
                            <span class="ready-status">Ready to Export</span>
                        </div>
                        <div class="mac-preview-actions">
                            <button class="mac-preview-btn primary" id="mac-preview-btn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" style="width:16px;height:16px;stroke-width:2;">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                Preview
                            </button>
                            <button class="mac-preview-btn secondary" id="mac-clear-btn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" style="width:16px;height:16px;stroke-width:2;">
                                    <path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                                </svg>
                                Clear
                            </button>
                            <button class="mac-preview-btn danger" id="mac-close-btn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" style="width:16px;height:16px;stroke-width:2;">
                                    <path d="M18 6L6 18M6 6l12 12"/>
                                </svg>
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            this.popup.innerHTML = html;
        }

        renderContainerRow(container, idx) {
            let statusHtml = '';
            if (container.id === 'container' || !container.id) {
                statusHtml = '<span class="mac-status-badge custom"><span style="font-size:15px;">✏️</span>Custom</span>';
            } else {
                statusHtml = '<span class="mac-status-badge current"><span style="font-size:15px;">🎯</span>Current</span>';
            }
            // Tên hiển thị mặc định
            const displayId = (container.id === 'container' || !container.id) ? 'Container' : this.escapeHtml(container.id);
            const displayPageSpan = `page:${container.page || ''}-tem:${(container.id === 'container' || !container.id) ? 'Container' : this.escapeHtml(container.id)}`;
            return `
                <div class="mac-container-row" draggable="true" data-idx="${idx}">
                    <div class="mac-kebab-container">
                        <span class="mac-kebab-menu">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="12" cy="5" r="2" fill="#bbb"/>
                                <circle cx="12" cy="12" r="2" fill="#bbb"/>
                                <circle cx="12" cy="19" r="2" fill="#bbb"/>
                            </svg>
                        </span>
                    </div>
                    <div class="mac-container-content">
                        <div class="mac-container-id-title" data-idx="${idx}">${displayId}</div>
                        <div class="mac-container-bottom">
                            <span class="mac-container-page-span" data-idx="${idx}">${displayPageSpan}</span>
                            ${statusHtml}
                        </div>
                    </div>
                    <button class="mac-preview-btn danger small mac-del-btn" data-idx="${idx}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" style="width:14px;height:14px;stroke-width:2;">
                            <path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                        </svg>
                    </button>
                </div>
            `;
        }

        show() {
            // Check if we're on a different page than cached data
            const currentUrl = window.location.href;
            const cachedUrl = this.state.previewUrl;
            
            // If no containers or different page, auto scan current page
            if (this.state.getContainers().length === 0 || currentUrl !== cachedUrl) {
                console.log(`[Mac Preview] Scanning page: ${currentUrl !== cachedUrl ? 'different page' : 'no containers'}`);
                this.autoScanCurrentPage();
            }
            
            this.render();
            this.popup.style.display = 'flex';
            this.popup.classList.add('show');
            this.bindEvents();
        }

        hide() {
            this.popup.style.display = 'none';
            this.popup.classList.remove('show');
        }

        autoScanCurrentPage() {
            try {
                // Clear current containers first
                this.state.containers = [];
                
                // Find Elementor root element on current page
                const root = document.querySelector('[data-elementor-type="wp-page"]');
                
                if (!root) {
                    console.log('[Mac Preview] No Elementor page found on current page');
                    this.state.saveState(); // Save empty state
                    return;
                }
                
                // Get page ID
                const pageId = root.getAttribute('data-elementor-id') || '';
                
                // Find all container elements
                const containersFound = Array.from(root.children).filter(el => 
                    el.getAttribute('data-element_type') === 'container'
                );
                
                if (containersFound.length === 0) {
                    console.log('[Mac Preview] No containers found on current page');
                    this.state.saveState(); // Save empty state
                    return;
                }
                
                // Update state with found containers
                this.state.containers = containersFound.map(el => ({
                    id: el.getAttribute('data-id') || '',
                    page: pageId
                }));
                
                // Set current page URL as preview URL
                this.state.setPreviewUrl(window.location.href);
                
                this.state.saveState();
                
                console.log(`[Mac Preview] Auto-scanned ${containersFound.length} containers on current page`);
                
            } catch(e) {
                console.error('[Mac Preview] Auto scan error:', e);
                // Don't show alert for auto scan errors, just log them
            }
        }

        bindEvents() {
            // Close buttons
            $(this.popup).find('.mac-preview-close, #mac-close-btn').on('click', () => {
                this.hide();
            });

            // Clear button
            $(this.popup).find('#mac-clear-btn').on('click', () => {
                this.state.clearContainers();
                this.render();
                this.bindEvents();
            });

            // Preview button
            $(this.popup).find('#mac-preview-btn').on('click', () => {
                this.handlePreview();
            });

            // Add container button
            $(this.popup).find('#mac-add-container-btn').on('click', () => {
                this.handleAddContainer();
            });

            // Scan button
            $(this.popup).find('#mac-scan-btn').on('click', () => {
                this.handleScanUrl();
            });

            // Preview URL change
            $(this.popup).find('#mac-preview-url').on('change', (e) => {
                this.state.setPreviewUrl(e.target.value);
            });

            // Delete buttons
            $(this.popup).find('.mac-del-btn').on('click', (e) => {
                this.handleDeleteContainer(e);
            });

            // Edit container page spans
            $(this.popup).find('.mac-container-page-span').on('click', (e) => {
                this.handleEditContainer(e);
            });

            // Drag and drop
            this.bindDragEvents();
        }

        bindDragEvents() {
            const rows = this.popup.querySelectorAll('.mac-container-row');
            
            rows.forEach(row => {
                row.addEventListener('dragstart', (e) => {
                    this.dragSrcIdx = +row.dataset.idx;
                    row.classList.add('dragging');
                    setTimeout(() => {
                        if (row.classList.contains('dragging')) {
                            row.classList.add('drag-placeholder');
                        }
                    }, 100);
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', '');
                });

                row.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    this.dragOverIdx = +row.dataset.idx;
                    if (this.dragOverIdx !== this.dragSrcIdx) {
                        row.classList.add('drag-over');
                    }
                });

                row.addEventListener('dragleave', (e) => {
                    if (!row.contains(e.relatedTarget)) {
                        row.classList.remove('drag-over');
                    }
                });

                row.addEventListener('drop', (e) => {
                    e.preventDefault();
                    row.classList.remove('drag-over');
                    if (this.dragSrcIdx !== null && this.dragOverIdx !== null && this.dragSrcIdx !== this.dragOverIdx) {
                        this.handleMoveContainer(this.dragSrcIdx, this.dragOverIdx);
                    }
                    this.dragSrcIdx = null;
                    this.dragOverIdx = null;
                });

                row.addEventListener('dragend', (e) => {
                    row.classList.remove('dragging', 'drag-placeholder');
                    this.popup.querySelectorAll('.mac-container-row').forEach(r => {
                        r.classList.remove('drag-over');
                    });
                });
            });
        }

        handlePreview() {
            const containers = this.state.getContainers();
            if (containers.length === 0) {
                alert('Chưa có container nào!');
                return;
            }
            // Chỉ encode từng phần tử, không encode toàn bộ chuỗi
            const ids = containers.map(c => encodeURIComponent(`page:${c.page}-tem:${c.id}`)).join(',');
            const url = `${macPreviewData.homeUrl}/page-mac-dynamic-section/?id=${ids}`;
            window.open(url, '_blank');
        }

        handleAddContainer() {
            const scrollTop = this.popup.querySelector('.mac-preview-popup-content').scrollTop;
            this.state.addContainer({id: 'container', page: ''});
            this.render();
            this.bindEvents();
            setTimeout(() => {
                this.popup.querySelector('.mac-preview-popup-content').scrollTop = scrollTop;
            }, 10);
        }

        async handleScanUrl() {
            const url = this.popup.querySelector('#mac-preview-url').value.trim();
            if (!url) {
                alert('Nhập URL!');
                return;
            }
            
            this.state.setPreviewUrl(url);
            
            try {
                const response = await fetch(url);
                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const root = doc.querySelector('[data-elementor-type="wp-page"]');
                
                if (!root) {
                    alert('Không tìm thấy data-elementor-type="wp-page"!');
                    return;
                }
                
                const pageId = root.getAttribute('data-elementor-id') || '';
                const containersFound = Array.from(root.children).filter(el => 
                    el.getAttribute('data-element_type') === 'container'
                );
                
                if (containersFound.length === 0) {
                    alert('Không tìm thấy container!');
                    return;
                }
                
                // Update state with found containers
                this.state.containers = containersFound.map(el => ({
                    id: el.getAttribute('data-id') || '',
                    page: pageId
                }));
                this.state.saveState();
                
                this.render();
                this.bindEvents();
                
            } catch(e) {
                console.error('[Mac Preview] Scan error:', e);
                alert('Lỗi khi scan: ' + e.message);
            }
        }

        handleDeleteContainer(e) {
            const scrollTop = this.popup.querySelector('.mac-preview-popup-content').scrollTop;
            const idx = +e.target.closest('.mac-del-btn').dataset.idx;
            
            this.state.removeContainer(idx);
            this.render();
            this.bindEvents();
            
            // Restore scroll position
            setTimeout(() => {
                this.popup.querySelector('.mac-preview-popup-content').scrollTop = scrollTop;
            }, 10);
        }

        handleEditContainer(e) {
            const span = e.target;
            const idx = +span.dataset.idx;
            const container = this.state.getContainers()[idx];
            const value = `page:${container.page}-tem:${container.id}`;
            // Tạo div cha bọc input + validation nếu chưa có
            let parentDiv = span.parentNode;
            if (!parentDiv.classList.contains('mac-container-edit-wrap')) {
                // Tạo div bọc
                const wrap = document.createElement('div');
                wrap.className = 'mac-container-edit-wrap';
                parentDiv.insertBefore(wrap, span);
                wrap.appendChild(span);
                parentDiv = wrap;
            }
            // Tạo input
            const input = document.createElement('input');
            input.type = 'text';
            input.value = value;
            input.className = 'mac-container-edit-input';
            // Tạo hoặc tìm validation message div
            let validationDiv = parentDiv.querySelector('.mac-validation-message');
            if (!validationDiv) {
                validationDiv = document.createElement('div');
                validationDiv.className = 'mac-validation-message';
                parentDiv.appendChild(validationDiv);
            }
            // Thay thế span bằng input
            span.replaceWith(input);
            input.focus();
            // Validation timeout
            let validationTimeout;
            const showValidation = (message, isError = false) => {
                validationDiv.textContent = message;
                validationDiv.style.display = message ? 'block' : 'none';
                validationDiv.className = 'mac-validation-message' + (message ? ' show' : '') + (isError ? ' error' : ' success');
                input.className = 'mac-container-edit-input' + (message ? (isError ? ' invalid' : ' valid') : '');
            };
            const validateContainerId = (inputValue) => {
                return new Promise((resolve, reject) => {
                    const trimmedValue = inputValue.trim();
                    if (!trimmedValue) {
                        showValidation('Vui lòng nhập Container ID', true);
                        reject({message: 'No container ID'});
                        return;
                    }
                    const match = trimmedValue.match(/^page:(\d+)-tem:([a-zA-Z0-9_-]+)$/);
                    if (!match) {
                        showValidation('Sai định dạng! Đúng: page:PAGE_ID-tem:CONTAINER_ID', true);
                        reject({message: 'Invalid format'});
                        return;
                    }
                    // Show validating state
                    input.className = 'mac-container-edit-input validating';
                    showValidation('Đang kiểm tra...', false);
                    // AJAX validation
                    $.ajax({
                        url: macPreviewData.ajaxUrl || (window.ajaxurl || '/wp-admin/admin-ajax.php'),
                        type: 'POST',
                        data: {
                            action: 'mac_preview_validate_container',
                            container_id: trimmedValue,
                            nonce: macPreviewData.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                showValidation('✓ Container ID hợp lệ', false);
                                resolve(response.data);
                            } else {
                                showValidation(response.data.message || 'Container ID không hợp lệ', true);
                                reject(response.data);
                            }
                        },
                        error: function(xhr, status, error) {
                            showValidation('⚠ Có lỗi xảy ra khi kiểm tra Container ID', true);
                            reject({message: 'Network error'});
                        }
                    });
                });
            };
            // Input validation with timeout
            input.addEventListener('input', (e) => {
                const value = e.target.value;
                clearTimeout(validationTimeout);
                showValidation('');
                if (!value.trim()) {
                    return;
                }
                validationTimeout = setTimeout(() => {
                    validateContainerId(value).catch(() => {});
                }, 500);
            });
            const save = () => {
                const scrollTop = this.popup.querySelector('.mac-preview-popup-content').scrollTop;
                const val = input.value.trim();
                if (!val) {
                    this.state.updateContainer(idx, {
                        page: '',
                        id: ''
                    });
                    this.render();
                    this.bindEvents();
                    return;
                }
                const match = val.match(/^page:(\d+)-tem:([a-zA-Z0-9_-]+)$/);
                if (match) {
                    validateContainerId(val).then(() => {
                        this.state.updateContainer(idx, {
                            page: match[1],
                            id: match[2]
                        });
                        this.render();
                        this.bindEvents();
                        setTimeout(() => {
                            this.popup.querySelector('.mac-preview-popup-content').scrollTop = scrollTop;
                        }, 10);
                    }).catch(() => {
                        input.focus();
                    });
                } else {
                    showValidation('Sai định dạng! Đúng: page:PAGE_ID-tem:CONTAINER_ID', true);
                    input.focus();
                }
            };
            input.addEventListener('blur', () => {
                setTimeout(save, 100);
            });
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    save();
                }
                if (e.key === 'Escape') {
                    this.render();
                    this.bindEvents();
                }
            });
        }

        handleMoveContainer(fromIdx, toIdx) {
            const scrollTop = this.popup.querySelector('.mac-preview-popup-content').scrollTop;
            
            this.state.moveContainer(fromIdx, toIdx);
            this.render();
            this.bindEvents();
            
            // Restore scroll position
            setTimeout(() => {
                this.popup.querySelector('.mac-preview-popup-content').scrollTop = scrollTop;
            }, 10);
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    /**
     * Bind main events
     */
    function bindEvents(floatBtn, popup, ui) {
        // Float button click
        floatBtn.addEventListener('click', () => {
            ui.show();
        });

        // Close popup when clicking outside
        popup.addEventListener('click', (e) => {
            if (e.target === popup) {
                ui.hide();
            }
        });

        // Close popup with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && popup.classList.contains('show')) {
                ui.hide();
            }
        });
    }

})(jQuery); 
