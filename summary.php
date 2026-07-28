<?php
require_once 'config/db.php';
require_once 'config/helpers.php';
requireLogin();
if (!defined('DATE_EXPENSE')) {
    define('DATE_EXPENSE', 'd/m/Y');
}
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

function summaryModeLink($mode, $date, $startDate = '', $endDate = '')
{
    $params = ['mode' => $mode];

    if ($mode === 'range') {
        $params['start_date'] = $startDate;
        $params['end_date'] = $endDate;
    } else {
        $params['date'] = $date;
    }

    return '?' . http_build_query(array_filter($params, static function ($value) {
        return $value !== '';
    }));
}

function summaryNormalizeRange($fallbackDate, $startDate, $endDate)
{
    $startDate = summaryIsValidDate($startDate) ? $startDate : '';
    $endDate = summaryIsValidDate($endDate) ? $endDate : '';

    if ($startDate === '' && $endDate !== '') {
        $startDate = $endDate;
    }

    if ($endDate === '' && $startDate !== '') {
        $endDate = $startDate;
    }

    if ($startDate === '') {
        $startDate = $fallbackDate;
    }

    if ($endDate === '') {
        $endDate = $fallbackDate;
    }

    return [$startDate, $endDate, $startDate > $endDate];
}

function summaryFormatDisplayDate($value)
{
    if (!summaryIsValidDate($value)) {
        return '--/--/----';
    }

    return date(DATE_EXPENSE, strtotime($value));
}

$mode = $_GET['mode'] ?? 'day';
if (!in_array($mode, ['day', 'week', 'month', 'range'], true)) {
    $mode = 'day';
}

$date = $_GET['date'] ?? today();
if (!summaryIsValidDate($date)) {
    $date = today();
}

$rangeStartInput = $_GET['start_date'] ?? '';
$rangeEndInput = $_GET['end_date'] ?? '';
[$rangeStart, $rangeEnd, $hasInvalidRangeOrder] = summaryNormalizeRange($date, $rangeStartInput, $rangeEndInput);
$hasSubmittedRange = summaryIsValidDate($rangeStartInput) || summaryIsValidDate($rangeEndInput);

if ($mode === 'week') {
    $start = date('Y-m-d', strtotime('monday this week', strtotime($date)));
    $end = date('Y-m-d', strtotime($start . ' +6 days'));
} elseif ($mode === 'month') {
    $start = date('Y-m-01', strtotime($date));
    $end = date('Y-m-t', strtotime($date));
} elseif ($mode === 'range') {
    if ($hasInvalidRangeOrder) {
        toast('warning', 'Ngày bắt đầu không được lớn hơn ngày kết thúc.');
        $start = $date;
        $end = $date;
    } else {
        $start = $rangeStart;
        $end = $rangeEnd;
    }
    $date = $end;
} else {
    $start = $date;
    $end = $date;
}

$rangeStartValue = $hasSubmittedRange ? $rangeStart : $start;
$rangeEndValue = $hasSubmittedRange ? $rangeEnd : $end;

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

