<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';

wp_require_method('POST');

$in = wp_read_json();
$submissionId = trim((string)($in['submission_id'] ?? ''));
$message = trim((string)($in['message'] ?? ''));

if ($submissionId === '')  wp_bad('submission_id が必要です');
if ($message === '')       wp_bad('報告内容を入力してください');
if (mb_strlen($message) > 2000) wp_bad('報告内容は2000文字以内で入力してください');

// CSRF は匿名投稿も受け付けたいので、ここでは軽く:
//   - Referer が同一オリジンであること
//   - 1 IP あたり 1 分に 5 件まで
$origin = wp_site_origin();
$ref = $_SERVER['HTTP_REFERER'] ?? '';
if ($ref !== '' && strpos($ref, $origin) !== 0) {
    wp_bad('不正なリクエスト元です', 403);
}

$pdo = wp_db();
$ipHash = wp_sha256_hex(wp_client_ip() . ($_SERVER['HTTP_USER_AGENT'] ?? ''));

$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS n FROM reports
     WHERE reporter_ip_hash = ? AND created_at > (UTC_TIMESTAMP() - INTERVAL 1 MINUTE)"
);
$stmt->execute([$ipHash]);
if ((int)($stmt->fetch()['n'] ?? 0) >= 5) {
    wp_bad('短時間に多数の報告が送信されました。少し時間をおいてください。', 429);
}

$check = $pdo->prepare("SELECT id, citation_ja FROM submissions WHERE id = ? LIMIT 1");
$check->execute([$submissionId]);
$sub = $check->fetch();
if (!$sub) wp_bad('対象の画像が見つかりません', 404);

$user = wp_current_user();
$reportId = wp_uuid();

$pdo->prepare(
    'INSERT INTO reports (id, submission_id, reporter_user_id, reporter_ip_hash, message)
     VALUES (?, ?, ?, ?, ?)'
)->execute([
    $reportId,
    $submissionId,
    $user['id'] ?? null,
    $ipHash,
    $message,
]);

// 管理者に通知
$adminEmail = (string)wp_cfg('admin_notify_email', '');
if ($adminEmail !== '') {
    $subject = '【WordPics】誤字報告: ' . $sub['citation_ja'];
    $site = wp_site_origin();
    $reporter = $user['email'] ?? '(未ログイン)';
    $text = <<<TXT
WordPics に誤字報告が届きました。

対象: {$sub['citation_ja']} (ID: {$submissionId})
報告者: {$reporter}

--- 内容 ---
{$message}

管理画面:
{$site}/admin.html
TXT;
    wp_send_email($adminEmail, $subject, $text);
}

wp_json(['ok' => true, 'report_id' => $reportId]);
