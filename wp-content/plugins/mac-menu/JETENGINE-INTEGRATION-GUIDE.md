# Hướng Dẫn Tích Hợp Mac Menu với JetEngine Query Builder

## Tổng Quan

Tích hợp này cho phép bạn sử dụng Mac Menu Categories như một nguồn dữ liệu trong JetEngine Query Builder, đặc biệt hữu ích cho JetTabs và các widget khác.

## Các File Đã Tạo

```
mac-menu/
├── includes/
│   ├── jet-engine-integration.php          # File chính đăng ký integration
│   └── jet-engine/
│       ├── editor-mac-menu.php             # Editor class (Admin UI)
│       ├── editor-mac-menu.html            # Vue template (Admin UI)
│       └── query-mac-menu.php              # Query class (Logic xử lý)
└── mac-menu.php                            # File chính (đã thêm include)
```

## Cách Sử Dụng

### Bước 1: Tạo Query trong JetEngine Query Builder

1. Vào **JetEngine → Query Builder**
2. Click **Add New Query**
3. Đặt tên cho Query (ví dụ: `mac-menu-parents`)
4. Trong dropdown **Query Type**, chọn **Mac Menu Categories**
5. Cấu hình query:

#### Các Options Quan Trọng:

**Chỉ lấy Categories Cha (Parents Only):**
- Bật option này để chỉ lấy categories có `parents_category = 0`
- Dùng cho menu chính, tabs chính

**Lấy Children của Parent ID:**
- Nhập ID của parent category để lấy các categories con
- Để trống để lấy tất cả

**Sắp xếp theo:**
- `order`: Thứ tự mặc định (được set trong Mac Menu)
- `id`: ID của category
- `category_name`: Tên category

**Giới hạn số lượng (Limit):**
- Số lượng items tối đa
- Để trống = không giới hạn

6. Click **Save Query**

### Bước 2: Sử Dụng Query với JetTabs

#### Option 1: Sử Dụng JetEngine Query Gateway (Khuyến nghị)

1. Thêm **JetTabs** widget vào trang
2. Trong phần **Items**, bật **Use JetEngine Query**
3. Chọn Query vừa tạo (ví dụ: `mac-menu-parents`)
4. Cấu hình Tab Item:

**Tab Label:**
- Click vào icon Dynamic Tags (⚡)
- Chọn **Mac Menu → Category Name**
- ✅ Bật **Current Category** option

**Tab Content:**
- Click vào icon Dynamic Tags (⚡)
- Chọn **Mac Menu → Category Content**
- ✅ Bật **Current Category** option

5. Save và xem kết quả!

#### Option 2: Sử Dụng Manual với Dynamic Tags

1. Thêm **JetTabs** widget
2. Thêm các tabs thủ công
3. Trong mỗi tab:
   - **Label**: Sử dụng Dynamic Tag `mac-menu-name` với **Current Category** ON
   - **Content**: Sử dụng Dynamic Tag `mac-menu-content` với **Current Category** ON

### Bước 3: Sử Dụng với JetListing Grid

1. Tạo **Listing Item** mới trong **JetEngine → Listings**
2. Thiết kế template cho 1 category
3. Sử dụng Dynamic Tags của Mac Menu:
   - `{jet-engine:mac-menu-name}` - Tên category
   - `{jet-engine:mac-menu-content}` - Nội dung category
   - `{jet-engine:mac-menu-price}` - Giá category

4. Thêm **Listing Grid** widget vào trang
5. Chọn:
   - **Listing**: Template vừa tạo
   - **Use Query**: Bật
   - **Query**: Chọn query Mac Menu

## Các Trường Hợp Sử Dụng Phổ Biến

### 1. Tabs cho Menu Chính (Parents Only)

**Query Settings:**
```
Query Type: Mac Menu Categories
Parents Only: ✅ ON
Order By: order
Order: ASC
Limit: 10
```

**JetTabs:**
- Label: `{mac-menu-name}` (Current Category: ON)
- Content: `{mac-menu-content}` (Current Category: ON)

