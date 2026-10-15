<?php
namespace AXiu\Notifications;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

require_once __DIR__ . '/revenue.php';

function fetchNotificationOrders($conn, DateTimeImmutable $now, $since, $orderId = null)
{
    $start = $now->format('Y-m-d') . ' 00:00:00';
    if ($since !== null && $since > $start) {
        $start = $since;
    }
    $end = $now->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
    // The public checkout writes header/items separately. Allow it to finish and
    // require matching line totals without changing either checkout implementation.
    // A checkout hook runs after the last item/COMMIT, so its exact order does
    // not need the polling delay. Otherwise a newly saved order is filtered out.
    $cutoff = $orderId === null ? $now->modify('-5 seconds')->format('Y-m-d H:i:s') : $end;
    $orderFilter = $orderId === null ? '' : ' AND o.id = ?';
    $stmt = $conn->prepare(
        'SELECT o.id, o.order_code, o.order_type, o.total_amount, o.note, o.created_at,
                u.full_name, u.username
         FROM orders o
         LEFT JOIN users u ON u.id = o.user_id
         INNER JOIN order_items i ON i.order_id = o.id
         WHERE o.created_at >= ? AND o.created_at < ? AND o.created_at <= ?' . $orderFilter . '
         GROUP BY o.id, o.order_code, o.order_type, o.total_amount, o.note, o.created_at,
                  u.full_name, u.username
         HAVING SUM(i.subtotal) = o.total_amount
         ORDER BY o.id'
    );
    if (!$stmt) {
        throw new RuntimeException('Không chuẩn bị được truy vấn đơn hàng mới.');
    }
    try {
        if ($orderId === null) {
            $stmt->bind_param('sss', $start, $end, $cutoff);
        } else {
            $stmt->bind_param('sssi', $start, $end, $cutoff, $orderId);
        }
        if (!$stmt->execute() || !($result = $stmt->get_result())) {
            throw new RuntimeException('Không đọc được đơn hàng mới.');
        }
        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        return $orders;
    } finally {
        $stmt->close();
    }
}

function renderOrderEmail(array $order, $shopName)
{
    $created = new DateTimeImmutable($order['created_at']);
    $money = number_format((int) $order['total_amount'], 0, ',', '.') . ' đ';
    $type = $order['order_type'] === 'bank_transfer' ? 'Chuyển khoản' : 'Tiền mặt';
    $seller = $order['full_name'] ?: ($order['username'] ?: 'Không xác định');
    $text = $shopName . "\nTHÔNG BÁO ĐƠN HÀNG MỚI\n\nMã đơn: " . $order['order_code']
        . "\nThời gian: " . $created->format('H:i:s d/m/Y') . ' (UTC+7)'
        . "\nTổng tiền: " . $money . "\nThanh toán: " . $type
        . "\nNgười bán: " . $seller . "\nGhi chú: " . ($order['note'] ?: 'Không có')
        . "\n\nSổ Tay A Xíu - Thông báo đơn hàng tự động.";
    $escape = static function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };
    return [
        'subject' => $shopName . ' | Đơn mới ' . $order['order_code'] . ': ' . $money,
        'text' => $text,
        'html' => '<!doctype html><html lang="vi"><head><meta charset="utf-8"></head>'
            . '<body style="background:#fff7f2;padding:24px;font-family:Arial,sans-serif;color:#4a2b1a">'
            . '<div style="max-width:600px;margin:auto;background:white;padding:28px;border-radius:16px">'
            . '<h1 style="font-size:24px;color:#c74420">Đơn hàng mới</h1><p style="line-height:1.8">'
            . nl2br($escape($text)) . '</p></div></body></html>',
    ];
}

function createOrderMailer(array $config, $recipient, array $order)
{
    // Reuse only SMTP construction; replace all daily-report content and identity.
    $mail = createMailer($config, $recipient, [
        'date' => substr($order['created_at'], 0, 10),
        'generated_at' => (new DateTimeImmutable($order['created_at'], new DateTimeZone($config['timezone'])))->format(DateTimeImmutable::ATOM),
        'revenue_total' => 0, 'expense_total' => 0, 'net_revenue' => 0, 'order_count' => 0,
    ]);
    $content = renderOrderEmail($order, $config['shop_name']);
    $mail->Subject = $content['subject'];
    $mail->Body = $content['html'];
    $mail->AltBody = $content['text'];
    $domain = substr(strrchr($config['smtp']['from_email'], '@'), 1);
    $mail->MessageID = '<axiu-order-' . hash('sha256', $order['id'] . '|' . $recipient . '|' . $config['smtp']['from_email']) . '@' . $domain . '>';
    return $mail;
}

