<?php
require_once 'config/db.php';
require_once 'config/helpers.php';
requireLogin();

$id = (int) ($_GET['id'] ?? 0);
$o = $conn->query("SELECT * FROM orders WHERE id=$id")->fetch_assoc();
$items = $conn->query("SELECT * FROM order_items WHERE order_id=$id");

include_once 'header.php'; ?>

<div class="date">📄 Chi tiết đơn</div>
<?php if (!$o): ?>
    <div class="card">Không tìm thấy đơn.</div>
<?php else: ?>
    <div class="card"><b>#<?= h($o['order_code']) ?></b>
        <div class="small"><?= date('H:i d/m/Y', strtotime($o['created_at'])) ?> · <?= order_type_text($o['order_type']) ?>
        </div><br>
        <?php while ($i = $items->fetch_assoc()): ?>
            <div class="between">
                <span><?= h($i['product_name']) ?> x<?= $i['quantity'] ?></span>
                <b><?= moneyVND($i['subtotal']) ?></b>
            </div><br>
        <?php endwhile; ?>
        <hr style="border:0;border-top:1px solid #f2dfd2">
        <div class="between"><b>Tổng tiền</b>
            <div class="big-total"><?= moneyVND($o['total_amount']) ?></div>
        </div>
    </div>
<?php endif; ?>

<?php include_once 'footer.php'; ?>
