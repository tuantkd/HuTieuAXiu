<?php
namespace AXiu\Notifications;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

function createSmtpTestMailer(array $config, $recipient)
{
    $now = new DateTimeImmutable('now', new DateTimeZone($config['timezone']));
    // Reuse the existing SMTP settings without changing the daily mailer.
    // Replace the report content completely; no database or daily state is used.
    $mail = createMailer($config, $recipient, [
        'date' => $now->format('Y-m-d'),
        'generated_at' => $now->format(DateTimeImmutable::ATOM),
        'revenue_total' => 0,
        'expense_total' => 0,
        'net_revenue' => 0,
        'order_count' => 0,
    ]);
    $escape = static function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };
    $sentAt = $now->format('H:i:s d/m/Y') . ' (UTC' . $now->format('P') . ')';
    $shopName = $escape($config['shop_name']);
    $time = $escape($sentAt);
    $mail->Subject = '[KIỂM TRA SMTP] ' . $config['shop_name'];
    $mail->Body = '<!doctype html><html lang="vi"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Kiểm tra gửi email</title></head>'
        . '<body style="margin:0;padding:24px 12px;background:#f2f4f7;font-family:Arial,Helvetica,sans-serif;color:#243447;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center">'
        . '<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="width:100%;max-width:600px;background:#fff;border:1px solid #e4e7ec;border-radius:12px;">'
        . '<tr><td style="padding:26px;background:#173e35;color:#fff;">'
        . '<p style="margin:0 0 8px;font-size:12px;letter-spacing:1px;">KIỂM TRA HỆ THỐNG EMAIL</p>'
        . '<h1 style="margin:0;font-size:24px;">' . $shopName . '</h1></td></tr>'
        . '<tr><td style="padding:26px;line-height:1.7;">'
        . '<h2 style="margin:0 0 16px;font-size:21px;color:#12664f;">Bạn đã nhận được email thử</h2>'
        . '<p style="margin:0 0 16px;">Email này được gửi để kiểm tra cấu hình SMTP của Sổ Tay A Xíu.</p>'
        . '<p style="margin:0 0 16px;"><strong>Thời điểm gửi:</strong> ' . $time . '</p>'
        . '<p style="margin:0;color:#667085;">Đây là email kiểm tra, không chứa số liệu doanh thu. '
        . 'Lịch gửi báo cáo và trạng thái gửi hằng ngày được giữ nguyên.</p></td></tr>'
        . '<tr><td style="padding:18px 26px;background:#f8fafc;color:#667085;font-size:12px;">'
        . 'Sổ Tay A Xíu &middot; Kiểm tra kết nối SMTP</td></tr></table></td></tr></table></body></html>';
    $mail->AltBody = $config['shop_name'] . "\nKIỂM TRA HỆ THỐNG EMAIL\n\n"
        . "Bạn đã nhận được email thử từ Sổ Tay A Xíu.\nThời điểm gửi: " . $sentAt
        . "\n\nĐây là email kiểm tra, không chứa số liệu doanh thu."
        . "\nLịch gửi báo cáo và trạng thái gửi hằng ngày được giữ nguyên.";
    $domain = substr(strrchr($config['smtp']['from_email'], '@'), 1);
    // Every manual test is a separate message, independent of daily Message-IDs.
    $mail->MessageID = '<axiu-smtp-test-' . bin2hex(random_bytes(16)) . '@' . $domain . '>';
    return $mail;
}

function sendSmtpTest($mail)
{
    if (!$mail->send()) {
        throw new RuntimeException('SMTP chưa xác nhận nhận email thử.');
    }
    return $mail->getLastMessageID();
}
