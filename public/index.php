<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__ . '/../reservation-system-backend/storage/framework/maintenance.php')) {
    require $maintenance;
}

// // Register the Composer autoloader...
// require __DIR__ . '/../reservation-system-backend/vendor/autoload.php';

// // Bootstrap Laravel and handle the request...
// $app = require_once __DIR__ . '/../reservation-system-backend/bootstrap/app.php';

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$app->handleRequest(Request::capture());

