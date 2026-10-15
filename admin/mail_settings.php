<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
requireRole(ADMIN_ROLE);
require_once __DIR__ . '/../notifications/revenue.php';

use function AXiu\Notifications\loadConfig;
use function AXiu\Notifications\prepareMailSettings;
use function AXiu\Notifications\writeMailSettings;

class InvalidMailDeliveryStateException extends RuntimeException
{
}

header('Cache-Control: no-store');
$page_title = 'Cấu hình email';
$errorMessage = '';
try {
  $mailConfig = loadConfig();
} catch (Throwable $error) {
  adminAbort('Không đọc được cấu hình email. Kiểm tra tệp notifications/runtime/mail-settings.json và quyền đọc.', 500);
}
if (empty($_SESSION['mail_settings_csrf'])) {
  $_SESSION['mail_settings_csrf'] = bin2hex(random_bytes(32));
}
$fields = [
  'shop_name' => $mailConfig['shop_name'],
  'send_time' => $mailConfig['send_time'],
  'recipients' => implode("\n", $mailConfig['recipients']),
  'enabled' => !empty($mailConfig['enabled']) ? '1' : '',
  'new_orders_enabled' => !empty($mailConfig['new_orders_enabled']) ? '1' : '',
];
foreach (['host', 'port', 'encryption', 'username', 'from_email', 'from_name'] as $key) {
  $fields['smtp_' . $key] = (string) $mailConfig['smtp'][$key];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!is_string($token) || !hash_equals($_SESSION['mail_settings_csrf'], $token)) {
    http_response_code(403);
    $errorMessage = 'Phiên xác nhận không hợp lệ. Tải lại trang rồi thử lại.';
  } else {
    foreach ($fields as $key => $value) {
      $fields[$key] = is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
    }
    try {
      $settings = prepareMailSettings($_POST, $mailConfig, new DateTimeImmutable('now'));
      writeMailSettings(__DIR__ . '/../notifications/runtime/mail-settings.json', $settings);
      adminFlashSet('Đã lưu cấu hình email. Thông báo đơn mới áp dụng ngay cho các đơn được lưu tiếp theo.', 'success');
      header('Location: mail_settings.php');
      exit;
    } catch (Throwable $error) {
      $errorMessage = $error instanceof RuntimeException ? $error->getMessage() : 'Không lưu được cấu hình email. Kiểm tra quyền ghi notifications/runtime.';
    }
  }
}
$senderDomain = filter_var($mailConfig['smtp']['from_email'], FILTER_VALIDATE_EMAIL)
  ? strtolower(substr(strrchr($mailConfig['smtp']['from_email'], '@'), 1)) : '';
$deliverySummary = ['sent' => 0, 'failed' => 0, 'pending' => 0, 'uncertain' => 0];
$lastDeliveryCheck = '';
$deliveryStateError = '';
$deliveryDate = new DateTimeImmutable('now', new DateTimeZone($mailConfig['timezone']));
$deliveryPath = __DIR__ . '/../notifications/runtime/orders-' . $deliveryDate->format('Y-m-d') . '.json';
if (is_file($deliveryPath)) {
  try {
    $deliveryState = json_decode(file_get_contents($deliveryPath), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($deliveryState) || !is_array($deliveryState['orders'] ?? null)) {
      throw new InvalidMailDeliveryStateException('Trạng thái gửi không hợp lệ.');
    }
    if (!empty($deliveryState['last_checked_at'])) {
      $lastDeliveryCheck = (new DateTimeImmutable($deliveryState['last_checked_at']))
        ->setTimezone(new DateTimeZone($mailConfig['timezone']))->format('H:i:s d/m/Y');
    }
    foreach ($deliveryState['orders'] as $delivery) {
      foreach ($delivery['recipients'] ?? [] as $status) {
        $key = $status === 'sending' ? 'uncertain' : $status;
        if (isset($deliverySummary[$key])) {
          $deliverySummary[$key]++;
        }
      }
    }
  } catch (Throwable $error) {
    $deliveryStateError = 'Không đọc được trạng thái gửi. Kiểm tra notifications/runtime và nhật ký PHP.';
  }
}
define('ADMIN_APP', true);
include_once __DIR__ . '/layout/header.php';
?>
<?php if ($errorMessage): ?>
  <div class="admin-alert error" role="alert"><?= adminH($errorMessage) ?></div>
