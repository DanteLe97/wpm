/**
 * Admin JavaScript for Role URL Dashboard
 */

(function($) {
	'use strict';
	
	$(document).ready(function() {
		
		// Bulk actions
		$('#bulk-action-selector').on('change', function() {
			if ($(this).val() !== '-1') {
				$('#rud-bulk-form').on('submit', function(e) {
					if (!confirm(rudAdmin.strings.confirmDelete)) {
						e.preventDefault();
						return false;
					}
				});
			}
		});
		
		// Delete confirmation
		$('.rud-delete-link').on('click', function(e) {
			if (!confirm(rudAdmin.strings.confirmDelete)) {
				e.preventDefault();
				return false;
			}
		});
		
		// Entity type toggle
		$('#entity_type').on('change', function() {
			var entityType = $(this).val();
			if (entityType === 'role') {
				$('#entity-role-row').show();
				$('#entity-user-row').hide();
				$('#entity_user').removeAttr('required');
				$('#entity_role').attr('required', 'required');
			} else {
				$('#entity-role-row').hide();
				$('#entity-user-row').show();
				$('#entity_role').removeAttr('required');
				$('#entity_user').attr('required', 'required');
			}
		});
		
		// Form validation
		$('.rud-edit-form').on('submit', function(e) {
			var url = $('#url').val();
			if (!url) {
				alert('Please enter a URL');
				e.preventDefault();
				return false;
			}
			
			// Basic URL validation
			if (url.indexOf('http://') === 0 || url.indexOf('https://') === 0) {
				alert('Please enter a relative admin URL, not an absolute URL');
				e.preventDefault();
				return false;
			}
		});
		
		// Initialize combobox (single field with dropdown and autocomplete)
		if (typeof rudAdmin !== 'undefined' && rudAdmin.menuItems) {
			var menuItems = rudAdmin.menuItems;
			
			// Prepare autocomplete source data
			var autocompleteSource = menuItems.map(function(item) {
				return {
					label: item.label,
					value: item.url,
					type: item.type || 'common',
					parent: item.parent || ''
				};
			});
			
			// Initialize main URL field
			initializeCombobox($('#url'), menuItems, autocompleteSource);
			
			// Initialize existing additional URL fields
			$('.rud-additional-url-input').each(function() {
				initializeCombobox($(this), menuItems, autocompleteSource);
			});
			
			// Handle additional URL fields added dynamically
			$(document).on('focus', '.rud-additional-url-input', function() {
				if (!$(this).data('combobox-initialized')) {
					initializeCombobox($(this), menuItems, autocompleteSource);
				}
			});
			
			// Add URL field
			$('#rud-add-url').on('click', function() {
				var urlField = $('<div class="rud-url-field" style="margin-bottom: 10px;">' +
					'<div class="rud-combobox-wrapper" style="position: relative; max-width: 450px; display: inline-block;">' +
					'<input type="text" name="additional_urls[]" class="rud-additional-url-input" ' +
					'style="font-size: 16px; padding: 8px 40px 8px 8px; width: 100%;" ' +
					'placeholder="Chọn từ menu hoặc gõ để tìm kiếm/ nhập URL" ' +
					'autocomplete="off">' +
					'<button type="button" class="rud-dropdown-toggle" style="position: absolute; right: 5px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; padding: 5px; font-size: 18px; color: #666;" title="Xem danh sách menu">' +
					'▼' +
					'</button>' +
					'<div class="rud-dropdown-menu" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #8c8f94; border-radius: 4px; max-height: 300px; overflow-y: auto; z-index: 1000; margin-top: 2px; box-shadow: 0 2px 8px rgba(0,0,0,.15);">' +
					'<div class="rud-dropdown-search" style="padding: 8px; border-bottom: 1px solid #f0f0f1;">' +
					'<input type="text" class="rud-menu-search" placeholder="Tìm kiếm..." style="width: 100%; padding: 6px; font-size: 14px; border: 1px solid #8c8f94; border-radius: 3px;">' +
					'</div>' +
					'<div class="rud-dropdown-list"></div>' +
					'</div>' +
					'</div>' +
					'<button type="button" class="button rud-remove-url" style="margin-left: 5px; vertical-align: top; margin-top: 0;">Xóa</button>' +
					'</div>');
				$('#additional-urls-container').append(urlField);
				initializeCombobox(urlField.find('.rud-additional-url-input'), menuItems, autocompleteSource);
			});
			
			// Close dropdown when clicking outside
			$(document).on('click', function(e) {
				if (!$(e.target).closest('.rud-combobox-wrapper').length) {
					$('.rud-dropdown-menu').hide();
				}
			});

		// Auto-add submenu URLs using AJAX to get WordPress menu structure
		$('#rud-auto-submenu').on('click', function() {
			if (typeof rudAdmin === 'undefined') {
				console.log('[rud-auto-submenu] rudAdmin không tồn tại');
				return;
			}
			
			var mainUrl = $('#url').val().trim();
			if (!mainUrl) {
				alert('Hãy chọn Admin URL trước.');
				return;
			}

			console.log('[rud-auto-submenu] Bắt đầu với mainUrl:', mainUrl);

			// Normalize main URL and extract page slug (menu slug)
			var normalizedMain = normalizeUrl(mainUrl);
			console.log('[rud-auto-submenu] normalizedMain:', normalizedMain);
			
			var menuSlug = '';
			if (normalizedMain.indexOf('admin.php?page=') !== -1) {
				var query = normalizedMain.split('?')[1] || '';
				var params = new URLSearchParams(query);
				menuSlug = params.get('page') || '';
			} else {
				// If not admin.php?page=, try to extract from URL
				menuSlug = normalizedMain.split('?')[0].replace(/\.php$/, '');
			}
			
			console.log('[rud-auto-submenu] menuSlug:', menuSlug);
			
			if (!menuSlug) {
				alert('Không xác định được menu slug từ Admin URL.');
				return;
			}

			// Disable button during AJAX
			var $button = $(this);
			var originalText = $button.text();
			$button.prop('disabled', true).text('Đang tải...');

			// Collect existing URLs to avoid duplicates
			var existing = [];
			$('.rud-additional-url-input').each(function() {
				var val = $(this).val().trim();
				if (val) existing.push(val);
			});
			console.log('[rud-auto-submenu] existing URLs:', existing);

			// Call AJAX to get submenus from WordPress
			$.ajax({
				url: rudAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rud_get_submenus',
					nonce: rudAdmin.nonce,
					menu_slug: menuSlug
				},
				success: function(response) {
					$button.prop('disabled', false).text(originalText);
					
					if (!response.success) {
						console.error('[rud-auto-submenu] AJAX error:', response.data);
						alert('Lỗi: ' + (response.data.message || 'Không thể lấy submenu.'));
						return;
					}

					var submenus = response.data.submenus || [];
					console.log('[rud-auto-submenu] Nhận được ' + submenus.length + ' submenu(s):', submenus);

					if (submenus.length === 0) {
						alert('Không tìm thấy submenu cho menu này.');
						return;
					}

					// Get menuItems and autocompleteSource for initializeCombobox
					var menuItems = typeof rudAdmin !== 'undefined' && rudAdmin.menuItems ? rudAdmin.menuItems : [];
					var autocompleteSource = menuItems.map(function(item) {
						return {
							label: item.label,
							value: item.url,
							type: item.type || 'common',
							parent: item.parent || ''
						};
					});

					var added = 0;
					submenus.forEach(function(submenu) {
						// Skip if same as main URL
						if (normalizeUrl(submenu.url) === normalizedMain) {
							console.log('[rud-auto-submenu] Bỏ qua submenu trùng với main:', submenu.url);
							return;
						}

						// Skip if already exists
						if (existing.indexOf(submenu.url) !== -1) {
							console.log('[rud-auto-submenu] Bỏ qua submenu đã tồn tại:', submenu.url);
							return;
						}

						// Add new field
						var urlField = $('<div class="rud-url-field" style="margin-bottom: 10px;">' +
							'<div class="rud-combobox-wrapper" style="position: relative; max-width: 450px; display: inline-block;">' +
							'<input type="text" name="additional_urls[]" class="rud-additional-url-input" ' +
							'style="font-size: 16px; padding: 8px 40px 8px 8px; width: 100%;" ' +
							'placeholder="Chọn từ menu hoặc gõ để tìm kiếm/ nhập URL" ' +
							'autocomplete="off" value="' + submenu.url + '">' +
							'<button type="button" class="rud-dropdown-toggle" style="position: absolute; right: 5px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; padding: 5px; font-size: 18px; color: #666;" title="Xem danh sách menu">▼</button>' +
							'<div class="rud-dropdown-menu" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #8c8f94; border-radius: 4px; max-height: 300px; overflow-y: auto; z-index: 1000; margin-top: 2px; box-shadow: 0 2px 8px rgba(0,0,0,.15);">' +
							'<div class="rud-dropdown-search" style="padding: 8px; border-bottom: 1px solid #f0f0f1;">' +
							'<input type="text" class="rud-menu-search" placeholder="Tìm kiếm..." style="width: 100%; padding: 6px; font-size: 14px; border: 1px solid #8c8f94; border-radius: 3px;">' +
							'</div>' +
							'<div class="rud-dropdown-list"></div>' +
							'</div>' +
							'</div>' +
							'<button type="button" class="button rud-remove-url" style="margin-left: 5px; vertical-align: top; margin-top: 0;">Xóa</button>' +
							'</div>');
						$('#additional-urls-container').append(urlField);
						
						// Initialize combobox if menuItems available
						if (menuItems.length > 0) {
							initializeCombobox(urlField.find('.rud-additional-url-input'), menuItems, autocompleteSource);
						}
						
						existing.push(submenu.url);
						added++;
						console.log('[rud-auto-submenu] Đã thêm submenu:', submenu.label, submenu.url);
					});

					if (added === 0) {
						alert('Tất cả submenu đã tồn tại hoặc trùng với Admin URL chính.');
					} else {
						console.log('[rud-auto-submenu] Đã thêm ' + added + ' submenu(s)');
					}
				},
				error: function(xhr, status, error) {
					$button.prop('disabled', false).text(originalText);
					console.error('[rud-auto-submenu] AJAX request failed:', status, error);
					alert('Lỗi khi tải submenu: ' + error);
				}
			});
		});
		}
		
		// Function to initialize combobox (input + dropdown + autocomplete)
		function initializeCombobox($input, menuItems, autocompleteSource) {
			if (!$input.length || !menuItems) {
				return;
			}
			
			if ($input.data('combobox-initialized')) {
				return;
			}
			
			var $wrapper = $input.closest('.rud-combobox-wrapper');
			var $dropdown = $wrapper.find('.rud-dropdown-menu');
			var $dropdownList = $wrapper.find('.rud-dropdown-list');
			var $dropdownToggle = $wrapper.find('.rud-dropdown-toggle');
			var $searchInput = $wrapper.find('.rud-menu-search');
			
			// Build dropdown list
			function buildDropdownList(searchTerm) {
				searchTerm = (searchTerm || '').toLowerCase();
				var filteredItems = menuItems;
				
				// Filter if search term exists
				if (searchTerm) {
					filteredItems = menuItems.filter(function(item) {
						return item.label.toLowerCase().indexOf(searchTerm) !== -1 ||
						       item.url.toLowerCase().indexOf(searchTerm) !== -1;
					});
				}
				
				// Group items
				var mainItems = [];
				var submenuItems = [];
				var commonItems = [];
				
				filteredItems.forEach(function(item) {
					if (item.type === 'main') {
						mainItems.push(item);
					} else if (item.type === 'submenu') {
						submenuItems.push(item);
					} else {
						commonItems.push(item);
					}
				});
				
				$dropdownList.empty();
				
				// Add main menu items
				if (mainItems.length > 0) {
					var $group = $('<div class="rud-dropdown-group" style="padding: 5px 0;">' +
						'<div style="padding: 5px 10px; font-weight: bold; color: #666; font-size: 12px; text-transform: uppercase;">Main Menu</div>' +
						'</div>');
					mainItems.forEach(function(item) {
						var $item = $('<div class="rud-dropdown-item" data-url="' + item.url + '" style="padding: 8px 15px; cursor: pointer; transition: background 0.15s;">' +
							'<div style="font-size: 14px; color: #2c3338;">' + item.label + '</div>' +
							'<div style="font-size: 12px; color: #666; margin-top: 2px;">' + item.url + '</div>' +
							'</div>');
						$item.on('click', function() {
							$input.val(item.url);
							$dropdown.hide();
						});
						$item.on('mouseenter', function() {
							$(this).css('background', '#f0f6fc');
						});
						$item.on('mouseleave', function() {
							$(this).css('background', '');
						});
						$group.append($item);
					});
					$dropdownList.append($group);
				}
				
				// Add submenu items
				if (submenuItems.length > 0) {
					var $group = $('<div class="rud-dropdown-group" style="padding: 5px 0;">' +
						'<div style="padding: 5px 10px; font-weight: bold; color: #666; font-size: 12px; text-transform: uppercase;">Submenu</div>' +
						'</div>');
					submenuItems.forEach(function(item) {
						var $item = $('<div class="rud-dropdown-item" data-url="' + item.url + '" style="padding: 8px 15px; cursor: pointer; transition: background 0.15s;">' +
							'<div style="font-size: 14px; color: #2c3338;">' + item.label + '</div>' +
							'<div style="font-size: 12px; color: #666; margin-top: 2px;">' + item.url + '</div>' +
							'</div>');
						$item.on('click', function() {
							$input.val(item.url);
							$dropdown.hide();
						});
						$item.on('mouseenter', function() {
							$(this).css('background', '#f0f6fc');
						});
						$item.on('mouseleave', function() {
							$(this).css('background', '');
						});
						$group.append($item);
					});
					$dropdownList.append($group);
				}
				
				// Add common pages
				if (commonItems.length > 0) {
					var $group = $('<div class="rud-dropdown-group" style="padding: 5px 0;">' +
						'<div style="padding: 5px 10px; font-weight: bold; color: #666; font-size: 12px; text-transform: uppercase;">Common Pages</div>' +
						'</div>');
					commonItems.forEach(function(item) {
						var $item = $('<div class="rud-dropdown-item" data-url="' + item.url + '" style="padding: 8px 15px; cursor: pointer; transition: background 0.15s;">' +
							'<div style="font-size: 14px; color: #2c3338;">' + item.label + '</div>' +
							'<div style="font-size: 12px; color: #666; margin-top: 2px;">' + item.url + '</div>' +
							'</div>');
						$item.on('click', function() {
							$input.val(item.url);
							$dropdown.hide();
						});
						$item.on('mouseenter', function() {
							$(this).css('background', '#f0f6fc');
						});
						$item.on('mouseleave', function() {
							$(this).css('background', '');
						});
						$group.append($item);
					});
					$dropdownList.append($group);
				}
				
				// Show message if no results
				if (filteredItems.length === 0) {
					$dropdownList.html('<div style="padding: 20px; text-align: center; color: #666;">Không tìm thấy kết quả</div>');
				}
			}
			
			// Toggle dropdown
			$dropdownToggle.on('click', function(e) {
				e.stopPropagation();
				if ($dropdown.is(':visible')) {
					$dropdown.hide();
				} else {
					buildDropdownList('');
					$dropdown.show();
					$searchInput.focus();
				}
			});
			
			// Search in dropdown
			$searchInput.on('input', function() {
				buildDropdownList($(this).val());
			});
			
			// Initialize autocomplete for input
			$input.autocomplete({
				source: function(request, response) {
					var term = request.term.toLowerCase();
					var matches = [];
					
					autocompleteSource.forEach(function(item) {
						var labelMatch = item.label.toLowerCase().indexOf(term) !== -1;
						var urlMatch = item.value.toLowerCase().indexOf(term) !== -1;
						
						if (labelMatch || urlMatch) {
							var score = 0;
							if (item.label.toLowerCase().indexOf(term) === 0) {
								score += 100;
							}
							if (labelMatch) {
								score += 50;
							}
							if (urlMatch) {
								score += 25;
							}
							
							matches.push({
								label: item.label,
								value: item.value,
								score: score,
								type: item.type
							});
						}
					});
					
					matches.sort(function(a, b) {
						return b.score - a.score;
					});
					
					matches = matches.slice(0, 20);
					
					var formatted = matches.map(function(item) {
						var typeLabel = '';
						if (item.type === 'main') {
							typeLabel = ' [Main]';
						} else if (item.type === 'submenu') {
							typeLabel = ' [Submenu]';
						}
						return {
							label: item.label + typeLabel,
							value: item.value
						};
					});
					
					response(formatted);
				},
				minLength: 1,
				delay: 100,
				select: function(event, ui) {
					$(this).val(ui.item.value);
					$dropdown.hide();
					return false;
				},
				focus: function(event, ui) {
					$(this).val(ui.item.value);
					return false;
				}
			}).autocomplete('instance')._renderItem = function(ul, item) {
				var $li = $('<li>')
					.append($('<div>').html(item.label))
					.appendTo(ul);
				return $li;
			};
			
			// Hide dropdown when input gets focus (to show autocomplete instead)
			$input.on('focus', function() {
				$dropdown.hide();
			});
			
			$input.data('combobox-initialized', true);
		}

	// Helper (kept for reference but unused now)
	function getUrlPrefix(url) {
		if (!url) return '';
		if (url.indexOf('admin.php?page=') !== -1) {
			var query = url.split('?')[1] || '';
			var params = new URLSearchParams(query);
			var page = params.get('page') || '';
			if (!page) return url;
			return 'admin.php?page=' + page;
		}
		return url.split('?')[0];
	}

	// Normalize URL similar to PHP normalize_url (simplified)
	function normalizeUrl(url) {
		if (!url) return '';
		// remove scheme and host
		url = url.replace(/^https?:\/\/[^/]+/i, '');
		// remove leading slash
		if (url.charAt(0) === '/') url = url.substring(1);
		// remove wp-admin/ prefix
		if (url.indexOf('wp-admin/') === 0) url = url.replace(/^wp-admin\//, '');
		return url;
	}
	
	// Default Links: Toggle enable/disable (delegated for dynamic content)
	$(document).on('change', '.rud-default-link-toggle', function() {
		var $toggle = $(this);
		var linkId = $toggle.data('link-id');
		var enabled = $toggle.is(':checked');
		
		// Disable toggle during AJAX
		$toggle.prop('disabled', true);
		
		$.ajax({
			url: rudAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'rud_toggle_default_link',
				nonce: rudAdmin.nonce,
				link_id: linkId,
				enabled: enabled ? 1 : 0
			},
			success: function(response) {
				$toggle.prop('disabled', false);
				if (response.success) {
					// Update toggle slider style
					var $slider = $toggle.siblings('.rud-toggle-slider');
					if (enabled) {
						$slider.css('background-color', '#ff5c02');
						$slider.css('transform', 'translateX(20px)');
					} else {
						$slider.css('background-color', '#ccc');
						$slider.css('transform', 'translateX(0)');
					}
					// Reload page to update mappings
					location.reload();
				} else {
					// Revert toggle
					$toggle.prop('checked', !enabled);
					alert('Error: ' + (response.data.message || 'Unknown error'));
				}
			},
			error: function() {
				$toggle.prop('disabled', false);
				$toggle.prop('checked', !enabled);
				alert('Error: Failed to update link status');
			}
		});
	});
	
	// Default Links: Edit button (delegated for dynamic content)
	$(document).on('click', '.rud-edit-default-link', function() {
		var $btn = $(this);
		var linkId = $btn.data('link-id');
		var label = $btn.data('label');
		var icon = $btn.data('icon');
		var url = $btn.data('url');
		var description = $btn.data('description') || '';
		var priority = $btn.data('priority') || 10;
		
		// Create modal dialog
		var $modal = $('<div class="rud-edit-default-modal" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100000; display: flex; align-items: center; justify-content: center;">' +
			'<div style="background: #fff; padding: 30px; border-radius: 4px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">' +
			'<h2 style="margin-top: 0;">Edit Default Link</h2>' +
			'<table class="form-table" style="width: 100%;">' +
			'<tr><th style="width: 120px;"><label>Label:</label></th>' +
			'<td><input type="text" class="rud-edit-label" value="' + label + '" style="width: 100%; padding: 8px;"></td></tr>' +
			'<tr><th><label>Icon:</label></th>' +
			'<td><input type="text" class="rud-edit-icon" value="' + icon + '" style="width: 100px; padding: 8px; font-size: 20px;"></td></tr>' +
			'<tr><th><label>URL:</label></th>' +
			'<td><input type="text" class="rud-edit-url" value="' + url + '" style="width: 100%; padding: 8px;"></td></tr>' +
			'<tr><th><label>Description:</label></th>' +
			'<td><textarea class="rud-edit-description" rows="3" style="width: 100%; padding: 8px;">' + description + '</textarea></td></tr>' +
			'<tr><th><label>Priority:</label></th>' +
			'<td><input type="number" class="rud-edit-priority" value="' + priority + '" min="0" max="100" style="width: 100px; padding: 8px;"></td></tr>' +
			'</table>' +
			'<p style="margin-top: 20px;">' +
			'<button type="button" class="button button-primary rud-save-default-link" data-link-id="' + linkId + '">Save</button> ' +
			'<button type="button" class="button rud-cancel-edit-default">Cancel</button>' +
			'</p>' +
			'</div>' +
			'</div>');
		
		$('body').append($modal);
		
		// Close on cancel or outside click
		$modal.find('.rud-cancel-edit-default, .rud-edit-default-modal').on('click', function(e) {
			if (e.target === this || $(e.target).hasClass('rud-cancel-edit-default')) {
				$modal.remove();
			}
		});
		
		// Save button
		$modal.find('.rud-save-default-link').on('click', function() {
			var $saveBtn = $(this);
			var linkId = $saveBtn.data('link-id');
			
			var data = {
				action: 'rud_update_default_link',
				nonce: rudAdmin.nonce,
				link_id: linkId,
				label: $modal.find('.rud-edit-label').val(),
				icon: $modal.find('.rud-edit-icon').val(),
				url: $modal.find('.rud-edit-url').val(),
				description: $modal.find('.rud-edit-description').val(),
				priority: parseInt($modal.find('.rud-edit-priority').val()) || 10
			};
			
			$saveBtn.prop('disabled', true).text('Saving...');
			
			$.ajax({
				url: rudAdmin.ajaxUrl,
				type: 'POST',
				data: data,
				success: function(response) {
					if (response.success) {
						$modal.remove();
						// Reload page to update mappings from database
						location.reload();
					} else {
						alert('Error: ' + (response.data.message || 'Unknown error'));
						$saveBtn.prop('disabled', false).text('Save');
					}
				},
				error: function() {
					alert('Error: Failed to update link');
					$saveBtn.prop('disabled', false).text('Save');
				}
			});
		});
	});
		
	});
	
})(jQuery);

