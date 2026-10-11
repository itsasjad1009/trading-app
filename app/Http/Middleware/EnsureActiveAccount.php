<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;

class EnsureActiveAccount
{
    public function handle(Request $request, \Closure $next)
    {
        abort_unless($request->user()?->status === 'active', 403, 'Account suspended. Contact support.');

        return $next($request);
    }
}
