<?php require_once __DIR__.'/config/helpers.php'; ?>
<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Sổ Bán Hàng A Xíu</title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body>
    <div class="app">
        <div class="top"><a class="icon-btn" href="javascript:history.back()">←</a>
            <div class="brand">Sổ Bán Hàng A Xíu</div><a class="icon-btn" href="cart.php">🛒 <?=cart_count()?></a>
        </div>
        <div class="content">