<?php require 'config/db.php'; require 'config/helpers.php'; $date=$_GET['date']??today(); $orders=$conn->query("SELECT * FROM orders WHERE DATE(created_at)='".$conn->real_escape_string($date)."' ORDER BY id DESC"); include 'header.php'; ?>
<div class="date">🕘 Lịch sử bán hàng</div><form><input class="input" type="date" name="date" value="<?=h($date)?>" onchange="this.form.submit()"></form><br>
<?php if($orders->num_rows==0): ?><div class="card">Chưa có đơn trong ngày này.</div><?php endif; ?>
<?php while($o=$orders->fetch_assoc()): ?><a class="card" style="display:block" href="order_detail.php?id=<?=$o['id']?>"><div class="between"><b>#<?=h($o['order_code'])?></b><span class="price"><?=money_vnd($o['total_amount'])?></span></div><div class="small"><?=date('H:i d/m/Y',strtotime($o['created_at']))?> · <?=order_type_text($o['order_type'])?></div></a><?php endwhile; ?>
<?php include 'footer.php'; ?>
