<?php

namespace App\MarketData;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class Ingestor
{
    public array $counts = ['received' => 0, 'trades' => 0, 'duplicates' => 0, 'out_of_order' => 0, 'invalid' => 0, 'candles' => 0, 'tickers' => 0, 'latency_total_ms' => 0, 'latency_max_ms' => 0, 'exchange_age_total_ms' => 0, 'exchange_age_max_ms' => 0];

    public function ingest(array $event, array $timing = []): void
    {
        $this->counts['received']++;
        $kind = $event['kind'];
        $s = $event['symbol'];
        $this->counts['by_symbol'][$s] = ($this->counts['by_symbol'][$s] ?? 0) + 1;
        $latency = $event['received_ms'] - $event['event_ms'];
        $this->counts['latency_total_ms'] += $latency;
        $this->counts['latency_max_ms'] = max($this->counts['latency_max_ms'], $latency);
        $this->counts['exchange_age_total_ms'] += $latency;
        $this->counts['exchange_age_max_ms'] = max($this->counts['exchange_age_max_ms'], $latency);
        if ($kind === 'trade') {
            $row = $event;
            unset($row['kind']);
            $inserted = DB::table('market_price_observations')->insertOrIgnore($row);
            if (! $inserted) {
                $this->counts['duplicates']++;

                return;
            }$this->counts['trades']++;
            $last = Cache::get('market:price:'.$s);
            if (! $last || [$event['trade_ms'], $event['trade_id']] > [$last['trade_ms'], $last['trade_id']]) {
                Cache::put('market:price:'.$s, $event + ['timing'=>$timing + ['stored_ms'=>now()->getTimestampMs()]], config('market-data.display_ttl'));
            } else {
                $this->counts['out_of_order']++;
            }
        } elseif ($kind === 'ticker') {
            $old = Cache::get('market:ticker:'.$s);
            if (! $old || $old['event_ms'] < $event['event_ms']) {
                Cache::put('market:ticker:'.$s, $event, config('market-data.display_ttl'));
            }$this->counts['tickers']++;
        } elseif ($kind === 'candle') {
            $this->storeCandle($event['candle']);
            $old = Cache::get('market:current:'.$s);
            if (! $old || [$event['candle']['open_ms'], $event['candle']['updated_ms']] >= [$old['open_ms'], $old['updated_ms']]) {
                Cache::put('market:current:'.$s, $event['candle'] + ['timing'=>$timing + ['stored_ms'=>now()->getTimestampMs()]], 120);
            }
            $this->counts['candles']++;
        }
    }

    public function storeCandle(array $c): void
    {
        $key = array_intersect_key($c, array_flip(['provider', 'market_type', 'symbol', 'interval_seconds', 'open_ms']));
        DB::transaction(function () use ($key, $c) {
            $old = DB::table('market_candles')->where($key)->lockForUpdate()->first();
            if ($old && ($old->updated_ms > $c['updated_ms'] || ($old->closed && ! $c['closed']))) {
                return;
            }DB::table('market_candles')->updateOrInsert($key, $c);
        });
    }

    public function invalid(string $reason): void
    {
        $this->counts['invalid']++;
        if (Cache::add('market:invalid:logged', true, 60)) {
            DB::table('market_data_incidents')->insert(['provider' => config('market-data.provider'), 'type' => 'invalid_message', 'metadata' => json_encode(['reason' => substr($reason, 0, 300)]), 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
