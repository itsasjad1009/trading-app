<?php

namespace App\MarketData;

use Illuminate\Support\Facades\Cache;

final class WorkerLease
{
    public function __construct(private string $key = "market:leader") {}

    public function renew(string $owner): bool
    {
        return Cache::lock($this->key.':guard', 5)->block(2, function () use ($owner) {
            $current = Cache::get($this->key);
            if ($current && $current !== $owner) {
                return false;
            }Cache::put($this->key, $owner, 45);

            return true;
        });
    }

    public function release(string $owner): void
    {
        Cache::lock($this->key.':guard', 5)->block(2, function () use ($owner) {
            if (Cache::get($this->key) === $owner) {
                Cache::forget($this->key);
            }
        });
    }
}
