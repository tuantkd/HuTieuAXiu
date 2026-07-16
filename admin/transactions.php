<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

requireRole(ADMIN_ROLE);

$page_title = 'Thu chi';
$today = admin_today();

define('LOCATION_HEADER', 'Location: ');
define('TRANSACTIONS_PAGE', 'transactions.php');

$requestedDate = $_GET['date'] ?? '';
$selectedDate = admin_is_valid_date($requestedDate) ? $requestedDate : '';
$latestOrderDate = admin_scalar('SELECT MAX(DATE(created_at)) FROM orders', '', [], null);
$latestTransactionDate = admin_scalar('SELECT MAX(transaction_date) FROM transactions', '', [], null);
$latestActivityDate = admin_max_iso_date($latestOrderDate, $latestTransactionDate);

if ($selectedDate === '') {
    $selectedDate = $latestActivityDate ?? $today;
}

$isShowingFallbackDate = $requestedDate === '' && $selectedDate !== $today && $latestActivityDate !== null;
$summaryDateLabel = $selectedDate === $today ? 'hôm nay' : 'ngày ' . date('d/m/Y', strtotime($selectedDate));
$rangeStartDate = date('Y-m-d', strtotime($selectedDate . ' -6 day'));
$rangeLabel = date('d/m/Y', strtotime($rangeStartDate)) . ' - ' . date('d/m/Y', strtotime($selectedDate));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transactionType = $_POST['type'] ?? '';
    $category = trim((string) ($_POST['category'] ?? ''));
    $amount = (int) ($_POST['amount'] ?? 0);
    $note = trim((string) ($_POST['note'] ?? ''));
    $transactionDate = $_POST['transaction_date'] ?? $selectedDate;
    $redirectUrl = TRANSACTIONS_PAGE . '?date=' . rawurlencode($selectedDate);

    $allowedTypes = ['income', 'expense'];
    if (!in_array($transactionType, $allowedTypes, true)) {
        adminFlashSet('Vui lòng chọn loại thu chi hợp lệ.', 'error');
        header(LOCATION_HEADER . $redirectUrl);
        exit;
    }

    if ($category === '') {
        adminFlashSet('Vui lòng nhập mục thu chi.', 'error');
        header(LOCATION_HEADER . $redirectUrl);
        exit;
    }

    if ($amount <= 0) {
        adminFlashSet('Số tiền phải lớn hơn 0.', 'error');
        header(LOCATION_HEADER . $redirectUrl);
        exit;
    }

    $transactionDate = admin_is_valid_date($transactionDate) ? $transactionDate : $selectedDate;

    admin_execute(
        'INSERT INTO transactions (type, category, amount, note, transaction_date) VALUES (?, ?, ?, ?, ?)',
        'ssiss',
        [$transactionType, $category, $amount, $note, $transactionDate]
    );

    adminFlashSet('Ghi nhận thu chi thành công.');
    header(LOCATION_HEADER . TRANSACTIONS_PAGE . '?date=' . rawurlencode($transactionDate));
    exit;
}

$revenueForDate = admin_scalar(
    'SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(created_at) = ?',
    's',
    [$selectedDate]
);

$transactionsForDate = admin_fetch_all(
    'SELECT id, type, category, amount, note, transaction_date, created_at
     FROM transactions
     WHERE transaction_date = ?
     ORDER BY created_at DESC, id DESC',
    's',
    [$selectedDate]
);

$ingredientExpenseForDate = 0;
$expenseForDate = 0;
$otherIncomeForDate = 0;

foreach ($transactionsForDate as $transaction) {
    $amount = (int) ($transaction['amount'] ?? 0);
    $type = (string) ($transaction['type'] ?? '');

    if ($type === 'expense') {
        $expenseForDate += $amount;
        if (admin_is_ingredient_expense($transaction)) {
            $ingredientExpenseForDate += $amount;
        }
        continue;
    }

    if ($type === 'income' && !admin_is_sales_income($transaction)) {
        $otherIncomeForDate += $amount;
    }
}

$netProfitForDate = $revenueForDate + $otherIncomeForDate - $expenseForDate;

$recentCashFlow = admin_fetch_all(
    'SELECT DATE(created_at) AS sale_date, COUNT(*) AS order_count, SUM(total_amount) AS amount
     FROM orders
     WHERE DATE(created_at) BETWEEN ? AND ?
     GROUP BY DATE(created_at)
     ORDER BY sale_date DESC',
    'ss',
    [$rangeStartDate, $selectedDate]
);

$recentTransactions = admin_fetch_all(
    'SELECT id, type, category, amount, note, transaction_date, created_at
     FROM transactions
     WHERE transaction_date BETWEEN ? AND ?
     ORDER BY transaction_date DESC, created_at DESC',
    'ss',
    [$rangeStartDate, $selectedDate]
);

