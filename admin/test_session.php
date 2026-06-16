<?php
require_once __DIR__ . '/config.php';

require_admin();
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Kiểm tra session</title>
</head>
<body>
<h2>Session hiện tại</h2>
<ul>
    <li>user_id: <?= htmlspecialchars($_SESSION['user_id'] ?? '') ?></li>
    <li>username: <?= htmlspecialchars($_SESSION['username'] ?? '') ?></li>
    <li>full_name: <?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></li>
    <li>role: <?= htmlspecialchars($_SESSION['role'] ?? '') ?></li>
    <li>admin_id: <?= htmlspecialchars($_SESSION['admin_id'] ?? '') ?></li>
    <li>admin_username: <?= htmlspecialchars($_SESSION['admin_username'] ?? '') ?></li>
    <li>admin_role: <?= htmlspecialchars($_SESSION['admin_role'] ?? '') ?></li>
</ul>
<p><a href="logout.php">Đăng xuất</a></p>
</body>
</html>
