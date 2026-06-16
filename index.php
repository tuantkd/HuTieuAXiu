<?php require 'config/db.php'; require 'config/helpers.php';
if (isset($_POST['add_product_id'])) {
  $pid=(int)$_POST['add_product_id']; $qty=max(1,(int)($_POST['quantity']??1));
  $p=$conn->query("SELECT p.*, c.slug FROM products p JOIN categories c ON c.id=p.category_id WHERE p.id=$pid AND is_active=1")->fetch_assoc();
  if($p){
    if(!isset($_SESSION['cart'])) $_SESSION['cart']=[];
    if(!isset($_SESSION['cart'][$pid])) $_SESSION['cart'][$pid]=['id'=>$pid,'name'=>$p['name'],'price'=>(int)$p['price'],'unit'=>$p['unit'],'image_url'=>$p['image_url'],'quantity'=>0];
    $_SESSION['cart'][$pid]['quantity'] += $qty;
  }
  redirect('index.php');
}
$filter=$_GET['cat']??'all';
$where="p.is_active=1"; if($filter==='mon-an'||$filter==='nuoc-uong') $where.=" AND c.slug='".$conn->real_escape_string($filter)."'";
$products=$conn->query("SELECT p.*, c.name category_name, c.slug FROM products p JOIN categories c ON c.id=p.category_id WHERE $where ORDER BY c.sort_order,p.id");
$today=today();
$sum=$conn->query("SELECT COALESCE(SUM(total_amount),0) revenue, COUNT(*) orders, SUM(order_type='dine_in') dine_in, SUM(order_type='takeaway') takeaway FROM orders WHERE DATE(created_at)='$today'")->fetch_assoc();
$itemStats=$conn->query("SELECT c.slug, SUM(oi.quantity) qty FROM order_items oi JOIN products p ON p.id=oi.product_id JOIN categories c ON c.id=p.category_id JOIN orders o ON o.id=oi.order_id WHERE DATE(o.created_at)='$today' GROUP BY c.slug");
$food=0;$drink=0; while($r=$itemStats->fetch_assoc()){ if($r['slug']=='mon-an')$food=$r['qty']; else $drink=$r['qty']; }
include 'header.php'; ?>
<div class="date">📅 Hôm nay: <?=today_vi()?></div>
<div class="section-title">Chọn nhóm món</div><div class="tabs"><a class="tab <?=$filter=='all'?'active':''?>" href="index.php">Tất cả</a><a class="tab <?=$filter=='mon-an'?'active':''?>" href="?cat=mon-an">Món ăn</a><a class="tab <?=$filter=='nuoc-uong'?'active':''?>" href="?cat=nuoc-uong">Nước uống</a></div>
<div class="section-title">Chọn món</div><div class="grid <?=$filter=='nuoc-uong'?'drink':''?>">
<?php while($p=$products->fetch_assoc()): ?><form method="post" class="product"><img src="<?=h($p['image_url'])?>"><div class="p"><b><?=h($p['name'])?></b><div class="price"><?=money_vnd($p['price'])?></div><input type="hidden" name="add_product_id" value="<?=$p['id']?>"><input type="hidden" name="quantity" value="1"><button class="btn btn-light full" style="margin-top:8px">+ Thêm</button></div></form><?php endwhile; ?></div>
<div class="summary-card"><div class="between"><div class="section-title" style="margin:0">Tổng quan hôm nay</div><a class="small" href="cart.php">Xem chi tiết ›</a></div><div class="mini-grid"><div class="mini"><span class="emoji">🍜</span><div>Món ăn<br><b><?=$food?:0?> tô/phần</b></div></div><div class="mini"><span class="emoji">🥤</span><div>Nước uống<br><b><?=$drink?:0?> ly/chai</b></div></div><div class="mini"><span class="emoji">🪑</span><div>Ăn tại quán<br><b><?=(int)$sum['dine_in']?> đơn</b></div></div><div class="mini"><span class="emoji">🛍️</span><div>Mang đi<br><b><?=(int)$sum['takeaway']?> đơn</b></div></div></div><hr style="border:0;border-top:1px solid #f2dfd2"><div class="between"><b>Doanh thu</b><div class="big-total"><?=money_vnd($sum['revenue'])?></div></div></div>
<a class="btn btn-red full" href="summary.php">📊 Tổng kết hôm nay</a>
<?php include 'footer.php'; ?>
