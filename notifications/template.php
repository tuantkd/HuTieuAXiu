<?php
// Variables come from renderRevenueEmail(); all dynamic HTML is escaped below.
$escape = static function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$money = static function ($amount) {
    return number_format((int) $amount, 0, ',', '.') . ' đ';
};
$reportDate = DateTimeImmutable::createFromFormat('!Y-m-d', $report['date'])->format('d/m/Y');
$snapshot = new DateTimeImmutable($report['generated_at']);
$netColor = $report['net_revenue'] < 0 ? '#b42318' : '#12664f';
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Báo cáo doanh thu ngày <?= $escape($reportDate) ?></title>
</head>
<body style="margin:0;padding:0;background-color:#f2f4f7;font-family:Arial,Helvetica,sans-serif;color:#243447;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;mso-hide:all;">
        Doanh thu hôm nay <?= $escape($reportDate) ?>: <?= $escape($money($report['net_revenue'])) ?> sau khi trừ chi phí.
    </div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f2f4f7;">
        <tr><td align="center" style="padding:32px 12px;">
            <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;background-color:#ffffff;border:1px solid #e4e7ec;border-radius:16px;overflow:hidden;">
                <tr><td style="padding:30px 28px;background-color:#173e35;">
                    <p style="margin:0 0 10px;font-size:12px;letter-spacing:2px;font-weight:bold;color:#b9d8ce;">BÁO CÁO KINH DOANH</p>
                    <h1 style="margin:0;font-size:25px;line-height:1.4;font-weight:bold;color:#ffffff;"><?= $escape($shopName) ?></h1>
                    <p style="margin:8px 0 0;font-size:14px;color:#d5e7e1;">Tổng kết doanh thu &middot; <?= $escape($reportDate) ?></p>
                </td></tr>
                <tr><td style="padding:28px;">
                    <p style="margin:0 0 8px;font-size:16px;line-height:1.6;">Xin chào,</p>
                    <p style="margin:0 0 24px;font-size:15px;line-height:1.7;color:#596779;">Dưới đây là doanh thu hôm nay của <strong><?= $escape($shopName) ?></strong>, cập nhật đến <?= $escape($snapshot->format('H:i')) ?> ngày <?= $escape($reportDate) ?> (UTC<?= $escape($snapshot->format('P')) ?>).</p>
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#eef7f2;border:1px solid #d5e9dd;border-radius:12px;">
                        <tr><td align="center" style="padding:25px 12px;">
                            <p style="margin:0 0 10px;font-size:13px;font-weight:bold;letter-spacing:1px;color:#496657;">DOANH THU SAU CHI PHÍ</p>
                            <p style="margin:0;font-size:36px;line-height:1.3;font-weight:bold;color:<?= $netColor ?>;"><?= $escape($money($report['net_revenue'])) ?></p>
                            <p style="margin:10px 0 0;font-size:13px;color:#62746b;">Tổng tiền bán hàng &minus; Tổng khoản chi</p>
                        </td></tr>
                    </table>
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top:20px;font-size:15px;">
                        <tr>
                            <td style="padding:15px 0;border-bottom:1px solid #edf0f3;color:#596779;">Tổng tiền bán hàng</td>
                            <td align="right" style="padding:15px 0;border-bottom:1px solid #edf0f3;font-weight:bold;"><?= $escape($money($report['revenue_total'])) ?></td>
                        </tr>
                        <tr>
                            <td style="padding:15px 0;border-bottom:1px solid #edf0f3;color:#596779;">Tổng khoản chi</td>
                            <td align="right" style="padding:15px 0;border-bottom:1px solid #edf0f3;font-weight:bold;"><?= $escape($money($report['expense_total'])) ?></td>
                        </tr>
                        <tr>
                            <td style="padding:15px 0;color:#596779;">Số đơn hàng</td>
                            <td align="right" style="padding:15px 0;font-weight:bold;"><?= $escape(number_format($report['order_count'], 0, ',', '.')) ?> đơn</td>
                        </tr>
                    </table>
                    <p style="margin:22px 0 0;padding:14px 16px;background-color:#f8fafc;border-left:3px solid #cbd5e1;font-size:13px;line-height:1.7;color:#667085;">Số liệu được tính theo cùng công thức trên trang Tổng kết. Các giao dịch phát sinh hoặc chỉnh sửa sau thời điểm cập nhật chưa được tính trong email này.</p>
                </td></tr>
                <tr><td style="padding:20px 28px;background-color:#f8fafc;border-top:1px solid #edf0f3;">
                    <p style="margin:0;font-size:13px;line-height:1.7;color:#667085;"><strong>Sổ Tay A Xíu</strong> &middot; Thông báo doanh thu tự động</p>
                    <p style="margin:5px 0 0;font-size:12px;line-height:1.6;color:#98a2b3;">Email được gửi hằng ngày đến danh sách nhận báo cáo đã cấu hình.</p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
