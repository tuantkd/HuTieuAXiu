# POS PHP thuần + MySQL trong thư mục `admin`

## Luồng database chuẩn

- `database.sql` ở thư mục gốc là nguồn sự thật duy nhất của database.
- `admin/setup.sql` không còn tạo schema riêng; file này chỉ hỗ trợ cập nhật database cũ để tương thích với khu vực `admin`.
- Cả POS/public gốc và khu vực `admin` dùng chung một database `so_ban_hang_a_xiu`.

## Tính năng đã đồng bộ

- Đăng nhập bằng `password_hash` / `password_verify`.
- Role DB chuẩn là `admin` và `staff`.
- Username mẫu là `admin` và `nhanvien`.
- Session lưu `user_id`, `username`, `full_name`, `role`.
- Nhân viên chỉ dùng được `admin/pos.php`.
- Nếu nhân viên mở link admin như `dashboard.php`, `products.php`, `orders.php`, `transactions.php`, `report.php`, `settings.php`, `users.php` thì sẽ bị chuyển về `pos.php`.
- Admin xem và quản lý toàn bộ màn hình.
- POS admin lưu đơn theo schema gốc:
  - `orders.order_code`, `orders.order_type`, `orders.total_amount`, `orders.note`, `orders.user_id`
  - `order_items.product_name`, `order_items.price`, `order_items.quantity`, `order_items.subtotal`
- Quản lý món trong admin dùng `products.category_id` và bảng `categories`.
- Cài đặt admin map trực tiếp vào `settings.shop_name`, `settings.phone`, `settings.address`, `settings.open_hours`, `settings.logo_url`.
- Trang **Cấu hình email** (`mail_settings.php`) chỉ dành cho admin: quản lý SMTP, nhiều địa chỉ nhận, bật/tắt thông báo đơn mới và báo cáo doanh thu; hiển thị trạng thái gửi trong ngày. Ô mật khẩu để trống sẽ giữ nguyên. Cấu hình lưu riêng trong `notifications/runtime/mail-settings.json`, không đổi schema hay cách tính tiền. Thông báo đơn mới tự chạy sau khi lưu đơn thành công, không cần cron cho lần gửi đầu; cron dùng thử lại khi lỗi và gửi báo cáo theo giờ, theo [hướng dẫn email](../notifications/README.md).

## Cách chạy trên XAMPP

1. Mở XAMPP và chạy `Apache` + `MySQL`.
2. Tạo database `so_ban_hang_a_xiu`.
3. Import file `database.sql`.
4. Nếu database cũ từng chạy theo schema admin riêng, import thêm `admin/setup.sql`.
5. Mở trình duyệt:
   `http://localhost/so-ban-hang-a-xiu-full/admin/login.php`

## Tài khoản mẫu

- `admin / 123456`
- `nhanvien / 123456`

## Nếu muốn tạo lại tài khoản mẫu

Chạy:

`http://localhost/so-ban-hang-a-xiu-full/admin/create_users.php`

Script này sẽ:

- chuyển mọi role `nhanvien` cũ về `staff`
- tạo hoặc cập nhật:
  - `admin / 123456`
  - `nhanvien / 123456`

## Kiểm tra nhanh

1. Đăng nhập bằng `admin`:
   - xem được tất cả menu
   - thêm món theo `categories`
   - xem đơn hàng, báo cáo, cài đặt
2. Đăng nhập bằng `nhanvien`:
   - chỉ thấy POS + đổi mật khẩu + đăng xuất
   - mở trực tiếp `admin/dashboard.php` sẽ bị đẩy về `admin/pos.php`
3. Tạo vài đơn ở POS:
   - chọn món, tăng giảm số lượng, chọn loại đơn, nhập ghi chú
   - bấm `Lưu đơn`
   - kiểm tra đơn được lưu với `order_code` và danh sách “đơn hôm nay” chỉ hiện đúng quyền
