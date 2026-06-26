<?php require_once __DIR__.'/config/helpers.php'; ?>
<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Sổ Bán Hàng A Xíu</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">
</head>

<body style="font-family: Roboto, sans-serif;">
    <div class="app">
        <div class="top"><a class="icon-btn" href="javascript:history.back()"><i class="fa fa-long-arrow-left"></i></a>
            <div>
                <div class="brand">Sổ Bán Hàng A Xíu</div>
                <?php if (isLoggedIn()): ?>
                    <div class="small" style="color:#fff; opacity:.9; margin-top:4px;">Xin chào, <?= h(currentUserName()) ?></div>
                <?php endif; ?>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                <?php if (isLoggedIn()): ?>
                    <a class="icon-btn" href="logout.php">🚪</a>
                <?php endif; ?>
                <a class="icon-btn" href="cart.php">🛒 <span class="js-cart-count"><?=cart_count()?></span></a>
            </div>
        </div>
        <div class="content">
