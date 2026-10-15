<?php
// Copy to config.local.php, enter your actual settings, then enable delivery.
return [
    'enabled' => false,
    'new_orders_enabled' => false, // Prefer enabling this on admin/mail_settings.php.
    'send_time' => '23:00',
    'recipients' => [
        'nguoinhan1@example.com',
        'nguoinhan2@example.com',
    ],
    'smtp' => [
        'host' => 'sotayaxiu.site', // Use the exact Outgoing Server in cPanel.
        'port' => 465,
        'encryption' => 'ssl',
        'username' => '_mainaccount@sotayaxiu.site',
        // With this account, use your cPanel password as provided by the host.
        'password' => getenv('AXIU_SMTP_PASSWORD') ?: '',
        'from_email' => '_mainaccount@sotayaxiu.site',
        'from_name' => 'Sổ Tay A Xíu',
    ],
];
