<?php
require_once __DIR__ . '/auth.php';

check_admin_login();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Vui lòng nhập tên đăng nhập và mật khẩu.';
    } elseif (login_admin($username, $password)) {
        header('Location: index.php');
        exit;
    } else {
        $error = 'Tên đăng nhập hoặc mật khẩu không chính xác.';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập quản trị POS</title>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="login-page">
    <div class="login-box login-box--wide">
        <div class="login-badge">POS quản trị</div>
        <div class="login-brand">Quán ăn A Xíu</div>
        <p class="login-note">Đăng nhập để bán hàng nhanh, quản lý món, theo dõi đơn hàng và báo cáo doanh thu trên XAMPP.</p>

        <?php if ($error): ?>
            <div class="admin-alert error"><?= admin_h($error) ?></div>
        <?php endif; ?>

        <form method="post" class="login-form">
            <div class="field">
                <label for="username">Tên đăng nhập</label>
                <input id="username" type="text" name="username" value="<?= admin_h($_POST['username'] ?? '') ?>" placeholder="admin" required>
            </div>
            <div class="field">
                <label for="password">Mật khẩu</label>
                <input id="password" type="password" name="password" placeholder="123456" required>
            </div>
            <button type="submit" class="button block">Đăng nhập</button>
        </form>

        <div class="login-accounts">
            <div class="login-account-card">
                <strong>Admin</strong>
                <span>admin / 123456</span>
            </div>
            <div class="login-account-card">
                <strong>Nhân viên</strong>
                <span>nhanvien / 123456</span>
            </div>
        </div>
    </div>
</body>
</html>