function deliverOrderNotifications(array $config, DateTimeImmutable $now, callable $fetch, callable $send, $directory, $waitForLock = false)
{
    $result = ['busy' => false, 'sent' => 0, 'failed' => 0, 'uncertain' => 0, 'errors' => []];
    if (empty($config['new_orders_enabled'])) {
        return $result;
    }
    $config = validateDeliveryConfig($config);
    $now = $now->setTimezone(new DateTimeZone($config['timezone']));
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Không tạo được thư mục trạng thái đơn hàng mới.');
    }
    $lock = fopen($directory . '/order-notifications.lock', 'c');
    if (!$lock) {
        throw new RuntimeException('Không mở được khóa gửi thông báo đơn mới.');
    }
    try {
        // Checkout hooks wait for another send to finish rather than losing the
        // notification when two cashiers save orders at the same time.
        if (!flock($lock, $waitForLock ? LOCK_EX : LOCK_EX | LOCK_NB)) {
            $result['busy'] = true;
            return $result;
        }
        $date = $now->format('Y-m-d');
        $path = $directory . '/orders-' . $date . '.json';
        $state = is_file($path) ? json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)
            : ['date' => $date, 'orders' => []];
        if (!is_array($state) || ($state['date'] ?? '') !== $date || !isset($state['orders']) || !is_array($state['orders'])) {
            throw new RuntimeException('Trạng thái đơn hàng mới không hợp lệ; dừng để tránh gửi trùng.');
        }
        $orders = $fetch($now, $config['new_orders_since'] ?? null);
        $attempts = 0;
        foreach ($orders as $order) {
            // Defense in depth: never deliver historical or pre-activation orders.
            if (substr($order['created_at'], 0, 10) !== $date
                || (!empty($config['new_orders_since']) && $order['created_at'] < $config['new_orders_since'])) {
                continue;
            }
            $id = (string) $order['id'];
            if (!isset($state['orders'][$id])) {
                $state['orders'][$id] = ['order' => $order, 'recipients' => array_fill_keys($config['recipients'], 'pending')];
            }
            $entry = &$state['orders'][$id];
            if (!is_array($entry) || !isset($entry['order'], $entry['recipients']) || !is_array($entry['order']) || !is_array($entry['recipients'])) {
                throw new RuntimeException('Trạng thái người nhận không hợp lệ; dừng để tránh gửi trùng.');
            }
            foreach ($entry['recipients'] as $recipient => &$status) {
                if (!in_array($status, ['pending', 'sending', 'sent', 'failed', 'uncertain'], true)) {
                    throw new RuntimeException('Trạng thái gửi không hợp lệ; dừng để tránh gửi trùng.');
                }
                if (!in_array($recipient, $config['recipients'], true) || $status === 'sent') {
                    continue;
                }
                if ($status === 'sending' || $status === 'uncertain') {
                    // A process may have stopped after SMTP accepted the message.
                    $status = 'uncertain';
                    $result['uncertain']++;
                    continue;
                }
                if ($attempts >= 50) {
                    continue;
                }
                $attempts++;
                $status = 'sending';
                saveState($path, $state);
                try {
                    $send($recipient, $entry['order']);
                } catch (Throwable $error) {
                    $status = 'failed';
                    $result['failed']++;
                    $result['errors'][$id . ' / ' . $recipient] = str_replace($config['smtp']['password'], '[hidden]', $error->getMessage());
                    saveState($path, $state);
                    continue;
                }
                $status = 'sent';
                $result['sent']++;
                // A failed write leaves "sending" on disk; never blindly resend.
                saveState($path, $state);
            }
            unset($status, $entry);
        }
        $state['last_checked_at'] = $now->format(DateTimeImmutable::ATOM);
        saveState($path, $state);
        return $result;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
