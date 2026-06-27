<?php
require_once 'config/db.php';
require_once 'config/helpers.php';
require_non_staff();

function summaryIsValidDate($value)
{
    return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
}

function summaryFetchOne($conn, $sql, $types, array $params, array $default = [])
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $default;
    }

    if ($types !== '' && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        return $default;
    }

    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: $default;
}

function summaryFetchAll($conn, $sql, $types, array $params)
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    if ($types !== '' && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $rows;
}

function summaryModeLink($mode, $date)
{
    return '?mode=' . rawurlencode($mode) . '&date=' . rawurlencode($date);
}

$mode = $_GET['mode'] ?? 'day';
if (!in_array($mode, ['day', 'week', 'month'], true)) {
    $mode = 'day';
}

$date = $_GET['date'] ?? today();
if (!summaryIsValidDate($date)) {
    $date = today();
}

if ($mode === 'week') {
    $start = date('Y-m-d', strtotime('monday this week', strtotime($date)));
    $end = date('Y-m-d', strtotime($start . ' +6 days'));
} elseif ($mode === 'month') {
    $start = date('Y-m-01', strtotime($date));
    $end = date('Y-m-t', strtotime($date));
} else {
    $start = $date;
    $end = $date;
}

$total = summaryFetchOne(
    $conn,
    "SELECT
        COALESCE(SUM(total_amount), 0) AS revenue,
        COUNT(*) AS orders,
        COALESCE(SUM(order_type = 'cash'), 0) AS cash,
        COALESCE(SUM(order_type = 'bank_transfer'), 0) AS bank_transfer
     FROM orders o
     WHERE DATE(o.created_at) BETWEEN ? AND ?",
    'ss',
    [$start, $end],
    ['revenue' => 0, 'orders' => 0, 'cash' => 0, 'bank_transfer' => 0]
);

$items = summaryFetchAll(
    $conn,
    "SELECT oi.product_name, SUM(oi.quantity) AS qty, SUM(oi.subtotal) AS amount
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     WHERE DATE(o.created_at) BETWEEN ? AND ?
     GROUP BY oi.product_name
     ORDER BY amount DESC, oi.product_name ASC",
    'ss',
    [$start, $end]
);

$expenseSummary = summaryFetchOne(
    $conn,
    "SELECT
        COALESCE(SUM(amount), 0) AS expense_total,
        COUNT(*) AS expense_count
     FROM transactions
     WHERE type = 'expense' AND transaction_date BETWEEN ? AND ?",
    'ss',
    [$start, $end],
    ['expense_total' => 0, 'expense_count' => 0]
);

$expenseItems = summaryFetchAll(
    $conn,
    "SELECT
        category,
        COUNT(*) AS entry_count,
        SUM(amount) AS amount
     FROM transactions
     WHERE type = 'expense' AND transaction_date BETWEEN ? AND ?
     GROUP BY category
     ORDER BY amount DESC, category ASC",
    'ss',
    [$start, $end]
);

$revenueTotal = (int) ($total['revenue'] ?? 0);
$expenseTotal = (int) ($expenseSummary['expense_total'] ?? 0);
$netRevenue = $revenueTotal - $expenseTotal;

include_once 'header.php';
?>

<div class="date">
    📊 Tổng kết
    <?= date('d/m/Y', strtotime($start)) ?>
    <?= $start !== $end ? ' - ' . date('d/m/Y', strtotime($end)) : '' ?>
</div>

<div class="tabs">
    <a class="tab <?= $mode === 'day' ? 'active' : '' ?>" href="<?= h(summaryModeLink('day', $date)) ?>">Ngày</a>
    <a class="tab <?= $mode === 'week' ? 'active' : '' ?>" href="<?= h(summaryModeLink('week', $date)) ?>">Tuần</a>
    <a class="tab <?= $mode === 'month' ? 'active' : '' ?>" href="<?= h(summaryModeLink('month', $date)) ?>">Tháng</a>
</div>

<form>
    <input type="hidden" name="mode" value="<?= h($mode) ?>">
    <label for="date_id" class="sr-only">Chọn ngày</label>
    <input class="input" type="date" name="date" id="date_id" value="<?= h($date) ?>" onchange="this.form.submit()">
    <small class="note">* Chọn ngày để xem</small>
</form>

<div class="card">
    <div class="section-title">Tổng quan doanh thu và chi phí</div>
    <div class="mini-grid">
        <div class="mini">
            <div class="emoji">💰</div>
            <div>
                <div class="small">Doanh thu bán hàng</div>
                <b><?= moneyVND($revenueTotal) ?></b>
            </div>
        </div>
        <div class="mini">
            <div class="emoji">🧾</div>
            <div>
                <div class="small">Chi phí đã ghi nhận</div>
                <b><?= moneyVND($expenseTotal) ?></b>
            </div>
        </div>
    </div>

    <hr style="border:0;border-top:1px solid #f2dfd2;margin:16px 0">

    <div class="between">
        <span><b>Doanh thu cuối sau chi phí</b></span>
        <div class="big-total<?= $netRevenue < 0 ? ' danger' : '' ?>"><?= moneyVND($netRevenue) ?></div>
    </div>
    <div class="small">Công thức: Doanh thu bán hàng - Chi phí đã ghi nhận.</div>
    <div class="between">
        <span>Tổng số đơn</span>
        <b><?= (int) ($total['orders'] ?? 0) ?> đơn</b>
    </div>
    <div class="between">
        <span>Tiền mặt</span>
        <b><?= (int) ($total['cash'] ?? 0) ?> đơn</b>
    </div>
    <div class="between">
        <span>Chuyển khoản</span>
        <b><?= (int) ($total['bank_transfer'] ?? 0) ?> đơn</b>
    </div>
    <div class="between">
        <span>Số khoản chi</span>
        <b><?= (int) ($expenseSummary['expense_count'] ?? 0) ?> khoản</b>
    </div>
</div>

<div class="card">
    <div class="section-title">Chi tiết doanh thu</div>
    <table class="table">
        <tr>
            <th>Món</th>
            <th class="right">Số lượng</th>
            <th class="right">Tiền</th>
        </tr>
        <?php if (empty($items)): ?>
            <tr>
                <td colspan="3" class="small">Chưa có doanh thu trong khoảng thời gian này.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= h($item['product_name']) ?></td>
                    <td class="right"><?= (int) ($item['qty'] ?? 0) ?></td>
                    <td class="right"><?= moneyVND($item['amount'] ?? 0) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>
</div>

<div class="card">
    <div class="section-title">Chi tiết chi phí</div>
    <table class="table">
        <tr>
            <th>Hạng mục</th>
            <th class="right">Số phiếu</th>
            <th class="right">Tiền</th>
        </tr>
        <?php if (empty($expenseItems)): ?>
            <tr>
                <td colspan="3" class="small">Chưa có chi phí trong khoảng thời gian này.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($expenseItems as $expense): ?>
                <tr>
                    <td><?= h(trim((string) ($expense['category'] ?? '')) ?: 'Chi phí khác') ?></td>
                    <td class="right"><?= (int) ($expense['entry_count'] ?? 0) ?></td>
                    <td class="right"><?= moneyVND($expense['amount'] ?? 0) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>
</div>

<?php include_once 'footer.php'; ?>
