<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

requireRole(ADMIN_ROLE);

$page_title = 'Dashboard';
$today = admin_today();
$monthStart = date('Y-m-01');

$todayRevenue = admin_scalar('SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(created_at) = ?', 's', [$today]);
$todayOrders = admin_scalar('SELECT COUNT(*) FROM orders WHERE DATE(created_at) = ?', 's', [$today]);
$activeProducts = admin_scalar('SELECT COUNT(*) FROM products WHERE is_active = 1');
$totalUsers = admin_scalar('SELECT COUNT(*) FROM users');
$takeawayOrders = admin_scalar('SELECT COUNT(*) FROM orders WHERE DATE(created_at) = ? AND order_type = ?', 'ss', [$today, 'bank_transfer']);
$monthRevenue = admin_scalar('SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(created_at) >= ?', 's', [$monthStart]);

$recentOrders = admin_fetch_all(
    'SELECT o.id, o.order_code, o.order_type, o.total_amount, o.created_at, u.full_name, u.username
     FROM orders o
     LEFT JOIN users u ON u.id = o.user_id
     ORDER BY o.id DESC
     LIMIT 8'
);

$bestProducts = admin_fetch_all(
    'SELECT product_name, SUM(quantity) AS qty, SUM(subtotal) AS amount
     FROM order_items
     GROUP BY product_name
     ORDER BY qty DESC, amount DESC
     LIMIT 5'
);

define('ADMIN_APP', true);
include __DIR__ . '/layout/header.php';
?>
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Doanh thu hôm nay</div>
        <div class="big-number"><?= admin_money($todayRevenue) ?></div>
        <div class="muted">Tính từ các đơn đã lưu trong ngày.</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Số đơn hôm nay</div>
        <div class="big-number"><?= (int) $todayOrders ?></div>
        <div class="muted"><?= (int) $takeawayOrders ?> đơn mang đi.</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Món đang bán</div>
        <div class="big-number"><?= (int) $activeProducts ?></div>
        <div class="muted">Đang hiển thị ở cả POS public và POS admin.</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Tài khoản hệ thống</div>
        <div class="big-number"><?= (int) $totalUsers ?></div>
        <div class="muted">Bao gồm admin và nhân viên.</div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="panel">
        <div class="panel-header">
            <h3>Tổng quan nhanh</h3>
        </div>
        <div class="info-grid">
            <div class="card soft">
                <div class="stat-label">Doanh thu tháng này</div>
                <div class="big-number"><?= admin_money($monthRevenue) ?></div>
            </div>
            <div class="card soft">
                <div class="stat-label">Ngày hiện tại</div>
                <div class="big-number"><?= adminH(date('d/m/Y')) ?></div>
            </div>
        </div>
        <div class="helper-text">Schema admin đã map chung về `database.sql`, không còn tách cấu trúc dữ liệu riêng.</div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <h3>Món bán chạy</h3>
        </div>
        <?php if (empty($bestProducts)): ?>
            <div class="empty-state">Chưa có dữ liệu bán hàng.</div>
        <?php else: ?>
            <div class="quick-order-list">
                <?php foreach ($bestProducts as $product): ?>
                    <div class="quick-order-card">
                        <div>
                            <strong><?= adminH($product['product_name']) ?></strong>
                            <div class="product-meta"><?= (int) $product['qty'] ?> phần đã bán</div>
                        </div>
                        <span class="meta-pill"><?= admin_money($product['amount']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="panel" style="grid-column: 1 / -1;">
        <div class="panel-header">
            <h3>Đơn hàng gần nhất</h3>
            <a class="button light small" href="orders.php">Xem toàn bộ</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Mã đơn</th>
                        <th>Nhân viên</th>
                        <th>Loại đơn</th>
                        <th>Thời gian</th>
                        <th>Tổng tiền</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentOrders as $order): ?>
                        <tr>
                            <td><?= adminH($order['order_code'] ?: ('#' . $order['id'])) ?></td>
                            <td><?= adminH(admin_order_seller_label($order)) ?></td>
                            <td><?= adminH(admin_order_type_label($order['order_type'])) ?></td>
                            <td><?= adminH(admin_datetime($order['created_at'])) ?></td>
                            <td><?= admin_money($order['total_amount']) ?></td>
                            <td><a class="button light small" href="order_detail.php?id=<?= (int) $order['id'] ?>">Chi tiết</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>