### 2. Tabs cho Sub-Categories

**Query Settings:**
```
Query Type: Mac Menu Categories
Parents Only: ❌ OFF
Parent ID: 5  (ID của parent category)
Order By: order
Order: ASC
```

### 3. Grid Hiển Thị Tất Cả Categories

**Query Settings:**
```
Query Type: Mac Menu Categories
Parents Only: ❌ OFF
Order By: category_name
Order: ASC
```

**JetListing:**
- Thiết kế template với card layout
- Sử dụng Dynamic Tags để hiển thị thông tin

## Cấu Trúc Dữ Liệu Trả Về

Mỗi item trong query có các trường sau:

```php
stdClass Object (
    [id] => 1
    [category_name] => "Appetizers"
    [slug_category] => "appetizers"
    [category_description] => "Start your meal..."
    [price] => "8.99"
    [featured_img] => "http://..."
    [parents_category] => "0"
    [order] => 1
    [group_repeater] => Array (...)
    [is_table] => 0
    [is_hidden] => 0
    [data_table] => Array (...)
    [category_inside] => 1
    [category_inside_order] => "new"
)
```

## Dynamic Tags Hoạt Động

Khi sử dụng Query với JetEngine Query Gateway, các Dynamic Tags sau sẽ tự động nhận context:

- `mac-menu-dynamic-tag-name.php` → Tên category (+ giá nếu có)
- `mac-menu-dynamic-tag-content.php` → Nội dung đầy đủ category
- `mac-menu-dynamic-tag-price.php` → Giá category
- Và tất cả các dynamic tags khác của Mac Menu

**Điều kiện:** Phải bật **Current Category** option trong settings của Dynamic Tag.

## Troubleshooting

### Query không hiển thị categories

**Kiểm tra:**
1. Đảm bảo Mac Menu có categories với `is_hidden = 0`
2. Kiểm tra filter `parents_only` có đúng không
3. Xem Preview trong Query Builder

### Dynamic Tags không hoạt động

**Kiểm tra:**
1. ✅ Bật **Current Category** option trong Dynamic Tag settings
2. ✅ Sử dụng query qua **JetEngine Query Gateway** trong widget
3. Đảm bảo JetEngine đã cập nhật phiên bản mới nhất

### Tabs không tạo tự động

**Giải pháp:**
1. Trong JetTabs widget settings
2. Phần **Items** → Bật **Use JetEngine Query**
3. Chọn query Mac Menu
4. JetTabs sẽ tự động tạo tabs dựa trên query results

## Code Reference

### Hook vào Query Gateway

File `includes/jet-engine-integration.php` đã tự động hook vào:

```php
add_action( 'jet-engine-query-gateway/do-item', array( $this, 'set_current_category_context' ) );
```

Hook này set `$custom_array` cho mỗi category trong loop, giúp Dynamic Tags nhận được context đúng.

### Extend Query

Nếu muốn thêm filter hoặc logic tùy chỉnh, edit file:

```
mac-menu/includes/jet-engine/query-mac-menu.php
```

Trong method `_get_items()` và `get_items_total_count()`.

## Ví Dụ Thực Tế

### Menu Restaurant với Tabs

1. Query: Lấy tất cả parent categories (Appetizers, Main Course, Desserts...)
2. JetTabs: 
   - Mỗi tab = 1 category
   - Label = Tên category
   - Content = Danh sách món ăn trong category đó

### Grid Categories

1. Query: Lấy tất cả categories
2. JetListing Grid:
   - Mỗi card = 1 category
   - Hiển thị: Hình ảnh, tên, mô tả
   - Click vào card → View chi tiết category

## Kết Luận

Với tích hợp này, bạn có thể:
- ✅ Sử dụng Mac Menu làm nguồn dữ liệu cho JetEngine
- ✅ Tự động tạo tabs dựa trên categories
- ✅ Dynamic Tags hoạt động hoàn hảo với "Current Category"
- ✅ Linh hoạt trong việc filter, sort, limit data

Chúc bạn thành công! 🎉

