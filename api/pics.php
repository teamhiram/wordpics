<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';

wp_require_method('GET');

$stmt = wp_db()->query(
    "SELECT id, is_user_submission, original_path, revised_path,
            book_abbr, chapter, verse, verse_text, citation_ja,
            size, orientation, tags, author_user_id, approved_at, created_at
       FROM submissions
      WHERE status = 'approved'
      ORDER BY COALESCE(approved_at, created_at) DESC, created_at DESC"
);
$rows = $stmt->fetchAll();

$items = array_map(function ($r) {
    $displayPath = !empty($r['revised_path']) ? $r['revised_path'] : $r['original_path'];
    $tags = json_decode((string)$r['tags'], true);
    if (!is_array($tags)) $tags = [];
    return [
        'id'            => $r['id'],
        'file'          => '/' . ltrim($displayPath, '/'),
        'book'          => $r['book_abbr'],
        'chapter'       => (int)$r['chapter'],
        'verse'         => $r['verse'],
        'verseText'     => $r['verse_text'],
        'citationJa'    => $r['citation_ja'],
        'size'          => $r['size'],
        'orientation'   => $r['orientation'],
        'tags'          => $tags,
        'source'        => $r['is_user_submission'] ? 'user' : 'official',
    ];
}, $rows);

wp_json($items, 200, [
    'Cache-Control' => 'public, max-age=60',
]);
