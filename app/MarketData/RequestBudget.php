<?php

namespace App\MarketData;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

final class RequestBudget
{
    public function request(int $weight, callable $send): Response
    {
        return Cache::lock('market:rest:serial', 30)->block(5, function () use ($weight, $send) {
            $now = now()->timestamp;
            $state = Cache::get('market:budget', []);
            if (($state['blocked_until'] ?? 0) > $now) {
                throw new RuntimeException('Provider backoff active');
            }
            $limits = Cache::get('market:limits', [['seconds' => 60, 'limit' => config('market-data.request_weight_limit')]]);
            foreach ($limits as $limit) {
                $window = intdiv($now, $limit['seconds']);
                $key = 'market:weight:'.$limit['seconds'].':'.$window;
                $used = (int) Cache::get($key, 0);
                $cap = min($limit['limit'], config('market-data.request_weight_limit') * max(1, $limit['seconds'] / 60));
                if ($used + $weight > floor($cap * config('market-data.safety_margin'))) {
                    throw new RuntimeException('Conservative request budget exhausted');
                }Cache::put($key, $used + $weight, $limit['seconds'] + 5);
            }
            $response = $send();
            $used = (int) $response->header('X-MBX-USED-WEIGHT-1M');
            $key = 'market:weight:60:'.intdiv($now, 60);
            Cache::put($key, max($used, (int) Cache::get($key, 0)), 65);
            $state = ['last_status' => $response->status(), 'used_weight_1m' => Cache::get($key), 'last_request_ms' => now()->getTimestampMs(), 'requests' => ($state['requests'] ?? 0) + 1, 'blocked_until' => 0];
            if (in_array($response->status(), [429, 418])) {
                $wait = max($response->status() === 418 ? 120 : 60, (int) $response->header('Retry-After'));
                $state['blocked_until'] = $now + $wait;
            } elseif ($response->serverError()) {
                $state['blocked_until'] = $now + 30;
            }
            Cache::put('market:budget', $state, 86400);
            $response->throw();

            return $response;
        });
    }

    public function metadata(array $data): void
    {
        $limits = [];
        foreach ($data['rateLimits'] ?? [] as $r) {
            if (($r['rateLimitType'] ?? null) !== 'REQUEST_WEIGHT') {
                continue;
            }$unit = ['SECOND' => 1, 'MINUTE' => 60, 'HOUR' => 3600, 'DAY' => 86400][$r['interval']] ?? null;
            if ($unit && ($r['limit'] ?? 0) > 0) {
                $limits[] = ['seconds' => $unit * (int) $r['intervalNum'], 'limit' => (int) $r['limit']];
            }
        }if ($limits) {
            Cache::put('market:limits', $limits, 86400);
        }
    }
}
