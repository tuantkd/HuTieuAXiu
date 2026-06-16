<?php
if (!defined('ADMIN_APP')) {
    define('ADMIN_APP', true);
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';

$pageTitle = $page_title ?? 'Quản trị bán hàng';
$currentPage = admin_current_page();
$flash = admin_flash_get();

$menuItems = [
    ['href' => 'pos.php', 'label' => 'POS bán hàng', 'roles' => [ADMIN_ROLE, STAFF_ROLE]],
    ['href' => 'dashboard.php', 'label' => 'Dashboard', 'roles' => [ADMIN_ROLE]],
    ['href' => 'products.php', 'label' => 'Quản lý món', 'roles' => [ADMIN_ROLE]],
    ['href' => 'orders.php', 'label' => 'Quản lý đơn hàng', 'roles' => [ADMIN_ROLE]],
    ['href' => 'transactions.php', 'label' => 'Thu chi', 'roles' => [ADMIN_ROLE]],
    ['href' => 'report.php', 'label' => 'Báo cáo', 'roles' => [ADMIN_ROLE]],
    ['href' => 'settings.php', 'label' => 'Cài đặt', 'roles' => [ADMIN_ROLE]],
    ['href' => 'users.php', 'label' => 'Quản lý người dùng', 'roles' => [ADMIN_ROLE]],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= admin_h($pageTitle) ?> - POS A Xíu</title>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <div class="brand-block">
            <div class="brand-logo">AX</div>
            <div>
                <strong>Quán ăn A Xíu</strong>
                <div class="brand-sub">POS bán hàng trên XAMPP</div>
            </div>
        </div>

        <nav class="sidebar-menu">
            <?php foreach ($menuItems as $item): ?>
                <?php if (!in_array(admin_role(), $item['roles'], true)) continue; ?>
                <a class="sidebar-link <?= $currentPage === $item['href'] ? 'active' : '' ?>" href="<?= admin_h($item['href']) ?>">
                    <?= admin_h($item['label']) ?>
                </a>
            <?php endforeach; ?>
            <a class="sidebar-link <?= $currentPage === 'change_password.php' ? 'active' : '' ?>" href="change_password.php">Đổi mật khẩu</a>
            <a class="sidebar-link logout" href="logout.php">Đăng xuất</a>
        </nav>
    </aside>

    <main class="admin-content">
        <header class="admin-header">
            <div>
                <span class="page-tag"><?= is_admin() ? 'Toàn quyền quản trị' : 'Quầy bán hàng' ?></span>
                <h1><?= admin_h($pageTitle) ?></h1>
                <div class="helper-text">Xin chào, <?= admin_h(admin_full_name() ?: admin_username() ?: 'Nhân viên') ?></div>
            </div>
            <div class="header-meta">
                <span class="meta-pill"><?= admin_h(admin_role_label(admin_role())) ?></span>
                <span class="meta-pill meta-pill--light"><?= admin_h(admin_username()) ?></span>
            </div>
        </header>

        <?php if ($flash): ?>
            <div class="admin-alert <?= admin_h($flash['type']) ?>"><?= admin_h($flash['message']) ?></div>
        <?php endif; ?>

        <section class="page-body">
