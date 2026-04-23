<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * メール送信。RESEND_API_KEY が設定されていればそれを使い、
 * そうでなければ PHP の mb_send_mail() を使う（Xserver の SMTP 経由）。
 *
 * @param string|array $to 受信者
 * @param string       $subject 件名
 * @param string       $text 本文（必須）
 * @param string|null  $html 本文 HTML（任意）
 */
function wp_send_email($to, string $subject, string $text, ?string $html = null): bool
{
    $apiKey = (string)wp_cfg('resend_api_key', '');
    if ($apiKey !== '') return wp_send_email_resend($apiKey, $to, $subject, $text, $html);
    return wp_send_email_native($to, $subject, $text, $html);
}

function wp_send_email_resend(string $apiKey, $to, string $subject, string $text, ?string $html): bool
{
    $from = trim((string)wp_cfg('mail_from', 'onboarding@resend.dev'));
    $body = [
        'from'    => $from,
        'to'      => is_array($to) ? $to : [$to],
        'subject' => $subject,
        'text'    => $text,
    ];
    if ($html !== null) $body['html'] = $html;

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($code < 200 || $code >= 300) {
        error_log('[resend] failed: ' . $code . ' ' . substr((string)$res, 0, 500));
        return false;
    }
    return true;
}

function wp_send_email_native($to, string $subject, string $text, ?string $html): bool
{
    mb_language('Japanese');
    mb_internal_encoding('UTF-8');

    $fromAddr = (string)wp_cfg('mail_from', 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $fromName = (string)wp_cfg('mail_from_name', 'WordPics');
    $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $fromHeader = "{$encodedFromName} <{$fromAddr}>";

    $toList = is_array($to) ? implode(', ', $to) : $to;

    if ($html !== null) {
        $boundary = '=_' . bin2hex(random_bytes(12));
        $headers = [
            "From: {$fromHeader}",
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
        ];
        $body = "--{$boundary}\r\n"
              . "Content-Type: text/plain; charset=UTF-8\r\n"
              . "Content-Transfer-Encoding: base64\r\n\r\n"
              . chunk_split(base64_encode($text)) . "\r\n"
              . "--{$boundary}\r\n"
              . "Content-Type: text/html; charset=UTF-8\r\n"
              . "Content-Transfer-Encoding: base64\r\n\r\n"
              . chunk_split(base64_encode($html)) . "\r\n"
              . "--{$boundary}--\r\n";
        $ok = @mail($toList, mb_encode_mimeheader($subject, 'UTF-8'), $body, implode("\r\n", $headers));
    } else {
        $headers = [
            "From: {$fromHeader}",
            "Content-Type: text/plain; charset=UTF-8",
        ];
        $ok = @mb_send_mail($toList, $subject, $text, implode("\r\n", $headers));
    }

    if (!$ok) error_log('[mail] send failed to ' . $toList);
    return (bool)$ok;
}
