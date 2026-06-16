<?php require 'config/db.php'; require 'config/helpers.php';
require_non_staff();
if(isset($_GET['delete'])){ $id=(int)$_GET['delete']; $conn->query("UPDATE products SET is_active=0 WHERE id=$id"); redirect('products.php'); }
$products=$conn->query("SELECT p.*,c.name category FROM products p JOIN categories c ON c.id=p.category_id WHERE p.is_active=1 ORDER BY c.sort_order,p.id"); include 'header.php'; ?>
<div class="date">⚙️ Quản lý món</div><a class="btn btn-red full" href="product_form.php">+ Thêm món</a><br><br>
<?php while($p=$products->fetch_assoc()): ?><div class="cart-item"><img src="<?=h($p['image_url'])?>">
    <div><b><?=h($p['name'])?></b>
        <div class="small"><?=h($p['category'])?> · <?=h($p['unit'])?></div>
        <div class="price"><?=money_vnd($p['price'])?></div>
    </div>
    <div><a class="btn btn-light" href="product_form.php?id=<?=$p['id']?>">Sửa</a><br><br><a class="danger small"
            onclick="return confirm('Ẩn món này?')" href="?delete=<?=$p['id']?>">Ẩn</a></div>
</div><?php endwhile; ?>
<div class="card"><b>Cài đặt khác</b>
    <p class="small">Dữ liệu đang lưu trong MySQL. Có thể sao lưu bằng cách export database trong phpMyAdmin.</p>
</div>
<?php include 'footer.php'; ?>
