<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// php artisan serve does not copy Authorization into $_SERVER. Pub/Sub push
// auth is that header, so pull it from the raw request before Laravel reads it.
if (! isset($_SERVER['HTTP_AUTHORIZATION']) && function_exists('getallheaders')) {
    foreach (getallheaders() as $name => $value) {
        if (is_string($name) && strcasecmp($name, 'Authorization') === 0 && is_string($value)) {
            $_SERVER['HTTP_AUTHORIZATION'] = $value;
            break;
        }
    }
}

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
