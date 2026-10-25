<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;

$public = realpath(__DIR__.'/../../public');
$path = realpath($public.'/'.ltrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '', '/'));
if ($path && is_file($path) && str_starts_with($path, $public.DIRECTORY_SEPARATOR)) {
    return false;
}

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->useStoragePath((string) getenv('LARAVEL_STORAGE_PATH'));
$app->make(Kernel::class)->bootstrap();
try {
    require __DIR__.'/cms-editor-target.php';
} catch (RuntimeException $exception) {
    http_response_code(503);
    echo $exception->getMessage();

    return;
}
$app->make(Vite::class)->useHotFile(getenv('CMS_PREVIEW_LIVE_ASSETS') === '1'
    ? base_path('public/hot') : storage_path('disabled-hot'));
$app->handleRequest(Request::capture());
