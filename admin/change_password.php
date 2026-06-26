<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

requireAdmin();

$page_title = 'Đổi mật khẩu';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = trim($_POST['current_password'] ?? '');
    $newPassword = trim($_POST['new_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $errors[] = 'Vui lòng nhập đầy đủ thông tin.';
    } elseif ($newPassword !== $confirmPassword) {
        $errors[] = 'Mật khẩu mới và xác nhận mật khẩu chưa khớp.';
    } elseif (strlen($newPassword) < 6) {
        $errors[] = 'Mật khẩu mới cần tối thiểu 6 ký tự.';
    } else {
        $row = admin_fetch_one('SELECT password_hash FROM users WHERE id = ? LIMIT 1', 'i', [adminUserId()]);
        if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
            $errors[] = 'Mật khẩu hiện tại không chính xác.';
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            admin_execute('UPDATE users SET password_hash = ? WHERE id = ?', 'si', [$newHash, adminUserId()]);
            adminFlashSet('Đổi mật khẩu thành công.', 'success');
            header('Location: ' . (isAdmin() ? 'dashboard.php' : 'pos.php'));
            exit;
        }
    }
}

define('ADMIN_APP', true);
include __DIR__ . '/layout/header.php';
?>
<div class="panel">
    <div class="panel-header">
        <h3>Đổi mật khẩu</h3>
    </div>

    <?php if ($errors): ?>
        <div class="admin-alert error"><?= adminH(implode(' ', $errors)) ?></div>
    <?php endif; ?>

    <form method="post" class="form-card">
        <div class="field field--full">
            <label for="current_password">Mật khẩu hiện tại</label>
            <input id="current_password" type="password" name="current_password" required>
        </div>
        <div class="field">
            <label for="new_password">Mật khẩu mới</label>
            <input id="new_password" type="password" name="new_password" required>
        </div>
        <div class="field">
            <label for="confirm_password">Xác nhận mật khẩu mới</label>
            <input id="confirm_password" type="password" name="confirm_password" required>
        </div>
        <div class="field field--full">
            <button type="submit" class="button">Lưu mật khẩu mới</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>
