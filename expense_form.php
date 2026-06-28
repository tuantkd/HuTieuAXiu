<?php
require_once 'config/db.php';
require_once 'config/helpers.php';

if (!defined('EXPENSE_DATE_URL')) {
    define('EXPENSE_DATE_URL', 'expense.php?date=');
}

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

function expenseFormNormalizeAmount($value)
{
    return preg_replace('/\D+/', '', trim((string) $value));
}

function expenseFormFormatAmount($value)
{
    $normalized = expenseFormNormalizeAmount($value);
    if ($normalized === '') {
        return '';
    }

    return number_format((int) $normalized, 0, '.', ',');
}

$expenseId = (int) ($_GET['id'] ?? 0);
$requestedDate = $_GET['date'] ?? today();
$defaultDate = expenseFormIsValidDate($requestedDate) ? $requestedDate : today();
$isEditing = $expenseId > 0;
$error = '';
$errorItems = [];
$expense = [
    'category' => '',
    'amount' => '',
    'note' => '',
    'transaction_date' => $defaultDate,
];
$expenseRows = [
    [
        'category' => '',
        'amount' => '',
        'note' => '',
        'transaction_date' => $defaultDate,
    ],
];

if ($isEditing) {
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
        redirect(EXPENSE_DATE_URL . rawurlencode($defaultDate));
    }

    $expense = $loadedExpense;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isEditing) {
        $category = trim((string) ($_POST['category'] ?? ''));
        $amountRaw = expenseFormNormalizeAmount($_POST['amount'] ?? '');
        $amount = (int) $amountRaw;
        $transactionDate = $_POST['transaction_date'] ?? today();
        $note = trim((string) ($_POST['note'] ?? ''));

        $expense['category'] = $category;
        $expense['amount'] = $amountRaw === '' ? '' : expenseFormFormatAmount($amountRaw);
        $expense['transaction_date'] = expenseFormIsValidDate($transactionDate) ? $transactionDate : today();
        $expense['note'] = $note;

        if ($category === '') {
            $error = 'Vui lòng nhập hạng mục chi phí.';
        } elseif ($amount <= 0) {
            $error = 'Số tiền phải lớn hơn 0.';
        }

        if ($error === '') {
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

            redirect(EXPENSE_DATE_URL . rawurlencode($expense['transaction_date']));
        }
    } else {
        $categories = $_POST['category'] ?? [];
        $amounts = $_POST['amount'] ?? [];
        $notes = $_POST['note'] ?? [];
        $transactionDates = $_POST['transaction_date'] ?? [];
        $rowCount = max(count((array) $categories), count((array) $amounts), count((array) $notes), count((array) $transactionDates));
        $expenseRows = [];
        $hasAtLeastOneRow = false;

        for ($index = 0; $index < $rowCount; $index++) {
            $category = trim((string) ($categories[$index] ?? ''));
            $amountRaw = expenseFormNormalizeAmount($amounts[$index] ?? '');
            $amount = (int) $amountRaw;
            $note = trim((string) ($notes[$index] ?? ''));
            $transactionDateRaw = trim((string) ($transactionDates[$index] ?? $defaultDate));
            $transactionDate = expenseFormIsValidDate($transactionDateRaw) ? $transactionDateRaw : $defaultDate;

            $row = [
                'category' => $category,
                'amount' => $amountRaw === '' ? '' : expenseFormFormatAmount($amountRaw),
                'note' => $note,
                'transaction_date' => $transactionDate,
            ];

            if ($category === '' && $amountRaw === '' && $note === '') {
                continue;
            }

            $hasAtLeastOneRow = true;
            $expenseRows[] = $row;

            if ($category === '') {
                $errorItems[] = 'Khoản chi #' . ($index + 1) . ' chưa có hạng mục.';
            }

            if ($amount <= 0) {
                $errorItems[] = 'Khoản chi #' . ($index + 1) . ' phải có số tiền lớn hơn 0.';
            }
        }

        if (!$hasAtLeastOneRow) {
            $error = 'Vui lòng nhập ít nhất một khoản chi trước khi lưu.';
        } elseif (!empty($errorItems)) {
            $error = 'Vui lòng kiểm tra lại các khoản chi bên dưới.';
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO transactions (type, category, amount, note, transaction_date)
                 VALUES ('expense', ?, ?, ?, ?)"
            );

            if (!$stmt) {
                $error = 'Không thể lưu chi phí lúc này. Vui lòng thử lại.';
            } else {
                $conn->begin_transaction();
                $savedDate = $defaultDate;
                $saveOk = true;

                foreach ($expenseRows as $row) {
                    $category = $row['category'];
                    $amount = (int) expenseFormNormalizeAmount($row['amount']);
                    $note = $row['note'];
                    $transactionDate = $row['transaction_date'];
                    $savedDate = $transactionDate;

                    $stmt->bind_param('siss', $category, $amount, $note, $transactionDate);
                    if (!$stmt->execute()) {
                        $saveOk = false;
                        break;
                    }
                }

                if ($saveOk) {
                    $conn->commit();
                    $stmt->close();
                    redirect(EXPENSE_DATE_URL . rawurlencode($savedDate));
                }

                $conn->rollback();
                $stmt->close();
                $error = 'Lưu chi phí chưa thành công. Vui lòng thử lại.';
            }
        }

        if (empty($expenseRows)) {
            $expenseRows[] = [
                'category' => '',
                'amount' => '',
                'note' => '',
                'transaction_date' => $defaultDate,
            ];
        }
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

