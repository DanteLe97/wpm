# Kiến trúc Plugin: MAC Interactive Tutorials

## 1. CẤU TRÚC DỮ LIỆU

### Option 1: Custom Post Type (Đề xuất) ✅
**Ưu điểm:**
- Tận dụng WordPress editor (Gutenberg/Classic)
- Có sẵn revision, draft, publish workflow
- Dễ quản lý với admin UI quen thuộc
- Hỗ trợ taxonomy để phân loại
- SEO friendly với permalink

**Cấu trúc:**
- Post Type: `mac_tutorial`
- Post Content: Mô tả tổng quan (dùng WordPress editor)
- Meta Fields: Lưu steps, settings, dependencies

### Option 2: Custom Database Table
**Ưu điểm:**
- Cấu trúc dữ liệu tùy chỉnh hoàn toàn
- Hiệu suất tốt hơn cho dữ liệu phức tạp

**Nhược điểm:**
- Phải tự xây dựng admin UI
- Không tận dụng được WordPress editor

## 2. CẤU TRÚC META FIELDS (Cho Custom Post Type)

### Workflow Meta Structure:
```php
// Lưu trong post_meta với key: '_mac_tutorial_steps'
[
    'steps' => [
        [
            'id' => 'step_1',
            'title' => 'Bước 1: Tạo Post Type',
            'description' => 'Mô tả chi tiết bước này...',
            'content' => 'HTML content từ editor',
            'target_url' => 'admin.php?page=...',
            'target_selector' => '#element-id', // Optional: highlight element
            'min_time' => 2, // phút
            'max_time' => 5,
            'order' => 1,
            'dependencies' => [
                'type' => 'plugin',
                'file' => 'plugin-file.php'
            ]
        ],
        // ... more steps
    ],
    'settings' => [
        'estimated_time' => '10-15',
        'difficulty' => 'beginner',
        'category' => 'post-types'
    ]
]
```

## 3. KIẾN TRÚC PLUGIN

```
mac-interactive-tutorials/
├── mac-interactive-tutorials.php (Main file)
├── includes/
│   ├── class-post-type.php (Register CPT)
│   ├── class-meta-boxes.php (Meta boxes cho steps)
│   ├── class-state-manager.php (Quản lý state của user)
│   ├── class-frontend.php (Render floating widget)
│   ├── class-ajax-handler.php (Xử lý AJAX)
│   └── class-dependencies.php (Kiểm tra dependencies)
├── admin/
│   ├── class-admin.php
│   ├── assets/
│   │   ├── css/admin.css
│   │   └── js/admin.js (Step builder UI)
├── frontend/
│   ├── assets/
│   │   ├── css/tutorial-widget.css
│   │   └── js/tutorial-widget.js
└── templates/
    └── tutorial-widget.php
```

## 4. TÍNH NĂNG CHÍNH

### 4.1. Admin Interface
- **List View**: Danh sách tutorials (Custom Post Type)
- **Editor**: WordPress editor cho mô tả tổng quan
- **Step Builder**: Meta box để thêm/sửa/xóa steps
  - Reorderable steps (drag & drop)
  - Rich text editor cho mỗi step
  - URL picker cho target_url
  - Element selector cho highlight
  - Dependency checker

### 4.2. Frontend Widget
- Floating widget (draggable, resizable)
- Hiển thị step hiện tại
- Navigation: Next/Previous
- Auto-scroll đến target element
- Highlight element trên page
- Pause/Resume functionality
- Progress indicator

### 4.3. State Management
- Lưu trong `user_meta`: `mac_tutorial_state`
- Track: workflow_id, current_step, status (in-progress/pause/complete)
- Multi-user support

### 4.4. URL Navigation
- Khi click "Next", tự động chuyển đến `target_url` của step tiếp theo
- Sử dụng `window.location.href` hoặc `window.open()` tùy setting

## 5. DATABASE SCHEMA

### Post Meta Table (WordPress default)
- `post_id`: ID của tutorial post
- `meta_key`: `_mac_tutorial_steps`, `_mac_tutorial_settings`
- `meta_value`: Serialized array

### User Meta Table (WordPress default)
- `user_id`: ID của user
- `meta_key`: `mac_tutorial_state`
- `meta_value`: Serialized state data

## 6. WORKFLOW

### Tạo Tutorial:
1. Admin tạo post mới (Custom Post Type)
2. Viết mô tả trong WordPress editor
3. Thêm steps trong meta box
4. Mỗi step có:
   - Title & Description
   - Content (HTML)
   - Target URL
   - Optional: Element selector
5. Publish tutorial

### User sử dụng:
1. User vào trang "Interactive Tutorials"
2. Chọn tutorial → Click "Start"
3. Frontend widget hiển thị
4. Hiển thị step đầu tiên
5. User click "Next" → Chuyển đến URL của step tiếp theo
6. Widget tự động load lại với step mới
7. Có thể pause/resume

## 7. TECHNICAL IMPLEMENTATION

### 7.1. Register Custom Post Type
```php
register_post_type('mac_tutorial', [
    'public' => false,
    'show_ui' => true,
    'show_in_menu' => true,
    'menu_icon' => 'dashicons-welcome-learn-more',
    'supports' => ['title', 'editor', 'thumbnail'],
    'capability_type' => 'post',
]);
```

### 7.2. Meta Box cho Steps
- Sử dụng WordPress Meta Box API
- JavaScript để add/remove/reorder steps
- AJAX để save steps

### 7.3. Frontend Widget
- JavaScript class tương tự `CroblockWorkflow`
- Load tutorial data từ REST API hoặc inline script
- State management với AJAX

## 8. SECURITY CONSIDERATIONS

- Nonce verification cho AJAX
- Capability checks (`manage_options` cho admin)
- Sanitize và validate input
- Escape output
- CSRF protection

## 9. PERFORMANCE

- Cache tutorial data (transient)
- Lazy load widget chỉ khi cần
- Minify CSS/JS
- Optimize database queries

## 10. EXTENSIBILITY

- Hooks và filters để extend
- Support cho custom step types
- Integration với other plugins
- Import/Export tutorials

