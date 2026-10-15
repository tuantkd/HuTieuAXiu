# Gmail từ chối: 550-5.7.26

Log Track Delivery bạn cung cấp cho biết Gmail đã từ chối giao dịch `1xHEOT-000000004T3-0uib`: **SPF không đạt với IP 103.15.222.22; DKIM không đạt**. Đây là kết quả trả về sau khi hosting tiếp nhận thư, nên kết nối SMTP và mật khẩu đúng vẫn chưa đủ để giao thư đến Gmail. [Yêu cầu xác thực của Gmail](https://support.google.com/mail/answer/81126#authentication).

DNS kiểm tra ngày 15/10/2026 cho thấy domain dùng `dns1.registrar-servers.com` và `dns2.registrar-servers.com` (Namecheap BasicDNS). Trước khi sửa, SPF được công bố là:

```text
v=spf1 include:spf.efwd.registrar-servers.com ~all
```

## Tiến độ sau khi sửa SPF

Đã kiểm tra trực tiếp DNS Namecheap và Google DNS: cả hai đã trả về bản ghi mới duy nhất:

```text
v=spf1 ip4:103.15.222.22 include:spf.efwd.registrar-servers.com ~all
```

Đã gửi lại một email thử đến `tuanb1607138@gmail.com`, mã giao dịch **`1xHEhl-00000000SsU-3lqp`**. Người dùng đã cung cấp đúng Result của giao dịch này: **Gmail từ chối với 550-5.7.26, SPF và DKIM không đạt tại thời điểm gửi**. Biên nhận trong `runtime/` được cập nhật để ghi nhận kết quả từ chối này.

Sau đó đã đối chiếu cả hai DNS Namecheap, Google DNS và Cloudflare DNS: tất cả đều trả về một SPF mới đúng như trên, TTL 1800 giây (30 phút). Gmail có thể còn dùng kết quả DNS lưu trước khi sửa, nhưng chưa có bằng chứng để kết luận đó là nguyên nhân. Không coi việc DNS công bố bản ghi mới là bằng chứng Gmail đã nhận thư.

Trước khi sửa DKIM, tên `default._domainkey.sotayaxiu.site` chưa có trên DNS Namecheap. Sau khi người dùng thêm bản ghi, đã xác nhận bản ghi xuất hiện trên cả hai DNS Namecheap, Google DNS và Cloudflare DNS; khóa công khai khớp hoàn toàn với khóa cPanel đã cung cấp.

Người dùng đã cung cấp bản ghi DKIM công khai từ cPanel với Name `default._domainkey.sotayaxiu.site.`. Value đã được lưu nguyên dòng trong [dkim-record.txt](dkim-record.txt). Tại Namecheap, thêm bản ghi **TXT**, Host **`default._domainkey`**, Value là toàn bộ nội dung file đó, TTL **Automatic**. Không nhập thêm tên miền vào Host. Bản ghi này cần được lưu trên DNS Namecheap trước khi thử gửi lại; file trong dự án chỉ chứa nội dung để sao chép, không tự cập nhật DNS.

## Kết quả gửi lại sau khi công bố DKIM

Đã gửi email thử mới, mã giao dịch **`1xHEvo-00000000khB-2KiU`**. Người dùng **đã xác nhận nhận được email trong Gmail**. Biên nhận `runtime/smtp-test-823a4cdbb2d8b1964098ebbf.json` ghi nhận `recipient_delivery = confirmed_by_recipient`.

Đã bật `enabled = true` trong `config.local.php`, giờ gửi `23:00`, UTC+7. Để gửi tự động trên hosting, triển khai thư mục `notifications/` cùng file cấu hình riêng và thiết lập Cron Jobs theo README. Việc gửi thử không tạo trạng thái doanh thu ngày và không thay thế bước cài cron.

## Sửa SPF tại nơi quản lý DNS

Trong Namecheap, vào **Domain List → Manage sotayaxiu.site → Advanced DNS**. Hợp nhất IP gửi từ log vào **bản ghi TXT SPF hiện có** của Host `@`:

| Trường | Giá trị đề xuất dựa trên log hiện tại |
| --- | --- |
| Type | TXT Record |
| Host | `@` |
| Value | `v=spf1 ip4:103.15.222.22 include:spf.efwd.registrar-servers.com ~all` |
| TTL | Automatic |

Giữ đúng **một** bản ghi `v=spf1` cho Host `@`; không thêm một SPF thứ hai. Giá trị đề xuất giữ cơ chế Email Forwarding đang có và bổ sung IP mà Gmail ghi nhận. Nếu hosting sử dụng nhiều IP gửi, bổ sung theo danh sách chính thức của hosting. [Cách hợp nhất SPF của Namecheap](https://www.namecheap.com/support/knowledgebase/article.aspx/9736/2237/consolidating-several-spf-records-into-one/).

Nếu SPF do Email Forwarding tự tạo và không cho sửa, nhờ hỗ trợ Namecheap hợp nhất bản ghi trên. Không đổi nameserver, MX hoặc tắt Email Forwarding để sửa lỗi gửi này.

## Công bố DKIM của hosting

Trong **cPanel → Email → Email Deliverability → Manage sotayaxiu.site**, lấy phần **Suggested DKIM (TXT) Record**. Thêm đúng Name và Value đó vào Namecheap Advanced DNS dưới dạng TXT. Nếu Name là `default._domainkey.sotayaxiu.site`, Host ở Namecheap là `default._domainkey`; dùng selector khác nếu cPanel cung cấp tên khác. Sao chép đủ giá trị public key `v=DKIM1; ... p=...` từ hosting; không tự tạo khóa thay thế. Nếu chưa có khóa, nhờ hosting bật DKIM hoặc dùng Generate Local DKIM Key trong cPanel.

DNS đang nằm ở Namecheap, nên chỉ thêm TXT trong Zone Editor của hosting có thể chưa công bố ra Internet. [Hướng dẫn cPanel khi DNS nằm bên ngoài](https://docs.cpanel.net/cpanel/email/email-deliverability-in-cpanel/#dkim), [cách thêm TXT ở Namecheap](https://www.namecheap.com/support/knowledgebase/article.aspx/317/2237/how-do-i-add-txtspfdkimdmarc-records-for-my-domain/).

Sau khi DNS công khai đã cập nhật, chạy lại một email thử và kiểm tra **Track Delivery → Result**. Chỉ kết luận Gmail nhận thư khi có bằng chứng giao thư phía Gmail hoặc người nhận thấy thư; trạng thái SMTP tiếp nhận chưa đủ. IP `171.255.184.190` trong Sender IP là IP máy gửi lên hosting, không phải IP cần thêm vào SPF cho lần gửi này.
