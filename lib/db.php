<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function wp_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $cfg = wp_config();
    $host    = $cfg['db']['host']    ?? '127.0.0.1';
    $name    = $cfg['db']['name']    ?? '';
    $user    = $cfg['db']['user']    ?? '';
    $pass    = $cfg['db']['password'] ?? '';
    $charset = $cfg['db']['charset'] ?? 'utf8mb4';

    $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}, time_zone = '+00:00'",
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        error_log('DB connect error: ' . $e->getMessage());
        // `config.local.php` に `'debug' => true` を入れると実際のエラー内容を出します。
        if (!empty($cfg['debug'])) {
            header('Content-Type: text/plain; charset=utf-8');
            exit("データベース接続に失敗しました。\n\n"
               . "host: {$host}\nname: {$name}\nuser: {$user}\n\n"
               . 'error: ' . $e->getMessage());
        }
        exit('データベース接続に失敗しました。');
    }
    return $pdo;
}
