<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$storagePath = sys_get_temp_dir().'/shimadzu';

foreach (['bootstrap', 'framework/cache/data', 'framework/cache/locks', 'framework/sessions', 'framework/views', 'logs'] as $directory) {
    $path = $storagePath.'/'.$directory;

    if (! is_dir($path) && ! mkdir($path, 0700, true) && ! is_dir($path)) {
        throw new RuntimeException('Unable to initialize temporary application storage.');
    }
}

foreach (['CONFIG', 'EVENTS', 'PACKAGES', 'ROUTES', 'SERVICES'] as $cache) {
    $variable = 'APP_'.$cache.'_CACHE';
    $value = $storagePath.'/bootstrap/'.strtolower($cache).'.php';
    $_ENV[$variable] = $_SERVER[$variable] = $value;
    putenv($variable.'='.$value);
}

require __DIR__.'/../vendor/autoload.php';

/** @var Application $app */
$app = require __DIR__.'/../bootstrap/app.php';
$app->addAbsoluteCachePathPrefix($storagePath);
$app->useStoragePath($storagePath);
$app->handleRequest(Request::capture());
