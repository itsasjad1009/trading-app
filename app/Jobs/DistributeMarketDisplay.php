<?php

namespace App\Jobs;

use App\Events\MarketUpdated;
use App\MarketData\AccessGate;
use App\MarketData\Display;
use App\MarketData\FeedHealth;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

final class DistributeMarketDisplay implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 120;
    public int $queuedMs;

    public int $timeout = 20;

    public int $tries = 3;

    public function __construct(public array $symbols)
    {
        $this->queuedMs=now()->getTimestampMs();
        $this->onQueue('market-display');
    }

    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function handle(Display $display, FeedHealth $health): void
    {
        if (! app(AccessGate::class)->workerAllowed()) {
            return;
        }
        MarketUpdated::dispatch('ticker', ['markets' => $display->snapshot(),'distribution'=>['queued_ms'=>$this->queuedMs,'emit_started_ms'=>now()->getTimestampMs()]]);
        MarketUpdated::dispatch('health', $health->health());
        $candles = [];
        foreach ($this->symbols as $s) {
            if ($c = Cache::get('market:current:'.$s)) {
                $candles[] = $c;
            }
        }MarketUpdated::dispatch('candle', ['candles' => $candles,'distribution'=>['queued_ms'=>$this->queuedMs,'emit_started_ms'=>now()->getTimestampMs()]]);
        Cache::put('market:distribution', ['state' => 'CONNECTED', 'last_sent_ms' => now()->getTimestampMs()], 60);
    }

    public function failed(?\Throwable $e): void
    {
        Cache::put('market:distribution', ['state' => 'DISCONNECTED', 'last_failure_ms' => now()->getTimestampMs()], 300);
    }
}
