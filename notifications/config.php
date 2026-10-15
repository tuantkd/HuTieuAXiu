<?php
// Defaults < config.local.php < settings saved on admin/mail_settings.php.
return [
    'enabled' => false,
    'new_orders_enabled' => false,
    'new_orders_since' => null,
    'timezone' => 'Asia/Bangkok',
    'send_time' => '23:00', // HH:MM, local time (UTC+7).
    'shop_name' => 'Bì Cuốn A Xíu',
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
