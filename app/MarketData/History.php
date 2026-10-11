<?php

namespace App\MarketData;

use App\MarketData\Contracts\HistoricalCandleProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class History
{
    public function __construct(private HistoricalCandleProvider $provider, private Ingestor $ingestor) {}

    public function window(string $symbol, int $interval, int $count = 180): array
    {
        $end = intdiv(now()->getTimestampMs(), $interval * 1000) * $interval * 1000;
        $start = $end - ($count - 1) * $interval * 1000;

        return Cache::remember('market:history:'.$symbol.':'.$interval.':'.$end, config('market-data.history_ttl'), fn () => Cache::lock('market:backfill:'.$symbol.':'.$interval, 120)->block(5, fn () => $this->backfill($symbol, $interval, $start, $end)));
    }

    public function backfill(string $symbol, int $interval, int $start, int $end): array
    {
        $key = ['provider' => config('market-data.provider'), 'market_type' => 'spot', 'symbol' => $symbol, 'interval_seconds' => $interval];
        $rows = DB::table('market_candles')->where($key)->whereBetween('open_ms', [$start, $end])->orderBy('open_ms')->get()->map(fn ($r) => (array) $r)->all();
        $expected = intdiv($end - $start, $interval * 1000) + 1;
        if (count($rows) < $expected || ! $rows || ! end($rows)['closed']) {
            $incoming = $this->provider->candles($symbol, $interval, $start, $end);
            foreach ($incoming as $c) {
                $this->ingestor->storeCandle($c);
            }$rows = DB::table('market_candles')->where($key)->whereBetween('open_ms', [$start, $end])->orderBy('open_ms')->get()->map(fn ($r) => (array) $r)->all();
        }
        $missing = [];
        $found = array_column($rows, 'open_ms');
        for ($t = $start; $t <= $end; $t += $interval * 1000) {
            if (! in_array($t, $found)) {
                $missing[] = $t;
            }
        }
        if ($missing && Cache::add('market:gap:'.$symbol.':'.$interval.':'.$start, true, 300)) {
            DB::table('market_data_incidents')->insert(['provider' => config('market-data.provider'), 'symbol' => $symbol, 'type' => 'history_gap', 'metadata' => json_encode(['interval' => $interval, 'start_ms' => $start, 'end_ms' => $end, 'missing_count' => count($missing)]), 'created_at' => now(), 'updated_at' => now()]);
        }

        return ['candles' => array_map(fn ($r) => ['time' => intdiv($r['open_ms'], 1000), 'open' => $r['open'], 'high' => $r['high'], 'low' => $r['low'], 'close' => $r['close'], 'volume' => $r['volume']], $rows), 'missing_count' => count($missing)];
    }
}
