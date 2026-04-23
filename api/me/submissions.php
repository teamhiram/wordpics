<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/bootstrap.php';

wp_require_method('GET');
$user = wp_require_login();

$stmt = wp_db()->prepare(
    "SELECT id, status, original_path, revised_path, mime_type,
            book_abbr, chapter, verse, verse_text, citation_ja,
            size, orientation, tags, rejection_reason, created_at, approved_at
       FROM submissions
      WHERE author_user_id = ?
      ORDER BY created_at DESC"
);
$stmt->execute([$user['id']]);
$rows = $stmt->fetchAll();

$items = array_map(function ($r) {
    $displayPath = !empty($r['revised_path']) ? $r['revised_path'] : $r['original_path'];
    $tags = json_decode((string)$r['tags'], true);
    if (!is_array($tags)) $tags = [];
    return [
        'id'              => $r['id'],
        'status'          => $r['status'],
        'file'            => '/' . ltrim($displayPath, '/'),
        'original_file'   => '/' . ltrim($r['original_path'], '/'),
        'revised_file'    => $r['revised_path'] ? '/' . ltrim($r['revised_path'], '/') : null,
        'book'            => $r['book_abbr'],
        'chapter'         => (int)$r['chapter'],
        'verse'           => $r['verse'],
        'verseText'       => $r['verse_text'],
        'citationJa'      => $r['citation_ja'],
        'size'            => $r['size'],
        'orientation'     => $r['orientation'],
        'tags'            => $tags,
        'rejectionReason' => $r['rejection_reason'],
        'createdAt'       => $r['created_at'],
        'approvedAt'      => $r['approved_at'],
    ];
}, $rows);

wp_json(['submissions' => $items]);
