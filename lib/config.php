<?php
declare(strict_types=1);

/**
 * 設定ロード。config.local.php から読み込み。
 * 環境変数 (putenv) でも上書き可能。
 */
function wp_config(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;

    $file = dirname(__DIR__) . '/config.local.php';
    if (!is_file($file)) {
        http_response_code(500);
        exit("config.local.php が見つかりません。config.local.php.example をコピーして作成してください。");
    }
    $cfg = require $file;
    if (!is_array($cfg)) {
        http_response_code(500);
        exit("config.local.php の形式が不正です。");
    }

    // 環境変数での上書き（デプロイ環境で便利）
    $cfg['db']['password']     = getenv('WP_DB_PASSWORD') ?: ($cfg['db']['password'] ?? '');
    $cfg['resend_api_key']     = getenv('WP_RESEND_API_KEY') ?: ($cfg['resend_api_key'] ?? '');
    $cfg['session_secret']     = getenv('WP_SESSION_SECRET') ?: ($cfg['session_secret'] ?? '');

    $cache = $cfg;
    return $cfg;
}

function wp_cfg(string $key, $default = null)
{
    $c = wp_config();
    if (strpos($key, '.') === false) return $c[$key] ?? $default;
    [$a, $b] = explode('.', $key, 2);
    return $c[$a][$b] ?? $default;
}
