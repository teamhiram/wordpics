<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/bootstrap.php';

wp_require_method('GET');

$user = wp_current_user();
if (!$user) {
    wp_json(['user' => null, 'csrf' => wp_csrf_token()]);
}

wp_json([
    'user' => [
        'id'           => $user['id'],
        'email'        => $user['email'],
        'display_name' => $user['display_name'] ?? null,
        'is_admin'     => (bool)$user['is_admin'],
    ],
    'csrf' => wp_csrf_token(),
]);
