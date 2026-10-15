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

function fetchOrderRevenueNotifications($conn, DateTimeImmutable $now, $since, $orderId = null)
{
    $orders = fetchNotificationOrders($conn, $now, $since, $orderId);
    if (!$orders) {
        return [];
    }
    // Recalculate the whole day, including orders before notification activation.
    // Each order keeps this snapshot for every recipient and any later retry.
    $report = fetchReport($conn, $now);
    foreach ($orders as &$order) {
        $order['revenue_report'] = $report;
    }
    unset($order);
    return $orders;
}

function orderRevenueReport(array $order)
{
    $report = $order['revenue_report'] ?? null;
    if (!is_array($report) || ($report['date'] ?? '') !== substr($order['created_at'], 0, 10)
        || empty($report['generated_at'])) {
        throw new RuntimeException('Thiếu báo cáo doanh thu đúng ngày của đơn hàng; hủy gửi để tránh sai số liệu.');
    }
    foreach (['revenue_total', 'expense_total', 'net_revenue', 'order_count'] as $key) {
        if (!isset($report[$key]) || !is_int($report[$key])) {
            throw new RuntimeException('Số liệu báo cáo doanh thu của đơn hàng không hợp lệ.');
        }
    }
    if ($report['order_count'] < 1 || $report['net_revenue'] !== $report['revenue_total'] - $report['expense_total']) {
        throw new RuntimeException('Tổng hợp doanh thu của đơn hàng không khớp; hủy gửi.');
    }
    return $report;
}

function renderOrderEmail(array $order, $shopName)
{
    $created = new DateTimeImmutable($order['created_at']);
    $money = number_format((int) $order['total_amount'], 0, ',', '.') . ' đ';
    $type = $order['order_type'] === 'bank_transfer' ? 'Chuyển khoản' : 'Tiền mặt';
    $seller = $order['full_name'] ?: ($order['username'] ?: 'Không xác định');
    $details = 'Mã đơn: ' . $order['order_code']
        . "\nThời gian: " . $created->format('H:i:s d/m/Y') . ' (UTC+7)'
        . "\nTổng tiền: " . $money . "\nThanh toán: " . $type
        . "\nNgười bán: " . $seller . "\nGhi chú: " . ($order['note'] ?: 'Không có');
    $content = renderRevenueEmail(orderRevenueReport($order), $shopName, $details);
    $content['subject'] .= ' | Sau đơn ' . $order['order_code'];
    return $content;
}

function createOrderMailer(array $config, $recipient, array $order)
{
    $mail = createMailer($config, $recipient, orderRevenueReport($order));
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
            // Pending entries from older versions contain only order details.
            // Attach their first report without replacing an existing snapshot.
            if (!isset($entry['order']['revenue_report']) && isset($order['revenue_report'])) {
                $entry['order']['revenue_report'] = $order['revenue_report'];
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
