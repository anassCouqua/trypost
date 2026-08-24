<?php

declare(strict_types=1);

namespace App\Http\Middleware\App;

use Closure;
use Illuminate\Http\Request;

class EnsureRegistrationEnabled
{
    public function handle(Request $request, Closure $next): mixed
    {
        // Self-hosted TryPost instances should allow the owner to create
        // their first account without requiring an invite.
        return $next($request);
    }
}
