<?php
require_once __DIR__ . '/config.php';

function check_admin_login()
{
    if (!is_logged_in()) {
        return;
    }

    if (is_staff()) {
        header('Location: pos.php');
        exit;
    }

    header('Location: dashboard.php');
    exit;
}

function login_admin($username, $password)
{
    $stmt = admin_prepare('SELECT id, username, password_hash, full_name, role FROM users WHERE username = ? LIMIT 1');
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

    $user['role'] = admin_normalize_role($user['role']);
    admin_set_auth_session($user);

    return true;
}

function logout_admin()
{
    admin_clear_auth_session();
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}
