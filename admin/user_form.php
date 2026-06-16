<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

require_role(ADMIN_ROLE);

$userId = (int) ($_GET['id'] ?? 0);
$isEditing = $userId > 0;
$page_title = $isEditing ? 'Cập nhật người dùng' : 'Tạo người dùng mới';

$user = [
    'username' => '',
    'full_name' => '',
    'role' => STAFF_ROLE,
];

if ($isEditing) {
    $user = admin_fetch_one('SELECT id, username, full_name, role FROM users WHERE id = ? LIMIT 1', 'i', [$userId]);
    if (!$user) {
        admin_flash_set('Không tìm thấy tài khoản cần sửa.', 'error');
        header('Location: users.php');
        exit;
    }
    $user['role'] = admin_normalize_role($user['role']);
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user['username'] = trim($_POST['username'] ?? '');
    $user['full_name'] = trim($_POST['full_name'] ?? '');
    $user['role'] = admin_normalize_role($_POST['role'] ?? STAFF_ROLE);
    $password = trim($_POST['password'] ?? '');

    if ($user['username'] === '') {
        $errors[] = 'Tên đăng nhập không được để trống.';
    }
    if ($user['full_name'] === '') {
        $errors[] = 'Họ tên không được để trống.';
    }
    if (!$isEditing && $password === '') {
        $errors[] = 'Mật khẩu là bắt buộc khi tạo tài khoản.';
    }

    $existing = admin_fetch_one(
        'SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1',
        'si',
        [$user['username'], $userId]
    );
    if ($existing) {
        $errors[] = 'Tên đăng nhập đã tồn tại.';
    }

    if (empty($errors)) {
        if ($isEditing) {
            if ($password !== '') {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                admin_execute(
                    'UPDATE users SET username = ?, full_name = ?, role = ?, password_hash = ? WHERE id = ?',
                    'ssssi',
                    [$user['username'], $user['full_name'], $user['role'], $passwordHash, $userId]
                );
            } else {
                admin_execute(
                    'UPDATE users SET username = ?, full_name = ?, role = ? WHERE id = ?',
                    'sssi',
                    [$user['username'], $user['full_name'], $user['role'], $userId]
                );
            }
            admin_flash_set('Đã cập nhật tài khoản thành công.', 'success');
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            admin_execute(
                'INSERT INTO users (username, password_hash, full_name, role, created_at) VALUES (?, ?, ?, ?, NOW())',
                'ssss',
                [$user['username'], $passwordHash, $user['full_name'], $user['role']]
            );
            admin_flash_set('Đã tạo tài khoản mới thành công.', 'success');
        }

        header('Location: users.php');
        exit;
    }
}

define('ADMIN_APP', true);
include __DIR__ . '/layout/header.php';
?>
<div class="panel">
    <div class="panel-header">
        <h3><?= admin_h($page_title) ?></h3>
        <a class="button light small" href="users.php">Quay lại</a>
    </div>

    <?php if ($errors): ?>
        <div class="admin-alert error"><?= admin_h(implode(' ', $errors)) ?></div>
    <?php endif; ?>

    <form method="post" class="form-card">
        <div class="field">
            <label for="username">Tên đăng nhập</label>
            <input id="username" type="text" name="username" value="<?= admin_h($user['username']) ?>" required>
        </div>
        <div class="field">
            <label for="full_name">Họ tên</label>
            <input id="full_name" type="text" name="full_name" value="<?= admin_h($user['full_name']) ?>" required>
        </div>
        <div class="field">
            <label for="role">Vai trò</label>
            <select id="role" name="role">
                <option value="admin" <?= $user['role'] === ADMIN_ROLE ? 'selected' : '' ?>>Admin</option>
                <option value="staff" <?= $user['role'] === STAFF_ROLE ? 'selected' : '' ?>>Nhân viên</option>
            </select>
        </div>
        <div class="field">
            <label for="password">Mật khẩu <?= $isEditing ? '(để trống nếu không đổi)' : '' ?></label>
            <input id="password" type="password" name="password" <?= $isEditing ? '' : 'required' ?>>
        </div>
        <div class="field field--full">
            <button type="submit" class="button"><?= $isEditing ? 'Lưu thay đổi' : 'Tạo tài khoản' ?></button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>
