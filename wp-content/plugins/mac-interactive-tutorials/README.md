# MAC Interactive Tutorials Plugin

## 📋 Tổng Quan

Plugin tạo và quản lý interactive tutorials tương tự Crocoblock Workflows, nhưng với khả năng tạo tutorials trực tiếp trong WordPress admin sử dụng Custom Post Type và WordPress Editor.

## ✅ Độ Khả Thi: **CAO**

### Lý do:
- ✅ WordPress cung cấp đủ công cụ cần thiết
- ✅ Có thể tận dụng WordPress Editor
- ✅ Không cần external dependencies phức tạp
- ✅ Có thể phát triển theo từng giai đoạn

## 🎯 Tính Năng Chính

### 1. Quản Lý Tutorials
- Custom Post Type để tạo tutorials
- WordPress Editor cho nội dung mô tả
- Meta boxes để quản lý steps
- Settings: difficulty, category

### 2. Step Builder
- Thêm/sửa/xóa steps
- Drag & drop để sắp xếp lại
- Mỗi step có:
  - Title & Description
  - Target URL (để navigate)
  - Element Selector (để highlight)
  - Estimated time

### 3. Frontend Widget
- Floating widget (draggable, resizable)
- Hiển thị step hiện tại
- Navigation: Next/Previous
- Auto-navigate đến target URL
- Element highlighting
- Progress indicator
- Pause/Resume functionality

### 4. State Management
- Lưu state trong user_meta
- Multi-user support
- Track: current_step, status
- Auto-resume khi reload page

## 📁 Cấu Trúc Plugin

```
mac-interactive-tutorials/
├── mac-interactive-tutorials.php (Main file)
├── includes/
│   ├── class-post-type.php
│   ├── class-meta-boxes.php
│   ├── class-state-manager.php
│   ├── class-frontend.php
│   └── class-ajax-handler.php
├── admin/
│   ├── assets/
│   │   ├── css/admin.css
│   │   └── js/admin.js
├── frontend/
│   ├── assets/
│   │   ├── css/widget.css
│   │   └── js/widget.js
└── templates/
    └── widget.php
```

## 🚀 Cài Đặt & Sử Dụng

### Tạo Tutorial:
1. Vào **Interactive Tutorials** → **Add New**
2. Nhập title và mô tả trong WordPress editor
3. Thêm steps trong meta box "Tutorial Steps"
4. Mỗi step cần:
   - Title
   - Description
   - Target URL (nơi sẽ navigate đến)
   - Optional: Element selector để highlight
5. Publish tutorial

### Sử Dụng Tutorial:
1. Vào trang **Interactive Tutorials**
2. Chọn tutorial → Click **"Start Tutorial"**
3. Floating widget sẽ hiển thị
4. Click **Next** để chuyển step và navigate đến URL
5. Có thể **Pause** để tạm dừng

## 📊 So Sánh Với Crocoblock

| Tính năng | Crocoblock | Plugin này | Status |
|-----------|------------|------------|--------|
| Danh sách tutorials | Remote API | Custom Post Type | ✅ |
| Tạo tutorial | Remote | WordPress Editor | ✅ |
| Rich text editor | ❌ | ✅ WordPress Editor | ✅ |
| Multiple steps | ✅ | ✅ | ✅ |
| URL navigation | ✅ | ✅ | ✅ |
| Floating widget | ✅ | ✅ | ✅ |
| Element highlight | ✅ | ✅ | ✅ |
| State management | ✅ | ✅ | ✅ |
| Dependencies | ✅ Auto install | ⚠️ Có thể thêm | ⚠️ |
| Drag & drop steps | ❌ | ✅ | ✅ |

## 🛠️ Development Roadmap

### Phase 1: MVP (1-2 tuần)
- [x] Custom Post Type
- [x] Basic meta boxes
- [x] Step builder (simple)
- [x] Frontend widget
- [x] URL navigation
- [x] State management

### Phase 2: Enhanced (1 tuần)
- [ ] Rich step builder với drag & drop
- [ ] Element highlighting
- [ ] Auto-scroll
- [ ] Better UI/UX

### Phase 3: Advanced (1 tuần)
- [ ] Dependencies checking
- [ ] Import/Export tutorials
- [ ] Analytics
- [ ] Shortcode support

## 📝 Files Quan Trọng

- `ARCHITECTURE.md` - Kiến trúc chi tiết
- `FEASIBILITY_ANALYSIS.md` - Phân tích độ khả thi
- `IMPLEMENTATION_EXAMPLE.php` - Code examples
- `frontend/js/widget-example.js` - Frontend widget example

## 🔒 Security

- Nonce verification cho AJAX
- Capability checks
- Sanitize và validate input
- Escape output
- CSRF protection

## ⚡ Performance

- Cache tutorial data
- Lazy load widget
- Minify CSS/JS
- Optimize database queries

## 📚 Documentation

Xem các file:
- `ARCHITECTURE.md` - Kiến trúc hệ thống
- `FEASIBILITY_ANALYSIS.md` - Phân tích chi tiết
- `IMPLEMENTATION_EXAMPLE.php` - Code examples

## 🎓 Học Từ Crocoblock

Plugin này học hỏi từ Crocoblock Workflows nhưng có những cải tiến:
- ✅ Tạo tutorials trực tiếp trong WordPress (không cần remote API)
- ✅ Sử dụng WordPress Editor (familiar UI)
- ✅ Full control over content
- ✅ Dễ customize và extend

## 💡 Tips

1. **Bắt đầu với MVP**: Implement các tính năng cơ bản trước
2. **Test thường xuyên**: Test với users thực tế
3. **Iterate**: Thêm features theo feedback
4. **Document**: Giữ documentation up-to-date

## 🤝 Contributing

Nếu muốn contribute, vui lòng:
1. Fork repository
2. Tạo feature branch
3. Commit changes
4. Push và tạo Pull Request

## 📄 License

Tùy chọn license của bạn

---

**Tác giả:** MAC USA One  
**Version:** 1.0.0  
**Last Updated:** 2025

