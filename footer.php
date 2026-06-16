<?php
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$isSalesPage = in_array($currentPage, ['index.php', 'cart.php'], true);
$isHistoryPage = $currentPage === 'history.php';
$isSummaryPage = $currentPage === 'summary.php';
$isSettingsPage = in_array($currentPage, ['products.php', 'product_form.php'], true);
?>
</div>
<div class="bottom">
    <a class="nav <?= $isSalesPage ? 'active' : '' ?>" href="index.php"><span>🏪</span>Bán hàng</a>
    <a class="nav <?= $isHistoryPage ? 'active' : '' ?>" href="history.php"><span>🕘</span>Lịch sử</a>
    <?php if (!is_staff_role()): ?>
    <a class="nav <?= $isSummaryPage ? 'active' : '' ?>" href="summary.php"><span>📊</span>Thống kê</a>
    <a class="nav <?= $isSettingsPage ? 'active' : '' ?>" href="products.php"><span>⚙️</span>Cài đặt</a>
    <?php endif; ?>
</div>
</div>
</body>

</html>
