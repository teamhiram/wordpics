<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/bootstrap.php';

wp_require_method('POST');
$admin = wp_require_admin();

$isMultipart = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') === 0;
$in = $isMultipart ? $_POST : wp_read_json();

wp_check_csrf($in['csrf'] ?? null);

$id     = trim((string)($in['id']     ?? ''));
$action = trim((string)($in['action'] ?? ''));

if ($id === '')     wp_bad('id が必要です');
$allowedActions = ['approve', 'reject', 'revision', 'unpublish'];
if (!in_array($action, $allowedActions, true)) wp_bad('action が不正です');

$pdo = wp_db();
$stmt = $pdo->prepare('SELECT * FROM submissions WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$sub = $stmt->fetch();
if (!$sub) wp_bad('対象が見つかりません', 404);

$now = wp_now_sql();

switch ($action) {
    case 'approve':
        $pdo->prepare(
            "UPDATE submissions
                SET status = 'approved',
                    approved_at = UTC_TIMESTAMP(),
                    approver_user_id = ?,
                    rejection_reason = NULL
              WHERE id = ?"
        )->execute([$admin['id'], $id]);

        notifyAuthor($sub, 'approved');
        break;

    case 'reject':
        $reason = trim((string)($in['reason'] ?? ''));
        if (mb_strlen($reason) > 1000) wp_bad('却下理由が長すぎます');
        $pdo->prepare(
            "UPDATE submissions
                SET status = 'rejected',
                    rejection_reason = ?,
                    approver_user_id = ?
              WHERE id = ?"
        )->execute([$reason !== '' ? $reason : null, $admin['id'], $id]);

        notifyAuthor($sub, 'rejected', $reason);
        break;

    case 'unpublish':
        $pdo->prepare(
            "UPDATE submissions SET status = 'unpublished' WHERE id = ?"
        )->execute([$id]);
        break;

    case 'revision':
        if (empty($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
            wp_bad('改訂版の画像ファイルが添付されていません');
        }
        $f = $_FILES['image'];
        if (($f['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            wp_bad('アップロードに失敗しました (code=' . $f['error'] . ')');
        }
        $maxBytes = (int)wp_cfg('max_upload_bytes', 10 * 1024 * 1024);
        if ((int)$f['size'] > $maxBytes) wp_bad('ファイルサイズが大きすぎます');

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($f['tmp_name']);
        $ext = $mime === 'image/png' ? 'png' : ($mime === 'image/jpeg' ? 'jpg' : null);
        if (!$ext) wp_bad('PNG か JPG の画像のみアップロードできます');

        $dims = @getimagesize($f['tmp_name']);
        if (!$dims) wp_bad('画像が読み取れません');
        [$w, $h] = $dims;
        $orientation = $w === $h ? 'square' : ($w > $h ? 'landscape' : 'portrait');

        $uploadsRoot = realpath(__DIR__ . '/../..') . '/uploads';
        $targetDir = $uploadsRoot . '/submissions/' . $id;
        if (!is_dir($targetDir)) @mkdir($targetDir, 0755, true);

        $filename = wp_submission_filename(
            (string)$sub['book_abbr'],
            (int)$sub['chapter'],
            (string)$sub['verse'],
            'revised',
            $ext
        );
        $relativePath = 'uploads/submissions/' . $id . '/' . $filename;
        $absPath      = $targetDir . '/' . $filename;

        // 既存の改訂版ファイル（古い命名を含む）を削除
        foreach (glob($targetDir . '/*-revised.*') ?: [] as $old) {
            if ($old !== $absPath) @unlink($old);
        }
        foreach (['png','jpg'] as $e) {
            $oldLegacy = $targetDir . '/revised.' . $e;
            if (is_file($oldLegacy)) @unlink($oldLegacy);
        }

        if (!move_uploaded_file($f['tmp_name'], $absPath)) {
            wp_bad('改訂版の保存に失敗しました', 500);
        }
        @chmod($absPath, 0644);

        $pdo->prepare(
            "UPDATE submissions
                SET revised_path = ?,
                    orientation = ?,
                    status = 'approved',
                    approved_at = COALESCE(approved_at, UTC_TIMESTAMP()),
                    approver_user_id = ?,
                    rejection_reason = NULL
              WHERE id = ?"
        )->execute([$relativePath, $orientation, $admin['id'], $id]);

        notifyAuthor($sub, 'revised');
        break;
}

wp_json(['ok' => true]);

// ========== helpers ==========

function notifyAuthor(array $sub, string $kind, string $reason = ''): void
{
    $authorId = $sub['author_user_id'] ?? null;
    if (!$authorId) return;
    $stmt = wp_db()->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$authorId]);
    $row = $stmt->fetch();
    if (!$row) return;

    $site = wp_site_origin();
    $cit = $sub['citation_ja'];
    $subject = match ($kind) {
        'approved' => "【WordPics】投稿が承認されました — {$cit}",
        'rejected' => "【WordPics】投稿が却下されました — {$cit}",
        'revised'  => "【WordPics】管理者が改訂版を公開しました — {$cit}",
        default    => "【WordPics】投稿の状態が更新されました",
    };

    $body = match ($kind) {
        'approved' => "あなたの投稿（{$cit}）が承認され、公開されました。\n\nマイページ: {$site}/me.html",
        'rejected' => "あなたの投稿（{$cit}）は却下されました。\n\n理由:\n" . ($reason !== '' ? $reason : '(未記載)') . "\n\nマイページ: {$site}/me.html",
        'revised'  => "あなたの投稿（{$cit}）について、管理者が改訂版を公開しました。\n公開ギャラリーに反映されています。\n\nマイページ: {$site}/me.html",
        default    => "投稿の状態が更新されました。\n{$site}/me.html",
    };

    wp_send_email($row['email'], $subject, $body);
}
