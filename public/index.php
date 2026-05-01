<?php

ini_set('upload_max_filesize', '200M');

if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    http_response_code(500);
    echo "Application needs to be installed. Please run included setup script. Refer to Github";
    exit;
}

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';


(require_once __DIR__.'/../bootstrap/app.php')->handleRequest(Request::capture());
