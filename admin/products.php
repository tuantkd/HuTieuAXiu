<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

requireRole(ADMIN_ROLE);

if (isset($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];
    if ($deleteId > 0) {
        admin_execute('UPDATE products SET is_active = 0 WHERE id = ?', 'i', [$deleteId]);
        adminFlashSet('Đã ẩn món khỏi màn hình POS.', 'success');
    }
    header('Location: products.php');
    exit;
}

$page_title = 'Quản lý món';
$products = admin_fetch_all(
    'SELECT p.*, c.name AS category_name
     FROM products p
     INNER JOIN categories c ON c.id = p.category_id
     ORDER BY p.is_active DESC, c.sort_order, p.id DESC'
);
$activeCount = admin_scalar('SELECT COUNT(*) FROM products WHERE is_active = 1');
$inactiveCount = admin_scalar('SELECT COUNT(*) FROM products WHERE is_active = 0');

define('ADMIN_APP', true);
include __DIR__ . '/layout/header.php';
?>
<div class="panel">
    <div class="panel-header">
        <div>
            <h3>Danh sách món ăn / nước uống</h3>
            <div class="helper-text">Admin có thể thêm, sửa hoặc ẩn món khỏi màn hình bán hàng.</div>
        </div>
        <div class="actions">
            <span class="meta-pill"><?= (int) $activeCount ?> món đang bán</span>
            <a class="button" href="product_form.php">Thêm món mới</a>
        </div>
    </div>

    <div class="info-grid" style="margin-bottom:18px;">
        <div class="card soft">
            <div class="stat-label">Món đang hiển thị</div>
            <div class="big-number"><?= (int) $activeCount ?></div>
        </div>
        <div class="card soft">
            <div class="stat-label">Món đang ẩn</div>
            <div class="big-number"><?= (int) $inactiveCount ?></div>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Món</th>
                    <th>Nhóm món</th>
                    <th>Giá</th>
                    <th>Đơn vị</th>
                    <th>Trạng thái</th>
                    <th>Tạo lúc</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td>
                            <strong><?= adminH($product['name']) ?></strong>
                            <div class="product-meta"><?= adminH($product['image_url']) ?></div>
                        </td>
                        <td><?= adminH($product['category_name']) ?></td>
                        <td><?= admin_money($product['price']) ?></td>
                        <td><?= adminH($product['unit'] ?: 'phần') ?></td>
                        <td>
                            <span class="badge <?= (int) $product['is_active'] === 1 ? 'success' : 'warning' ?>">
                                <?= (int) $product['is_active'] === 1 ? 'Đang bán' : 'Đã ẩn' ?>
                            </span>
                        </td>
                        <td><?= adminH(admin_datetime($product['created_at'] ?? null)) ?></td>
                        <td>
                            <div class="actions">
                                <a class="button light small" href="product_form.php?id=<?= (int) $product['id'] ?>">Sửa</a>
                                <?php if ((int) $product['is_active'] === 1): ?>
                                    <a class="button danger small" href="products.php?delete=<?= (int) $product['id'] ?>" data-confirm="Ẩn món này khỏi màn hình POS?">Ẩn món</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>