<?php endif; ?>
<div class="panel">
  <div class="panel-header">
    <div>
      <h3>Cấu hình gửi email</h3>
      <div class="helper-text">Dùng chung SMTP và danh sách người nhận cho đơn hàng mới và báo cáo doanh thu. Giờ Việt
        Nam (UTC+7).</div>
    </div>
  </div>
  <form method="post" class="form-card" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= adminH($_SESSION['mail_settings_csrf']) ?>">
    <div class="field field--full">
      <label class="checkbox-inline"><input type="checkbox" name="new_orders_enabled" value="1"
          <?= $fields['new_orders_enabled'] === '1' ? 'checked' : '' ?>> Gửi email khi có đơn hàng mới</label>
      <div class="helper-text">Tự gửi sau khi lưu đơn thành công, áp dụng cho cả quầy bán hàng và POS admin. Chỉ gửi đơn
        trong ngày, từ lúc bật tính năng; không cần cron để gửi lần đầu.</div>
    </div>
    <div class="field">
      <label class="checkbox-inline"><input type="checkbox" name="enabled" value="1" <?= $fields['enabled'] === '1' ? 'checked' : '' ?>> Gửi báo cáo doanh thu hằng ngày</label>
    </div>
    <div class="field">
      <label for="send_time">Giờ gửi báo cáo</label>
      <input type="time" id="send_time" name="send_time" value="<?= adminH($fields['send_time']) ?>" required>
    </div>
    <div class="field field--full">
      <label for="recipients">Email nhận thông báo</label>
      <textarea id="recipients" name="recipients" rows="4"
        placeholder="chucuahang@example.com&#10;ketoan@example.com"><?= adminH($fields['recipients']) ?></textarea>
      <div class="helper-text">Mỗi dòng một địa chỉ, hoặc phân cách bằng dấu phẩy/chấm phẩy. Địa chỉ trùng được gộp; mỗi
        người nhận một email riêng.</div>
    </div>
    <div class="field">
      <label for="shop_name">Tên cửa hàng trong email</label>
      <input id="shop_name" name="shop_name" value="<?= adminH($fields['shop_name']) ?>" maxlength="150" required>
    </div>
    <div class="field">
      <label for="smtp_from_name">Tên người gửi</label>
      <input id="smtp_from_name" name="smtp_from_name" value="<?= adminH($fields['smtp_from_name']) ?>" maxlength="150">
    </div>
    <div class="field">
      <label for="smtp_host">Máy chủ SMTP</label>
      <input id="smtp_host" name="smtp_host" value="<?= adminH($fields['smtp_host']) ?>" maxlength="255"
        placeholder="mail.tenmien.vn">
    </div>
    <div class="field">
      <label for="smtp_port">Cổng SMTP</label>
      <input type="number" id="smtp_port" name="smtp_port" value="<?= adminH($fields['smtp_port']) ?>" min="1"
        max="65535" required>
    </div>
    <div class="field">
      <label for="smtp_encryption">Mã hóa kết nối</label>
      <select id="smtp_encryption" name="smtp_encryption">
        <option value="ssl" <?= $fields['smtp_encryption'] === 'ssl' ? 'selected' : '' ?>>SSL/TLS (thường cổng 465)
        </option>
        <option value="tls" <?= $fields['smtp_encryption'] === 'tls' ? 'selected' : '' ?>>STARTTLS (thường cổng 587)
        </option>
      </select>
    </div>
    <div class="field">
      <label for="smtp_from_email">Địa chỉ email gửi</label>
      <input type="email" id="smtp_from_email" name="smtp_from_email" value="<?= adminH($fields['smtp_from_email']) ?>"
        maxlength="255">
    </div>
    <div class="field">
      <label for="smtp_username">Tài khoản SMTP</label>
      <input id="smtp_username" name="smtp_username" value="<?= adminH($fields['smtp_username']) ?>" maxlength="255"
        autocomplete="off">
    </div>
    <div class="field">
      <label for="smtp_password">Mật khẩu SMTP mới</label>
      <input type="password" id="smtp_password" name="smtp_password" value="" maxlength="2048"
        autocomplete="new-password">
      <div class="helper-text">
        <?= !empty($mailConfig['smtp']['password']) ? 'Đã có mật khẩu. Để trống để giữ nguyên.' : 'Chưa có mật khẩu. Nhập trước khi bật gửi email.' ?>
      </div>
    </div>
    <div class="field field--full"><button type="submit" class="button">Lưu cấu hình email</button></div>
  </form>
