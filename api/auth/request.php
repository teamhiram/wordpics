<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/bootstrap.php';

wp_require_method('POST');

$in = wp_read_json();
$email = wp_normalize_email($in['email'] ?? '');
$redirect = wp_safe_redirect($in['redirect'] ?? '/submit.html');

if (!wp_is_valid_email($email)) wp_bad('メールアドレスが無効です');

if (wp_rate_recent_tokens_for_email($email) >= 3) {
    wp_bad('短時間に複数回送信されました。数分待ってからお試しください。', 429);
}

$token = wp_random_token(32);
$expires = gmdate('Y-m-d H:i:s', time() + 15 * 60);

$stmt = wp_db()->prepare(
    'INSERT INTO magic_tokens (token, email, expires_at) VALUES (?, ?, ?)'
);
$stmt->execute([$token, $email, $expires]);

$origin = wp_site_origin();
$link   = $origin . '/api/auth/verify.php?token=' . rawurlencode($token)
        . '&redirect=' . rawurlencode($redirect);

$text = <<<TXT
WordPics にログインするには、以下のリンクを 15 分以内にクリックしてください。

{$link}

このメールに心当たりがない場合は無視してください。
TXT;

$linkHtml = wp_esc($link);
$html = <<<HTML
<div style="font-family:-apple-system,Segoe UI,sans-serif;line-height:1.7;color:#0f172a;max-width:560px">
  <h2 style="color:#0f172a;margin:0 0 12px">WordPics にログイン</h2>
  <p>下のボタンをクリックするとログインできます（有効期限 15 分）。</p>
  <p style="margin:24px 0">
    <a href="{$linkHtml}"
       style="display:inline-block;padding:12px 22px;background:#3b5bdb;color:#fff;border-radius:999px;text-decoration:none;font-weight:600">
      ログインする
    </a>
  </p>
  <p style="color:#5b6472;font-size:13px">
    ボタンが開けない場合はこちらの URL をブラウザに貼り付けてください:<br />
    <span style="word-break:break-all">{$linkHtml}</span>
  </p>
  <hr style="border:none;border-top:1px solid #e5e7ef;margin:24px 0" />
  <p style="color:#828aa0;font-size:12px">
    このメールに心当たりがない場合は無視してください。リンクをクリックしない限りログインは行われません。
  </p>
</div>
HTML;

wp_send_email($email, 'WordPics — ログインリンク', $text, $html);

wp_json(['ok' => true]);
