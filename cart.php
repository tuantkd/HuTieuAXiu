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
<?php if(empty($_SESSION['cart'])): ?><div class="card" style="text-align:center;padding:35px">Chưa có món nào.<br><br><a class="btn btn-red" href="index.php">+ Chọn món</a></div><?php else: ?>
<form method="post" id="cart-qty-form"><input type="hidden" name="update_qty" value="1"><?php foreach($_SESSION['cart'] as $it): ?><div class="cart-item"><img
            src="<?=h($it['image_url'])?>">
        <div><b><?=h($it['name'])?></b>
            <div class="price"><?=money_vnd($it['price'])?></div><a class="small danger"
                href="?remove=<?=$it['id']?>">Xóa</a>
        </div>
        <div class="cart-qty-box" data-price="<?=$it['price']?>">
            <div class="cart-qty-control"><button class="cart-qty-btn" type="button" data-action="decrease"
                    aria-label="Giảm số lượng">-</button><input class="cart-qty-input" type="number" min="0"
                    name="qty[<?=$it['id']?>]" value="<?=$it['quantity']?>" inputmode="numeric"><button
                    class="cart-qty-btn" type="button" data-action="increase" aria-label="Tăng số lượng">+</button>
            </div>
            <div class="right small cart-line-total"><?=money_vnd($it['price']*$it['quantity'])?></div>
        </div>
    </div><?php endforeach; ?></form>
<form method="post" class="card">
    <div class="section-title">Hình thức</div>
    <div class="choice">
        <label class="btn btn-red">
            <input type="radio" name="order_type" value="dine_in" checked>
            🪑 Ăn tại quán
        </label>
        <label class="btn btn-red"><input type="radio" name="order_type" value="takeaway">
            🛍️ Mang đi
        </label>
    </div>
    <input class="input" name="note" placeholder="Ghi chú nếu có"><br><br>
    <div class="between"><b>Tổng tiền</b>
        <div class="big-total js-cart-total"><?=money_vnd(cart_total())?></div>
    </div>
    <br><button class="btn btn-red full" name="save_order" value="1">Xác nhận & Lưu đơn</button>
</form><?php endif; ?>
<script>
var cartQtyForm = document.getElementById('cart-qty-form');
var cartSubmitTimer = null;
var cartIsSubmitting = false;

function formatVnd(value) {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
    }).format(value);
}

function updateCartPreview() {
    var total = 0;
    document.querySelectorAll('.cart-qty-box').forEach(function(item) {
        var qtyInput = item.querySelector('.cart-qty-input');
        var price = Number(item.dataset.price || 0);
        var qty = Math.max(0, Number(qtyInput.value || 0));
        total += price * qty;
    });
    var totalNode = document.querySelector('.js-cart-total');
    if (totalNode) totalNode.textContent = formatVnd(total);
}

function submitCartQty(delay) {
    if (!cartQtyForm || cartIsSubmitting) return;
    if (cartSubmitTimer) clearTimeout(cartSubmitTimer);
    cartSubmitTimer = setTimeout(function() {
        cartIsSubmitting = true;
        cartQtyForm.requestSubmit();
    }, delay || 0);
}

document.querySelectorAll('.cart-qty-box').forEach(function(box) {
    var input = box.querySelector('.cart-qty-input');
    var lineTotal = box.querySelector('.cart-line-total');
    var unitPrice = Number(box.dataset.price || 0);

    function syncRow() {
        var qty = Math.max(0, Number(input.value || 0));
        input.value = qty;
        lineTotal.textContent = formatVnd(unitPrice * qty);
        updateCartPreview();
    }

    box.querySelectorAll('.cart-qty-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var step = btn.dataset.action === 'increase' ? 1 : -1;
            input.value = Math.max(0, Number(input.value || 0) + step);
            syncRow();
            submitCartQty(0);
        });
    });

    input.addEventListener('input', function() {
        syncRow();
        submitCartQty(500);
    });

    input.addEventListener('change', function() {
        syncRow();
        submitCartQty(0);
    });
});
</script>
<?php include 'footer.php'; ?>
