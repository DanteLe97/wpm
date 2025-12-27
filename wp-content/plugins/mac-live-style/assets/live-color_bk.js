jQuery(document).ready(function($) {
  const panel = $('#elementor-color-control-panel');
  const toggleBtn = $('.toggle-button');
  const closeBtn = $('.close-panel');

  // Định nghĩa các preset
  const presets = {
    modern: {
      colors: {
        primary: '#F26212',
        secondary: '#FBAE85',
        text: '#333333',
        accent: '#FBAE85'
      },
      fonts: {
        primary: 'Montserrat',
        secondary: 'Open Sans'
      }
    },
    minimal: {
      colors: {
        primary: '#333333',
        secondary: '#666666',
        text: '#333333',
        accent: '#666666'
      },
      fonts: {
        primary: 'Helvetica',
        secondary: 'Arial'
      }
    },
    nature: {
      colors: {
        primary: '#28a745',
        secondary: '#20c997',
        text: '#333333',
        accent: '#20c997'
      },
      fonts: {
        primary: 'Poppins',
        secondary: 'Open Sans'
      }
    },
    ocean: {
      colors: {
        primary: '#007bff',
        secondary: '#17a2b8',
        text: '#333333',
        accent: '#17a2b8'
      },
      fonts: {
        primary: 'Roboto',
        secondary: 'Lato'
      }
    }
  };

  // Hàm lấy tất cả màu hiện tại từ các input (tối ưu hóa)
  function getCurrentColors() {
    const colors = {};
    $('.color-control input[type="color"]').each(function() {
      const input = $(this);
      const id = input.attr('id');
      if (!id) return;
      
      const colorName = id.replace('-color', '');
      const colorValue = input.val();
      if (colorValue) {
        colors[colorName] = colorValue;
      }
    });
    return colors;
  }

  // Hàm lấy tất cả font hiện tại từ các select (tối ưu hóa)
  function getCurrentFonts() {
    const fonts = {};
    $('.font-control select.font-select').each(function() {
      const select = $(this);
      const id = select.attr('id');
      if (!id) return;
      
      const fontName = id.replace('-font', '');
      const fontValue = select.val();
      if (fontValue) {
        fonts[fontName] = fontValue;
      }
    });
    return fonts;
  }

  // Hàm lấy tất cả CSS variables từ database
  function getCSSVariables() {
    const variables = {};
    $('.color-control input[type="color"]').each(function() {
      const input = $(this);
      const id = input.attr('id');
      if (!id) return;
      
      const colorName = id.replace('-color', '');
      const colorValue = input.val();
      
      if (colorValue) {
        const cssVar = `--e-global-color-${colorName}`;
        variables[cssVar] = colorValue;
      }
    });
    return variables;
  }

  // Hàm lấy tất cả CSS font variables từ database
  function getCSSFontVariables() {
    const variables = {};
    $('.font-control select.font-select').each(function() {
      const select = $(this);
      const id = select.attr('id');
      if (!id) return;
      
      const fontName = id.replace('-font', '');
      const fontValue = select.val();
      
      if (fontValue) {
        const cssVar = `--e-global-typography-${fontName}-font-family`;
        variables[cssVar] = `"${fontValue}", sans-serif`;
      }
    });
    return variables;
  }

  // Tối ưu hóa helper function để tạo CSS rules
  function createCSSRules(variables) {
    if (!variables || Object.keys(variables).length === 0) return '';
    
    return Object.entries(variables)
      .map(([key, value]) => `${key}: ${value} !important;`)
      .join('\n        ');
  }

  // Hàm cập nhật CSS cho website được tối ưu hóa
  function updateWebsiteStyles(colors, fonts) {
    // Tạo hoặc lấy style element
    let styleElement = $('#elementor-live-color-dynamic-styles');
    if (styleElement.length === 0) {
      styleElement = $('<style id="elementor-live-color-dynamic-styles"></style>');
      $('head').append(styleElement);
    }
    
    // Lấy CSS variables từ database
    const colorVariables = getCSSVariables();
    const fontVariables = getCSSFontVariables();
    
    // Tạo CSS rules hiệu quả hơn
    const colorRules = createCSSRules(colorVariables);
    const fontRules = createCSSRules(fontVariables);
    
    // Tạo CSS với cấu trúc rõ ràng và hiệu quả
    const cssRules = [];
    
    // Thêm color variables vào :root
    if (colorRules) {
      cssRules.push(`:root {\n        ${colorRules}\n    }`);
    }
    
    // Thêm font variables vào :root  
    if (fontRules) {
      cssRules.push(`:root {\n        ${fontRules}\n    }`);
    }
    
    // Áp dụng font variables cho các elements
    if (fontRules) {
      cssRules.push(`
    [data-elementor-type="wp-page"] p,
    [data-elementor-type="wp-page"] span,
    [data-elementor-type="wp-page"] div,
    [data-elementor-type="wp-page"] h1,
    [data-elementor-type="wp-page"] h2,
    [data-elementor-type="wp-page"] h3,
    [data-elementor-type="wp-page"] h4,
    [data-elementor-type="wp-page"] h5,
    [data-elementor-type="wp-page"] h6 {
        font-family: var(--e-global-typography-primary-font-family, inherit);
    }`);
    }
    
    // Cập nhật style element
    styleElement.html(cssRules.join('\n'));
  }

  // Xử lý chuyển đổi tab
  $('.tab-button').on('click', function() {
    const tabId = $(this).data('tab');
    
    // Cập nhật trạng thái active của tab
    $('.tab-button').removeClass('active');
    $(this).addClass('active');
    
    // Hiển thị nội dung tab tương ứng
    $('.tab-pane').removeClass('active');
    $(`#${tabId}-tab`).addClass('active');
  });

  // Tối ưu hóa xử lý chọn preset
  $('.preset-item').on('click', function() {
    const presetId = $(this).data('preset');
    const preset = presets[presetId];
    
    if (!preset) return;
    
    // Cập nhật màu sắc với cách hiệu quả hơn
    Object.entries(preset.colors).forEach(([colorName, colorValue]) => {
      const colorInput = $(`#${colorName}-color`);
      if (colorInput.length) {
        colorInput.val(colorValue);
        // Cập nhật preview luôn
        colorInput.siblings('.color-preview').css('background-color', colorValue);
      }
    });
    
    // Cập nhật fonts với cách hiệu quả hơn
    Object.entries(preset.fonts).forEach(([fontName, fontValue]) => {
      const fontSelect = $(`#${fontName}-font`);
      if (fontSelect.length) {
        fontSelect.val(fontValue);
      }
    });
    
    // Cập nhật website một lần duy nhất
    const currentColors = getCurrentColors();
    const currentFonts = getCurrentFonts();
    updateWebsiteStyles(currentColors, currentFonts);
  });

  toggleBtn.on('click', function() {
    panel.toggleClass('active');
  });

  closeBtn.on('click', function() {
    panel.removeClass('active');
  });

  // Cập nhật preview và website khi thay đổi màu
  $('.color-control input[type="color"]').on('input', function() {
    const preview = $(this).siblings('.color-preview');
    const colorValue = $(this).val();
    preview.css('background-color', colorValue);
    
    // Log màu sắc riêng lẻ khi thay đổi
    console.log(`Đã thay đổi ${$(this).prev('label').text()}:`, colorValue);
    
    // Lấy tất cả màu hiện tại
    const colors = getCurrentColors();

    // Lấy font hiện tại
    const fonts = getCurrentFonts();
    
    // Cập nhật website
    updateWebsiteStyles(colors, fonts);

    // Gọi các addon
    if (elementorLiveColor.addons) {
      Object.keys(elementorLiveColor.addons).forEach(addonName => {
        if (typeof window[`update${addonName}Colors`] === 'function') {
          window[`update${addonName}Colors`](colors);
        }
      });
    }
  });

  // Cập nhật website khi thay đổi font
  $('.font-select').on('change', function() {
    const colors = getCurrentColors();
    const fonts = getCurrentFonts();

    console.log('Font đã thay đổi:', fonts);
    updateWebsiteStyles(colors, fonts);
  });

  // Khởi tạo preview ban đầu
  $('.color-control input[type="color"]').each(function() {
    const preview = $(this).siblings('.color-preview');
    preview.css('background-color', $(this).val());
  });

  // Khởi tạo màu ban đầu cho website
  const initialColors = getCurrentColors();

  // Khởi tạo font ban đầu
  const initialFonts = getCurrentFonts();

  updateWebsiteStyles(initialColors, initialFonts);

  // Khởi tạo các addon
  if (elementorLiveColor.addons) {
    Object.keys(elementorLiveColor.addons).forEach(addonName => {
      if (typeof window[`init${addonName}`] === 'function') {
        window[`init${addonName}`](initialColors);
      }
    });
  }

  // Xử lý export settings
  jQuery('#export-settings').on('click', function() {
    const pageId = getCurrentPageId();
    
    // Lấy giá trị fonts từ select boxes
    const currentFonts = getCurrentFonts();
    const fontValues = Object.values(currentFonts);

    console.log("currentFonts", currentFonts);
    console.log("fontValues", fontValues);

    jQuery.ajax({
      url: elementorLiveColor.ajaxurl,
      type: 'POST',
      data: {
        action: 'export_page_settings',
        nonce: elementorLiveColor.nonce,
        page_id: pageId,
        fonts: fontValues
      },
      success: function(response) {
        if (response.success) {
          const data = response.data;
          const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
          const url = window.URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = `page-settings-${data.page_id}.json`;
          document.body.appendChild(a);
          a.click();
          window.URL.revokeObjectURL(url);
          document.body.removeChild(a);
        } else {
          alert('Có lỗi xảy ra khi export settings');
        }
      },
      error: function() {
        alert('Có lỗi xảy ra khi export settings');
      }
    });
  });

  // Hàm lấy ID của trang hiện tại
  function getCurrentPageId() {
    // Kiểm tra nếu đang ở trang đơn
    if (document.body.classList.contains('single')) {
      const postId = document.body.className.match(/postid-(\d+)/);
      if (postId && postId[1]) {
        return postId[1];
      }
    }
    
    // Kiểm tra nếu đang ở trang Elementor
    const elementorData = window.elementorFrontendConfig;
    if (elementorData && elementorData.post && elementorData.post.id) {
      return elementorData.post.id;
    }
    
    // Nếu không tìm thấy, trả về 0
    return 0;
  }
});