<div class="date"><?= $isEditing ? '✏️ Sửa phiếu chi' : '🧾 Nhập nhiều chi phí' ?></div>

<?php if ($error !== ''): ?>
    <div class="card" style="border-color:#f3c3c3;background:#fff1f1;color:#9f2525;">
        <div><?= h($error) ?></div>
        <?php if (!empty($errorItems)): ?>
            <div class="expense-error-list">
                <?php foreach ($errorItems as $item): ?>
                    <div><?= h($item) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($isEditing): ?>
    <form method="post" class="card expense-form">
        <div>
            <div class="section-title" style="margin:0 0 6px;">Cập nhật phiếu chi</div>
            <div class="small">Chỉnh lại thông tin của một phiếu chi đã lưu.</div>
        </div>

        <datalist id="expense-category-list">
            <?php foreach ($quickCategories as $category): ?>
                <option value="<?= h($category) ?>"></option>
            <?php endforeach; ?>
        </datalist>

        <div class="field-block">
            <label class="form-label" for="category">Hạng mục chi phí</label>
            <input class="input" id="category" name="category" list="expense-category-list"
                placeholder="Ví dụ: Nguyên liệu, rau củ..." value="<?= h($expense['category']) ?>" required>
        </div>

        <div class="field-row">
            <div class="field-block">
                <label class="form-label" for="amount">Số tiền</label>
                <input class="input" id="amount" type="text" name="amount" inputmode="numeric" autocomplete="off"
                    data-money-input placeholder="Ví dụ: 150000"
                    value="<?= h(expenseFormFormatAmount($expense['amount'])) ?>" required>
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

        <div class="quick-grid">
            <?php foreach ($quickCategories as $category): ?>
                <button type="button" class="quick-pill"
                    data-fill-single-category="<?= h($category) ?>"><?= h($category) ?></button>
            <?php endforeach; ?>
        </div>

        <div class="expense-form-actions">
            <button class="btn btn-red full" type="submit">Lưu thay đổi</button>
            <a class="btn btn-light full" href="expense.php?date=<?= h($expense['transaction_date']) ?>">
                <i class="fa fa-arrow-left"></i> Quay lại danh sách chi phí
            </a>
        </div>
    </form>
