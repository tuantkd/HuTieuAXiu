<?php
    $currentPage = basename($_SERVER['PHP_SELF'] ?? '');
    $isSalesPage = in_array($currentPage, ['index.php', 'cart.php'], true);
    $isHistoryPage = $currentPage === 'history.php';
    $isSummaryPage = $currentPage === 'summary.php';
    $isSettingsPage = in_array($currentPage, ['expense.php', 'expense_form.php'], true);
?>
    </div>

    <div class="bottom">
        <a class="nav <?= $isSalesPage ? 'active' : '' ?>" href="index.php">
            <span>🏪</span>Bán hàng
        </a>
        <a class="nav <?= $isHistoryPage ? 'active' : '' ?>" href="history.php">
            <span>🕘</span>Lịch sử
        </a>

        <?php if (is_admin_role()): ?>
        <a class="nav <?= $isSummaryPage ? 'active' : '' ?>" href="summary.php">
            <span>📊</span>Thống kê
        </a>
        <a class="nav <?= $isSettingsPage ? 'active' : '' ?>" href="expense.php">
            <span>💸</span>Chi phí
        </a>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
