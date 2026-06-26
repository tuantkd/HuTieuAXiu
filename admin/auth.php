<?php
require_once __DIR__ . '/config.php';

function check_admin_login()
{
    if (!isLoggedIn()) {
        return;
    }

    if (isStaff()) {
        header('Location: pos.php');
        exit;
    }

    header('Location: dashboard.php');
    exit;
}

function login_admin($username, $password)
{
    $stmt = adminPrepare('SELECT id, username, password_hash, full_name, role FROM users WHERE username = ? LIMIT 1');
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

    $user['role'] = adminNormalizeRole($user['role']);
    adminSetAuthSession($user);

    return true;
}

function logout_admin()
{
    adminClearAuthSession();
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}
