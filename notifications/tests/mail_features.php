<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/orders.php';
require dirname(__DIR__) . '/vendor/autoload.php';

use function AXiu\Notifications\prepareMailSettings;
use function AXiu\Notifications\writeMailSettings;
use function AXiu\Notifications\readMailSettings;
use function AXiu\Notifications\fetchNotificationOrders;
use function AXiu\Notifications\fetchOrderRevenueNotifications;
use function AXiu\Notifications\createOrderMailer;
use function AXiu\Notifications\deliverOrderNotifications;

$checks = 0;
function checkMail($condition, $message)
{
    global $checks;
    $checks++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function rejectMail(callable $callback, $message)
{
    try {
        $callback();
    } catch (Throwable $expected) {
        checkMail(true, $message);
        return;
    }
    checkMail(false, $message);
}
function mailDatabaseAdapter(PDO $db)
{
    return new class($db) {
        private $db;
        public function __construct($db) { $this->db = $db; }
        public function prepare($sql)
        {
            return new class($this->db->prepare($sql)) {
                private $stmt;
                private $parameters = [];
                public function __construct($stmt) { $this->stmt = $stmt; }
                public function bind_param($types, &...$parameters) { $this->parameters = $parameters; }
                public function execute() { return $this->stmt->execute($this->parameters); }
                public function get_result() { return $this; }
                public function fetch_assoc() { return $this->stmt->fetch(PDO::FETCH_ASSOC); }
                public function close() { $this->stmt->closeCursor(); }
            };
        }
    };
}

$temporary = sys_get_temp_dir() . '/axiu-mail-test-' . bin2hex(random_bytes(8));
mkdir($temporary, 0700);
try {
    $config = require dirname(__DIR__) . '/config.php';
    $config['smtp'] = [
        'host' => 'mail.example.com', 'port' => 465, 'encryption' => 'ssl',
        'username' => 'sender@example.com', 'password' => 'test-only-secret',
        'from_email' => 'sender@example.com', 'from_name' => 'Sổ Tay A Xíu',
    ];
    $input = [
        'shop_name' => 'A Xíu', 'send_time' => '23:00', 'new_orders_enabled' => '1',
        'recipients' => "FIRST@example.com; second@example.com\nfirst@example.com",
    ];
    foreach ($config['smtp'] as $key => $value) {
        $input['smtp_' . $key] = (string) $value;
    }
    $input['smtp_password'] = '';
    $now = new DateTimeImmutable('2026-10-15T10:00:00+07:00');
    $settings = prepareMailSettings($input, $config, $now);
    checkMail($settings['recipients'] === ['first@example.com', 'second@example.com'], 'Multiple recipients normalized and deduplicated.');
    checkMail($settings['smtp']['password'] === 'test-only-secret', 'Empty password preserves current secret.');
    checkMail($settings['new_orders_since'] === '2026-10-15 10:00:00', 'Enablement records local activation time.');
    checkMail(!$settings['enabled'] && $settings['new_orders_enabled'], 'Daily and new-order switches are independent.');
    $config = array_replace($config, $settings);
    $changed = prepareMailSettings(array_replace($input, ['smtp_password' => 'new-test-secret']), $config, $now->modify('+1 hour'));
    checkMail($changed['smtp']['password'] === 'new-test-secret' && $changed['new_orders_since'] === $settings['new_orders_since'], 'Editing settings replaces password without moving activation.');
    $disabled = prepareMailSettings(array_replace($input, ['new_orders_enabled' => '']), $config, $now);
    $reenabled = prepareMailSettings($input, array_replace($config, $disabled), $now->modify('+1 hour'));
    checkMail($reenabled['new_orders_since'] === '2026-10-15 11:00:00', 'Re-enabling does not replay orders created while disabled.');
    foreach (['recipients' => "bad@example.com\r\nBcc: attacker@example.com", 'send_time' => '25:00', 'smtp_port' => '0', 'smtp_encryption' => 'none', 'smtp_from_email' => 'invalid', 'smtp_host' => 'https://example.com', 'smtp_password' => "secret\n", 'shop_name' => ['bad']] as $key => $value) {
        rejectMail(static function () use ($input, $config, $now, $key, $value) {
            prepareMailSettings(array_replace($input, [$key => $value]), $config, $now);
        }, 'Reject invalid field ' . $key);
    }
    rejectMail(static function () use ($input, $config, $now) {
        prepareMailSettings(array_replace($input, ['recipients' => '']), $config, $now);
    }, 'Enabled delivery requires recipients.');
    writeMailSettings($temporary . '/settings.json', $settings);
    checkMail(readMailSettings($temporary . '/settings.json') === $settings, 'Managed settings survive JSON round trip.');
    writeMailSettings($temporary . '/settings.json', $changed);
    checkMail(readMailSettings($temporary . '/settings.json') === $changed, 'Settings can be atomically replaced.');
    file_put_contents($temporary . '/settings.json', '{broken');
    rejectMail(static function () use ($temporary) { readMailSettings($temporary . '/settings.json'); }, 'Corrupt managed config is surfaced.');

    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE users (id INTEGER, full_name TEXT, username TEXT)');
    $db->exec('CREATE TABLE orders (id INTEGER PRIMARY KEY, order_code TEXT, order_type TEXT, total_amount INTEGER, note TEXT, created_at TEXT, user_id INTEGER)');
    $db->exec('CREATE TABLE order_items (order_id INTEGER, subtotal INTEGER)');
    $db->exec('CREATE TABLE transactions (type TEXT, amount INTEGER, transaction_date TEXT)');
    $db->exec("INSERT INTO transactions VALUES
        ('expense', 20000, '2026-10-15'), ('expense', 30000, '2026-10-15'),
        ('income', 9000000, '2026-10-15'), ('expense', 8000000, '2026-10-14'),
        ('expense', 7000000, '2026-10-17')");
    $insert = $db->prepare("INSERT INTO orders VALUES (?, ?, 'cash', 75000, '<script>alert(1)</script>', ?, NULL)");
    $times = [1 => '2026-10-14 23:59:59', 2 => '2026-10-15 09:59:59', 3 => '2026-10-15 10:00:00', 4 => '2026-10-15 10:59:54', 5 => '2026-10-15 10:59:59', 6 => '2026-10-16 00:00:00', 7 => '2026-10-15 10:30:00'];
    foreach ($times as $id => $time) {
        $insert->execute([$id, 'AX-TEST-' . $id, $time]);
        if ($id !== 7) {
            $db->exec('INSERT INTO order_items VALUES (' . $id . ', 75000)');
        }
    }
    $at = $now->modify('+1 hour');
    $adapter = mailDatabaseAdapter($db);
    $orders = fetchNotificationOrders($adapter, $at, $config['new_orders_since']);
    checkMail(array_map('intval', array_column($orders, 'id')) === [3, 4], 'SQL excludes yesterday, tomorrow, pre-activation, recent and unfinished orders.');
    $saved = fetchNotificationOrders($adapter, $at, $config['new_orders_since'], 5);
    checkMail(array_map('intval', array_column($saved, 'id')) === [5], 'Exact saved order is eligible immediately without the polling delay.');
    checkMail(fetchNotificationOrders($adapter, $at, $config['new_orders_since'], 2) === [], 'Checkout hook still excludes pre-activation orders.');
    checkMail(fetchNotificationOrders($adapter, $at, $config['new_orders_since'], 7) === [], 'Checkout hook never sends an order without saved items.');
    $db->exec('INSERT INTO order_items VALUES (7, 25000)');
    checkMail(count(fetchNotificationOrders($adapter, $at, $config['new_orders_since'])) === 2, 'Partial checkout is not sent.');
    $db->exec('INSERT INTO order_items VALUES (7, 50000)');
    checkMail(count(fetchNotificationOrders($adapter, $at, $config['new_orders_since'])) === 3, 'Checkout becomes eligible when all item totals match.');
    $db->exec("UPDATE orders SET order_type = 'bank_transfer' WHERE id = 4");
    $notifications = fetchOrderRevenueNotifications($adapter, $at, $config['new_orders_since']);
    checkMail(array_map('intval', array_column($notifications, 'id')) === [3, 4, 7], 'Revenue notifications retain the completed current-day trigger rules.');
    $orders = array_slice($notifications, 0, 2);
    $snapshot = $orders[0]['revenue_report'];
    checkMail($snapshot['order_count'] === 5 && $snapshot['revenue_total'] === 375000,
        'Report includes all of today, including pre-activation orders and both payment methods, excluding other days.');
    checkMail($snapshot['expense_total'] === 50000 && $snapshot['net_revenue'] === 325000,
        'Report subtracts only today expenses, excluding income and expenses on other days.');
    $immediate = fetchOrderRevenueNotifications($adapter, $at, $config['new_orders_since'], 5);
    checkMail(count($immediate) === 1 && $immediate[0]['revenue_report'] === $snapshot,
        'Checkout fetches the just-saved order immediately with the whole-day report.');
    $withoutReport = $orders[0];
    unset($withoutReport['revenue_report']);
    rejectMail(static function () use ($config, $withoutReport) {
        createOrderMailer($config, 'first@example.com', $withoutReport);
    }, 'Missing report must never produce a zero-filled or order-only email.');
    $wrongReport = $orders[0];
    $wrongReport['revenue_report']['date'] = '2026-10-14';
    rejectMail(static function () use ($config, $wrongReport) {
        createOrderMailer($config, 'first@example.com', $wrongReport);
    }, 'Reject a cached report from a different day.');
    $wrongReport['revenue_report'] = $snapshot;
    $wrongReport['revenue_report']['net_revenue']++;
    rejectMail(static function () use ($config, $wrongReport) {
        createOrderMailer($config, 'first@example.com', $wrongReport);
    }, 'Reject inconsistent net revenue.');
    $mail = createOrderMailer($config, 'first@example.com', $orders[0]);
    checkMail($mail->getToAddresses() === [['first@example.com', '']] && !$mail->getCcAddresses() && !$mail->getBccAddresses(), 'Recipient addresses remain private.');
    checkMail($mail->Helo === 'example.com' && $mail->Sender === $mail->From, 'Order notifications reuse aligned SMTP identity.');
    checkMail(strpos($mail->Subject, 'AX-TEST-3') !== false && strpos($mail->AltBody, '75.000 đ') !== false, 'Email includes saved order code and total.');
    checkMail(strpos($mail->AltBody, 'Tổng tiền bán hàng: 375.000 đ') !== false
        && strpos($mail->AltBody, 'Tổng khoản chi: 50.000 đ') !== false
        && strpos($mail->AltBody, 'Doanh thu sau chi phí: 325.000 đ') !== false
        && strpos($mail->AltBody, 'Số đơn hàng: 5') !== false, 'Text email includes cumulative daily totals and the correct remainder.');
    checkMail(strpos($mail->Body, '375.000 đ') !== false && strpos($mail->Body, '325.000 đ') !== false
        && strpos($mail->Body, 'sau mỗi đơn hàng mới thành công') !== false, 'HTML email displays the same totals and describes the per-order schedule.');
    checkMail(strpos($mail->Body, '<script>') === false && strpos($mail->Body, '&lt;script&gt;') !== false, 'HTML safely escapes order notes.');
    checkMail(strpos($mail->Body, $config['smtp']['password']) === false, 'Email never contains SMTP password.');
    checkMail($mail->MessageID === createOrderMailer($config, 'first@example.com', $orders[0])->MessageID
        && $mail->MessageID !== createOrderMailer($config, 'second@example.com', $orders[0])->MessageID, 'Message identity is stable per order/recipient.');
    checkMail($mail->preSend(), 'PHPMailer generates a valid MIME message without connecting to SMTP.');

    $subjects = [
        'Sổ Tay A Xíu | Xác nhận hệ thống thông báo [AXIU-20261015-193714-EEC7]',
        $mail->Subject,
        str_repeat('Đơn hàng A Xíu 😊 ', 15),
    ];
    foreach ($subjects as $subject) {
        $subject = trim($subject);
        $mail->Subject = $subject;
        $mail->FromName = trim(str_repeat('Sổ Tay A Xíu ', 8));
        checkMail($mail->preSend(), 'Generate MIME with a long Vietnamese header.');
        $mime = $mail->getSentMIMEMessage();
        $headers = explode("\r\n\r\n", $mime, 2)[0];
        preg_match_all('/=\?[^?\s]+\?[BQ]\?[^?\r\n]*\?=/i', $headers, $words);
        checkMail(count($words[0]) > 1 && max(array_map('strlen', $words[0])) <= 75, 'Every encoded word in the actual MIME headers respects RFC 2047.');
        preg_match('/^Subject: ([^\r\n]*(?:\r\n[ \t][^\r\n]*)*)/m', $headers, $subjectHeader);
        checkMail(iconv_mime_decode($subjectHeader[1], ICONV_MIME_DECODE_STRICT, 'UTF-8') === $subject, 'Strict MIME decoding preserves the complete Vietnamese subject.');
        preg_match('/^From: ([^\r\n]*(?:\r\n[ \t][^\r\n]*)*)/m', $headers, $fromHeader);
        checkMail(iconv_mime_decode($fromHeader[1], ICONV_MIME_DECODE_STRICT, 'UTF-8') === $mail->FromName . ' <sender@example.com>', 'Strict MIME decoding preserves a long sender display name.');
    }
    checkMail($mail->encodeHeader('Order AX-123') === 'Order AX-123', 'Plain ASCII headers keep the standard PHPMailer behavior.');

    $fetch = static function () use (&$orders) { return $orders; };
    $attempts = [];
    $first = deliverOrderNotifications($config, $at, $fetch, static function ($recipient, $order) use (&$attempts) {
        $attempts[] = [$recipient, $order['id']];
        if ($recipient === 'second@example.com') {
            throw new RuntimeException('Simulated SMTP rejection.');
        }
    }, $temporary);
    checkMail($first['sent'] === 2 && $first['failed'] === 2, 'Each current-day order goes to every recipient with independent results.');
    $db->exec("INSERT INTO orders VALUES (8, 'AX-TEST-8', 'bank_transfer', 125000, '', '2026-10-15 11:01:00', NULL)");
    $db->exec('INSERT INTO order_items VALUES (8, 125000)');
    $db->exec("INSERT INTO transactions VALUES ('expense', 25000, '2026-10-15')");
    $next = fetchOrderRevenueNotifications($adapter, $at->modify('+1 minute'), $config['new_orders_since'], 8);
    $nextReport = $next[0]['revenue_report'];
    checkMail($nextReport['order_count'] === 6 && $nextReport['revenue_total'] === 500000
        && $nextReport['expense_total'] === 75000 && $nextReport['net_revenue'] === 425000,
        'A later successful order recalculates today orders and updated expenses instead of reusing a daily cache.');
    foreach ($orders as &$order) {
        $order['revenue_report'] = $nextReport;
    }
    unset($order);
    $retry = deliverOrderNotifications($config, $at->modify('+1 minute'), $fetch, static function ($recipient, $order) use (&$attempts, $snapshot) {
        checkMail($recipient === 'second@example.com', 'Retry only failed recipients.');
        checkMail($order['revenue_report'] === $snapshot, 'Retries preserve the same totals already sent to the other recipient.');
        $attempts[] = [$recipient, $order['id']];
    }, $temporary);
    checkMail($retry['sent'] === 2 && !$retry['failed'] && count($attempts) === 6, 'Retry does not resend successful deliveries.');
    $nextDelivery = deliverOrderNotifications($config, $at->modify('+1 minute'), static function () use ($next) { return $next; },
        static function ($recipient, $order) use ($config, $nextReport) {
            checkMail($order['revenue_report'] === $nextReport, 'Every recipient of the later order receives its new snapshot.');
            checkMail(strpos(createOrderMailer($config, $recipient, $order)->AltBody, '425.000 đ') !== false, 'Later order email renders the recalculated remainder.');
        }, $temporary);
    checkMail($nextDelivery['sent'] === 2 && !$nextDelivery['failed'], 'Each new order sends a fresh cumulative report to all configured recipients.');
    $again = deliverOrderNotifications($config, $at, $fetch, static function () { throw new RuntimeException('Must not resend.'); }, $temporary);
    checkMail(!$again['sent'] && !$again['failed'], 'Repeated cron invocation sends nothing twice.');
    $moreRecipients = array_replace($config, ['recipients' => ['first@example.com', 'second@example.com', 'third@example.com']]);
    $added = deliverOrderNotifications($moreRecipients, $at, $fetch, static function () { throw new RuntimeException('Must not replay.'); }, $temporary);
    checkMail(!$added['sent'] && !$added['failed'], 'Adding a recipient does not replay previously observed orders.');
    $path = $temporary . '/orders-2026-10-15.json';
    $legacyState = json_decode(file_get_contents($path), true);
    unset($legacyState['orders'][4]['order']['revenue_report']);
    $legacyState['orders'][4]['recipients']['second@example.com'] = 'failed';
    file_put_contents($path, json_encode($legacyState));
    $legacyRetry = deliverOrderNotifications($config, $at, $fetch, static function ($recipient, $order) use ($nextReport) {
        checkMail($order['revenue_report'] === $nextReport, 'Older pending notifications receive their first revenue snapshot without resetting sent recipients.');
    }, $temporary);
    checkMail($legacyRetry['sent'] === 1 && !$legacyRetry['failed'], 'Upgrade retries an older failed entry once without replaying successful emails.');
    $lock = fopen($temporary . '/order-notifications.lock', 'c');
    flock($lock, LOCK_EX);
    $busy = deliverOrderNotifications($config, $at, $fetch, static function () {}, $temporary);
    checkMail($busy['busy'], 'Overlapping workers do not send concurrently.');
    flock($lock, LOCK_UN);
    fclose($lock);
    $off = array_replace($config, ['new_orders_enabled' => false]);
    $offResult = deliverOrderNotifications($off, $at, static function () { throw new RuntimeException('Must not fetch.'); }, static function () {}, $temporary);
    checkMail(!$offResult['sent'], 'Disabled feature performs no database reads or delivery.');
    $path = $temporary . '/orders-2026-10-15.json';
    $state = json_decode(file_get_contents($path), true);
    $state['orders'][3]['recipients']['first@example.com'] = 'sending';
    file_put_contents($path, json_encode($state));
    $uncertain = deliverOrderNotifications($config, $at, $fetch, static function () { throw new RuntimeException('Must not resend uncertain delivery.'); }, $temporary);
    checkMail($uncertain['uncertain'] === 1 && !$uncertain['sent'] && !$uncertain['failed'], 'Interrupted send is flagged instead of blindly resent.');
    file_put_contents($path, '{broken');
    rejectMail(static function () use ($config, $at, $fetch, $temporary) {
        deliverOrderNotifications($config, $at, $fetch, static function () {}, $temporary);
    }, 'Corrupt state stops sending.');
    $tomorrowOrders = fetchOrderRevenueNotifications($adapter, $at->modify('+1 day'), $config['new_orders_since']);
    checkMail(array_map('intval', array_column($tomorrowOrders, 'id')) === [6], 'Day boundary selects only the new current day.');
    checkMail($tomorrowOrders[0]['revenue_report']['order_count'] === 1
        && $tomorrowOrders[0]['revenue_report']['expense_total'] === 0
        && $tomorrowOrders[0]['revenue_report']['net_revenue'] === 75000, 'New day resets cumulative orders and supports no expenses.');
    $tomorrow = deliverOrderNotifications($config, $at->modify('+1 day'), static function () use ($orders, $tomorrowOrders) {
        return array_merge($orders, $tomorrowOrders);
    }, static function () {}, $temporary);
    checkMail($tomorrow['sent'] === 2, 'Delivery also rejects historical rows independently of the SQL query.');
    $db->exec("INSERT INTO transactions VALUES ('expense', 600000, '2026-10-15')");
    $negative = fetchOrderRevenueNotifications($adapter, $at->modify('+2 minutes'), $config['new_orders_since'], 8);
    checkMail(strpos(createOrderMailer($config, 'first@example.com', $negative[0])->AltBody, '-175.000 đ') !== false,
        'Expenses exceeding sales render a negative remainder rather than zero.');
    $db->exec('DROP TABLE transactions');
    checkMail(fetchOrderRevenueNotifications($adapter, $at, $config['new_orders_since'], 2) === [], 'No eligible trigger skips report queries.');
    rejectMail(static function () use ($adapter, $at, $config) {
        fetchOrderRevenueNotifications($adapter, $at, $config['new_orders_since'], 8);
    }, 'Expense query errors abort report creation instead of sending incorrect totals.');
    echo 'PASS: ' . $checks . " mail feature checks. No real emails sent; application database untouched.\n";
} finally {
    foreach (['settings.json', 'orders-2026-10-15.json', 'orders-2026-10-16.json', 'order-notifications.lock'] as $name) {
        if (is_file($temporary . '/' . $name)) {
            unlink($temporary . '/' . $name);
        }
    }
    rmdir($temporary);
}
