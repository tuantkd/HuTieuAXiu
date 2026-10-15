<?php
namespace AXiu\Notifications;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

function readMailSettings($path)
{
    if (!is_file($path)) {
        return [];
    }
    $json = file_get_contents($path);
    if ($json === false) {
        throw new RuntimeException('Không đọc được cấu hình email đã lưu.');
    }
    $settings = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($settings) || !isset($settings['smtp']) || !is_array($settings['smtp'])) {
        throw new RuntimeException('Cấu hình email đã lưu không hợp lệ.');
    }
    return $settings;
}

function prepareMailSettings(array $input, array $current, DateTimeImmutable $now)
{
    $read = static function ($key, $limit = 255) use ($input) {
        $value = $input[$key] ?? '';
        if (!is_string($value) || strlen($value) > $limit || strpos($value, "\0") !== false) {
            throw new RuntimeException('Giá trị cấu hình không hợp lệ: ' . $key . '.');
        }
        return $value;
    };
    $line = static function ($key, $limit = 255) use ($read) {
        $value = trim($read($key, $limit));
        if (preg_match('/[\r\n]/', $value)) {
            throw new RuntimeException('Giá trị cấu hình phải nằm trên một dòng: ' . $key . '.');
        }
        return $value;
    };
    $settings = [
        'enabled' => $read('enabled') === '1',
        'new_orders_enabled' => $read('new_orders_enabled') === '1',
        'new_orders_since' => $current['new_orders_since'] ?? null,
        'shop_name' => $line('shop_name', 600),
        'send_time' => $line('send_time'),
        'recipients' => [],
    ];
    if ($settings['shop_name'] === '' || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $settings['send_time'])) {
        throw new RuntimeException('Nhập tên cửa hàng và giờ gửi báo cáo hợp lệ (HH:MM).');
    }
    foreach (preg_split('/[\s,;]+/', $read('recipients', 20000), -1, PREG_SPLIT_NO_EMPTY) as $email) {
        $email = strtolower($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Danh sách người nhận có địa chỉ email không hợp lệ.');
        }
        $settings['recipients'][$email] = $email;
    }
    $settings['recipients'] = array_values($settings['recipients']);
    $password = $read('smtp_password', 2048);
    if (preg_match('/[\r\n]/', $password)) {
        throw new RuntimeException('Mật khẩu SMTP không được chứa ký tự xuống dòng.');
    }
    $settings['smtp'] = [
        'host' => $line('smtp_host'),
        'port' => filter_var($line('smtp_port'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]),
        'encryption' => $line('smtp_encryption'),
        'username' => $line('smtp_username'),
        'password' => $password !== '' ? $password : $current['smtp']['password'],
        'from_email' => $line('smtp_from_email'),
        'from_name' => $line('smtp_from_name', 600),
    ];
    $smtp = $settings['smtp'];
    if (!$smtp['port'] || !in_array($smtp['encryption'], ['ssl', 'tls'], true)
        || ($smtp['host'] !== '' && !filter_var($smtp['host'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME))
        || ($smtp['from_email'] !== '' && !filter_var($smtp['from_email'], FILTER_VALIDATE_EMAIL))) {
        throw new RuntimeException('Kiểm tra máy chủ SMTP, cổng, mã hóa và địa chỉ gửi.');
    }
    if ($settings['enabled'] || $settings['new_orders_enabled']) {
        validateDeliveryConfig($settings);
    }
    if ($settings['new_orders_enabled'] && (empty($current['new_orders_enabled']) || empty($settings['new_orders_since']))) {
        $settings['new_orders_since'] = $now->setTimezone(new DateTimeZone($current['timezone']))->format('Y-m-d H:i:s');
    }
    return $settings;
}

function writeMailSettings($path, array $settings)
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Không tạo được thư mục lưu cấu hình email.');
    }
    $temporary = tempnam($directory, 'mail-settings-');
    if ($temporary === false) {
        throw new RuntimeException('Không tạo được tệp cấu hình email. Kiểm tra quyền ghi notifications/runtime.');
    }
    try {
        chmod($temporary, 0600);
        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (file_put_contents($temporary, $json) !== strlen($json) || !rename($temporary, $path)) {
            throw new RuntimeException('Không lưu được cấu hình email. Kiểm tra quyền ghi notifications/runtime.');
        }
    } finally {
        if (is_file($temporary)) {
            unlink($temporary);
        }
    }
}
