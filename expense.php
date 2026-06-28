<?php
require_once 'config/db.php';
require_once 'config/helpers.php';
requireLogin();
require_non_staff();

function expenseIsValidDate($value)
{
    return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
}

function expenseFetchOne($conn, $sql, $types, array $params, array $default = [])
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

function expenseFetchAll($conn, $sql, $types, array $params)
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

$selectedDate = $_GET['date'] ?? today();
if (!expenseIsValidDate($selectedDate)) {
    $selectedDate = today();
}

$rangeStartDate = date('Y-m-d', strtotime($selectedDate . ' -6 days'));

$dailySummary = expenseFetchOne(
    $conn,
    "SELECT COUNT(*) AS expense_count, COALESCE(SUM(amount), 0) AS expense_total
     FROM transactions
     WHERE type = 'expense' AND transaction_date = ?",
    's',
    [$selectedDate],
    ['expense_count' => 0, 'expense_total' => 0]
);

$weeklySummary = expenseFetchOne(
    $conn,
    "SELECT COUNT(*) AS expense_count, COALESCE(SUM(amount), 0) AS expense_total
     FROM transactions
     WHERE type = 'expense' AND transaction_date BETWEEN ? AND ?",
    'ss',
    [$rangeStartDate, $selectedDate],
    ['expense_count' => 0, 'expense_total' => 0]
);

$categorySummary = expenseFetchAll(
    $conn,
    "SELECT category, COUNT(*) AS expense_count, COALESCE(SUM(amount), 0) AS expense_total
     FROM transactions
     WHERE type = 'expense' AND transaction_date = ?
     GROUP BY category
     ORDER BY expense_total DESC, category ASC",
    's',
    [$selectedDate]
);

$expenses = expenseFetchAll(
    $conn,
    "SELECT id, category, amount, note, transaction_date, created_at
     FROM transactions
     WHERE type = 'expense' AND transaction_date = ?
     ORDER BY created_at DESC, id DESC",
    's',
    [$selectedDate]
);

include_once 'header.php';
?>

<div class="date">💸 Nhập chi phí ngày <?= date('d/m/Y', strtotime($selectedDate)) ?></div>

<div class="card expense-toolbar">
    <div>
        <div class="section-title" style="margin:0 0 6px;">Theo dõi chi phí</div>
        <div class="small">Chọn ngày để nhập nhanh, xem tổng chi và sửa lại phiếu chi ngay trên điện thoại.</div>
    </div>
    <div>
        <a class="btn btn-red full-mobile" href="expense_form.php?date=<?= h($selectedDate) ?>">+ Nhập chi phí</a>
    </div>
</div>

<form method="get" class="card expense-filter">
    <div class="field-block">
        <label class="form-label" for="expense_date">Ngày cần xem</label>
        <input class="input" type="date" name="date" id="expense_date" value="<?= h($selectedDate) ?>">
    </div>
    <div class="expense-filter-actions">
        <button class="btn btn-red full" type="submit">Xem chi phí</button>
    </div>
</form>

<div class="expense-stat-grid">
    <div class="expense-stat-card">
        <div class="small">Tổng chi trong ngày</div>
        <div class="expense-stat-value"><?= moneyVND($dailySummary['expense_total'] ?? 0) ?></div>
        <div class="small"><?= (int) ($dailySummary['expense_count'] ?? 0) ?> phiếu chi</div>
    </div>
    <div class="expense-stat-card">
        <div class="small">7 ngày gần nhất</div>
        <div class="expense-stat-value"><?= moneyVND($weeklySummary['expense_total'] ?? 0) ?></div>
        <div class="small">
            <?= (int) ($weeklySummary['expense_count'] ?? 0) ?> khoản từ
            <?= date('d/m', strtotime($rangeStartDate)) ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="section-title">Tổng theo hạng mục</div>
    <?php if (empty($categorySummary)): ?>
        <div class="expense-empty">Chưa có khoản chi nào trong ngày này.</div>
    <?php else: ?>
        <div class="expense-category-list">
            <?php foreach ($categorySummary as $item): ?>
                <div class="expense-category-item">
                    <div>
                        <b><?= h(trim((string) ($item['category'] ?? '')) ?: 'Chi phí khác') ?></b>
                        <div class="small"><?= (int) ($item['expense_count'] ?? 0) ?> phiếu</div>
                    </div>
                    <div class="price"><?= moneyVND($item['expense_total'] ?? 0) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="between">
        <div class="section-title" style="margin:0;">Phiếu chi trong ngày</div>
        <div><a class="small danger" href="expense_form.php?date=<?= h($selectedDate) ?>">+ Thêm mới</a></div>
    </div>

    <?php if (empty($expenses)): ?>
        <div class="expense-empty">Chưa có phiếu chi. Bạn có thể bấm "Nhập chi phí" để thêm ngay.</div>
    <?php else: ?>
        <div class="expense-list">
            <?php foreach ($expenses as $expense): ?>
                <a class="expense-item" href="expense_form.php?id=<?= (int) $expense['id'] ?>">
                    <div class="expense-item-top">
                        <div>
                            <b><?= h($expense['category']) ?></b>
                            <div class="small">
                                <?= date('H:i', strtotime($expense['created_at'])) ?> •
                                <?= date('d/m/Y', strtotime($expense['transaction_date'])) ?>
                            </div>
                        </div>
                        <div class="expense-amount"><?= moneyVND($expense['amount'] ?? 0) ?></div>
                    </div>
                    <?php if (trim((string) ($expense['note'] ?? '')) !== ''): ?>
                        <div class="expense-note"><?= h($expense['note']) ?></div>
                    <?php endif; ?>
                    <div class="expense-meta">
                        <span>Chạm để chỉnh sửa</span>
                        <span>#<?= (int) $expense['id'] ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include_once 'footer.php'; ?>
