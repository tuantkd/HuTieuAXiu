<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

requireRole(ADMIN_ROLE);

$page_title = 'Cài đặt';
$setting = admin_get_setting_row() ?: [
    'shop_name' => 'Quán Hủ Tiếu A Xíu',
    'phone' => '',
    'address' => '',
    'open_hours' => '',
    'logo_url' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $setting['shop_name'] = trim($_POST['shop_name'] ?? '');
    $setting['phone'] = trim($_POST['phone'] ?? '');
    $setting['address'] = trim($_POST['address'] ?? '');
    $setting['open_hours'] = trim($_POST['open_hours'] ?? '');
    $setting['logo_url'] = trim($_POST['logo_url'] ?? '');

    if (admin_get_setting_row()) {
        admin_execute(
            'UPDATE settings SET shop_name = ?, phone = ?, address = ?, open_hours = ?, logo_url = ? WHERE id = 1',
            'sssss',
            [$setting['shop_name'], $setting['phone'], $setting['address'], $setting['open_hours'], $setting['logo_url']]
        );
    } else {
        admin_execute(
            'INSERT INTO settings (id, shop_name, phone, address, open_hours, logo_url) VALUES (1, ?, ?, ?, ?, ?)',
            'sssss',
            [$setting['shop_name'], $setting['phone'], $setting['address'], $setting['open_hours'], $setting['logo_url']]
        );
    }

    adminFlashSet('Đã lưu cài đặt cửa hàng.', 'success');
    header('Location: settings.php');
    exit;
}

define('ADMIN_APP', true);
include __DIR__ . '/layout/header.php';
?>
<div class="panel">
    <div class="panel-header">
        <h3>Cài đặt cửa hàng</h3>
    </div>

    <form method="post" class="form-card">
        <div class="field">
            <label for="shop_name">Tên cửa hàng</label>
            <input id="shop_name" type="text" name="shop_name" value="<?= adminH($setting['shop_name']) ?>" required>
        </div>
        <div class="field">
            <label for="phone">Số điện thoại</label>
            <input id="phone" type="text" name="phone" value="<?= adminH($setting['phone']) ?>">
        </div>
        <div class="field field--full">
            <label for="address">Địa chỉ</label>
            <input id="address" type="text" name="address" value="<?= adminH($setting['address']) ?>">
        </div>
        <div class="field">
            <label for="open_hours">Giờ mở cửa</label>
            <input id="open_hours" type="text" name="open_hours" value="<?= adminH($setting['open_hours']) ?>" placeholder="06:00 - 21:30">
        </div>
        <div class="field">
            <label for="logo_url">Logo / ảnh đại diện</label>
            <input id="logo_url" type="text" name="logo_url" value="<?= adminH($setting['logo_url']) ?>" placeholder="assets/img/logo-placeholder.png">
        </div>
        <div class="field field--full">
            <button type="submit" class="button">Lưu cài đặt</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>
