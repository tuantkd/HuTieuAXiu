<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

require_role(ADMIN_ROLE);

$page_title = 'Báo cáo';
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

$summary = admin_fetch_one(
    'SELECT COUNT(*) AS order_count, COALESCE(SUM(total_amount), 0) AS revenue, COALESCE(AVG(total_amount), 0) AS avg_ticket
     FROM orders
     WHERE DATE(created_at) BETWEEN ? AND ?',
    'ss',
    [$from, $to]
);

$topProducts = admin_fetch_all(
    'SELECT product_name, SUM(quantity) AS qty, SUM(subtotal) AS amount
     FROM order_items oi
     INNER JOIN orders o ON o.id = oi.order_id
     WHERE DATE(o.created_at) BETWEEN ? AND ?
     GROUP BY product_name
     ORDER BY qty DESC, amount DESC
     LIMIT 10',
    'ss',
    [$from, $to]
);

$sellerStats = admin_fetch_all(
    'SELECT u.full_name, u.username, COUNT(o.id) AS order_count, COALESCE(SUM(o.total_amount), 0) AS revenue
     FROM users u
     LEFT JOIN orders o ON o.user_id = u.id AND DATE(o.created_at) BETWEEN ? AND ?
     GROUP BY u.id, u.full_name, u.username
     ORDER BY revenue DESC, order_count DESC',
    'ss',
    [$from, $to]
);

define('ADMIN_APP', true);
include __DIR__ . '/layout/header.php';
?>
<div class="panel">
    <div class="panel-header">
        <h3>Báo cáo doanh thu</h3>
    </div>

    <form method="get" class="filters-grid" style="margin-bottom:18px;">
        <div class="field">
            <label for="from">Từ ngày</label>
            <input id="from" type="date" name="from" value="<?= admin_h($from) ?>">
        </div>
        <div class="field">
            <label for="to">Đến ngày</label>
            <input id="to" type="date" name="to" value="<?= admin_h($to) ?>">
        </div>
        <div class="field" style="display:flex;align-items:flex-end;">
            <button type="submit" class="button">Xem báo cáo</button>
        </div>
    </form>

    <div class="report-grid" style="margin-bottom:18px;">
        <div class="stat-card">
            <div class="stat-label">Tổng số đơn</div>
            <div class="big-number"><?= (int) ($summary['order_count'] ?? 0) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Doanh thu</div>
            <div class="big-number"><?= admin_money($summary['revenue'] ?? 0) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Trung bình / đơn</div>
            <div class="big-number"><?= admin_money($summary['avg_ticket'] ?? 0) ?></div>
        </div>
    </div>

    <div class="info-grid">
        <div class="card soft">
            <div class="section-head">
                <h4>Món bán chạy</h4>
            </div>
            <div class="quick-order-list">
                <?php foreach ($topProducts as $row): ?>
                    <div class="quick-order-card">
                        <div>
                            <strong><?= admin_h($row['product_name']) ?></strong>
                            <div class="product-meta"><?= (int) $row['qty'] ?> phần</div>
                        </div>
                        <span class="meta-pill"><?= admin_money($row['amount']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card soft">
            <div class="section-head">
                <h4>Hiệu suất nhân viên</h4>
            </div>
            <div class="quick-order-list">
                <?php foreach ($sellerStats as $row): ?>
                    <div class="quick-order-card">
                        <div>
                            <strong><?= admin_h($row['full_name'] ?: $row['username']) ?></strong>
                            <div class="product-meta"><?= (int) $row['order_count'] ?> đơn</div>
                        </div>
                        <span class="meta-pill"><?= admin_money($row['revenue']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>
