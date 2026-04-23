<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/** JSON レスポンスを返して終了 */
function wp_json($data, int $status = 200, array $headers = []): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    foreach ($headers as $k => $v) header("{$k}: {$v}");
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** エラー JSON */
function wp_bad(string $message, int $status = 400, array $extra = []): void
{
    wp_json(array_merge(['error' => $message], $extra), $status);
}

function wp_uuid(): string
{
    $b = random_bytes(16);
    $b[6] = chr(ord($b[6]) & 0x0f | 0x40);
    $b[8] = chr(ord($b[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

function wp_random_token(int $bytes = 32): string
{
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}

function wp_now_sql(): string
{
    return gmdate('Y-m-d H:i:s');
}

function wp_normalize_email(?string $email): string
{
    return strtolower(trim((string)$email));
}

function wp_is_valid_email(string $email): bool
{
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

function wp_is_admin_email(string $email): bool
{
    $list = preg_split('/[,\s]+/', (string)wp_cfg('admin_emails', ''), -1, PREG_SPLIT_NO_EMPTY);
    $email = wp_normalize_email($email);
    foreach ($list as $e) {
        if (wp_normalize_email($e) === $email) return true;
    }
    return false;
}

function wp_sha256_hex(string $input): string
{
    return hash('sha256', $input);
}

function wp_client_ip(): string
{
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) return $_SERVER['HTTP_CF_CONNECTING_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

function wp_esc(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** POST JSON を取得 */
function wp_read_json(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') return [];
    $j = json_decode($raw, true);
    return is_array($j) ? $j : [];
}

/** サイト origin */
function wp_site_origin(): string
{
    $o = (string)wp_cfg('site_origin', '');
    if ($o !== '') return rtrim($o, '/');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return "{$scheme}://{$host}";
}

/** 相対リダイレクト先の安全化 */
function wp_safe_redirect(?string $r): string
{
    $r = (string)$r;
    if ($r === '' || $r[0] !== '/' || (isset($r[1]) && $r[1] === '/')) return '/submit.html';
    return $r;
}

/**
 * 投稿用のファイル名を生成する。
 * 例: ('Jhn', 8, '12', 'original', 'png') -> 'Jhn-8-12-original.png'
 * 書名・章・節はサニタイズ済みの安全な文字列に変換される。
 */
function wp_submission_filename(string $bookAbbr, int $chapter, string $verse, string $suffix, string $ext): string
{
    $book = preg_replace('/[^A-Za-z0-9]/', '', $bookAbbr) ?: 'x';
    $ch   = max(0, $chapter);
    $v    = preg_replace('/[^A-Za-z0-9\-]/', '', $verse);
    if ($v === '' || $v === null) $v = '0';
    return "{$book}-{$ch}-{$v}-{$suffix}.{$ext}";
}
