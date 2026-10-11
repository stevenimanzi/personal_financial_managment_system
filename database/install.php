<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require __DIR__ . '/../config/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$link = new mysqli(DB_HOST, DB_USER, DB_PASS, '', DB_PORT);
$link->set_charset('utf8mb4');
$link->multi_query(file_get_contents(__DIR__ . '/schema.sql'));

do {
    if ($result = $link->store_result()) {
        $result->free();
    }
} while ($link->more_results() && $link->next_result());

$link->close();

echo "Database " . DB_NAME . " is ready.\n";
