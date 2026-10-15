<?php
namespace AXiu\Notifications;

use DateTimeImmutable;
use DateTimeZone;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;
use Throwable;

require_once __DIR__ . '/mail_settings.php';

function loadConfig()
{
    $config = require __DIR__ . '/config.php';
    if (is_file(__DIR__ . '/config.local.php')) {
        $overrides = require __DIR__ . '/config.local.php';
        if (!is_array($overrides)) {
            throw new RuntimeException('config.local.php phải trả về một mảng cấu hình.');
        }
        // Lists such as recipients must replace the old list completely.
        $smtp = array_replace($config['smtp'], $overrides['smtp'] ?? []);
        $config = array_replace($config, $overrides);
        $config['smtp'] = $smtp;
    }

    $managed = readMailSettings(__DIR__ . '/runtime/mail-settings.json');
    $smtp = array_replace($config['smtp'], $managed['smtp'] ?? []);
    $config = array_replace($config, $managed);
    $config['smtp'] = $smtp;

    new DateTimeZone($config['timezone']);
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $config['send_time'])) {
        throw new RuntimeException('send_time phải có định dạng HH:MM, ví dụ 23:00.');
    }

    return $config;
}

function validateDeliveryConfig(array $config)
{
    if (!is_array($config['recipients']) || !$config['recipients']) {
        throw new RuntimeException('Chưa cấu hình danh sách email nhận báo cáo.');
    }

    $recipients = [];
    foreach ($config['recipients'] as $recipient) {
        $email = is_string($recipient) ? strtolower(trim($recipient)) : '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Danh sách người nhận có địa chỉ email không hợp lệ.');
        }
        $recipients[$email] = $email;
    }
    $config['recipients'] = array_values($recipients);

    $smtp = $config['smtp'];
    foreach (['host', 'username', 'password', 'from_email', 'from_name'] as $key) {
        if (!is_string($smtp[$key]) || trim($smtp[$key]) === '' || preg_match('/[\r\n]/', $smtp[$key])) {
            throw new RuntimeException('Thiếu hoặc sai cấu hình SMTP: ' . $key . '.');
        }
    }
    if (
        !filter_var($smtp['host'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
        || !filter_var($smtp['from_email'], FILTER_VALIDATE_EMAIL)
        || !filter_var($smtp['port'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]])
        || !in_array($smtp['encryption'], ['ssl', 'tls'], true)
    ) {
        throw new RuntimeException('Kiểm tra SMTP host, port, from_email và encryption (ssl hoặc tls).');
    }

    return $config;
}

function isDue(array $config, DateTimeImmutable $now)
{
    return $now->setTimezone(new DateTimeZone($config['timezone']))->format('H:i') >= $config['send_time'];
}

function fetchReport($conn, DateTimeImmutable $now)
{
    $date = $now->format('Y-m-d');
    $start = $date . ' 00:00:00';
    $end = $now->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
    // Same daily scope and formula as summary.php. A single SELECT keeps both
    // aggregates consistent, without multiplying orders by expense rows.
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(total_amount), 0) AS revenue_total,
                COUNT(*) AS order_count,
                (SELECT COALESCE(SUM(amount), 0) FROM transactions
                 WHERE type = 'expense' AND transaction_date = ?) AS expense_total
         FROM orders WHERE created_at >= ? AND created_at < ?"
    );
    if (!$stmt) {
        throw new RuntimeException('Không chuẩn bị được truy vấn doanh thu.');
    }
    try {
        $stmt->bind_param('sss', $date, $start, $end);
        if (!$stmt->execute()) {
            throw new RuntimeException('Không đọc được doanh thu; hủy gửi để tránh báo cáo sai.');
        }
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        if (!$row) {
            throw new RuntimeException('Không nhận được kết quả doanh thu.');
        }
    } finally {
        $stmt->close();
    }

    $revenueTotal = (int) $row['revenue_total'];
    $expenseTotal = (int) $row['expense_total'];
    $netRevenue = $revenueTotal - $expenseTotal;

    return [
        'date' => $date,
        'generated_at' => $now->format(DateTimeImmutable::ATOM),
        'revenue_total' => $revenueTotal,
        'expense_total' => $expenseTotal,
        'net_revenue' => $netRevenue,
        'order_count' => (int) $row['order_count'],
    ];
}

function renderRevenueEmail(array $report, $shopName)
{
    $displayDate = DateTimeImmutable::createFromFormat('!Y-m-d', $report['date'])->format('d/m/Y');
    $money = static function ($amount) {
        return number_format((int) $amount, 0, ',', '.') . ' đ';
    };
    $subject = $shopName . ' | Doanh thu ngày ' . $displayDate . ': ' . $money($report['net_revenue']);
    $snapshot = new DateTimeImmutable($report['generated_at']);
    $text = $shopName . "\nBÁO CÁO DOANH THU NGÀY " . $displayDate
        . "\n\nDoanh thu sau chi phí: " . $money($report['net_revenue'])
        . "\nTổng tiền bán hàng: " . $money($report['revenue_total'])
        . "\nTổng khoản chi: " . $money($report['expense_total'])
        . "\nSố đơn hàng: " . $report['order_count']
        . "\n\nCông thức: tổng tiền bán hàng - tổng khoản chi."
        . "\nCập nhật đến " . $snapshot->format('H:i d/m/Y') . ' (UTC' . $snapshot->format('P') . ').'
        . "\nCác giao dịch phát sinh hoặc chỉnh sửa sau thời điểm cập nhật chưa được tính."
        . "\n\nSổ Tay A Xíu - Thông báo doanh thu tự động.";

    ob_start();
    try {
        include __DIR__ . '/template.php';
        $html = ob_get_contents();
    } finally {
        ob_end_clean();
    }

    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}

