<?php

ini_set('upload_max_filesize', '200M');

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
try {
    (require_once __DIR__.'/../bootstrap/app.php')->handleRequest(Request::capture());
} catch (Throwable $e) {
    if (str_contains($e->getMessage(), 'readonly database') || str_contains($e->getMessage(), 'open database')) {
        http_response_code(500);
        echo 'Database error: check permissions on database.sqlite';
        exit;
    }
}
