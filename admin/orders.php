<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

require_role(ADMIN_ROLE);

$page_title = 'Quản lý đơn hàng';
$date = $_GET['date'] ?? admin_today();
$orderType = $_GET['order_type'] ?? '';

$sql = 'SELECT o.id, o.order_code, o.order_type, o.total_amount, o.note, o.created_at,
               u.full_name, u.username, COUNT(oi.id) AS item_count
        FROM orders o
        LEFT JOIN users u ON u.id = o.user_id
        LEFT JOIN order_items oi ON oi.order_id = o.id
        WHERE DATE(o.created_at) = ?';
$types = 's';
$params = [$date];

if (in_array($orderType, ['cash', 'bank_transfer'], true)) {
    $sql .= ' AND o.order_type = ?';
    $types .= 's';
    $params[] = $orderType;
}

$sql .= ' GROUP BY o.id, o.order_code, o.order_type, o.total_amount, o.note, o.created_at, u.full_name, u.username
          ORDER BY o.id DESC';

$orders = admin_fetch_all($sql, $types, $params);

define('ADMIN_APP', true);
include __DIR__ . '/layout/header.php';
?>
<div class="panel">
    <div class="panel-header">
        <div>
            <h3>Danh sách đơn hàng</h3>
            <div class="helper-text">Admin xem toàn bộ đơn, kể cả đơn cũ chưa gán nhân viên.</div>
        </div>
    </div>

    <form method="get" class="filters-grid" style="margin-bottom:18px;">
        <div class="field">
            <label for="date">Ngày bán</label>
            <input id="date" type="date" name="date" value="<?= admin_h($date) ?>">
        </div>
        <div class="field">
            <label for="order_type">Loại đơn</label>
            <select id="order_type" name="order_type">
                <option value="">Tất cả</option>
                <option value="cash" <?= $orderType === 'cash' ? 'selected' : '' ?>>Ăn tại quán</option>
                <option value="bank_transfer" <?= $orderType === 'bank_transfer' ? 'selected' : '' ?>>Mang đi</option>
            </select>
        </div>
        <div class="field" style="display:flex;align-items:flex-end;">
            <button type="submit" class="button">Lọc dữ liệu</button>
        </div>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Mã đơn</th>
                    <th>Nhân viên</th>
                    <th>Loại đơn</th>
                    <th>Số món</th>
                    <th>Ghi chú</th>
                    <th>Thời gian</th>
                    <th>Tổng tiền</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">Chưa có đơn nào theo bộ lọc đang chọn.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?= admin_h($order['order_code'] ?: ('#' . $order['id'])) ?></td>
                            <td><?= admin_h(admin_order_seller_label($order)) ?></td>
                            <td><?= admin_h(admin_order_type_label($order['order_type'])) ?></td>
                            <td><?= (int) $order['item_count'] ?></td>
                            <td><?= admin_h($order['note'] ?: '-') ?></td>
                            <td><?= admin_h(admin_datetime($order['created_at'])) ?></td>
                            <td><?= admin_money($order['total_amount']) ?></td>
                            <td><a class="button light small" href="order_detail.php?id=<?= (int) $order['id'] ?>">Chi tiết</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>