function createMailer(array $config, $recipient, array $report)
{
    $content = renderRevenueEmail($report, $config['shop_name']);
    $smtp = $config['smtp'];
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $smtp['host'];
    $mail->Port = (int) $smtp['port'];
    $mail->SMTPSecure = $smtp['encryption'];
    $mail->SMTPAuth = true;
    $mail->Username = $smtp['username'];
    $mail->Password = $smtp['password'];
    $mail->Timeout = 30;
    $mail->Timelimit = 60;
    $mail->SMTPDebug = 0;
    $mail->CharSet = PHPMailer::CHARSET_UTF8;
    $mail->Encoding = PHPMailer::ENCODING_BASE64;
    $mail->setFrom($smtp['from_email'], $smtp['from_name']);
    // One message per recipient keeps the mailing list private and permits retries.
    $mail->addAddress($recipient);
    $mail->isHTML(true);
    $mail->Subject = $content['subject'];
    $mail->Body = $content['html'];
    $mail->AltBody = $content['text'];
    $domain = substr(strrchr($smtp['from_email'], '@'), 1);
    $mail->MessageID = '<axiu-' . hash('sha256', $report['date'] . '|' . $recipient . '|' . $smtp['from_email']) . '@' . $domain . '>';
    return $mail;
}

function sendReport(array $config, $recipient, array $report)
{
    if (!createMailer($config, $recipient, $report)->send()) {
        throw new RuntimeException('SMTP chưa xác nhận nhận email.');
    }
}

function saveState($path, array $state)
{
    $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $temporary = tempnam(dirname($path), 'revenue-');
    if ($temporary === false) {
        throw new RuntimeException('Không tạo được tệp trạng thái gửi mail.');
    }
    try {
        if (file_put_contents($temporary, $json) !== strlen($json) || !rename($temporary, $path)) {
            throw new RuntimeException('Không lưu được trạng thái gửi mail. Kiểm tra quyền ghi runtime.');
        }
    } finally {
        if (is_file($temporary)) {
            unlink($temporary);
        }
    }
}

function deliverDailyReport(array $config, DateTimeImmutable $now, callable $fetch, callable $send, $stateDirectory)
{
    $now = $now->setTimezone(new DateTimeZone($config['timezone']));
    if (!is_dir($stateDirectory) && !mkdir($stateDirectory, 0700, true) && !is_dir($stateDirectory)) {
        throw new RuntimeException('Không tạo được thư mục runtime.');
    }
    $lock = fopen($stateDirectory . '/daily-revenue.lock', 'c');
    if ($lock === false) {
        throw new RuntimeException('Không mở được khóa gửi báo cáo.');
    }

    try {
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            return ['sent' => 0, 'skipped' => 0, 'failed' => [], 'busy' => true];
        }
        $path = $stateDirectory . '/' . $now->format('Y-m-d') . '.json';
        if (is_file($path)) {
            $state = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (
                !is_array($state) || !isset($state['report'], $state['sent'])
                || !is_array($state['report']) || !is_array($state['sent'])
                || ($state['report']['date'] ?? '') !== $now->format('Y-m-d')
            ) {
                throw new RuntimeException('Trạng thái gửi không hợp lệ; dừng để tránh gửi trùng.');
            }
        } else {
            $state = ['report' => $fetch($now), 'sent' => []];
        }

        foreach (['revenue_total', 'expense_total', 'net_revenue', 'order_count'] as $key) {
            if (!isset($state['report'][$key]) || !is_int($state['report'][$key])) {
                throw new RuntimeException('Số liệu báo cáo không hợp lệ; hủy gửi.');
            }
        }
        $snapshot = isset($state['report']['generated_at']) && is_string($state['report']['generated_at'])
            ? DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, $state['report']['generated_at']) : false;
        if (
            !$snapshot || $snapshot->format('Y-m-d') !== $now->format('Y-m-d')
            || $state['report']['date'] !== $now->format('Y-m-d')
            || $state['report']['net_revenue'] !== $state['report']['revenue_total'] - $state['report']['expense_total']
            || $state['report']['order_count'] < 0
        ) {
            throw new RuntimeException('Số liệu báo cáo không hợp lệ; hủy gửi.');
        }
        if (!is_file($path)) {
            // Freeze the snapshot before sending, including retries to failed recipients.
            saveState($path, $state);
        }

        $result = ['sent' => 0, 'skipped' => 0, 'failed' => [], 'busy' => false];
        foreach ($config['recipients'] as $recipient) {
            $recipientKey = hash('sha256', $recipient);
            if (isset($state['sent'][$recipientKey])) {
                $result['skipped']++;
                continue;
            }
            try {
                $send($recipient, $state['report']);
            } catch (Throwable $error) {
                $result['failed'][$recipient] = $error->getMessage();
                continue;
            }
            $state['sent'][$recipientKey] = (new DateTimeImmutable('now', $now->getTimezone()))->format(DateTimeImmutable::ATOM);
            // Abort on a write failure so later recipients are not sent without tracking.
            saveState($path, $state);
            $result['sent']++;
        }
        return $result;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}