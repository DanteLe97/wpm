# MAC Unused Images Scanner

Plugin WordPress để quét và xóa ảnh không sử dụng trong Media Library.

## Tính năng

- ✅ Quét ảnh không sử dụng bằng WP-Cron (không timeout)
- ✅ Hiển thị tiến trình quét real-time
- ✅ Bulk delete với xác nhận
- ✅ Tự động xóa WebP version (WebP Express)
- ✅ Kiểm tra nhiều nơi: Featured Image, WooCommerce Gallery, Post Meta, Post Content, WordPress Options
- ✅ Hỗ trợ các builder: Elementor, ACF, và các custom options

## Cài đặt

1. Copy thư mục `mac-unused-images-scanner` vào `wp-content/plugins/`
2. Kích hoạt plugin trong WordPress Admin > Plugins
3. Vào Media > Unused Images (Cron) để sử dụng

## Sử dụng

1. **Quét nền (WP-Cron)**: Click "Bắt đầu quét nền" - chạy trong nền, không timeout
2. **Quét thủ công**: Click "Chạy quét ngay" - chạy trực tiếp (có thể timeout nếu nhiều ảnh)
3. **Xóa ảnh**: Sau khi quét xong, chọn ảnh và click "Xóa ảnh được chọn"

## Kiểm tra nơi nào?

Plugin kiểm tra ảnh có đang được sử dụng ở:

- Featured Image (`_thumbnail_id`)
- WooCommerce Product Gallery (`_product_image_gallery`)
- Post Meta (Elementor, ACF, builders)
- Post Content
- WordPress Options:
  - `web-info` (logo, gallery, gallery_gift_card, combination_logo)
  - `design-template` (gallery đệ quy)
  - `site_logo`, `custom_logo`
  - `theme_mods_*`
  - `elementor_pro_theme_builder_conditions`

## Log

Tất cả hoạt động được ghi vào error log với prefix `[MAC-UIS]`:
- Tiến trình quét
- Ảnh được tìm thấy ở đâu
- Ảnh đã xóa

## Yêu cầu

- WordPress 5.0+
- PHP 7.2+
- Quyền `manage_options`

## Tác giả

MAC USA One - https://macusaone.com

## Version

1.0.0

