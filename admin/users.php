<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

require_role(ADMIN_ROLE);

if (isset($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];
    if ($deleteId > 0 && $deleteId !== admin_user_id()) {
        admin_execute('DELETE FROM users WHERE id = ?', 'i', [$deleteId]);
        admin_flash_set('Đã xóa tài khoản người dùng.', 'success');
    } else {
        admin_flash_set('Không thể xóa chính tài khoản đang đăng nhập.', 'error');
    }
    header('Location: users.php');
    exit;
}

$page_title = 'Quản lý người dùng';
$users = admin_fetch_all('SELECT id, username, full_name, role, created_at FROM users ORDER BY id DESC');

define('ADMIN_APP', true);
include __DIR__ . '/layout/header.php';
?>
<div class="panel">
    <div class="panel-header">
        <div>
            <h3>Tài khoản hệ thống</h3>
            <div class="helper-text">Mật khẩu luôn dùng `password_hash` / `password_verify` và role DB chỉ gồm `admin` hoặc `staff`.</div>
        </div>
        <a class="button" href="user_form.php">Tạo tài khoản</a>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Tài khoản</th>
                    <th>Họ tên</th>
                    <th>Vai trò</th>
                    <th>Ngày tạo</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <strong><?= admin_h($user['username']) ?></strong>
                            <?php if ((int) $user['id'] === admin_user_id()): ?>
                                <div class="product-meta">Tài khoản đang đăng nhập</div>
                            <?php endif; ?>
                        </td>
                        <td><?= admin_h($user['full_name']) ?></td>
                        <td>
                            <span class="badge <?= admin_normalize_role($user['role']) === ADMIN_ROLE ? 'admin' : 'staff' ?>">
                                <?= admin_h(admin_role_label($user['role'])) ?>
                            </span>
                        </td>
                        <td><?= admin_h(admin_datetime($user['created_at'])) ?></td>
                        <td>
                            <div class="actions">
                                <a class="button light small" href="user_form.php?id=<?= (int) $user['id'] ?>">Sửa</a>
                                <?php if ((int) $user['id'] !== admin_user_id()): ?>
                                    <a class="button danger small" href="users.php?delete=<?= (int) $user['id'] ?>" data-confirm="Xóa tài khoản này khỏi hệ thống?">Xóa</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>
