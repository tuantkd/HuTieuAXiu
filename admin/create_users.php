<?php
require_once __DIR__ . '/../config/db.php';

function upsert_user($username, $password, $fullName, $role)
{
    global $conn;

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare(
        'INSERT INTO users (username, password_hash, full_name, role, created_at)
         VALUES (?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), full_name = VALUES(full_name), role = VALUES(role)'
    );

    if (!$stmt) {
        echo 'Prepare failed: ' . $conn->error;
        return false;
    }

    $stmt->bind_param('ssss', $username, $passwordHash, $fullName, $role);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

header('Content-Type: text/plain; charset=utf-8');

$conn->query("UPDATE users SET role = 'staff' WHERE role = 'nhanvien'");
upsert_user('admin', '123456', 'Quản trị viên', 'admin');
upsert_user('nhanvien', '123456', 'Nhân viên bán hàng', 'staff');

echo "Đã tạo hoặc cập nhật 2 tài khoản mẫu:\n";
echo "- admin / 123456\n";
echo "- nhanvien / 123456\n";
