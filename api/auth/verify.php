<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/bootstrap.php';

$token = (string)($_GET['token'] ?? '');
$redirect = wp_safe_redirect($_GET['redirect'] ?? '/submit.html');

if ($token === '') {
    wp_verify_error('ログインリンクが不正です。');
}

$pdo = wp_db();
$stmt = $pdo->prepare(
    'SELECT token, email FROM magic_tokens
     WHERE token = ? AND consumed_at IS NULL AND expires_at > UTC_TIMESTAMP() LIMIT 1'
);
$stmt->execute([$token]);
$row = $stmt->fetch();

if (!$row) {
    wp_verify_error('ログインリンクが期限切れか、すでに使用済みです。もう一度メールを送信してください。');
}

$email = (string)$row['email'];

$pdo->prepare('UPDATE magic_tokens SET consumed_at = UTC_TIMESTAMP() WHERE token = ?')
    ->execute([$token]);

$stmt = $pdo->prepare('SELECT id, email, is_admin FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

$isAdmin = wp_is_admin_email($email) ? 1 : 0;

if (!$user) {
    $uid = wp_uuid();
    $pdo->prepare('INSERT INTO users (id, email, is_admin) VALUES (?, ?, ?)')
        ->execute([$uid, $email, $isAdmin]);
    $user = ['id' => $uid, 'email' => $email, 'is_admin' => $isAdmin];
} elseif ($isAdmin && empty($user['is_admin'])) {
    $pdo->prepare('UPDATE users SET is_admin = 1 WHERE id = ?')->execute([$user['id']]);
}

wp_login((string)$user['id']);

header('Location: ' . $redirect, true, 302);
exit;

function wp_verify_error(string $message): void
{
    http_response_code(400);
    header('Content-Type: text/html; charset=utf-8');
    $safe = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo <<<HTML
<!doctype html><html lang="ja"><head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>ログインできませんでした — WordPics</title>
  <link rel="stylesheet" href="/styles.css" />
</head><body>
  <main style="padding:64px 20px;max-width:560px;margin:0 auto;text-align:center">
    <h1 style="font-size:22px;margin:0 0 12px">ログインできませんでした</h1>
    <p style="color:#5b6472">{$safe}</p>
    <p style="margin-top:24px"><a class="btn-primary" href="/submit.html">もう一度やり直す</a></p>
  </main>
</body></html>
HTML;
    exit;
}
