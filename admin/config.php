<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const ADMIN_ROLE = 'admin';
const STAFF_ROLE = 'staff';

function admin_normalize_role($role)
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

function admin_set_auth_session(array $user)
{
    $role = admin_normalize_role($user['role'] ?? STAFF_ROLE);

    $_SESSION['user_id'] = (int) ($user['id'] ?? 0);
    $_SESSION['username'] = $user['username'] ?? '';
    $_SESSION['full_name'] = $user['full_name'] ?? '';
    $_SESSION['role'] = $role;

    $_SESSION['admin_id'] = $_SESSION['user_id'];
    $_SESSION['admin_username'] = $_SESSION['username'];
    $_SESSION['admin_full_name'] = $_SESSION['full_name'];
    $_SESSION['admin_role'] = $role;
}

function admin_clear_auth_session()
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

function admin_user_id()
{
    return (int) ($_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 0);
}

function admin_username()
{
    return (string) ($_SESSION['username'] ?? $_SESSION['admin_username'] ?? '');
}

function admin_full_name()
{
    return (string) ($_SESSION['full_name'] ?? $_SESSION['admin_full_name'] ?? '');
}

function admin_role()
{
    return admin_normalize_role($_SESSION['role'] ?? $_SESSION['admin_role'] ?? '');
}

function is_logged_in()
{
    return admin_user_id() > 0;
}

function is_admin()
{
    return admin_role() === ADMIN_ROLE;
}

function is_staff()
{
    return admin_role() === STAFF_ROLE;
}

function require_admin()
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function admin_abort($message = 'Bạn không có quyền truy cập.', $statusCode = 403)
{
    http_response_code($statusCode);
    echo '<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Không có quyền truy cập</title><style>body{font-family:Segoe UI,Tahoma,sans-serif;background:#fff7f2;color:#4a2b1a;display:grid;place-items:center;min-height:100vh;margin:0;padding:24px}.box{max-width:420px;background:#fff;border:1px solid #ffd5c2;border-radius:24px;padding:28px;box-shadow:0 20px 50px rgba(236,94,42,.15)}a{display:inline-block;margin-top:16px;padding:12px 18px;border-radius:999px;background:#ef5b2a;color:#fff;text-decoration:none}</style></head><body><div class="box"><h2>Không có quyền truy cập</h2><p>' . admin_h($message) . '</p><a href="pos.php">Về màn hình POS</a></div></body></html>';
    exit;
}

function require_role($roles)
{
    require_admin();

    $roles = (array) $roles;
    $allowedRoles = array_map('admin_normalize_role', $roles);

    if (in_array(admin_role(), $allowedRoles, true)) {
        return;
    }

    if (is_staff()) {
        admin_flash_set('Bạn không có quyền truy cập trang này.', 'error');
        header('Location: pos.php');
        exit;
    }

    admin_abort();
}

function admin_flash_set($message, $type = 'success')
{
    $_SESSION['admin_flash'] = [
        'message' => (string) $message,
        'type' => in_array($type, ['success', 'error'], true) ? $type : 'success',
    ];
}

function admin_flash_get()
{
    $flash = $_SESSION['admin_flash'] ?? null;
    unset($_SESSION['admin_flash']);

    return $flash;
}

function admin_h($value)
{
    if (function_exists('h')) {
        return h($value);
    }

    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function admin_prepare($sql)
{
    global $conn;

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        admin_abort('Lỗi truy vấn cơ sở dữ liệu: ' . $conn->error, 500);
    }

    return $stmt;
}

function admin_current_page()
{
    return basename($_SERVER['PHP_SELF'] ?? '');
}
