<?php

namespace App\Http\Middleware;

use App\Services\GuestIdentity;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

class GuestAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = app(GuestIdentity::class)->authenticate((string) $request->header('X-Guest-Id'), (string) $request->bearerToken());
        if (! $user) {
            throw new AuthenticationException;
        }
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
