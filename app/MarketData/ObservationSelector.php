<?php

namespace App\MarketData;

use Illuminate\Support\Facades\DB;

final class ObservationSelector
{
    // Audit-only candidate policy; never called by the simulated trading engine.
    public function atExpiry(string $provider, string $symbol, int $expiryMs, int $maximumAgeMs = 1000): ?object
    {
        return DB::table('market_price_observations')->where('provider', $provider)->where('market_type', 'spot')->where('symbol', $symbol)->where('valid', true)->whereBetween('trade_ms', [$expiryMs - $maximumAgeMs, $expiryMs])->orderByDesc('trade_ms')->orderByDesc('trade_id')->first();
    }
}
