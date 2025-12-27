// Activity Log JavaScript

document.addEventListener('click', function(e){
    if(e.target && e.target.classList.contains('toggle-desc')){
        var wrap = e.target.closest('.desc-wrap');
        if(!wrap) return;
        var content = wrap.querySelector('.desc-content');
        if(!content) return;
        var isExpanded = content.classList.toggle('is-expanded');
        content.classList.toggle('is-collapsed', !isExpanded);
        e.target.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
        e.target.textContent = isExpanded ? 'Hide' : 'Show all';
        
        // If expanding and snapshot comparison not loaded yet, call AJAX
        if(isExpanded && !content.dataset.snapshotLoaded) {
            var logId = content.dataset.logId;
            if(logId) {
                loadSnapshotComparison(logId, content, e.target);
            }
        }
    }
});

/**
 * Load snapshot comparison via AJAX
 */
function loadSnapshotComparison(logId, contentElement, buttonElement) {
    // Check macActivityLog object
    if (typeof macActivityLog === 'undefined') {
        console.error('macActivityLog object not found');
            contentElement.insertAdjacentHTML('beforeend', 
                '<div class="notice notice-error"><p>Error: macActivityLog object not found. Please refresh the page.</p></div>'
            );
        return;
    }
    
    // Show loading
    var originalButtonText = buttonElement.textContent;
    buttonElement.textContent = 'Loading...';
    buttonElement.disabled = true;
    
    // Add loading indicator to content
    var loadingHtml = '<div class="snapshot-loading" style="padding: 20px; text-align: center; color: #666;">';
    loadingHtml += '<span class="spinner is-active" style="float: none; margin: 0;"></span> ';
    loadingHtml += 'Comparing snapshots...</div>';
    contentElement.insertAdjacentHTML('beforeend', loadingHtml);
    
    // Debug log
    console.log('AJAX Request:', {
        url: macActivityLog.ajaxurl,
        action: 'mac_compare_snapshots',
        log_id: logId,
        nonce: macActivityLog.nonce ? 'present' : 'missing'
    });
    
    // Call AJAX
    jQuery.ajax({
        url: macActivityLog.ajaxurl,
        type: 'POST',
        data: {
            action: 'mac_compare_snapshots',
            log_id: logId,
            nonce: macActivityLog.nonce
        },
        success: function(response) {
            // Remove loading indicator
            var loadingEl = contentElement.querySelector('.snapshot-loading');
            if(loadingEl) {
                loadingEl.remove();
            }
            
            if(response.success && response.data) {
                // Render comparison results
                renderSnapshotComparison(response.data, contentElement);
                contentElement.dataset.snapshotLoaded = 'true';
            } else {
                var errorMsg = response.data || 'Failed to load snapshot comparison';
                contentElement.insertAdjacentHTML('beforeend', 
                    '<div class="notice notice-error"><p>' + errorMsg + '</p></div>'
                );
            }
        },
        error: function(xhr, status, error) {
            // Remove loading indicator
            var loadingEl = contentElement.querySelector('.snapshot-loading');
            if(loadingEl) {
                loadingEl.remove();
            }
            
            // Log detailed error for debugging
            console.error('AJAX Error:', {
                status: status,
                error: error,
                statusCode: xhr.status,
                responseText: xhr.responseText,
                responseJSON: xhr.responseJSON
            });
            
            var errorMsg = 'AJAX Error: ' + error;
            if (xhr.status === 400) {
                errorMsg = 'Bad Request - Possibly due to invalid nonce or missing parameters';
            } else if (xhr.status === 403) {
                errorMsg = 'Forbidden - No access permission';
            } else if (xhr.status === 500) {
                errorMsg = 'Server Error - An error occurred on the server';
            }
            
            // Display response if available
            if (xhr.responseJSON && xhr.responseJSON.data) {
                errorMsg += '<br><small>' + xhr.responseJSON.data + '</small>';
            } else if (xhr.responseText) {
                errorMsg += '<br><small>Response: ' + xhr.responseText.substring(0, 200) + '</small>';
            }
            
            contentElement.insertAdjacentHTML('beforeend', 
                '<div class="notice notice-error"><p>' + errorMsg + '</p></div>'
            );
        },
        complete: function() {
            buttonElement.textContent = originalButtonText;
            buttonElement.disabled = false;
        }
    });
}

/**
 * Render snapshot comparison results - only show Category Info
 */
