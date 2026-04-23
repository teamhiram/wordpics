<?php
declare(strict_types=1);

/**
 * すべての API エンドポイントから最初に include する共通ブートストラップ。
 */

mb_internal_encoding('UTF-8');
date_default_timezone_set('UTC');

// エラーはログに記録、画面には出さない（JSON 前提のAPI）
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/util.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/email.php';

/** HTTP メソッドの強制 */
function wp_require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== strtoupper($method)) {
        header('Allow: ' . strtoupper($method));
        wp_bad('Method Not Allowed', 405);
    }
}

/** レート制限（メールアドレスごとの最近のトークン数） */
function wp_rate_recent_tokens_for_email(string $email): int
{
    $stmt = wp_db()->prepare(
        "SELECT COUNT(*) AS n FROM magic_tokens
         WHERE email = ? AND created_at > (UTC_TIMESTAMP() - INTERVAL 5 MINUTE)"
    );
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    return (int)($row['n'] ?? 0);
}
