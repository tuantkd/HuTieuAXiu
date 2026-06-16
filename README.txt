HƯỚNG DẪN CHẠY SỔ BÁN HÀNG A XÍU TRÊN XAMPP

1. Giải nén thư mục so-ban-hang-a-xiu-full vào:
   C:\xampp\htdocs\so-ban-hang-a-xiu-full

2. Mở XAMPP Control Panel, bật Apache và MySQL.

3. Mở trình duyệt vào:
   http://localhost/phpmyadmin

4. Chọn tab Import, chọn file database.sql trong thư mục source, bấm Go.
   File SQL sẽ tự tạo database: so_ban_hang_a_xiu

5. Mở app:
   http://localhost/so-ban-hang-a-xiu-full/

TÀI KHOẢN DATABASE MẶC ĐỊNH
- Host: localhost
- User: root
- Password: rỗng
- Database: so_ban_hang_a_xiu

Nếu XAMPP của bạn có mật khẩu MySQL, sửa file:
config/db.php

TÍNH NĂNG CÓ SẴN
- Màn hình bán nhanh trên điện thoại
- Menu món ăn và nước uống
- Thêm món vào đơn
- Chọn ăn tại quán hoặc mang đi
- Lưu đơn hàng
- Lịch sử bán hàng theo ngày
- Xem chi tiết từng đơn
- Tổng kết ngày / tuần / tháng
- Quản lý món: thêm, sửa, ẩn món
- Giao diện cam nhạt + đỏ tươi phù hợp quán

TRUY CẬP ADMIN
- Mở: http://localhost/so-ban-hang-a-xiu-full/admin/login.php
- Tài khoản: admin / Admin@123
- Nhân viên: staff / Admin@123

