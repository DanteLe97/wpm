=== Role URL Dashboard ===
Contributors: yourname
Tags: roles, users, dashboard, admin, links, permissions
Requires at least: 5.8
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Quản lý URL admin được phép truy cập theo Role/User với UI đơn giản dành cho người lớn tuổi.

== Description ==

Plugin Role URL Dashboard cho phép quản trị viên tạo và quản lý các liên kết admin được phép truy cập theo Role hoặc User cụ thể. Plugin cung cấp giao diện đơn giản, dễ sử dụng với các card/tile lớn, phù hợp cho người lớn tuổi.

= Tính năng chính: =

* Tạo mapping URL admin theo Role hoặc User
* Giao diện admin với card layout dễ sử dụng
* User Dashboard hiển thị grid tiles responsive (3 cột desktop, 1 cột mobile)
* Hỗ trợ 3 chế độ mở link: same tab, new tab, iframe
* Validation URL đầy đủ (chỉ chấp nhận admin URLs)
* Caching để tối ưu hiệu suất
* Security: nonce checks, capability checks, URL sanitization
* Accessibility: keyboard navigation, ARIA labels, high contrast support

= Cài đặt =

1. Upload plugin vào thư mục `/wp-content/plugins/`
2. Kích hoạt plugin qua menu 'Plugins' trong WordPress
3. Plugin sẽ tự động tạo database table và thêm capability `manage_role_dashboards` cho administrator
4. Truy cập menu "Role Links" để bắt đầu tạo mappings

= Sử dụng =

1. **Tạo Link mới:**
   - Vào "Role Links" > "Add New"
   - Chọn Type: Role hoặc User
   - Nhập Label, URL admin (relative path), Description, Icon (emoji)
   - Chọn Open Behavior và Priority
   - Lưu

2. **Quản lý Links:**
   - Xem danh sách tất cả links trong "All Links"
   - Sử dụng filter để tìm kiếm
   - Bulk actions: Activate, Deactivate, Delete

3. **User Dashboard:**
   - Users sẽ thấy menu "My Dashboard" sau khi đăng nhập
   - Dashboard hiển thị các tiles cho các links họ được phép truy cập
   - Click vào tile để mở link theo cấu hình

4. **Settings:**
   - Cấu hình vị trí dashboard
   - Bật/tắt iframe support
   - Điều chỉnh cache TTL

= Security =

* Tất cả forms sử dụng nonce verification
* Capability checks cho tất cả admin actions
* URL validation: chỉ chấp nhận relative admin paths
* Sanitization và escaping đầy đủ
* Không cho phép external URLs

= Database =

Plugin tạo table `wp_role_url_map` với các trường:
- id, entity_type, entity, url, label, open_behavior
- icon, description, priority, active, meta
- created_at, updated_at

= Hỗ trợ =

Nếu gặp vấn đề, vui lòng kiểm tra:
1. PHP version >= 7.4
2. WordPress version >= 5.8
3. Quyền truy cập database
4. Capability `manage_role_dashboards` đã được gán

== Installation ==

1. Upload plugin vào `/wp-content/plugins/mac-role/`
2. Kích hoạt plugin
3. Bắt đầu tạo links trong menu "Role Links"

== Frequently Asked Questions ==

= Tôi có thể sử dụng external URLs không? =

Không, plugin chỉ chấp nhận relative admin URLs để đảm bảo security.

= Làm thế nào để xóa tất cả data khi gỡ plugin? =

Mặc định, plugin không xóa table khi uninstall. Để xóa data, uncomment dòng trong `uninstall.php`.

= Plugin có hỗ trợ multisite không? =

Hiện tại plugin được thiết kế cho single-site. Để hỗ trợ multisite, cần thêm `blog_id` column vào table.

== Changelog ==

= 1.0.0 =
* Initial release
* Core functionality: CRUD mappings, admin UI, user dashboard
* Security & validation
* Caching support
* Accessibility features

== Upgrade Notice ==

= 1.0.0 =
Initial release. Cài đặt và kích hoạt plugin để bắt đầu sử dụng.