define('ADMIN_APP', true);
include_once __DIR__ . '/layout/header.php';
?>
<div class="panel form-card form-card--single">
    <div class="panel-header">
        <div>
            <h3>Xem thu chi theo ngày</h3>
            <div class="helper-text">
                <?php if ($isShowingFallbackDate): ?>
                    Hôm nay chưa có phát sinh nên hệ thống đang hiển thị ngày gần nhất có dữ liệu.
                <?php else: ?>
                    Chọn ngày để đối chiếu doanh thu, chi phí và lợi nhuận tạm.
                <?php endif; ?>
            </div>
        </div>
    </div>
    <form method="get">
        <div class="field">
            <label for="date">Ngày cần xem</label>
            <input id="date" type="date" name="date" value="<?= adminH($selectedDate) ?>" required>
        </div>
        <div class="field" style="display:flex;align-items:flex-end;">
            <button type="submit" class="button">Xem số liệu</button>
        </div>
    </form>
</div>

<div class="report-grid">
    <div class="stat-card">
        <div class="stat-label">Doanh thu bán hàng <?= adminH($summaryDateLabel) ?></div>
        <div class="big-number"><?= admin_money($revenueForDate) ?></div>
        <div class="muted">Dựa trên đơn đã lưu trong ngày đang xem.</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Tổng chi phí <?= adminH($summaryDateLabel) ?></div>
        <div class="big-number"><?= admin_money($expenseForDate) ?></div>
        <div class="muted">Trong đó nguyên liệu: <?= admin_money($ingredientExpenseForDate) ?>.</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Lợi nhuận tạm <?= adminH($summaryDateLabel) ?></div>
        <div class="big-number"><?= admin_money($netProfitForDate) ?></div>
        <div class="muted">Doanh thu đơn hàng + thu khác không trùng doanh thu - tổng chi phí.</div>
    </div>
</div>

<div class="panel form-card form-card--single">
    <div class="panel-header">
        <h3>Ghi nhận thu chi nhập nguyên liệu</h3>
    </div>
    <form method="post">
        <div class="field">
            <label for="type">Loại giao dịch</label>
            <select id="type" name="type" required>
                <option value="income">Thu</option>
                <option value="expense" selected>Chi</option>
            </select>
        </div>
        <div class="field">
            <label for="category">Mục thu/chi</label>
            <input id="category" type="text" name="category" value="Bánh tráng" required
                placeholder="Nguyên liệu, Tiền điện nước, Bán hàng...">
        </div>
        <div class="field">
            <label for="amount">Số tiền</label>
            <input id="amount" type="number" name="amount" min="0" step="1000" required>
        </div>
        <div class="field">
            <label for="transaction_date">Ngày giao dịch</label>
            <input id="transaction_date" type="date" name="transaction_date" value="<?= adminH($selectedDate) ?>"
                required>
        </div>
        <div class="field field--full">
            <label for="note">Ghi chú</label>
            <textarea id="note" name="note" placeholder="Nhập ghi chú chi tiết nếu cần..."></textarea>
        </div>
        <div class="field" style="display:flex;align-items:flex-end;">
            <button type="submit" class="button">Lưu</button>
        </div>
    </form>
</div>

<div class="panel">
    <div class="panel-header">
        <div>
            <h3>Tổng hợp doanh thu bán hàng 7 ngày</h3>
            <div class="helper-text">Dữ liệu từ <?= adminH($rangeLabel) ?>, dựa trên đơn hàng đã lưu.</div>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Ngày</th>
                    <th>Số đơn</th>
                    <th>Doanh thu</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentCashFlow)): ?>
                    <tr>
                        <td colspan="3">
                            <div class="empty-state">Chưa có đơn hàng trong khoảng <?= adminH($rangeLabel) ?>.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentCashFlow as $row): ?>
                        <tr>
                            <td><?= adminH(date('d/m/Y', strtotime($row['sale_date']))) ?></td>
                            <td><?= (int) $row['order_count'] ?></td>
                            <td><?= admin_money($row['amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <div>
            <h3>Giao dịch thu chi 7 ngày</h3>
            <div class="helper-text">Danh sách giao dịch từ <?= adminH($rangeLabel) ?>.</div>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Ngày</th>
                    <th>Loại</th>
                    <th>Mục</th>
                    <th>Số tiền</th>
                    <th>Ghi chú</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentTransactions)): ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">Chưa có giao dịch thu chi trong khoảng <?= adminH($rangeLabel) ?>.
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentTransactions as $transaction): ?>
                        <tr>
                            <td><?= adminH(date('d/m/Y', strtotime($transaction['transaction_date']))) ?></td>
                            <td>
                                <span class="badge <?= $transaction['type'] === 'income' ? 'success' : 'warning' ?>">
                                    <?= adminH($transaction['type'] === 'income' ? 'Thu' : 'Chi') ?>
                                </span>
                            </td>
                            <td><?= adminH($transaction['category']) ?></td>
                            <td><?= admin_money($transaction['amount']) ?></td>
                            <td><?= adminH($transaction['note'] ?: '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once __DIR__ . '/layout/footer.php'; ?>
