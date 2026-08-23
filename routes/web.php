<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Lightweight public endpoint for Render health checks.
Route::get('/health', static function () {
    return response('OK', 200);
});

// Public entry point must be registered before the authenticated app routes.
require __DIR__.'/webhook.php';
require __DIR__.'/auth.php';
require __DIR__.'/root.php';
require __DIR__.'/app.php';
