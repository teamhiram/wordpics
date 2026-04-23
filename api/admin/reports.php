<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/bootstrap.php';

wp_require_method('GET');
wp_require_admin();

$status = (string)($_GET['status'] ?? 'open');
$allowed = ['open','resolved','dismissed','all'];
if (!in_array($status, $allowed, true)) $status = 'open';

$pdo = wp_db();
$sql = "SELECT r.id, r.submission_id, r.message, r.status,
               r.created_at, r.resolved_at,
               u.email AS reporter_email,
               s.citation_ja, s.book_abbr, s.chapter, s.verse,
               s.original_path, s.revised_path
          FROM reports r
     LEFT JOIN users u ON u.id = r.reporter_user_id
     LEFT JOIN submissions s ON s.id = r.submission_id";
if ($status !== 'all') {
    $sql .= " WHERE r.status = ?";
}
$sql .= " ORDER BY r.created_at DESC LIMIT 500";

$stmt = $pdo->prepare($sql);
if ($status !== 'all') {
    $stmt->execute([$status]);
} else {
    $stmt->execute();
}

$items = array_map(function ($r) {
    $file = $r['revised_path'] ?: $r['original_path'];
    return [
        'id'             => $r['id'],
        'submission_id'  => $r['submission_id'],
        'status'         => $r['status'],
        'message'        => $r['message'],
        'reporter_email' => $r['reporter_email'],
        'citationJa'     => $r['citation_ja'],
        'file'           => $file ? '/' . ltrim($file, '/') : null,
        'createdAt'      => $r['created_at'],
        'resolvedAt'     => $r['resolved_at'],
    ];
}, $stmt->fetchAll());

wp_json(['items' => $items]);
