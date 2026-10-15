<?php
namespace AXiu\Notifications;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

function sendSavedOrderNotification($conn, $orderId)
{
    require_once __DIR__ . '/orders.php';
    $config = loadConfig();
    if (empty($config['new_orders_enabled'])) {
        return;
    }
    $now = new DateTimeImmutable('now', new DateTimeZone($config['timezone']));
    $directory = __DIR__ . '/runtime';
    try {
        $config = validateDeliveryConfig($config);
        if (!is_file(__DIR__ . '/vendor/autoload.php')) {
            throw new RuntimeException('Thiếu thư viện gửi mail trong notifications/vendor.');
        }
        require_once __DIR__ . '/vendor/autoload.php';
        $result = deliverOrderNotifications($config, $now, static function ($at, $since) use ($conn, $orderId) {
            return fetchNotificationOrders($conn, $at, $since, $orderId);
        }, static function ($recipient, $order) use ($config) {
            if (!createOrderMailer($config, $recipient, $order)->send()) {
                throw new RuntimeException('SMTP chưa xác nhận nhận thông báo đơn mới.');
            }
        }, $directory, true);
        $message = $now->format('[Y-m-d H:i:s P]') . ' Đơn #' . $orderId
            . ' - đã gửi: ' . $result['sent'] . '; lỗi: ' . $result['failed']
            . '; chưa rõ kết quả: ' . $result['uncertain'];
        foreach ($result['errors'] as $delivery => $error) {
            $message .= ' | ' . $delivery . ': ' . $error;
        }
        error_log($message . PHP_EOL, 3, $directory . '/order-checkout.log');
    } catch (Throwable $error) {
        $message = $error->getMessage();
        if (!empty($config['smtp']['password'])) {
            $message = str_replace($config['smtp']['password'], '[hidden]', $message);
        }
        error_log('Thông báo đơn #' . $orderId . ': ' . $message);
        if (is_dir($directory)) {
            error_log($now->format('[Y-m-d H:i:s P]') . ' Đơn #' . $orderId . ': ' . $message . PHP_EOL, 3, $directory . '/order-checkout.log');
        }
    }
}

function queueOrderNotification($conn, $orderId)
{
    static $scheduled = [];
    $orderId = (int) $orderId;
    if ($orderId <= 0 || isset($scheduled[$orderId])) {
        return;
    }
    $scheduled[$orderId] = true;
    // Registration does no DB/SMTP work inside the checkout transaction. All
    // failures are contained after the order has already been saved.
    register_shutdown_function(static function () use ($conn, $orderId) {
        try {
            if (PHP_SAPI !== 'cli') {
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_write_close();
                }
                ignore_user_abort(true);
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                } elseif (http_response_code() >= 300 && http_response_code() < 400 && !headers_sent()) {
                    // Apache/XAMPP: finish the empty redirect response before SMTP.
                    header('Connection: close');
                    header('Content-Length: 0');
                    while (ob_get_level() > 0) {
                        if (!ob_end_clean()) {
                            break;
                        }
                    }
                    flush();
                }
            }
            sendSavedOrderNotification($conn, $orderId);
        } catch (Throwable $error) {
            // Never bubble a notification/configuration error into checkout.
            error_log('Không chạy được thông báo cho đơn #' . $orderId . '. Kiểm tra cấu hình email và notifications/runtime.');
        }
    });
}
