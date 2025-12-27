jQuery(document).ready(function($) {
  // Category change - load styles
  $('#category').on('change', function() {
    const category = $(this).val();
    const $styleRow = $('#style-row');
    const $styleSelect = $('#style');
    const $categorySelect = $(this);
    
    if (!category) {
      $styleRow.hide();
      $styleSelect.html('<option value="">-- Select Style --</option>');
      return;
    }
    
    // Show loading
    $styleSelect.html('<option value="">Loading...</option>').prop('disabled', true);
    $categorySelect.prop('disabled', true);
    
    // Add loading spinner to category select
    if (!$categorySelect.next('.mac-loading-spinner').length) {
      $categorySelect.after('<span class="mac-loading-spinner"></span>');
    }
    
    $.ajax({
      url: macSeasonalEffects.ajaxurl,
      type: 'POST',
      data: {
        action: 'mac_seasonal_effects_get_styles',
        nonce: macSeasonalEffects.nonce,
        category: category
      },
      success: function(response) {
        // Remove loading spinner
        $categorySelect.next('.mac-loading-spinner').remove();
        $categorySelect.prop('disabled', false);
        $styleSelect.prop('disabled', false);
        
        if (response && response.success) {
          $styleSelect.html('<option value="">-- Select Style --</option>');
          if (response.data && response.data.styles) {
            $.each(response.data.styles, function(key, style) {
              $styleSelect.append($('<option>', {
                value: key,
                text: style.name || key
              }));
            });
            $styleRow.show();
          } else {
            $styleSelect.html('<option value="">No styles found</option>');
          }
        } else {
          const errorMsg = (response && response.data && response.data.message) ? response.data.message : 'Error loading styles';
          $styleSelect.html('<option value="">' + errorMsg + '</option>');
          console.error('MAC Seasonal Effects AJAX Error:', response);
        }
      },
      error: function(xhr, status, error) {
        // Remove loading spinner
        $categorySelect.next('.mac-loading-spinner').remove();
        $categorySelect.prop('disabled', false);
        $styleSelect.prop('disabled', false);
        
        $styleSelect.html('<option value="">Error loading styles</option>');
        console.error('MAC Seasonal Effects AJAX Request Failed:', {
          status: status,
          error: error,
          response: xhr.responseText
        });
      }
    });
  });
  
  // Style change - load customization section via AJAX
  $('#style').on('change', function() {
    const category = $('#category').val();
    const style = $(this).val();
    const $customizationSection = $('.mac-customization-section');
    const $styleSelect = $(this);
    
    if (!category || !style) {
      $customizationSection.hide().html('');
      return;
    }
    
    // Show loading overlay
    $customizationSection.addClass('mac-loading-overlay mac-ajax-loading').show();
    $styleSelect.prop('disabled', true);
    if (!$styleSelect.next('.mac-loading-spinner').length) {
      $styleSelect.after('<span class="mac-loading-spinner"></span>');
    }
    
    // Load customization section HTML via AJAX
    $.ajax({
      url: macSeasonalEffects.ajaxurl,
      type: 'POST',
      data: {
        action: 'mac_seasonal_effects_get_customization_html',
        nonce: macSeasonalEffects.nonce,
        category: category,
        style: style
      },
      success: function(response) {
        // Remove loading
        $customizationSection.removeClass('mac-loading-overlay mac-ajax-loading');
        $styleSelect.next('.mac-loading-spinner').remove();
        $styleSelect.prop('disabled', false);
        
        if (response && response.success) {
          if (response.data && response.data.html) {
            // Wrap HTML with h2 and container
            const html = '<h2>Customization</h2>' + response.data.html;
            $customizationSection.html(html).show();
            
            // Re-initialize color pickers
            if ($.fn.wpColorPicker) {
              $customizationSection.find('.mac-color-picker').wpColorPicker({
                change: function(event, ui) {
                  // Color changed
                }
              });
            }
            
            // Image uploader and Use Default buttons are handled by delegate events
            // No need to re-bind - they work with dynamically loaded content
          } else {
            $customizationSection.hide().html('');
          }
        } else {
          console.error('MAC Seasonal Effects AJAX Error:', response);
          $customizationSection.hide().html('');
        }
      },
      error: function(xhr, status, error) {
        // Remove loading
        $customizationSection.removeClass('mac-loading-overlay mac-ajax-loading');
        $styleSelect.next('.mac-loading-spinner').remove();
        $styleSelect.prop('disabled', false);
        
        console.error('MAC Seasonal Effects AJAX Request Failed:', {
          status: status,
          error: error,
          response: xhr.responseText
        });
        $customizationSection.hide().html('');
      }
    });
  });
  
  // Initialize color pickers
  if ($.fn.wpColorPicker) {
    $('.mac-color-picker').wpColorPicker({
      change: function(event, ui) {
        // Color changed
      }
    });
  }
  
  // Image uploader
  $(document).on('click', '.mac-upload-image', function(e) {
    e.preventDefault();
    const $button = $(this);
    const $input = $('#' + $button.data('target'));
    const $preview = $input.siblings('.image-preview');
    
    const frame = wp.media({
      title: 'Select Image',
      button: {
        text: 'Use this image'
      },
      multiple: false
    });
    
    frame.on('select', function() {
      const attachment = frame.state().get('selection').first().toJSON();
      $input.val(attachment.url);
      
      // Update preview
      if ($preview.length) {
        $preview.attr('src', attachment.url);
      } else {
        $input.after('<img src="' + attachment.url + '" style="max-width: 100px; height: auto; margin-left: 10px; vertical-align: middle;" class="image-preview">');
      }
    });
    
    frame.open();
  });
  
  // Use Default Image button - Clear input to use default from config
  $(document).on('click', '.mac-use-default-image', function(e) {
    e.preventDefault();
    const $button = $(this);
    const $input = $('#' + $button.data('target'));
    const defaultUrl = $button.data('default');
    const $preview = $input.siblings('.image-preview');
    const $row = $input.closest('tr');
    
    // Clear input - backend will use default from config
    $input.val('');
    
    // Update preview to show default image (so user knows what will be used)
    if (defaultUrl) {
      if ($preview.length) {
        $preview.attr('src', defaultUrl);
      } else {
        $input.after('<img src="' + defaultUrl + '" style="max-width: 100px; height: auto; margin-left: 10px; vertical-align: middle;" class="image-preview">');
      }
    } else {
      $preview.remove();
    }
    
    // Show "Using default" indicator
    let $indicator = $row.find('.using-default-indicator');
    if (!$indicator.length) {
      $indicator = $('<span class="description using-default-indicator" style="color: #666; margin-left: 5px;">(Using default)</span>');
      $input.after($indicator);
    }
  });
  
  // Preview button
  $('#preview-btn').on('click', function(e) {
    e.preventDefault();
    const category = $('#category').val();
    const style = $('#style').val();
    
    if (!category || !style) {
      alert('Please select category and style first.');
      return;
    }
    
    const previewUrl = macSeasonalEffects.previewUrl + category + '-' + style;
    window.open(previewUrl, '_blank', 'width=1200,height=800');
  });
  
  // Update preview when inputs change
  $('input[type="url"], input[type="text"], input[type="number"]').on('change', function() {
    // Could add live preview update here if needed
  });
});

