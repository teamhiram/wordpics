<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';

wp_require_method('POST');

$user = wp_require_login();
wp_check_csrf($_POST['csrf'] ?? null);

$SIZES = ['postcard', 'businesscard', 'square'];

$bookAbbr   = trim((string)($_POST['book_abbr']   ?? ''));
$chapter    = (int)($_POST['chapter']    ?? 0);
$verse      = trim((string)($_POST['verse']      ?? ''));
$verseText  = trim((string)($_POST['verse_text'] ?? ''));
$citationJa = trim((string)($_POST['citation_ja'] ?? ''));
$size       = trim((string)($_POST['size']       ?? ''));
$tagsRaw    = (string)($_POST['tags']         ?? '');
$notes      = trim((string)($_POST['notes']     ?? ''));

if ($bookAbbr === '' || !preg_match('/^[A-Za-z0-9]{2,8}$/', $bookAbbr)) wp_bad('書名（略号）が不正です');
if ($chapter < 1 || $chapter > 500) wp_bad('章が不正です');
if ($verse === '' || mb_strlen($verse) > 20) wp_bad('節が不正です');
if ($verseText === '' || mb_strlen($verseText) > 1000) wp_bad('御言本文が不正です');
if ($citationJa === '' || mb_strlen($citationJa) > 100) wp_bad('引用表記が不正です');
if (!in_array($size, $SIZES, true)) wp_bad('サイズが不正です');
if (mb_strlen($notes) > 1000) wp_bad('メモが長すぎます');

$tagList = array_values(array_filter(array_map(
    fn($t) => mb_substr(trim($t), 0, 20),
    preg_split('/[,、\s]+/u', $tagsRaw, -1, PREG_SPLIT_NO_EMPTY) ?: []
)));
if (count($tagList) > 20) wp_bad('タグは20個以内にしてください');

if (empty($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
    wp_bad('画像が添付されていません');
}
$f = $_FILES['image'];
if (($f['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
    wp_bad('アップロード中にエラーが発生しました (code=' . $f['error'] . ')');
}

$maxBytes = (int)wp_cfg('max_upload_bytes', 10 * 1024 * 1024);
if ((int)$f['size'] > $maxBytes) {
    $mb = number_format($maxBytes / 1024 / 1024, 1);
    wp_bad("ファイルサイズが大きすぎます（最大 {$mb}MB）");
}

// MIME 判定
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($f['tmp_name']);
$ext = null;
if ($mime === 'image/png') $ext = 'png';
elseif ($mime === 'image/jpeg') $ext = 'jpg';
else wp_bad('PNG か JPG の画像のみアップロードできます');

$dims = @getimagesize($f['tmp_name']);
if (!$dims) wp_bad('画像が破損しているか読み取れません');
[$w, $h] = $dims;

if ($w < 300 || $h < 300) wp_bad('画像が小さすぎます（短辺 300px 以上にしてください）');
if ($w > 6000 || $h > 6000) wp_bad('画像が大きすぎます（長辺 6000px 以下にしてください）');

$orientation = $w === $h ? 'square' : ($w > $h ? 'landscape' : 'portrait');

$submissionId = wp_uuid();

$uploadsRoot = realpath(__DIR__ . '/..') . '/uploads';
$targetDir = $uploadsRoot . '/submissions/' . $submissionId;
if (!is_dir($targetDir)) {
    if (!@mkdir($targetDir, 0755, true)) {
        wp_bad('アップロード先ディレクトリを作成できませんでした', 500);
    }
}
$filename     = wp_submission_filename($bookAbbr, $chapter, $verse, 'original', $ext);
$originalPath = 'uploads/submissions/' . $submissionId . '/' . $filename;
$absolutePath = $targetDir . '/' . $filename;
if (!move_uploaded_file($f['tmp_name'], $absolutePath)) {
    wp_bad('ファイル保存に失敗しました', 500);
}
@chmod($absolutePath, 0644);

$pdo = wp_db();
$pdo->prepare(
    'INSERT INTO submissions
       (id, author_user_id, is_user_submission, status, original_path, mime_type,
        book_abbr, chapter, verse, verse_text, citation_ja, size, orientation, tags, notes)
     VALUES (?, ?, 1, "pending", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
)->execute([
    $submissionId,
    $user['id'],
    $originalPath,
    $mime,
    $bookAbbr,
    $chapter,
    $verse,
    $verseText,
    $citationJa,
    $size,
    $orientation,
    json_encode($tagList, JSON_UNESCAPED_UNICODE),
    $notes !== '' ? $notes : null,
]);

// 管理者通知
$adminEmail = (string)wp_cfg('admin_notify_email', '');
if ($adminEmail !== '') {
    $site = wp_site_origin();
    $subject = '【WordPics】新規投稿: ' . $citationJa;
    $tagsJoined = implode(', ', $tagList);
    $notesText = $notes !== '' ? "\nメモ:\n{$notes}\n" : '';
    $text = <<<TXT
WordPics に画像の投稿がありました。

投稿者: {$user['email']}
引用: {$citationJa}
御言: {$verseText}
サイズ/向き: {$size} / {$orientation}
タグ: {$tagsJoined}
{$notesText}
管理画面で承認・却下・改訂版アップロードができます:
{$site}/admin.html
TXT;
    wp_send_email($adminEmail, $subject, $text);
}

wp_json([
    'ok' => true,
    'submission' => [
        'id'          => $submissionId,
        'status'      => 'pending',
        'orientation' => $orientation,
        'size'        => $size,
    ],
]);
