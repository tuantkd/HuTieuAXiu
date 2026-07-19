<?php
require_once 'config/db.php';
require_once 'config/helpers.php';
requireLogin();

if (isset($_POST['add_product_id'])) {
    $pid = (int) $_POST['add_product_id'];
    $qty = max(1, (int) ($_POST['quantity'] ?? 1));
    $p = $conn->query("SELECT p.*, c.slug FROM products p JOIN categories c ON c.id=p.category_id WHERE p.id=$pid AND is_active=1")->fetch_assoc();
    if ($p) {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        if (!isset($_SESSION['cart'][$pid])) {
            $_SESSION['cart'][$pid] = ['id' => $pid, 'name' => $p['name'], 'price' => (int) $p['price'], 'unit' => $p['unit'], 'image_url' => $p['image_url'], 'quantity' => 0];
        }
        $_SESSION['cart'][$pid]['quantity'] += $qty;
        toast('success', 'Đã thêm "' . $p['name'] . '" vào giỏ hàng.', true);
    } else {
        toast('warning', 'Sản phẩm không còn khả dụng hoặc đã bị ẩn.', true);
    }
    redirect('cart.php');
}

$filter = $_GET['cat'] ?? 'all';
$categories = $conn->query('SELECT slug, name FROM categories ORDER BY sort_order, id');
$categoryTabs = [];
while ($category = $categories->fetch_assoc()) {
    $categoryTabs[$category['slug']] = $category['name'];
}
if ($filter !== 'all' && !isset($categoryTabs[$filter])) {
    $filter = 'all';
}

$where = "p.is_active=1";
if ($filter !== 'all') {
    $where .= " AND c.slug='" . $conn->real_escape_string($filter) . "'";
}

$products = $conn->query("SELECT p.*, c.name category_name, c.slug FROM products p JOIN categories c ON c.id=p.category_id WHERE $where ORDER BY c.sort_order,p.id");
$today = today();
$todayStart = $today . ' 00:00:00';
$tomorrowStart = date('Y-m-d 00:00:00', strtotime($today . ' +1 day'));
$sum = $conn->query("
    SELECT
        COALESCE(SUM(total_amount), 0) AS revenue,
        COUNT(*) AS orders,
        COALESCE(SUM(order_type='cash'), 0) AS cash,
        COALESCE(SUM(order_type='bank_transfer'), 0) AS bank_transfer
    FROM orders
    WHERE created_at >= '$todayStart' AND created_at < '$tomorrowStart'
")->fetch_assoc();
$categoryStats = $conn->query("
    SELECT
        c.slug,
        c.name,
        COALESCE(SUM(CASE WHEN o.id IS NOT NULL THEN oi.quantity ELSE 0 END), 0) AS qty
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    LEFT JOIN order_items oi ON oi.product_id = p.id
    LEFT JOIN orders o ON o.id = oi.order_id
        AND o.created_at >= '$todayStart'
        AND o.created_at < '$tomorrowStart'
    GROUP BY c.slug, c.name
    ORDER BY c.sort_order, c.id
");
$categoryTotals = [];
while ($r = $categoryStats->fetch_assoc()) {
    $categoryTotals[$r['slug']] = [
        'name' => $r['name'],
        'qty' => (int) $r['qty'],
    ];
}

include_once 'header.php'; ?>

<div class="date">📅 Hôm nay: <?= todayVi() ?></div>
<div class="section-title">Chọn loại sản phẩm</div>
<div class="tabs">
    <a class="tab <?= $filter === 'all' ? 'active' : '' ?>" href="index.php">
        Tất cả
    </a>
    <?php foreach ($categoryTabs as $slug => $name): ?>
        <a aria-label="Xem chi tiết" class="tab <?= $filter === $slug ? 'active' : '' ?>" href="?cat=<?= h($slug) ?>">
            <?= h($name) ?>
        </a>
    <?php endforeach; ?>
</div>
<div class="section-title">Chọn sản phẩm</div>
<div class="grid <?= $filter == 'nuoc-uong' ? 'drink' : '' ?>">
    <?php while ($p = $products->fetch_assoc()): ?>
        <form method="post" class="product">
            <img src="<?= h($p['image_url']) ?>" alt="<?= h($p['name']) ?>" class="product-image">
            <div class="p"><b><?= h($p['name']) ?></b>
                <div class="price"><?= moneyVND($p['price']) ?></div>
                <input type="hidden" name="add_product_id" value="<?= $p['id'] ?>">
                <input type="hidden" name="quantity" value="1">
                <button class="btn btn-light full" style="margin-top:8px">+ Thêm</button>
            </div>
        </form>
    <?php endwhile; ?>
</div>
<div class="summary-card">
    <div class="between">
        <div class="section-title" style="margin:0">Tổng quan hôm nay</div>
    </div>
    <div class="mini-grid">
        <?php foreach ($categoryTotals as $slug => $stats): ?>
            <div class="mini">
                <span class="emoji"><?= h(category_icon($slug)) ?></span>
                <div><?= h($stats['name']) ?><br><b><?= h($stats['qty']) ?></b></div>
            </div>
        <?php endforeach; ?>
        <div class="mini"><span class="emoji"><?= h(order_type_icon('cash')) ?></span>
            <div>Tiền mặt<br><b><?= (int) $sum['cash'] ?> đơn</b></div>
        </div>
        <div class="mini"><span class="emoji"><?= h(order_type_icon('bank_transfer')) ?></span>
            <div>Chuyển khoản<br><b><?= (int) $sum['bank_transfer'] ?> đơn</b></div>
        </div>
    </div>
    <hr style="border:0;border-top:1px solid #f2dfd2">
    <div class="between"><b>Doanh thu</b>
        <div class="big-total"><?= moneyVND($sum['revenue']) ?></div>
    </div>
</div>
<?php include_once 'footer.php'; ?>
