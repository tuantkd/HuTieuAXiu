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
        toast('warning', $error);
    } elseif (login($username, $password)) {
        toast('success', 'Đăng nhập thành công.', true);
        redirect('index.php');
    } else {
        $error = 'Tên đăng nhập hoặc mật khẩu không chính xác.';
        toast('error', $error);
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
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css"
        integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous">
</head>

<body class="login-page">
    <?php render_toasts(); ?>
    <main class="login-stage">
        <div class="login-card">
            <section class="login-showcase" aria-label="Giới thiệu Sổ Tay A Xíu">
                <div class="login-showcase__ornament" aria-hidden="true"></div>

                <div class="login-showcase__brand">
                    <img class="login-logo" src="assets/img/logo-axiu.png" alt="Logo A Xíu">
                    <p class="login-kicker">Sổ Tay</p>
                    <h1 class="login-display">A Xíu</h1>
                    <p class="login-showcase__copy">
                        Quản lý đơn hàng - Theo dõi lịch sử - Tạo đơn nhanh chóng
                    </p>
                </div>

                <div class="login-feature-list" aria-label="Điểm nổi bật">
                    <article class="login-feature">
                        <span class="login-feature__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path
                                    d="M7 18c-.6 0-1 .4-1 1s.4 1 1 1 1-.4 1-1-.4-1-1-1Zm10 0c-.6 0-1 .4-1 1s.4 1 1 1 1-.4 1-1-.4-1-1-1ZM7.2 14h9.9c.8 0 1.5-.5 1.8-1.2l2-4.7a1 1 0 0 0-.9-1.4H6.2L5.7 4.6A1 1 0 0 0 4.7 4H3a1 1 0 1 0 0 2h1l2.3 9.2A2 2 0 0 0 8.2 17H18a1 1 0 1 0 0-2H8.2Z" />
                            </svg>
                        </span>
                        <div>
                            <h2>Tạo đơn nhanh</h2>
                            <p>Tạo đơn hàng chỉ với vài thao tác đơn giản</p>
                        </div>
                    </article>

                    <article class="login-feature">
                        <span class="login-feature__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path
                                    d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm1 10.6 3.2 1.9a1 1 0 1 1-1 1.7l-3.7-2.2a1 1 0 0 1-.5-.8V7a1 1 0 1 1 2 0Z" />
                            </svg>
                        </span>
                        <div>
                            <h2>Theo dõi lịch sử</h2>
                            <p>Xem lại và quản lý các đơn hàng dễ dàng</p>
                        </div>
                    </article>

                    <article class="login-feature">
                        <span class="login-feature__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path
                                    d="M8 3a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h8a3 3 0 0 0 3-3V6a3 3 0 0 0-3-3Zm0 2h8a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm2 12a1 1 0 1 0 0 2h4a1 1 0 1 0 0-2Z" />
                            </svg>
                        </span>
                        <div>
                            <h2>Dễ thao tác</h2>
                            <p>Tối ưu giao diện cho điện thoại và máy tính</p>
                        </div>
                    </article>
                </div>
            </section>

            <section class="login-panel" aria-label="Biểu mẫu đăng nhập">
                <div class="login-panel__body">
                    <header class="login-panel__intro">
                        <div class="login-wave" aria-hidden="true">👋</div>
                        <div>
                            <h2>Chào mừng trở lại!</h2>
                            <p>Đăng nhập để tiếp tục sử dụng Sổ Tay A Xíu</p>
                        </div>
                    </header>

                    <form method="post" class="login-form">
                        <div class="field">
                            <label for="username">Tên đăng nhập</label>
                            <div class="field-control">
                                <span class="field-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" focusable="false">
                                        <path
                                            d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4.4 0-8 2.2-8 5a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1c0-2.8-3.6-5-8-5Z" />
                                    </svg>
                                </span>
                                <input id="username" name="username" type="text"
                                    value="<?= h($_POST['username'] ?? '') ?>" placeholder="Nhập tên đăng nhập"
                                    autocomplete="username" inputmode="text" required>
                            </div>
                        </div>

                        <div class="field">
                            <label for="password">Mật khẩu</label>
                            <div class="field-control">
                                <span class="field-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" focusable="false">
                                        <path
                                            d="M17 9h-1V7a4 4 0 0 0-8 0v2H7a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2Zm-7-2a2 2 0 1 1 4 0v2h-4Zm3 8.7V17a1 1 0 1 1-2 0v-1.3a2 2 0 1 1 2 0Z" />
                                    </svg>
                                </span>
                                <input id="password" name="password" type="password" placeholder="Nhập mật khẩu"
                                    autocomplete="current-password" required>
                                <button type="button" class="field-toggle" data-password-toggle aria-controls="password"
                                    aria-label="Hiện mật khẩu">
                                    <svg viewBox="0 0 24 24" focusable="false">
                                        <path
                                            d="M12 5C6.5 5 2.1 8.6 1 12c1.1 3.4 5.5 7 11 7s9.9-3.6 11-7c-1.1-3.4-5.5-7-11-7Zm0 11a4 4 0 1 1 4-4 4 4 0 0 1-4 4Zm0-6a2 2 0 1 0 2 2 2 2 0 0 0-2-2Z" />
                                        <path d="M4.2 3.8a1 1 0 0 0-1.4 1.4l15.4 15.4a1 1 0 0 0 1.4-1.4Z" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-red full login-submit">
                            <span>Đăng nhập</span>
                            <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                                <path
                                    d="M13.2 5.8a1 1 0 0 1 1.4 0l5.4 5.4a1.1 1.1 0 0 1 0 1.5l-5.4 5.5a1 1 0 1 1-1.4-1.4l3.7-3.8H5a1 1 0 1 1 0-2h11.9l-3.7-3.7a1 1 0 0 1 0-1.5Z" />
                            </svg>
                        </button>
                    </form>

                    <div class="login-note">
                        <span class="login-note__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path
                                    d="M12 2 5 5v6c0 4.6 3 8.8 7 10 4-1.2 7-5.4 7-10V5Zm0 17c-2.8-1.1-5-4.5-5-8V6.4l5-2 5 2V11c0 3.5-2.2 6.9-5 8Z" />
                            </svg>
                        </span>
                        <div>
                            <strong>Bảo mật &amp; An toàn</strong>
                            <p>Thông tin của bạn được bảo vệ tuyệt đối</p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <p class="login-footer">© <?php echo date('Y'); ?> Sổ Tay A Xíu. All rights reserved.</p>

    <script>
        (function () {
            function closeToast(toast) {
                if (!toast || toast.classList.contains('is-leaving')) {
                    return;
                }

                toast.classList.add('is-leaving');
                window.setTimeout(function () {
                    if (toast && toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 220);
            }

            document.querySelectorAll('[data-toast]').forEach(function (toast, index) {
                var closeButton = toast.querySelector('[data-toast-close]');
                var delay = 2600 + (index * 180);

                if (closeButton) {
                    closeButton.addEventListener('click', function () {
                        closeToast(toast);
                    });
                }

                window.setTimeout(function () {
                    closeToast(toast);
                }, delay);
            });

            var toggle = document.querySelector('[data-password-toggle]');
            var password = document.getElementById('password');

            if (!toggle || !password) {
                return;
            }

            toggle.addEventListener('click', function () {
                var isHidden = password.type === 'password';
                password.type = isHidden ? 'text' : 'password';
                toggle.setAttribute('aria-label', isHidden ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
                toggle.classList.toggle('is-active', isHidden);
            });
        })();
    </script>
</body>

</html>
