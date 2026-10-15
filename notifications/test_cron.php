<?php
namespace AXiu\Notifications;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/revenue.php';
require_once __DIR__ . '/smtp_test.php';

function cronMinuteTestTick($path, DateTimeImmutable $now, callable $send, $reset = false)
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Không tạo được thư mục trạng thái kiểm tra Cron.');
    }
    $lock = fopen($path . '.lock', 'c');
    if ($lock === false) {
        throw new RuntimeException('Không mở được khóa kiểm tra Cron.');
    }
    try {
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            return ['event' => 'busy'];
        }
        if ($reset || !is_file($path)) {
            $state = [
                'status' => 'waiting',
                'scheduled_at' => $now->format(DateTimeImmutable::ATOM),
                'due_at' => $now->modify('+1 minute')->format(DateTimeImmutable::ATOM),
            ];
            saveState($path, $state);
            return ['event' => 'scheduled', 'state' => $state];
        }
        $state = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($state) || !isset($state['status'], $state['due_at'])
            || !is_string($state['due_at'])
            || !in_array($state['status'], ['waiting', 'sending', 'smtp_accepted', 'failed'], true)) {
            throw new RuntimeException('Trạng thái kiểm tra Cron không hợp lệ; dừng để tránh gửi lại.');
        }
        $due = DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, $state['due_at']);
        if (!$due) {
            throw new RuntimeException('Thời gian hẹn kiểm tra Cron không hợp lệ.');
        }
        if ($state['status'] !== 'waiting') {
            return ['event' => 'done', 'state' => $state];
        }
        if ($now < $due) {
            return ['event' => 'waiting', 'state' => $state];
        }

        // Claim the only send attempt before SMTP, including process interruptions.
        $state['status'] = 'sending';
        $state['started_at'] = $now->format(DateTimeImmutable::ATOM);
        saveState($path, $state);
        try {
            $receipt = $send();
        } catch (Throwable $error) {
            $state['status'] = 'failed';
            saveState($path, $state);
            throw $error;
        }
        $state['status'] = 'smtp_accepted';
        $state['message_id'] = $receipt['message_id'];
        $state['smtp_queue_id'] = $receipt['smtp_queue_id'];
        $state['recipient_delivery'] = 'unconfirmed';
        try {
            saveState($path, $state);
            $saved = true;
        } catch (Throwable $error) {
            // The persisted 'sending' claim still prevents another email.
            $saved = false;
        }
        return ['event' => 'accepted', 'state' => $state, 'receipt_saved' => $saved];
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

// The test suite can include the scheduler function without running the command.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== realpath(__FILE__)) {
    return;
}

try {
    $arguments = array_slice($argv, 1);
    if (!$arguments || $arguments === ['--help']) {
        echo "Cron kiểm tra, hẹn một email sau 60 giây từ lần chạy đầu:\n"
            . "  php notifications/test_cron.php --to=EMAIL_NHAN\n"
            . "Xem trạng thái: thêm --status\n"
            . "Hẹn một lần thử mới: chạy thủ công với --reset\n"
            . "Không đặt --reset trong Cron. Lịch doanh thu 23:00 được giữ nguyên.\n";
        exit(0);
    }
    $recipient = null;
    $mode = '';
    foreach ($arguments as $argument) {
        if (strpos($argument, '--to=') === 0 && $recipient === null) {
            $recipient = trim(substr($argument, 5));
        } elseif (in_array($argument, ['--status', '--reset'], true) && $mode === '') {
            $mode = $argument;
        } else {
            throw new RuntimeException('Tham số không hợp lệ hoặc bị lặp. Dùng --help.');
        }
    }
    if (!$recipient || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Điền một địa chỉ hợp lệ bằng --to=EMAIL_NHAN.');
    }
    $recipient = strtolower($recipient);
    $path = __DIR__ . '/runtime/cron-minute-test-' . substr(hash('sha256', $recipient), 0, 16) . '.json';
    if ($mode === '--status') {
        echo is_file($path) ? file_get_contents($path) . "\n" : "Chưa hẹn kiểm tra cho địa chỉ này.\n";
        exit(0);
    }
    $config = loadConfig();
    $config['recipients'] = [$recipient];
    $config = validateDeliveryConfig($config);
    if (!is_file(__DIR__ . '/vendor/autoload.php')) {
        throw new RuntimeException('Chưa có vendor/autoload.php. Cài PHPMailer trong notifications trước.');
    }
    require_once __DIR__ . '/vendor/autoload.php';
    $now = new DateTimeImmutable('now', new DateTimeZone($config['timezone']));
    $result = cronMinuteTestTick($path, $now, static function () use ($config, $recipient) {
        $mail = createSmtpTestMailer($config, $recipient);
        $mail->Subject = '[KIỂM TRA CRON 1 PHÚT] ' . $config['shop_name'];
        $mail->Body = str_replace('KIỂM TRA HỆ THỐNG EMAIL', 'KIỂM TRA CRON SAU 1 PHÚT', $mail->Body);
        $mail->AltBody = str_replace('KIỂM TRA HỆ THỐNG EMAIL', 'KIỂM TRA CRON SAU 1 PHÚT', $mail->AltBody);
        $messageId = sendSmtpTest($mail);
        $queueId = $mail->getSMTPInstance()->getLastTransactionID();
        return ['message_id' => $messageId, 'smtp_queue_id' => is_string($queueId) ? $queueId : null];
    }, $mode === '--reset');
    if ($result['event'] === 'scheduled') {
        echo 'ĐÃ HẸN: ' . $recipient . '; bắt đầu ' . $result['state']['scheduled_at']
            . '; gửi từ ' . $result['state']['due_at'] . ".\n";
    } elseif ($result['event'] === 'accepted') {
        echo 'SMTP ĐÃ TIẾP NHẬN MỘT EMAIL THỬ: ' . $recipient
            . '; lúc ' . $result['state']['started_at'] . ".\n"
            . 'Message-ID: ' . $result['state']['message_id'] . "\n"
            . 'Mã giao dịch SMTP: ' . ($result['state']['smtp_queue_id'] ?: 'không có') . "\n"
            . "Lần Cron sau sẽ bỏ qua. Kiểm tra Gmail/Spam hoặc Track Delivery để xác nhận giao thư.\n";
        if (!$result['receipt_saved']) {
            fwrite(STDERR, "Chưa lưu được biên nhận sau gửi; trạng thái sending vẫn ngăn gửi lặp.\n");
        }
    } elseif ($result['event'] === 'done' && $result['state']['status'] !== 'smtp_accepted') {
        fwrite(STDERR, 'Lần kiểm tra đã dừng ở trạng thái ' . $result['state']['status']
            . "; xem log, sau khi khắc phục chạy thủ công --reset để thử lại.\n");
        exit(1);
    }
    exit(0);
} catch (Throwable $error) {
    $message = $error->getMessage();
    if (!empty($config['smtp']['password'])) {
        $message = str_replace($config['smtp']['password'], '[hidden]', $message);
    }
    fwrite(STDERR, 'KIỂM TRA CRON: ' . $message . "\n");
    exit(1);
}
