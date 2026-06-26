<?php
require_once __DIR__ . '/config.php';

requireAdmin();

if (isStaff()) {
    header('Location: pos.php');
    exit;
}

header('Location: dashboard.php');
exit;
