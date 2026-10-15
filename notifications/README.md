# Thông báo email

Module gửi báo cáo doanh thu lũy kế sau mỗi đơn hàng mới thành công và báo cáo doanh thu theo giờ hằng ngày. Cả hai dùng chung cấu hình SMTP và danh sách người nhận trong **Admin → Cấu hình email** (`admin/mail_settings.php`).

## Cấu hình trong admin

- Chỉ admin được thay cấu hình và xem trạng thái gửi.
- Nhập nhiều email, mỗi dòng một địa chỉ hoặc phân cách bằng dấu phẩy/chấm phẩy. Địa chỉ trùng được gộp; mỗi người nhận một thư riêng.
- Điền máy chủ SMTP, cổng, mã hóa, tài khoản và địa chỉ gửi theo thông tin của nhà cung cấp. Thường dùng SSL/TLS cổng 465 hoặc STARTTLS cổng 587.
- Để trống mật khẩu để giữ nguyên mật khẩu đã lưu.
- Bật/tắt riêng thông báo đơn mới và báo cáo doanh thu. Giờ hiển thị theo UTC+7 (`Asia/Bangkok`).

Cấu hình được đọc theo thứ tự ưu tiên: `config.php` → `config.local.php` → `runtime/mail-settings.json`. Khi đã lưu qua admin, chỉnh trên trang này thay vì sửa PHP. Bộ gửi dùng tên miền của địa chỉ From để định danh SMTP; envelope sender cùng địa chỉ From. DKIM do máy chủ SMTP ký, không được xác nhận chỉ bằng việc tồn tại một khóa công khai trong DNS.

## Xác thực tên miền để gửi đến Gmail