function renderSnapshotComparison(data, contentElement) {
    var changes = data.changes || [];
    var totalChanges = data.total_changes || 0;
    
    if(totalChanges === 0) {
        contentElement.insertAdjacentHTML('beforeend', 
            '<div class="notice notice-info" style="margin-top: 15px;"><p>No changes detected between 2 snapshots.</p></div>'
        );
        return;
    }
    
    // Create HTML for comparison - only show Category Info
    var comparisonHtml = '<div class="snapshot-comparison" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 5px;">';
    comparisonHtml += '<h4 style="margin: 0 0 15px 0; font-size: 14px; color: #333;">Full Table Comparison (' + totalChanges + ' changes):</h4>';
    
    // Group changes by action type
    var created = changes.filter(function(c) { return c.action === 'created'; });
    var updated = changes.filter(function(c) { return c.action === 'updated'; });
    var deleted = changes.filter(function(c) { return c.action === 'deleted'; });
    
    // Created records - only show Category Info
    if(created.length > 0) {
        comparisonHtml += '<div style="margin-bottom: 15px;">';
        comparisonHtml += '<strong style="color: #28a745;">Created (' + created.length + '):</strong>';
        created.forEach(function(change) {
            var categoryId = change.id || '';
            var categoryName = change.category_name || '';
            
            comparisonHtml += '<div style="margin: 10px 0; padding: 8px; background: #f0f0f0; border-left: 3px solid #0073aa; border-radius: 3px;">';
            comparisonHtml += '<strong>Category Info:</strong><br>';
            if(categoryId) {
                comparisonHtml += 'ID: <strong>' + escapeHtml(categoryId) + '</strong>';
            }
            if(categoryName) {
                if(categoryId) {
                    comparisonHtml += ' | ';
                }
                comparisonHtml += 'Name: <strong>' + escapeHtml(categoryName) + '</strong>';
            }
            comparisonHtml += '</div>';
        });
        comparisonHtml += '</div>';
    }
    
    // Updated records - show Category Info and detailed changes
    if(updated.length > 0) {
        comparisonHtml += '<div style="margin-bottom: 15px;">';
        comparisonHtml += '<strong style="color: #ffc107;">Updated (' + updated.length + '):</strong>';
        
        updated.forEach(function(change) {
            var categoryId = change.id || '';
            var categoryName = change.category_name || '';
            var oldData = change.old_data || {};
            var newData = change.new_data || {};
            var changedFields = change.changed_fields || {};
            
            comparisonHtml += '<div style="margin: 10px 0; padding: 12px; background: #f0f0f0; border-left: 3px solid #0073aa; border-radius: 3px;">';
            comparisonHtml += '<strong>Category Info:</strong><br>';
            if(categoryId) {
                comparisonHtml += 'ID: <strong>' + escapeHtml(categoryId) + '</strong>';
            }
            if(categoryName) {
                if(categoryId) {
                    comparisonHtml += ' | ';
                }
                comparisonHtml += 'Name: <strong>' + escapeHtml(categoryName) + '</strong>';
            }
            
            // Display detailed changes - only show changed fields
            var hasFieldChanges = false;
            var fieldChangesHtml = '<div style="margin-top: 12px;">';
            fieldChangesHtml += '<strong style="color: #856404; font-size: 12px;">Changed Fields:</strong>';
            
            // Loop through changed fields
            Object.keys(changedFields).forEach(function(field) {
                var fieldChange = changedFields[field];
                
                // Priority: get from old_data and new_data (original values, may be array)
                // If not available, get from fieldChange.old and fieldChange.new (already JSON string)
                var oldValue = null;
                var newValue = null;
                
                if(oldData[field] !== undefined) {
                    oldValue = oldData[field];
                } else if(fieldChange && fieldChange.old !== undefined) {
                    oldValue = fieldChange.old;
                }
                
                if(newData[field] !== undefined) {
                    newValue = newData[field];
                } else if(fieldChange && fieldChange.new !== undefined) {
                    newValue = fieldChange.new;
                }
                
                // Special handling for group_repeater
                if(field === 'group_repeater') {
                    // Parse JSON if string
                    var oldRepeater = parseJSON(oldValue);
                    var newRepeater = parseJSON(newValue);
                    
                    fieldChangesHtml += '<div style="margin-top: 8px; padding: 8px; background: #fff; border-left: 3px solid #ffc107; border-radius: 3px;">';
                    fieldChangesHtml += '<strong style="color: #856404; font-size: 11px;">' + escapeHtml(field) + ':</strong>';
                    fieldChangesHtml += renderGroupRepeaterComparison(oldRepeater, newRepeater);
                    fieldChangesHtml += '</div>';
                    hasFieldChanges = true;
                } else {
                    // Other fields - simple display: Field name: old → new
                    fieldChangesHtml += '<div style="margin-top: 6px; padding: 6px 8px; background: #fff; border-left: 3px solid #ffc107; border-radius: 3px; font-size: 11px;">';
                    fieldChangesHtml += '<strong style="color: #856404;">' + escapeHtml(field) + ':</strong> ';
                    fieldChangesHtml += '<span style="color: #999;">' + formatValueSimple(oldValue) + '</span>';
                    fieldChangesHtml += ' → ';
                    fieldChangesHtml += '<span style="color: #d32f2f; font-weight: bold;">' + formatValueSimple(newValue) + '</span>';
                    fieldChangesHtml += '</div>';
                    hasFieldChanges = true;
                }
            });
            
            fieldChangesHtml += '</div>';
            
            if(hasFieldChanges) {
                comparisonHtml += fieldChangesHtml;
            }
            
            comparisonHtml += '</div>';
        });
        
        comparisonHtml += '</div>';
    }
    
    // Deleted records - only show Category Info
    if(deleted.length > 0) {
        comparisonHtml += '<div style="margin-bottom: 15px;">';
        comparisonHtml += '<strong style="color: #dc3545;">Deleted (' + deleted.length + '):</strong>';
        deleted.forEach(function(change) {
            var categoryId = change.id || '';
            var categoryName = '';
            
            if(change.old_data && change.old_data.category_name) {
                categoryName = change.old_data.category_name;
            }
            
            comparisonHtml += '<div style="margin: 10px 0; padding: 8px; background: #f0f0f0; border-left: 3px solid #0073aa; border-radius: 3px;">';
            comparisonHtml += '<strong>Category Info:</strong><br>';
            if(categoryId) {
                comparisonHtml += 'ID: <strong>' + escapeHtml(categoryId) + '</strong>';
            }
            if(categoryName) {
                if(categoryId) {
                    comparisonHtml += ' | ';
                }
                comparisonHtml += 'Name: <strong>' + escapeHtml(categoryName) + '</strong>';
            }
            comparisonHtml += '</div>';
        });
        comparisonHtml += '</div>';
    }
    
    comparisonHtml += '</div>';
    
    // Insert into content
    contentElement.insertAdjacentHTML('beforeend', comparisonHtml);
}

