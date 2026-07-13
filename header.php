<?php
require_once __DIR__ . '/config/helpers.php'; ?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Sổ Bán Hàng A Xíu</title>
    <link rel="icon" href="assets/img/logo-axiu.png" type="image/png">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css"
        integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous">
</head>
<body style="font-family: Roboto, sans-serif;">
    <div class="app">
        <div class="top">
            <a class="icon-btn" href="javascript:history.back()">
                <i class="fa fa-arrow-left"></i>
            </a>
            <div>
                <div class="brand">Sổ Bán Hàng A Xíu</div>
                <?php if (isLoggedIn()): ?>
                    <div class="small" style="color:#fff; opacity:.9; margin-top:4px;">
                        Xin chào, <b><?= h(currentUserName()) ?></b>
                    </div>
                <?php endif; ?>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                <?php if (isLoggedIn()): ?>
                    <a class="icon-btn" href="logout.php" title="Đăng xuất" style="color:#fff;">
                        <i class="fa fa-sign-out"></i>
                    </a>
                <?php endif; ?>
                <a class="icon-btn" href="cart.php" title="Giỏ hàng">
                    <i class="fa fa-shopping-cart"></i>
                    <span class="js-cart-count"><?= cart_count() ?></span>
                </a>
            </div>
        </div>
        <?php render_toasts(); ?>
        <div class="content">
