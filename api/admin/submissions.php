<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/bootstrap.php';

wp_require_method('GET');
wp_require_admin();

$status = (string)($_GET['status'] ?? 'pending');
$allowed = ['pending','approved','rejected','unpublished','all'];
if (!in_array($status, $allowed, true)) $status = 'pending';

$pdo = wp_db();
if ($status === 'all') {
    $sql = "SELECT s.*, u.email AS author_email
              FROM submissions s
         LEFT JOIN users u ON u.id = s.author_user_id
          ORDER BY s.created_at DESC
             LIMIT 500";
    $rows = $pdo->query($sql)->fetchAll();
} else {
    $sql = "SELECT s.*, u.email AS author_email
              FROM submissions s
         LEFT JOIN users u ON u.id = s.author_user_id
             WHERE s.status = ?
          ORDER BY s.created_at DESC
             LIMIT 500";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$status]);
    $rows = $stmt->fetchAll();
}

$items = array_map(function ($r) {
    $tags = json_decode((string)$r['tags'], true);
    if (!is_array($tags)) $tags = [];
    return [
        'id'               => $r['id'],
        'status'           => $r['status'],
        'is_user'          => (bool)$r['is_user_submission'],
        'author_email'     => $r['author_email'] ?? null,
        'original_file'    => '/' . ltrim($r['original_path'], '/'),
        'revised_file'     => $r['revised_path'] ? '/' . ltrim($r['revised_path'], '/') : null,
        'book'             => $r['book_abbr'],
        'chapter'          => (int)$r['chapter'],
        'verse'            => $r['verse'],
        'verseText'        => $r['verse_text'],
        'citationJa'       => $r['citation_ja'],
        'size'             => $r['size'],
        'orientation'      => $r['orientation'],
        'tags'             => $tags,
        'notes'            => $r['notes'],
        'rejectionReason'  => $r['rejection_reason'],
        'createdAt'        => $r['created_at'],
        'approvedAt'       => $r['approved_at'],
    ];
}, $rows);

$pending = (int)$pdo->query("SELECT COUNT(*) FROM submissions WHERE status = 'pending'")->fetchColumn();
$reports = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'open'")->fetchColumn();

wp_json([
    'items' => $items,
    'counts' => [
        'pending_submissions' => $pending,
        'open_reports'        => $reports,
    ],
]);
