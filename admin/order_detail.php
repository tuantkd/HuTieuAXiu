<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

require_admin();

$orderId = (int) ($_GET['id'] ?? 0);
$order = admin_fetch_one(
    'SELECT o.*, u.full_name, u.username
     FROM orders o
     LEFT JOIN users u ON u.id = o.user_id
     WHERE o.id = ? LIMIT 1',
    'i',
    [$orderId]
);

if (!$order) {
    admin_flash_set('Không tìm thấy đơn hàng.', 'error');
    header('Location: ' . (is_admin() ? 'orders.php' : 'pos.php'));
    exit;
}

if (is_staff() && (int) ($order['user_id'] ?? 0) !== admin_user_id()) {
    admin_flash_set('Bạn không có quyền xem đơn hàng này.', 'error');
    header('Location: pos.php');
    exit;
}

$items = admin_fetch_all(
    'SELECT product_name, quantity, price, subtotal FROM order_items WHERE order_id = ? ORDER BY id ASC',
    'i',
    [$orderId]
);

$page_title = 'Chi tiết đơn ' . ($order['order_code'] ?: ('#' . $orderId));

define('ADMIN_APP', true);
include __DIR__ . '/layout/header.php';
?>
<div class="panel">
    <div class="panel-header">
        <div>
            <h3><?= admin_h($order['order_code'] ?: ('Đơn #' . $orderId)) ?></h3>
            <div class="helper-text">
                <?= admin_h(admin_order_type_label($order['order_type'])) ?> • <?= admin_h(admin_datetime($order['created_at'])) ?>
            </div>
        </div>
        <div class="actions">
            <?php if (is_admin()): ?>
                <a class="button light small" href="orders.php">Quay lại danh sách</a>
            <?php else: ?>
                <a class="button light small" href="pos.php">Quay lại POS</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="info-grid" style="margin-bottom:18px;">
        <div class="card soft">
            <div class="stat-label">Người bán</div>
            <div class="big-number" style="font-size:1.4rem;"><?= admin_h(admin_order_seller_label($order)) ?></div>
        </div>
        <div class="card soft">
            <div class="stat-label">Tổng tiền</div>
            <div class="big-number" style="font-size:1.4rem;"><?= admin_money($order['total_amount']) ?></div>
        </div>
    </div>

    <div class="card soft" style="margin-bottom:18px;">
        <div class="stat-label">Ghi chú đơn hàng</div>
        <div style="margin-top:8px;"><?= admin_h($order['note'] ?: 'Không có ghi chú.') ?></div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Món</th>
                    <th>Số lượng</th>
                    <th>Đơn giá</th>
                    <th>Thành tiền</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= admin_h($item['product_name']) ?></td>
                        <td><?= (int) $item['quantity'] ?></td>
                        <td><?= admin_money($item['price']) ?></td>
                        <td><?= admin_money($item['subtotal']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>
