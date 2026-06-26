<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

requireRole(ADMIN_ROLE);

$categoryId = (int) ($_GET['id'] ?? 0);
$isEditing = $categoryId > 0;
$page_title = $isEditing ? 'Cập nhật nhóm món' : 'Thêm nhóm mới';

$category = [
    'name' => '',
    'slug' => '',
    'sort_order' => 0,
];

if ($isEditing) {
    $category = admin_fetch_one('SELECT * FROM categories WHERE id = ? LIMIT 1', 'i', [$categoryId]);
    if (!$category) {
        adminFlashSet('Không tìm thấy nhóm món cần sửa.', 'error');
        header('Location: categories.php');
        exit;
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category['name'] = trim($_POST['name'] ?? '');
    $category['slug'] = trim($_POST['slug'] ?? '');
    $category['sort_order'] = (int) ($_POST['sort_order'] ?? 0);

    if ($category['name'] === '') {
        $errors[] = 'Tên nhóm không được để trống.';
    }

    if ($category['slug'] === '') {
        $category['slug'] = admin_slugify($category['name']);
    } else {
        $category['slug'] = admin_slugify($category['slug']);
    }

    if ($category['slug'] === '') {
        $errors[] = 'Slug nhóm không hợp lệ.';
    }

    $existing = admin_fetch_one('SELECT id FROM categories WHERE slug = ? LIMIT 1', 's', [$category['slug']]);
    if ($existing && (!$isEditing || (int) $existing['id'] !== $categoryId)) {
        $errors[] = 'Slug đã tồn tại, vui lòng chọn slug khác.';
    }

    if (empty($errors)) {
        if ($isEditing) {
            admin_execute(
                'UPDATE categories SET name = ?, slug = ?, sort_order = ? WHERE id = ?',
                'ssii',
                [$category['name'], $category['slug'], $category['sort_order'], $categoryId]
            );
            adminFlashSet('Đã cập nhật nhóm món thành công.', 'success');
        } else {
            admin_execute(
                'INSERT INTO categories (name, slug, sort_order) VALUES (?, ?, ?)',
                'ssi',
                [$category['name'], $category['slug'], $category['sort_order']]
            );
            adminFlashSet('Đã tạo nhóm món mới thành công.', 'success');
        }

        header('Location: categories.php');
        exit;
    }
}

define('ADMIN_APP', true);
include_once __DIR__ . '/layout/header.php';
?>
<div class="panel">
    <div class="panel-header">
        <h3><?= adminH($page_title) ?></h3>
        <a class="button light small" href="categories.php">Quay lại</a>
    </div>

    <?php if ($errors): ?>
        <div class="admin-alert error"><?= adminH(implode(' ', $errors)) ?></div>
    <?php endif; ?>

    <form method="post" class="form-card form-card--single">
        <div class="field">
            <label for="name">Tên nhóm</label>
            <input id="name" type="text" name="name" value="<?= adminH($category['name']) ?>" required>
        </div>

        <div class="field">
            <label for="slug">Slug</label>
            <input id="slug" type="text" name="slug" value="<?= adminH($category['slug']) ?>" placeholder="mon-an" required>
            <div class="muted">Nếu để trống, hệ thống sẽ tự tạo từ tên nhóm.</div>
        </div>

        <div class="field">
            <label for="sort_order">Thứ tự hiển thị</label>
            <input id="sort_order" type="number" name="sort_order" value="<?= adminH($category['sort_order']) ?>" min="0">
        </div>

        <div class="field field--full">
            <button type="submit" class="button"><?= $isEditing ? 'Lưu thay đổi' : 'Tạo nhóm mới' ?></button>
        </div>
    </form>
</div>

<?php include_once __DIR__ . '/layout/footer.php';
