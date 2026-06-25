<?php
require_once 'config/db.php';
require_once 'config/helpers.php';
require_non_staff();

$id = (int) ($_GET['id'] ?? 0);
$p = ['category_id' => 1, 'name' => '', 'price' => 0, 'unit' => 'phần', 'image_url' => 'assets/img/hu-tieu.svg', 'is_active' => 1];

if ($id) {
    $p = $conn->query("SELECT * FROM products WHERE id=$id")->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = (int) $_POST['category_id'];
    $name = $_POST['name'];
    $price = (int) $_POST['price'];
    $unit = $_POST['unit'];
    $image = $_POST['image_url'] ?: 'assets/img/hu-tieu.svg';
    if ($id) {
        $stmt = $conn->prepare("UPDATE products SET category_id=?,name=?,price=?,unit=?,image_url=? WHERE id=?");
        $stmt->bind_param('isissi', $category_id, $name, $price, $unit, $image, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO products(category_id,name,price,unit,image_url) VALUES(?,?,?,?,?)");
        $stmt->bind_param('isiss', $category_id, $name, $price, $unit, $image);
    }
    $stmt->execute();
    redirect('products.php');
}

$cats = $conn->query("SELECT * FROM categories ORDER BY sort_order");
include_once 'header.php'; ?>

<div class="date"><?= $id ? '✏️ Sửa món' : '➕ Thêm món' ?></div>
<form method="post" class="card">
    <label for="category_id">Nhóm món</label>
    <select name="category_id" id="category_id">
        <?php while ($c = $cats->fetch_assoc()): ?>
            <option value="<?= $c['id'] ?>" <?= $p['category_id'] == $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?>
            </option>
        <?php endwhile; ?>
    </select><br><br>

    <label for="name">Tên món</label>
    <input class="input" id="name" name="name" required value="<?= h($p['name']) ?>"><br><br>

    <label for="price">Giá bán</label>
    <input class="input" id="price" type="number" name="price" required value="<?= h($p['price']) ?>"><br><br>

    <label for="unit">Đơn vị</label>
    <input class="input" id="unit" name="unit" value="<?= h($p['unit']) ?>"><br><br>

    <label for="image_url">Ảnh món</label>
    <select id="image_url" name="image_url">
        <option value="assets/img/images.png">Mặc định</option>
    </select><br><br>
    <button class="btn btn-red full">Lưu</button>
</form>
<?php include_once 'footer.php'; ?>
