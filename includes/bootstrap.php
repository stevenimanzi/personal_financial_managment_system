<?php

define('ROOT', dirname(__DIR__));

require ROOT . '/config/config.php';

date_default_timezone_set(APP_TIMEZONE);
mb_internal_encoding('UTF-8');

require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('imanzi_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

set_exception_handler(function (Throwable $e) {
    error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Something went wrong</title><style>body{font-family:system-ui,sans-serif;background:#f4f6f8;color:#0f1f2e;display:grid;place-items:center;min-height:100vh;margin:0;padding:24px}div{background:#fff;padding:40px;border-radius:16px;max-width:420px;text-align:center;box-shadow:0 10px 30px rgba(15,31,46,.08)}h1{font-size:1.25rem;margin:0 0 8px}p{color:#64748b;margin:0 0 20px}a{color:#0f766e;font-weight:600}</style></head><body><div><h1>Something went wrong</h1><p>The request could not be completed. Please try again.</p><a href="dashboard.php">Back to dashboard</a></div></body></html>';
    exit;
});
