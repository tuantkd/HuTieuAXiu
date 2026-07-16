<?php
if (!defined('ADMIN_APP')) {
    define('ADMIN_APP', true);
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';

$pageTitle = $page_title ?? 'Quản trị bán hàng';
$currentPage = adminCurrentPage();
$flash = adminFlashGet();

$menuItems = [
    ['href' => 'pos.php', 'label' => 'POS bán hàng', 'roles' => [ADMIN_ROLE, STAFF_ROLE]],
    ['href' => 'dashboard.php', 'label' => 'Bảng điều khiển', 'roles' => [ADMIN_ROLE]],
    ['href' => 'products.php', 'label' => 'Quản lý sản phẩm', 'roles' => [ADMIN_ROLE]],
    ['href' => 'categories.php', 'label' => 'Quản lý loại sản phẩm', 'roles' => [ADMIN_ROLE]],
    ['href' => 'orders.php', 'label' => 'Quản lý đơn hàng', 'roles' => [ADMIN_ROLE]],
    ['href' => 'transactions.php', 'label' => 'Quản lý thu chi', 'roles' => [ADMIN_ROLE]],
    ['href' => 'report.php', 'label' => 'Quản lý báo cáo', 'roles' => [ADMIN_ROLE]],
    ['href' => 'users.php', 'label' => 'Quản lý người dùng', 'roles' => [ADMIN_ROLE]],
    ['href' => 'settings.php', 'label' => 'Cài đặt', 'roles' => [ADMIN_ROLE]],
];
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= adminH($pageTitle) ?> - POS A Xíu</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css"
        integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous">
</head>

<body>
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <div class="brand-block">
                <div class="brand-logo">
                    <img src="../assets/img/logo-axiu.png" alt="Logo">
                </div>
                <div>
                    <strong>Bì Cuốn A Xíu</strong>
                    <div class="brand-sub">POS bán hàng</div>
                </div>
            </div>

            <nav class="sidebar-menu">
                <?php foreach ($menuItems as $item): ?>
                    <?php if (!in_array(adminRole(), $item['roles'], true)) {
                        continue;
                    } ?>
                    <a class="sidebar-link <?= $currentPage === $item['href'] ? 'active' : '' ?>"
                        href="<?= adminH($item['href']) ?>">
                        <?= adminH($item['label']) ?>
                    </a>
                <?php endforeach; ?>
                <a class="sidebar-link <?= $currentPage === 'change_password.php' ? 'active' : '' ?>"
                    href="change_password.php">Đổi mật khẩu</a>
                <a class="sidebar-link logout" href="logout.php">
                    <i class="fa fa-sign-out"></i> Đăng xuất
                </a>
            </nav>
        </aside>

        <main class="admin-content">
            <header class="admin-header">
                <div>
                    <span class="page-tag"><?= isAdmin() ? 'Toàn quyền quản trị' : 'Quầy bán hàng' ?></span>
                    <h1><?= adminH($pageTitle) ?></h1>
                    <div class="helper-text">Xin chào, <?= adminH(adminFullName() ?: adminUserName() ?: 'Nhân viên') ?>
                    </div>
                </div>
                <div class="header-meta">
                    <span class="meta-pill"><?= adminH(admin_role_label(adminRole())) ?></span>
                    <span class="meta-pill meta-pill--light"><?= adminH(adminUserName()) ?></span>
                </div>
            </header>

            <?php if ($flash): ?>
                <div class="admin-alert <?= adminH($flash['type']) ?>"><?= adminH($flash['message']) ?></div>
            <?php endif; ?>

            <section class="page-body">
