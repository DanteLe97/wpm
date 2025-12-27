# Hướng dẫn Setup Plugin MAC Interactive Tutorials

## Setup Web Gốc (Source Site)

**Để plugin hoạt động như web gốc, bạn cần:**

1. **Mở file:** `wp-content/plugins/mac-interactive-tutorials/mac-interactive-tutorials.php`

2. **Tìm dòng ~25-27** và sửa thành:
   ```php
   if (!defined('MAC_TUTORIALS_IS_SOURCE')) {
       define('MAC_TUTORIALS_IS_SOURCE', true); // Set = true để làm web gốc
   }
   ```

3. **Sau khi activate, bạn sẽ thấy:**
   - Menu "Tutorial Sites" trong admin (để quản lý web con)
   - Có thể tạo tutorials trên posts/pages như bình thường

## Setup Web Con (Client Site)

**Để plugin hoạt động như web con, bạn cần:**

1. **Mở file:** `wp-content/plugins/mac-interactive-tutorials/mac-interactive-tutorials.php`

2. **Tìm dòng ~25-27** và đảm bảo:
   ```php
   if (!defined('MAC_TUTORIALS_IS_SOURCE')) {
       define('MAC_TUTORIALS_IS_SOURCE', false); // Set = false hoặc không define
   }
   ```
   (Đây là giá trị mặc định, không cần sửa nếu muốn làm web con)

3. **Sau khi activate, bạn sẽ thấy:**
   - Menu "Tutorials" (CPT) trong admin
   - Submenu "Sync Tutorials" để sync từ web gốc

## Cách phân biệt Web Gốc vs Web Con

Plugin tự động detect dựa vào constant `MAC_TUTORIALS_IS_SOURCE`:

```php
// Web gốc: MAC_TUTORIALS_IS_SOURCE = true
define('MAC_TUTORIALS_IS_SOURCE', true);

// Web con: MAC_TUTORIALS_IS_SOURCE = false hoặc không define
define('MAC_TUTORIALS_IS_SOURCE', false);
```

## Hardcode URL (Web Con)

URL web gốc đã được hardcode trong file:
- `wp-content/plugins/mac-interactive-tutorials/admin/class-sync-admin.php`
- Dòng 15: `private $default_source_url = 'https://note.macmarketing.us';`

Nếu muốn thay đổi, sửa trực tiếp trong file này.
