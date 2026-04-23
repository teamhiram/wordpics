<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/bootstrap.php';

wp_require_method('POST');
wp_require_admin();

$in = wp_read_json();
wp_check_csrf($in['csrf'] ?? null);

$id          = trim((string)($in['id'] ?? ''));
$bookAbbr    = trim((string)($in['book_abbr'] ?? ''));
$chapter     = (int)($in['chapter'] ?? 0);
$verse       = trim((string)($in['verse'] ?? ''));
$verseText   = trim((string)($in['verse_text'] ?? ''));
$citationJa  = trim((string)($in['citation_ja'] ?? ''));
$size        = trim((string)($in['size'] ?? ''));
$orientation = trim((string)($in['orientation'] ?? ''));
$tagsRaw     = (string)($in['tags'] ?? '');

if ($id === '') wp_bad('id が必要です');
if ($bookAbbr === '' || !preg_match('/^[A-Za-z0-9]{2,8}$/', $bookAbbr)) wp_bad('書略号が不正です');
if ($chapter < 1 || $chapter > 500) wp_bad('章が不正です');
if ($verse === '' || mb_strlen($verse) > 20) wp_bad('節が不正です');
if ($verseText === '' || mb_strlen($verseText) > 1000) wp_bad('御言本文が不正です');
if ($citationJa === '' || mb_strlen($citationJa) > 100) wp_bad('引用表記が不正です');

$allowedSizes = ['postcard', 'businesscard', 'square'];
$allowedOrientations = ['landscape', 'portrait', 'square'];
if (!in_array($size, $allowedSizes, true)) wp_bad('サイズが不正です');
if (!in_array($orientation, $allowedOrientations, true)) wp_bad('向きが不正です');

$tagList = array_values(array_filter(array_map(
    fn($t) => mb_substr(trim($t), 0, 20),
    preg_split('/[,、\s]+/u', $tagsRaw, -1, PREG_SPLIT_NO_EMPTY) ?: []
)));
if (count($tagList) > 20) wp_bad('タグは20個以内にしてください');

$pdo = wp_db();
$stmt = $pdo->prepare('SELECT * FROM submissions WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$sub = $stmt->fetch();
if (!$sub) wp_bad('対象が見つかりません', 404);

$pdo->prepare(
    "UPDATE submissions
        SET book_abbr = ?,
            chapter = ?,
            verse = ?,
            verse_text = ?,
            citation_ja = ?,
            size = ?,
            orientation = ?,
            tags = ?
      WHERE id = ?"
)->execute([
    $bookAbbr,
    $chapter,
    $verse,
    $verseText,
    $citationJa,
    $size,
    $orientation,
    json_encode($tagList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    $id,
]);

$stmt = $pdo->prepare('SELECT * FROM submissions WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$updated = $stmt->fetch();
if (!$updated) wp_bad('更新後のデータ取得に失敗しました', 500);

sync_pics_json($updated);

wp_json(['ok' => true]);

function sync_pics_json(array $sub): void
{
    $root = realpath(__DIR__ . '/../..');
    if ($root === false) wp_bad('ルートパスの解決に失敗しました', 500);

    $jsonPath = $root . '/data/pics.json';
    $raw = @file_get_contents($jsonPath);
    if ($raw === false) wp_bad('data/pics.json の読み込みに失敗しました', 500);

    $arr = json_decode($raw, true);
    if (!is_array($arr)) wp_bad('data/pics.json の形式が不正です', 500);

    $displayPath = !empty($sub['revised_path']) ? (string)$sub['revised_path'] : (string)$sub['original_path'];
    $normalizedFile = (strpos($displayPath, 'uploads/') === 0)
        ? '/' . ltrim($displayPath, '/')
        : ltrim($displayPath, '/');

    $tags = json_decode((string)$sub['tags'], true);
    if (!is_array($tags)) $tags = [];

    $createdAt = null;
    if (!empty($sub['created_at'])) {
        $dt = new DateTimeImmutable((string)$sub['created_at'], new DateTimeZone('UTC'));
        $createdAt = $dt->setTimezone(new DateTimeZone('Asia/Tokyo'))->format('Y-m-d\TH:i:sP');
    }

    $payload = [
        'id' => (string)$sub['id'],
        'file' => $normalizedFile,
        'book' => (string)$sub['book_abbr'],
        'chapter' => (int)$sub['chapter'],
        'verse' => (string)$sub['verse'],
        'verseText' => (string)$sub['verse_text'],
        'citationJa' => (string)$sub['citation_ja'],
        'size' => (string)$sub['size'],
        'orientation' => (string)$sub['orientation'],
        'tags' => $tags,
        'source' => !empty($sub['is_user_submission']) ? 'user' : 'official',
    ];
    if ($createdAt) $payload['createdAt'] = $createdAt;

    $updated = false;
    foreach ($arr as &$item) {
        if ((string)($item['id'] ?? '') !== (string)$sub['id']) continue;
        $item = array_merge($item, $payload);
        // 既存データの source がない場合は official を省略（旧形式互換）
        if (($item['source'] ?? '') === 'official' && empty($sub['is_user_submission'])) {
            unset($item['source']);
        }
        $updated = true;
        break;
    }
    unset($item);

    if (!$updated) {
        if (($payload['source'] ?? '') === 'official') unset($payload['source']);
        $arr[] = $payload;
    }

    $json = json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) wp_bad('data/pics.json のエンコードに失敗しました', 500);
    $json .= "\n";

    if (@file_put_contents($jsonPath, $json, LOCK_EX) === false) {
        wp_bad('data/pics.json の書き込みに失敗しました', 500);
    }
}
