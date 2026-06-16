<?php
require_once __DIR__ . '/config.php';

require_admin();

if (is_staff()) {
    header('Location: pos.php');
    exit;
}

header('Location: dashboard.php');
exit;
