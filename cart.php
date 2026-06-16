<?php require 'config/db.php'; require 'config/helpers.php';
if(isset($_GET['remove'])){ unset($_SESSION['cart'][(int)$_GET['remove']]); redirect('cart.php'); }
if(isset($_POST['update_qty'])){ foreach($_POST['qty']??[] as $id=>$q){ $q=max(0,(int)$q); if($q==0) unset($_SESSION['cart'][$id]); else $_SESSION['cart'][$id]['quantity']=$q; } redirect('cart.php'); }
if(isset($_POST['save_order'])){
  $cart=$_SESSION['cart']??[]; if(!$cart) redirect('index.php');
  $type=$_POST['order_type']==='takeaway'?'takeaway':'dine_in'; $total=cart_total(); $code='AX'.date('YmdHis');
  $stmt=$conn->prepare("INSERT INTO orders(order_code,order_type,total_amount,note) VALUES(?,?,?,?)"); $note=$_POST['note']??''; $stmt->bind_param('ssis',$code,$type,$total,$note); $stmt->execute(); $oid=$stmt->insert_id;
  $stmt2=$conn->prepare("INSERT INTO order_items(order_id,product_id,product_name,price,quantity,subtotal) VALUES(?,?,?,?,?,?)");
  foreach($cart as $it){$sub=$it['price']*$it['quantity']; $stmt2->bind_param('iisiii',$oid,$it['id'],$it['name'],$it['price'],$it['quantity'],$sub); $stmt2->execute();}
  $_SESSION['cart']=[]; redirect('success.php?id='.$oid);
}
include 'header.php'; ?>
<div class="date">📋 Chi tiết đơn hàng</div>
<?php if(empty($_SESSION['cart'])): ?><div class="card" style="text-align:center;padding:35px">Chưa có món
    nào.<br><br><a class="btn btn-red" href="index.php">+ Chọn món</a></div><?php else: ?>
<form method="post"><?php foreach($_SESSION['cart'] as $it): ?><div class="cart-item"><img
            src="<?=h($it['image_url'])?>">
        <div><b><?=h($it['name'])?></b>
            <div class="price"><?=money_vnd($it['price'])?></div><a class="small danger"
                href="?remove=<?=$it['id']?>">Xóa</a>
        </div>
        <div><input class="input" style="width:72px;text-align:center" type="number" min="0" name="qty[<?=$it['id']?>]"
                value="<?=$it['quantity']?>">
            <div class="right small"><?=money_vnd($it['price']*$it['quantity'])?></div>
        </div>
    </div><?php endforeach; ?><button class="btn btn-light full" name="update_qty" value="1">Cập nhật số lượng</button>
</form>
<form method="post" class="card">
    <div class="section-title">Hình thức</div>
    <div class="choice"><label class="btn btn-light"><input type="radio" name="order_type" value="dine_in" checked> 🪑
            Ăn tại quán</label><label class="btn btn-red"><input type="radio" name="order_type" value="takeaway"> 🛍️
            Mang đi</label></div><input class="input" name="note" placeholder="Ghi chú nếu có"><br><br>
    <div class="between"><b>Tổng tiền</b>
        <div class="big-total"><?=money_vnd(cart_total())?></div>
    </div><br><button class="btn btn-red full" name="save_order" value="1">Xác nhận & Lưu đơn</button>
</form><?php endif; ?>
<?php include 'footer.php'; ?>