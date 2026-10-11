<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::loginView(fn () => Inertia::render('Auth', ['mode' => 'login']));
        Fortify::registerView(fn () => Inertia::render('Auth', ['mode' => 'register']));
        Fortify::requestPasswordResetLinkView(fn () => Inertia::render('Auth', ['mode' => 'forgot-password']));
        Fortify::resetPasswordView(fn (Request $r) => Inertia::render('Auth', ['mode' => 'reset-password', 'token' => $r->route('token'), 'email' => $r->email]));
        Fortify::verifyEmailView(fn () => Inertia::render('Auth', ['mode' => 'verify-email']));
        Fortify::confirmPasswordView(fn () => Inertia::render('Auth', ['mode' => 'confirm-password']));
        Fortify::twoFactorChallengeView(fn () => Inertia::render('Auth', ['mode' => 'two-factor-challenge']));
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(5)->by(strtolower((string) $r->email).'|'.$r->ip()));
        RateLimiter::for('two-factor', fn (Request $r) => Limit::perMinute(5)->by($r->session()->get('login.id')));
    }
}
