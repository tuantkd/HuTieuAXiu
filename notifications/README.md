# Gửi email doanh thu hằng ngày

Chức năng nằm riêng trong `notifications/`, không sửa `summary.php`, luồng bán hàng, đăng nhập hay cấu trúc database. Số chính trong email là `$netRevenue`, được tính giống trang Tổng kết theo ngày: **tổng `orders.total_amount` − tổng `transactions.amount` có `type = 'expense'`**. Email còn có tổng bán hàng, khoản chi, số đơn và thời điểm cập nhật, bằng tiếng Việt, có cả HTML lẫn văn bản thuần.

Giờ mặc định là **23:00, UTC+7** (`Asia/Bangkok`, giống ứng dụng). Số liệu phản ánh dữ liệu lúc lập báo cáo; bán hàng hoặc sửa khoản chi sau giờ đó chưa nằm trong email. Đổi `send_time` theo giờ chốt sổ thực tế. Lịch gửi mặc định **tắt**; chỉ bật `enabled` khi đã điền địa chỉ nhận, mật khẩu SMTP và đặt cron trên hosting. Có thể gửi email thử bằng lệnh riêng mà không bật lịch hằng ngày.

**Trạng thái cấu hình riêng ngày 15/10/2026:** SPF và DKIM đã được công bố, người dùng đã xác nhận nhận được email thử trong Gmail. File `config.local.php` hiện bật `enabled = true`, giờ gửi `23:00` (UTC+7). Cần upload bộ mã lên hosting và thêm cron theo bước 4; cấu hình cục bộ không tự cài cron trên máy chủ.

## Cấu hình hosting đã điền

File riêng **`config.local.php`** đã được tạo theo thông tin cPanel bạn cung cấp:

| Khóa cấu hình | Giá trị |
| --- | --- |
| `smtp.host` | `sotayaxiu.site` |
| `smtp.port` | `465` |
| `smtp.encryption` | `ssl` |
| `smtp.username` | `_mainaccount@sotayaxiu.site` |
| `smtp.from_email` | `_mainaccount@sotayaxiu.site` |

Riêng tài khoản `_mainaccount@sotayaxiu.site`, thông tin bạn cung cấp ghi **Use your cPanel password**: điền mật khẩu cPanel vào `smtp.password` trong file riêng, hoặc đặt biến môi trường `AXIU_SMTP_PASSWORD` cho tiến trình cron. Không điền nguyên câu hướng dẫn này làm mật khẩu. Nếu tạo hộp thư riêng ở bước 1 bên dưới, dùng thông số và mật khẩu của hộp thư mới thay thế.

Thêm địa chỉ thật vào `recipients`, kiểm tra giờ gửi rồi đặt `enabled` thành `true` khi sẵn sàng. IMAP/POP3 là thông số nhận thư, không cần cho chức năng gửi báo cáo này. File `config.local.php` không đưa vào Git; khi triển khai bằng Git, tải riêng file này lên hosting. Giữ nguyên file riêng đã cấu hình, không sao chép file mẫu đè lên.

## 1. Tạo email gửi trên cPanel

1. Đăng nhập cPanel của hosting → **Email → Email Accounts → Create**.
2. Chọn tên miền, nhập tên hộp thư, ví dụ `baocao` để có `baocao@tenmiencuaban.vn`.
3. Đặt mật khẩu bằng **Generate** hoặc nhập mật khẩu đủ mạnh; lưu vào nơi quản lý mật khẩu. Chọn dung lượng hộp thư nếu hosting cho phép, rồi bấm **Create**.
4. Trở về **Email Accounts**, chọn **Connect Devices** ở hộp thư vừa tạo.
5. Trong **Mail Client Manual Settings → Secure SSL/TLS Settings (Recommended)**, ghi lại thông tin gửi thư. Dùng đúng hostname hiển thị; hostname này có thể là tên máy chủ hosting, không nhất thiết là `mail.tenmiencuaban.vn`.

| Thông tin cPanel | Khóa cấu hình PHP | Ví dụ minh họa |
| --- | --- | --- |
| Outgoing Server | `smtp.host` | `mail.tenmiencuaban.vn` hoặc hostname hosting |
| SMTP Port | `smtp.port` | `465` |
| SSL/TLS | `smtp.encryption` | `ssl` khi dùng port 465 |
| Username | `smtp.username` | `baocao@tenmiencuaban.vn` (địa chỉ đầy đủ) |
| Password | `smtp.password` | Mật khẩu hộp thư vừa tạo, không phải mật khẩu cPanel |
| Địa chỉ gửi | `smtp.from_email` | `baocao@tenmiencuaban.vn` |

