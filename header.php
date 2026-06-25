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
            <div class="brand">Sổ Bán Hàng A Xíu</div><a class="icon-btn" href="cart.php">🛒 <span
                    class="js-cart-count"><?=cart_count()?></span></a>
        </div>
        <div class="content">
