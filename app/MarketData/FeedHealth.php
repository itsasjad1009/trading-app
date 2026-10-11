<?php

namespace App\MarketData;

use App\MarketData\Contracts\MarketDataHealthProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class FeedHealth implements MarketDataHealthProvider
{
    public function transition(string $state, string $reason = ''): void
    {
        $old = Cache::get('market:health', []);
        if (($old['state'] ?? null) !== $state) {
            DB::table('market_data_incidents')->insert(['provider' => config('market-data.provider'), 'type' => $state, 'metadata' => json_encode(['reason' => substr($reason, 0, 300)]), 'created_at' => now(), 'updated_at' => now()]);
        }Cache::put('market:health', array_merge($old, ['state' => $state, 'reason' => $reason, 'updated_ms' => now()->getTimestampMs()]), 86400);
    }

    public function beat(): void
    {
        Cache::put('market:heartbeat', now()->getTimestampMs(), 60);
    }

    public function health(): array
    {
        $h = Cache::get('market:health', ['state' => 'DISCONNECTED']);
        $h['heartbeat_ms'] = Cache::get('market:heartbeat');
        if (! $h['heartbeat_ms'] || now()->getTimestampMs() - $h['heartbeat_ms'] > config('market-data.stale_seconds') * 1000) {
            $h['state'] = 'DISCONNECTED';
        }$h['budget'] = Cache::get('market:budget', []);
        $h['clock'] = Cache::get('market:clock');
        $h['distribution'] = Cache::get('market:distribution', ['state' => 'DISCONNECTED']);

        return $h;
    }

    public function symbol(string $symbol): array
    {
        $ticker = Cache::get('market:ticker:'.$symbol);
        $price = Cache::get('market:price:'.$symbol);
        $h = $this->health();
        $age = $price ? now()->getTimestampMs() - $price['event_ms'] : null;
        $fresh = $age !== null && $age <= config('market-data.stale_seconds') * 1000 && $age >= -5000 && $ticker && now()->getTimestampMs() - $ticker['event_ms'] <= config('market-data.stale_seconds') * 1000;

        return ['state' => $h['state'] === 'LIVE' ? ($fresh ? 'LIVE' : 'STALE') : $h['state'], 'age_ms' => $age, 'last_update_ms' => $price['event_ms'] ?? null];
    }
}
