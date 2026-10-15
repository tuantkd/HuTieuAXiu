<?php
// Override these defaults in config.local.php; keep SMTP passwords out of Git.
return [
    'enabled' => false,
    'timezone' => 'Asia/Bangkok',
    'send_time' => '23:00', // HH:MM, local time (UTC+7).
    'shop_name' => 'Hủ Tiếu A Xíu',
    'recipients' => [],
    'smtp' => [
        'host' => '',
        'port' => 465,
        'encryption' => 'ssl', // cPanel: SSL/TLS (465); STARTTLS (587) if supported.
        'username' => '',
        'password' => getenv('AXIU_SMTP_PASSWORD') ?: '',
        'from_email' => '',
        'from_name' => 'Sổ Tay A Xíu',
    ],
];
