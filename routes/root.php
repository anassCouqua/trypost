<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Keep the public entry point outside the authenticated application group.
// This prevents Render/browser requests to / from booting the auth/session
// middleware before a user has logged in.
Route::get('/', static function () {
    return redirect()->route('login');
});
