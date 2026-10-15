<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/orders.php';

use function AXiu\Notifications\loadConfig;
use function AXiu\Notifications\validateDeliveryConfig;
use function AXiu\Notifications\fetchNotificationOrders;
use function AXiu\Notifications\renderOrderEmail;
use function AXiu\Notifications\createOrderMailer;
use function AXiu\Notifications\deliverOrderNotifications;

try {
    $arguments = array_slice($argv, 1);
    if (count($arguments) > 1 || ($arguments && !in_array($arguments[0], ['--demo', '--help'], true))) {
        throw new RuntimeException('Tham số không hợp lệ. Dùng --help để xem hướng dẫn.');
    }
    $mode = $arguments[0] ?? '';
    if ($mode === '--help') {
        echo "Cron mỗi phút: php notifications/send_order_notifications.php\n"
            . "Xem mẫu HTML, không gửi/không dùng DB: thêm --demo\n";
        exit(0);
    }
    $config = loadConfig();
    date_default_timezone_set($config['timezone']);
    $now = new DateTimeImmutable('now', new DateTimeZone($config['timezone']));
    if ($mode === '--demo') {
        echo renderOrderEmail([
            'id' => 123, 'order_code' => 'AX-DEMO-123', 'created_at' => $now->format('Y-m-d H:i:s'),
            'order_type' => 'cash', 'total_amount' => 75000, 'note' => 'Ít hành',
            'full_name' => 'Nhân viên mẫu', 'username' => 'demo',
        ], $config['shop_name'])['html'];
        exit(0);
    }
    if (empty($config['new_orders_enabled'])) {
        exit(0);
    }
    $config = validateDeliveryConfig($config);
    if (!is_file(__DIR__ . '/vendor/autoload.php')) {
        throw new RuntimeException('Chạy composer install --no-dev trong notifications trước.');
    }
    require __DIR__ . '/vendor/autoload.php';
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require __DIR__ . '/../config/db.php';
    try {
        $result = deliverOrderNotifications($config, $now, static function ($at, $since) use ($conn) {
            return fetchNotificationOrders($conn, $at, $since);
        }, static function ($recipient, $order) use ($config) {
            if (!createOrderMailer($config, $recipient, $order)->send()) {
                throw new RuntimeException('SMTP chưa xác nhận nhận thông báo đơn mới.');
            }
        }, __DIR__ . '/runtime');
    } finally {
        $conn->close();
    }
    if (!$result['busy']) {
        echo $now->format('[Y-m-d H:i:s P]') . ' Đơn mới - đã gửi: ' . $result['sent']
            . '; lỗi (sẽ thử lại): ' . $result['failed'] . '; chưa rõ kết quả (cần kiểm tra): ' . $result['uncertain'] . ".\n";
    }
    foreach ($result['errors'] as $delivery => $message) {
        fwrite(STDERR, 'Đơn / người nhận ' . $delivery . ': ' . $message . "\n");
    }
    exit(($result['failed'] || $result['uncertain']) ? 1 : 0);
} catch (Throwable $error) {
    $message = $error->getMessage();
    if (!empty($config['smtp']['password'])) {
        $message = str_replace($config['smtp']['password'], '[hidden]', $message);
    }
    fwrite(STDERR, 'Thông báo đơn mới: ' . $message . "\n");
    exit(1);
}
