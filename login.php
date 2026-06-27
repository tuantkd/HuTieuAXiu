<?php
require_once 'config/db.php';
require_once 'config/helpers.php';

if (isLoggedIn()) {
    if (currentRole() === 'admin') {
        redirect('admin/dashboard.php');
    }
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Vui lòng nhập tên đăng nhập và mật khẩu.';
    } elseif (login($username, $password)) {
        if (currentRole() === 'admin') {
            redirect('admin/dashboard.php');
        }
        redirect('index.php');
    } else {
        $error = 'Tên đăng nhập hoặc mật khẩu không chính xác.';
    }
}
?>

<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Đăng nhập bán hàng</title>
    <link rel="icon" href="assets/img/logo-axiu.png" type="image/png">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/login.css">
</head>

<body class="login-page">
    <div class="login-shell">
        <section class="login-hero" aria-label="Giới thiệu đăng nhập">
            <div class="login-badge">Sổ Tay A Xíu</div>
            <img class="login-logo" src="assets/img/logo-axiu.png" alt="Logo A Xíu">
            <div class="login-title">Đăng nhập bán hàng</div>
            <div class="login-subtitle">Giao diện tối ưu cho điện thoại, thao tác nhanh để tạo đơn và xem lịch sử thuận tiện hơn.</div>
            <div class="login-highlights">
                <div class="login-highlight">Tạo đơn nhanh</div>
                <div class="login-highlight">Theo dõi lịch sử</div>
                <div class="login-highlight">Dễ thao tác trên điện thoại</div>
            </div>
        </section>

        <div class="login-wrapper">
            <?php if ($error): ?>
                <div class="alert"><?= h($error) ?></div>
            <?php endif; ?>

            <form method="post" class="login-form">
                <div class="field">
                    <label for="username">Tên đăng nhập</label>
                    <input
                        id="username"
                        name="username"
                        type="text"
                        value="<?= h($_POST['username'] ?? '') ?>"
                        placeholder="Nhập tên đăng nhập"
                        autocomplete="username"
                        inputmode="text"
                        required>
                </div>
                <div class="field">
                    <label for="password">Mật khẩu</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        placeholder="Nhập mật khẩu"
                        autocomplete="current-password"
                        required>
                </div>
                <button type="submit" class="btn btn-red full login-submit">Đăng nhập</button>
            </form>

            <div class="login-note">
                <strong>Tài khoản mặc định</strong>
                <span>admin / 123456 hoặc nhanvien / 123456</span>
            </div>
        </div>
    </div>
</body>

</html>
