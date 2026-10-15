<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/revenue.php';
require dirname(__DIR__) . '/vendor/autoload.php';

use function AXiu\Notifications\loadConfig;
use function AXiu\Notifications\validateDeliveryConfig;
use function AXiu\Notifications\isDue;
use function AXiu\Notifications\fetchReport;
use function AXiu\Notifications\renderRevenueEmail;
use function AXiu\Notifications\createMailer;
use function AXiu\Notifications\deliverDailyReport;

$checks = 0;
function check($condition, $message)
{
    global $checks;
    $checks++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expectFailure(callable $action, $message)
{
    try {
        $action();
    } catch (Throwable $expected) {
        check(true, $message);
        return;
    }
    check(false, $message);
}

// Adapt a real, in-memory SQL database to the mysqli methods used by fetchReport.
// This executes the production SELECT without touching the application's DB.
function databaseAdapter(PDO $database)
{
    return new class($database) {
        private $database;
        public function __construct($database) { $this->database = $database; }
        public function prepare($sql)
        {
            return new class($this->database->prepare($sql)) {
                private $statement;
                private $parameters = [];
                public function __construct($statement) { $this->statement = $statement; }
                public function bind_param($types, &...$parameters) { $this->parameters = $parameters; }
                public function execute() { return $this->statement->execute($this->parameters); }
                public function get_result() { return $this; }
                public function fetch_assoc() { return $this->statement->fetch(PDO::FETCH_ASSOC); }
                public function close() { $this->statement->closeCursor(); }
            };
        }
    };
}

$temporary = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'axiu-revenue-test-' . bin2hex(random_bytes(8));
mkdir($temporary, 0700);
try {
    $config = loadConfig();
    $config['enabled'] = true;
    $config['recipients'] = ['first@example.com', 'SECOND@example.com', ' first@example.com '];
    $config['smtp'] = [
        'host' => 'mail.example.com', 'port' => 465, 'encryption' => 'ssl',
        'username' => 'report@example.com', 'password' => 'test-only-password',
        'from_email' => 'report@example.com', 'from_name' => 'Sổ Tay A Xíu',
    ];
    $config = validateDeliveryConfig($config);
    check($config['recipients'] === ['first@example.com', 'second@example.com'], 'Normalize and deduplicate recipients.');
    $invalid = $config;
    $invalid['recipients'][] = "injected@example.com\r\nBcc: other@example.com";
    expectFailure(static function () use ($invalid) { validateDeliveryConfig($invalid); }, 'Reject header injection.');
    $invalid['recipients'] = [];
    expectFailure(static function () use ($invalid) { validateDeliveryConfig($invalid); }, 'Require recipients.');
    $invalid = $config;
    $invalid['smtp']['encryption'] = '';
    expectFailure(static function () use ($invalid) { validateDeliveryConfig($invalid); }, 'Require SMTP encryption.');
    $invalid = $config;
    $invalid['smtp']['password'] = '';
    expectFailure(static function () use ($invalid) { validateDeliveryConfig($invalid); }, 'Require SMTP credentials.');

    $config['send_time'] = '23:00';
    check(!isDue($config, new DateTimeImmutable('2026-10-15T15:59:59+00:00')), 'Do not send before local delivery time.');
    check(isDue($config, new DateTimeImmutable('2026-10-15T16:00:00+00:00')), 'Send at 23:00 in UTC+7.');
    check(!isDue($config, new DateTimeImmutable('2026-10-15T17:00:00+00:00')), 'Reset schedule at local midnight.');

    $database = new PDO('sqlite::memory:');
    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $database->exec("CREATE TABLE orders (total_amount INTEGER, created_at TEXT, order_type TEXT DEFAULT 'cash')");
    $database->exec('CREATE TABLE transactions (type TEXT, amount INTEGER, transaction_date TEXT)');
    $database->exec("INSERT INTO orders (total_amount, created_at) VALUES
        (9000000, '2026-10-14 23:59:59'), (150000, '2026-10-15 00:00:00'),
        (250000, '2026-10-15 23:59:59'), (8000000, '2026-10-16 00:00:00')");
    $database->exec("INSERT INTO transactions VALUES
        ('expense', 50000, '2026-10-15'), ('expense', 25000, '2026-10-15'),
        ('income', 7000000, '2026-10-15'), ('expense', 6000000, '2026-10-14')");
    $now = new DateTimeImmutable('2026-10-15T23:00:00+07:00');
    $report = fetchReport(databaseAdapter($database), $now);
    check($report['net_revenue'] === 325000 && $report['revenue_total'] === 400000
        && $report['expense_total'] === 75000 && $report['order_count'] === 2,
        'Daily report excludes other dates and income entries, including midnight boundaries.');

    // Check against the actual aggregation queries currently present in summary.php.
    $summarySource = file_get_contents(dirname(__DIR__, 2) . '/summary.php');
    preg_match('/\$total = summaryFetchOne\(\s*\$conn,\s*"(.*?)",/s', $summarySource, $ordersMatch);
    preg_match('/\$expenseSummary = summaryFetchOne\(\s*\$conn,\s*"(.*?)",/s', $summarySource, $expensesMatch);
    check(isset($ordersMatch[1], $expensesMatch[1]), 'Find existing summary aggregation queries.');
    $statement = $database->prepare($ordersMatch[1]);
    $statement->execute(['2026-10-15', '2026-10-15']);
    $summaryRevenue = (int) $statement->fetch(PDO::FETCH_ASSOC)['revenue'];
    $statement = $database->prepare($expensesMatch[1]);
    $statement->execute(['2026-10-15', '2026-10-15']);
    $summaryExpense = (int) $statement->fetch(PDO::FETCH_ASSOC)['expense_total'];
    check($report['net_revenue'] === $summaryRevenue - $summaryExpense, 'Email net revenue matches summary.php.');

    $empty = fetchReport(databaseAdapter($database), $now->modify('+3 days'));
    check($empty['net_revenue'] === 0 && $empty['order_count'] === 0, 'Report zero on an empty day.');
    $database->exec("INSERT INTO transactions VALUES ('expense', 500000, '2026-10-15')");
    $negative = fetchReport(databaseAdapter($database), $now);
    check($negative['net_revenue'] === -175000, 'Preserve a negative net revenue.');
    $failedDatabase = new class { public function prepare($sql) { return false; } };
    expectFailure(static function () use ($failedDatabase, $now) { fetchReport($failedDatabase, $now); }, 'Query failures must not silently become zero revenue.');

    $email = renderRevenueEmail($negative, '<script>alert("x")</script> & A Xíu');
    check(strpos($email['html'], '<script>') === false && strpos($email['html'], '&lt;script&gt;') !== false, 'Escape dynamic HTML.');
    check(strpos($email['html'], '-175.000 đ') !== false && strpos($email['html'], '#b42318') !== false, 'Show negative amount in red.');
    check(strpos($email['subject'], '-175.000 đ') !== false && strpos($email['text'], '-175.000 đ') !== false, 'Subject and plain text contain the net amount.');
    $mail = createMailer($config, 'first@example.com', $report);
    check($mail->preSend(), 'Build valid email without a network connection.');
    check(count($mail->getToAddresses()) === 1 && !$mail->getBccAddresses() && !$mail->getCcAddresses(), 'Each email has only one recipient.');
    check(strpos($mail->getSentMIMEMessage(), 'multipart/alternative') !== false, 'Include HTML and plain text alternatives.');
    check($mail->SMTPSecure === 'ssl' && $mail->SMTPAuth && $mail->Port === 465, 'Use authenticated cPanel SSL SMTP.');
    check($mail->Helo === 'example.com' && $mail->Hostname === 'example.com', 'Use the sending domain for SMTP identity, independent of local machine names.');
    check($mail->Sender === $mail->From && $mail->From === $config['smtp']['from_email'], 'Keep envelope sender aligned with the visible From address.');
    check($mail->MessageID === createMailer($config, 'first@example.com', $report)->MessageID, 'Keep a stable Message-ID for retries.');
    check($mail->MessageID !== createMailer($config, 'second@example.com', $report)->MessageID, 'Use separate Message-IDs per recipient.');

    $fetchCount = 0;
    $fetch = static function ($at) use (&$fetchCount, $report) {
        $fetchCount++;
        return array_replace($report, ['date' => $at->format('Y-m-d'), 'generated_at' => $at->format(DateTimeImmutable::ATOM)]);
    };
    $attempts = [];
    $sender = static function ($recipient, $snapshot) use (&$attempts) {
        $attempts[] = [$recipient, $snapshot['net_revenue']];
        if ($recipient === 'second@example.com') {
            throw new RuntimeException('Simulated SMTP failure.');
        }
    };
    $first = deliverDailyReport($config, $now, $fetch, $sender, $temporary);
    check($first['sent'] === 1 && count($first['failed']) === 1, 'Track partial delivery per recipient.');
    check($fetchCount === 1 && count($attempts) === 2, 'Compute one report for all recipients.');
    $state = json_decode(file_get_contents($temporary . '/2026-10-15.json'), true);
    check(count($state['sent']) === 1, 'Persist accepted recipient immediately.');

    $retry = deliverDailyReport($config, $now->modify('+1 minute'), $fetch, static function ($recipient, $snapshot) use (&$attempts, $report) {
        check($snapshot === $report, 'Reuse original data for retries.');
        $attempts[] = [$recipient, $snapshot['net_revenue']];
    }, $temporary);
    check($retry['sent'] === 1 && $retry['skipped'] === 1 && !$retry['failed'], 'Retry only the failed address.');
    check($fetchCount === 1 && count($attempts) === 3, 'Retry does not re-query revenue or repeat accepted recipient.');
    $again = deliverDailyReport($config, $now, $fetch, $sender, $temporary);
    check($again['sent'] === 0 && $again['skipped'] === 2 && count($attempts) === 3, 'Repeated cron runs do not resend.');

    $overlapLock = fopen($temporary . '/daily-revenue.lock', 'c');
    flock($overlapLock, LOCK_EX);
    $overlap = deliverDailyReport($config, $now, $fetch, $sender, $temporary);
    check($overlap['busy'] && count($attempts) === 3, 'Skip overlapping job while lock is held.');
    flock($overlapLock, LOCK_UN);
    fclose($overlapLock);

    $tomorrow = deliverDailyReport($config, $now->modify('+1 day'), $fetch, static function () {}, $temporary);
    check($tomorrow['sent'] === 2 && $fetchCount === 2, 'New day sends a new report to all recipients.');
    file_put_contents($temporary . '/2026-10-15.json', '{broken');
    expectFailure(static function () use ($config, $now, $fetch, $sender, $temporary) {
        deliverDailyReport($config, $now, $fetch, $sender, $temporary);
    }, 'Do not resend when state file is corrupted.');
    $state['report']['net_revenue'] = 999999;
    file_put_contents($temporary . '/2026-10-15.json', json_encode($state));
    expectFailure(static function () use ($config, $now, $fetch, $sender, $temporary) {
        deliverDailyReport($config, $now, $fetch, $sender, $temporary);
    }, 'Reject inconsistent totals in saved report.');
    unset($state['report']['net_revenue']);
    file_put_contents($temporary . '/2026-10-15.json', json_encode($state));
    expectFailure(static function () use ($config, $now, $fetch, $sender, $temporary) {
        deliverDailyReport($config, $now, $fetch, $sender, $temporary);
    }, 'Reject missing totals in saved report.');

    // A DB failure must leave no daily state so the next cron can retry cleanly.
    $later = $now->modify('+2 days');
    expectFailure(static function () use ($config, $later, $sender, $temporary) {
        deliverDailyReport($config, $later, static function () {
            throw new RuntimeException('Simulated database failure.');
        }, $sender, $temporary);
    }, 'Database failure aborts delivery.');
    check(!is_file($temporary . '/2026-10-17.json'), 'Do not record a failed DB read as a daily report.');

    echo 'PASS: ' . $checks . " checks. No real emails sent; application database untouched.\n";
} finally {
    // Delete only known files in the unique test directory created above.
    foreach (['2026-10-15.json', '2026-10-16.json', '2026-10-17.json', 'daily-revenue.lock'] as $name) {
        $path = $temporary . DIRECTORY_SEPARATOR . $name;
        if (is_file($path)) {
            unlink($path);
        }
    }
    rmdir($temporary);
}
