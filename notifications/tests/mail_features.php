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
    $mail = createOrderMailer($config, 'first@example.com', $orders[0]);
    checkMail($mail->getToAddresses() === [['first@example.com', '']] && !$mail->getCcAddresses() && !$mail->getBccAddresses(), 'Recipient addresses remain private.');
    checkMail(strpos($mail->Subject, 'AX-TEST-3') !== false && strpos($mail->AltBody, '75.000 đ') !== false, 'Email includes saved order code and total.');
    checkMail(strpos($mail->Body, '<script>') === false && strpos($mail->Body, '&lt;script&gt;') !== false, 'HTML safely escapes order notes.');
    checkMail(strpos($mail->Body, $config['smtp']['password']) === false, 'Email never contains SMTP password.');
    checkMail($mail->MessageID === createOrderMailer($config, 'first@example.com', $orders[0])->MessageID
        && $mail->MessageID !== createOrderMailer($config, 'second@example.com', $orders[0])->MessageID, 'Message identity is stable per order/recipient.');
    checkMail($mail->preSend(), 'PHPMailer generates a valid MIME message without connecting to SMTP.');

    $fetch = static function () use ($orders) { return $orders; };
    $attempts = [];
    $first = deliverOrderNotifications($config, $at, $fetch, static function ($recipient, $order) use (&$attempts) {
        $attempts[] = [$recipient, $order['id']];
        if ($recipient === 'second@example.com') {
            throw new RuntimeException('Simulated SMTP rejection.');
        }
    }, $temporary);
    checkMail($first['sent'] === 2 && $first['failed'] === 2, 'Each current-day order goes to every recipient with independent results.');
    $retry = deliverOrderNotifications($config, $at->modify('+1 minute'), $fetch, static function ($recipient, $order) use (&$attempts) {
        checkMail($recipient === 'second@example.com', 'Retry only failed recipients.');
        $attempts[] = [$recipient, $order['id']];
    }, $temporary);
    checkMail($retry['sent'] === 2 && !$retry['failed'] && count($attempts) === 6, 'Retry does not resend successful deliveries.');
    $again = deliverOrderNotifications($config, $at, $fetch, static function () { throw new RuntimeException('Must not resend.'); }, $temporary);
    checkMail(!$again['sent'] && !$again['failed'], 'Repeated cron invocation sends nothing twice.');
    $moreRecipients = array_replace($config, ['recipients' => ['first@example.com', 'second@example.com', 'third@example.com']]);
    $added = deliverOrderNotifications($moreRecipients, $at, $fetch, static function () { throw new RuntimeException('Must not replay.'); }, $temporary);
    checkMail(!$added['sent'] && !$added['failed'], 'Adding a recipient does not replay previously observed orders.');
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
    $tomorrowOrders = fetchNotificationOrders($adapter, $at->modify('+1 day'), $config['new_orders_since']);
    checkMail(array_map('intval', array_column($tomorrowOrders, 'id')) === [6], 'Day boundary selects only the new current day.');
    $tomorrow = deliverOrderNotifications($config, $at->modify('+1 day'), static function () use ($orders, $tomorrowOrders) {
        return array_merge($orders, $tomorrowOrders);
    }, static function () {}, $temporary);
    checkMail($tomorrow['sent'] === 2, 'Delivery also rejects historical rows independently of the SQL query.');
    echo 'PASS: ' . $checks . " mail feature checks. No real emails sent; application database untouched.\n";
} finally {
    foreach (['settings.json', 'orders-2026-10-15.json', 'orders-2026-10-16.json', 'order-notifications.lock'] as $name) {
        if (is_file($temporary . '/' . $name)) {
            unlink($temporary . '/' . $name);
        }
    }
    rmdir($temporary);
}