<?php else: ?>
    <form method="post" class="card expense-form">
        <div class="expense-form-header">
            <div>
                <div class="section-title" style="margin:0 0 6px;">Nhập nhiều khoản chi cùng lúc</div>
                <div class="small">Bạn có thể thêm nhiều dòng chi phí, nhập xong hết rồi bấm lưu một lần.</div>
            </div>
        </div>

        <datalist id="expense-category-list">
            <?php foreach ($quickCategories as $category): ?>
                <option value="<?= h($category) ?>"></option>
            <?php endforeach; ?>
        </datalist>

        <div class="expense-entry-list" data-expense-rows>
            <?php foreach ($expenseRows as $index => $row): ?>
                <div class="expense-entry-card">
                    <div class="expense-entry-header">
                        <b data-row-title>Khoản chi #<?= $index + 1 ?></b>
                        <button type="button" class="expense-inline-btn danger" data-remove-expense-row>Xóa khoản chi</button>
                    </div>

                    <div class="field-block">
                        <label class="sr-only" for="category-<?= $index ?>">Hạng mục chi phí</label>
                        <input class="input" id="category-<?= $index ?>" name="category[]" list="expense-category-list"
                            placeholder="Tên hạng mục..." value="<?= h($row['category']) ?>">
                    </div>

                    <div class="quick-grid">
                        <?php foreach ($quickCategories as $category): ?>
                            <button type="button" class="quick-pill"
                                data-fill-category="<?= h($category) ?>"><?= h($category) ?></button>
                        <?php endforeach; ?>
                    </div>

                    <div class="field-row">
                        <div class="field-block">
                            <label class="form-label" for="amount-<?= $index ?>">Số tiền</label>
                            <input class="input" type="text" id="amount-<?= $index ?>" name="amount[]" inputmode="numeric"
                                autocomplete="off" data-money-input placeholder="Ví dụ: 150000"
                                value="<?= h(expenseFormFormatAmount($row['amount'])) ?>">
                        </div>

                        <div class="field-block">
                            <label class="form-label" for="transaction-date-<?= $index ?>">Ngày chi</label>
                            <input class="input" type="date" id="transaction-date-<?= $index ?>" name="transaction_date[]"
                                value="<?= h($row['transaction_date']) ?>">
                        </div>
                    </div>

                    <div class="field-block">
                        <label class="form-label" for="note-<?= $index ?>">Ghi chú</label>
                        <textarea class="input expense-textarea" id="note-<?= $index ?>" name="note[]"
                            placeholder="Ghi thêm số lượng, nơi mua hoặc nội dung cần nhớ..."><?= h($row['note']) ?></textarea>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-light full-mobile" data-add-expense-row>+ Thêm khoản chi</button>
        <br>
        <div class="expense-form-actions">
            <button class="btn btn-red full" type="submit">Lưu tất cả chi phí</button>
            <a class="btn btn-light full" href="expense.php?date=<?= h($defaultDate) ?>">
                <i class="fa fa-arrow-left"></i> Quay lại danh sách
            </a>
        </div>
    </form>
<?php endif; ?>

