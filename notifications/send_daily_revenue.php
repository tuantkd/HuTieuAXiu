<?php
// Run only from cron / Task Scheduler. Never expose an HTTP send endpoint.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/revenue.php';

use function AXiu\Notifications\loadConfig;
use function AXiu\Notifications\validateDeliveryConfig;
use function AXiu\Notifications\isDue;
use function AXiu\Notifications\fetchReport;
use function AXiu\Notifications\renderRevenueEmail;
use function AXiu\Notifications\deliverDailyReport;
use function AXiu\Notifications\sendReport;

try {
    $arguments = array_slice($argv, 1);
    if (count($arguments) > 1 || ($arguments && !in_array($arguments[0], ['--preview', '--demo', '--help'], true))) {
        throw new RuntimeException('Tham số không hợp lệ. Dùng --help để xem hướng dẫn.');
    }
    $mode = $arguments[0] ?? '';
    if ($mode === '--help') {
        echo "Gửi hằng ngày: php notifications/send_daily_revenue.php\n"
            . "Xem HTML từ dữ liệu thật (không gửi): thêm --preview\n"
            . "Xem HTML từ dữ liệu minh họa (không cần DB/SMTP): thêm --demo\n";
        exit(0);
    }

    $config = loadConfig();
    $now = new DateTimeImmutable('now', new DateTimeZone($config['timezone']));
    date_default_timezone_set($config['timezone']);
    if ($mode === '--demo') {
        $report = [
            'date' => $now->format('Y-m-d'),
            'generated_at' => $now->format(DateTimeImmutable::ATOM),
            'revenue_total' => 5280000,
            'expense_total' => 1280000,
            'net_revenue' => 4000000,
            'order_count' => 42,
        ];
        echo renderRevenueEmail($report, $config['shop_name'])['html'];
        exit(0);
    }

    if ($mode !== '--preview') {
        if (!$config['enabled'] || !isDue($config, $now)) {
            exit(0);
        }
        $config = validateDeliveryConfig($config);
        if (!is_file(__DIR__ . '/vendor/autoload.php')) {
            throw new RuntimeException('Chạy composer install --no-dev trong thư mục notifications trước.');
        }
        require __DIR__ . '/vendor/autoload.php';
    }

    $fetch = static function (DateTimeImmutable $at) {
        // Connect lazily: already-delivered days do not depend on DB availability.
        // Strict mode also makes a failed connection exit with a nonzero status.
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        require __DIR__ . '/../config/db.php';
        try {
            return fetchReport($conn, $at);
        } finally {
            $conn->close();
        }
    };

    if ($mode === '--preview') {
        echo renderRevenueEmail($fetch($now), $config['shop_name'])['html'];
        exit(0);
    }

    $result = deliverDailyReport($config, $now, $fetch, static function ($recipient, $report) use ($config) {
        sendReport($config, $recipient, $report);
    }, __DIR__ . '/runtime');
    if ($result['busy']) {
        exit(0);
    }
    if ($result['sent'] || $result['failed']) {
        echo $now->format('[Y-m-d H:i:s P]') . ' Đã gửi: ' . $result['sent']
            . '; đã gửi trước đó: ' . $result['skipped'] . '; lỗi: ' . count($result['failed']) . ".\n";
    }
    foreach ($result['failed'] as $recipient => $message) {
        // SMTP errors may quote server responses; redact the password defensively.
        $message = str_replace($config['smtp']['password'], '[hidden]', $message);
        fwrite(STDERR, 'Không gửi được đến ' . $recipient . ': ' . $message . "\n");
    }
    exit($result['failed'] ? 1 : 0);
} catch (Throwable $error) {
    $message = $error->getMessage();
    if (!empty($config['smtp']['password'])) {
        $message = str_replace($config['smtp']['password'], '[hidden]', $message);
    }
    fwrite(STDERR, 'Báo cáo doanh thu: ' . $message . "\n");
    exit(1);
}
