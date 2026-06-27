<?php
$DB_HOST = 'localhost';
$DB_USER = 'vkgaiiwp_so_ban_hang_a_xiu';
$DB_PASS = 'LSqKdf&E9';
$DB_NAME = 'vkgaiiwp_so_ban_hang_a_xiu';

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    die('Không kết nối được database: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
