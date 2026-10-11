<?php

namespace App\Http\Middleware;

use App\Services\Ledger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [...parent::share($request),
            'auth' => ['user' => $request->user()?->only('id', 'name', 'email', 'phone', 'referral_code', 'status', 'email_verified_at', 'created_at'), 'mfa_enabled' => (bool) $request->user()?->two_factor_confirmed_at, 'wallet' => fn () => $request->user() ? app(Ledger::class)->wallet($request->user()->id) : null],
            'flash' => ['success' => fn () => $request->session()->get('success'), 'status' => fn () => $request->session()->get('status')],
            'routes' => fn () => collect(Route::getRoutes()->getRoutesByName())->filter(fn ($r) => in_array('GET', $r->methods()) && ! str_contains($r->uri(), '{'))->map(fn ($r) => '/'.ltrim($r->uri(), '/'))->all(),
        ];
    }
}