/**
 * Parse JSON value
 */
function parseJSON(val) {
    if(val === null || val === undefined) {
        return null;
    }
    
    if(typeof val === 'string') {
        try {
            return JSON.parse(val);
        } catch(e) {
            return val;
        }
    }
    
    return val;
}

/**
 * Render group_repeater comparison - only show changed parts
 */
function renderGroupRepeaterComparison(oldRepeater, newRepeater) {
    if(!Array.isArray(oldRepeater) && !Array.isArray(newRepeater)) {
        return '<em style="color: #999;">Invalid group_repeater data</em>';
    }
    
    var oldItems = Array.isArray(oldRepeater) ? oldRepeater : [];
    var newItems = Array.isArray(newRepeater) ? newRepeater : [];
    
    var maxLength = Math.max(oldItems.length, newItems.length);
    var hasChanges = false;
    var html = '<div style="margin-top: 10px;">';
    
    for(var i = 0; i < maxLength; i++) {
        var oldItem = oldItems[i] || null;
        var newItem = newItems[i] || null;
        
        // Compare item
        var itemChanged = false;
        if(oldItem && newItem) {
            // Compare each field in item
            var allFields = new Set();
            if(oldItem) Object.keys(oldItem).forEach(function(k) { allFields.add(k); });
            if(newItem) Object.keys(newItem).forEach(function(k) { allFields.add(k); });
            
            Array.from(allFields).forEach(function(field) {
                var oldFieldVal = oldItem[field];
                var newFieldVal = newItem[field];
                
                // Normalize for comparison
                if(typeof oldFieldVal === 'object') oldFieldVal = JSON.stringify(oldFieldVal);
                if(typeof newFieldVal === 'object') newFieldVal = JSON.stringify(newFieldVal);
                
                if(oldFieldVal !== newFieldVal) {
                    itemChanged = true;
                }
            });
        } else if(oldItem !== newItem) {
            itemChanged = true;
        }
        
        // Only show changed items
        if(itemChanged) {
            hasChanges = true;
            html += '<div style="margin: 10px 0; padding: 10px; background: #fff; border: 1px solid #ffc107; border-radius: 3px;">';
            html += '<strong style="font-size: 12px; color: #856404;">Item ' + (i + 1) + ':</strong><br>';
            
            // Create comparison table for fields in item
            html += '<table style="margin-top: 8px; width: 100%; font-size: 11px; border-collapse: collapse;">';
            html += '<thead>';
            html += '<tr style="background: #f5f5f5;">';
            html += '<th style="padding: 4px; border: 1px solid #ddd; text-align: left; width: 25%;">Field</th>';
            html += '<th style="padding: 4px; border: 1px solid #ddd; text-align: left; width: 37.5%; background: #fff3cd;">Old Value</th>';
            html += '<th style="padding: 4px; border: 1px solid #ddd; text-align: left; width: 37.5%; background: #f8d7da;">New Value</th>';
            html += '</tr>';
            html += '</thead>';
            html += '<tbody>';
            
            // Get all fields
            var allItemFields = new Set();
            if(oldItem) Object.keys(oldItem).forEach(function(k) { allItemFields.add(k); });
            if(newItem) Object.keys(newItem).forEach(function(k) { allItemFields.add(k); });
            
            // Only show changed fields
            Array.from(allItemFields).forEach(function(field) {
                var oldFieldVal = oldItem ? oldItem[field] : null;
                var newFieldVal = newItem ? newItem[field] : null;
                
                // Normalize for comparison
                var oldStr = oldFieldVal === null ? null : (typeof oldFieldVal === 'object' ? JSON.stringify(oldFieldVal) : String(oldFieldVal));
                var newStr = newFieldVal === null ? null : (typeof newFieldVal === 'object' ? JSON.stringify(newFieldVal) : String(newFieldVal));
                
                // Only show if changed
                if(oldStr !== newStr) {
                    html += '<tr>';
                    html += '<td style="padding: 4px; border: 1px solid #ddd; font-weight: bold;">' + escapeHtml(field) + '</td>';
                    html += '<td style="padding: 4px; border: 1px solid #ddd;">' + formatValue(oldFieldVal) + '</td>';
                    html += '<td style="padding: 4px; border: 1px solid #ddd; color: #d32f2f; font-weight: bold;">' + formatValue(newFieldVal) + '</td>';
                    html += '</tr>';
                }
            });
            
            html += '</tbody>';
            html += '</table>';
            html += '</div>';
        }
    }
    
    if(!hasChanges) {
        html += '<em style="color: #999;">No changes in group_repeater</em>';
    }
    
    html += '</div>';
    return html;
}