Xác thực SPF/DKIM/DMARC và cấu hình DNS đúng giúp Gmail nhận diện người gửi. Gmail vẫn đánh giá uy tín IP/tên miền và phản hồi người nhận; không có tùy chọn PHP bảo đảm mọi thư vào Inbox. Tham khảo [hướng dẫn Google](https://support.google.com/mail/answer/81126?hl=vi).

Với tên miền `sotayaxiu.site`, lần kiểm tra ngày 15/10/2026 ghi nhận:

- SMTP SSL cổng 465 tại `sotayaxiu.site`, địa chỉ gửi `_mainaccount@sotayaxiu.site`.
- SPF công khai có cho phép IP `103.15.222.22`.
- DKIM selector `default` có khóa RSA 2048 bit công khai.
- PTR của IP trên là `hosting222.lanit.com.vn`; hostname này phân giải về cùng IP.
- Sau khi chủ tên miền cập nhật DNS, đã xác nhận TXT `_dmarc.sotayaxiu.site` trả về `v=DMARC1; p=none;` từ DNS Namecheap, Google (`8.8.8.8`) và Cloudflare (`1.1.1.1`).
- Header của thư kiểm tra lúc 19:37 (UTC+7), do người nhận cung cấp, xác nhận Gmail đánh giá **SPF PASS, DKIM PASS, DMARC PASS**, kết nối TLS 1.3. Người nhận vẫn thấy thư trong Spam. Header này chưa xác định được nguyên nhân phân loại Spam; không cần đổi chính sách DMARC để sửa một lỗi xác thực không tồn tại.

Bộ gửi có lớp `NotificationMailer.php` giới hạn từng khối mã hóa header theo [RFC 2047](https://www.rfc-editor.org/rfc/rfc2047.html#section-2). Tiêu đề tiếng Việt dài được chia khối mà không cắt giữa ký tự UTF-8; nội dung hiển thị giữ nguyên. Đây là sửa lỗi định dạng email, không phải bảo đảm vượt bộ lọc Spam.

Thử lại sau sửa header lúc 19:53 ngày 15/10/2026: SMTP tiếp nhận thành công, các khối mã hóa dài tối đa 64 ký tự, nhưng người nhận vẫn xác nhận thư nằm trong Spam. Kết quả này chưa chứng minh nguyên nhân là uy tín IP/tên miền; cần dữ liệu từ Gmail/Postmaster hoặc nhà cung cấp SMTP để kết luận.

### Vấn đề hạ tầng đã xác minh

Tra cứu DNSBL ngày 15/10/2026 khoảng 19:56–19:57 (UTC+7) phát hiện IP gửi `103.15.222.22` nằm trong **Spamhaus SBL693406**: cả `22.222.15.103.zen.spamhaus.org` và `22.222.15.103.sbl.spamhaus.org` trả mã A `127.0.0.2`; TXT dẫn đến <https://check.spamhaus.org/sbl/query/SBL693406>. Truy vấn đối chứng dương/âm trả kết quả đúng. [Spamhaus xác nhận](https://docs.spamhaus.com/datasets/docs/source/10-data-type-documentation/datasets/040-zones.html) mã này là SBL, không phải mã lỗi truy vấn.

Đây là vấn đề cụ thể cần nhà cung cấp SMTP xử lý; chưa chứng minh Gmail dựa riêng vào listing này. [Google](https://support.google.com/mail/answer/81126?hl=vi) lưu ý thư từ IP nằm trong blocklist có khả năng bị đánh dấu Spam cao hơn. Người dùng muốn khắc phục phía gửi, không dùng bộ lọc Gmail. Cần LANIT khắc phục nguyên nhân rồi yêu cầu gỡ SBL, hoặc cung cấp SMTP relay phù hợp để cấu hình lại. Theo [quy trình SBL](https://www.spamhaus.org/faqs/spamhaus-blocklist/), đơn vị quản lý IP phải thực hiện bước xử lý/gỡ listing. Thay DMARC hoặc chỉ đổi A record website không khắc phục listing của IP SMTP.

Giữ nguyên cấu hình SMTP hiện tại đến khi có phương án xác minh được từ nhà cung cấp. Cần kiểm tra lại listing và email thực tế sau khi nhà cung cấp xử lý; kết quả DNSBL có thể thay đổi theo thời gian.

### Hosting nhận SMTP rồi hủy thư do ngưỡng gửi lỗi

Thư thử với số liệu mẫu ngày 15/10/2026 lúc 20:53 đã được SMTP tiếp nhận với mã `1xHLtf-00000009r34-394N`. Track Delivery do chủ tài khoản cung cấp xác nhận chính mã này có `Event: failure`, `Router: enforce_mail_permissions`, `Transport: fail`, với kết quả:

```text
Domain sotayaxiu.site has exceeded the max defers and failures per hour (5/5 (29%)) allowed. Message discarded.
```

Đây là lỗi hủy thư tại hosting trước khi chuyển tới Gmail. `5/5` nghĩa là số thư failed/deferred đã chạm ngưỡng kiểm tra; `29%` là tỷ lệ failed/deferred được báo tại thời điểm đó, không phải ngưỡng chỉ cho phép gửi 5 email/giờ. Theo [cPanel](https://docs.cpanel.net/knowledge-base/email/how-to-prevent-spam-with-mail-limiting-features/), bảo vệ áp dụng khi chạm cả ngưỡng số lỗi và ngưỡng tỷ lệ lỗi trong một giờ.

Chủ tài khoản tiếp tục cung cấp một kết quả trước đó:

```text
Domain sotayaxiu.site has exceeded the max emails per hour (13/10 (130%)) allowed. Message discarded.
```

Kết quả này xác nhận hạn mức gửi của tên miền là **10 email/giờ** và máy chủ đã đếm **13/10**. Cấu hình tại lần kiểm tra có 3 địa chỉ nhận; ứng dụng tạo 3 email riêng cho mỗi đơn. Vì vậy 4 đơn trong một giờ cần 12 email, vượt hạn mức 10 dù chưa tính báo cáo theo giờ, thư thử, thư khác và lần gửi lại. Hai ngưỡng (tổng số email và tỷ lệ/số failed/deferred) là các giới hạn khác nhau; cần LANIT đối chiếu Exim để kết luận trình tự toàn bộ lỗi. Chưa có bằng chứng quy kết tất cả các lần gửi lỗi cho SBL.

Không gửi thử liên tục khi đang bị hạn chế. Bước khắc phục nằm ở quản trị hosting/WHM: xử lý lỗi gốc và khôi phục khả năng gửi theo chính sách của nhà cung cấp. Bộ đếm được xét theo cửa sổ một giờ; chờ qua cửa sổ chỉ có thể hết hạn chế khi lỗi không tiếp tục tái diễn, không bảo đảm một mốc giờ mở khóa cụ thể. Quyền cPanel thông thường không có quyền sửa các giới hạn quản trị trong WHM. [Tài liệu cấu hình giới hạn](https://docs.cpanel.net/knowledge-base/email/how-to-set-email-send-limits/).

Để giữ yêu cầu gửi ngay sau từng đơn đến đầy đủ người nhận, nhà cung cấp cần cấp hạn mức phù hợp: tối thiểu số đơn dự kiến mỗi giờ × số email nhận, cộng lượng báo cáo theo giờ, thư khác và lần gửi lại. Xếp hàng hoặc gộp báo cáo để giảm tần suất sẽ thay đổi thời điểm nhận thư, cần chủ website xác nhận trước khi triển khai.

Nội dung có thể gửi cho hỗ trợ LANIT (bản nháp, chưa gửi tự động):

> Domain sotayaxiu.site, tài khoản cPanel vkgaiiwp, SMTP hosting222.lanit.com.vn / IP 103.15.222.22 đang nhận SMTP rồi hủy email. Track Delivery ghi "exceeded the max emails per hour (13/10 (130%)) allowed. Message discarded." Hạn mức 10 email/giờ không đủ cho tính năng báo cáo sau mỗi đơn đến 3 người nhận (4 đơn đã cần 12 email). Nhờ cấp hạn mức phù hợp với lưu lượng bán hàng và các báo cáo khác. Thư mẫu 1xHLtf-00000009r34-394N lúc 20:53 ngày 15/10/2026 còn bị router enforce_mail_permissions hủy với lỗi "exceeded the max defers and failures per hour (5/5 (29%)) allowed. Message discarded." Nhờ kiểm tra các lỗi/defer ban đầu trong Exim, khắc phục nguyên nhân và khôi phục gửi cho domain. Tra cứu DNSBL trong cùng phiên kiểm tra còn ghi nhận IP gửi nằm trong Spamhaus SBL693406; nhờ kiểm tra và xử lý listing hoặc cung cấp SMTP relay được hỗ trợ. Xin xác nhận khi gửi ra Gmail đã hoạt động để tôi thử lại một email. Không đề nghị tắt bảo vệ failed/deferred khi chưa xử lý nguyên nhân.

Ứng dụng chỉ xác nhận SMTP đã tiếp nhận; không tự biết thư bị hủy sau đó. Thư đã `Message discarded` không tự được chuyển tiếp khi hết hạn chế. Đối chiếu Track Delivery rồi xác định thư cần gửi lại sau khi khôi phục; không xóa trạng thái gửi hoặc đặt lại toàn bộ thư thành chờ gửi vì có thể gửi trùng các thư khác.

Nameserver hiện đặt ở Namecheap (`dns1.registrar-servers.com`, `dns2.registrar-servers.com`). Để bổ sung DMARC, mở **Domain List → Manage → Advanced DNS → Host Records → Add New Record**:

| Trường | Giá trị khởi đầu |
| --- | --- |
| Type | TXT Record |
| Host | `_dmarc` |
| Value | `v=DMARC1; p=none; adkim=r; aspf=r` |
| TTL | Automatic |

Lưu bản ghi. Host chỉ nhập `_dmarc`, không kèm tên miền; tên đầy đủ sẽ là `_dmarc.sotayaxiu.site`. Chỉ tạo một bản ghi DMARC. Nếu bản ghi đã tồn tại, xem chính sách hiện tại trước khi chỉnh; không tự thay chính sách đang áp dụng bằng bản mẫu. `p=none` là chính sách khởi đầu không yêu cầu máy nhận cách ly/từ chối thư không xác thực. Bản ghi hiện tại `v=DMARC1; p=none;` vẫn hợp lệ: `adkim` và `aspf` mặc định là `r` khi không ghi rõ, theo [RFC 7489](https://www.rfc-editor.org/rfc/rfc7489.html#section-6.3). Chưa cấu hình địa chỉ nhận báo cáo DMARC vì chưa xác minh hộp thư dành cho báo cáo.

SPF và DKIM đang tồn tại không cần tạo lại chỉ để thêm DMARC. Bản ghi trên phải lưu tại Namecheap; sửa file PHP hoặc DNS không có thẩm quyền trên hosting không tự xuất bản bản ghi. [Hướng dẫn Namecheap](https://www.namecheap.com/support/knowledgebase/article.aspx/317/2237/how-do-i-add-txtspfdkimdmarc-records-for-my-domain/).

Sau khi DNS cập nhật, kiểm tra **một email mới** trong Gmail: mở email → menu ba chấm của email → **Hiển thị thư gốc / Show original**. Xem SPF, DKIM và DMARC có PASS, và phần `Authentication-Results`. DNS tồn tại chưa chứng minh máy chủ đã ký đúng hoặc Gmail đã xác thực thư. Nếu thư hợp lệ nằm trong Spam, người nhận có thể chọn **Không phải thư rác**; thao tác này không thay việc cấu hình xác thực tên miền.

Nếu cả ba xác thực đã PASS nhưng thông báo hợp lệ vẫn vào Spam, có thể cấu hình riêng hộp thư Gmail nhận thông báo: mở tùy chọn tìm kiếm → trường **Từ** nhập chính xác `_mainaccount@sotayaxiu.site` → **Tạo bộ lọc** → chọn **Không bao giờ gửi vào thư rác** → **Tạo bộ lọc**. Thực hiện trong từng tài khoản Gmail muốn nhận thông báo. Với thư đã ở Spam, dùng **Không phải thư rác** để chuyển ra. Bộ lọc chỉ áp dụng cho tài khoản người nhận đã cấu hình, không phải thay đổi uy tín toàn bộ tên miền. [Hướng dẫn chính thức của Google](https://support.google.com/mail/answer/16457426?co=GENIE.Platform%3DDesktop&hl=vi).

## Gửi báo cáo doanh thu sau mỗi đơn mới

`cart.php` gọi `order_hook.php` sau khi ghi xong các món; `admin/pos.php` gọi sau COMMIT thành công. Bộ gửi chạy khi request kết thúc, sau phản hồi chuyển trang nếu máy chủ hỗ trợ. Không cần cron cho lần gửi đầu, kể cả trên XAMPP. Lỗi email không hủy đơn đã lưu.

Chỉ gửi đơn thuộc ngày hiện tại, tạo từ lúc bật tính năng. Tắt rồi bật lại sẽ lấy mốc mới. Tổng các dòng món phải khớp tổng tiền đơn. Admin hiển thị lần xử lý gần nhất và số thư theo trạng thái.

Sau mỗi đơn thành công, email hiển thị:

- Tổng tiền của **tất cả đơn hôm nay** và số đơn, bao gồm cả đơn có trước thời điểm bật tính năng.
- Tổng chi phí hôm nay: chỉ tính `transactions.type = 'expense'` theo `transaction_date` của ngày hiện tại.
- Số tiền còn lại = tổng tiền các đơn hôm nay − tổng chi phí hôm nay. Nếu chi phí lớn hơn tiền bán hàng, hiển thị số âm.
- Mã, thời gian, tổng tiền, phương thức thanh toán, người bán, ghi chú của đơn kích hoạt báo cáo; thời điểm cập nhật số liệu.

`fetchOrderRevenueNotifications()` gọi lại truy vấn tổng hợp `fetchReport()` cho từng lần xử lý đơn mới, cùng công thức với trang Tổng kết, không dùng bản báo cáo theo giờ đã chốt trước đó. Ví dụ: hôm nay bán 100.000đ, chi 20.000đ thì thư đầu báo còn 80.000đ; thêm đơn 50.000đ thì thư sau báo tổng bán 150.000đ, còn 130.000đ.

Báo cáo được lưu cùng trạng thái của từng đơn trong `runtime/orders-YYYY-MM-DD.json`. Tất cả người nhận và các lần gửi lại của cùng đơn dùng chung bản số liệu đã lưu; đơn tiếp theo có bản tổng hợp mới. Nếu cron xử lý nhiều đơn tồn cùng một lượt, các đơn đó dùng số liệu tại lượt xử lý đó. Trạng thái cũ chưa có báo cáo sẽ được bổ sung trong lần thử lại; thư đã gửi thành công không tự gửi lại sau nâng cấp. Thay đổi khoản chi riêng lẻ không tự kích hoạt email, số liệu được cập nhật ở đơn mới tiếp theo.

Có thể thêm Cron Job mỗi phút (`* * * * *`) để thử lại thư lỗi trong cùng ngày hoặc xử lý phần còn lại khi một đơn có trên 50 người nhận:

```sh
/usr/local/bin/php /home/CPANEL_USER/public_html/notifications/send_order_notifications.php >> /home/CPANEL_USER/order-notifications.log 2>&1
```

Thay đường dẫn theo hosting thực tế. Tác vụ quét chờ đơn được tạo ít nhất 5 giây và xử lý tối đa 50 lần gửi mỗi lượt. Móc sau lưu và cron dùng chung trạng thái, bỏ qua người đã gửi, không gửi bù ngày cũ.

## Báo cáo doanh thu hằng ngày

Báo cáo tính **tổng `orders.total_amount` − tổng chi `transactions.amount` có `type = 'expense'`**, cùng phạm vi ngày và công thức của `summary.php`. Email gồm tổng bán hàng, tổng chi, doanh thu sau chi phí, số đơn và thời điểm cập nhật. Dữ liệu được chốt ở lần lập báo cáo đầu tiên trong ngày.

Báo cáo theo giờ cần Cron Job mỗi phút (`* * * * *`); tác vụ chỉ gửi từ giờ đã cấu hình trở đi, mặc định 23:00 UTC+7:

```sh
/usr/local/bin/php /home/CPANEL_USER/public_html/notifications/send_daily_revenue.php >> /home/CPANEL_USER/daily-revenue.log 2>&1
```

Trên Windows, dùng Task Scheduler chạy `C:\xampp\php\php.exe`, đối số là đường dẫn tuyệt đối đến script tương ứng, lặp mỗi phút. Không cần mở trang Tổng kết hoặc giữ trình duyệt chạy.

## Triển khai

Giữ các file cấu hình, `mail_settings.php`, `NotificationMailer.php`, `orders.php`, `order_hook.php`, `send_order_notifications.php`, `revenue.php`, `template.php`, `send_daily_revenue.php`, `vendor/`, `composer.json`, `composer.lock`, `.htaccess`, `.gitignore` và `runtime/`. `tests/` phục vụ kiểm tra, không cần upload lên hosting.

Nếu chưa có thư viện:

```sh
composer install --working-dir=notifications --no-dev --no-interaction --prefer-dist --no-scripts --no-plugins
```

PHP cần từ 7.4, có `mysqli` và `openssl`. Upload cả module cùng `cart.php`, `admin/pos.php`, `admin/mail_settings.php` và menu admin đã cập nhật. Giữ cấu hình riêng và trạng thái `runtime/` trên hosting, không ghi đè bằng dữ liệu máy phát triển. Cấp quyền ghi `runtime/` cho PHP web và PHP cron. Giữ `.htaccess` chặn truy cập web trên Apache; với Nginx, cấu hình từ chối truy cập `/notifications/`.

## Theo dõi và kiểm tra

- `runtime/order-checkout.log`: kết quả gửi sau lưu đơn.
- `runtime/orders-YYYY-MM-DD.json`: trạng thái từng đơn/người nhận; `order-notifications.lock` ngăn gửi chồng nhau.
- `runtime/YYYY-MM-DD.json`: số liệu và trạng thái báo cáo doanh thu.
- `runtime/mail-settings.json`: cấu hình SMTP riêng; không đưa vào Git hoặc chia sẻ công khai.

Không xóa trạng thái gửi để thử lại vì có thể gửi trùng. Trạng thái `sending` bị gián đoạn chuyển thành `uncertain`; đối chiếu nhật ký và Track Delivery trước khi xử lý. SMTP tiếp nhận chưa bảo đảm thư đã vào Inbox.

Xem mẫu HTML, không kết nối database hoặc gửi email:

```sh
php notifications/send_order_notifications.php --demo
php notifications/send_daily_revenue.php --demo
```

Chạy kiểm tra tự động (cần `pdo_sqlite`):

```sh
php notifications/tests/run.php
php notifications/tests/mail_features.php
```

Các kiểm tra dùng database trong bộ nhớ và bộ gửi giả, không gửi thư thật hoặc sửa database website.
