<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

require_role(ADMIN_ROLE);

if (isset($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];
    if ($deleteId > 0) {
        $productCount = admin_scalar('SELECT COUNT(*) FROM products WHERE category_id = ?', 'i', [$deleteId]);
        if ($productCount > 0) {
            admin_flash_set('Không thể xóa nhóm món này vì vẫn còn món trong nhóm.', 'error');
        } else {
            admin_execute('DELETE FROM categories WHERE id = ?', 'i', [$deleteId]);
            admin_flash_set('Đã xóa nhóm món thành công.', 'success');
        }
    }

    header('Location: categories.php');
    exit;
}

$page_title = 'Quản lý nhóm món';
$categories = admin_fetch_all('SELECT id, name, slug, sort_order FROM categories ORDER BY sort_order, id');

define('ADMIN_APP', true);
include_once __DIR__ . '/layout/header.php';
?>
<div class="panel">
    <div class="panel-header">
        <div>
            <h3>Nhóm món</h3>
            <div class="helper-text">Quản lý danh mục nhóm món ăn, nước uống và thứ tự hiển thị.</div>
        </div>
        <a class="button" href="category_form.php">Thêm nhóm mới</a>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Tên nhóm</th>
                    <th>Slug</th>
                    <th>Thứ tự</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $category): ?>
                    <tr>
                        <td>
                            <strong><?= admin_h($category['name']) ?></strong>
                        </td>
                        <td><?= admin_h($category['slug']) ?></td>
                        <td><?= admin_h($category['sort_order']) ?></td>
                        <td>
                            <div class="actions">
                                <a class="button light small"
                                    href="category_form.php?id=<?= (int) $category['id'] ?>">Sửa</a>
                                <a class="button danger small" href="categories.php?delete=<?= (int) $category['id'] ?>"
                                    data-confirm="Xóa nhóm món này?">Xóa</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once __DIR__ . '/layout/footer.php';
