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
        adminAbort('Lỗi truy vấn cơ sở dữ liệu: ' . $stmt->error, 500);
    }

    return $stmt;
}

function admin_fetch_all($sql, $types = '', array $params = [])
{
    $stmt = adminPrepare($sql);
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
    $stmt = adminPrepare($sql);
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

function admin_slugify($text)
{
    $text = trim((string) $text);
    if ($text === '') {
        return '';
    }

    $utf8Text = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
    if ($utf8Text !== false && $utf8Text !== '') {
        $text = $utf8Text;
    }

    $text = preg_replace('~[^\\pL\\d]+~u', '-', $text);
    $asciiText = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    if ($asciiText !== false && $asciiText !== '') {
        $text = $asciiText;
    }

    $text = preg_replace('~[^-\\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);

    if ($text === '') {
        return 'category-' . time();
    }

    return $text;
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
    return $type === 'bank_transfer' ? 'Mang đi' : 'Ăn tại quán';
}

function admin_role_label($role)
{
    return adminNormalizeRole($role) === ADMIN_ROLE ? 'Admin' : 'Nhân viên';
}

function admin_today()
{
    return date('Y-m-d');
}

function admin_is_valid_date($date)
{
    return is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1;
}

function admin_max_iso_date(...$dates)
{
    $validDates = array_values(array_filter($dates, 'admin_is_valid_date'));
    if (empty($validDates)) {
        return null;
    }

    sort($validDates);
    return $validDates[count($validDates) - 1];
}

function admin_string_contains_keyword($value, array $keywords)
{
    foreach ($keywords as $keyword) {
        if ($keyword !== '' && strpos($value, $keyword) !== false) {
            return true;
        }
    }

    return false;
}

function admin_transaction_keyword_source(array $transaction)
{
    return admin_slugify(
        trim((string) ($transaction['category'] ?? '')) . ' ' . trim((string) ($transaction['note'] ?? ''))
    );
}

function admin_is_ingredient_expense(array $transaction)
{
    if (($transaction['type'] ?? '') !== 'expense') {
        return false;
    }

    $source = admin_transaction_keyword_source($transaction);
    if ($source === '') {
        return false;
    }

    $nonIngredientKeywords = [
        'dien',
        'nuoc',
        'gas',
        'internet',
        'luong',
        'thue',
        'van-phong',
        'van-chuyen',
        'ship',
        'giao-hang',
        'sua-chua',
        'bao-tri',
        'khuyen-mai',
        'quang-cao',
        'marketing',
        'thiet-bi',
        'ban-ghe',
        'may-moc',
        'bao-hiem',
    ];

    if (admin_string_contains_keyword($source, $nonIngredientKeywords)) {
        return false;
    }

    $ingredientKeywords = [
        'nguyen-lieu',
        'rau',
        'cu',
        'xa-lach',
        'thit',
        'tom',
        'ca',
        'muc',
        'ga',
        'heo',
        'bo',
        'xuong',
        'gio',
        'cha',
        'trung',
        'bun',
        'mi',
        'gao',
        'pho',
        'hu-tieu',
        'hanh',
        'toi',
        'ot',
        'gia-vi',
        'nuoc-mam',
        'duong',
        'muoi',
        'dau-an',
        'rau-thom',
    ];

    return strpos($source, 'mua-') === 0 || admin_string_contains_keyword($source, $ingredientKeywords);
}

function admin_is_sales_income(array $transaction)
{
    if (($transaction['type'] ?? '') !== 'income') {
        return false;
    }

    $source = admin_transaction_keyword_source($transaction);
    if ($source === '') {
        return false;
    }

    return admin_string_contains_keyword($source, [
        'ban-hang',
        'doanh-thu',
        'don-hang',
        'thu-tien-ban',
        'ban-truc-tiep',
    ]);
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
