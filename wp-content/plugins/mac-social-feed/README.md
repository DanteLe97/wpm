# Social Feed Aggregator Plugin

Plugin WordPress để tổng hợp feed từ Instagram, Facebook Page và Google My Business.

## Tính năng

- ✅ Kết nối Instagram qua OAuth (Basic Display API)
- ✅ Kết nối Facebook Page qua OAuth
- ✅ Kết nối Google My Business / Places API
- ✅ Tự động fetch posts hàng ngày qua cron job
- ✅ Tự động refresh Instagram token
- ✅ Shortcode để hiển thị feed: `[social_feed]`
- ✅ Admin panel với nút kiểm tra kết nối
- ✅ Manual fetch button

## Cài đặt

1. Upload thư mục `mac-social-feed` vào `/wp-content/plugins/`
2. Kích hoạt plugin trong WordPress Admin
3. Vào menu **Social Feed** để cấu hình

## Cấu hình

### Instagram

1. Tạo Instagram App tại [Facebook Developers](https://developers.facebook.com/)
2. Lấy **App ID** và **App Secret**
3. Điền vào form settings
4. Click **Connect Instagram** để kết nối qua OAuth
5. Hoặc điền **Access Token** thủ công

### Facebook

1. Tạo Facebook App tại [Facebook Developers](https://developers.facebook.com/)
2. Lấy **App ID** và **App Secret**
3. Điền vào form settings
4. Click **Connect Facebook Page** để kết nối qua OAuth
5. Hoặc điền **Page ID** và **Page Access Token** thủ công

### Google My Business

1. Tạo Google Cloud Project
2. Bật Places API
3. Tạo API Key
4. Lấy Place ID từ [Google Maps](https://www.google.com/maps)
5. Điền **API Key** và **Place ID** vào form

## Sử dụng Shortcode

```
[social_feed source="all" limit="5"]
```

**Tham số:**
- `source`: `all`, `instagram`, `facebook`, hoặc `gmb` (mặc định: `all`)
- `limit`: Số lượng items hiển thị (mặc định: `5`)
- `layout`: Layout hiển thị (mặc định: `grid`)

**Ví dụ:**
```
[social_feed source="instagram" limit="10"]
[social_feed source="facebook" limit="3"]
```

## Cron Job

Plugin tự động chạy cron job hàng ngày để:
- Refresh Instagram token (nếu cần)
- Validate Facebook token
- Fetch posts từ tất cả nguồn đã kết nối

## Cấu trúc File

```
mac-social-feed/
├── mac-social-feed.php         # File chính
├── assets/
│   ├── admin.js                # JavaScript cho admin
│   ├── admin.css                # CSS cho admin
│   └── frontend.css             # CSS cho frontend
├── readme.txt                   # WordPress readme
└── README.md                    # File này
```

## Lưu ý

- Plugin lưu dữ liệu vào Custom Post Type `social_feed_item`
- Mỗi item được lưu với meta: `_sfi_origin`, `_sfi_origin_id`, `_sfi_media`, `_sfi_permalink`, `_sfi_raw`
- Instagram token sẽ tự động refresh trước khi hết hạn 7 ngày

## Hỗ trợ

Nếu gặp vấn đề, kiểm tra:
1. App ID và Secret đã đúng chưa
2. Redirect URI đã được cấu hình trong Facebook/Instagram App
3. API Key và Place ID đã đúng cho Google My Business
4. Cron job đang hoạt động (có thể dùng plugin WP Crontrol để kiểm tra)

