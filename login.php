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
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body {
            background: linear-gradient(180deg, #fff4ea, #ffe7d5);
        }

        .login-wrapper {
            max-width: 420px;
            margin: 40px auto;
            padding: 24px;
            background: white;
            border: 1px solid #f0ded0;
            border-radius: 22px;
            box-shadow: 0 22px 40px rgba(239, 29, 36, .12);
        }

        .login-title {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 12px;
        }

        .login-subtitle {
            color: #666;
            margin-bottom: 22px;
        }

        .field {
            margin-bottom: 16px;
        }

        .field label {
            display: block;
            margin-bottom: 6px;
            font-weight: 700;
        }

        .field input {
            width: 100%;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid #e8d7cc;
            background: #fffaf5;
        }

        .login-note {
            margin-top: 18px;
            font-size: 14px;
            color: #555;
        }

        .alert {
            padding: 12px 14px;
            border-radius: 14px;
            background: #ffebeb;
            color: #9f2525;
            margin-bottom: 18px;
        }
    </style>
</head>

<body>
    <div class="login-wrapper">
        <div class="login-title">Đăng nhập bán hàng</div>
        <div class="login-subtitle">Hãy đăng nhập để tiếp tục tạo đơn và xem lịch sử.</div>

        <?php if ($error): ?>
            <div class="alert"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="field">
                <label for="username">Tên đăng nhập</label>
                <input id="username" name="username" type="text" value="<?= h($_POST['username'] ?? '') ?>" required>
            </div>
            <div class="field">
                <label for="password">Mật khẩu</label>
                <input id="password" name="password" type="password" required>
            </div>
            <button type="submit" class="btn btn-red full">Đăng nhập</button>
        </form>

        <div class="login-note">Mặc định: admin / 123456 hoặc nhanvien / 123456</div>
    </div>
</body>

</html>
