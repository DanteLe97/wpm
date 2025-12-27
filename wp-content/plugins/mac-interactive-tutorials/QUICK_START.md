# Quick Start Guide - MAC Interactive Tutorials

## ✅ Plugin đã được tạo thành công!

### Cấu trúc Plugin

```
mac-interactive-tutorials/
├── mac-interactive-tutorials.php    # File chính
├── includes/
│   ├── class-post-type.php         # Custom Post Type
│   ├── class-meta-boxes.php         # Meta boxes cho steps
│   ├── class-state-manager.php     # Quản lý state
│   └── class-frontend.php          # Frontend widget loader
├── admin/
│   ├── class-admin.php             # Admin page
│   └── assets/
│       ├── js/admin.js            # Step builder JS
│       └── css/admin.css          # Admin styles
└── frontend/
    └── assets/
        ├── js/widget.js           # Widget JavaScript
        └── css/widget.css         # Widget styles
```

## 🚀 Cách Sử Dụng

### 1. Kích hoạt Plugin
- Vào **Plugins** → **Installed Plugins**
- Tìm **MAC Interactive Tutorials**
- Click **Activate**

### 2. Tạo Tutorial Mới
1. Vào **Interactive Tutorials** → **Add New**
2. Nhập **Title** và **Description** (dùng WordPress editor)
3. Trong meta box **Tutorial Steps**, click **+ Add Step**
4. Điền thông tin cho mỗi step:
   - **Title**: Tên step
   - **Description**: Mô tả chi tiết
   - **Target URL**: URL sẽ navigate đến (ví dụ: `admin.php?page=example`)
   - **Element Selector**: CSS selector để highlight (optional)
   - **Min/Max Time**: Thời gian ước tính (phút)
5. Thêm nhiều steps bằng cách click **+ Add Step**
6. Có thể kéo thả để sắp xếp lại steps
7. Click **Publish**

### 3. Bắt Đầu Tutorial
1. Vào **Interactive Tutorials** → **All Tutorials**
2. Tìm tutorial muốn chạy
3. Click **Start Tutorial** trong row actions
4. Widget sẽ hiển thị ở góc dưới bên phải
5. Click **Next** để chuyển step
6. Widget sẽ tự động navigate đến URL của step tiếp theo

### 4. Điều Khiển Widget
- **Next/Previous**: Điều hướng giữa các steps
- **Pause**: Tạm dừng tutorial (có thể resume sau)
- **Close**: Đóng widget
- **Drag**: Kéo header để di chuyển widget
- **Keyboard Shortcuts**:
  - `Ctrl/Cmd + →`: Next step
  - `Ctrl/Cmd + ←`: Previous step
  - `Esc`: Pause

## 📋 Tính Năng Hiện Tại

### ✅ Đã Hoàn Thành (MVP)
- [x] Custom Post Type cho tutorials
- [x] WordPress Editor cho content
- [x] Step Builder với add/remove/reorder
- [x] State Management (lưu progress)
- [x] Frontend Widget
- [x] URL Navigation
- [x] Element Highlighting
- [x] Progress Indicator
- [x] Pause/Resume
- [x] Drag & Drop widget

### 🔄 Cần Cải Thiện (Phase 2)
- [ ] Rich text editor cho steps
- [ ] Better UI/UX
- [ ] Widget resize
- [ ] More keyboard shortcuts
- [ ] Better error handling

### 🚀 Tính Năng Nâng Cao (Phase 3)
- [ ] Dependencies checking
- [ ] Import/Export
- [ ] Analytics
- [ ] Shortcode support

## 🐛 Troubleshooting

### Widget không hiển thị?
- Kiểm tra xem tutorial đã được publish chưa
- Kiểm tra xem đã click "Start Tutorial" chưa
- Mở browser console để xem lỗi JavaScript

### Steps không lưu?
- Kiểm tra xem đã điền Title cho step chưa
- Kiểm tra permissions (cần quyền edit posts)
- Kiểm tra nonce (thử reload page)

### URL Navigation không hoạt động?
- Kiểm tra format URL (relative hoặc absolute)
- Kiểm tra xem URL có hợp lệ không
- Kiểm tra browser console

## 📝 Notes

- Tutorial state được lưu per user
- Mỗi user có thể có 1 tutorial active tại một thời điểm
- Widget position được lưu trong localStorage
- Progress được tự động lưu khi chuyển step

## 🎯 Next Steps

1. Test plugin với các tutorials thực tế
2. Thu thập feedback
3. Cải thiện UI/UX
4. Thêm tính năng theo nhu cầu

---

**Version:** 1.0.0  
**Status:** MVP Complete ✅

