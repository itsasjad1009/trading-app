<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('account.{id}', fn ($user, $id) => (int) $user->id === (int) $id && $user->status === 'active' && $user->hasVerifiedEmail());

foreach (['ticker','candle','health'] as $kind) {
 Broadcast::channel('market.local.'.$kind, fn($user) => $user->status === 'active' && $user->hasVerifiedEmail() && app(\App\MarketData\AccessGate::class)->allows(request()));
}
