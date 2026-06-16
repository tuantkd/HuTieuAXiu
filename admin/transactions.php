<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

require_role(ADMIN_ROLE);

$page_title = 'Thu chi';
$today = admin_today();
$revenueToday = admin_scalar('SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(created_at) = ?', 's', [$today]);
$dineInRevenue = admin_scalar('SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(created_at) = ? AND order_type = ?', 'ss', [$today, 'dine_in']);
$takeawayRevenue = admin_scalar('SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(created_at) = ? AND order_type = ?', 'ss', [$today, 'takeaway']);
$recentCashFlow = admin_fetch_all(
    'SELECT DATE(created_at) AS sale_date, COUNT(*) AS order_count, SUM(total_amount) AS amount
     FROM orders
     WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(created_at)
     ORDER BY sale_date DESC'
);

define('ADMIN_APP', true);
include __DIR__ . '/layout/header.php';
?>
<div class="report-grid">
    <div class="stat-card">
        <div class="stat-label">Thu hôm nay</div>
        <div class="big-number"><?= admin_money($revenueToday) ?></div>
        <div class="muted">Dựa trên đơn đã lưu.</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Ăn tại quán</div>
        <div class="big-number"><?= admin_money($dineInRevenue) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Mang đi</div>
        <div class="big-number"><?= admin_money($takeawayRevenue) ?></div>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <h3>Tổng hợp dòng tiền bán hàng 7 ngày gần nhất</h3>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Ngày</th>
                    <th>Số đơn</th>
                    <th>Doanh thu</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentCashFlow as $row): ?>
                    <tr>
                        <td><?= admin_h(date('d/m/Y', strtotime($row['sale_date']))) ?></td>
                        <td><?= (int) $row['order_count'] ?></td>
                        <td><?= admin_money($row['amount']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>
