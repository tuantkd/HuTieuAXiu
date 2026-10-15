<?php
namespace AXiu\Notifications;

use PHPMailer\PHPMailer\PHPMailer;

final class NotificationMailer extends PHPMailer
{
    public function encodeHeader($str, $position = 'text')
    {
        $encoded = parent::encodeHeader($str, $position);
        preg_match_all('/=\?[^?\s]+\?[BQ]\?[^?\r\n]*\?=/i', $encoded, $words);
        $oversized = false;
        foreach ($words[0] as $word) {
            if (strlen($word) > 75) {
                $oversized = true;
                break;
            }
        }
        if (!$oversized || strcasecmp($this->CharSet, self::CHARSET_UTF8) !== 0) {
            return $encoded;
        }

        // PHPMailer's SMTP Q encoder can use the SMTP line limit (998) for
        // encoded words. RFC 2047 limits each encoded word to 75 characters.
        // Split by UTF-8 characters, without requiring the mbstring extension.
        $value = str_replace(["\r", "\n"], '', $str);
        if (!preg_match_all('/./us', $value, $characters)) {
            return $encoded;
        }
        $chunks = [];
        $chunk = '';
        foreach ($characters[0] as $character) {
            // 39 bytes -> 52 base64 characters + 12 delimiter/charset characters.
            // Leave room for the Subject/From label on the first header line.
            if (strlen($chunk) + strlen($character) > 39) {
                $chunks[] = '=?UTF-8?B?' . base64_encode($chunk) . '?=';
                $chunk = '';
            }
            $chunk .= $character;
        }
        if ($chunk !== '') {
            $chunks[] = '=?UTF-8?B?' . base64_encode($chunk) . '?=';
        }
        return implode(static::$LE . ' ', $chunks);
    }
}