<script>
    (function () {
        function formatMoneyValue(value) {
            var digits = String(value || '').replace(/\D/g, '');
            if (!digits) {
                return '';
            }

            return digits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }

        function countDigits(value) {
            var match = String(value || '').match(/\d/g);
            return match ? match.length : 0;
        }

        function setCaretFromDigitIndex(input, digitIndex) {
            if (typeof input.setSelectionRange !== 'function') {
                return;
            }

            if (digitIndex <= 0) {
                input.setSelectionRange(0, 0);
                return;
            }

            var seenDigits = 0;
            for (var index = 0; index < input.value.length; index++) {
                if (/\d/.test(input.value.charAt(index))) {
                    seenDigits += 1;
                    if (seenDigits >= digitIndex) {
                        var caret = index + 1;
                        input.setSelectionRange(caret, caret);
                        return;
                    }
                }
            }

            var end = input.value.length;
            input.setSelectionRange(end, end);
        }

        function bindMoneyInput(input) {
            if (!input || input.dataset.moneyBound === '1') {
                return;
            }

            input.dataset.moneyBound = '1';
            input.value = formatMoneyValue(input.value);

            input.addEventListener('input', function () {
                var start = input.selectionStart || 0;
                var digitIndex = countDigits(input.value.slice(0, start));
                input.value = formatMoneyValue(input.value);
                setCaretFromDigitIndex(input, digitIndex);
            });

            input.addEventListener('blur', function () {
                input.value = formatMoneyValue(input.value);
            });
        }

        function bindMoneyInputs(scope) {
            scope.querySelectorAll('[data-money-input]').forEach(bindMoneyInput);
        }

        bindMoneyInputs(document);

        document.querySelectorAll('form.expense-form').forEach(function (form) {
            form.addEventListener('submit', function () {
                form.querySelectorAll('[data-money-input]').forEach(function (input) {
                    input.value = String(input.value || '').replace(/\D/g, '');
                });
            });
        });

        document.querySelectorAll('[data-fill-single-category]').forEach(function (button) {
            button.addEventListener('click', function () {
                var input = document.getElementById('category');
                if (!input) {
                    return;
                }
                input.value = button.getAttribute('data-fill-single-category') || '';
                input.focus();
            });
        });

        var rowsContainer = document.querySelector('[data-expense-rows]');
        var addRowButton = document.querySelector('[data-add-expense-row]');

        if (!rowsContainer || !addRowButton) {
            return;
        }

        function refreshRowState() {
            var cards = rowsContainer.querySelectorAll('.expense-entry-card');
            cards.forEach(function (card, index) {
                var title = card.querySelector('[data-row-title]');
                var removeButton = card.querySelector('[data-remove-expense-row]');

                if (title) {
                    title.textContent = 'Khoản chi #' + (index + 1);
                }

                if (removeButton) {
                    removeButton.style.display = cards.length > 1 ? 'inline-flex' : 'none';
                }
            });
        }

        function bindCategoryPills(scope) {
            scope.querySelectorAll('[data-fill-category]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var card = button.closest('.expense-entry-card');
                    var input = card ? card.querySelector('input[name="category[]"]') : null;
                    if (!input) {
                        return;
                    }
                    input.value = button.getAttribute('data-fill-category') || '';
                    input.focus();
                });
            });
        }

        bindCategoryPills(rowsContainer);
        refreshRowState();

        addRowButton.addEventListener('click', function () {
            var cards = rowsContainer.querySelectorAll('.expense-entry-card');
            var sourceCard = cards[cards.length - 1];
            if (!sourceCard) {
                return;
            }

            var clonedCard = sourceCard.cloneNode(true);
            clonedCard.querySelectorAll('input, textarea').forEach(function (field) {
                if (field.name === 'transaction_date[]') {
                    field.value = sourceCard.querySelector('input[name="transaction_date[]"]').value;
                    return;
                }

                field.value = '';
            });

            rowsContainer.appendChild(clonedCard);
            bindCategoryPills(clonedCard);
            bindMoneyInputs(clonedCard);
            refreshRowState();

            var categoryInput = clonedCard.querySelector('input[name="category[]"]');
            if (categoryInput) {
                categoryInput.focus();
            }
        });

        rowsContainer.addEventListener('click', function (event) {
            var removeButton = event.target.closest('[data-remove-expense-row]');
            if (!removeButton) {
                return;
            }

            var cards = rowsContainer.querySelectorAll('.expense-entry-card');
            if (cards.length <= 1) {
                return;
            }

            var card = removeButton.closest('.expense-entry-card');
            if (card) {
                card.remove();
                refreshRowState();
            }
        });
    })();
</script>

<?php include_once 'footer.php'; ?>
