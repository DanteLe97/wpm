jQuery(document).ready(function($) {
  const panel = $('#elementor-color-control-panel');
  const toggleBtn = $('.toggle-button');
  const closeBtn = $('.close-panel');

  /*---------------------------- Initialization ----------------------------*/ 
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

  /*---------------------------- URL Hash Color Sync ----------------------------*/
  
  // Kiểm tra xem đang ở trong iframe không
  function isInIframe() {
    try {
      return window.self !== window.top;
    } catch (e) {
      return true;
    }
  }
  
  // ==================== Color Hash Encoding ====================
  // Định nghĩa thứ tự cố định cho các color keys
  const COLOR_KEY_ORDER = ['primary', 'secondary', 'text', 'accent', '041be46', '54f3520', '2c30e4f', '68c5c02', 'cf3521e', '575bd41'];
  
  // Base62 characters
  const BASE62_CHARS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
  
  // Encode hex string to Base62
  function hexToBase62(hex) {
    if (!hex) return '';
    // Convert hex to BigInt
    let num = BigInt('0x' + hex);
    if (num === 0n) return '0';
    
    let result = '';
    const base = BigInt(62);
    while (num > 0n) {
      result = BASE62_CHARS[Number(num % base)] + result;
      num = num / base;
    }
    return result;
  }
  
  // Decode Base62 to hex string
  function base62ToHex(str, targetLength) {
    if (!str) return '';
    let num = 0n;
    const base = BigInt(62);
    
    for (let i = 0; i < str.length; i++) {
      const char = str[i];
      const value = BASE62_CHARS.indexOf(char);
      if (value === -1) return '';
      num = num * base + BigInt(value);
    }
    
    let hex = num.toString(16).toUpperCase();
    // Pad với 0 để đạt độ dài target
    while (hex.length < targetLength) {
      hex = '0' + hex;
    }
    return hex;
  }
  
  // Encode colors object thành chuỗi ngắn
  function encodeColorsToHash(colors) {
    if (!colors || Object.keys(colors).length === 0) return '';
    
    // Lấy các màu theo thứ tự cố định
    const hexParts = [];
    const keysUsed = [];
    
    COLOR_KEY_ORDER.forEach((key, index) => {
      if (colors[key]) {
        hexParts.push(colors[key].replace('#', '').toUpperCase());
        keysUsed.push(index);
      }
    });
    
    // Thêm các key không trong danh sách cố định
    Object.keys(colors).forEach(key => {
      if (!COLOR_KEY_ORDER.includes(key)) {
        hexParts.push(colors[key].replace('#', '').toUpperCase());
        keysUsed.push(key); // Giữ nguyên key string cho các key không chuẩn
      }
    });
    
    if (hexParts.length === 0) return '';
    
    // Nối tất cả hex values
    const fullHex = hexParts.join('');
    
    // Encode sang Base62
    const encoded = hexToBase62(fullHex);
    
    // Tạo key mask (dùng bit): 4 key đầu = 4 bits
    const keyMask = keysUsed.filter(k => typeof k === 'number').reduce((mask, idx) => mask | (1 << idx), 0);
    const keyMaskBase62 = keyMask.toString(36); // base36 cho ngắn gọn
    
    // Format: keyMask.encodedColors
    return `${keyMaskBase62}.${encoded}`;
  }
  
  // Decode chuỗi hash thành colors object
  function decodeHashToColors(hashStr) {
    if (!hashStr || !hashStr.includes('.')) {
      return null;
    }
    
    const parts = hashStr.split('.');
    if (parts.length < 2) return null;
    
    const keyMaskBase36 = parts[0];
    const encoded = parts[1];
    
    // Decode key mask
    const keyMask = parseInt(keyMaskBase36, 36);
    
    // Tìm các keys được sử dụng
    const usedKeys = [];
    for (let i = 0; i < COLOR_KEY_ORDER.length; i++) {
      if (keyMask & (1 << i)) {
        usedKeys.push(COLOR_KEY_ORDER[i]);
      }
    }
    
    if (usedKeys.length === 0) return null;
    
    // Decode Base62 về hex
    const targetLength = usedKeys.length * 6; // mỗi màu 6 ký tự hex
    const fullHex = base62ToHex(encoded, targetLength);
    
    if (!fullHex || fullHex.length < targetLength) {
      return null;
    }
    
    // Split hex thành các màu riêng
    const colors = {};
    for (let i = 0; i < usedKeys.length; i++) {
      const hex = fullHex.substring(i * 6, (i + 1) * 6);
      if (hex.length === 6) {
        colors[usedKeys[i]] = '#' + hex;
      }
    }
    
    return colors;
  }
  // ==================== END Color Hash Encoding ====================
  
  // Lấy tất cả màu hiện tại dưới dạng object đơn giản {key: hexValue}
  function getAllColorsSimple() {
    const colors = {};
    
    $('.color-control .clr-field > input.coloris[data-color]').each(function() {
      const $input = $(this);
      const colorKey = $input.data('color');
      const $field = $input.closest('.clr-field');
      
      let colorValue = '';
      const rgb = $field.css('color');
      
      if (rgb && /^rgba?/i.test(rgb)) {
        const m = rgb.match(/rgba?\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i);
        if (m) {
          const toHex = function(n) { return parseInt(n, 10).toString(16).padStart(2, '0'); };
          colorValue = toHex(m[1]) + toHex(m[2]) + toHex(m[3]);
        }
      }
      
      if (!colorValue) {
        colorValue = $input.val().replace('#', '');
      }
      
      if (colorKey && colorValue) {
        colors[colorKey] = colorValue.toUpperCase();
      }
    });
    
    return colors;
  }
  
  // Cập nhật URL hash khi màu thay đổi
  function updateURLHash() {
    const colors = getAllColorsSimple();
    
    if (Object.keys(colors).length === 0) return;
    
    // Encode colors thành chuỗi ngắn
    const encoded = encodeColorsToHash(colors);
    const newHash = `#c.${encoded}`;
    
    // Nếu đang trong iframe, cập nhật trực tiếp URL của parent (same-origin)
    if (isInIframe()) {
      try {
        // Thử cập nhật trực tiếp parent URL (same-origin)
        const parentLocation = window.parent.location;
        const newURL = parentLocation.pathname + parentLocation.search + newHash;
        
        if (window.parent.history && window.parent.history.replaceState) {
          window.parent.history.replaceState(null, '', newURL);
        } else {
          window.parent.location.hash = newHash.substring(1);
        }
      } catch (e) {
        // Cross-origin hoặc không access được parent
        try {
          window.parent.postMessage({
            type: 'MAC_LIVESTYLE_COLOR_UPDATE',
            colors: colors,
            hash: newHash
          }, '*');
        } catch (e2) {
          // Cannot communicate with parent
        }
      }
    } else {
      // Không trong iframe, cập nhật hash trực tiếp
      if (window.history.replaceState) {
        const newURL = window.location.pathname + window.location.search + newHash;
        window.history.replaceState(null, '', newURL);
      } else {
        window.location.hash = newHash.substring(1);
      }
    }
  }
  
  // Đọc màu từ URL hash hoặc từ hash string
  function parseColorsFromHash(hashString) {
    const hash = hashString || window.location.hash;
    
    // Format mới: #c.keyMask.encoded
    if (hash && hash.includes('#c.')) {
      const encodedPart = hash.substring(hash.indexOf('#c.') + 3);
      const decoded = decodeHashToColors(encodedPart);
      if (decoded && Object.keys(decoded).length > 0) {
        return decoded;
      }
    }
    
    // Format cũ (backward compatible): #color?key=value&...
    if (hash && hash.includes('color?')) {
      const colors = {};
      const startIndex = hash.indexOf('color?') + 6;
      const paramsString = hash.substring(startIndex);
      const params = new URLSearchParams(paramsString);
      
      params.forEach((value, key) => {
        colors[key] = value.startsWith('#') ? value : '#' + value;
      });
      
      if (Object.keys(colors).length > 0) {
        return colors;
      }
    }
    
    return {};
  }
  
  // Áp dụng màu vào color inputs
  function applyColors(hashColors) {
    if (!hashColors || Object.keys(hashColors).length === 0) {
      return false;
    }
    
    let applied = 0;
    
    $('.color-control .clr-field > input.coloris[data-color]').each(function() {
      const $input = $(this);
      const colorKey = $input.data('color');
      
      if (hashColors[colorKey]) {
        const colorValue = hashColors[colorKey];
        
        $input.val(colorValue);
        
        const $clrField = $input.closest('.clr-field');
        if ($clrField.length) {
          $clrField.css('color', colorValue);
        }
        
        const $preview = $input.closest('.color-control').find('.color-preview');
        if ($preview.length) {
          $preview.css('background-color', colorValue);
        }
        
        applied++;
        console.log(`  ✓ Applied ${colorKey}: ${colorValue}`);
      }
    });
    
    if (applied > 0) {
      setTimeout(function() {
        const colors = getCurrentColorsStyles();
        const fonts = getCurrentFonts();
        updateWebsiteColors(colors, fonts);
        console.log(`🎨 Applied ${applied} colors`);
      }, 100);
    }
    
    return applied > 0;
  }
  
  // Áp dụng màu từ URL hash vào color inputs
  function loadColorsFromHash() {
    let hashColors = {};
    
    // Thử đọc từ parent hash trước (nếu trong iframe)
    if (isInIframe()) {
      try {
        const parentHash = window.parent.location.hash;
        
        // Check cả format mới (#c.) và format cũ (color?)
        if (parentHash && (parentHash.includes('#c.') || parentHash.includes('color?'))) {
          hashColors = parseColorsFromHash(parentHash);
        }
      } catch (e) {
        // Cannot read parent hash (cross-origin)
      }
    }
    
    // Fallback: đọc từ iframe hash
    if (Object.keys(hashColors).length === 0) {
      const iframeHash = window.location.hash;
      
      if (iframeHash && (iframeHash.includes('#c.') || iframeHash.includes('color?'))) {
        hashColors = parseColorsFromHash(iframeHash);
      }
    }
    
    if (Object.keys(hashColors).length === 0) {
      return false;
    }
    
    return applyColors(hashColors);
  }
  
  // Lắng nghe postMessage từ parent (nhận màu từ parent)
  window.addEventListener('message', function(event) {
    if (event.data && event.data.type === 'MAC_LIVESTYLE_SET_COLORS') {
      
      // Chuyển đổi format {key: 'HEX'} thành {key: '#HEX'}
      const colors = {};
      for (const [key, value] of Object.entries(event.data.colors)) {
        colors[key] = value.startsWith('#') ? value : '#' + value;
      }
      
      applyColors(colors);
    }
  });
  
  // Lắng nghe sự kiện hash change
  $(window).on('hashchange', function() {
    loadColorsFromHash();
  });
  
  // Lắng nghe hashchange của parent khi trong iframe (same-origin)
  if (isInIframe()) {
    try {
      $(window.parent).on('hashchange', function() {
        loadColorsFromHash();
      });
    } catch (e) {
      // Cannot listen to parent hashchange (cross-origin)
    }
  }

  /*---------------------------- Helper Functions ----------------------------*/ 
  // Google Fonts Loader
  function ensurePreconnect() {
    if (!document.querySelector('link[rel="preconnect"][href="https://fonts.googleapis.com"]')) {
      const l1 = document.createElement('link');
      l1.rel = 'preconnect';
      l1.href = 'https://fonts.googleapis.com';
      document.head.appendChild(l1);
    }
    if (!document.querySelector('link[rel="preconnect"][href="https://fonts.gstatic.com"]')) {
      const l2 = document.createElement('link');
      l2.rel = 'preconnect';
      l2.href = 'https://fonts.gstatic.com';
      l2.crossOrigin = 'anonymous';
      document.head.appendChild(l2);
    }
  }
  function loadGoogleFontFamily(fontFamily, weights = '100;200;300;400;500;600;700;800;900') {
    if (!fontFamily) return Promise.resolve();
    ensurePreconnect();
    const famParam = String(fontFamily).trim().replace(/\s+/g, '+');
    const id = `glf-${famParam}-${String(weights).replace(/[^0-9;]/g,'')}`;
    if (!document.getElementById(id)) {
      const link = document.createElement('link');
      link.id = id;
      link.rel = 'stylesheet';
      link.href = `https://fonts.googleapis.com/css2?family=${famParam}:wght@${weights}&display=swap`;
      document.head.appendChild(link);
    }
    if (document.fonts && document.fonts.load) {
      return document.fonts.load(`1em "${fontFamily}"`);
    }
    return new Promise(r => setTimeout(r, 300));
  }
   function getCurrentColorsStyles() {
    const colors = {};
    // Ưu tiên cấu trúc mới của Coloris: .clr-field > input.coloris[data-color]
    const $newInputs = $('.color-control .clr-field > input.coloris[data-color]');
    if ($newInputs.length) {
      $newInputs.each(function() {
        const $input = $(this);
        const dataColor = $input.data('color');
        const $colorControl = $input.closest('.color-control');
        const label = $colorControl.find('label').text().trim();
        
        // Ưu tiên lấy giá trị trực tiếp từ input để giữ nguyên alpha channel (8 ký tự hex)
        let hex = $input.val() || '';
        
        // Nếu input không có giá trị hoặc giá trị không hợp lệ, mới fallback sang CSS
        if (!hex || !/^#[0-9A-Fa-f]{6,8}$/.test(hex)) {
          const $field = $input.closest('.clr-field');
          const rgb = $field.css('color');
          // Chuyển rgb/rgba sang hex (chỉ khi input không có giá trị)
          if (rgb && /^rgba?/i.test(rgb)) {
            const m = rgb.match(/rgba?\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)(?:\s*,\s*([\d.]+))?/i);
            if (m) {
              const toHex = function(n) { return parseInt(n, 10).toString(16).padStart(2, '0'); };
              const r = toHex(m[1]);
              const g = toHex(m[2]);
              const b = toHex(m[3]);
              const a = m[4] ? toHex(Math.round(parseFloat(m[4]) * 255)) : '';
              hex = ('#' + r + g + b + a).toLowerCase();
            }
          }
        }
        
        if (dataColor && hex) {
          // Lưu dưới dạng object với _id và title
          colors[dataColor] = {
            _id: dataColor,
            title: label || dataColor,
            color: hex
          };
        }
      });
      return colors;
    }
    // Fallback: giữ nguyên cách lấy cũ từ input
    $('.color-control input.coloris-input, .color-control input[type="color"]').each(function() {
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
  // Hàm lấy tất cả màu hiện tại từ các input
  function getCurrentColors() {
    const colors = {};
    $('.color-control input.coloris, .color-control input[type="text"]').each(function() {
//     $('.color-control input[type="color"]').each(function() {
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

  // Hàm lấy tất cả font hiện tại từ các select
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

    $('.color-control input[type="color"],.color-control .coloris').each(function() {
      const input = $(this);
      const id = input.attr('data-color');
      if (!id) return;
      
      const colorName = id.replace('-color', '');
      const colorValue = input.val();
      // Tìm CSS variable tương ứng từ data attribute hoặc tạo mới
      if (colorValue) {
        const cssVar = `--e-global-color-${colorName}`;
        variables[cssVar] = colorValue;
      }
        console.log(colorValue);
    });

    return variables;
  }

  // Hàm lấy map selector -> font-family từ UI (primary/secondary/accent)
  function getCSSFontVariables() {
    const selectorToFont = {};
    $('.font-control select.font-select').each(function() {
      const select = $(this);
      const id = select.attr('id');
      if (!id) return;

      const slug = id.replace('-font', '');
      const fontValue = select.val();
      if (!fontValue) return;

      // Map slug -> selectors (đồng bộ với macruleid)
      let selectors = '';
       if (slug === 'secondary') {
        selectors = 'main span, p, main, main .secondary-font';
      }else if (slug === 'primary') {
        selectors = 'main h1, main h2,main h3,main h4,main h5,main h6,main .primary-font,main .module-category__name,main h1 span,main h2 span,main h3 span,main h4 span,main h5 span,main h6 span,main .primary-font,main .module-category__name';
      } else if (slug === 'accent') {
        selectors = 'main .accent-font h1,main .accent-font h2,main .accent-font h3,main .accent-font h4,main .accent-font h5,main .accent-font h6,main .accent-font .elementor-heading-title,main .accent-font h1 span,main .accent-font h2 span,main .accent-font h3 span,main .accent-font h4 span,main .accent-font h5 span,main .accent-font h6 span,main .accent-font .elementor-heading-title,main .accent-font';
      } else {
        return; // bỏ qua slug lạ
      }

      selectorToFont[selectors] = `"${fontValue}", sans-serif`;
    });
    return selectorToFont;
  }

  // Tối ưu hóa helper function để tạo CSS rules
  function createCSSRules(variables) {
    if (!variables || Object.keys(variables).length === 0) return '';
    
    return Object.entries(variables)
      .map(([key, value]) => `${key}: ${value} !important;`)
      .join('\n        ');
  }

  // Tạo CSS rules theo selectors cho fonts
  function createFontSelectorRules(selectorMap) {
    if (!selectorMap || Object.keys(selectorMap).length === 0) return '';
    return Object.entries(selectorMap)
      .map(([selectors, family]) => `${selectors} {\n  font-family: ${family} !important  ;\n}`)
      .join('\n');
  }

  // Hàm cập nhật CSS cho website
  function updateWebsiteColors() {
      // Tạo hoặc lấy style element
      let styleElement = $('#elementor-live-color-dynamic-styles');
      if (styleElement.length === 0) {
        styleElement = $('<style id="elementor-live-color-dynamic-styles"></style>');
        $('head').append(styleElement);
      }

      // Lấy CSS variables từ database
      const colorVariables = getCSSVariables();
      const fontSelectorMap = getCSSFontVariables();
    
      // Tạo CSS rules hiệu quả hơn
      const colorRules = createCSSRules(colorVariables);
      const fontSelectorRules = createFontSelectorRules(fontSelectorMap);

      // Tạo CSS với cấu trúc rõ ràng và hiệu quả
      const cssRules = [];
      
      // Thêm color variables vào :root
      if (colorRules) {
        cssRules.push(`body {\n         ${colorRules}\n    }`);
      }  
      if (fontSelectorRules) {
        cssRules.push(fontSelectorRules);
      }

      // Áp dụng font variables cho các elements
//       if (fontRules) {
//         cssRules.push(`
//           [data-elementor-type="wp-page"] h1,
//           [data-elementor-type="wp-page"] h2,
//           [data-elementor-type="wp-page"] h3,
//           [data-elementor-type="wp-page"] h4,
//           [data-elementor-type="wp-page"] h5,
//           [data-elementor-type="wp-page"] h6,
//           [data-elementor-type="wp-page"] h1 span,
//           [data-elementor-type="wp-page"] h2 span,
//           [data-elementor-type="wp-page"] h3 span,
//           [data-elementor-type="wp-page"] h4 span,
//           [data-elementor-type="wp-page"] h5 span,
//           [data-elementor-type="wp-page"] h6 span {
//             font-family: var(--e-global-typography-primary-font-family, inherit) !important;
//           }
//         `);
        
//         cssRules.push(`
//           [data-elementor-type="wp-page"] p,
//           [data-elementor-type="wp-page"] div,
//           [data-elementor-type="wp-page"] p span,
//           [data-elementor-type="wp-page"] div span {
//             font-family: var(--e-global-typography-secondary-font-family, inherit);
//           }
//         `);
//       }
      
      // Cập nhật style element
      styleElement.html(cssRules.join('\n'));
  }

  /*---------------------------- UI Events ----------------------------*/ 
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

  // Xử lý chọn preset
  $('.preset-item').on('click', function() {
    const presetId = $(this).data('preset');
    const preset = presets[presetId];
    
    if (preset) {
      // Cập nhật các input với giá trị từ preset (chỉ cho các màu cơ bản)
      if ($('#primary-color').length) $('#primary-color').val(preset.colors.primary);
      if ($('#secondary-color').length) $('#secondary-color').val(preset.colors.secondary);
      if ($('#text-color').length) $('#text-color').val(preset.colors.text);
      if ($('#accent-color').length) $('#accent-color').val(preset.colors.accent);
      if ($('#primary-font').length) $('#primary-font').val(preset.fonts.primary);
      if ($('#secondary-font').length) $('#secondary-font').val(preset.fonts.secondary);
      
      // Cập nhật preview
      $('.color-preview').each(function() {
        const colorInput = $(this).siblings('input[type="color"]');
        $(this).css('background-color', colorInput.val());
      });
      
      // Cập nhật website
      const currentColors = getCurrentColorsStyles();
      const currentFonts = getCurrentFonts();
      updateWebsiteColors(currentColors, currentFonts);
      
      // Cập nhật URL hash
      updateURLHash();
    }
  });

  toggleBtn.on('click', function() {
    panel.toggleClass('active');
  });

  closeBtn.on('click', function() {
    panel.removeClass('active');
  });

  // Copy URL button
  $('#copy-color-url').on('click', function() {
    const $btn = $(this);
    
    // Lấy URL hiện tại (đã có hash màu)
    let urlToCopy = '';
    if (isInIframe()) {
      try {
        urlToCopy = window.parent.location.href;
      } catch (e) {
        urlToCopy = window.location.href;
      }
    } else {
      urlToCopy = window.location.href;
    }
    
    // Lấy toàn bộ màu hiện tại
    const colors = getAllColorsSimple();
    const colorList = Object.values(colors).map(val => `#${val}`).join(' ');
    
    // Lấy tên demo từ URL path
    const pathParts = urlToCopy.split('/').filter(p => p && !p.includes('#') && !p.includes('?'));
    const demoName = pathParts[pathParts.length - 2] || pathParts[pathParts.length - 1] || 'demo';
    
    // Tạo nội dung copy: URL + màu sắc
    const copyContent = `${demoName}: ${urlToCopy}\nColors: ${colorList}`;
    
    // Copy vào clipboard
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(copyContent).then(function() {
        showCopySuccess($btn, colorList);
      }).catch(function() {
        fallbackCopy(copyContent, $btn, colorList);
      });
    } else {
      fallbackCopy(copyContent, $btn, colorList);
    }
  });
  
  function fallbackCopy(text, $btn, colorList) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    try {
      document.execCommand('copy');
      showCopySuccess($btn, colorList);
    } catch (e) {
      showCopyError($btn);
    }
    document.body.removeChild(textarea);
  }
  
  function showCopySuccess($btn, colorList) {
    $btn.find('.copy-tooltip').remove();
    $btn.append('<span class="copy-tooltip">Copied! ' + colorList + '</span>');
    
    $btn.addClass('copied show-tooltip');
    
    // Đổi icon thành check
    const originalSvg = $btn.find('svg').html();
    $btn.find('svg').html('<polyline points="20 6 9 17 4 12"/>');
    
    setTimeout(function() {
      $btn.removeClass('copied show-tooltip');
      $btn.find('svg').html(originalSvg);
      $btn.find('.copy-tooltip').remove();
    }, 2000);
  }
  
  function showCopyError($btn) {
    $btn.find('.copy-tooltip').remove();
    $btn.append('<span class="copy-tooltip">Copy failed!</span>');
    $btn.addClass('show-tooltip');
    
    setTimeout(function() {
      $btn.removeClass('show-tooltip');
      $btn.find('.copy-tooltip').remove();
    }, 2000);
  }

  // Cập nhật preview và website khi thay đổi màu
  $('.color-control .coloris').on('input change', function() {
    //const preview = $(this).siblings('.color-preview');
    const preview = $(this).closest('.color-control').find('.color-preview');
    const colorValue = $(this).val();
    preview.css('background-color', colorValue);
    
    // Log màu sắc riêng lẻ khi thay đổi
    console.log(`Đã thay đổi ${$(this).prev('label').text()}:`, colorValue);
    
    // Lấy tất cả màu hiện tại - SỬ DỤNG HÀM MỚI
    const colors = getCurrentColorsStyles();

    // Lấy font hiện tại
    const fonts = getCurrentFonts();
    
    // Cập nhật website
    updateWebsiteColors(colors, fonts);
    
    // Cập nhật URL hash với màu mới (debounce để tránh cập nhật quá nhiều)
    clearTimeout(window.urlHashTimeout);
    window.urlHashTimeout = setTimeout(function() {
      updateURLHash();
    }, 100);
  });

  // Cập nhật website khi thay đổi font
  $('.font-select').on('change', function() {
    const colors = getCurrentColorsStyles();
    const fonts = getCurrentFonts();
    Promise.all(Object.values(fonts).map(f => loadGoogleFontFamily(f)))
      .finally(() => {
        console.log('Font đã thay đổi:', fonts);
        updateWebsiteColors(colors, fonts);
      });
  });

  // Khởi tạo preview ban đầu
  $('.color-control input[type="text"]').each(function() {
    const preview = $(this).siblings('.color-preview');
    preview.css('background-color', $(this).val());
  });

  // Khởi tạo màu ban đầu cho website
  const initialColors = getCurrentColorsStyles();

  // Khởi tạo font ban đầu
  const initialFonts = getCurrentFonts();

  // Init Select2 for font selects using fonts from PHP (elementorLiveColor.fonts)
  function initFontSelect2() {
    if (!window.elementorLiveColor || !Array.isArray(window.elementorLiveColor.fonts)) return;
    const fonts = window.elementorLiveColor.fonts;
    $('.font-select').each(function() {
      const $sel = $(this);
      const current = $sel.data('current') || $sel.val() || '';
      // clear and populate
      $sel.empty();
      $sel.append($('<option>'));
      fonts.forEach(f => {
        if (f && f.value) {
          const opt = new Option(f.text || f.value, f.value, false, false);
          $sel.append(opt);
        }
      });
      // init select2
      if ($sel.hasClass('select2-hidden-accessible')) {
        $sel.select2('destroy');
      }
      $sel.select2({ width: '100%', placeholder: 'Select Font', allowClear: true, dropdownAutoWidth: true });
      if (current) {
        $sel.val(current).trigger('change');
      }
    });
  }
  initFontSelect2();

  Promise.all(Object.values(initialFonts).map(f => loadGoogleFontFamily(f)))
    .finally(() => {
      updateWebsiteColors(initialColors, initialFonts);
      
      // Load màu từ URL hash nếu có (sau khi init xong)
      // Thử nhiều lần vì khi trong iframe, parent có thể chưa sẵn sàng
      function tryLoadColorsFromHash(attempts) {
        const loaded = loadColorsFromHash();
        if (!loaded && attempts > 0 && isInIframe()) {
          setTimeout(function() {
            tryLoadColorsFromHash(attempts - 1);
          }, 300);
        }
      }
      
      setTimeout(function() {
        tryLoadColorsFromHash(5); // Thử tối đa 5 lần
      }, 200);
    });

  // Xử lý export settings
  jQuery('#export-settings').on('click', function() {
    const pageId = getCurrentPageId();
    
    // Lấy tất cả màu sắc hiện tại đã thay đổi
    const currentColors = getCurrentColorsStyles();
    
    // Lấy tất cả font hiện tại đã thay đổi  
    const currentFonts = getCurrentFonts();
    const fontValues = Object.values(currentFonts);
    
    console.log("Đang export với dữ liệu hiện tại:");
    console.log("- Màu sắc hiện tại:", currentColors);
    console.log("- Font hiện tại:", currentFonts);
    console.log("- Page ID:", pageId);

    jQuery.ajax({
      url: elementorLiveColor.ajaxurl,
      type: 'POST',
      data: {
        action: 'export_page_settings',
        nonce: elementorLiveColor.nonce,
        page_id: pageId,
        current_colors: currentColors,     // Gửi màu sắc hiện tại
        current_fonts: currentFonts,       // Gửi font hiện tại
        fonts: fontValues                  // Giữ lại để tương thích
      },
      success: function(response) {
        if (response.success) {
          const data = response.data;
          console.log("Export thành công:", data);
          
          // Tạo tên file với timestamp để dễ phân biệt
          const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, -5);
          const filename = `mac-theme-settings-${data.page_id}-${timestamp}.json`;
          
          const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
          const url = window.URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = filename;
          document.body.appendChild(a);
          a.click();
          window.URL.revokeObjectURL(url);
          document.body.removeChild(a);
          
          alert(`Đã export thành công các cài đặt hiện tại!\nFile: ${filename}`);
        } else {
          console.error("Export failed:", response);
          alert('Có lỗi xảy ra khi export settings: ' + (response.data || 'Unknown error'));
        }
      },
      error: function(xhr, status, error) {
        console.error("AJAX Error:", {xhr, status, error});
        alert('Có lỗi xảy ra khi export settings: ' + error);
      }
    });
  });

  // Xử lý export site settings
  jQuery('#export-site-settings').on('click', function() {
    // Lấy tất cả màu sắc hiện tại đã thay đổi
    //const currentColors = getCurrentColors();
  
    const currentColors = getCurrentColorsStyles();
    // Lấy tất cả font hiện tại đã thay đổi  
    const currentFonts = getCurrentFonts();
    
    console.log("Đang export Site Settings với dữ liệu hiện tại:");
    console.log("- Màu sắc hiện tại:", currentColors);
    console.log("- Font hiện tại:", currentFonts);
    
    // Hiển thị loading state
    const btn = $(this);
    const originalHTML = btn.html();
    btn.html('<span>Đang export...</span>').prop('disabled', true);

    jQuery.ajax({
      url: elementorLiveColor.ajaxurl,
      type: 'POST',
      data: {
        action: 'export_site_settings',
        nonce: elementorLiveColor.nonce,
        current_colors: currentColors,
        current_fonts: currentFonts
      },
      success: function(response) {
        // Khôi phục nút
        btn.html(originalHTML).prop('disabled', false);
        
        if (response.success) {
          const data = response.data;
          console.log("Export Site Settings thành công:", data);
          
          // Tạo tên file với thông tin site và timestamp
          const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, -5);
          const siteNameSource = document.title || window.location.hostname || 'site';
          const siteName = siteNameSource.replace(/[^a-zA-Z0-9]/g, '-').toLowerCase();
          const filename = `${siteName}-elementor-kit-${timestamp}.json`;
          
          const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
          const url = window.URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = filename;
          document.body.appendChild(a);
          a.click();
          window.URL.revokeObjectURL(url);
          document.body.removeChild(a);
          
          // Hiển thị thông báo chi tiết
          const message = `Đã export thành công Site Settings!\n\n` +
                         `🎨 Màu sắc: ${Object.keys(currentColors).length} màu\n` +
                         `📝 Font: ${Object.keys(currentFonts).length} font\n` +
                         `📁 File: ${filename}`;
          
          alert(message);
        } else {
          console.error("Export Site Settings failed:", response);
          alert('Có lỗi xảy ra khi export site settings: ' + (response.data || 'Unknown error'));
        }
      },
      error: function(xhr, status, error) {
        // Khôi phục nút
        btn.html(originalHTML).prop('disabled', false);
        
        console.error("AJAX Error:", {xhr, status, error});
        alert('Có lỗi xảy ra khi export site settings: ' + error);
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

  /*---------------------------- Reset Colors to Default ----------------------------*/
  // Lưu giá trị default ban đầu từ các input
  const defaultColors = {};
  
  // Lưu giá trị default khi page load
  function saveDefaultColors() {
    $('.color-control .clr-field > input.coloris[data-color]').each(function() {
      const $input = $(this);
      const dataColor = $input.data('color');
      // Lấy giá trị từ attribute value ban đầu (default từ PHP)
      const defaultValue = $input.attr('value') || $input.val();
      if (dataColor && defaultValue) {
        defaultColors[dataColor] = defaultValue;
      }
    });
    
    // Fallback cho các input cũ
    $('.color-control input.coloris-input, .color-control input[type="color"]').each(function() {
      const input = $(this);
      const id = input.attr('id');
      if (id) {
        const colorName = id.replace('-color', '');
        const defaultValue = input.attr('value') || input.val();
        if (defaultValue) {
          defaultColors[colorName] = defaultValue;
        }
      }
    });
    
    console.log('💾 Default colors saved:', defaultColors);
  }
  
  // Reset màu về giá trị default
  function resetColorsToDefault() {
    if (Object.keys(defaultColors).length === 0) {
      console.warn('⚠️ No default colors found');
      return;
    }
    
    let resetCount = 0;
    
    // Reset các input màu về giá trị default
    $('.color-control .clr-field > input.coloris[data-color]').each(function() {
      const $input = $(this);
      const dataColor = $input.data('color');
      
      if (dataColor && defaultColors[dataColor]) {
        const defaultValue = defaultColors[dataColor];
        
        // Set giá trị cho input
        $input.val(defaultValue);
        
        // Cập nhật Coloris field
        const $clrField = $input.closest('.clr-field');
        if ($clrField.length) {
          $clrField.css('color', defaultValue);
        }
        
        // Cập nhật preview
        const $preview = $input.closest('.color-control').find('.color-preview');
        if ($preview.length) {
          $preview.css('background-color', defaultValue);
        }
        
        resetCount++;
        console.log(`  ✓ Reset ${dataColor}: ${defaultValue}`);
      }
    });
    
    // Fallback cho các input cũ
    $('.color-control input.coloris-input, .color-control input[type="color"]').each(function() {
      const input = $(this);
      const id = input.attr('id');
      if (id) {
        const colorName = id.replace('-color', '');
        if (defaultColors[colorName]) {
          input.val(defaultColors[colorName]);
          const preview = input.siblings('.color-preview');
          if (preview.length) {
            preview.css('background-color', defaultColors[colorName]);
          }
          resetCount++;
        }
      }
    });
    
    if (resetCount > 0) {
      // Cập nhật website colors
      const colors = getCurrentColorsStyles();
      const fonts = getCurrentFonts();
      updateWebsiteColors(colors, fonts);
      
      // Xóa hash khỏi URL
      removeColorHashFromURL();
      
      console.log(`🎨 Reset ${resetCount} colors to default`);
    }
  }
  
  // Xóa hash màu khỏi URL
  function removeColorHashFromURL() {
    let hashRemoved = false;
    
    if (isInIframe()) {
      // Trong iframe: ưu tiên xóa hash của parent window
      try {
        const parentLocation = window.parent.location;
        const parentHash = parentLocation.hash;
        
        // Kiểm tra nếu parent có hash màu
        if (parentHash && (parentHash.includes('#c.') || parentHash.includes('color?'))) {
          const parentNewURL = parentLocation.pathname + parentLocation.search;
          
          if (window.parent.history && window.parent.history.replaceState) {
            window.parent.history.replaceState(null, '', parentNewURL);
            hashRemoved = true;
            console.log('🗑️ Removed color hash from parent URL:', parentNewURL);
          } else {
            window.parent.location.hash = '';
            hashRemoved = true;
            console.log('🗑️ Cleared parent hash');
          }
        }
      } catch (e) {
        // Cross-origin hoặc không access được parent
        console.log('⚠️ Cannot update parent URL (cross-origin):', e.message);
        
        // Thử dùng postMessage
        try {
          window.parent.postMessage({
            type: 'MAC_LIVESTYLE_REMOVE_HASH'
          }, '*');
          console.log('📤 Sent postMessage to remove hash');
        } catch (e2) {
          console.log('⚠️ Cannot send postMessage:', e2.message);
        }
      }
      
      // Cũng kiểm tra và xóa hash của iframe window
      const iframeHash = window.location.hash;
      if (iframeHash && (iframeHash.includes('#c.') || iframeHash.includes('color?'))) {
        const iframeNewURL = window.location.pathname + window.location.search;
        if (window.history.replaceState) {
          window.history.replaceState(null, '', iframeNewURL);
          hashRemoved = true;
          console.log('🗑️ Removed color hash from iframe URL:', iframeNewURL);
        } else {
          window.location.hash = '';
          hashRemoved = true;
        }
      }
    } else {
      // Không trong iframe, xóa hash trực tiếp
      const currentHash = window.location.hash;
      
      if (currentHash && (currentHash.includes('#c.') || currentHash.includes('color?'))) {
        const newURL = window.location.pathname + window.location.search;
        
        if (window.history.replaceState) {
          window.history.replaceState(null, '', newURL);
          hashRemoved = true;
          console.log('🗑️ Removed color hash from URL:', newURL);
        } else {
          window.location.hash = '';
          hashRemoved = true;
          console.log('🗑️ Cleared hash');
        }
      }
    }
    
    if (!hashRemoved) {
      console.log('ℹ️ No color hash found to remove');
    }
  }
  
  // Lưu default colors khi page load
  $(document).ready(function() {
    // Đợi một chút để đảm bảo các input đã được render
    setTimeout(function() {
      saveDefaultColors();
    }, 100);
  });
  
  // Xử lý click button Reset Colors
  jQuery('#reset-colors').on('click', function() {
    const $btn = $(this);
    const originalHTML = $btn.html();
    
    // Hiển thị loading state
    $btn.html('<span>Đang reset...</span>').prop('disabled', true);
    
    // Reset màu
    resetColorsToDefault();
    
    // Khôi phục button sau 500ms
    setTimeout(function() {
      $btn.html(originalHTML).prop('disabled', false);
    }, 500);
  });

  /*---------------------------- Close Coloris Picker on Scroll ----------------------------*/
  // Đóng picker khi scroll window hoặc tab-content
  let scrollTimeout;
  
  function closeColorisPicker() {
    const picker = document.getElementById('clr-picker');
    if (!picker) return;
    
    // Kiểm tra xem picker có đang hiển thị không
    const pickerDisplay = window.getComputedStyle(picker).display;
    if (pickerDisplay === 'none') return;
    
    // Đóng picker bằng cách click vào preview button (close button)
    const previewButton = picker.querySelector('.clr-preview');
    if (previewButton) {
      previewButton.click();
    } else {
      // Fallback: ẩn picker trực tiếp nếu không tìm thấy button
      picker.style.display = 'none';
    }
  }
  
  // Xử lý scroll của window
  function handleWindowScroll() {
    clearTimeout(scrollTimeout);
    scrollTimeout = setTimeout(function() {
      closeColorisPicker();
    }, 100); // Debounce 100ms để tránh đóng quá nhanh
  }
  
  // Xử lý scroll của tab-content
  function handleTabContentScroll() {
    clearTimeout(scrollTimeout);
    scrollTimeout = setTimeout(function() {
      closeColorisPicker();
    }, 100); // Debounce 100ms
  }
  
  // Lắng nghe scroll của window
  $(window).on('scroll', handleWindowScroll);
  
  // Lắng nghe scroll của tab-content trong panel
  const panelElement = document.getElementById('elementor-color-control-panel');
  if (panelElement) {
    const tabContent = panelElement.querySelector('.tab-content');
    if (tabContent) {
      $(tabContent).on('scroll', handleTabContentScroll);
      tabContent.addEventListener('scroll', handleTabContentScroll, true);
    }
  } else {
    // Retry sau 1 giây nếu panel chưa có
    setTimeout(function() {
      const panelRetry = document.getElementById('elementor-color-control-panel');
      if (panelRetry) {
        const tabContent = panelRetry.querySelector('.tab-content');
        if (tabContent) {
          $(tabContent).on('scroll', handleTabContentScroll);
          tabContent.addEventListener('scroll', handleTabContentScroll, true);
        }
      }
    }, 1000);
  }
  
  console.log('✅ Coloris picker auto-close on scroll initialized');
});


