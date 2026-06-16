<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function money_vnd($amount) { return number_format((int) $amount, 0, ',', '.') . 'đ'; }
function today() { return date('Y-m-d'); }
function today_vi() { return date('d/m/Y'); }
function redirect($url) { header('Location: ' . $url); exit; }
function current_role() {
    $role = strtolower(trim((string) ($_SESSION['role'] ?? $_SESSION['admin_role'] ?? '')));
    if (in_array($role, ['owner', 'superadmin', 'admin'], true)) return 'admin';
    if (in_array($role, ['staff', 'employee', 'nhan_vien', 'nhanvien'], true)) return 'staff';
    return $role;
}
function is_staff_role() { return current_role() === 'staff'; }
function is_admin_role() { return current_role() === 'admin'; }
function require_non_staff($url = 'index.php') { if (is_staff_role()) redirect($url); }
function cart_count() { return array_sum(array_column($_SESSION['cart'] ?? [], 'quantity')); }
function cart_total() { $t = 0; foreach ($_SESSION['cart'] ?? [] as $i) { $t += $i['price'] * $i['quantity']; } return $t; }
function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function order_type_text($type) { return $type === 'takeaway' ? 'Mang đi' : 'Ăn tại quán'; }
