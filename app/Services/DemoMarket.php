<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DemoMarket
{
    // Fixed public mathematical simulation, independent of users and positions. Price units = 1e-6 USDT.
    public function price(object $pair, int $timestamp): int
    {
        $phase = (crc32($pair->symbol) % 1000) / 100;
        $change = 0.012 * sin($timestamp / 270 + $phase) + 0.005 * sin($timestamp / 53 + $phase) + 0.002 * sin($timestamp / 13);

        return max(1, (int) round($pair->base_price_units * (1 + $change)));
    }

    public function quote(object $pair): array
    {
        $at = now()->timestamp;
        $price = $this->price($pair, $at);
        $previous = $this->price($pair, $at - 86400);

        return ['id' => $pair->id, 'symbol' => $pair->symbol, 'name' => $pair->name, 'price' => $price / 1000000, 'change' => round(($price - $previous) / $previous * 100, 2), 'volume' => round($pair->base_price_units / 100000000, 1), 'observed_at' => now()->toIso8601String()];
    }

    public function snapshot(): array
    {
        return DB::table('trading_pairs')->where('enabled', true)->get()->map(fn ($p) => $this->quote($p))->all();
    }

    public function candles(object $pair, int $interval): array
    {
        $end = intdiv(now()->timestamp, $interval) * $interval;
        $result = [];
        for ($i = 179; $i >= 0; $i--) {
            $t = $end - $i * $interval;
            $closeTime = min(now()->timestamp, $t + $interval - 1);
            $o = $this->price($pair, $t);
            $c = $this->price($pair, $closeTime);
            $m = $this->price($pair, min($closeTime, $t + intdiv($interval, 2)));
            $result[] = ['time' => $t, 'open' => $o / 1e6, 'high' => max($o, $c, $m) / 1e6, 'low' => min($o, $c, $m) / 1e6, 'close' => $c / 1e6, 'volume' => abs($c - $o) / 10000 + 10];
        }

        return $result;
    }
}