/**
 * Format value for display
 */
function formatValue(val) {
    if(val === null || val === undefined) {
        return '<em style="color: #999;">(empty)</em>';
    }
    
    if(typeof val === 'object') {
        try {
            return '<pre style="margin: 0; padding: 5px; background: #f9f9f9; border-radius: 3px; font-size: 11px; overflow-y: auto;">' + 
                   escapeHtml(JSON.stringify(val, null, 2)) + '</pre>';
        } catch(e) {
            return escapeHtml(String(val));
        }
    }
    
    if(typeof val === 'string' && val.length > 100) {
        return '<pre style="margin: 0; padding: 5px; background: #f9f9f9; border-radius: 3px; font-size: 11px;  overflow-y: auto;">' + 
               escapeHtml(val) + '</pre>';
    }
    
    return escapeHtml(String(val));
}

/**
 * Format value simply for display (truncated)
 */
function formatValueSimple(val) {
    if(val === null || val === undefined) {
        return '(empty)';
    }
    
    if(typeof val === 'object') {
        try {
            var jsonStr = JSON.stringify(val);
            if(jsonStr.length > 150) {
                return escapeHtml(jsonStr.substring(0, 150) + '...');
            }
            return escapeHtml(jsonStr);
        } catch(e) {
            return '[Object]';
        }
    }
    
    var str = String(val);
    if(str.length > 150) {
        return escapeHtml(str.substring(0, 150) + '...');
    }
    
    return escapeHtml(str);
}

/**
 * Escape HTML
 */
function escapeHtml(text) {
    // Convert to string if not already
    if(text === null || text === undefined) {
        return '';
    }
    
    if(typeof text !== 'string') {
        text = String(text);
    }
    
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Auto-hide restore message after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    var restoreMessage = document.getElementById('restore-message');
    if (restoreMessage && restoreMessage.style.display !== 'none') {
        setTimeout(function() {
            restoreMessage.style.opacity = '0';
            restoreMessage.style.transform = 'translateY(-10px)';
            setTimeout(function() {
                restoreMessage.style.display = 'none';
            }, 300);
        }, 5000);
    }
});