$revenueEntries = summaryFetchAll(
    $conn,
    "SELECT
        o.id,
        o.order_code,
        o.order_type,
        o.total_amount,
        o.note,
        o.created_at,
        GROUP_CONCAT(
            CONCAT(oi.product_name, ' x ', oi.quantity)
            ORDER BY oi.id ASC
            SEPARATOR ' • '
        ) AS item_summary
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id = o.id
     WHERE DATE(o.created_at) BETWEEN ? AND ?
     GROUP BY o.id, o.order_code, o.order_type, o.total_amount, o.note, o.created_at
     ORDER BY o.created_at DESC, o.id DESC",
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

$expenseEntries = summaryFetchAll(
    $conn,
    "SELECT
        id,
        category,
        amount,
        note,
        transaction_date
     FROM transactions
     WHERE type = 'expense' AND transaction_date BETWEEN ? AND ?
     ORDER BY transaction_date DESC, id DESC",
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
    <?= date(DATE_EXPENSE, strtotime($start)) ?>
    <?= $start !== $end ? ' - ' . date(DATE_EXPENSE, strtotime($end)) : '' ?>
</div>

<div class="tabs">
    <a class="tab <?= $mode === 'day' ? 'active' : '' ?>" href="<?= h(summaryModeLink('day', $date)) ?>">Ngày</a>
    <a class="tab <?= $mode === 'week' ? 'active' : '' ?>" href="<?= h(summaryModeLink('week', $date)) ?>">Tuần</a>
    <a class="tab <?= $mode === 'month' ? 'active' : '' ?>" href="<?= h(summaryModeLink('month', $date)) ?>">Tháng</a>
    <a class="tab <?= $mode === 'range' ? 'active' : '' ?>"
        href="<?= h(summaryModeLink('range', $date, $rangeStartValue, $rangeEndValue)) ?>">Khoảng</a>
</div>

<div class="card">
    <div class="section-title">Bộ lọc thời gian</div>

    <?php if ($mode !== 'range'): ?>
        <form method="get" class="field-block">
            <input type="hidden" name="mode" value="<?= h($mode) ?>">
            <label for="date_id" class="form-label">Chọn ngày</label>
            <input class="input" type="date" name="date" id="date_id" value="<?= h($date) ?>" onchange="this.form.submit()">
            <small class="note">* Áp dụng cho chế độ ngày, tuần và tháng.</small>
        </form>

        <hr style="border:0;border-top:1px solid #f2dfd2;margin:16px 0">
    <?php endif; ?>

    <form method="get" class="field-block expense-filter" id="summary-range-form">
        <input type="hidden" name="mode" value="range">

        <div class="field-row">
            <div class="field-block">
                <label for="start_date_id" class="form-label">Từ ngày</label>
                <input class="input" type="date" name="start_date" id="start_date_id" value="<?= h($rangeStartValue) ?>"
                    max="<?= h($rangeEndValue) ?>">
            </div>

            <div class="field-block">
                <label for="end_date_id" class="form-label">Đến ngày</label>
                <input class="input" type="date" name="end_date" id="end_date_id" value="<?= h($rangeEndValue) ?>"
                    min="<?= h($rangeStartValue) ?>">
            </div>
        </div>

        <button class="btn btn-red full-mobile" type="submit">Xem theo khoảng ngày</button>
        <small class="note">* Doanh thu lấy theo ngày tạo đơn, chi phí lấy theo ngày ghi nhận.</small>
    </form>
</div>

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
    <div class="summary-sales-panel">
        <?php if (empty($revenueEntries)): ?>
            <div class="expense-empty">Chưa có doanh thu trong khoảng thời gian này.</div>
        <?php else: ?>
            <div class="summary-order-entry-list">
                <?php foreach ($revenueEntries as $entry): ?>
                    <?php
                    $orderNote = trim((string) ($entry['note'] ?? ''));
                    $itemSummary = trim((string) ($entry['item_summary'] ?? ''));
                    $createdAt = trim((string) ($entry['created_at'] ?? ''));
                    $createdLabel = $createdAt !== '' ? date('H:i d/m/Y', strtotime($createdAt)) : '--:-- --/--/----';
                    $orderLabel = trim((string) ($entry['order_code'] ?? '')) ?: ('Đơn #' . (int) ($entry['id'] ?? 0));
                    ?>
                    <div class="summary-order-entry">
                        <div class="summary-order-entry-top">
                            <div class="summary-order-entry-head">
                                <b class="summary-order-entry-code"><?= h($orderLabel) ?></b>
                                <div class="summary-order-entry-meta">
                                    <?= h($createdLabel) ?> • <?= h(order_type_text($entry['order_type'] ?? '')) ?>
                                </div>
                            </div>
                            <div class="summary-order-entry-amount">
                                <?= moneyVND($entry['total_amount'] ?? 0) ?>
                            </div>
                        </div>

                        <?php if ($itemSummary !== ''): ?>
                            <div class="summary-order-entry-items"><?= h($itemSummary) ?></div>
                        <?php endif; ?>

                        <?php if ($orderNote !== ''): ?>
                            <div class="summary-order-entry-note">
                                <span class="summary-order-entry-note-label">Ghi chú:</span>
                                <?= nl2br(h($orderNote)) ?>
                            </div>
                        <?php else: ?>
                            <div class="summary-order-entry-note summary-order-entry-note-empty">
                                Chưa có ghi chú.
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="section-title">Chi tiết chi phí</div>
    <?php if (empty($expenseEntries)): ?>
        <div class="expense-empty">Chưa có chi phí trong khoảng thời gian này.</div>
    <?php else: ?>
        <div class="summary-expense-entry-list">
            <?php foreach ($expenseEntries as $expense): ?>
                <?php $note = trim((string) ($expense['note'] ?? '')); ?>
                <div class="summary-expense-entry">
                    <div class="summary-expense-entry-top">
                        <div class="summary-expense-entry-title">
                            <b class="summary-expense-entry-category">
                                <?= h(trim((string) ($expense['category'] ?? '')) ?: 'Chi phí khác') ?>
                            </b>
                            <div class="summary-expense-entry-date">
                                Ngày chi: <?= h(summaryFormatDisplayDate($expense['transaction_date'] ?? '')) ?>
                            </div>
                        </div>
                        <div class="summary-expense-entry-amount">
                            <?= moneyVND($expense['amount'] ?? 0) ?>
                        </div>
                    </div>

                    <?php if ($note !== ''): ?>
                        <div class="summary-expense-entry-note">
                            <span class="summary-expense-entry-note-label">Ghi chú:</span>
                            <?= nl2br(h($note)) ?>
                        </div>
                    <?php else: ?>
                        <div class="summary-expense-entry-note summary-expense-entry-note-empty">
                            Chưa có ghi chú.
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include_once 'footer.php'; ?>

<script>
    (function () {
        var rangeForm = document.getElementById('summary-range-form');
        var startInput = document.getElementById('start_date_id');
        var endInput = document.getElementById('end_date_id');

        if (!rangeForm || !startInput || !endInput) {
            return;
        }

        function syncRangeValidation() {
            if (endInput.value) {
                startInput.max = endInput.value;
            } else {
                startInput.removeAttribute('max');
            }

            if (startInput.value) {
                endInput.min = startInput.value;
            } else {
                endInput.removeAttribute('min');
            }

            var invalid = startInput.value !== '' && endInput.value !== '' && startInput.value > endInput.value;
            var message = invalid ? 'Ngày bắt đầu không được lớn hơn ngày kết thúc.' : '';

            startInput.setCustomValidity(message);
            endInput.setCustomValidity(message);

            return !invalid;
        }

        startInput.addEventListener('input', syncRangeValidation);
        endInput.addEventListener('input', syncRangeValidation);

        rangeForm.addEventListener('submit', function (event) {
            if (syncRangeValidation()) {
                return;
            }

            event.preventDefault();
            endInput.reportValidity();
        });

        syncRangeValidation();
    })();
</script>
