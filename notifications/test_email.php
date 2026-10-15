<?php
// Run manually from Terminal/CLI. This script sends one real test email.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/revenue.php';
require __DIR__ . '/smtp_test.php';

use function AXiu\Notifications\loadConfig;
use function AXiu\Notifications\validateDeliveryConfig;
use function AXiu\Notifications\createSmtpTestMailer;
use function AXiu\Notifications\sendSmtpTest;
use function AXiu\Notifications\saveState;

try {
    $arguments = array_slice($argv, 1);
    if (!$arguments || $arguments === ['--help']) {
        echo "Gửi một email thử ngay:\n"
            . "  php notifications/test_email.php --to=EMAIL_NHAN\n\n"
            . "Chỉ kiểm tra cấu hình, không gửi thư:\n"
            . "  php notifications/test_email.php --check --to=EMAIL_NHAN\n\n"
            . "Lệnh thử dùng SMTP trong config.local.php, kể cả khi enabled=false.\n"
            . "Không truy vấn doanh thu, không đổi lịch và không ghi trạng thái ngày.\n";
        exit(0);
    }

    $recipient = null;
    $checkOnly = false;
    foreach ($arguments as $argument) {
        if ($argument === '--check' && !$checkOnly) {
            $checkOnly = true;
        } elseif (strpos($argument, '--to=') === 0 && $recipient === null) {
            $recipient = trim(substr($argument, 5));
        } else {
            throw new RuntimeException('Tham số không hợp lệ hoặc bị lặp. Dùng --help để xem hướng dẫn.');
        }
    }

    $config = loadConfig();
    if ($recipient === null || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Điền một địa chỉ email nhận hợp lệ bằng --to=EMAIL_NHAN.');
    }
    // Limit this manual test to the explicitly requested address only.
    $config['recipients'] = [$recipient];
    if ($checkOnly) {
        echo 'SMTP: ' . $config['smtp']['host'] . ':' . $config['smtp']['port']
            . ' (' . $config['smtp']['encryption'] . ").\n"
            . 'Email nhận thử: ' . $recipient . ".\n"
            . 'Mật khẩu SMTP: ' . (!empty($config['smtp']['password']) ? 'đã điền' : 'chưa điền') . ".\n";
    }
    if (empty($config['smtp']['password'])) {
        throw new RuntimeException('Chưa có mật khẩu SMTP. Điền smtp.password trong notifications/config.local.php hoặc đặt AXIU_SMTP_PASSWORD; không gửi mật khẩu qua chat.');
    }
    $config = validateDeliveryConfig($config);
    if (!is_file(__DIR__ . '/vendor/autoload.php')) {
        throw new RuntimeException('Chạy composer install --no-dev trong thư mục notifications trước.');
    }
    require __DIR__ . '/vendor/autoload.php';
    $mail = createSmtpTestMailer($config, $config['recipients'][0]);
    if ($checkOnly) {
        if (!$mail->preSend()) {
            throw new RuntimeException('Không tạo được nội dung email thử.');
        }
        echo "Cấu hình hợp lệ và đã tạo được email. Chưa kết nối SMTP, chưa gửi thư.\n";
        exit(0);
    }

    echo 'Đang gửi email thử đến ' . $config['recipients'][0] . "...\n";
    $messageId = sendSmtpTest($mail);
    $queueId = $mail->getSMTPInstance()->getLastTransactionID();
    $receipt = [
        'accepted_at' => (new DateTimeImmutable('now', new DateTimeZone($config['timezone'])))->format(DateTimeImmutable::ATOM),
        'smtp_host' => $config['smtp']['host'],
        'from_email' => $config['smtp']['from_email'],
        'recipient' => $config['recipients'][0],
        'message_id' => $messageId,
        'smtp_queue_id' => is_string($queueId) ? $queueId : null,
        'status' => 'smtp_accepted',
        'recipient_delivery' => 'unconfirmed',
    ];
    echo 'SMTP ĐÃ TIẾP NHẬN email gửi đến ' . $config['recipients'][0] . ".\n"
        . 'Message-ID: ' . $messageId . "\n"
        . 'Mã giao dịch SMTP: ' . ($receipt['smtp_queue_id'] ?: 'máy chủ không cung cấp') . "\n"
        . "CHƯA XÁC NHẬN máy chủ người nhận/Gmail đã nhận thư.\n"
        . "Nếu chưa nhận được, tra mã giao dịch trong cPanel > Email > Track Delivery để xem Result.\n";
    try {
        $runtime = __DIR__ . '/runtime';
        if (!is_dir($runtime) && !mkdir($runtime, 0700, true) && !is_dir($runtime)) {
            throw new RuntimeException('Không tạo được thư mục runtime.');
        }
        $receiptPath = $runtime . '/smtp-test-' . substr(hash('sha256', $messageId), 0, 24) . '.json';
        saveState($receiptPath, $receipt);
        echo 'Đã lưu biên nhận SMTP: ' . $receiptPath . "\n";
    } catch (Throwable $receiptError) {
        // A local logging failure must not be presented as an SMTP send failure.
        fwrite(STDERR, "Không lưu được biên nhận cục bộ; SMTP đã tiếp nhận. Giữ lại các mã tra cứu bên trên.\n");
    }
    exit(0);
} catch (Throwable $error) {
    $message = $error->getMessage();
    if (!empty($config['smtp']['password'])) {
        $message = str_replace($config['smtp']['password'], '[hidden]', $message);
    }
    fwrite(STDERR, 'GỬI THỬ CHƯA THÀNH CÔNG: ' . $message . "\n");
    exit(1);
}
