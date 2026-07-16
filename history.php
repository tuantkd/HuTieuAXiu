<?php
require_once 'config/db.php';
require_once 'config/helpers.php';
requireLogin();

$date = $_GET['date'] ?? today();
$orders = $conn->query("SELECT * FROM orders WHERE DATE(created_at)='" . $conn->real_escape_string($date) . "' ORDER BY id DESC");

include_once 'header.php'; ?>

<div class="date">🕘 Lịch sử bán hàng</div>
<form>
    <label for="date_id_history" class="sr-only">Chọn ngày</label>
    <input class="input" type="date" name="date" id="date_id_history" value="<?= h($date) ?>" onchange="this.form.submit()">
    <small class="note">* Chọn ngày để xem</small>
</form>

<?php if ($orders->num_rows == 0): ?>
    <div class="card">Chưa có đơn trong ngày này.</div>
<?php endif; ?>

<?php while ($o = $orders->fetch_assoc()): ?>
    <a class="card" style="display:block" href="order_detail.php?id=<?= $o['id'] ?>">
        <div class="between">
            <b>#<?= h($o['order_code']) ?></b>
            <span class="price"><?= moneyVND($o['total_amount']) ?></span>
        </div>
        <div class="small">
            <p><?= h($o['note']) ?></p>
            <?= date('H:i d/m/Y', strtotime($o['created_at'])) ?> · <?= order_type_text($o['order_type']) ?>
        </div>
    </a>
<?php endwhile; ?>
<?php include_once 'footer.php'; ?>

