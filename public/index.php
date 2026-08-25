<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__ . '/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__ . '/../bootstrap/app.php';

if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] !== '/') {
    $parts = explode('?', $_SERVER['REQUEST_URI'], 2);
    if (str_ends_with($parts[0], '/')) {
        $parts[0] = rtrim($parts[0], '/');
        $_SERVER['REQUEST_URI'] = implode('?', $parts);
    }
}

$app->handleRequest(Request::capture());
