<?php require 'config/db.php'; require 'config/helpers.php';
require_non_staff();
$mode=$_GET['mode']??'day'; $date=$_GET['date']??today();
if($mode==='week'){ $start=date('Y-m-d',strtotime('monday this week',strtotime($date))); $end=date('Y-m-d',strtotime($start.' +6 days')); }
elseif($mode==='month'){ $start=date('Y-m-01',strtotime($date)); $end=date('Y-m-t',strtotime($date)); }
else { $start=$end=$date; }
$range="DATE(o.created_at) BETWEEN '$start' AND '$end'";
$total=$conn->query("SELECT COALESCE(SUM(total_amount),0) revenue, COUNT(*) orders, SUM(order_type='dine_in') dine_in, SUM(order_type='takeaway') takeaway FROM orders o WHERE $range")->fetch_assoc();
$items=$conn->query("SELECT oi.product_name, SUM(oi.quantity) qty, SUM(oi.subtotal) amount FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE $range GROUP BY oi.product_name ORDER BY amount DESC");
include 'header.php'; ?>
<div class="date">📊 Tổng kết <?=date('d/m/Y',strtotime($start))?><?= $start!=$end?' - '.date('d/m/Y',strtotime($end)):'' ?></div>
<div class="tabs"><a class="tab <?=$mode=='day'?'active':''?>" href="?mode=day">Ngày</a><a class="tab <?=$mode=='week'?'active':''?>" href="?mode=week">Tuần</a><a class="tab <?=$mode=='month'?'active':''?>" href="?mode=month">Tháng</a></div>
<form><input type="hidden" name="mode" value="<?=h($mode)?>"><input class="input" type="date" name="date" value="<?=h($date)?>" onchange="this.form.submit()"></form><br>
<div class="card"><div class="section-title">Chi tiết doanh thu</div><table class="table"><tr><th>Món</th><th class="right">SL</th><th class="right">Tiền</th></tr><?php while($i=$items->fetch_assoc()): ?><tr><td><?=h($i['product_name'])?></td><td class="right"><?=$i['qty']?></td><td class="right"><?=money_vnd($i['amount'])?></td></tr><?php endwhile; ?></table><br><div class="between"><span>Tổng số đơn</span><b><?=(int)$total['orders']?> đơn</b></div><br><div class="between"><span>Ăn tại quán</span><b><?=(int)$total['dine_in']?> đơn</b></div><br><div class="between"><span>Mang đi</span><b><?=(int)$total['takeaway']?> đơn</b></div><hr style="border:0;border-top:1px solid #f2dfd2"><div class="between"><b>Tổng doanh thu</b><div class="big-total"><?=money_vnd($total['revenue'])?></div></div></div>
<?php include 'footer.php'; ?>
