<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\PlatformController;
use App\Http\Middleware\EnsureActiveAccount;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Home'))->name('home');
Route::get('/legal/{page}', fn (string $page) => Inertia::render('Legal', ['page' => $page]))->whereIn('page', ['privacy', 'terms', 'risk'])->name('legal');
Route::middleware(['auth', 'verified', EnsureActiveAccount::class])->group(function () {
    foreach (['dashboard', 'trade', 'positions', 'transactions', 'deposit', 'withdraw', 'kyc', 'support', 'profile', 'settings'] as $page) {
        Route::get('/'.$page, [PlatformController::class, 'page'])->name($page);
    }
    Route::get('/market/live/snapshot', [\App\Http\Controllers\MarketDataController::class, 'snapshot'])->middleware('throttle:60,1');
    Route::get('/market/live/candles', [\App\Http\Controllers\MarketDataController::class, 'candles'])->middleware('throttle:60,1');
    Route::get('/market/health', [\App\Http\Controllers\MarketDataController::class, 'health'])->middleware('throttle:30,1');
    Route::get('/market/candles', [PlatformController::class, 'candles'])->middleware('throttle:60,1')->name('market.candles');
    Route::post('/trade', [PlatformController::class, 'place'])->middleware('throttle:20,1')->name('trade.place');
    Route::post('/deposit', [AccountController::class, 'deposit'])->middleware('throttle:5,1')->name('deposit.store');
    Route::post('/withdraw', [AccountController::class, 'withdraw'])->middleware('throttle:5,1')->name('withdraw.store');
    Route::post('/kyc', [AccountController::class, 'kyc'])->middleware('throttle:3,1')->name('kyc.store');
    Route::get('/kyc/document/{id}', [AccountController::class, 'document'])->name('kyc.document');
    Route::post('/support', [AccountController::class, 'message'])->middleware('throttle:20,1')->name('support.store');
    Route::get('/support/attachment/{id}', [AccountController::class, 'attachment'])->name('support.attachment');
    Route::patch('/settings', [AccountController::class, 'settings'])->name('settings.update');
});
