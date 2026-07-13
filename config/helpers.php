<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Bangkok');

function moneyVND($amount)
{
    return number_format((int) $amount, 0, ',', '.') . 'đ';
}
function today()
{
    return date('Y-m-d');
}
function todayVi()
{
    return date('d/m/Y');
}
function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

function toast($type, $message, $flash = false)
{
    $allowedTypes = ['success', 'warning', 'error'];
    $normalizedType = in_array($type, $allowedTypes, true) ? $type : 'success';
    $normalizedMessage = trim((string) $message);

    if ($normalizedMessage === '') {
        return;
    }

    $payload = [
        'type' => $normalizedType,
        'message' => $normalizedMessage,
    ];

    if ($flash) {
        if (!isset($_SESSION['_app_toasts']) || !is_array($_SESSION['_app_toasts'])) {
            $_SESSION['_app_toasts'] = [];
        }
        $_SESSION['_app_toasts'][] = $payload;
        return;
    }

    if (!isset($GLOBALS['_app_toasts']) || !is_array($GLOBALS['_app_toasts'])) {
        $GLOBALS['_app_toasts'] = [];
    }
    $GLOBALS['_app_toasts'][] = $payload;
}

function app_toasts()
{
    static $resolved = null;

    if ($resolved !== null) {
        return $resolved;
    }

    $sessionToasts = $_SESSION['_app_toasts'] ?? [];
    unset($_SESSION['_app_toasts']);

    $inlineToasts = $GLOBALS['_app_toasts'] ?? [];
    $resolved = array_values(array_filter(array_merge($sessionToasts, $inlineToasts), static function ($toast) {
        return is_array($toast)
            && !empty($toast['message'])
            && in_array(($toast['type'] ?? ''), ['success', 'warning', 'error'], true);
    }));

    return $resolved;
}
function isLoggedIn()
{
    return (int) ($_SESSION['user_id'] ?? 0) > 0;
}

function currentUserName()
{
    return trim((string) ($_SESSION['full_name'] ?? $_SESSION['username'] ?? ''));
}

function login($username, $password)
{
    global $conn;

    $username = trim((string) $username);
    $password = trim((string) $password);

    if ($username === '' || $password === '') {
        return false;
    }

    $stmt = $conn->prepare('SELECT id, username, full_name, password_hash, role FROM users WHERE username = ? LIMIT 1');
    if ($stmt === false) {
        return false;
    }

    $stmt->bind_param('s', $username);
    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }

    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = strtolower(trim((string) $user['role']));

    return true;
}

function logout()
{
    unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['full_name'], $_SESSION['role']);
    session_regenerate_id(true);
    redirect('login.php');
}

function requireLogin()
{
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

function currentRole()
{
    $role = strtolower(trim((string) ($_SESSION['role'] ?? $_SESSION['admin_role'] ?? '')));
    if (in_array($role, ['owner', 'superadmin', 'admin'], true))
        return 'admin';
    if (in_array($role, ['staff', 'employee', 'nhan_vien', 'nhanvien'], true))
        return 'staff';
    return $role;
}
function is_staff_role()
{
    return currentRole() === 'staff';
}
function is_admin_role()
{
    return currentRole() === 'admin';
}
function require_non_staff($url = 'index.php')
{
    if (is_staff_role())
        redirect($url);
}
function cart_count()
{
    return array_sum(array_column($_SESSION['cart'] ?? [], 'quantity'));
}
function cart_total()
{
    $t = 0;
    foreach ($_SESSION['cart'] ?? [] as $i) {
        $t += $i['price'] * $i['quantity'];
    }
    return $t;
}
function h($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function render_toasts()
{
    $toasts = app_toasts();
    if (empty($toasts)) {
        return;
    }

    echo '<div class="toast-stack" aria-live="polite" aria-atomic="true">';
    foreach ($toasts as $toast) {
        $type = h($toast['type']);
        $message = h($toast['message']);
        echo '<div class="toast toast--' . $type . '" data-toast>';
        echo '<div class="toast__icon" aria-hidden="true">';
        if ($type === 'success') {
            echo '<i class="fa fa-check-circle"></i>';
        } elseif ($type === 'warning') {
            echo '<i class="fa fa-exclamation-triangle"></i>';
        } else {
            echo '<i class="fa fa-times-circle"></i>';
        }
        echo '</div>';
        echo '<div class="toast__body">' . $message . '</div>';
        echo '<button class="toast__close" type="button" data-toast-close aria-label="Đóng thông báo">';
        echo '<i class="fa fa-times"></i>';
        echo '</button>';
        echo '</div>';
    }
    echo '</div>';
}

function category_icon($slug)
{
    $icons = [
        'mon-an' => '🍜',
        'nuoc-uong' => '🥤',
        'bi-cuon' => '🌯',
    ];

    return $icons[$slug] ?? '🚚';
}

function order_type_meta($type)
{
    static $types = [
    'bank_transfer' => ['icon' => '🏦', 'label' => 'Chuyển khoản'],
    'cash' => ['icon' => '💵', 'label' => 'Tiền mặt'],
    'takeaway' => ['icon' => '🥡', 'label' => 'Mang đi'],
    'dine_in' => ['icon' => '🍽️', 'label' => 'Ăn tại quán'],
    ];

    return $types[$type] ?? ['icon' => '❓', 'label' => 'Không xác định'];
}

function order_type_icon($type)
{
    return order_type_meta($type)['icon'];
}

function order_type_label($type)
{
    return order_type_meta($type)['label'];
}

function order_type_text($type)
{
    return order_type_label($type);
}
