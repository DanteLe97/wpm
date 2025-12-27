# Mac Preview Module

## Tổng quan
Module Mac Preview được tích hợp vào plugin Mac Rule ID để cung cấp chức năng preview và export dynamic sections của Elementor.

## Cấu trúc Files
```
macruleid/modules/mac-preview/
├── mac-preview-core.php        # Core functions và hooks
├── assets/
│   ├── css/
│   │   └── mac-preview-frontend.css  # Frontend styles
│   └── js/
│       └── mac-preview-frontend.js   # Frontend JavaScript
└── README.md                   # Documentation
```

## Tích hợp với Plugin Chính

### 1. **Include Module**
```php
// macruleid.php
require_once MAC_RULEID_PATH . 'modules/mac-preview/mac-preview-core.php';
```

### 2. **Activation/Deactivation Hooks**
```php
// Activation
function mac_create_previews_directory() {
    // ... existing code ...
    
    // Activate Mac Preview Module
    if (function_exists('mac_preview_activate')) {
        mac_preview_activate();
    }
}

// Deactivation
function mac_ruleid_deactivate() {
    if (function_exists('mac_preview_deactivate')) {
        mac_preview_deactivate();
    }
}
```

### 3. **Admin Integration**
Module được hiển thị trong Theme Options admin page với:
- Status check (Active/Inactive)
- Feature description
- Requirements validation
- Elementor dependency warning

## Tối ưu hóa Code

### 1. **CSS Variables Sharing**
- ✅ Loại bỏ duplicate CSS variables
- ✅ Sử dụng chung variables từ `custom-colors-fonts.css`
- ✅ Dependency management trong enqueue

**Trước:**
```css
:root {
    --mac-primary-color: #f26212;
    --mac-border-color: #e1e4e8;
    /* ... duplicate variables ... */
}
```

**Sau:**
```css
/* Sử dụng chung variables từ custom-colors-fonts.css:
   --primary-color, --border-color, --text-color, etc. */
```

### 2. **Function Naming Convention**
- ✅ Tất cả functions có prefix `mac_preview_`
- ✅ Không có xung đột với plugin chính
- ✅ Consistent naming pattern

### 3. **Script Optimization**
- ✅ Dependency checking trong JavaScript
- ✅ Error handling cho missing dependencies
- ✅ Proper jQuery wrapper

### 4. **Enqueue Optimization**
- ✅ CSS dependency trên `custom-colors-fonts-css`
- ✅ Duplicate script checking với `wp_script_is()`
- ✅ Proper load order

### 5. **Animation Names**
- ✅ Đổi từ `macExportFadeIn` → `macPreviewFadeIn`
- ✅ Đổi từ `macExportSpin` → `macPreviewSpin`
- ✅ Consistent với module name

## Chức năng

### 1. **Auto Scan**
- Tự động scan containers khi mở popup lần đầu
- Chỉ scan một lần, không scan lại nếu đã có data
- Scan containers trên trang hiện tại

### 2. **Export JSON**
- Export Elementor container data dưới dạng JSON
- Support multiple containers
- Include metadata (page_id, exported_at, site_url)

### 3. **Frontend UI**
- Floating button cho admin users
- Modern popup với drag & drop
- URL scanning cho remote containers
- LocalStorage persistence

### 4. **Rewrite Rules**
- Endpoint: `/page-mac-dynamic-section/`
- Template fallback system
- JSON download functionality

## Requirements
- WordPress 5.0+
- Elementor plugin
- Admin permissions (`manage_options`)
- Modern browser với ES6 support

## Security
- ✅ ABSPATH check
- ✅ Capability checking
- ✅ Nonce validation
- ✅ Input sanitization
- ✅ Output escaping

## Performance
- ✅ Conditional loading (chỉ load cho admin trên Elementor pages)
- ✅ Minified assets ready
- ✅ Efficient DOM queries
- ✅ LocalStorage caching

## Browser Support
- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+

## Troubleshooting

### Common Issues
1. **Floating button không hiện**: Kiểm tra user permissions và Elementor page
2. **CSS không load**: Kiểm tra dependency `custom-colors-fonts-css`
3. **JavaScript errors**: Kiểm tra jQuery và macPreviewData

### Debug Mode
```javascript
// Enable debug logging
localStorage.setItem('mac_preview_debug', 'true');
```

## Changelog

### Version 2.0
- ✅ Tích hợp vào plugin chính Mac Rule ID
- ✅ Tối ưu hóa CSS variables sharing
- ✅ Cải thiện error handling
- ✅ Auto scan functionality
- ✅ Admin integration
- ✅ Code cleanup và optimization