</div>
<?php if ($senderDomain): ?>
  <div class="panel">
    <div class="panel-header">
      <h3>Xác thực tên miền gửi</h3>
    </div>
    <p>Email đang dùng tên miền <strong><?= adminH($senderDomain) ?></strong>.
      Cấu hình SMTP và xác thực tên miền đều cần đúng để Gmail đánh giá người gửi.</p>
    <p>Kiểm tra SPF và DKIM trong <strong>cPanel → Email Deliverability</strong>.
      Bản ghi DNS phải được lưu tại nơi quản lý nameserver của tên miền; nếu dùng Namecheap BasicDNS thì lưu trong
      <strong>Advanced DNS → Host Records</strong>.
    </p>
    <p>Nếu chưa có DMARC, thêm đúng một bản ghi TXT với các giá trị khởi đầu dưới đây.
      Nếu đã có DMARC, kiểm tra bản ghi hiện tại trước khi thay chính sách.</p>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Type</th>
            <th>Host</th>
            <th>Value</th>
            <th>TTL</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>TXT</td>
            <td><code>_dmarc</code></td>
            <td><code>v=DMARC1; p=none; adkim=r; aspf=r</code></td>
            <td>Automatic</td>
          </tr>
        </tbody>
      </table>
    </div>
    <p class="helper-text">Tên đầy đủ: <code>_dmarc.<?= adminH($senderDomain) ?></code>.
      Với Namecheap, ô Host chỉ nhập <code>_dmarc</code>. Bảng này là hướng dẫn, không tự ghi DNS.</p>
    <p class="helper-text">Sau khi DNS cập nhật, mở một email mới trong Gmail → menu ba chấm của email → Hiển thị thư gốc.
      Kiểm tra SPF, DKIM và DMARC có kết quả PASS. Xác thực thành công không bảo đảm mọi thư vào Inbox;
      Gmail còn đánh giá uy tín người gửi và phản hồi của người nhận.
      <a href="https://support.google.com/mail/answer/81126?hl=vi" target="_blank" rel="noopener noreferrer">Hướng dẫn
        Google</a>.
    </p>
  </div>
<?php endif; ?>
<div class="panel">
  <div class="panel-header">
    <h3>Trạng thái gửi đơn mới hôm nay</h3>
  </div>
  <?php if ($deliveryStateError): ?>
    <p role="alert"><?= adminH($deliveryStateError) ?></p>
  <?php elseif ($lastDeliveryCheck): ?>
    <p>Lần xử lý gần nhất: <strong><?= adminH($lastDeliveryCheck) ?></strong> (UTC+7).</p>
    <p>SMTP đã tiếp nhận: <strong><?= $deliverySummary['sent'] ?></strong> thư;
      lỗi: <strong><?= $deliverySummary['failed'] ?></strong>;
      chờ gửi: <strong><?= $deliverySummary['pending'] ?></strong>;
      cần đối soát: <strong><?= $deliverySummary['uncertain'] ?></strong>.</p>
    <p class="helper-text">Nếu chưa nhận thư đã được SMTP tiếp nhận, kiểm tra Hộp thư đến/Spam và Track Delivery trên
      hosting.</p>
  <?php else: ?>
    <p>Chưa ghi nhận lần gửi nào hôm nay. Khi bật thông báo, đơn mới được gửi tự động sau khi lưu thành công.</p>
  <?php endif; ?>
</div>
<div class="panel">
  <div class="panel-header">
    <h3>Gửi lại khi lỗi và lịch báo cáo</h3>
  </div>
  <p>Thông báo đơn mới tự chạy sau khi lưu đơn, kể cả trên XAMPP. Có thể thêm Cron Job mỗi phút (<code>* * * * *</code>)
    để thử lại những email bị lỗi; thay đường dẫn bằng thư mục website trên hosting:</p>
  <pre
    style="white-space:pre-wrap;overflow-wrap:anywhere"><code>/usr/local/bin/php /home/CPANEL_USER/public_html/notifications/send_order_notifications.php &gt;&gt; /home/CPANEL_USER/order-notifications.log 2&gt;&amp;1</code></pre>
  <p class="helper-text">Báo cáo doanh thu hằng ngày vẫn cần Cron Job <code>send_daily_revenue.php</code>. Nhật ký gửi
    sau khi lưu đơn nằm trong <code>notifications/runtime/order-checkout.log</code>.</p>
</div>
<?php include_once __DIR__ . '/layout/footer.php'; ?>
