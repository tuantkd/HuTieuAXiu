<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const ADMIN_ROLE = 'admin';
const STAFF_ROLE = 'staff';

function adminNormalizeRole($role)
{
    $role = strtolower(trim((string) $role));

    if (in_array($role, ['owner', 'superadmin'], true)) {
        return ADMIN_ROLE;
    }

    if (in_array($role, ['staff', 'employee', 'nhan_vien', 'nhanvien'], true)) {
        return STAFF_ROLE;
    }

    return $role === ADMIN_ROLE ? ADMIN_ROLE : STAFF_ROLE;
}

function adminSetAuthSession(array $user)
{
    $role = adminNormalizeRole($user['role'] ?? STAFF_ROLE);

    $_SESSION['user_id'] = (int) ($user['id'] ?? 0);
    $_SESSION['username'] = $user['username'] ?? '';
    $_SESSION['full_name'] = $user['full_name'] ?? '';
    $_SESSION['role'] = $role;

    $_SESSION['admin_id'] = $_SESSION['user_id'];
    $_SESSION['admin_username'] = $_SESSION['username'];
    $_SESSION['admin_full_name'] = $_SESSION['full_name'];
    $_SESSION['admin_role'] = $role;
}

function adminClearAuthSession()
{
    unset(
        $_SESSION['user_id'],
        $_SESSION['username'],
        $_SESSION['full_name'],
        $_SESSION['role'],
        $_SESSION['admin_id'],
        $_SESSION['admin_username'],
        $_SESSION['admin_full_name'],
        $_SESSION['admin_role']
    );
}

function adminUserId()
{
    return (int) ($_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 0);
}

function adminUserName()
{
    return (string) ($_SESSION['username'] ?? $_SESSION['admin_username'] ?? '');
}

function adminFullName()
{
    return (string) ($_SESSION['full_name'] ?? $_SESSION['admin_full_name'] ?? '');
}

function adminRole()
{
    return adminNormalizeRole($_SESSION['role'] ?? $_SESSION['admin_role'] ?? '');
}

if (!function_exists('isLoggedIn')) {
    function isLoggedIn()
    {
        return adminUserId() > 0;
    }
}

function isAdmin()
{
    return adminRole() === ADMIN_ROLE;
}

function isStaff()
{
    return adminRole() === STAFF_ROLE;
}

function requireAdmin()
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function adminAbort($message = 'Bạn không có quyền truy cập.', $statusCode = 403)
{
    http_response_code($statusCode);
    echo '<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Không có quyền truy cập</title><style>body{font-family:Segoe UI,Tahoma,sans-serif;background:#fff7f2;color:#4a2b1a;display:grid;place-items:center;min-height:100vh;margin:0;padding:24px}.box{max-width:420px;background:#fff;border:1px solid #ffd5c2;border-radius:24px;padding:28px;box-shadow:0 20px 50px rgba(236,94,42,.15)}a{display:inline-block;margin-top:16px;padding:12px 18px;border-radius:999px;background:#ef5b2a;color:#fff;text-decoration:none}</style></head><body><div class="box"><h2>Không có quyền truy cập</h2><p>' . adminH($message) . '</p><a href="pos.php">Về màn hình POS</a></div></body></html>';
    exit;
}

function requireRole($roles)
{
    requireAdmin();

    $roles = (array) $roles;
    $allowedRoles = array_map('adminNormalizeRole', $roles);

    if (in_array(adminRole(), $allowedRoles, true)) {
        return;
    }

    if (isStaff()) {
        adminFlashSet('Bạn không có quyền truy cập trang này.', 'error');
        header('Location: pos.php');
        exit;
    }

    adminAbort();
}

function adminFlashSet($message, $type = 'success')
{
    $_SESSION['admin_flash'] = [
        'message' => (string) $message,
        'type' => in_array($type, ['success', 'error'], true) ? $type : 'success',
    ];
}

function adminFlashGet()
{
    $flash = $_SESSION['admin_flash'] ?? null;
    unset($_SESSION['admin_flash']);

    return $flash;
}

function adminH($value)
{
    if (function_exists('h')) {
        return h($value);
    }

    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function adminPrepare($sql)
{
    global $conn;

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        adminAbort('Lỗi truy vấn cơ sở dữ liệu: ' . $conn->error, 500);
    }

    return $stmt;
}

function adminCurrentPage()
{
    return basename($_SERVER['PHP_SELF'] ?? '');
}
