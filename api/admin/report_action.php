<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/bootstrap.php';

wp_require_method('POST');
wp_require_admin();

$in = wp_read_json();
wp_check_csrf($in['csrf'] ?? null);

$id     = trim((string)($in['id']     ?? ''));
$action = trim((string)($in['action'] ?? ''));
if ($id === '') wp_bad('id が必要です');
if (!in_array($action, ['resolve','dismiss','reopen'], true)) wp_bad('action が不正です');

$newStatus = match ($action) {
    'resolve' => 'resolved',
    'dismiss' => 'dismissed',
    'reopen'  => 'open',
};

$resolvedAtSql = $action === 'reopen' ? 'NULL' : 'UTC_TIMESTAMP()';

$pdo = wp_db();
$stmt = $pdo->prepare(
    "UPDATE reports SET status = ?, resolved_at = {$resolvedAtSql} WHERE id = ?"
);
$stmt->execute([$newStatus, $id]);

wp_json(['ok' => true, 'status' => $newStatus]);
