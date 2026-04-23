<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/bootstrap.php';

wp_require_method('POST');
wp_require_admin();

$in = wp_read_json();
wp_check_csrf($in['csrf'] ?? null);

$root = realpath(__DIR__ . '/../..');
if ($root === false) wp_bad('root path error', 500);
$jsonPath = $root . '/data/pics.json';
$raw = @file_get_contents($jsonPath);
if ($raw === false) wp_bad('data/pics.json の読み込みに失敗しました', 500);
$pics = json_decode($raw, true);
if (!is_array($pics)) wp_bad('data/pics.json の形式が不正です', 500);

$pdo = wp_db();
$existingIds = [];
foreach ($pdo->query("SELECT id FROM submissions")->fetchAll() as $row) {
    $existingIds[(string)$row['id']] = true;
}

$inserted = 0;
$skipped = 0;

$stmt = $pdo->prepare(
    "INSERT INTO submissions (
        id, author_user_id, is_user_submission, status,
        original_path, revised_path, mime_type,
        book_abbr, chapter, verse, verse_text, citation_ja,
        size, orientation, tags, notes, rejection_reason,
        approved_at, approver_user_id, created_at
    ) VALUES (
        ?, NULL, 0, 'approved',
        ?, NULL, ?,
        ?, ?, ?, ?, ?,
        ?, ?, ?, NULL, NULL,
        ?, NULL, ?
    )"
);

foreach ($pics as $p) {
    if (!is_array($p)) continue;
    $id = trim((string)($p['id'] ?? ''));
    $file = ltrim((string)($p['file'] ?? ''), '/');
    if ($id === '' || $file === '') continue;

    // 公式画像のみ同期（uploads はユーザー投稿扱い）
    if (strpos($file, 'pics/') !== 0) {
        $skipped++;
        continue;
    }
    if (isset($existingIds[$id])) {
        $skipped++;
        continue;
    }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mime = $ext === 'png' ? 'image/png' : ($ext === 'jpg' || $ext === 'jpeg' ? 'image/jpeg' : 'image/png');

    $book = trim((string)($p['book'] ?? ''));
    $chapter = (int)($p['chapter'] ?? 0);
    $verse = trim((string)($p['verse'] ?? ''));
    $verseText = trim((string)($p['verseText'] ?? ''));
    $citationJa = trim((string)($p['citationJa'] ?? ''));
    $size = trim((string)($p['size'] ?? ''));
    $orientation = trim((string)($p['orientation'] ?? ''));
    $tags = $p['tags'] ?? [];
    if (!is_array($tags)) $tags = [];
    $tagsJson = json_encode(array_values($tags), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($tagsJson === false) $tagsJson = '[]';

    if ($book === '' || $chapter < 1 || $verse === '' || $verseText === '' || $citationJa === '') {
      $skipped++;
      continue;
    }

    $createdSql = wp_now_sql();
    if (!empty($p['createdAt'])) {
        try {
            $dt = new DateTimeImmutable((string)$p['createdAt']);
            $createdSql = $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            // fallback to now
        }
    }

    $stmt->execute([
        $id,
        $file,
        $mime,
        $book,
        $chapter,
        $verse,
        $verseText,
        $citationJa,
        $size,
        $orientation,
        $tagsJson,
        $createdSql,
        $createdSql,
    ]);
    $inserted++;
}

wp_json([
    'ok' => true,
    'inserted' => $inserted,
    'skipped' => $skipped,
]);
