<?php
header('Content-Type: text/html; charset=utf-8');
echo '<h2>Kiểm tra tài khoản đăng nhập</h2>';

require_once __DIR__ . '/../config/db.php';

if (!$conn) {
    echo "<div style='color:red'>Không kết nối được database.</div>";
    exit;
}

$expected = [
    ['username' => 'admin', 'password' => '123456'],
    ['username' => 'nhanvien', 'password' => '123456'],
];

$tests = [];
foreach ($expected as $item) {
    $stmt = $conn->prepare('SELECT username, password_hash, role FROM users WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $item['username']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    $tests[] = [
        'username' => $item['username'],
        'role' => $row['role'] ?? '',
        'ok' => $row ? password_verify($item['password'], $row['password_hash']) : false,
    ];
}
?>
<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;">
    <tr>
        <th>Tài khoản</th>
        <th>Role</th>
        <th>Kết quả</th>
    </tr>
    <?php foreach ($tests as $test): ?>
        <tr>
            <td><?= htmlspecialchars($test['username']) ?></td>
            <td><?= htmlspecialchars($test['role']) ?></td>
            <td style="color:<?= $test['ok'] ? 'green' : 'red' ?>;font-weight:bold;"><?= $test['ok'] ? 'PASS' : 'FAIL' ?></td>
        </tr>
    <?php endforeach; ?>
</table>
