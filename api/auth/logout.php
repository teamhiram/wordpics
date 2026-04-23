<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/bootstrap.php';

wp_require_method('POST');
wp_logout();
wp_json(['ok' => true]);
