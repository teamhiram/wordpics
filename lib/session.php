<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/util.php';

/**
 * PHP 標準の $_SESSION を使う。
 *   - セッションID は HttpOnly Cookie (SameSite=Lax)
 *   - ユーザー情報はログイン時にセッションへ保存し、getCurrentUser() で返す
 *   - CSRF トークンもセッション内に保持
 */
function wp_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('wp_sid');
    @session_start();

    // ログイン継続時に期限延長
    if (!empty($_SESSION['uid']) && empty($_SESSION['created_at'])) {
        $_SESSION['created_at'] = time();
    }
}

/** 現在のログインユーザー（配列 or null） */
function wp_current_user(): ?array
{
    wp_session_start();
    $uid = $_SESSION['uid'] ?? null;
    if (!$uid) return null;

    $row = wp_db()->prepare('SELECT id, email, display_name, is_admin FROM users WHERE id = ? LIMIT 1');
    $row->execute([$uid]);
    $u = $row->fetch();
    return $u ?: null;
}

function wp_require_login(): array
{
    $u = wp_current_user();
    if (!$u) wp_bad('ログインが必要です', 401);
    return $u;
}

function wp_require_admin(): array
{
    $u = wp_current_user();
    if (!$u || empty($u['is_admin'])) wp_bad('管理者権限が必要です', 403);
    return $u;
}

function wp_login(string $userId): void
{
    wp_session_start();
    session_regenerate_id(true);
    $_SESSION['uid']        = $userId;
    $_SESSION['created_at'] = time();
    // 新規 CSRF トークン
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

function wp_logout(): void
{
    wp_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function wp_csrf_token(): string
{
    wp_session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function wp_check_csrf(?string $token): void
{
    wp_session_start();
    $expected = $_SESSION['csrf'] ?? '';
    if (!$expected || !is_string($token) || !hash_equals($expected, $token)) {
        wp_bad('セッションが無効です。ページを再読み込みしてやり直してください。', 403);
    }
}
