<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

require_role(ADMIN_ROLE);

$productId = (int) ($_GET['id'] ?? 0);
$isEditing = $productId > 0;
$page_title = $isEditing ? 'Cập nhật món' : 'Thêm món mới';

$categories = admin_fetch_all('SELECT id, name FROM categories ORDER BY sort_order, id');
$product = [
    'category_id' => $categories[0]['id'] ?? 0,
    'name' => '',
    'price' => 0,
    'unit' => 'phần',
    'image_url' => '',
    'is_active' => 1,
];

if ($isEditing) {
    $product = admin_fetch_one('SELECT * FROM products WHERE id = ? LIMIT 1', 'i', [$productId]);
    if (!$product) {
        admin_flash_set('Không tìm thấy món cần sửa.', 'error');
        header('Location: products.php');
        exit;
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product['category_id'] = (int) ($_POST['category_id'] ?? 0);
    $product['name'] = trim($_POST['name'] ?? '');
    $product['price'] = (int) ($_POST['price'] ?? 0);
    $product['unit'] = trim($_POST['unit'] ?? 'phần');
    $product['image_url'] = trim($_POST['image_url'] ?? '');
    $product['is_active'] = isset($_POST['is_active']) ? 1 : 0;

    if ($product['category_id'] <= 0) {
        $errors[] = 'Vui lòng chọn nhóm món.';
    }
    if ($product['name'] === '') {
        $errors[] = 'Tên món không được để trống.';
    }
    if ($product['price'] <= 0) {
        $errors[] = 'Giá bán phải lớn hơn 0.';
    }

    if (empty($errors)) {
        if ($isEditing) {
            admin_execute(
                'UPDATE products SET category_id = ?, name = ?, price = ?, unit = ?, image_url = ?, is_active = ? WHERE id = ?',
                'isissii',
                [$product['category_id'], $product['name'], $product['price'], $product['unit'], $product['image_url'], $product['is_active'], $productId]
            );
            admin_flash_set('Đã cập nhật món thành công.', 'success');
        } else {
            admin_execute(
                'INSERT INTO products (category_id, name, price, unit, image_url, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
                'isissi',
                [$product['category_id'], $product['name'], $product['price'], $product['unit'], $product['image_url'], $product['is_active']]
            );
            admin_flash_set('Đã thêm món mới thành công.', 'success');
        }

        header('Location: products.php');
        exit;
    }
}

define('ADMIN_APP', true);
include __DIR__ . '/layout/header.php';
?>
<div class="panel">
    <div class="panel-header">
        <h3><?= admin_h($page_title) ?></h3>
        <a class="button light small" href="products.php">Quay lại</a>
    </div>

    <?php if ($errors): ?>
        <div class="admin-alert error"><?= admin_h(implode(' ', $errors)) ?></div>
    <?php endif; ?>

    <form method="post" class="form-card">
        <div class="field">
            <label for="category_id">Nhóm món</label>
            <select id="category_id" name="category_id" required>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>" <?= (int) $product['category_id'] === (int) $category['id'] ? 'selected' : '' ?>>
                        <?= admin_h($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="name">Tên món</label>
            <input id="name" type="text" name="name" value="<?= admin_h($product['name']) ?>" required>
        </div>
        <div class="field">
            <label for="price">Giá bán</label>
            <input id="price" type="number" min="0" step="1000" name="price" value="<?= admin_h($product['price']) ?>" required>
        </div>
        <div class="field">
            <label for="unit">Đơn vị</label>
            <input id="unit" type="text" name="unit" value="<?= admin_h($product['unit']) ?>" placeholder="tô, ly, phần">
        </div>
        <div class="field field--full">
            <label for="image_url">Ảnh minh họa</label>
            <input id="image_url" type="text" name="image_url" value="<?= admin_h($product['image_url']) ?>" placeholder="assets/img/hu-tieu.svg">
        </div>
        <div class="field field--full">
            <label class="checkbox-inline">
                <input type="checkbox" name="is_active" value="1" <?= (int) $product['is_active'] === 1 ? 'checked' : '' ?>>
                <span>Hiển thị món này trên màn hình POS</span>
            </label>
        </div>
        <div class="field field--full">
            <button type="submit" class="button"><?= $isEditing ? 'Lưu thay đổi' : 'Tạo món mới' ?></button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>
