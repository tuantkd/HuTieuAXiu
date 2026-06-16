<?php

function admin_get_setting_row()
{
    return admin_fetch_one('SELECT * FROM settings WHERE id = 1 LIMIT 1');
}

function admin_bind_and_execute($stmt, $types = '', array $params = [])
{
    if ($types !== '' && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        admin_abort('Lỗi truy vấn cơ sở dữ liệu: ' . $stmt->error, 500);
    }

    return $stmt;
}

function admin_fetch_all($sql, $types = '', array $params = [])
{
    $stmt = admin_prepare($sql);
    admin_bind_and_execute($stmt, $types, $params);
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $rows;
}

function admin_fetch_one($sql, $types = '', array $params = [])
{
    $rows = admin_fetch_all($sql, $types, $params);
    return $rows[0] ?? null;
}

function admin_execute($sql, $types = '', array $params = [])
{
    $stmt = admin_prepare($sql);
    admin_bind_and_execute($stmt, $types, $params);
    $payload = [
        'insert_id' => $stmt->insert_id,
        'affected_rows' => $stmt->affected_rows,
    ];
    $stmt->close();

    return $payload;
}

function admin_scalar($sql, $types = '', array $params = [], $default = 0)
{
    $row = admin_fetch_one($sql, $types, $params);
    if (!$row) {
        return $default;
    }

    $values = array_values($row);
    return $values[0] ?? $default;
}

function admin_money($amount)
{
    return number_format((float) $amount, 0, ',', '.') . 'đ';
}

function admin_datetime($datetime)
{
    if (!$datetime) {
        return '';
    }

    return date('d/m/Y H:i', strtotime($datetime));
}

function admin_order_type_label($type)
{
    return $type === 'takeaway' ? 'Mang đi' : 'Ăn tại quán';
}

function admin_role_label($role)
{
    return admin_normalize_role($role) === ADMIN_ROLE ? 'Admin' : 'Nhân viên';
}

function admin_today()
{
    return date('Y-m-d');
}

function admin_order_seller_label(array $order)
{
    $fullName = trim((string) ($order['full_name'] ?? ''));
    $username = trim((string) ($order['username'] ?? ''));

    if ($fullName !== '') {
        return $fullName;
    }

    if ($username !== '') {
        return $username;
    }

    return 'Chưa gán nhân viên';
}

function admin_generate_order_code()
{
    return 'AX' . date('YmdHis') . random_int(10, 99);
}

function admin_media_url($path)
{
    $path = trim((string) $path);
    if ($path === '') {
        return '../assets/img/hu-tieu.svg';
    }

    if (preg_match('/^(https?:)?\/\//i', $path)) {
        return $path;
    }

    if (strpos($path, '../') === 0) {
        return $path;
    }

    return '../' . ltrim($path, '/');
}