Nếu hosting cung cấp **STARTTLS** trên cổng 587, dùng `port = 587`, `encryption = 'tls'`. Không tắt kiểm tra chứng chỉ SSL khi gặp lỗi; kiểm tra lại hostname trong Connect Devices hoặc nhờ hosting kiểm tra chứng chỉ.

Tài liệu cPanel chính thức: [tạo hộp thư](https://docs.cpanel.net/cpanel/email/create-an-email-account/), [lấy thông số mail client/SMTP](https://docs.cpanel.net/cpanel/email/set-up-mail-client/).

## 2. Điền cấu hình và danh sách nhiều người nhận

Nếu chưa có file riêng, sao chép `config.local.example.php` thành **`config.local.php`** trong cùng thư mục. Với file đã được tạo ở trên, sửa trực tiếp các thông tin còn thiếu trong `config.local.php`. Các khóa trong file này ghi đè cấu hình mặc định, không cần sửa file của ứng dụng cũ. Ví dụ:

```php
<?php
return [
    'enabled' => true,
    'send_time' => '23:00',
    'shop_name' => 'Hủ Tiếu A Xíu',
    'recipients' => [
        'chucuahang@example.com',
        'ketoan@example.com',
    ],
    'smtp' => [
        'host' => 'mail.tenmiencuaban.vn', // Thay bằng Outgoing Server thật.
        'port' => 465,
        'encryption' => 'ssl',
        'username' => 'baocao@tenmiencuaban.vn',
        'password' => 'DIEN_MAT_KHAU_HOP_THU_TAI_DAY',
        'from_email' => 'baocao@tenmiencuaban.vn',
        'from_name' => 'Sổ Tay A Xíu',
    ],
];
```

Thay toàn bộ địa chỉ ví dụ bằng địa chỉ thật. Có thể đặt mật khẩu qua biến môi trường `AXIU_SMTP_PASSWORD` như file mẫu nếu hosting hỗ trợ; biến đó phải có trong môi trường chạy cron. `config.local.php` đã được loại khỏi Git. Không gửi mật khẩu vào chat. Khi đổi mật khẩu hộp thư trong cPanel, cập nhật cấu hình này.

## 3. Cài thư viện và xem mẫu

PHP CLI cần phiên bản từ 7.4, các extension `mysqli`, `openssl` và quyền truy cập cùng database với website. Thư viện gửi SMTP là [PHPMailer](https://github.com/PHPMailer/PHPMailer), phiên bản được khóa trong `composer.lock`.

Tại thư mục gốc website, chạy:

```sh
composer install --working-dir=notifications --no-dev --no-interaction --prefer-dist --no-scripts --no-plugins
```

Nếu hosting không có Composer/Terminal, chạy lệnh trên ở máy tính rồi upload cả thư mục `notifications/`, bao gồm **`vendor/`**, các file ẩn `.htaccess` và file cấu hình riêng. Không upload dữ liệu thử nghiệm trong `runtime/` từ máy tính; giữ trạng thái gửi của hosting khi cập nhật các lần sau.

Xem mẫu có số liệu minh họa, không kết nối DB và không gửi email:

```sh
php notifications/send_daily_revenue.php --demo > notifications/preview.html
```

Mở `preview.html` trực tiếp trên máy tính. Trên hosting, thư mục này bị chặn truy cập web nên tải file về để xem. Xem email với số liệu thật, vẫn không gửi và không ghi trạng thái:

```sh
php notifications/send_daily_revenue.php --preview > /home/CPANEL_USER/daily-revenue-preview.html
```

Thay `CPANEL_USER` bằng tài khoản hosting. Lệnh `--preview` dùng `config/db.php` hiện có; không cần phiên đăng nhập trình duyệt.

## Gửi email thử ngay

Dùng file **`test_email.php`** trong Terminal của cPanel hoặc PHP CLI. Lệnh này gửi đúng một email có tiêu đề `[KIỂM TRA SMTP]`, không chứa doanh thu, không truy cập database và không ghi trạng thái gửi hằng ngày. Có thể chạy khi `enabled = false` và không cần chờ đến 23:00.

1. Điền `smtp.password` trong `config.local.php` trên hosting (hoặc đặt `AXIU_SMTP_PASSWORD`). Với `_mainaccount@sotayaxiu.site`, dùng mật khẩu cPanel theo thông tin đã cung cấp.
2. Kiểm tra cấu hình trước, thay `EMAIL_NHAN_THAT` bằng địa chỉ bạn nhận được thư:

```sh
php notifications/test_email.php --check --to=EMAIL_NHAN_THAT
```

Lệnh `--check` không kết nối máy chủ SMTP và không gửi thư. Nếu thiếu cấu hình, chương trình báo rõ và trả mã thoát `1`.

3. Gửi email thử thật:

```sh
php notifications/test_email.php --to=EMAIL_NHAN_THAT
```

Trên cPanel, nếu Terminal không đứng tại thư mục website, dùng đường dẫn tuyệt đối tương ứng:

```sh
/usr/local/bin/php /home/CPANEL_USER/public_html/notifications/test_email.php --to=EMAIL_NHAN_THAT
```

Thay đường dẫn PHP, `CPANEL_USER`, document root và địa chỉ nhận bằng giá trị thật. Chạy thủ công một lần; không đưa lệnh thử vào cron lặp lại. Thông báo **SMTP ĐÃ TIẾP NHẬN** chỉ xác nhận hosting nhận thư vào hàng đợi; chưa xác nhận Gmail đã nhận. Kết quả in Message-ID, mã giao dịch SMTP và lưu biên nhận trong `runtime/smtp-test-*.json` để đối chiếu trong **Email → Track Delivery → Result**. Biên nhận không chứa mật khẩu và không thay đổi trạng thái báo cáo ngày. Nếu SMTP từ chối ngay lúc gửi, chương trình trả mã thoát `1`.

Nếu Track Delivery báo **550-5.7.26**, xem [hướng dẫn sửa SPF/DKIM cho sotayaxiu.site](DNS_FIX.md). Không gửi lại liên tục khi bản ghi xác thực chưa được sửa.

## Gửi thử một lần sau 1 phút bằng Cron

Upload thêm **`test_cron.php`** vào thư mục `notifications/` trên hosting, cùng các file và thư viện đã có. File này dùng nội dung email thử, không truy cập database hay thay đổi lịch báo cáo doanh thu 23:00.

Thêm **một Cron Job kiểm tra riêng**, đặt cả 5 ô lịch thành `*`. Với website tại `/home/vkgaiiwp/public_html` và PHP CLI tại `/usr/local/bin/php`, Command là:

```sh
/usr/local/bin/php /home/vkgaiiwp/public_html/notifications/test_cron.php --to=tuanb1607138@gmail.com >> /home/vkgaiiwp/cron-test.log 2>&1
```

Điều chỉnh đường dẫn nếu document root hoặc PHP của hosting khác. Lần Cron đầu tiên ghi **ĐÃ HẸN** và thời điểm hẹn sau 60 giây vào `cron-test.log`. Lần Cron đến hạn gửi **đúng một email**, tiêu đề `[KIỂM TRA CRON 1 PHÚT]`, ghi mã giao dịch SMTP rồi các lần chạy sau bỏ qua. Không có tiến trình ngủ giữ kết nối PHP trong lúc chờ. Tính từ lúc bấm lưu Cron Job, cần khoảng 1–2 phút để Cron bắt đầu và chạy đến hạn.

Kiểm tra Hộp thư đến/Spam và `cron-test.log`, hoặc tra mã giao dịch trong Track Delivery. Khi kiểm tra xong, xóa Cron Job kiểm tra riêng; giữ Cron Job `send_daily_revenue.php` của báo cáo 23:00.

Xem trạng thái qua Terminal:

```sh
php notifications/test_cron.php --to=tuanb1607138@gmail.com --status
```

Muốn thử một lần nữa, chạy thủ công lệnh dưới đây để hẹn lại, rồi để Cron gọi lệnh thường khi đến hạn:

```sh
php notifications/test_cron.php --to=tuanb1607138@gmail.com --reset
```

**Không đặt `--reset` trong Command của Cron.** Nếu lần thử lỗi hoặc bị dừng lúc gửi, trạng thái chặn gửi lại; kiểm tra log, khắc phục rồi dùng `--reset` thủ công. Trạng thái riêng được lưu trong `runtime/cron-minute-test-*.json`, không dùng chung file trạng thái doanh thu ngày. Nếu đã chạy thử trên máy tính, chỉ upload mã `test_cron.php`, giữ nguyên `runtime/` của hosting.

## 4. Đặt lịch tự động trong cPanel

1. Xác định đường dẫn tuyệt đối của website, ví dụ `/home/CPANEL_USER/public_html`. Nếu website nằm trong thư mục con hoặc tên miền phụ, dùng đúng document root của website đó.
2. Xác nhận đường dẫn PHP CLI với hosting. Ví dụ bên dưới dùng `/usr/local/bin/php`; PHP dùng cho cron phải có các extension cần thiết và tương thích với website.
3. Vào **Advanced → Cron Jobs → Add New Cron Job**. Đặt các ô:

| Minute | Hour | Day | Month | Weekday |
| --- | --- | --- | --- | --- |
| `*` | `*` | `*` | `*` | `*` |

4. Điền **Command** (thay đường dẫn và username thực tế):

```sh
/usr/local/bin/php /home/CPANEL_USER/public_html/notifications/send_daily_revenue.php >> /home/CPANEL_USER/daily-revenue.log 2>&1
```

5. Bấm **Add New Cron Job**. Chương trình kiểm tra mỗi phút, chỉ gửi từ `send_time` trở đi theo giờ UTC+7. Mỗi người nhận đã được SMTP chấp nhận sẽ được bỏ qua trong các lần chạy còn lại của ngày. Cron chạy xuyên ngày giúp giờ hệ thống hosting khác múi giờ vẫn không làm lệch giờ gửi.

Không điền danh sách nhận báo cáo vào ô **Cron Email**: ô này dùng nhận đầu ra của cron; danh sách báo cáo nằm trong `recipients`. [Hướng dẫn Cron Jobs chính thức](https://docs.cpanel.net/cpanel/advanced/cron-jobs/).

Không cần mở trang Tổng kết hoặc giữ trình duyệt chạy. Máy chủ phải đang hoạt động vào giờ gửi. Nếu hosting giới hạn cron mỗi 5 phút, dùng `*/5` cho Minute và chọn `send_time` có phút chia hết cho 5, chừa thời gian thử lại trước nửa đêm. Lịch này gửi báo cáo ngày hiện tại; không tự gửi bù các ngày máy chủ ngừng hoạt động.

Trên XAMPP/Windows, có thể dùng **Task Scheduler** chạy `C:\xampp\php\php.exe` với đối số `"C:\xampp\htdocs\SoTayAXiu\notifications\send_daily_revenue.php"`, lặp mỗi phút, vô thời hạn; chọn chạy cả khi chưa đăng nhập Windows. Không dùng cron hosting và Task Scheduler cùng lúc để gửi cùng một danh sách.

## 5. Theo dõi và xử lý lỗi

- `runtime/` phải cho phép tài khoản chạy PHP cron ghi file. Giữ quyền tối thiểu cần thiết, không dùng `777`. Thư mục `notifications/` có `.htaccess` chặn truy cập web trên Apache/cPanel; giữ file này khi upload. Nếu dùng Nginx thuần, cấu hình web server từ chối URL `/notifications/`.
- Kiểm tra `/home/CPANEL_USER/daily-revenue.log` khi không nhận được thư. Lỗi SMTP hoặc database trả mã thoát `1`; lỗi truy vấn sẽ hủy gửi, không thay bằng doanh thu 0.
- Mỗi ngày có một file trạng thái `runtime/YYYY-MM-DD.json` chứa số liệu đã chốt và dấu gửi theo từng người nhận. Email lỗi được thử lại ở lần cron sau trong cùng ngày với cùng số liệu; email đã gửi không gửi lại. Các tiến trình chạy chồng nhau dùng khóa để tránh gửi trùng.
- Không xóa hoặc thay `runtime/` khi chương trình đang hoạt động: mất trạng thái có thể khiến gửi lại. Nếu thêm người nhận sau lần gửi đầu tiên trong ngày, người đó nhận cùng báo cáo đã chốt của ngày đó.
- SMTP xác nhận nhận thư chưa đảm bảo thư đến Inbox. Kiểm tra Spam, cấu hình xác thực tên miền trong hosting và hạn mức gửi khi cần. Nếu SMTP nhận thư rồi tiến trình bị dừng trước khi ghi trạng thái, lần thử lại vẫn có thể tạo thư trùng; Message-ID được giữ cố định cho cùng ngày/người nhận để hỗ trợ đối soát.
- Muốn tạm dừng lịch hằng ngày, đặt `enabled` thành `false`. Muốn gửi thử, dùng `test_email.php --to=EMAIL_NHAN_THAT` theo hướng dẫn ở trên; không cần thay danh sách nhận báo cáo hoặc giờ gửi.

Kiểm tra tự động bằng `php notifications/tests/run.php` (máy kiểm tra cần thêm `pdo_sqlite`). Bộ kiểm tra dùng database trong bộ nhớ và bộ gửi giả, không gửi thư thật, không sửa database của website.
