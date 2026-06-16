<?php require 'config/db.php'; require 'config/helpers.php';
$id=(int)($_GET['id']??0); $p=['category_id'=>1,'name'=>'','price'=>0,'unit'=>'phần','image_url'=>'assets/img/hu-tieu.svg','is_active'=>1];
if($id) $p=$conn->query("SELECT * FROM products WHERE id=$id")->fetch_assoc();
if($_SERVER['REQUEST_METHOD']==='POST'){
  $category_id=(int)$_POST['category_id']; $name=$_POST['name']; $price=(int)$_POST['price']; $unit=$_POST['unit']; $image=$_POST['image_url']?:'assets/img/hu-tieu.svg';
  if($id){$stmt=$conn->prepare("UPDATE products SET category_id=?,name=?,price=?,unit=?,image_url=? WHERE id=?"); $stmt->bind_param('isissi',$category_id,$name,$price,$unit,$image,$id);}
  else {$stmt=$conn->prepare("INSERT INTO products(category_id,name,price,unit,image_url) VALUES(?,?,?,?,?)"); $stmt->bind_param('isiss',$category_id,$name,$price,$unit,$image);}
  $stmt->execute(); redirect('products.php');
}
$cats=$conn->query("SELECT * FROM categories ORDER BY sort_order"); include 'header.php'; ?>
<div class="date"><?=$id?'✏️ Sửa món':'➕ Thêm món'?></div>
<form method="post" class="card"><label>Nhóm món</label><select
        name="category_id"><?php while($c=$cats->fetch_assoc()): ?><option value="<?=$c['id']?>"
            <?=$p['category_id']==$c['id']?'selected':''?>><?=h($c['name'])?></option>
        <?php endwhile; ?></select><br><br><label>Tên món</label><input class="input" name="name" required
        value="<?=h($p['name'])?>"><br><br><label>Giá bán</label><input class="input" type="number" name="price"
        required value="<?=h($p['price'])?>"><br><br><label>Đơn vị</label><input class="input" name="unit"
        value="<?=h($p['unit'])?>"><br><br><label>Ảnh món</label><select name="image_url">
        <option value="assets/img/hu-tieu.svg">Hủ tiếu</option>
        <option value="assets/img/chao.svg">Cháo</option>
        <option value="assets/img/tra-da.svg">Trà đá</option>
        <option value="assets/img/nuoc-suoi.svg">Nước suối</option>
        <option value="assets/img/nuoc-ngot.svg">Nước ngọt</option>
    </select><br><br><button class="btn btn-red full">Lưu</button></form>
<?php include 'footer.php'; ?>