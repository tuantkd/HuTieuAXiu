<?php
require_once 'config/db.php';
require_once 'config/helpers.php';
requireLogin();
require_non_staff();

function expenseFormIsValidDate($value)
{
    return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
}

function expenseFormFetchOne($conn, $sql, $types, array $params)
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    if ($types !== '' && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }

    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

$expenseId = (int) ($_GET['id'] ?? 0);
$requestedDate = $_GET['date'] ?? today();
$defaultDate = expenseFormIsValidDate($requestedDate) ? $requestedDate : today();
$expense = [
    'category' => '',
    'amount' => '',
    'note' => '',
    'transaction_date' => $defaultDate,
];
$error = '';

if ($expenseId > 0) {
    $loadedExpense = expenseFormFetchOne(
        $conn,
        "SELECT id, category, amount, note, transaction_date
         FROM transactions
         WHERE id = ? AND type = 'expense'
         LIMIT 1",
        'i',
        [$expenseId]
    );

    if (!$loadedExpense) {
        redirect('expense.php?date=' . rawurlencode($defaultDate));
    }

    $expense = $loadedExpense;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = trim((string) ($_POST['category'] ?? ''));
    $amount = (int) ($_POST['amount'] ?? 0);
    $transactionDate = $_POST['transaction_date'] ?? today();
    $note = trim((string) ($_POST['note'] ?? ''));

    $expense['category'] = $category;
    $expense['amount'] = $amount > 0 ? $amount : ($_POST['amount'] ?? '');
    $expense['transaction_date'] = expenseFormIsValidDate($transactionDate) ? $transactionDate : today();
    $expense['note'] = $note;

    if ($category === '') {
        $error = 'Vui lòng nhập hạng mục chi phí.';
    } elseif ($amount <= 0) {
        $error = 'Số tiền phải lớn hơn 0.';
    }

    if ($error === '') {
        if ($expenseId > 0) {
            $stmt = $conn->prepare(
                "UPDATE transactions
                 SET category = ?, amount = ?, note = ?, transaction_date = ?
                 WHERE id = ? AND type = 'expense'"
            );
            if ($stmt) {
                $stmt->bind_param('sissi', $category, $amount, $note, $expense['transaction_date'], $expenseId);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO transactions (type, category, amount, note, transaction_date)
                 VALUES ('expense', ?, ?, ?, ?)"
            );
            if ($stmt) {
                $stmt->bind_param('siss', $category, $amount, $note, $expense['transaction_date']);
                $stmt->execute();
                $stmt->close();
            }
        }

        redirect('expense.php?date=' . rawurlencode($expense['transaction_date']));
    }
}

$quickCategories = [
    'Tép',
    'Rau xà lách',
    'Thịt heo',
    'Bún khô',
    'Bắp cải',
    'Dưa leo',
    'Rau thơm',
    'Bánh tráng',
    'Đường cát',
    'Chi phí khác',
];

include_once 'header.php';
?>

<div class="date"><?= $expenseId > 0 ? '✏️ Sửa phiếu chi' : '🧾 Nhập chi phí mới' ?></div>

<?php if ($error !== ''): ?>
    <div class="card" style="border-color:#f3c3c3;background:#fff1f1;color:#9f2525;"><?= h($error) ?></div>
<?php endif; ?>

<form method="post" class="card expense-form">
    <div>
        <div class="section-title" style="margin:0 0 6px;">Nhập nhanh trên điện thoại</div>
        <div class="small">Chạm vào hạng mục gợi ý hoặc nhập trực tiếp để lưu phiếu chi nhanh hơn.</div>
    </div>

    <div class="field-block">
        <label class="form-label" for="category">Hạng mục chi phí</label>
        <input class="input" id="category" name="category" list="expense-category-list"
            placeholder="Ví dụ: Nguyên liệu, rau củ..." value="<?= h($expense['category']) ?>" required>
        <datalist id="expense-category-list">
            <?php foreach ($quickCategories as $category): ?>
                <option value="<?= h($category) ?>"></option>
            <?php endforeach; ?>
        </datalist>
        <div class="quick-grid">
            <?php foreach ($quickCategories as $category): ?>
                <button type="button" class="quick-pill"
                    data-fill-category="<?= h($category) ?>"><?= h($category) ?></button>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="field-row">
        <div class="field-block">
            <label class="form-label" for="amount">Số tiền</label>
            <input class="input" id="amount" type="number" name="amount" min="1000" step="1000" inputmode="numeric"
                placeholder="Ví dụ: 150000" value="<?= h((string) $expense['amount']) ?>" required>
        </div>

        <div class="field-block">
            <label class="form-label" for="transaction_date">Ngày chi</label>
            <input class="input" id="transaction_date" type="date" name="transaction_date"
                value="<?= h($expense['transaction_date']) ?>" required>
        </div>
    </div>

    <div class="field-block">
        <label class="form-label" for="note">Ghi chú</label>
        <textarea class="input expense-textarea" id="note" name="note"
            placeholder="Ghi thêm số lượng, nơi mua hoặc nội dung cần nhớ..."><?= h($expense['note']) ?></textarea>
    </div>

    <div class="expense-form-actions">
        <button class="btn btn-red full" type="submit"><?= $expenseId > 0 ? 'Lưu thay đổi' : 'Lưu chi phí' ?></button>
        <a class="btn btn-light full" href="expense.php?date=<?= h($expense['transaction_date']) ?>">
            <i class="fa fa-arrow-left"></i> Quay lại danh sách chi phí
        </a>
    </div>
</form>

<script>
    document.querySelectorAll('[data-fill-category]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById('category');
            if (!input) {
                return;
            }
            input.value = button.getAttribute('data-fill-category') || '';
            input.focus();
        });
    });
</script>

<?php include_once 'footer.php'; ?>
