<?php

namespace App\MarketData;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class Display
{
    public function snapshot(): array
    {
        return DB::table('trading_pairs')->where('enabled', true)->get()->map(function ($p) {
            $s = $p->exchange_symbol ?? '';
            $ticker = Cache::get('market:ticker:'.$s);
            $price = Cache::get('market:price:'.$s);

            return ['id' => $p->id, 'symbol' => $p->symbol, 'name' => $p->name, 'price' => $price['price'] ?? null, 'change' => $ticker['change'] ?? null, 'volume' => $ticker['quote_volume'] ?? null, 'timing' => $price['timing'] ?? null, 'observed_at' => isset($price['event_ms']) ? Carbon::createFromTimestampMs($price['event_ms'], 'UTC')->toIso8601String() : null, 'source' => config('market-data.provider'), 'market_type' => 'spot', 'health' => app(FeedHealth::class)->symbol($s)];
        })->all();
    }
}
