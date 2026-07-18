<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$basePath = getenv('LARAVEL_BASE_PATH') ?: ($_SERVER['LARAVEL_BASE_PATH'] ?? '');

if ($basePath !== '') {
    $basePath = trim($basePath);

    if (! preg_match('/^(\/|[A-Za-z]:[\/\\\\])/', $basePath)) {
        $basePath = __DIR__.'/'.trim($basePath, '/\\');
    }
} elseif (is_file(__DIR__.'/../vendor/autoload.php')) {
    $basePath = __DIR__.'/..';
} else {
    $basePath = __DIR__.'/nebvsin';
}

/*
|--------------------------------------------------------------------------
| Check If The Application Is Under Maintenance
|--------------------------------------------------------------------------
*/

if (file_exists($maintenance = $basePath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
*/

require $basePath.'/vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
*/

$app = require_once $basePath.'/bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
